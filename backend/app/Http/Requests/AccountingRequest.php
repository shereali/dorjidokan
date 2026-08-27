<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccountingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['type' => 'required|in:charge,credit', 'amount_minor' => 'required|integer|min:1', 'memo' => 'required|string|max:500'];
    }
}
