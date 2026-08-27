<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PasswordRecoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'forgotPassword' => ['tenant' => 'required|string|max:80', 'email' => 'required|email'],
            'resetPassword' => ['tenant' => 'required|string|max:80', 'email' => 'required|email', 'token' => 'required|string', 'password' => 'required|string|min:12|confirmed'],
            default => [],
        };
    }
}
