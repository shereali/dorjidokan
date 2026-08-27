<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IdempotentRequest
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || strlen($key) < 16 || strlen($key) > 100) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'idempotency_key_required', 'message' => 'A 16–100 character Idempotency-Key is required.']]], 422);
        }$tenant = app(TenantContext::class)->get();
        $operation = $request->method().' '.$request->route()->uri();
        $hash = hash('sha256', $request->getContent());
        $old = DB::table('idempotency_keys')->where(['tenant_id' => $tenant->id, 'key' => $key, 'operation' => $operation])->first();
        if ($old) {
            if ($old->request_hash !== $hash) {
                return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'idempotency_conflict', 'message' => 'The key was used with another payload.']]], 409);
            }

            return response()->json(json_decode($old->response_body, true), $old->response_status);
        }$response = $next($request);
        if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
            DB::table('idempotency_keys')->insert(['tenant_id' => $tenant->id, 'key' => $key, 'operation' => $operation, 'request_hash' => $hash, 'response_status' => $response->getStatusCode(), 'response_body' => $response->getContent(), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        }

        return $response;
    }
}
