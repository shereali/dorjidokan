<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'confirmTwoFactor' => ['code' => 'required|string|max:64'],
            'disableTwoFactor' => ['password' => 'required|string'],
            default => [],
        };
    }
}
