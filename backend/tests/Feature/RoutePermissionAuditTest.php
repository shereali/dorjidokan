<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Guards against the original gap: a tenant route added without a permission check.
 * Every route behind the `tenant` middleware must also carry `permission:` — except the explicit own-account list.
 */
class RoutePermissionAuditTest extends TestCase
{
    /** Routes any signed-in workshop member may call about their own account. */
    private const OWN_ACCOUNT = ['api/v1/auth/me', 'api/v1/auth/two-factor', 'api/v1/auth/two-factor/setup', 'api/v1/auth/two-factor/confirm'];

    private function tenantRoutes(): array
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => in_array('tenant', $route->gatherMiddleware(), true) && str_starts_with($route->uri(), 'api/v1/') && ! str_starts_with($route->uri(), 'api/v1/voice'))
            ->all();
    }

    public function test_every_tenant_route_is_permission_guarded(): void
    {
        $unguarded = [];
        foreach ($this->tenantRoutes() as $route) {
            $guarded = collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'));
            if (! $guarded && ! in_array($route->uri(), self::OWN_ACCOUNT, true)) {
                $unguarded[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        $this->assertSame([], $unguarded, 'These tenant routes have no permission middleware.');
    }

    public function test_every_permission_used_by_a_route_exists_in_the_catalog(): void
    {
        $catalog = collect(config('permissions.groups'))->flatten()->all();
        $unknown = [];
        foreach ($this->tenantRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'permission:')) {
                    foreach (explode(',', substr($middleware, 11)) as $permission) {
                        if (! in_array($permission, $catalog, true)) {
                            $unknown[] = $permission.' on '.$route->uri();
                        }
                    }
                }
            }
        }

        $this->assertSame([], $unknown);
    }

    public function test_super_admin_routes_require_the_super_admin_middleware(): void
    {
        $open = [];
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/v1/super-admin') && ! in_array('super.admin', $route->gatherMiddleware(), true)) {
                $open[] = $route->uri();
            }
        }

        $this->assertSame([], $open);
    }

    public function test_default_roles_only_use_known_permissions_and_never_hold_owner_only_ones(): void
    {
        $catalog = collect(config('permissions.groups'))->flatten()->all();
        foreach (config('permissions.defaults') as $role => $permissions) {
            $this->assertSame([], array_diff($permissions, $catalog), "$role has unknown permissions");
            if ($role !== 'owner') {
                $this->assertSame([], array_intersect($permissions, config('permissions.owner_only')), "$role holds owner-only permissions");
            }
        }
    }
}
