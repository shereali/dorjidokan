<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppMutationRequest;
use App\Models\Customer;
use App\Models\DeliveryReminder;
use App\Models\Employee;
use App\Models\Garment;
use App\Models\GarmentPart;
use App\Models\InventoryItem;
use App\Models\LoyaltyPoint;
use App\Models\Order;
use App\Services\LedgerService;
use App\Services\LoyaltyService;
use App\Services\MobileNumber;
use App\Services\VoiceOrderService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppController extends Controller
{
    public function __construct(private VoiceOrderService $orders, private LedgerService $ledger) {}

    public function dashboard(): JsonResponse
    {
        $day = now()->startOfDay();
        $tenant = app(TenantContext::class)->get();

        return $this->ok(['metrics' => ['due_today' => Order::whereDate('promised_at', $day)->whereNotIn('status', ['delivered', 'cancelled'])->count(), 'in_progress' => Order::where('status', 'in_progress')->count(), 'ready' => Order::where('status', 'ready')->count(), 'revenue_minor' => Order::whereDate('created_at', $day)->sum('paid_minor')], 'due_today_orders' => Order::with(['customer', 'garment'])->whereDate('promised_at', $day)->whereNotIn('status', ['delivered', 'cancelled'])->orderBy('promised_at')->limit(10)->get()->map(fn ($o) => ['id' => $o->public_id, 'order_number' => $o->order_number, 'customer' => ['name' => $o->customer->name, 'mobile_number' => $o->customer->mobile_number], 'garment' => ['name' => $o->garment->name], 'status' => $o->status, 'promised_at' => $o->promised_at?->toIso8601String()]), 'recent_orders' => Order::with(['customer', 'garment', 'karigar'])->latest('id')->limit(8)->get()->map(fn ($o) => $this->order($o)), 'onboarding' => [
            ['key' => 'customer', 'label' => 'Add your first customer', 'complete' => Customer::exists()],
            ['key' => 'inventory', 'label' => 'Add your first fabric or rental item', 'complete' => InventoryItem::exists()],
            ['key' => 'staff', 'label' => 'Invite a staff member', 'complete' => $tenant->users()->count() > 1],
            ['key' => 'order', 'label' => 'Create your first order', 'complete' => Order::exists()],
        ]]);
    }

    public function customers(Request $r): JsonResponse
    {
        $term = $r->string('query')->toString();
        $q = Customer::query()
            ->when($r->boolean('archived'), fn ($builder) => $builder->onlyTrashed())
            ->when($term, fn ($builder) => $builder->where(function ($search) use ($term) {
                $escaped = '%'.addcslashes($term, '%_').'%';
                $search->where('name', 'like', $escaped)->orWhere('mobile_number', 'like', $escaped)
                    ->orWhereHas('orders', fn ($orders) => $orders->where('order_number', 'like', $escaped));
            }));

        return $this->page($q->latest('id')->cursorPaginate(20), fn ($customer) => $this->customer($customer));
    }

    public function saveCustomer(AppMutationRequest $r): JsonResponse
    {
        $d = $r->validated();
        try {
            $d['mobile_number'] = MobileNumber::normalize($d['mobile_number']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'validation', 'field' => 'mobile_number', 'message' => $e->getMessage()]]], 422);
        }$c = Customer::create([...$d, 'created_via' => 'manual']);

        return $this->ok(['customer' => $this->customer($c)], [], 201);
    }

    public function updateCustomer(AppMutationRequest $request, Customer $customer): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['mobile_number'])) {
            try {
                $data['mobile_number'] = MobileNumber::normalize($data['mobile_number']);
            } catch (\InvalidArgumentException $exception) {
                return $this->validationError('mobile_number', $exception->getMessage());
            }
        }
        $customer->update($data);

        return $this->ok(['customer' => $this->customer($customer->fresh())]);
    }

    public function archiveCustomer(Customer $customer): JsonResponse
    {
        $customer->delete();

        return $this->ok(['archived' => true]);
    }

    public function restoreCustomer(string $customer): JsonResponse
    {
        $record = Customer::onlyTrashed()->where('public_id', $customer)->firstOrFail();
        $record->restore();

        return $this->ok(['customer' => $this->customer($record->fresh())]);
    }

    public function orders(Request $r): JsonResponse
    {
        $term = $r->string('query')->toString();
        $q = Order::with(['customer', 'garment', 'karigar', 'items.garment'])
            ->when($r->boolean('archived'), fn ($builder) => $builder->onlyTrashed())
            ->when($r->string('status')->toString(), fn ($b, $v) => $b->where('status', $v))
            ->when($term, fn ($builder) => $builder->where(function ($search) use ($term) {
                $escaped = '%'.addcslashes($term, '%_').'%';
                $search->where('order_number', 'like', $escaped)
                    ->orWhereHas('customer', fn ($customers) => $customers->where('name', 'like', $escaped)->orWhere('mobile_number', 'like', $escaped));
            }));

        return $this->page($q->latest('id')->cursorPaginate(20), fn ($o) => $this->order($o));
    }

    public function showOrder(Order $order): JsonResponse
    {
        return $this->ok(['order' => $this->order($order->load(['customer', 'garment.parts', 'karigar', 'measurements.part', 'statusEvents', 'items.garment']))]);
    }

    public function saveOrder(AppMutationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $order = $this->orders->order($data, 'manual', 'user');
        $order->update(['promised_at' => $data['promised_at'] ?? null, 'total_minor' => $data['total_minor'], 'paid_minor' => $data['paid_minor']]);
        if (! empty($data['promised_at']) && $order->customer?->mobile_number) {
            DeliveryReminder::create(['order_id' => $order->id, 'customer_id' => $order->customer_id, 'channel' => 'sms', 'status' => 'scheduled', 'scheduled_at' => $data['promised_at']]);
        }
        if ($data['total_minor'] > 0) {
            $this->ledger->orderInvoice($order->load('customer'), $request->user()->id);
            app(LoyaltyService::class)->earnForOrder($order, $request->user()->id);
        }
        if ($data['paid_minor'] > 0) {
            $this->recordPayment($request, $order, $data['paid_minor'], 'cash', null);
            $this->ledger->customerPayment($order, $order->customer, $data['paid_minor'], 'cash', $request->user()->id);
        }

        return $this->ok(['order' => $this->order($order->load(['customer', 'garment.parts', 'measurements.part', 'karigar', 'statusEvents']))], [], 201);
    }

    public function saveOrderMeasurement(AppMutationRequest $request, Order $order): JsonResponse
    {
        $data = $request->validated();
        [$measurement,$missing] = $this->orders->measurement($order, $data, 'manual', 'user');

        return $this->ok(['measurement' => ['part_id' => $measurement->part->public_id, 'value' => (float) $measurement->value, 'unit' => $measurement->unit], 'missing_measurements' => $missing->map(fn ($part) => $part->public_id)->values()]);
    }

    public function assignOrder(AppMutationRequest $request, Order $order): JsonResponse
    {
        $data = $request->validated();
        $employee = $this->orders->assign($order, $data, 'user');

        return $this->ok(['karigar' => $this->employee($employee)]);
    }

    public function updateOrderStatus(AppMutationRequest $request, Order $order): JsonResponse
    {
        $data = $request->validated();
        $order = $this->orders->status($order, $data['status'], $data['note'] ?? null, 'user');

        return $this->ok(['order' => $this->order($order->load(['customer', 'garment.parts', 'measurements.part', 'karigar', 'statusEvents']))]);
    }

    public function payOrder(AppMutationRequest $request, Order $order): JsonResponse
    {
        $data = $request->validated();
        if ($data['amount_minor'] > $order->total_minor - $order->paid_minor) {
            return $this->validationError('amount_minor', 'Payment exceeds the outstanding balance.');
        }
        DB::transaction(function () use ($request, $order, $data) {
            $this->recordPayment($request, $order, $data['amount_minor'], $data['method'], $data['reference'] ?? null);
            $order->increment('paid_minor', $data['amount_minor']);
            $this->ledger->customerPayment($order, $order->customer, $data['amount_minor'], $data['method'], $request->user()->id);
        });

        return $this->ok(['order' => $this->order($order->fresh()->load(['customer', 'garment.parts', 'measurements.part', 'karigar', 'statusEvents']))]);
    }

    public function archiveOrder(Order $order): JsonResponse
    {
        abort_if(in_array($order->status, ['ready', 'delivered'], true), 409, 'Ready or delivered orders cannot be archived.');
        $order->delete();

        return $this->ok(['archived' => true]);
    }

    public function restoreOrder(string $order): JsonResponse
    {
        $archived = Order::onlyTrashed()->where('public_id', $order)->firstOrFail();
        $archived->restore();

        return $this->ok(['order' => $this->order($archived->load(['customer', 'garment', 'karigar']))]);
    }

    public function garments(): JsonResponse
    {
        return $this->ok(['garments' => Garment::with('parts')->orderBy('name')->get()->map(fn ($garment) => $this->garment($garment))]);
    }

    public function saveGarment(AppMutationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $slug = Str::slug(($data['slug'] ?? null) ?: $data['name']);
        if (Garment::where('slug', $slug)->exists()) {
            return $this->validationError('slug', 'A garment with this slug already exists.');
        }

        $garment = DB::transaction(function () use ($data, $slug) {
            $garment = Garment::create(['name' => $data['name'], 'slug' => $slug, 'active' => true]);
            foreach ($data['parts'] as $index => $part) {
                $garment->parts()->create([
                    'name' => $part['name'],
                    'slug' => Str::slug($part['name']),
                    'unit' => $part['unit'],
                    'display_order' => $index + 1,
                    'svg_asset_ref' => "/garments/{$slug}/".Str::slug($part['name']).'.svg',
                    'required' => $part['required'] ?? true,
                ]);
            }

            return $garment->load('parts');
        });

        return $this->ok(['garment' => $this->garment($garment)], [], 201);
    }

    public function updateGarment(AppMutationRequest $request, Garment $garment): JsonResponse
    {
        $data = $request->validated();
        $garment->update($data);

        return $this->ok(['garment' => $this->garment($garment->load('parts'))]);
    }

    public function saveGarmentPart(AppMutationRequest $request, Garment $garment): JsonResponse
    {
        $data = $request->validated();
        $slug = Str::slug($data['name']);
        if ($garment->parts()->where('slug', $slug)->exists()) {
            return $this->validationError('name', 'That measurement part already exists.');
        }
        $part = $garment->parts()->create([...$data, 'slug' => $slug, 'display_order' => ($garment->parts()->max('display_order') ?? 0) + 1, 'svg_asset_ref' => "/garments/{$garment->slug}/{$slug}.svg"]);

        return $this->ok(['part' => $this->garmentPart($part)], [], 201);
    }

    public function deleteGarmentPart(Garment $garment, GarmentPart $part): JsonResponse
    {
        abort_unless($part->garment_id === $garment->id, 404);
        if ($part->measurements()->exists()) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'in_use', 'message' => 'This part is used by an order and cannot be deleted.']]], 409);
        }
        DB::transaction(function () use ($garment, $part) {
            $part->delete();
            $garment->parts()->orderBy('display_order')->get()->each(fn ($item, $index) => $item->update(['display_order' => $index + 1]));
        });

        return $this->ok(['deleted' => true]);
    }

    public function employees(): JsonResponse
    {
        return $this->ok(['employees' => Employee::orderBy('name')->get()->map(fn ($employee) => $this->employee($employee))]);
    }

    public function loyaltyPoints(Customer $customer): JsonResponse
    {
        $account = app(LoyaltyService::class)->account($customer);

        return $this->ok(['account' => ['balance' => $account->balance, 'total_earned' => $account->total_earned, 'total_redeemed' => $account->total_redeemed], 'points' => LoyaltyPoint::where('customer_id', $customer->id)->latest('id')->limit(50)->get()->map(fn ($point) => ['id' => $point->public_id, 'type' => $point->type, 'points' => $point->points, 'reason' => $point->reason, 'created_at' => $point->created_at?->toIso8601String()])]);
    }

    public function redeemLoyalty(AppMutationRequest $request, Customer $customer): JsonResponse
    {
        $data = $request->validated();
        $service = app(LoyaltyService::class);
        $points = (int) $data['points'];
        try {
            $point = $service->redeem($customer, $points, $data['reason'] ?? 'Points redeemed', null, $request->user()->id);
        } catch (ValidationException $exception) {
            return $this->validationError('points', $exception->getMessage());
        }

        return $this->ok(['point' => ['id' => $point->public_id, 'type' => 'redeem', 'points' => $point->points, 'reason' => $point->reason], 'credit_minor' => $service->pointsToCurrencyMinor($points), 'balance' => $service->account($customer)->balance]);
    }

    public function barcode(Order $order): JsonResponse
    {
        return $this->ok(['order' => ['order_number' => $order->order_number, 'barcode' => $order->order_number, 'status' => $order->status, 'customer' => $order->customer ? ['name' => $order->customer->name, 'mobile_number' => $order->customer->mobile_number] : null], 'barcode_spec' => ['type' => 'code128', 'text' => $order->order_number, 'print_url' => '/barcode/'.$order->order_number.'/print']]);
    }

    public function saveEmployee(AppMutationRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['mobile_number'])) {
            try {
                $data['mobile_number'] = MobileNumber::normalize($data['mobile_number']);
            } catch (\InvalidArgumentException $exception) {
                return $this->validationError('mobile_number', $exception->getMessage());
            }
        }
        $employee = Employee::create([...$data, 'active' => true]);

        return $this->ok(['employee' => $this->employee($employee)], [], 201);
    }

    public function updateEmployee(AppMutationRequest $request, Employee $employee): JsonResponse
    {
        $data = $request->validated();
        if (array_key_exists('mobile_number', $data) && $data['mobile_number']) {
            try {
                $data['mobile_number'] = MobileNumber::normalize($data['mobile_number']);
            } catch (\InvalidArgumentException $exception) {
                return $this->validationError('mobile_number', $exception->getMessage());
            }
        }
        $employee->update($data);

        return $this->ok(['employee' => $this->employee($employee)]);
    }

    private function order($o): array
    {
        $data = ['id' => $o->public_id, 'number' => $o->order_number, 'order_number' => $o->order_number, 'barcode' => $o->order_number, 'status' => $o->status, 'archived_at' => $o->deleted_at?->toIso8601String(), 'customer' => ['id' => $o->customer->public_id, 'name' => $o->customer->name, 'mobile_number' => $o->customer->mobile_number], 'garment' => ['id' => $o->garment->public_id, 'name' => $o->garment->name], 'karigar' => $o->karigar ? ['id' => $o->karigar->public_id, 'name' => $o->karigar->name] : null, 'promised_at' => $o->promised_at?->toIso8601String(), 'total_minor' => $o->total_minor, 'paid_minor' => $o->paid_minor];
        if ($o->garment->relationLoaded('parts')) {
            $data['garment']['parts'] = $o->garment->parts->map(fn ($part) => ['id' => $part->public_id, 'name' => $part->name, 'unit' => $part->unit, 'required' => $part->required, 'display_order' => $part->display_order, 'svg_asset_ref' => $part->svg_asset_ref]);
        }
        if ($o->relationLoaded('measurements')) {
            $data['measurements'] = $o->measurements->map(fn ($measurement) => ['part_id' => $measurement->part->public_id, 'value' => (float) $measurement->value, 'unit' => $measurement->unit]);
        }
        if ($o->relationLoaded('statusEvents')) {
            $data['timeline'] = $o->statusEvents->map(fn ($event) => ['from_status' => $event->from_status, 'status' => $event->status, 'note' => $event->note, 'created_at' => $event->created_at?->toIso8601String()]);
        }
        if ($o->relationLoaded('items')) {
            $data['items'] = $o->items->map(fn ($item) => ['id' => $item->public_id, 'garment' => ['id' => $item->garment->public_id, 'name' => $item->garment->name], 'quantity' => (float) $item->quantity, 'making_cost_minor' => $item->making_cost_minor, 'design_cost_minor' => $item->design_cost_minor, 'group_name' => $item->group_name]);
        }

        return $data;
    }

    private function employee(Employee $employee): array
    {
        return ['id' => $employee->public_id, 'name' => $employee->name, 'mobile_number' => $employee->mobile_number, 'employee_type' => $employee->employee_type, 'active' => $employee->active];
    }

    private function garment(Garment $garment): array
    {
        return ['public_id' => $garment->public_id, 'id' => $garment->public_id, 'name' => $garment->name, 'slug' => $garment->slug, 'active' => $garment->active, 'parts' => $garment->parts->map(fn ($part) => $this->garmentPart($part))];
    }

    private function garmentPart(GarmentPart $part): array
    {
        return ['public_id' => $part->public_id, 'id' => $part->public_id, 'name' => $part->name, 'slug' => $part->slug, 'unit' => $part->unit, 'display_order' => $part->display_order, 'svg_asset_ref' => $part->svg_asset_ref, 'required' => $part->required];
    }

    private function customer(Customer $customer): array
    {
        return ['id' => $customer->public_id, 'public_id' => $customer->public_id, 'name' => $customer->name, 'mobile_number' => $customer->mobile_number, 'address' => $customer->address, 'marketing_consent' => $customer->marketing_consent];
    }

    private function validationError(string $field, string $message): JsonResponse
    {
        return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'validation', 'field' => $field, 'message' => $message]]], 422);
    }

    private function recordPayment(Request $request, Order $order, int $amount, string $method, ?string $reference): void
    {
        DB::table('payments')->insert(['tenant_id' => $order->tenant_id, 'payable_type' => Order::class, 'payable_id' => $order->id, 'amount_minor' => $amount, 'currency' => app(TenantContext::class)->get()->currency, 'method' => $method, 'reference' => $reference, 'received_by' => $request->user()->id, 'received_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function ok(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => (object) $meta, 'errors' => []], $status);
    }

    private function page($p, ?callable $map = null): JsonResponse
    {
        $items = collect($p->items());
        if ($map) {
            $items = $items->map($map);
        }

        return $this->ok(['items' => $items], ['next_cursor' => $p->nextCursor()?->encode(), 'previous_cursor' => $p->previousCursor()?->encode()]);
    }
}
