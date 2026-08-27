<?php

use App\Http\Middleware\IdempotentRequest;
use App\Http\Middleware\RequirePlanCapacity;
use App\Http\Middleware\RequirePlanFeature;
use App\Http\Middleware\RequireSuperAdmin;
use App\Http\Middleware\RequireTenantRole;
use App\Http\Middleware\RequireVoiceAbility;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: null,
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['api', 'auth:sanctum', 'tenant']])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->append(SecurityHeaders::class);
        $middleware->alias(['tenant' => ResolveTenant::class, 'tenant.role' => RequireTenantRole::class, 'idempotent' => IdempotentRequest::class, 'voice.ability' => RequireVoiceAbility::class, 'super.admin' => RequireSuperAdmin::class, 'plan.feature' => RequirePlanFeature::class, 'plan.capacity' => RequirePlanCapacity::class]);
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
