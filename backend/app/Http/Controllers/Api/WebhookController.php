<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebhookMutationRequest;
use App\Models\AuditLog;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->ok(['items' => WebhookEndpoint::latest('id')->get()->map(fn ($endpoint) => $this->dto($endpoint)), 'deliveries' => WebhookDelivery::latest('id')->limit(100)->get()->map(fn ($delivery) => ['id' => $delivery->id, 'event_name' => $delivery->event_name, 'attempt' => $delivery->attempt, 'response_status' => $delivery->response_status, 'error' => $delivery->error, 'delivered_at' => $delivery->delivered_at?->toIso8601String()])]);
    }

    public function store(WebhookMutationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $endpoint = WebhookEndpoint::create([...$data, 'signing_secret' => Str::random(64), 'active' => $data['active'] ?? true]);
        AuditLog::create(['tenant_id' => $endpoint->tenant_id, 'user_id' => $request->user()->id, 'action' => 'webhook.created', 'subject_type' => WebhookEndpoint::class, 'subject_id' => $endpoint->id, 'ip_address' => $request->ip(), 'created_at' => now()]);

        return $this->ok(['endpoint' => $this->dto($endpoint), 'signing_secret' => $endpoint->signing_secret], [], 201);
    }

    public function update(WebhookMutationRequest $request, WebhookEndpoint $endpoint): JsonResponse
    {
        $endpoint->update($request->validated());

        return $this->ok(['endpoint' => $this->dto($endpoint->fresh())]);
    }

    public function destroy(Request $request, WebhookEndpoint $endpoint): JsonResponse
    {
        $endpoint->delete();
        AuditLog::create(['tenant_id' => $endpoint->tenant_id, 'user_id' => $request->user()->id, 'action' => 'webhook.deleted', 'subject_type' => WebhookEndpoint::class, 'subject_id' => $endpoint->id, 'ip_address' => $request->ip(), 'created_at' => now()]);

        return $this->ok(['deleted' => true]);
    }

    public function rotateSecret(WebhookMutationRequest $request, WebhookEndpoint $endpoint): JsonResponse
    {
        $endpoint->update(['signing_secret' => Str::random(64)]);
        AuditLog::create(['tenant_id' => $endpoint->tenant_id, 'user_id' => $request->user()->id, 'action' => 'webhook.secret_rotated', 'subject_type' => WebhookEndpoint::class, 'subject_id' => $endpoint->id, 'ip_address' => $request->ip(), 'created_at' => now()]);

        return $this->ok(['signing_secret' => $endpoint->fresh()->signing_secret]);
    }

    private function dto(WebhookEndpoint $endpoint): array
    {
        return ['id' => $endpoint->id, 'url' => $endpoint->url, 'events' => $endpoint->events, 'active' => $endpoint->active, 'has_secret' => (bool) $endpoint->signing_secret, 'created_at' => $endpoint->created_at?->toIso8601String()];
    }

    private function ok(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => (object) $meta, 'errors' => []], $status);
    }
}
