<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;
use RuntimeException;

final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->getKey();
    }

    public function requireId(): string
    {
        return $this->id() ?? throw new RuntimeException('Nenhum tenant foi definido para a requisição atual.');
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
