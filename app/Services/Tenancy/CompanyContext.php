<?php

namespace App\Services\Tenancy;

use App\Models\Company;
use RuntimeException;

final class CompanyContext
{
    private ?Company $company = null;

    public function set(?Company $company): void { $this->company = $company; }
    public function company(): ?Company { return $this->company; }
    public function id(): ?string { return $this->company?->getKey(); }
    public function requireId(): string { return $this->id() ?? throw new RuntimeException('Nenhuma empresa foi definida para a requisição atual.'); }
    public function clear(): void { $this->company = null; }
}
