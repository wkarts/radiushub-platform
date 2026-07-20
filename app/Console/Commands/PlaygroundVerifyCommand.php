<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\MikrotikDevice;
use App\Models\NetworkAccess;
use App\Models\RadiusAccounting;
use App\Models\Tenant;
use App\Models\Voucher;
use App\Services\Mikrotik\MikrotikSshService;
use App\Services\Security\RadiusCredentialVault;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

final class PlaygroundVerifyCommand extends Command
{
    protected $signature = 'radiushub:playground:verify {--json : Retorna resultado em JSON} {--radius : Valida também a credencial e o NAS usados pelo FreeRADIUS} {--accounting-session= : Exige uma sessão de accounting criada pelo smoke RADIUS}';
    protected $description = 'Valida os dados, escopos e o simulador MikroTik do playground.';

    public function handle(MikrotikSshService $mikrotik): int
    {
        $result = [];

        try {
            if (! config('playground.enabled')) {
                throw new \RuntimeException('PLAYGROUND_MODE não está habilitado.');
            }

            $tenant = Tenant::query()->where('slug', config('playground.seed.tenant_slug'))->firstOrFail();
            $company = Company::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('document', config('playground.seed.company_document'))->firstOrFail();
            app(TenantContext::class)->set($tenant);
            app(CompanyContext::class)->set($company);

            $device = MikrotikDevice::query()->where('connection_method', 'simulator')->firstOrFail();
            $connection = $mikrotik->test($device);
            if (! ($connection['ok'] ?? false)) {
                throw new \RuntimeException((string) ($connection['error'] ?? 'Falha no simulador MikroTik.'));
            }

            $radiusResult = null;
            if ($this->option('radius')) {
                $access = NetworkAccess::query()
                    ->where('username', (string) config('playground.seed.network_username'))
                    ->firstOrFail();
                $vault = app(RadiusCredentialVault::class);

                if (! $device->active || $device->radius_source_ip !== (string) config('playground.seed.nas_ip_address')) {
                    throw new \RuntimeException('O NAS do playground não está ativo ou possui endereço RADIUS divergente.');
                }

                if (! $vault->isRadiusReadable((string) $access->password_ciphertext)) {
                    throw new \RuntimeException('A credencial do playground não está em formato legível pelo FreeRADIUS para o banco atual.');
                }

                $expectedPassword = (string) config('playground.seed.network_password');
                $actualPassword = $vault->decrypt((string) $access->password_ciphertext);
                if (! hash_equals($expectedPassword, $actualPassword)) {
                    throw new \RuntimeException('A credencial RADIUS persistida diverge da configuração do playground.');
                }

                $radiusResult = [
                    'username' => $access->username,
                    'nas_ip_address' => $device->radius_source_ip,
                    'credential_format' => str_contains((string) $access->password_ciphertext, ':')
                        ? strstr((string) $access->password_ciphertext, ':', true)
                        : 'legacy',
                ];
            }

            $accountingSession = trim((string) $this->option('accounting-session'));
            if ($accountingSession !== '' && ! RadiusAccounting::query()->where('acct_session_id', $accountingSession)->exists()) {
                throw new \RuntimeException('A sessão de accounting esperada não foi registrada: '.$accountingSession);
            }

            $result = [
                'ok' => true,
                'tenant' => $tenant->slug,
                'company' => $company->trade_name ?: $company->legal_name,
                'accesses' => NetworkAccess::query()->count(),
                'vouchers' => Voucher::query()->count(),
                'accounting_session' => $accountingSession !== '' ? $accountingSession : null,
                'mikrotik' => $connection['identity'] ?? [],
                'radius' => $radiusResult,
                'version' => config('app.version'),
            ];
        } catch (Throwable $exception) {
            $result = ['ok' => false, 'error' => $exception->getMessage()];
        } finally {
            app(CompanyContext::class)->clear();
            app(TenantContext::class)->clear();
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } elseif ($result['ok']) {
            $this->info('Playground validado com sucesso.');
            $this->table(['Item', 'Valor'], [
                ['Tenant', $result['tenant']],
                ['Empresa', $result['company']],
                ['Acessos', $result['accesses']],
                ['Vouchers', $result['vouchers']],
                ['MikroTik', ($result['mikrotik']['identity'] ?? 'simulado').' / '.($result['mikrotik']['version'] ?? '-')],
                ['Versão', $result['version']],
            ]);
        } else {
            $this->error((string) $result['error']);
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
