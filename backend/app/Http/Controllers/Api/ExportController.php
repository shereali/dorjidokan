<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportRequestForm;
use App\Http\Requests\TenantDeletionForm;
use App\Jobs\GenerateExport;
use App\Models\ExportRequest;
use App\Models\TenantDeletionRequest;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->ok(['items' => ExportRequest::latest('id')->limit(100)->get()->map(fn ($export) => $this->dto($export))]);
    }

    public function store(ExportRequestForm $request): JsonResponse
    {
        $data = $request->validated();
        $export = ExportRequest::create(['requested_by' => $request->user()->id, 'type' => $data['type'], 'filters' => collect($data)->only(['from', 'to', 'customer_id'])->filter()->all(), 'email' => $data['email'] ?? null, 'status' => 'queued']);
        GenerateExport::dispatch($export->id);

        return $this->ok(['export' => $this->dto($export)], [], 202);
    }

    public function download(ExportRequest $export)
    {
        $disk = Storage::disk(config('filesystems.default'));
        abort_unless($export->status === 'completed' && $export->storage_path && $disk->exists($export->storage_path), 404);
        $extension = pathinfo($export->storage_path, PATHINFO_EXTENSION);

        return $disk->download($export->storage_path, "tailors-{$export->type}.{$extension}", ['Content-Type' => $export->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }

    public function deletionStatus(): JsonResponse
    {
        $deletion = TenantDeletionRequest::where('status', 'scheduled')->latest('id')->first();

        return $this->ok(['deletion' => $deletion ? ['id' => $deletion->public_id, 'status' => $deletion->status, 'scheduled_for' => $deletion->scheduled_for->toIso8601String()] : null]);
    }

    public function scheduleDeletion(TenantDeletionForm $request): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();
        $data = $request->validated();
        if ($data['tenant_slug'] !== $tenant->slug || ! Hash::check($data['password'], $request->user()->password)) {
            return $this->validationError('confirmation', 'The workshop slug or password is invalid.');
        }
        if (TenantDeletionRequest::where('status', 'scheduled')->exists()) {
            return $this->validationError('deletion', 'Deletion is already scheduled.');
        }
        $deletion = TenantDeletionRequest::create(['requested_by' => $request->user()->id, 'status' => 'scheduled', 'scheduled_for' => now()->addDays(7)]);
        $export = ExportRequest::create(['requested_by' => $request->user()->id, 'type' => 'tenant_data', 'filters' => [], 'status' => 'queued']);
        GenerateExport::dispatch($export->id);

        return $this->ok(['deletion' => ['id' => $deletion->public_id, 'status' => $deletion->status, 'scheduled_for' => $deletion->scheduled_for->toIso8601String()], 'export' => $this->dto($export)], [], 202);
    }

    public function cancelDeletion(Request $request, TenantDeletionRequest $deletion): JsonResponse
    {
        abort_unless($deletion->status === 'scheduled', 409);
        $deletion->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return $this->ok(['cancelled' => true]);
    }

    private function dto(ExportRequest $export): array
    {
        return ['id' => $export->public_id, 'type' => $export->type, 'status' => $export->status, 'email' => $export->email, 'failure_message' => $export->failure_message, 'completed_at' => $export->completed_at?->toIso8601String(), 'download_path' => $export->status === 'completed' ? "/exports/{$export->public_id}/download" : null];
    }

    private function ok(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => (object) $meta, 'errors' => []], $status);
    }

    private function validationError(string $field, string $message): JsonResponse
    {
        return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'validation', 'field' => $field, 'message' => $message]]], 422);
    }
}
