<?php

namespace App\Console\Commands;

use App\Services\Platform\PlatformBootstrapService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

final class BootstrapPlatform extends Command
{
    protected $signature = 'radiushub:bootstrap-platform
        {--admin-email= : E-mail do Superadministrador a criar ou reconciliar}
        {--admin-login= : Login do Superadministrador}
        {--admin-name= : Nome do Superadministrador}
        {--admin-password= : Nova senha; use junto de --force-password em usuários existentes}
        {--force-password : Redefine a senha do usuário existente}
        {--json : Retorna o resultado em JSON}';

    protected $description = 'Cria ou repara o Superadministrador, tenant e empresa iniciais da plataforma.';

    public function handle(PlatformBootstrapService $bootstrap): int
    {
        try {
            Artisan::call('db:seed', [
                '--class' => RolePermissionSeeder::class,
                '--force' => true,
            ]);

            $overrides = array_filter([
                'admin_email' => $this->option('admin-email'),
                'admin_login' => $this->option('admin-login'),
                'admin_name' => $this->option('admin-name'),
                'admin_password' => $this->option('admin-password'),
                'force_password' => (bool) $this->option('force-password'),
            ], static fn (mixed $value, string $key): bool => $key === 'force_password' || ($value !== null && $value !== ''), ARRAY_FILTER_USE_BOTH);

            $result = $bootstrap->ensure($overrides);
            $payload = [
                'ok' => true,
                'enabled' => (bool) ($result['enabled'] ?? false),
                'admin' => isset($result['admin']) ? [
                    'id' => $result['admin']->id,
                    'name' => $result['admin']->name,
                    'email' => $result['admin']->email,
                    'login' => $result['admin']->login,
                    'is_super_admin' => (bool) $result['admin']->is_super_admin,
                ] : null,
                'tenant' => isset($result['tenant']) && $result['tenant'] ? [
                    'id' => $result['tenant']->id,
                    'name' => $result['tenant']->name,
                    'slug' => $result['tenant']->slug,
                ] : null,
                'company' => isset($result['company']) && $result['company'] ? [
                    'id' => $result['company']->id,
                    'name' => $result['company']->trade_name ?: $result['company']->legal_name,
                ] : null,
                'active_super_admins' => (int) ($result['super_admins'] ?? 0),
            ];

            if ($this->option('json')) {
                $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                $this->info('Bootstrap da plataforma validado com sucesso.');
                $this->table(['Item', 'Valor'], [
                    ['Superadministrador', ($payload['admin']['name'] ?? '—').' <'.($payload['admin']['email'] ?? '—').'>'],
                    ['Login', $payload['admin']['login'] ?? '—'],
                    ['Tenant inicial', $payload['tenant']['name'] ?? 'desabilitado'],
                    ['Empresa inicial', $payload['company']['name'] ?? 'desabilitada'],
                    ['Superadministradores ativos', (string) $payload['active_super_admins']],
                ]);
                $this->newLine();
                $this->line('A senha existente foi preservada, salvo quando --force-password foi informado.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($this->option('json')) {
                $this->line((string) json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->error($exception->getMessage());
            }

            return self::FAILURE;
        }
    }
}
