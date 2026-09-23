<?php

namespace Database\Seeders;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'heritage-tailors')->firstOrFail();
        app(TenantContext::class)->set($tenant);

        $accounts = [
            ['code' => '1001', 'name' => 'Cash in Atelier Drawer', 'type' => 'asset'],
            ['code' => '1002', 'name' => 'City Bank Current A/C (Dhaka)', 'type' => 'asset'],
            ['code' => '1100', 'name' => 'Customer Accounts Receivable', 'type' => 'asset'],
            ['code' => '1200', 'name' => 'Fabric & Trim Inventory Asset', 'type' => 'asset'],
            ['code' => '2001', 'name' => 'Supplier Accounts Payable', 'type' => 'liability'],
            ['code' => '2100', 'name' => 'Customer Advance Deposits', 'type' => 'liability'],
            ['code' => '3001', 'name' => 'Owner Atelier Capital', 'type' => 'equity'],
            ['code' => '4001', 'name' => 'Bespoke Tailoring Making Charges', 'type' => 'revenue'],
            ['code' => '4002', 'name' => 'Retail Fabric Sales Revenue', 'type' => 'revenue'],
            ['code' => '4003', 'name' => 'Wedding Sherwani Rental Income', 'type' => 'revenue'],
            ['code' => '5001', 'name' => 'Karigar & Craftsmen Piece Wages', 'type' => 'expense'],
            ['code' => '5002', 'name' => 'Workshop Electricity & Overhead', 'type' => 'expense'],
            ['code' => '5003', 'name' => 'Sewing Machine Repairs & Tools', 'type' => 'expense'],
        ];

        $accountMap = [];
        foreach ($accounts as $acc) {
            $accountMap[$acc['code']] = LedgerAccount::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $acc['code']],
                ['name' => $acc['name'], 'type' => $acc['type']]
            );
        }

        // Opening Balanced Capital Journal Entry
        $cashAcc = $accountMap['1001'];
        $bankAcc = $accountMap['1002'];
        $capAcc = $accountMap['3001'];

        if ($cashAcc && $bankAcc && $capAcc) {
            $openingEntry = JournalEntry::firstOrCreate(
                ['tenant_id' => $tenant->id, 'memo' => 'Atelier Opening Capital Balance (2026)'],
                [
                    'occurred_at' => now()->startOfYear(),
                    'posted_at' => now()->startOfYear(),
                ]
            );

            if ($openingEntry->wasRecentlyCreated) {
                JournalLine::create(['tenant_id' => $tenant->id, 'journal_entry_id' => $openingEntry->id, 'ledger_account_id' => $cashAcc->id, 'debit_minor' => 5000000, 'credit_minor' => 0]);
                JournalLine::create(['tenant_id' => $tenant->id, 'journal_entry_id' => $openingEntry->id, 'ledger_account_id' => $bankAcc->id, 'debit_minor' => 20000000, 'credit_minor' => 0]);
                JournalLine::create(['tenant_id' => $tenant->id, 'journal_entry_id' => $openingEntry->id, 'ledger_account_id' => $capAcc->id, 'debit_minor' => 0, 'credit_minor' => 25000000]);
            }
        }
    }
}
