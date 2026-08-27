<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationMutationRequest;
use App\Jobs\SendNotificationDelivery;
use App\Models\Customer;
use App\Models\NotificationCampaign;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->ok(['templates' => NotificationTemplate::orderBy('event')->get()->map(fn ($template) => $this->template($template)), 'campaigns' => NotificationCampaign::latest('id')->limit(50)->get()->map(fn ($campaign) => ['id' => $campaign->public_id, 'name' => $campaign->name, 'channel' => $campaign->channel, 'status' => $campaign->status, 'recipient_count' => $campaign->recipient_count, 'queued_at' => $campaign->queued_at?->toIso8601String()]), 'deliveries' => NotificationDelivery::latest('id')->limit(100)->get()->map(fn ($delivery) => ['id' => $delivery->public_id, 'event' => $delivery->event, 'channel' => $delivery->channel, 'recipient' => $delivery->recipient, 'status' => $delivery->status, 'sent_at' => $delivery->sent_at?->toIso8601String(), 'error' => $delivery->error])]);
    }

    public function saveTemplate(NotificationMutationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $template = NotificationTemplate::updateOrCreate(['event' => $data['event'], 'channel' => $data['channel']], $data);

        return $this->ok(['template' => $this->template($template)], [], $template->wasRecentlyCreated ? 201 : 200);
    }

    public function campaign(NotificationMutationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $recipients = Customer::where('marketing_consent', true)->whereNotNull('mobile_number')->get();
        $campaign = DB::transaction(function () use ($request, $data, $recipients) {
            $campaign = NotificationCampaign::create(['name' => $data['name'], 'channel' => $data['channel'], 'body' => $data['body'], 'status' => 'queued', 'recipient_count' => $recipients->count(), 'created_by' => $request->user()->id, 'queued_at' => now()]);
            foreach ($recipients as $customer) {
                $recipient = $customer->mobile_number;
                $delivery = NotificationDelivery::create(['event' => 'campaign.'.$campaign->public_id, 'channel' => $data['channel'], 'recipient' => $recipient, 'body' => strtr($data['body'], ['{{customer_name}}' => $customer->name]), 'status' => 'queued']);
                DB::afterCommit(fn () => SendNotificationDelivery::dispatch($delivery->id));
            }

            return $campaign;
        });

        return $this->ok(['campaign' => ['id' => $campaign->public_id, 'recipient_count' => $campaign->recipient_count, 'status' => $campaign->status]], [], 201);
    }

    private function template(NotificationTemplate $template): array
    {
        return ['id' => $template->public_id, 'event' => $template->event, 'channel' => $template->channel, 'name' => $template->name, 'body' => $template->body, 'active' => $template->active];
    }

    private function ok(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => (object) $meta, 'errors' => []], $status);
    }
}
