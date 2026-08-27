<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebhookMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'store' => ['url' => 'required|url|max:500', 'events' => 'required|array|min:1', 'events.*' => 'required|in:order.ready,order.delivered,order.cancelled,customer.created,payment.received', 'active' => 'sometimes|boolean'],
            'update' => ['url' => 'sometimes|required|url|max:500', 'events' => 'sometimes|required|array|min:1', 'events.*' => 'required|in:order.ready,order.delivered,order.cancelled,customer.created,payment.received', 'active' => 'sometimes|boolean'],
            'rotateSecret' => [],
            default => [],
        };
    }
}
