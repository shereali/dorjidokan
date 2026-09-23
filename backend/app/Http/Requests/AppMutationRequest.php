<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'saveCustomer' => ['name' => 'required|string|min:2|max:120', 'mobile_number' => 'required|string', 'address' => 'nullable|string|max:1000', 'marketing_consent' => 'sometimes|boolean'],
            'updateCustomer' => ['name' => 'sometimes|required|string|min:2|max:120', 'mobile_number' => 'sometimes|required|string', 'address' => 'sometimes|nullable|string|max:1000', 'marketing_consent' => 'sometimes|boolean'],
            'saveOrder' => ['customer_id' => 'required|string|size:26', 'garment_id' => 'required|string|size:26', 'promised_at' => 'nullable|date|after_or_equal:today', 'total_minor' => 'required|integer|min:0', 'paid_minor' => 'required|integer|min:0|lte:total_minor'],
            'saveOrderMeasurement' => ['garment_part_id' => 'required|string|size:26', 'value' => 'required|numeric|gt:0|lte:999.99', 'unit' => 'nullable|in:inch,cm'],
            'assignOrder' => ['karigar_id' => 'required|string|size:26'],
            'updateOrderStatus' => ['status' => 'required|in:measuring,pending_assignment,in_progress,ready,delivered,cancelled', 'note' => 'nullable|string|max:500'],
            'payOrder' => ['amount_minor' => 'required|integer|min:1', 'method' => 'required|in:cash,card,mobile_banking,bank_transfer', 'reference' => 'nullable|string|max:120'],
            'saveGarment' => [
                'name' => 'required|string|min:2|max:100',
                'slug' => 'nullable|string|max:100',
                'category' => 'nullable|string|in:gents,ladies,kids,unisex',
                'group_name' => 'nullable|string|max:100',
                'base_making_minor' => 'nullable|integer|min:0',
                'master_rate_minor' => 'nullable|integer|min:0',
                'karigar_rate_minor' => 'nullable|integer|min:0',
                'description' => 'nullable|string|max:2000',
                'loose_allowances' => 'nullable|array',
                'display_order' => 'nullable|integer',
                'illustration_url' => 'nullable|string|max:255',
                'parts' => 'required|array|min:1|max:40',
                'parts.*.name' => 'required|string|max:100',
                'parts.*.unit' => 'required|in:inch,cm',
                'parts.*.required' => 'sometimes|boolean',
            ],
            'updateGarment' => [
                'name' => 'sometimes|required|string|min:2|max:100',
                'slug' => 'sometimes|nullable|string|max:100',
                'category' => 'sometimes|nullable|string|in:gents,ladies,kids,unisex',
                'group_name' => 'sometimes|nullable|string|max:100',
                'base_making_minor' => 'sometimes|nullable|integer|min:0',
                'master_rate_minor' => 'sometimes|nullable|integer|min:0',
                'karigar_rate_minor' => 'sometimes|nullable|integer|min:0',
                'description' => 'sometimes|nullable|string|max:2000',
                'loose_allowances' => 'sometimes|nullable|array',
                'display_order' => 'sometimes|nullable|integer',
                'illustration_url' => 'sometimes|nullable|string|max:255',
                'active' => 'sometimes|boolean',
            ],
            'saveGarmentPart' => ['name' => 'required|string|max:100', 'unit' => 'required|in:inch,cm', 'required' => 'sometimes|boolean'],
            'saveGarmentDesignOption' => [
                'name' => 'required|string|max:100',
                'type' => 'nullable|in:select,checkbox,radio',
                'values' => 'nullable|array',
                'values.*.name' => 'required|string|max:100',
                'values.*.extra_price_minor' => 'nullable|integer|min:0',
                'values.*.is_default' => 'nullable|boolean',
            ],
            'updateGarmentDesignOption' => [
                'name' => 'sometimes|required|string|max:100',
                'type' => 'sometimes|in:select,checkbox,radio',
                'values' => 'nullable|array',
                'values.*.name' => 'required|string|max:100',
                'values.*.extra_price_minor' => 'nullable|integer|min:0',
                'values.*.is_default' => 'nullable|boolean',
            ],
            'saveEmployee' => ['name' => 'required|string|min:2|max:120', 'mobile_number' => 'nullable|string', 'employee_type' => 'required|in:karigar,staff,manager'],
            'updateEmployee' => ['name' => 'sometimes|required|string|min:2|max:120', 'mobile_number' => 'sometimes|nullable|string', 'employee_type' => 'sometimes|required|in:karigar,staff,manager', 'active' => 'sometimes|boolean'],
            'redeemLoyalty' => ['points' => 'required|integer|min:1', 'reason' => 'nullable|string|max:190'],
            default => [],
        };
    }
}
