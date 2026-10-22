<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TenantRole;
use App\Services\PermissionService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(private PermissionService $permissions) {}

    public function index(): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();
        $roles = collect(config('permissions.roles'))->map(fn (array $meta, string $key) => [
            'key' => $key,
            'name' => $meta['name'],
            'name_bn' => $meta['name_bn'],
            'locked' => $meta['locked'],
            'permissions' => $this->permissions->forRole($tenant, $key),
            'customized' => TenantRole::where('tenant_id', $tenant->id)->where('role_key', $key)->exists(),
        ])->values();

        return $this->ok([
            'roles' => $roles,
            'groups' => config('permissions.groups'),
            'owner_only' => config('permissions.owner_only'),
        ]);
    }

    public function update(Request $request, string $role): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();
        $key = $this->editableRole($role);
        $data = $request->validate(['permissions' => 'present|array', 'permissions.*' => 'string|in:'.implode(',', $this->permissions->allPermissions())]);
        $saved = $this->permissions->setRole($tenant, $key, $data['permissions']);
        AuditLog::create(['tenant_id' => $tenant->id, 'user_id' => $request->user()->id, 'action' => 'role.permissions_updated', 'context' => ['role' => $key, 'permissions' => $saved], 'ip_address' => $request->ip(), 'created_at' => now()]);

        return $this->ok(['role' => ['key' => $key, 'permissions' => $saved]]);
    }

    public function reset(Request $request, string $role): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();
        $key = $this->editableRole($role);
        $this->permissions->resetRole($tenant, $key);
        AuditLog::create(['tenant_id' => $tenant->id, 'user_id' => $request->user()->id, 'action' => 'role.permissions_reset', 'context' => ['role' => $key], 'ip_address' => $request->ip(), 'created_at' => now()]);

        return $this->ok(['role' => ['key' => $key, 'permissions' => $this->permissions->forRole($tenant, $key)]]);
    }

    private function editableRole(string $role): string
    {
        $roles = config('permissions.roles');
        abort_unless(isset($roles[$role]), 404);
        abort_if($roles[$role]['locked'], 422, 'The owner role always has every permission.');

        return $role;
    }

    private function ok(array $data): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => (object) [], 'errors' => []]);
    }
}
