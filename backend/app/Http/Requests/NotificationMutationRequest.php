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
            'occasionCampaign' => ['name' => 'required|string|max:120', 'occasion' => 'nullable|string|max:80', 'channel' => 'required|in:sms', 'body' => 'required|string|max:1000', 'consent_confirmed' => 'accepted', 'scheduled_at' => 'nullable|date|after:now'],
            'scheduleDeliveryReminder' => ['channel' => 'sometimes|in:sms,email', 'scheduled_at' => 'nullable|date'],
            default => [],
        };
    }
}
