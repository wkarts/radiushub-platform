<?php

namespace App\Services\Limits;

use App\Models\Company;
use App\Models\InternetPlan;
use App\Models\MikrotikDevice;
use App\Models\NetworkAccess;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UsageLimitService
{
    public function assertTenant(Tenant $tenant, string $resource, int $increment = 1): void
    {
        $this->assertLimit(
            $tenant->usage_limits ?? [],
            $resource,
            $this->tenantCount($tenant, $resource),
            $increment,
            'tenant',
        );
    }

    public function assertCompany(Company $company, string $resource, int $increment = 1): void
    {
        $this->assertTenant($company->tenant, $resource, $increment);
        $this->assertCompanyLocal($company, $resource, $increment);
    }

    public function assertCompanyLocal(Company $company, string $resource, int $increment = 1): void
    {
        $this->assertLimit(
            $company->usage_limits ?? [],
            $resource,
            $this->companyCount($company, $resource),
            $increment,
            'empresa',
        );
    }

    private function assertLimit(array $limits, string $resource, int $current, int $increment, string $scope): void
    {
        $limit = isset($limits[$resource]) ? (int) $limits[$resource] : 0;
        if ($limit <= 0 || ($current + $increment) <= $limit) return;

        throw ValidationException::withMessages([
            'usage_limit' => sprintf(
                'O limite de %s do %s foi atingido (%d de %d).',
                $this->label($resource),
                $scope,
                $current,
                $limit,
            ),
        ]);
    }

    private function tenantCount(Tenant $tenant, string $resource): int
    {
        return match ($resource) {
            'companies' => Company::query()->withoutGlobalScopes(['tenant', 'company'])->where('tenant_id', $tenant->id)->count(),
            'users' => DB::table('tenant_user')->where('tenant_id', $tenant->id)->distinct()->count('user_id'),
            'mikrotiks' => MikrotikDevice::query()->withoutGlobalScopes(['tenant', 'company'])->where('tenant_id', $tenant->id)->count(),
            'vouchers' => Voucher::query()->withoutGlobalScopes(['tenant', 'company'])->where('tenant_id', $tenant->id)->count(),
            'subscribers' => Subscriber::query()->withoutGlobalScopes(['tenant', 'company'])->where('tenant_id', $tenant->id)->count(),
            'plans' => InternetPlan::query()->withoutGlobalScopes(['tenant', 'company'])->where('tenant_id', $tenant->id)->count(),
            'accesses' => NetworkAccess::query()->withoutGlobalScopes(['tenant', 'company'])->where('tenant_id', $tenant->id)->count(),
            default => 0,
        };
    }

    private function companyCount(Company $company, string $resource): int
    {
        return match ($resource) {
            'users' => DB::table('company_user')->where('company_id', $company->id)->distinct()->count('user_id'),
            'mikrotiks' => MikrotikDevice::query()->withoutGlobalScopes(['tenant', 'company'])->where('company_id', $company->id)->count(),
            'vouchers' => Voucher::query()->withoutGlobalScopes(['tenant', 'company'])->where('company_id', $company->id)->count(),
            'subscribers' => Subscriber::query()->withoutGlobalScopes(['tenant', 'company'])->where('company_id', $company->id)->count(),
            'plans' => InternetPlan::query()->withoutGlobalScopes(['tenant', 'company'])->where('company_id', $company->id)->count(),
            'accesses' => NetworkAccess::query()->withoutGlobalScopes(['tenant', 'company'])->where('company_id', $company->id)->count(),
            default => 0,
        };
    }

    private function label(string $resource): string
    {
        return [
            'companies' => 'empresas',
            'users' => 'usuários',
            'mikrotiks' => 'equipamentos MikroTik',
            'vouchers' => 'vouchers',
            'subscribers' => 'clientes',
            'plans' => 'planos',
            'accesses' => 'acessos',
        ][$resource] ?? $resource;
    }
}
