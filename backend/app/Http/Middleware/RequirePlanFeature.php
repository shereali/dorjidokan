<?php

namespace App\Http\Middleware;

use App\Services\FeatureGateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePlanFeature
{
    public function __construct(private FeatureGateService $features) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $this->features->allows($feature)) {
            return $this->features->denial('feature_unavailable', "Your current plan does not include {$feature}.");
        }

        return $next($request);
    }
}
