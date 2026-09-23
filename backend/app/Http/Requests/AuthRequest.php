<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['tenant' => 'nullable|string|max:80', 'email' => 'required|email', 'password' => 'required|string', 'two_factor_code' => 'nullable|string|max:64'];
    }
}
