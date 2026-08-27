<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlatformMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'register' => ['business_name' => 'required|string|max:120', 'slug' => 'required|alpha_dash|max:80|unique:tenants,slug', 'name' => 'required|string|max:120', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:12|confirmed', 'locale' => 'nullable|in:bn,en'],
            'suspend' => ['status' => 'required|in:active,suspended'],
            'saveMember' => ['name' => 'required|string|max:120', 'email' => 'required|email|unique:users,email', 'role' => 'required|in:admin,manager,staff'],
            'updateMember' => ['role' => 'required|in:admin,manager,staff'],
            'checkout' => ['plan_code' => 'required|string|exists:plans,code'],
            'settings' => ['name' => 'sometimes|required|string|max:120', 'default_locale' => 'sometimes|required|in:bn,en', 'currency' => 'sometimes|required|string|size:3', 'settings' => 'sometimes|array', 'settings.order_prefix' => 'sometimes|string|max:12', 'settings.default_delivery_days' => 'sometimes|integer|min:0|max:365', 'settings.low_stock_alerts' => 'sometimes|boolean', 'settings.order_ready_notifications' => 'sometimes|boolean'],
            default => [],
        };
    }
}
