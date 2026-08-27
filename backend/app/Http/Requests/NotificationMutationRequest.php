<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotificationMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'saveTemplate' => ['event' => 'required|in:order.ready,order.reminder', 'channel' => 'required|in:sms,email', 'name' => 'required|string|max:120', 'body' => 'required|string|max:1000', 'active' => 'sometimes|boolean'],
            'campaign' => ['name' => 'required|string|max:120', 'channel' => 'required|in:sms', 'body' => 'required|string|max:1000', 'consent_confirmed' => 'accepted'],
            default => [],
        };
    }
}
