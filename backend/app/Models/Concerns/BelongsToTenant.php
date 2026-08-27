<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            if ($tenantId = app(TenantContext::class)->id()) {
                $builder->where($builder->qualifyColumn('tenant_id'), $tenantId);
            }
        });

        static::creating(function (Model $model): void {
            if (! $model->getAttribute('tenant_id') && $tenantId = app(TenantContext::class)->id()) {
                $model->setAttribute('tenant_id', $tenantId);
            }
        });
    }
}
