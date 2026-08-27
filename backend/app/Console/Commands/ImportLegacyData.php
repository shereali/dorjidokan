<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Garment;
use App\Models\GarmentPart;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\LegacyImportMapping;
use App\Models\LegacyStagingRecord;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMeasurement;
use App\Models\Payment;
use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use App\Models\Purchase;
use App\Models\Rental;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\WorkEntry;
use App\Services\LedgerService;
use App\Services\MobileNumber;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportLegacyData extends Command
{
    private const CORE_TABLES = ['customer', 'product', 'dsms'];

    private const RETAINED_TABLES = [
        'company', 'user', 'customer', 'product', 'measurement_head', 'select_detail',
        'dsms', 'order_record', 'order_details', 'accounting', 'transaction', 'update_due',
        'direct_sale_head', 'direct_sale', 'stock_adjustment', 'expense_type', 'expenses',
        'daily_attendance', 'employees_price_setup', 'emp_salary', 'wages_head',
        'wages_details', 'wages_memo', 'barcode', 'rent', 'tag_tbl', 'point_tbl',
        'set_text', 'order_num', 'vat_tax', 'only_febrics_tk', 'salary_transaction',
        'purchase_sms', 'dsms_occasion', 'extra_mobile', 'feg_order',
    ];

    private ?int $companyId = null;

    protected $signature = 'legacy:import {tenant : Target tenant slug} {--company-id= : Legacy company id when the source contains multiple businesses} {--dry-run}';

    protected $description = 'Stage and idempotently transform all retained legacy business data.';

    public function handle(TenantContext $context): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->firstOrFail();
        $context->set($tenant);
        try {
            foreach (self::CORE_TABLES as $table) {
                if (! Schema::connection('legacy')->hasTable($table)) {
                    $this->error("Legacy table {$table} is unavailable.");

                    return self::FAILURE;
                }
            }$this->companyId = $this->resolveCompanyId();
            $available = collect(self::RETAINED_TABLES)->filter(fn ($table) => Schema::connection('legacy')->hasTable($table))->values();
            if ($this->option('dry-run')) {
                $this->table(['Source table', 'Rows'], $available->map(fn ($table) => [$table, $this->sourceQuery($table)->count()]));

                return self::SUCCESS;
            }$this->stage($available->all());
            $this->companySettings($tenant);
            $this->notificationTemplates();
            $this->customers();
            $this->garments();
            $this->employees();
            $this->inventory();
            $this->purchases();
            $this->sales();
            $this->rentals();
            $this->orders();
            $this->orderItems();
            $this->measurements();
            $this->payments();
            $this->expenses();
            $this->attendance();
            $this->wages();
            $this->info('Legacy import completed; rerunning is safe.');

            return self::SUCCESS;
        } finally {
            $context->clear();
        }
    }

    private function customers(): void
    {
        $this->sourceQuery('customer')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $data = (array) $row;
                $checksum = $this->checksum($data);
                $map = $this->mapping('customer', $row->id);
                if ($map && $map->checksum === $checksum) {
                    continue;
                }try {
                    $mobile = MobileNumber::normalize((string) ($data['cus_mobile'] ?? $data['mobile'] ?? ''));
                } catch (\InvalidArgumentException) {
                    $mobile = '+880'.str_pad((string) $row->id, 10, '0', STR_PAD_LEFT);
                }$target = Customer::withTrashed()->updateOrCreate(['mobile_number' => $mobile], ['name' => $data['cus_name'] ?? $data['name'] ?? "Legacy Customer {$row->id}", 'address' => $data['cus_address'] ?? $data['address'] ?? null, 'created_via' => 'manual']);
                $this->record('customer', $row->id, $target, $checksum);
            }
        });
    }

    private function companySettings(Tenant $tenant): void
    {
        if (! Schema::connection('legacy')->hasTable('company')) {
            return;
        }
        $query = $this->sourceQuery('company');
        if ($this->companyId !== null) {
            $query->where('id', $this->companyId);
        }
        $company = $query->first();
        if (! $company) {
            return;
        }
        $data = (array) $company;
        $settings = array_replace($tenant->settings ?? [], [
            'low_stock_alerts' => ((int) ($data['is_stock'] ?? 0)) === 1,
            'order_ready_notifications' => ((int) ($data['order_sms'] ?? $data['sms_service'] ?? 0)) === 1,
            'item_receive_notifications' => ((int) ($data['item_receive_sms'] ?? 0)) === 1,
            'employee_payment_notifications' => ((int) ($data['emp_payment_sms'] ?? 0)) === 1,
            'legacy_company_id' => $data['id'],
        ]);
        $tenant->update(['name' => trim((string) ($data['com_name'] ?? '')) ?: $tenant->name, 'settings' => $settings]);
        $this->record('company', $data['id'], $tenant, $this->checksum($data));
    }

    private function notificationTemplates(): void
    {
        if (! Schema::connection('legacy')->hasTable('set_text')) {
            return;
        }
        $row = $this->sourceQuery('set_text')->orderByDesc('is_active')->orderByDesc('id')->first();
        if (! $row) {
            return;
        }
        $data = (array) $row;
        $template = NotificationTemplate::updateOrCreate(
            ['event' => 'order.ready', 'channel' => 'sms'],
            ['name' => 'Imported order-ready SMS', 'body' => (string) ($data['sms_text'] ?? 'Your order is ready.'), 'active' => ((int) ($data['is_active'] ?? 1)) === 1]
        );
        $this->record('set_text', $data['id'], $template, $this->checksum($data));
    }

    private function garments(): void
    {
        $this->sourceQuery('product')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $data = (array) $row;
                $checksum = $this->checksum($data);
                $map = $this->mapping('product', $row->id);
                if ($map && $map->checksum === $checksum) {
                    continue;
                }$target = Garment::withTrashed()->firstOrCreate(['slug' => 'legacy-'.$row->id], ['name' => trim((string) ($data['product_name'] ?? "Legacy Item {$row->id}")), 'active' => ($data['is_active'] ?? 1) == 1]);
                $this->record('product', $row->id, $target, $checksum);
                $this->garmentParts($target, $row->id, $data);
            }
        });
    }

    private function orders(): void
    {
        $this->sourceQuery('dsms')->orderBy('id')->chunkById(250, function ($rows) {
            foreach ($rows as $row) {
                $data = (array) $row;
                $checksum = $this->checksum($data);
                $map = $this->mapping('dsms', $row->id);
                if ($map && $map->checksum === $checksum) {
                    continue;
                }try {
                    $mobile = MobileNumber::normalize((string) ($data['cus_mobile'] ?? ''));
                } catch (\InvalidArgumentException) {
                    $mobile = '+881'.str_pad((string) $row->id, 10, '0', STR_PAD_LEFT);
                }$customer = Customer::firstOrCreate(['mobile_number' => $mobile], ['name' => $data['cus_name'] ?? "Legacy Customer {$row->id}", 'created_via' => 'manual']);
                $garment = Garment::orderBy('id')->firstOr(fn () => Garment::create(['name' => 'Legacy garment', 'slug' => 'legacy-garment', 'active' => true]));
                $target = Order::withTrashed()->updateOrCreate(['order_number' => (string) ($data['phy_ord_num'] ?? "LEGACY-{$row->id}")], ['customer_id' => $customer->id, 'garment_id' => $garment->id, 'status' => ! empty($data['phy_dv_date']) ? 'delivered' : 'in_progress', 'created_via' => 'manual', 'total_minor' => (int) round(((float) ($data['amount'] ?? 0)) * 100), 'created_at' => $data['insert_date'] ?? now(), 'updated_at' => now()]);
                $this->record('dsms', $row->id, $target, $checksum);
            }
        });
    }

    private function employees(): void
    {
        if (! Schema::connection('legacy')->hasTable('user')) {
            return;
        }
        $this->sourceQuery('user')->whereIn('emp_type', [1, 2, 3])->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $data = (array) $row;
                $mobile = trim((string) ($data['mobile'] ?? '')) ?: null;
                try {
                    $mobile = $mobile ? MobileNumber::normalize($mobile) : null;
                } catch (\InvalidArgumentException) {
                    $mobile = null;
                }
                $target = Employee::withTrashed()->updateOrCreate(['name' => $data['user_name'] ?? "Legacy employee {$row->id}"], ['mobile_number' => $mobile, 'employee_type' => ((int) ($data['emp_type'] ?? 2)) === 2 ? 'karigar' : 'staff', 'active' => ((int) ($data['is_active'] ?? 1)) === 1]);
                $this->record('user_employee', $row->id, $target, $this->checksum($data));
            }
        });
    }

    private function orderItems(): void
    {
        if (! Schema::connection('legacy')->hasTable('order_record')) {
            return;
        }
        $this->sourceQuery('order_record')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $data = (array) $row;
                $order = Order::where('order_number', (string) ($data['order_number'] ?? ''))->first();
                $garmentMap = $this->mapping('product', $data['item_id'] ?? '');
                $garment = $garmentMap ? Garment::withTrashed()->find($garmentMap->target_id) : null;
                if (! $order || ! $garment) {
                    continue;
                }
                $target = OrderItem::updateOrCreate(['legacy_source_id' => (string) $row->id], ['order_id' => $order->id, 'garment_id' => $garment->id, 'quantity' => max(0.01, (float) ($data['item_qty'] ?? 1)), 'making_cost_minor' => (int) round(((float) ($data['amount_cost'] ?? 0)) * 100), 'design_cost_minor' => (int) round(((float) ($data['design_cost'] ?? 0)) * 100), 'group_name' => $data['group_name'] ?? null]);
                $this->record('order_record', $row->id, $target, $this->checksum($data));
            }
        });
    }

    private function garmentParts(Garment $garment, int $productId, array $product): void
    {
        if (! Schema::connection('legacy')->hasTable('measurement_head')) {
            return;
        }
        $columns = Schema::connection('legacy')->getColumnListing('measurement_head');
        $query = $this->sourceQuery('measurement_head')->where('mtype', 1);
        foreach (['ptype', 'group_id'] as $column) {
            if (in_array($column, $columns, true) && isset($product[$column])) {
                $query->where($column, $product[$column]);
            }
        }
        foreach ($query->orderBy(in_array('steps', $columns, true) ? 'steps' : 'id')->get()->values() as $index => $row) {
            $data = (array) $row;
            $name = trim((string) ($data['title'] ?? "Measurement {$row->id}"));
            $slug = (string) str($name)->slug();
            if ($garment->parts()->where('slug', $slug)->where('id', '!=', $this->mapping("measurement_head_{$productId}", $row->id)?->target_id)->exists()) {
                $slug .= '-'.$row->id;
            }
            $map = $this->mapping("measurement_head_{$productId}", $row->id);
            $part = $map ? GarmentPart::find($map->target_id) : null;
            $part ??= new GarmentPart;
            $part->fill(['garment_id' => $garment->id, 'name' => $name, 'slug' => $slug, 'unit' => 'inch', 'display_order' => $index + 1, 'required' => true])->save();
            $this->record("measurement_head_{$productId}", $row->id, $part, $this->checksum($data));
        }
    }

    private function measurements(): void
    {
        if (! Schema::connection('legacy')->hasTable('order_details')) {
            return;
        }
        $this->sourceQuery('order_details')->where('mtype', 1)->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $data = (array) $row;
                $itemMap = $this->mapping('order_record', $data['order_number'] ?? '');
                $item = $itemMap ? OrderItem::with(['order'])->find($itemMap->target_id) : null;
                if (! $item || ! is_numeric($data['measurement'] ?? null) || (float) $data['measurement'] <= 0) {
                    continue;
                }
                $record = LegacyStagingRecord::where(['source_table' => 'order_record', 'source_id' => (string) ($data['order_number'] ?? '')])->first();
                $productId = $record?->payload['item_id'] ?? null;
                $partMap = $productId ? $this->mapping("measurement_head_{$productId}", $data['measure_id'] ?? '') : null;
                if (! $partMap) {
                    continue;
                }
                $target = OrderMeasurement::updateOrCreate(['order_id' => $item->order_id, 'garment_part_id' => $partMap->target_id], ['value' => (float) $data['measurement'], 'unit' => 'inch', 'entered_via' => 'manual', 'entered_at' => $data['insert_date'] ?? $item->order->created_at ?? now()]);
                $this->record('order_details', $row->id, $target, $this->checksum($data));
            }
        });
    }

    private function payments(): void
    {
        $ledger = app(LedgerService::class);
        foreach (LegacyImportMapping::where('source_table', 'dsms')->get() as $orderMap) {
            $order = Order::with('customer')->find($orderMap->target_id);
            if ($order && $order->total_minor > 0 && ! $this->mapping('dsms_invoice', $orderMap->source_id)) {
                $entry = $ledger->orderInvoice($order, null);
                $this->record('dsms_invoice', $orderMap->source_id, $entry, $orderMap->checksum);
            }
        }
        if (! Schema::connection('legacy')->hasTable('accounting')) {
            return;
        }
        $this->sourceQuery('accounting')->orderBy('id')->chunkById(500, function ($rows) use ($ledger) {
            foreach ($rows as $row) {
                $data = (array) $row;
                if (isset($data['is_active']) && (int) $data['is_active'] === 0) {
                    continue;
                }
                $orderMap = $this->mapping('dsms', $data['dsms_id'] ?? '');
                $order = $orderMap ? Order::with('customer')->find($orderMap->target_id) : null;
                $amount = (int) round(((float) ($data['amount'] ?? 0)) * 100);
                if (! $order || $amount < 1) {
                    continue;
                }
                $method = $this->paymentMethod($data['payment_type'] ?? null);
                $map = $this->mapping('accounting', $row->id);
                $payment = $map ? Payment::find($map->target_id) : null;
                $payment ??= new Payment;
                $payment->fill(['payable_type' => Order::class, 'payable_id' => $order->id, 'amount_minor' => $amount, 'currency' => 'BDT', 'method' => $method, 'reference' => 'Legacy accounting #'.$row->id, 'received_at' => $data['insert_date'] ?? $order->created_at ?? now()])->save();
                $this->record('accounting', $row->id, $payment, $this->checksum($data));
                if (! $this->mapping('accounting_journal', $row->id)) {
                    $entry = $ledger->customerPayment($payment, $order->customer, $amount, $method, null);
                    $this->record('accounting_journal', $row->id, $entry, $this->checksum($data));
                }
                $order->update(['paid_minor' => min($order->total_minor, (int) $order->payments()->sum('amount_minor'))]);
            }
        });
    }

    private function paymentMethod(?string $method): string
    {
        $value = strtolower(trim((string) $method));

        return match (true) {
            str_contains($value, 'card') => 'card',
            str_contains($value, 'bank') => 'bank_transfer',
            str_contains($value, 'bkash'), str_contains($value, 'nagad'), str_contains($value, 'rocket'), str_contains($value, 'mobile') => 'mobile_banking',
            default => 'cash',
        };
    }

    private function legacyDate(mixed $value, ?string $fallback = null): string
    {
        $text = trim((string) $value);
        foreach (['Y-m-d H:i:s', 'Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $text);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return $fallback ?? now()->toDateString();
    }

    private function inventory(): void
    {
        $this->sourceQuery('product')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $data = (array) $row;
                $target = InventoryItem::withTrashed()->updateOrCreate(['sku' => trim((string) ($data['item_code'] ?? '')) ?: "LEGACY-{$row->id}"], ['name' => trim((string) ($data['product_name'] ?? "Legacy item {$row->id}")), 'unit' => trim((string) ($data['uom'] ?? 'piece')) ?: 'piece', 'active' => ((int) ($data['is_active'] ?? 1)) !== 0]);
                $this->record('product_inventory', $row->id, $target, $this->checksum($data));
            }
        });
        if (! Schema::connection('legacy')->hasTable('direct_sale')) {
            return;
        }
        $this->sourceQuery('direct_sale')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $data = (array) $row;
                $itemMap = $this->mapping('product_inventory', $data['item_id'] ?? '');
                $item = $itemMap ? InventoryItem::withTrashed()->find($itemMap->target_id) : null;
                if (! $item) {
                    continue;
                }
                $quantity = (float) ($data['item_qty'] ?? 0);
                $type = ((int) ($data['in_out_status'] ?? 0)) === 1 ? 'legacy_purchase' : 'legacy_sale';
                $target = InventoryMovement::updateOrCreate(['reference_type' => LegacyStagingRecord::class, 'reference_id' => LegacyStagingRecord::where(['source_table' => 'direct_sale', 'source_id' => (string) $row->id])->value('id')], ['inventory_item_id' => $item->id, 'type' => $type, 'quantity' => $type === 'legacy_purchase' ? abs($quantity) : -abs($quantity), 'unit_cost_minor' => (int) round(((float) ($data['purchase_price'] ?? $data['price'] ?? 0)) * 100), 'reason' => 'Imported legacy stock transaction', 'occurred_at' => $data['insert_date'] ?? now()]);
                $this->record('direct_sale', $row->id, $target, $this->checksum($data));
            }
        });
    }

    private function expenses(): void
    {
        if (! Schema::connection('legacy')->hasTable('expenses')) {
            return;
        }
        $categories = Schema::connection('legacy')->hasTable('expense_type') ? $this->sourceQuery('expense_type')->get()->keyBy('id') : collect();
        $this->sourceQuery('expenses')->orderBy('id')->chunkById(500, function ($rows) use ($categories) {
            foreach ($rows as $row) {
                $data = (array) $row;
                $legacyCategory = $categories->get($data['expense_type'] ?? null);
                $category = ExpenseCategory::firstOrCreate(['name' => $legacyCategory->expense ?? 'Legacy expense']);
                $map = $this->mapping('expenses', $row->id);
                $target = $map ? Expense::withTrashed()->find($map->target_id) : new Expense;
                $target ??= new Expense;
                $target->fill(['expense_category_id' => $category->id, 'amount_minor' => (int) round(((float) ($data['expense'] ?? 0)) * 100), 'note' => $data['remarks'] ?? null, 'expense_date' => substr((string) ($data['insert_date'] ?? now()->toDateString()), 0, 10), 'status' => 'approved'])->save();
                $this->record('expenses', $row->id, $target, $this->checksum($data));
                if ($target->amount_minor > 0 && ! $this->mapping('expenses_journal', $row->id)) {
                    $entry = app(LedgerService::class)->expense($target, $category->name, $target->amount_minor, null);
                    $this->record('expenses_journal', $row->id, $entry, $this->checksum($data));
                }
            }
        });
    }

    private function sales(): void
    {
        if (! Schema::connection('legacy')->hasTable('direct_sale_head') || ! Schema::connection('legacy')->hasTable('direct_sale')) {
            return;
        }
        $ledger = app(LedgerService::class);
        $this->sourceQuery('direct_sale_head')->where(function ($query) {
            $query->whereNull('in_out_status')->orWhere('in_out_status', 0);
        })->where(function ($query) {
            $query->whereNull('is_active')->orWhere('is_active', '!=', 2);
        })->orderBy('id')->chunkById(250, function ($rows) use ($ledger) {
            foreach ($rows as $row) {
                $data = (array) $row;
                if (isset($data['is_active']) && (int) $data['is_active'] === 0) {
                    continue;
                }
                $mobile = null;
                try {
                    $mobile = MobileNumber::normalize((string) ($data['cus_mobile'] ?? ''));
                } catch (\InvalidArgumentException) {
                }
                $customer = $mobile ? Customer::where('mobile_number', $mobile)->first() : null;
                $subtotal = (int) round(((float) ($data['f_total'] ?? 0) + (float) ($data['others_tk'] ?? 0)) * 100);
                $discount = (int) round(((float) ($data['discount_tk'] ?? 0)) * 100);
                $total = max(0, $subtotal - $discount);
                $paid = min($total, (int) round(((float) ($data['total_receive'] ?? $total / 100)) * 100));
                if ($paid < $total && ! $customer) {
                    $customer = Customer::firstOrCreate(['mobile_number' => '+882'.str_pad((string) $row->id, 10, '0', STR_PAD_LEFT)], ['name' => $data['cus_name'] ?? "Legacy sale customer {$row->id}", 'created_via' => 'manual']);
                }
                $sale = Sale::withTrashed()->updateOrCreate(['sale_number' => "LEGACY-SALE-{$row->id}"], ['customer_id' => $customer?->id, 'sale_type' => 'direct', 'status' => $paid >= $total ? 'paid' : 'partial', 'subtotal_minor' => $subtotal, 'discount_minor' => $discount, 'total_minor' => $total, 'paid_minor' => $paid, 'created_at' => $data['insert_date'] ?? now(), 'updated_at' => now()]);
                $sale->items()->delete();
                foreach ($this->sourceQuery('direct_sale')->where('dstid', $row->id)->get() as $line) {
                    $lineData = (array) $line;
                    $itemMap = $this->mapping('product_inventory', $lineData['item_id'] ?? '');
                    if (! $itemMap) {
                        continue;
                    }
                    $quantity = abs((float) ($lineData['item_qty'] ?? 0));
                    $unitPrice = (int) round(((float) ($lineData['price'] ?? 0)) * 100);
                    $sale->items()->create(['inventory_item_id' => $itemMap->target_id, 'quantity' => $quantity, 'unit_price_minor' => $unitPrice, 'total_minor' => (int) round($quantity * $unitPrice)]);
                }
                $this->record('direct_sale_head', $row->id, $sale, $this->checksum($data));
                if ($sale->total_minor > 0 && ! $this->mapping('direct_sale_head_journal', $row->id)) {
                    $entry = $ledger->sale($sale->load('customer'), $this->paymentMethod($data['payment_type'] ?? null), null);
                    $this->record('direct_sale_head_journal', $row->id, $entry, $this->checksum($data));
                }
            }
        });
    }

    private function purchases(): void
    {
        if (! Schema::connection('legacy')->hasTable('direct_sale_head') || ! Schema::connection('legacy')->hasTable('direct_sale')) {
            return;
        }
        $this->sourceQuery('direct_sale_head')->where('in_out_status', 1)->orderBy('id')->chunkById(250, function ($rows) {
            foreach ($rows as $row) {
                $data = (array) $row;
                $mobile = trim((string) ($data['cus_mobile'] ?? '')) ?: null;
                try {
                    $mobile = $mobile ? MobileNumber::normalize($mobile) : null;
                } catch (\InvalidArgumentException) {
                    $mobile = null;
                }
                $mobile ??= '+884'.str_pad((string) $row->id, 10, '0', STR_PAD_LEFT);
                $supplier = Supplier::withTrashed()->firstOrCreate(['mobile_number' => $mobile], ['name' => $data['cus_name'] ?? ($mobile ? "Legacy supplier {$mobile}" : "Legacy supplier {$row->id}"), 'address' => $data['cus_address'] ?? null]);
                $purchase = Purchase::withTrashed()->updateOrCreate(['purchase_number' => "LEGACY-PUR-{$row->id}"], ['supplier_id' => $supplier->id, 'status' => 'received', 'total_minor' => (int) round(((float) ($data['f_total'] ?? 0)) * 100), 'received_at' => $data['insert_date'] ?? now(), 'created_at' => $data['insert_date'] ?? now(), 'updated_at' => now()]);
                $purchase->items()->delete();
                foreach ($this->sourceQuery('direct_sale')->where('dstid', $row->id)->get() as $line) {
                    $lineData = (array) $line;
                    $itemMap = $this->mapping('product_inventory', $lineData['item_id'] ?? '');
                    if (! $itemMap) {
                        continue;
                    }
                    $quantity = abs((float) ($lineData['item_qty'] ?? 0));
                    $cost = (int) round(((float) ($lineData['price'] ?? 0)) * 100);
                    $purchase->items()->create(['inventory_item_id' => $itemMap->target_id, 'quantity' => $quantity, 'unit_cost_minor' => $cost, 'total_minor' => (int) round($quantity * $cost)]);
                }
                $this->record('direct_sale_head_purchase', $row->id, $purchase, $this->checksum($data));
            }
        });
    }

    private function rentals(): void
    {
        if (! Schema::connection('legacy')->hasTable('direct_sale_head') || ! Schema::connection('legacy')->hasTable('direct_sale')) {
            return;
        }
        $this->sourceQuery('direct_sale_head')->where('is_active', 2)->orderBy('id')->chunkById(250, function ($rows) {
            foreach ($rows as $row) {
                $data = (array) $row;
                try {
                    $mobile = MobileNumber::normalize((string) ($data['cus_mobile'] ?? ''));
                } catch (\InvalidArgumentException) {
                    $mobile = '+883'.str_pad((string) $row->id, 10, '0', STR_PAD_LEFT);
                }
                $customer = Customer::firstOrCreate(['mobile_number' => $mobile], ['name' => $data['cus_name'] ?? "Legacy rental customer {$row->id}", 'address' => $data['cus_address'] ?? null, 'created_via' => 'manual']);
                $start = $this->legacyDate($data['submission_date'] ?? $data['insert_date'] ?? null);
                $due = $this->legacyDate($data['payment_date'] ?? null, $start);
                $rental = Rental::withTrashed()->updateOrCreate(['rental_number' => "LEGACY-RNT-{$row->id}"], ['customer_id' => $customer->id, 'status' => ($data['payment_status'] ?? '') === 'Paid' ? 'returned' : 'reserved', 'starts_on' => $start, 'due_on' => $due, 'returned_on' => ($data['payment_status'] ?? '') === 'Paid' ? $due : null, 'rent_minor' => (int) round(((float) ($data['f_total'] ?? 0)) * 100), 'deposit_minor' => (int) round(((float) ($data['total_receive'] ?? 0)) * 100), 'created_at' => $data['insert_date'] ?? now(), 'updated_at' => now()]);
                $rental->items()->delete();
                foreach ($this->sourceQuery('direct_sale')->where('dstid', $row->id)->get() as $line) {
                    $lineData = (array) $line;
                    $itemMap = $this->mapping('product_inventory', $lineData['item_id'] ?? '');
                    if ($itemMap) {
                        $rental->items()->create(['inventory_item_id' => $itemMap->target_id, 'quantity' => max(0.01, abs((float) ($lineData['item_qty'] ?? 1)))]);
                    }
                }
                $this->record('direct_sale_head_rental', $row->id, $rental, $this->checksum($data));
            }
        });
    }

    private function wages(): void
    {
        if (! Schema::connection('legacy')->hasTable('wages_details')) {
            return;
        }
        $this->sourceQuery('wages_details')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $data = (array) $row;
                if (isset($data['is_active']) && (int) $data['is_active'] === 0) {
                    continue;
                }
                $employeeMap = $this->mapping('user_employee', $data['emp_id'] ?? '');
                if (! $employeeMap) {
                    continue;
                }
                $amount = (int) round(abs((float) ($data['taka'] ?? 0)) * 100);
                if ($amount < 1) {
                    continue;
                }
                $quantity = max(0.01, abs((float) ($data['item_qty'] ?? 1)));
                $orderItemMap = $this->mapping('order_record', $data['ord_record_tblid'] ?? '');
                $orderItem = $orderItemMap ? OrderItem::find($orderItemMap->target_id) : null;
                $paid = (int) ($data['status'] ?? 0) === 1;
                $map = $this->mapping('wages_details', $row->id);
                $entry = $map ? WorkEntry::find($map->target_id) : null;
                $entry ??= new WorkEntry;
                $entry->fill(['employee_id' => $employeeMap->target_id, 'order_id' => $orderItem?->order_id, 'work_type' => $data['item_name'] ?? ($paid ? 'Legacy wage payment' : 'Legacy piece work'), 'quantity' => $quantity, 'rate_minor' => (int) round($amount / $quantity), 'amount_minor' => $amount, 'status' => $paid ? 'paid' : 'unpaid', 'completed_at' => $data['insert_date'] ?? now()])->save();
                $this->record('wages_details', $row->id, $entry, $this->checksum($data));
                if ($paid) {
                    $paidOn = $this->legacyDate($data['insert_date'] ?? null);
                    $batch = PayoutBatch::updateOrCreate(['batch_number' => "LEGACY-PAY-{$row->id}"], ['employee_id' => $employeeMap->target_id, 'period_from' => $paidOn, 'period_to' => $paidOn, 'total_minor' => $amount, 'payment_method' => 'cash', 'reference' => $data['remarks'] ?? null, 'paid_at' => $data['insert_date'] ?? now()]);
                    PayoutItem::updateOrCreate(['work_entry_id' => $entry->id], ['payout_batch_id' => $batch->id, 'amount_minor' => $amount]);
                    $this->record('wages_details_payout', $row->id, $batch, $this->checksum($data));
                }
            }
        });
    }

    private function attendance(): void
    {
        if (! Schema::connection('legacy')->hasTable('daily_attendance')) {
            return;
        }
        $this->sourceQuery('daily_attendance')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $data = (array) $row;
                $employeeMap = $this->mapping('user_employee', $data['emp_id'] ?? '');
                if (! $employeeMap) {
                    continue;
                }
                $status = ((int) ($data['pastatus'] ?? 1)) === 1 ? 'present' : 'absent';
                $workDate = $this->legacyDate($data['attendence_date'] ?? null);
                $target = AttendanceRecord::where('employee_id', $employeeMap->target_id)->whereDate('work_date', $workDate)->first() ?? new AttendanceRecord;
                $target->fill(['employee_id' => $employeeMap->target_id, 'work_date' => $workDate, 'status' => $status])->save();
                $this->record('daily_attendance', $row->id, $target, $this->checksum($data));
            }
        });
    }

    private function mapping(string $table, $id): ?LegacyImportMapping
    {
        return LegacyImportMapping::where(['source_table' => $table, 'source_id' => (string) $id])->first();
    }

    private function checksum(array $data): string
    {
        ksort($data);

        return hash('sha256', json_encode($data));
    }

    private function record(string $table, $id, $target, string $checksum): void
    {
        LegacyImportMapping::updateOrCreate(['source_table' => $table, 'source_id' => (string) $id], ['target_type' => $target::class, 'target_id' => $target->id, 'checksum' => $checksum]);
    }

    private function stage(array $tables): void
    {
        foreach ($tables as $table) {
            $columns = Schema::connection('legacy')->getColumnListing($table);
            $key = in_array('id', $columns, true) ? 'id' : null;
            $query = $this->sourceQuery($table);
            $process = function ($rows) use ($table, $key) {
                foreach ($rows as $row) {
                    $payload = (array) $row;
                    $sourceId = (string) ($key ? $payload[$key] : hash('sha256', json_encode($payload)));
                    LegacyStagingRecord::updateOrCreate(
                        ['source_table' => $table, 'source_id' => $sourceId],
                        ['source_company_id' => $payload['com_id'] ?? $payload['company_id'] ?? null, 'payload' => $payload, 'checksum' => $this->checksum($payload)]
                    );
                }
            };
            if ($key) {
                $query->orderBy($key)->chunkById(500, $process, $key);
            } else {
                $query->orderBy($columns[0])->chunk(500, $process);
            }
            $this->line("Staged {$table}");
        }
    }

    private function sourceQuery(string $table)
    {
        $query = DB::connection('legacy')->table($table);
        $columns = Schema::connection('legacy')->getColumnListing($table);
        if ($this->companyId !== null) {
            if (in_array('com_id', $columns, true)) {
                $query->where('com_id', $this->companyId);
            } elseif ($table === 'company' && in_array('id', $columns, true)) {
                $query->where('id', $this->companyId);
            }
        }

        return $query;
    }

    private function resolveCompanyId(): ?int
    {
        if ($this->option('company-id') !== null) {
            return (int) $this->option('company-id');
        }
        if (! Schema::connection('legacy')->hasTable('company')) {
            return null;
        }
        $ids = DB::connection('legacy')->table('company')->pluck('id');
        if ($ids->count() > 1) {
            throw new \RuntimeException('The legacy database contains multiple companies; pass --company-id to prevent cross-business data mixing.');
        }

        return $ids->first() !== null ? (int) $ids->first() : null;
    }
}
