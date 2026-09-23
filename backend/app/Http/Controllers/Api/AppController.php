<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppMutationRequest;
use App\Models\Customer;
use App\Models\DeliveryReminder;
use App\Models\Employee;
use App\Models\Garment;
use App\Models\GarmentDesignOption;
use App\Models\GarmentDesignValue;
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

        $pipeline = [
            'measuring' => Order::where('status', 'measuring')->count(),
            'pending_assignment' => Order::where('status', 'pending_assignment')->count(),
            'in_progress' => Order::where('status', 'in_progress')->count(),
            'ready' => Order::where('status', 'ready')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
        ];

        $overdueCount = Order::where('promised_at', '<', now())
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->count();

        $receivablesMinor = Order::whereNotIn('status', ['delivered', 'cancelled'])
            ->whereRaw('total_minor > paid_minor')
            ->sum(DB::raw('total_minor - paid_minor'));

        $lowStockCount = InventoryItem::withSum('movements', 'quantity')
            ->get()
            ->filter(fn ($item) => ($item->movements_sum_quantity ?? 0) <= $item->reorder_level)
            ->count();

        return $this->ok([
            'metrics' => [
                'due_today' => Order::whereDate('promised_at', $day)->whereNotIn('status', ['delivered', 'cancelled'])->count(),
                'in_progress' => Order::where('status', 'in_progress')->count(),
                'ready' => Order::where('status', 'ready')->count(),
                'revenue_minor' => (int) Order::whereDate('created_at', $day)->sum('paid_minor'),
                'pipeline' => $pipeline,
                'overdue_count' => $overdueCount,
                'total_receivables_minor' => (int) $receivablesMinor,
                'craftsmen_count' => Employee::where('active', true)->count(),
                'low_stock_count' => $lowStockCount,
            ],
            'due_today_orders' => Order::with(['customer', 'garment', 'karigar'])
                ->where(function ($q) use ($day) {
                    $q->whereDate('promised_at', $day)
                      ->orWhere(fn ($sub) => $sub->where('promised_at', '<', now())->whereNotIn('status', ['delivered', 'cancelled']));
                })
                ->whereNotIn('status', ['delivered', 'cancelled'])
                ->orderBy('promised_at')
                ->limit(10)
                ->get()
                ->map(fn ($o) => [
                    'id' => $o->public_id,
                    'order_number' => $o->order_number,
                    'customer' => ['name' => $o->customer->name, 'mobile_number' => $o->customer->mobile_number],
                    'garment' => ['name' => $o->garment->name],
                    'karigar' => $o->karigar ? ['name' => $o->karigar->name] : null,
                    'status' => $o->status,
                    'promised_at' => $o->promised_at?->toIso8601String(),
                    'total_minor' => $o->total_minor,
                    'paid_minor' => $o->paid_minor,
                    'due_minor' => max(0, $o->total_minor - $o->paid_minor),
                ]),
            'recent_orders' => Order::with(['customer', 'garment', 'karigar'])->latest('id')->limit(8)->get()->map(fn ($o) => $this->order($o)),
            'onboarding' => [
                ['key' => 'customer', 'label' => 'Add your first customer', 'complete' => Customer::exists()],
                ['key' => 'inventory', 'label' => 'Add your first fabric or rental item', 'complete' => InventoryItem::exists()],
                ['key' => 'staff', 'label' => 'Invite a staff member', 'complete' => $tenant->users()->count() > 1],
                ['key' => 'order', 'label' => 'Create your first order', 'complete' => Order::exists()],
            ],
        ]);
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
        $perPage = min(200, max(10, $r->integer('per_page', 25)));
        $page = max(1, $r->integer('page', 1));
        $sortBy = $r->string('sort_by', 'id')->toString();
        $sortDir = strtolower($r->string('sort_dir', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';

        $base = Order::query()
            ->when($r->boolean('archived'), fn ($builder) => $builder->onlyTrashed());

        // Fast aggregated pipeline counts across the entire workshop database
        $statusCounts = (clone $base)
            ->selectRaw('status, count(*) as total_count')
            ->groupBy('status')
            ->pluck('total_count', 'status')
            ->all();

        $totalCount = (clone $base)->count();
        $dueCount = (clone $base)->whereRaw('total_minor > paid_minor')->count();
        $totalDueMinor = (clone $base)->whereRaw('total_minor > paid_minor')->sum(DB::raw('total_minor - paid_minor'));

        $q = (clone $base)
            ->with(['customer', 'garment', 'karigar', 'items.garment'])
            ->when($r->string('status')->toString(), fn ($b, $v) => $b->where('status', $v))
            ->when($r->boolean('due_only'), fn ($b) => $b->whereRaw('total_minor > paid_minor'))
            ->when($r->string('karigar_id')->toString(), function ($b, $karigarId) {
                $b->whereHas('karigar', fn ($k) => $k->where('public_id', $karigarId)->orWhere('id', $karigarId));
            })
            ->when($r->string('date_filter')->toString(), function ($b, $df) {
                $today = now()->toDateString();
                if ($df === 'today') {
                    $b->whereDate('promised_at', $today);
                } elseif ($df === 'overdue') {
                    $b->where('promised_at', '<', now())->whereNotIn('status', ['delivered', 'cancelled']);
                } elseif ($df === 'this_week') {
                    $b->whereBetween('promised_at', [now()->startOfWeek(), now()->endOfWeek()]);
                }
            })
            ->when($term, fn ($builder) => $builder->where(function ($search) use ($term) {
                $escaped = '%'.addcslashes($term, '%_').'%';
                $search->where('order_number', 'like', $escaped)
                    ->orWhereHas('customer', fn ($customers) => $customers->where('name', 'like', $escaped)->orWhere('mobile_number', 'like', $escaped));
            }));

        // Sort by requested column with index alignment
        if ($sortBy === 'promised_at') {
            $q->orderBy('promised_at', $sortDir)->orderBy('id', $sortDir);
        } elseif ($sortBy === 'total_minor') {
            $q->orderBy('total_minor', $sortDir)->orderBy('id', $sortDir);
        } elseif ($sortBy === 'due_minor') {
            $q->orderByRaw("(total_minor - paid_minor) {$sortDir}")->orderBy('id', $sortDir);
        } else {
            $q->orderBy('id', $sortDir);
        }

        // Support standard pagination or cursor pagination if requested
        if ($r->has('cursor')) {
            $paginator = $q->cursorPaginate($perPage);
            return $this->ok(['items' => collect($paginator->items())->map(fn ($o) => $this->order($o))], [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'previous_cursor' => $paginator->previousCursor()?->encode(),
                'per_page' => $paginator->perPage(),
                'pipeline_counts' => [
                    'measuring' => (int) ($statusCounts['measuring'] ?? 0),
                    'pending_assignment' => (int) ($statusCounts['pending_assignment'] ?? 0),
                    'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
                    'ready' => (int) ($statusCounts['ready'] ?? 0),
                    'delivered' => (int) ($statusCounts['delivered'] ?? 0),
                    'total' => (int) $totalCount,
                    'due_count' => (int) $dueCount,
                    'total_due_minor' => (int) $totalDueMinor,
                ],
            ]);
        }

        $paginator = $q->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => [
                'items' => collect($paginator->items())->map(fn ($o) => $this->order($o)),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more' => $paginator->hasMorePages(),
                'pipeline_counts' => [
                    'measuring' => (int) ($statusCounts['measuring'] ?? 0),
                    'pending_assignment' => (int) ($statusCounts['pending_assignment'] ?? 0),
                    'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
                    'ready' => (int) ($statusCounts['ready'] ?? 0),
                    'delivered' => (int) ($statusCounts['delivered'] ?? 0),
                    'total' => (int) $totalCount,
                    'due_count' => (int) $dueCount,
                    'total_due_minor' => (int) $totalDueMinor,
                ],
            ],
            'errors' => [],
        ]);
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
        return $this->ok(['garments' => Garment::with(['parts', 'designOptions.values'])->orderBy('display_order')->orderBy('name')->get()->map(fn ($garment) => $this->garment($garment))]);
    }

    public function saveGarment(AppMutationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $slug = Str::slug(($data['slug'] ?? null) ?: $data['name']);
        if (Garment::where('slug', $slug)->exists()) {
            return $this->validationError('slug', 'A garment with this slug already exists.');
        }

        $garment = DB::transaction(function () use ($data, $slug) {
            $garment = Garment::create([
                'name' => $data['name'],
                'slug' => $slug,
                'category' => $data['category'] ?? 'gents',
                'group_name' => $data['group_name'] ?? null,
                'base_making_minor' => $data['base_making_minor'] ?? 0,
                'master_rate_minor' => $data['master_rate_minor'] ?? 0,
                'karigar_rate_minor' => $data['karigar_rate_minor'] ?? 0,
                'description' => $data['description'] ?? null,
                'loose_allowances' => $data['loose_allowances'] ?? null,
                'display_order' => $data['display_order'] ?? ((Garment::max('display_order') ?? 0) + 1),
                'illustration_url' => $data['illustration_url'] ?? null,
                'active' => true,
            ]);
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

            return $garment->load(['parts', 'designOptions.values']);
        });

        return $this->ok(['garment' => $this->garment($garment)], [], 201);
    }

    public function updateGarment(AppMutationRequest $request, Garment $garment): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $garment->update($data);

        return $this->ok(['garment' => $this->garment($garment->load(['parts', 'designOptions.values']))]);
    }

    public function deleteGarment(Garment $garment): JsonResponse
    {
        $hasOrders = Order::where('garment_id', $garment->id)->exists()
            || DB::table('order_items')->where('garment_id', $garment->id)->exists();

        if ($hasOrders) {
            return response()->json([
                'data' => null,
                'meta' => (object) [],
                'errors' => [['code' => 'in_use', 'message' => 'এই পোশাকটি পূর্বের অর্ডারে ব্যবহৃত হয়েছে, তাই এটি ডিলিট করার বদলে "সাময়িক বন্ধ" (Inactive) করতে পারেন।']],
            ], 409);
        }

        DB::transaction(function () use ($garment) {
            $garment->parts()->delete();
            $garment->designOptions()->delete();
            $garment->delete();
        });

        return $this->ok(['deleted' => true]);
    }

    public function cloneGarment(Garment $garment): JsonResponse
    {
        $newGarment = DB::transaction(function () use ($garment) {
            $baseName = $garment->name.' (কপি)';
            $baseSlug = Str::slug($garment->slug.'-copy');
            $uniqueSlug = $baseSlug;
            $counter = 1;
            while (Garment::where('slug', $uniqueSlug)->exists()) {
                $uniqueSlug = $baseSlug.'-'.(++$counter);
            }

            $cloned = Garment::create([
                'name' => $baseName,
                'slug' => $uniqueSlug,
                'category' => $garment->category,
                'group_name' => $garment->group_name,
                'base_making_minor' => $garment->base_making_minor,
                'master_rate_minor' => $garment->master_rate_minor,
                'karigar_rate_minor' => $garment->karigar_rate_minor,
                'description' => $garment->description,
                'loose_allowances' => $garment->loose_allowances,
                'display_order' => ($garment->display_order ?? 0) + 1,
                'illustration_url' => $garment->illustration_url,
                'active' => true,
            ]);

            foreach ($garment->parts as $part) {
                $cloned->parts()->create([
                    'name' => $part->name,
                    'slug' => $part->slug,
                    'unit' => $part->unit,
                    'display_order' => $part->display_order,
                    'svg_asset_ref' => "/garments/{$uniqueSlug}/{$part->slug}.svg",
                    'required' => $part->required,
                ]);
            }

            foreach ($garment->designOptions()->with('values')->get() as $option) {
                $newOpt = $cloned->designOptions()->create([
                    'name' => $option->name,
                    'type' => $option->type,
                    'display_order' => $option->display_order,
                ]);
                foreach ($option->values as $val) {
                    $newOpt->values()->create([
                        'name' => $val->name,
                        'extra_price_minor' => $val->extra_price_minor,
                        'is_default' => $val->is_default,
                        'display_order' => $val->display_order,
                    ]);
                }
            }

            return $cloned->load(['parts', 'designOptions.values']);
        });

        return $this->ok(['garment' => $this->garment($newGarment)], [], 201);
    }

    public function reorderGarments(Request $request): JsonResponse
    {
        $ids = $request->input('garment_ids', []);
        DB::transaction(function () use ($ids) {
            foreach ($ids as $index => $publicId) {
                Garment::where('public_id', $publicId)->update(['display_order' => $index + 1]);
            }
        });

        return $this->garments();
    }

    public function copyGarmentDesign(Garment $garment, Request $request): JsonResponse
    {
        $sourceId = $request->input('source_garment_id');
        $source = Garment::where('public_id', $sourceId)->with('designOptions.values')->firstOrFail();

        DB::transaction(function () use ($garment, $source) {
            foreach ($source->designOptions as $opt) {
                $newOpt = $garment->designOptions()->create([
                    'name' => $opt->name,
                    'type' => $opt->type,
                    'display_order' => ($garment->designOptions()->max('display_order') ?? 0) + 1,
                ]);
                foreach ($opt->values as $val) {
                    $newOpt->values()->create([
                        'name' => $val->name,
                        'extra_price_minor' => $val->extra_price_minor,
                        'is_default' => $val->is_default,
                        'display_order' => $val->display_order,
                    ]);
                }
            }
        });

        return $this->ok(['garment' => $this->garment($garment->load(['parts', 'designOptions.values']))]);
    }

    public function saveGarmentDesignOption(AppMutationRequest $request, Garment $garment): JsonResponse
    {
        $data = $request->validated();
        $option = DB::transaction(function () use ($garment, $data) {
            $opt = $garment->designOptions()->create([
                'name' => $data['name'],
                'type' => $data['type'] ?? 'select',
                'display_order' => ($garment->designOptions()->max('display_order') ?? 0) + 1,
            ]);

            if (! empty($data['values']) && is_array($data['values'])) {
                foreach ($data['values'] as $idx => $v) {
                    $opt->values()->create([
                        'name' => $v['name'],
                        'extra_price_minor' => $v['extra_price_minor'] ?? 0,
                        'is_default' => $v['is_default'] ?? false,
                        'display_order' => $idx + 1,
                    ]);
                }
            }

            return $opt->load('values');
        });

        return $this->ok(['design_option' => $this->garmentDesignOption($option)], [], 201);
    }

    public function updateGarmentDesignOption(AppMutationRequest $request, Garment $garment, GarmentDesignOption $option): JsonResponse
    {
        abort_unless($option->garment_id === $garment->id, 404);
        $data = $request->validated();

        DB::transaction(function () use ($option, $data) {
            $option->update([
                'name' => $data['name'] ?? $option->name,
                'type' => $data['type'] ?? $option->type,
            ]);

            if (isset($data['values']) && is_array($data['values'])) {
                $option->values()->delete();
                foreach ($data['values'] as $idx => $v) {
                    $option->values()->create([
                        'name' => $v['name'],
                        'extra_price_minor' => $v['extra_price_minor'] ?? 0,
                        'is_default' => $v['is_default'] ?? false,
                        'display_order' => $idx + 1,
                    ]);
                }
            }
        });

        return $this->ok(['design_option' => $this->garmentDesignOption($option->fresh()->load('values'))]);
    }

    public function deleteGarmentDesignOption(Garment $garment, GarmentDesignOption $option): JsonResponse
    {
        abort_unless($option->garment_id === $garment->id, 404);
        $option->delete();

        return $this->ok(['deleted' => true]);
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

    public function updateGarmentPart(Request $request, Garment $garment, GarmentPart $part): JsonResponse
    {
        abort_unless($part->garment_id === $garment->id, 404);
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'unit' => 'sometimes|in:inch,cm',
            'required' => 'sometimes|boolean',
        ]);
        if (isset($data['name']) && $data['name'] !== $part->name) {
            $slug = Str::slug($data['name']);
            if ($garment->parts()->where('slug', $slug)->where('id', '!=', $part->id)->exists()) {
                return $this->validationError('name', 'That measurement part already exists.');
            }
            $data['slug'] = $slug;
        }
        $part->update($data);

        return $this->ok(['part' => $this->garmentPart($part->fresh())]);
    }

    public function deleteGarmentPart(Garment $garment, GarmentPart $part): JsonResponse
    {
        abort_unless($part->garment_id === $garment->id, 404);
        if ($part->measurements()->exists()) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'in_use', 'message' => 'This part is used by an order and cannot be deleted.']]], 409);
        }
        DB::transaction(function () use ($garment, $part) {
            $part->delete();
            $garment->parts()->update(['display_order' => DB::raw('display_order + 10000')]);
            $garment->parts()->orderBy('id')->get()->each(fn ($item, $index) => $item->update(['display_order' => $index + 1]));
        });

        return $this->ok(['deleted' => true]);
    }

    public function reorderGarmentParts(Request $request, Garment $garment): JsonResponse
    {
        $partIds = $request->input('part_ids', []);
        DB::transaction(function () use ($garment, $partIds) {
            $garment->parts()->update(['display_order' => DB::raw('display_order + 10000')]);
            foreach ($partIds as $index => $publicId) {
                $garment->parts()->where('public_id', $publicId)->update(['display_order' => $index + 1]);
            }
        });

        return $this->ok(['parts' => $garment->parts()->orderBy('display_order')->get()->map(fn ($p) => $this->garmentPart($p))]);
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
        return [
            'public_id' => $garment->public_id,
            'id' => $garment->public_id,
            'name' => $garment->name,
            'slug' => $garment->slug,
            'category' => $garment->category ?? 'gents',
            'group_name' => $garment->group_name,
            'base_making_minor' => (int) ($garment->base_making_minor ?? 0),
            'master_rate_minor' => (int) ($garment->master_rate_minor ?? 0),
            'karigar_rate_minor' => (int) ($garment->karigar_rate_minor ?? 0),
            'description' => $garment->description,
            'loose_allowances' => $garment->loose_allowances ?? [],
            'display_order' => (int) ($garment->display_order ?? 0),
            'illustration_url' => $garment->illustration_url,
            'active' => (bool) $garment->active,
            'parts' => $garment->parts->map(fn ($part) => $this->garmentPart($part)),
            'design_options' => $garment->relationLoaded('designOptions')
                ? $garment->designOptions->map(fn ($opt) => $this->garmentDesignOption($opt))
                : $garment->designOptions()->with('values')->get()->map(fn ($opt) => $this->garmentDesignOption($opt)),
        ];
    }

    private function garmentDesignOption(GarmentDesignOption $option): array
    {
        return [
            'id' => $option->public_id,
            'public_id' => $option->public_id,
            'name' => $option->name,
            'type' => $option->type,
            'display_order' => (int) $option->display_order,
            'values' => $option->values->map(fn ($val) => $this->garmentDesignValue($val)),
        ];
    }

    private function garmentDesignValue(GarmentDesignValue $val): array
    {
        return [
            'id' => $val->public_id,
            'public_id' => $val->public_id,
            'name' => $val->name,
            'extra_price_minor' => (int) $val->extra_price_minor,
            'is_default' => (bool) $val->is_default,
            'display_order' => (int) $val->display_order,
        ];
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
