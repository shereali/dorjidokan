<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingRequest;
use App\Models\Customer;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function trialBalance(): JsonResponse
    {
        $accounts = LedgerAccount::query()->withSum('lines as debit_minor', 'debit_minor')->withSum('lines as credit_minor', 'credit_minor')->orderBy('code')->get();

        return $this->ok(['items' => $accounts->map(fn ($account) => ['id' => $account->public_id, 'code' => $account->code, 'name' => $account->name, 'type' => $account->type, 'debit_minor' => (int) ($account->debit_minor ?? 0), 'credit_minor' => (int) ($account->credit_minor ?? 0), 'balance_minor' => (int) ($account->debit_minor ?? 0) - (int) ($account->credit_minor ?? 0)])]);
    }

    public function statement(Request $request, Customer $customer, LedgerService $ledger): JsonResponse
    {
        $account = $ledger->customerReceivable($customer);
        $from = $request->date('from');
        $to = $request->date('to');
        $openingBalance = $from ? (int) JournalLine::where('ledger_account_id', $account->id)
            ->whereHas('entry', fn ($entry) => $entry->where('occurred_at', '<', $from->copy()->startOfDay()))
            ->selectRaw('COALESCE(SUM(debit_minor - credit_minor), 0) AS balance')
            ->value('balance') : 0;
        $query = JournalLine::with('entry')->where('ledger_account_id', $account->id)
            ->when($from, fn ($builder) => $builder->whereHas('entry', fn ($entry) => $entry->where('occurred_at', '>=', $from->startOfDay())))
            ->when($to, fn ($builder) => $builder->whereHas('entry', fn ($entry) => $entry->where('occurred_at', '<=', $to->endOfDay())))
            ->get()->sortBy(fn ($line) => $line->entry->occurred_at);
        $balance = $openingBalance;
        $items = $query->map(function ($line) use (&$balance) {
            $balance += $line->debit_minor - $line->credit_minor;

            return ['entry_id' => $line->entry->public_id, 'occurred_at' => $line->entry->occurred_at->toIso8601String(), 'memo' => $line->entry->memo, 'debit_minor' => $line->debit_minor, 'credit_minor' => $line->credit_minor, 'balance_minor' => $balance];
        })->values();

        return $this->ok(['customer' => ['id' => $customer->public_id, 'name' => $customer->name, 'mobile_number' => $customer->mobile_number], 'opening_balance_minor' => $openingBalance, 'items' => $items, 'balance_minor' => $balance]);
    }

    public function adjustment(AccountingRequest $request, Customer $customer, LedgerService $ledger): JsonResponse
    {
        $data = $request->validated();
        $entry = $ledger->customerAdjustment($customer, $data['type'], $data['amount_minor'], $data['memo'], $request->user()->id);

        return $this->ok(['entry' => ['id' => $entry->public_id, 'memo' => $entry->memo, 'posted_at' => $entry->posted_at->toIso8601String()]], [], 201);
    }

    private function ok(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => (object) $meta, 'errors' => []], $status);
    }
}
