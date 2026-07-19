<?php

namespace App\Console\Commands;

use App\Models\MikrotikDevice;
use App\Models\NetworkAccess;
use App\Services\Security\RadiusCredentialVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReencryptRadiusCredentials extends Command
{
    protected $signature = 'radiushub:credentials:reencrypt {--force : Executa sem confirmação}';
    protected $description = 'Regrava credenciais legadas no formato nativo do banco para leitura pelo FreeRADIUS.';

    public function handle(RadiusCredentialVault $vault): int
    {
        if (! $this->option('force') && ! $this->confirm('Recriptografar credenciais RADIUS usando a chave atual?')) {
            return self::SUCCESS;
        }

        $updated = 0;
        try {
            DB::transaction(function () use ($vault, &$updated): void {
                NetworkAccess::query()->withoutGlobalScopes()->orderBy('id')->each(function (NetworkAccess $access) use ($vault, &$updated): void {
                    if ($vault->isRadiusReadable((string) $access->password_ciphertext)) return;
                    $plain = $vault->decrypt((string) $access->password_ciphertext);
                    $access->forceFill(['password_ciphertext' => $vault->encrypt($plain)])->saveQuietly();
                    $updated++;
                });
                MikrotikDevice::query()->withoutGlobalScopes()->orderBy('id')->each(function (MikrotikDevice $device) use ($vault, &$updated): void {
                    if ($vault->isRadiusReadable((string) $device->radius_secret_ciphertext)) return;
                    $plain = $vault->decrypt((string) $device->radius_secret_ciphertext);
                    $device->forceFill(['radius_secret_ciphertext' => $vault->encrypt($plain)])->saveQuietly();
                    $updated++;
                });
            });
        } catch (Throwable $e) {
            $this->error('Falha: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info("{$updated} credenciais recriptografadas.");
        return self::SUCCESS;
    }
}
