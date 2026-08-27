<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OperationsMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'saveInventory' => ['sku' => 'required|string|max:80', 'name' => 'required|string|max:160', 'unit' => 'required|string|max:20', 'reorder_level' => 'nullable|numeric|min:0'],
            'adjust' => ['quantity' => 'required|numeric|not_in:0', 'reason' => 'required|string|max:255'],
            'purchase' => ['supplier_id' => 'nullable|string', 'items' => 'required|array|min:1', 'items.*.inventory_item_id' => 'required|string', 'items.*.quantity' => 'required|numeric|gt:0', 'items.*.unit_cost_minor' => 'required|integer|min:0'],
            'saveSupplier' => ['name' => 'required|string|max:160', 'mobile_number' => 'nullable|string|max:20', 'address' => 'nullable|string|max:1000'],
            'sale' => ['customer_id' => 'nullable|string', 'sale_type' => 'nullable|in:direct,fabric', 'discount_minor' => 'nullable|integer|min:0', 'paid_minor' => 'nullable|integer|min:0', 'payment_method' => 'nullable|in:cash,card,mobile_banking,bank', 'items' => 'required|array|min:1', 'items.*.inventory_item_id' => 'required|string', 'items.*.quantity' => 'required|numeric|gt:0', 'items.*.unit_price_minor' => 'required|integer|min:0'],
            'saveExpense' => ['category' => 'required|string|max:100', 'amount_minor' => 'required|integer|gt:0', 'note' => 'nullable|string|max:1000', 'expense_date' => 'required|date'],
            'uploadExpenseAttachment' => ['attachment' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120'],
            'attendance' => ['employee_id' => 'required|string', 'work_date' => 'required|date', 'status' => 'required|in:present,absent,leave,half_day', 'checked_in_at' => 'nullable|date_format:H:i', 'checked_out_at' => 'nullable|date_format:H:i'],
            'work' => ['employee_id' => 'required|string', 'order_id' => 'nullable|string', 'work_type' => 'required|string|max:80', 'quantity' => 'required|numeric|gt:0', 'rate_minor' => 'required|integer|min:0'],
            'rental' => ['customer_id' => 'required|string', 'starts_on' => 'required|date', 'due_on' => 'required|date|after_or_equal:starts_on', 'rent_minor' => 'required|integer|min:0', 'deposit_minor' => 'nullable|integer|min:0', 'items' => 'required|array|min:1', 'items.*.inventory_item_id' => 'required|string', 'items.*.quantity' => 'required|numeric|gt:0', 'items.*.condition_out' => 'nullable|string|max:255'],
            'returnRental' => ['conditions' => 'nullable|array', 'conditions.*' => 'nullable|string|max:255', 'damage_charge_minor' => 'nullable|integer|min:0', 'settlement_note' => 'nullable|string|max:500'],
            'payoutBatch' => ['employee_id' => 'required|string|size:26', 'period_from' => 'required|date', 'period_to' => 'required|date|after_or_equal:period_from', 'payment_method' => 'required|in:cash,mobile_banking,bank_transfer', 'reference' => 'nullable|string|max:120'],
            default => [],
        };
    }
}
