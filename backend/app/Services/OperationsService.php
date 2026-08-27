<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Purchase;
use App\Models\Rental;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OperationsService
{
    public function __construct(private LedgerService $ledger) {}

    public function adjust(InventoryItem $item, float $quantity, string $reason, ?int $userId): InventoryMovement
    {
        return DB::transaction(fn () => InventoryMovement::create(['inventory_item_id' => $item->id, 'type' => 'adjustment', 'quantity' => $quantity, 'reason' => $reason, 'created_by' => $userId, 'occurred_at' => now()]));
    }

    public function receivePurchase(array $data, ?int $userId): Purchase
    {
        return DB::transaction(function () use ($data, $userId) {
            $supplier = isset($data['supplier_id']) ? Supplier::where('public_id', $data['supplier_id'])->firstOrFail() : null;
            $purchase = Purchase::create(['purchase_number' => 'PUR-'.now()->format('ymd').'-'.strtoupper(substr((string) Str::ulid(), -6)), 'supplier_id' => $supplier?->id, 'status' => 'received', 'received_at' => now()]);
            $total = 0;
            foreach ($data['items'] as $row) {
                $item = InventoryItem::where('public_id', $row['inventory_item_id'])->lockForUpdate()->firstOrFail();
                $line = (int) round($row['quantity'] * $row['unit_cost_minor']);
                $purchase->items()->create(['inventory_item_id' => $item->id, 'quantity' => $row['quantity'], 'unit_cost_minor' => $row['unit_cost_minor'], 'total_minor' => $line]);
                InventoryMovement::create(['inventory_item_id' => $item->id, 'type' => 'purchase', 'quantity' => $row['quantity'], 'unit_cost_minor' => $row['unit_cost_minor'], 'reference_type' => Purchase::class, 'reference_id' => $purchase->id, 'created_by' => $userId, 'occurred_at' => now()]);
                $total += $line;
            }$purchase->update(['total_minor' => $total]);

            return $purchase->load(['supplier', 'items.inventoryItem']);
        });
    }

    public function completeSale(array $data, ?int $userId): Sale
    {
        return DB::transaction(function () use ($data, $userId) {
            $lines = [];
            $subtotal = 0;
            foreach ($data['items'] as $row) {
                $item = InventoryItem::where('public_id', $row['inventory_item_id'])->lockForUpdate()->firstOrFail();
                $balance = (float) $item->movements()->sum('quantity');
                if ($balance < (float) $row['quantity']) {
                    throw ValidationException::withMessages(['items' => "Insufficient stock for {$item->name}; available {$balance} {$item->unit}."]);
                }$line = (int) round($row['quantity'] * $row['unit_price_minor']);
                $lines[] = [$item, $row, $line];
                $subtotal += $line;
            }$discount = $data['discount_minor'] ?? 0;
            $total = max(0, $subtotal - $discount);
            $paid = min($data['paid_minor'] ?? 0, $total);
            $sale = Sale::create(['sale_number' => 'SAL-'.now()->format('ymd').'-'.strtoupper(substr((string) Str::ulid(), -6)), 'customer_id' => $data['customer_db_id'] ?? null, 'sale_type' => $data['sale_type'] ?? 'direct', 'status' => $paid === $total ? 'paid' : 'partial', 'subtotal_minor' => $subtotal, 'discount_minor' => $discount, 'total_minor' => $total, 'paid_minor' => $paid]);
            foreach ($lines as [$item,$row,$line]) {
                $sale->items()->create(['inventory_item_id' => $item->id, 'quantity' => $row['quantity'], 'unit_price_minor' => $row['unit_price_minor'], 'total_minor' => $line]);
                InventoryMovement::create(['inventory_item_id' => $item->id, 'type' => 'sale', 'quantity' => -$row['quantity'], 'reference_type' => Sale::class, 'reference_id' => $sale->id, 'created_by' => $userId, 'occurred_at' => now()]);
            }if ($paid > 0) {
                $sale->payments()->create(['amount_minor' => $paid, 'currency' => 'BDT', 'method' => $data['payment_method'] ?? 'cash', 'received_by' => $userId, 'received_at' => now()]);
            }$this->ledger->sale($sale->load('customer'), $data['payment_method'] ?? 'cash', $userId);

            return $sale->load(['customer', 'items.inventoryItem', 'payments']);
        });
    }

    public function reserveRental(array $data): Rental
    {
        return DB::transaction(function () use ($data) {
            $rental = Rental::create(['rental_number' => 'RNT-'.now()->format('ymd').'-'.strtoupper(substr((string) Str::ulid(), -6)), 'customer_id' => $data['customer_db_id'], 'status' => 'reserved', 'starts_on' => $data['starts_on'], 'due_on' => $data['due_on'], 'rent_minor' => $data['rent_minor'], 'deposit_minor' => $data['deposit_minor'] ?? 0]);
            foreach ($data['items'] as $row) {
                $item = InventoryItem::where('public_id', $row['inventory_item_id'])->lockForUpdate()->firstOrFail();
                $balance = (float) $item->movements()->sum('quantity');
                if ($balance < (float) $row['quantity']) {
                    throw ValidationException::withMessages(['items' => "Insufficient stock for {$item->name}."]);
                }$rental->items()->create(['inventory_item_id' => $item->id, 'quantity' => $row['quantity'], 'condition_out' => $row['condition_out'] ?? null]);
                InventoryMovement::create(['inventory_item_id' => $item->id, 'type' => 'rental_out', 'quantity' => -$row['quantity'], 'reference_type' => Rental::class, 'reference_id' => $rental->id, 'occurred_at' => now()]);
            }

            return $rental->load(['customer', 'items.inventoryItem']);
        });
    }

    public function returnRental(Rental $rental, array $settlement = []): Rental
    {
        return DB::transaction(function () use ($rental, $settlement) {
            if ($rental->status === 'returned') {
                return $rental;
            }
            $conditions = $settlement['conditions'] ?? [];
            $damageCharge = (int) ($settlement['damage_charge_minor'] ?? 0);
            $rental->load('items.inventoryItem');
            foreach ($rental->items as $item) {
                $item->update(['condition_in' => $conditions[$item->inventoryItem->public_id] ?? null]);
                InventoryMovement::create(['inventory_item_id' => $item->inventory_item_id, 'type' => 'rental_return', 'quantity' => $item->quantity, 'reference_type' => Rental::class, 'reference_id' => $rental->id, 'occurred_at' => now()]);
            }
            $rental->update(['status' => 'returned', 'returned_on' => now()->toDateString(), 'damage_charge_minor' => $damageCharge, 'deposit_refunded_minor' => max(0, $rental->deposit_minor - $damageCharge), 'settlement_due_minor' => max(0, $damageCharge - $rental->deposit_minor), 'settlement_note' => $settlement['settlement_note'] ?? null]);

            return $rental->fresh(['customer', 'items.inventoryItem']);
        });
    }
}
