<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Order;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LedgerService
{
    public function orderInvoice(Order $order, ?int $userId): JournalEntry
    {
        return $this->post($order, "Invoice {$order->order_number}", $userId, [
            [$this->customerReceivable($order->customer), $order->total_minor, 0],
            [$this->account('TAILORING_REVENUE', 'Tailoring revenue', 'revenue'), 0, $order->total_minor],
        ]);
    }

    public function customerPayment(Model $reference, Customer $customer, int $amount, string $method, ?int $userId): JournalEntry
    {
        return $this->post($reference, "Customer payment via {$method}", $userId, [
            [$this->account('CASH_'.$method, ucfirst(str_replace('_', ' ', $method)), 'asset'), $amount, 0],
            [$this->customerReceivable($customer), 0, $amount],
        ]);
    }

    public function expense(Model $reference, string $category, int $amount, ?int $userId): JournalEntry
    {
        return $this->post($reference, "Expense: {$category}", $userId, [
            [$this->account('EXP_'.str($category)->slug('_')->upper(), $category.' expense', 'expense'), $amount, 0],
            [$this->account('CASH_cash', 'Cash', 'asset'), 0, $amount],
        ]);
    }

    public function sale(Sale $sale, string $method, ?int $userId): JournalEntry
    {
        $lines = [];
        if ($sale->paid_minor > 0) {
            $lines[] = [$this->account('CASH_'.$method, ucfirst(str_replace('_', ' ', $method)), 'asset'), $sale->paid_minor, 0];
        }
        $due = $sale->total_minor - $sale->paid_minor;
        if ($due > 0) {
            if (! $sale->customer) {
                throw ValidationException::withMessages(['customer_id' => 'A customer is required when a sale has an outstanding balance.']);
            }
            $lines[] = [$this->customerReceivable($sale->customer), $due, 0];
        }
        $lines[] = [$this->account('SALES_REVENUE', 'Sales revenue', 'revenue'), 0, $sale->total_minor];

        return $this->post($sale, "Sale {$sale->sale_number}", $userId, $lines);
    }

    public function customerAdjustment(Customer $customer, string $type, int $amount, string $memo, ?int $userId): JournalEntry
    {
        $receivable = $this->customerReceivable($customer);
        $offset = $this->account('CUSTOMER_ADJUSTMENTS', 'Customer adjustments', 'equity');
        $lines = $type === 'charge' ? [[$receivable, $amount, 0], [$offset, 0, $amount]] : [[$offset, $amount, 0], [$receivable, 0, $amount]];

        return $this->post($customer, $memo, $userId, $lines);
    }

    public function post(Model $reference, string $memo, ?int $userId, array $lines): JournalEntry
    {
        $debits = collect($lines)->sum(fn ($line) => $line[1]);
        $credits = collect($lines)->sum(fn ($line) => $line[2]);
        if ($debits < 1 || $debits !== $credits) {
            throw ValidationException::withMessages(['ledger' => 'Journal entry must be positive and balanced.']);
        }

        return DB::transaction(function () use ($reference, $memo, $userId, $lines) {
            $entry = JournalEntry::create(['reference_type' => $reference::class, 'reference_id' => $reference->getKey(), 'memo' => $memo, 'occurred_at' => now(), 'posted_at' => now(), 'created_by' => $userId]);
            foreach ($lines as [$account, $debit, $credit]) {
                $entry->lines()->create(['ledger_account_id' => $account->id, 'debit_minor' => $debit, 'credit_minor' => $credit]);
            }

            return $entry->load('lines.account');
        });
    }

    public function customerReceivable(Customer $customer): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(['code' => 'AR_'.$customer->public_id], ['customer_id' => $customer->id, 'name' => $customer->name.' receivable', 'type' => 'asset', 'system' => true]);
    }

    private function account(string $code, string $name, string $type): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'system' => true]);
    }
}
