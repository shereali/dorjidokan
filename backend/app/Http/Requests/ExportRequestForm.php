<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['type' => 'required|in:summary,customer_statement,tenant_data', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'customer_id' => 'required_if:type,customer_statement|nullable|string|size:26', 'email' => 'nullable|email'];
    }
}
