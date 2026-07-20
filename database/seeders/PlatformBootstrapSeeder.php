<?php

namespace Database\Seeders;

use App\Services\Platform\PlatformBootstrapService;
use Illuminate\Database\Seeder;

final class PlatformBootstrapSeeder extends Seeder
{
    public function run(PlatformBootstrapService $bootstrap): void
    {
        $result = $bootstrap->ensure();

        if (! ($result['enabled'] ?? false)) {
            $this->command?->warn('PLATFORM_BOOTSTRAP_ENABLED está desabilitado; contexto inicial não foi reconciliado.');

            return;
        }

        $this->command?->info(sprintf(
            'Bootstrap da plataforma concluído: %s / %s / %s.',
            $result['admin']->email,
            $result['tenant']?->name ?? 'sem tenant padrão',
            $result['company']?->legal_name ?? 'sem empresa padrão',
        ));
    }
}
