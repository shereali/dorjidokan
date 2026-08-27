<?php

namespace App\Support;

use App\Models\Tenant;
use RuntimeException;

final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): Tenant
    {
        return $this->tenant ?? throw new RuntimeException('Tenant context has not been established.');
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
