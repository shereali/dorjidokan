<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthController extends Controller
{
    /** Readiness probe: database, cache, queue backlog and storage. Public and free of internal detail. */
    public function health(): JsonResponse
    {
        $checks = [
            'database' => $this->probe(fn () => DB::select('select 1') !== []),
            'cache' => $this->probe(function () {
                Cache::put('health:probe', '1', 10);

                return Cache::get('health:probe') === '1';
            }),
            'storage' => $this->probe(fn () => Storage::disk(config('filesystems.default'))->exists('.')),
        ];
        $backlog = $this->queueBacklog();
        $checks['queue'] = $backlog === null || $backlog < (int) config('monitoring.queue_backlog_warning') ? 'ok' : 'degraded';

        $failed = in_array('fail', $checks, true);
        $status = $failed ? 'fail' : (in_array('degraded', $checks, true) ? 'degraded' : 'ok');

        return response()->json(['status' => $status, 'checks' => $checks, 'time' => now()->toIso8601String()], $failed ? 503 : 200);
    }

    /** Prometheus text exposition. Requires `Authorization: Bearer <METRICS_TOKEN>`; disabled when no token is configured. */
    public function metrics(Request $request): Response
    {
        $token = config('monitoring.metrics_token');
        abort_if(! $token, 404);
        abort_unless(hash_equals((string) $token, (string) $request->bearerToken()), 401);

        $lines = [
            '# HELP tailors_workshops Registered workshops by status.',
            '# TYPE tailors_workshops gauge',
        ];
        $byStatus = Tenant::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        foreach (['active', 'inactive', 'hold', 'suspended'] as $status) {
            $lines[] = 'tailors_workshops{status="'.$status.'"} '.(int) ($byStatus[$status] ?? 0);
        }
        $lines[] = '# HELP tailors_orders_created_24h Orders created across all workshops in the last 24 hours.';
        $lines[] = '# TYPE tailors_orders_created_24h gauge';
        $lines[] = 'tailors_orders_created_24h '.(int) DB::table('orders')->where('created_at', '>=', now()->subDay())->count();
        $lines[] = '# HELP tailors_queue_pending_jobs Jobs waiting in the database queue.';
        $lines[] = '# TYPE tailors_queue_pending_jobs gauge';
        $lines[] = 'tailors_queue_pending_jobs '.($this->queueBacklog() ?? 0);
        $lines[] = '# HELP tailors_failed_jobs Failed jobs awaiting attention.';
        $lines[] = '# TYPE tailors_failed_jobs gauge';
        $lines[] = 'tailors_failed_jobs '.(Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0);

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8']);
    }

    /** Collects unhandled browser errors into the server log so they can be alerted on like backend errors. */
    public function clientError(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string',
            'stack' => 'nullable|string',
            'url' => 'nullable|string|max:300',
            'component' => 'nullable|string|max:120',
            'kind' => 'nullable|in:vue,unhandled,promise,resource',
        ]);
        Log::error('client_error', [
            'message' => mb_substr($data['message'], 0, (int) config('monitoring.client_error_max_message')),
            'stack' => isset($data['stack']) ? mb_substr($data['stack'], 0, (int) config('monitoring.client_error_max_stack')) : null,
            'url' => $data['url'] ?? null,
            'component' => $data['component'] ?? null,
            'kind' => $data['kind'] ?? 'unhandled',
            'user_id' => $request->user()?->getAuthIdentifier(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 200),
            'request_id' => $request->attributes->get('request_id'),
        ]);

        return response()->json(['data' => ['logged' => true], 'meta' => (object) [], 'errors' => []], 202);
    }

    private function probe(callable $check): string
    {
        try {
            return $check() ? 'ok' : 'fail';
        } catch (Throwable) {
            return 'fail';
        }
    }

    private function queueBacklog(): ?int
    {
        try {
            return config('queue.default') === 'database' && Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
