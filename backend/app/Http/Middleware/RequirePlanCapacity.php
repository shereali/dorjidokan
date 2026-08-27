<?php

namespace App\Http\Middleware;

use App\Services\FeatureGateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePlanCapacity
{
    public function __construct(private FeatureGateService $features) {}

    public function handle(Request $request, Closure $next, string $metric): Response
    {
        if (! $this->features->hasCapacity($metric)) {
            return $this->features->denial('plan_limit_reached', "The {$metric} limit for this plan has been reached.");
        }

        return $next($request);
    }
}
