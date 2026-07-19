<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Tenant;
use App\Services\Security\SensitiveDataSanitizer;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    public function __construct(
        private readonly Request $request,
        private readonly TenantContext $tenant,
        private readonly CompanyContext $company,
        private readonly SensitiveDataSanitizer $sanitizer,
    ) {}

    public function record(
        string $action,
        ?Model $model = null,
        array $old = [],
        array $new = [],
        string $result = 'success',
        array $metadata = [],
    ): void {
        $tenantId = $this->tenant->id()
            ?? $model?->getAttribute('tenant_id')
            ?? (($model instanceof Tenant) ? $model->getKey() : null);

        $companyId = $this->company->id()
            ?? $model?->getAttribute('company_id')
            ?? (($model instanceof Company) ? $model->getKey() : null);

        AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'user_id' => $this->request->user()?->id,
            'action' => $action,
            'result' => $result,
            'auditable_type' => $model?->getMorphClass(),
            'auditable_id' => $model?->getKey(),
            'old_values' => $this->clean($old) ?: null,
            'new_values' => $this->clean($new) ?: null,
            'metadata' => $this->clean($metadata) ?: null,
            'request_id' => $this->request->headers->get('X-Request-ID') ?: (string) Str::uuid(),
            'ip_address' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    private function clean(array $values): array
    {
        $sensitive = ['password', 'password_confirmation', 'secret', 'token', 'private_key', 'passphrase', 'api_key', 'credentials'];

        foreach ($values as $key => $value) {
            $normalized = Str::lower((string) $key);
            if (collect($sensitive)->contains(fn (string $item): bool => str_contains($normalized, $item))) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->clean($value);
            } elseif (is_string($value)) {
                $values[$key] = $this->sanitizer->excerpt($value, 2000);
            }
        }

        return $values;
    }
}
