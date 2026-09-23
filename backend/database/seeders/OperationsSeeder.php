<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkEntry;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OperationsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'heritage-tailors')->firstOrFail();
        app(TenantContext::class)->set($tenant);

        $adminUser = User::where('email', 'admin@tailors.test')->first();
        $suppliers = Supplier::where('tenant_id', $tenant->id)->get()->keyBy('name');
        $customers = Customer::where('tenant_id', $tenant->id)->get()->keyBy('mobile_number');
        $employees = Employee::where('tenant_id', $tenant->id)->get()->keyBy('name');
        $wool = InventoryItem::where('tenant_id', $tenant->id)->where('sku', 'FAB-IW-001')->first();
        $cotton = InventoryItem::where('tenant_id', $tenant->id)->where('sku', 'FAB-GC-003')->first();

        // 1. Stock Purchase
        if ($wool && isset($suppliers['Raymond Fabric Importers & Co.'])) {
            $purchase = Purchase::firstOrCreate(
                ['tenant_id' => $tenant->id, 'purchase_number' => 'PO-2026-001'],
                [
                    'public_id' => (string) Str::ulid(),
                    'supplier_id' => $suppliers['Raymond Fabric Importers & Co.']->id,
                    'status' => 'received',
                    'total_minor' => 3600000,
                    'received_at' => now()->subDays(10),
                ]
            );

            if ($purchase->wasRecentlyCreated) {
                PurchaseItem::create([
                    'tenant_id' => $tenant->id,
                    'purchase_id' => $purchase->id,
                    'inventory_item_id' => $wool->id,
                    'quantity' => 20,
                    'unit_cost_minor' => 180000,
                    'total_minor' => 3600000,
                ]);

                InventoryMovement::create([
                    'tenant_id' => $tenant->id,
                    'inventory_item_id' => $wool->id,
                    'type' => 'in',
                    'quantity' => 20,
                    'unit_cost_minor' => 180000,
                    'reason' => 'Purchase PO-2026-001',
                    'occurred_at' => now()->subDays(10),
                ]);
            }
        }

        // 2. Retail Fabric Sale (POS)
        if ($cotton && isset($customers['01711000101'])) {
            $sale = Sale::firstOrCreate(
                ['tenant_id' => $tenant->id, 'sale_number' => 'POS-2026-001'],
                [
                    'public_id' => (string) Str::ulid(),
                    'customer_id' => $customers['01711000101']->id,
                    'sale_type' => 'fabric',
                    'status' => 'completed',
                    'subtotal_minor' => 625000,
                    'discount_minor' => 0,
                    'total_minor' => 625000,
                    'paid_minor' => 625000,
                ]
            );

            if ($sale->wasRecentlyCreated) {
                SaleItem::create([
                    'tenant_id' => $tenant->id,
                    'sale_id' => $sale->id,
                    'inventory_item_id' => $cotton->id,
                    'quantity' => 5,
                    'unit_price_minor' => 125000,
                    'total_minor' => 625000,
                ]);

                InventoryMovement::create([
                    'tenant_id' => $tenant->id,
                    'inventory_item_id' => $cotton->id,
                    'type' => 'out',
                    'quantity' => -5,
                    'unit_cost_minor' => 65000,
                    'reason' => 'POS Sale POS-2026-001',
                    'occurred_at' => now()->subDay(),
                ]);
            }
        }

        // 3. Daily Expenses & Expense Categories
        $categories = [
            'Utilities' => ExpenseCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Utilities']),
            'Maintenance' => ExpenseCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Maintenance']),
            'Refreshments' => ExpenseCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Refreshments']),
            'Packaging' => ExpenseCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Packaging']),
        ];

        $expenses = [
            ['title' => 'Workshop Electricity & Ironing Bill', 'cat' => 'Utilities', 'amount' => 1850000, 'date' => now()->subDays(5)],
            ['title' => 'Sewing Machine Oil, Needles & Servicing', 'cat' => 'Maintenance', 'amount' => 450000, 'date' => now()->subDays(3)],
            ['title' => 'Master & Craftsmen Afternoon Tea & Snacks', 'cat' => 'Refreshments', 'amount' => 120000, 'date' => now()->subDay()],
            ['title' => 'Showroom Air Conditioner Filter Cleaning', 'cat' => 'Maintenance', 'amount' => 250000, 'date' => now()->subDays(8)],
            ['title' => 'Custom Gold-Foil Packaging Boxes & Hangers', 'cat' => 'Packaging', 'amount' => 680000, 'date' => now()->subDays(2)],
        ];

        foreach ($expenses as $exp) {
            Expense::firstOrCreate(
                ['tenant_id' => $tenant->id, 'note' => $exp['title']],
                [
                    'public_id' => (string) Str::ulid(),
                    'expense_category_id' => $categories[$exp['cat']]->id,
                    'amount_minor' => $exp['amount'],
                    'expense_date' => $exp['date'],
                    'status' => 'approved',
                    'created_by' => $adminUser?->id,
                    'approved_by' => $adminUser?->id,
                    'approved_at' => now(),
                ]
            );
        }

        // 4. Rentals
        if ($wool && isset($customers['01678000404'])) {
            $rental = Rental::firstOrCreate(
                ['tenant_id' => $tenant->id, 'rental_number' => 'RNT-2026-001'],
                [
                    'public_id' => (string) Str::ulid(),
                    'customer_id' => $customers['01678000404']->id,
                    'status' => 'active',
                    'starts_on' => now()->subDay(),
                    'due_on' => now()->addDays(2),
                    'rent_minor' => 350000,
                    'deposit_minor' => 1000000,
                ]
            );

            if ($rental->wasRecentlyCreated) {
                RentalItem::create([
                    'tenant_id' => $tenant->id,
                    'rental_id' => $rental->id,
                    'inventory_item_id' => $wool->id,
                    'quantity' => 1,
                    'condition_out' => 'Pristine condition',
                ]);
            }
        }

        // 5. Karigar Attendance & Piece Work
        $faruk = $employees['Md. Faruk Hossain'] ?? null;
        $kashem = $employees['Abul Kashem'] ?? null;

        if ($faruk) {
            AttendanceRecord::firstOrCreate(
                ['tenant_id' => $tenant->id, 'employee_id' => $faruk->id, 'work_date' => now()->toDateString()],
                ['status' => 'present', 'checked_in_at' => '09:00:00', 'checked_out_at' => '18:00:00']
            );

            WorkEntry::firstOrCreate(
                ['tenant_id' => $tenant->id, 'employee_id' => $faruk->id, 'work_type' => 'Panjabi Stitching'],
                [
                    'quantity' => 1,
                    'rate_minor' => 35000,
                    'amount_minor' => 35000,
                    'status' => 'completed',
                    'completed_at' => now()->subHours(4),
                ]
            );
        }

        if ($kashem) {
            AttendanceRecord::firstOrCreate(
                ['tenant_id' => $tenant->id, 'employee_id' => $kashem->id, 'work_date' => now()->toDateString()],
                ['status' => 'present', 'checked_in_at' => '09:00:00', 'checked_out_at' => '19:00:00']
            );

            $workEntry = WorkEntry::firstOrCreate(
                ['tenant_id' => $tenant->id, 'employee_id' => $kashem->id, 'work_type' => 'Suit Coat Stitching'],
                [
                    'quantity' => 1,
                    'rate_minor' => 150000,
                    'amount_minor' => 150000,
                    'status' => 'completed',
                    'completed_at' => now()->subDays(2),
                ]
            );

            // Wage Payout Batch
            $payoutBatch = PayoutBatch::firstOrCreate(
                ['tenant_id' => $tenant->id, 'batch_number' => 'WAGE-2026-W34'],
                [
                    'public_id' => (string) Str::ulid(),
                    'employee_id' => $kashem->id,
                    'period_from' => now()->subDays(7)->toDateString(),
                    'period_to' => now()->toDateString(),
                    'total_minor' => 150000,
                    'payment_method' => 'cash',
                    'paid_at' => now()->subHours(2),
                ]
            );

            if ($payoutBatch->wasRecentlyCreated) {
                PayoutItem::create([
                    'tenant_id' => $tenant->id,
                    'payout_batch_id' => $payoutBatch->id,
                    'work_entry_id' => $workEntry->id,
                    'amount_minor' => 150000,
                ]);
            }
        }
    }
}
