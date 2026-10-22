<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;

class PermissionService
{
    public function normalize(?string $role): string
    {
        $role = strtolower((string) $role);

        return config('permissions.aliases')[$role] ?? $role;
    }

    /** @return list<string> */
    public function roleKeys(): array
    {
        return array_keys(config('permissions.roles'));
    }

    /** @return list<string> */
    public function allPermissions(): array
    {
        return array_merge(...array_values(config('permissions.groups')));
    }

    /** @return list<string> */
    public function defaultsFor(string $role): array
    {
        return config('permissions.defaults')[$role] ?? [];
    }

    /** @return list<string> */
    public function forRole(Tenant $tenant, ?string $role): array
    {
        $role = $this->normalize($role);
        if ($role === 'owner') {
            return $this->allPermissions();
        }
        if (! in_array($role, $this->roleKeys(), true)) {
            return [];
        }
        $override = TenantRole::where('tenant_id', $tenant->id)->where('role_key', $role)->value('permissions');

        return $this->sanitize($override ?? $this->defaultsFor($role));
    }

    /** Drops unknown keys and owner-only permissions so a matrix edit cannot escalate privileges. */
    public function sanitize(array $permissions): array
    {
        $allowed = array_diff($this->allPermissions(), config('permissions.owner_only'));

        return array_values(array_intersect($allowed, array_unique($permissions)));
    }

    public function roleOf(User $user, Tenant $tenant): ?string
    {
        $membership = $user->tenants()->whereKey($tenant->id)->first();

        return $membership ? $this->normalize($membership->pivot->role) : null;
    }

    /** @return list<string> */
    public function forUser(User $user, Tenant $tenant): array
    {
        $role = $this->roleOf($user, $tenant);

        return $role ? $this->forRole($tenant, $role) : [];
    }

    public function setRole(Tenant $tenant, string $role, array $permissions): array
    {
        $clean = $this->sanitize($permissions);
        TenantRole::updateOrCreate(['tenant_id' => $tenant->id, 'role_key' => $role], ['permissions' => $clean]);

        return $clean;
    }

    public function resetRole(Tenant $tenant, string $role): void
    {
        TenantRole::where('tenant_id', $tenant->id)->where('role_key', $role)->delete();
    }
}
