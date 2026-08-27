<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoiceMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'register' => ['name' => 'required|string|min:2|max:120', 'mobile_number' => 'required|string|min:7|max:20'],
            'store' => ['customer_id' => 'required|string|size:26', 'garment_id' => 'required|string|size:26'],
            'measurement' => ['garment_part_id' => 'required_without:garment_part_name|nullable|string', 'garment_part_name' => 'required_without:garment_part_id|nullable|string|max:100', 'value' => 'required|numeric|gt:0|lte:999.99', 'unit' => 'nullable|in:inch,cm'],
            'assign' => ['karigar_id' => 'required_without:karigar_name|nullable|string', 'karigar_name' => 'required_without:karigar_id|nullable|string|max:120'],
            'status' => ['status' => 'required|in:measuring,pending_assignment,in_progress,ready,delivered,cancelled', 'note' => 'nullable|string|max:500'],
            'search' => ['query' => 'required|string|min:2|max:100'],
            default => [],
        };
    }
}
