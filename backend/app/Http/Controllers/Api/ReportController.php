<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Sale;
use App\Models\WorkEntry;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function summary(ReportRequest $r): JsonResponse
    {
        $d = $r->validated();
        $from = $d['from'] ?? now()->startOfMonth()->toDateString();
        $to = $d['to'] ?? now()->toDateString();
        $orders = Order::whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $sales = Sale::whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $orderValue = (int) (clone $orders)->sum('total_minor');
        $orderPaid = (int) (clone $orders)->sum('paid_minor');
        $saleValue = (int) (clone $sales)->sum('total_minor');
        $salePaid = (int) (clone $sales)->sum('paid_minor');
        $expenses = (int) Expense::where('status', 'approved')->whereBetween('expense_date', [$from, $to])->sum('amount_minor');
        $wages = (int) WorkEntry::whereBetween('completed_at', [$from.' 00:00:00', $to.' 23:59:59'])->sum('amount_minor');

        $lowStock = InventoryItem::withSum('movements', 'quantity')->get()->filter(fn ($item) => (float) $item->movements_sum_quantity <= (float) $item->reorder_level)->map(fn ($item) => ['id' => $item->public_id, 'sku' => $item->sku, 'name' => $item->name, 'unit' => $item->unit, 'balance' => (float) $item->movements_sum_quantity, 'reorder_level' => (float) $item->reorder_level])->values();

        return response()->json(['data' => ['period' => compact('from', 'to'), 'orders' => ['count' => (clone $orders)->count(), 'value_minor' => $orderValue, 'paid_minor' => $orderPaid], 'sales' => ['count' => (clone $sales)->count(), 'value_minor' => $saleValue, 'paid_minor' => $salePaid], 'expenses_minor' => $expenses, 'wages_unpaid_minor' => WorkEntry::where('status', 'unpaid')->sum('amount_minor'), 'decision' => ['gross_income_minor' => $orderValue + $saleValue, 'labor_cost_minor' => $wages, 'net_profit_minor' => $orderValue + $saleValue - $expenses - $wages, 'receivables_minor' => ($orderValue - $orderPaid) + ($saleValue - $salePaid), 'overdue_orders' => Order::where('promised_at', '<', now())->whereNotIn('status', ['delivered', 'cancelled'])->count()], 'low_stock' => $lowStock], 'meta' => (object) [], 'errors' => []]);
    }
}
