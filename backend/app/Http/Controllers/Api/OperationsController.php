<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OperationsMutationRequest;
use App\Models\AttendanceRecord;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\PayoutBatch;
use App\Models\Purchase;
use App\Models\Rental;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\WorkEntry;
use App\Services\LedgerService;
use App\Services\OperationsService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OperationsController extends Controller
{
    public function __construct(private OperationsService $service, private LedgerService $ledger) {}

    public function inventory(): JsonResponse
    {
        return $this->ok(['items' => InventoryItem::withSum('movements', 'quantity')->orderBy('name')->get()->map(fn ($item) => $this->inventoryItem($item))]);
    }

    public function saveInventory(OperationsMutationRequest $r): JsonResponse
    {
        $d = $r->validated();

        return $this->ok(['item' => $this->inventoryItem(InventoryItem::create($d))], [], 201);
    }

    public function adjust(OperationsMutationRequest $r, InventoryItem $item): JsonResponse
    {
        $d = $r->validated();

        $movement = $this->service->adjust($item, $d['quantity'], $d['reason'], $r->user()->id);

        return $this->ok(['movement' => ['type' => $movement->type, 'quantity' => (float) $movement->quantity, 'reason' => $movement->reason, 'occurred_at' => $movement->occurred_at?->toIso8601String()]], [], 201);
    }

    public function purchase(OperationsMutationRequest $r): JsonResponse
    {
        $d = $r->validated();

        return $this->ok(['purchase' => $this->purchaseDto($this->service->receivePurchase($d, $r->user()->id)->load(['supplier', 'items.inventoryItem']))], [], 201);
    }

    public function purchases(): JsonResponse
    {
        $page = Purchase::with(['supplier', 'items.inventoryItem'])->latest('id')->cursorPaginate(20);

        return $this->ok(['items' => collect($page->items())->map(fn ($purchase) => $this->purchaseDto($purchase))], ['next_cursor' => $page->nextCursor()?->encode()]);
    }

    public function suppliers(): JsonResponse
    {
        return $this->ok(['items' => Supplier::orderBy('name')->get(['public_id', 'name', 'mobile_number', 'address'])]);
    }

    public function saveSupplier(OperationsMutationRequest $r): JsonResponse
    {
        $d = $r->validated();

        $supplier = Supplier::create($d);

        return $this->ok(['supplier' => ['public_id' => $supplier->public_id, 'name' => $supplier->name, 'mobile_number' => $supplier->mobile_number, 'address' => $supplier->address]], [], 201);
    }

    public function sale(OperationsMutationRequest $r): JsonResponse
    {
        $d = $r->validated();
        if (isset($d['customer_id'])) {
            $d['customer_db_id'] = Customer::where('public_id', $d['customer_id'])->firstOrFail()->id;
        }

        $sale = $this->service->completeSale($d, $r->user()->id)->load(['customer', 'items.inventoryItem']);

        return $this->ok(['sale' => $this->saleDto($sale)], [], 201);
    }

    public function sales(): JsonResponse
    {
        $page = Sale::with(['customer', 'items.inventoryItem'])->latest('id')->cursorPaginate(20);

        return $this->ok(['items' => collect($page->items())->map(fn ($sale) => $this->saleDto($sale))], ['next_cursor' => $page->nextCursor()?->encode()]);
    }

    public function expenses(): JsonResponse
    {
        $page = Expense::with(['category', 'attachments'])->latest('expense_date')->cursorPaginate(20);

        return $this->ok(['items' => collect($page->items())->map(fn ($expense) => $this->expenseDto($expense))], ['next_cursor' => $page->nextCursor()?->encode()]);
    }

    public function saveExpense(OperationsMutationRequest $r): JsonResponse
    {
        $d = $r->validated();
        $category = ExpenseCategory::firstOrCreate(['name' => $d['category']]);

        $expense = Expense::create(['expense_category_id' => $category->id, 'amount_minor' => $d['amount_minor'], 'note' => $d['note'] ?? null, 'expense_date' => $d['expense_date'], 'status' => 'pending', 'created_by' => $r->user()->id]);

        return $this->ok(['expense' => $this->expenseDto($expense->load(['category', 'attachments']))], [], 201);
    }

    public function uploadExpenseAttachment(OperationsMutationRequest $request, Expense $expense): JsonResponse
    {
        $file = $request->validated('attachment');
        $tenant = app(TenantContext::class)->get();
        $path = $file->store("tenants/{$tenant->public_id}/expenses/{$expense->public_id}", config('filesystems.default'));
        $attachment = $expense->attachments()->create(['original_name' => $file->getClientOriginalName(), 'storage_path' => $path, 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'uploaded_by' => $request->user()->id]);

        return $this->ok(['attachment' => $this->attachmentDto($attachment)], [], 201);
    }

    public function downloadExpenseAttachment(ExpenseAttachment $attachment)
    {
        $disk = Storage::disk(config('filesystems.default'));
        abort_unless($disk->exists($attachment->storage_path), 404);

        return $disk->download($attachment->storage_path, $attachment->original_name, ['Content-Type' => $attachment->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }

    public function approveExpense(Request $request, Expense $expense): JsonResponse
    {
        if ($expense->status === 'approved') {
            return $this->ok(['expense' => $this->expenseDto($expense->load(['category', 'attachments']))]);
        }
        DB::transaction(function () use ($request, $expense) {
            $expense->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $this->ledger->expense($expense, $expense->category->name, $expense->amount_minor, $request->user()->id);
        });

        return $this->ok(['expense' => $this->expenseDto($expense->fresh()->load(['category', 'attachments']))]);
    }

    public function attendance(OperationsMutationRequest $r): JsonResponse
    {
        $d = $r->validated();
        $d['employee_id'] = Employee::where('public_id', $d['employee_id'])->firstOrFail()->id;

        $attendance = AttendanceRecord::updateOrCreate(['employee_id' => $d['employee_id'], 'work_date' => $d['work_date']], $d)->load('employee');

        return $this->ok(['attendance' => $this->attendanceDto($attendance)], [], 201);
    }

    public function work(OperationsMutationRequest $r): JsonResponse
    {
        $d = $r->validated();
        $d['employee_id'] = Employee::where('public_id', $d['employee_id'])->firstOrFail()->id;
        if (! empty($d['order_id'])) {
            $d['order_id'] = Order::where('public_id', $d['order_id'])->firstOrFail()->id;
        }
        $d['amount_minor'] = (int) round($d['quantity'] * $d['rate_minor']);
        $d['completed_at'] = now();

        return $this->ok(['work_entry' => $this->workDto(WorkEntry::create($d)->load(['employee', 'order']))], [], 201);
    }

    public function rental(OperationsMutationRequest $r): JsonResponse
    {
        $d = $r->validated();
        $d['customer_db_id'] = Customer::where('public_id', $d['customer_id'])->firstOrFail()->id;

        return $this->ok(['rental' => $this->rentalDto($this->service->reserveRental($d)->load(['customer', 'items.inventoryItem']))], [], 201);
    }

    public function returnRental(OperationsMutationRequest $r, Rental $rental): JsonResponse
    {
        $d = $r->validated();

        return $this->ok(['rental' => $this->rentalDto($this->service->returnRental($rental, $d)->load(['customer', 'items.inventoryItem']))]);
    }

    public function rentals(): JsonResponse
    {
        $page = Rental::with(['customer', 'items.inventoryItem'])->latest('id')->cursorPaginate(20);

        return $this->ok(['items' => collect($page->items())->map(fn ($rental) => $this->rentalDto($rental))], ['next_cursor' => $page->nextCursor()?->encode()]);
    }

    public function workforce(): JsonResponse
    {
        return $this->ok(['attendance' => AttendanceRecord::with('employee')->latest('work_date')->limit(100)->get()->map(fn ($record) => $this->attendanceDto($record)), 'work_entries' => WorkEntry::with(['employee', 'order'])->latest('completed_at')->limit(100)->get()->map(fn ($entry) => $this->workDto($entry))]);
    }

    public function payouts(): JsonResponse
    {
        return $this->ok(['items' => PayoutBatch::with(['employee', 'items.workEntry'])->latest('paid_at')->limit(100)->get()->map(fn ($batch) => $this->payout($batch))]);
    }

    public function payoutBatch(OperationsMutationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $employee = Employee::where('public_id', $data['employee_id'])->firstOrFail();
        $batch = DB::transaction(function () use ($request, $data, $employee) {
            $entries = WorkEntry::where('employee_id', $employee->id)->where('status', 'unpaid')->whereBetween('completed_at', [$data['period_from'].' 00:00:00', $data['period_to'].' 23:59:59'])->lockForUpdate()->get();
            if ($entries->isEmpty()) {
                throw ValidationException::withMessages(['period' => 'No unpaid work entries exist in this period.']);
            }
            $batch = PayoutBatch::create(['batch_number' => 'PAY-'.now()->format('ymd').'-'.strtoupper(substr((string) Str::ulid(), -6)), 'employee_id' => $employee->id, 'period_from' => $data['period_from'], 'period_to' => $data['period_to'], 'total_minor' => $entries->sum('amount_minor'), 'payment_method' => $data['payment_method'], 'reference' => $data['reference'] ?? null, 'paid_by' => $request->user()->id, 'paid_at' => now()]);
            foreach ($entries as $entry) {
                $batch->items()->create(['work_entry_id' => $entry->id, 'amount_minor' => $entry->amount_minor]);
                $entry->update(['status' => 'paid']);
            }

            return $batch->load(['employee', 'items.workEntry']);
        });

        return $this->ok(['payout' => $this->payout($batch)], [], 201);
    }

    private function payout(PayoutBatch $batch): array
    {
        return ['id' => $batch->public_id, 'number' => $batch->batch_number, 'employee' => ['id' => $batch->employee->public_id, 'name' => $batch->employee->name], 'period_from' => $batch->period_from->toDateString(), 'period_to' => $batch->period_to->toDateString(), 'total_minor' => $batch->total_minor, 'payment_method' => $batch->payment_method, 'reference' => $batch->reference, 'paid_at' => $batch->paid_at->toIso8601String(), 'items' => $batch->items->map(fn ($item) => ['work_type' => $item->workEntry->work_type, 'quantity' => (float) $item->workEntry->quantity, 'rate_minor' => $item->workEntry->rate_minor, 'amount_minor' => $item->amount_minor])];
    }

    private function inventoryItem(InventoryItem $item): array
    {
        return ['public_id' => $item->public_id, 'sku' => $item->sku, 'name' => $item->name, 'unit' => $item->unit, 'reorder_level' => (float) $item->reorder_level, 'active' => $item->active, 'movements_sum_quantity' => (float) ($item->movements_sum_quantity ?? $item->balance)];
    }

    private function purchaseDto(Purchase $purchase): array
    {
        return ['id' => $purchase->public_id, 'number' => $purchase->purchase_number, 'status' => $purchase->status, 'supplier' => $purchase->supplier ? ['id' => $purchase->supplier->public_id, 'name' => $purchase->supplier->name] : null, 'total_minor' => $purchase->total_minor, 'received_at' => $purchase->received_at?->toIso8601String(), 'items' => $purchase->items->map(fn ($item) => ['inventory_item' => ['id' => $item->inventoryItem->public_id, 'name' => $item->inventoryItem->name], 'quantity' => (float) $item->quantity, 'unit_cost_minor' => $item->unit_cost_minor, 'total_minor' => $item->total_minor])];
    }

    private function saleDto(Sale $sale): array
    {
        return ['id' => $sale->public_id, 'number' => $sale->sale_number, 'type' => $sale->sale_type, 'status' => $sale->status, 'customer' => $sale->customer ? ['id' => $sale->customer->public_id, 'name' => $sale->customer->name] : null, 'subtotal_minor' => $sale->subtotal_minor, 'discount_minor' => $sale->discount_minor, 'total_minor' => $sale->total_minor, 'paid_minor' => $sale->paid_minor, 'items' => $sale->items->map(fn ($item) => ['inventory_item' => ['id' => $item->inventoryItem->public_id, 'name' => $item->inventoryItem->name], 'quantity' => (float) $item->quantity, 'unit_price_minor' => $item->unit_price_minor, 'total_minor' => $item->total_minor])];
    }

    private function expenseDto(Expense $expense): array
    {
        return ['id' => $expense->public_id, 'category' => $expense->category->name, 'amount_minor' => $expense->amount_minor, 'note' => $expense->note, 'expense_date' => $expense->expense_date->toDateString(), 'status' => $expense->status, 'approved_at' => $expense->approved_at?->toIso8601String(), 'attachments' => $expense->relationLoaded('attachments') ? $expense->attachments->map(fn ($attachment) => $this->attachmentDto($attachment)) : []];
    }

    private function attachmentDto(ExpenseAttachment $attachment): array
    {
        return ['id' => $attachment->public_id, 'name' => $attachment->original_name, 'mime_type' => $attachment->mime_type, 'size_bytes' => $attachment->size_bytes, 'download_path' => "/expense-attachments/{$attachment->public_id}"];
    }

    private function attendanceDto(AttendanceRecord $record): array
    {
        return ['employee' => ['id' => $record->employee->public_id, 'name' => $record->employee->name], 'work_date' => $record->work_date->toDateString(), 'status' => $record->status, 'checked_in_at' => $record->checked_in_at, 'checked_out_at' => $record->checked_out_at];
    }

    private function workDto(WorkEntry $entry): array
    {
        return ['employee' => ['id' => $entry->employee->public_id, 'name' => $entry->employee->name], 'order' => $entry->order ? ['id' => $entry->order->public_id, 'number' => $entry->order->order_number] : null, 'work_type' => $entry->work_type, 'quantity' => (float) $entry->quantity, 'rate_minor' => $entry->rate_minor, 'amount_minor' => $entry->amount_minor, 'status' => $entry->status, 'completed_at' => $entry->completed_at->toIso8601String()];
    }

    private function rentalDto(Rental $rental): array
    {
        return ['id' => $rental->public_id, 'number' => $rental->rental_number, 'status' => $rental->status, 'customer' => ['id' => $rental->customer->public_id, 'name' => $rental->customer->name], 'starts_on' => $rental->starts_on->toDateString(), 'due_on' => $rental->due_on->toDateString(), 'returned_on' => $rental->returned_on?->toDateString(), 'rent_minor' => $rental->rent_minor, 'deposit_minor' => $rental->deposit_minor, 'damage_charge_minor' => $rental->damage_charge_minor, 'deposit_refunded_minor' => $rental->deposit_refunded_minor, 'settlement_due_minor' => $rental->settlement_due_minor, 'settlement_note' => $rental->settlement_note, 'items' => $rental->items->map(fn ($item) => ['inventory_item' => ['id' => $item->inventoryItem->public_id, 'name' => $item->inventoryItem->name], 'quantity' => (float) $item->quantity, 'condition_out' => $item->condition_out, 'condition_in' => $item->condition_in])];
    }

    private function ok(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => (object) $meta, 'errors' => []], $status);
    }
}
