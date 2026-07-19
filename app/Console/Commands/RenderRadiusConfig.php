<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RenderRadiusConfig extends Command
{
    protected $signature = 'radiushub:radius:render
        {--output=storage/app/freeradius-generated : Diretório de saída}
        {--force : Sobrescreve o diretório existente}';

    protected $description = 'Renderiza uma árvore de configuração FreeRADIUS compatível com MySQL ou PostgreSQL.';

    public function handle(): int
    {
        $driver = DB::connection()->getDriverName();
        $dialect = match ($driver) {
            'pgsql' => 'postgresql',
            'mysql' => 'mysql',
            default => throw new RuntimeException("Driver {$driver} não suportado pelo FreeRADIUS."),
        };

        $output = (string) $this->option('output');
        $output = str_starts_with($output, DIRECTORY_SEPARATOR)
            ? $output
            : base_path($output);

        if (is_dir($output) && ! $this->option('force')) {
            $this->error("O diretório {$output} já existe. Use --force para sobrescrever.");
            return self::FAILURE;
        }

        if (is_dir($output)) {
            $this->deleteDirectory($output);
        }

        $values = [
            '@@DB_HOST@@' => $this->escapeRadiusString((string) config("database.connections.{$driver}.host")),
            '@@DB_PORT@@' => (string) config("database.connections.{$driver}.port"),
            '@@DB_DATABASE@@' => $this->escapeRadiusString((string) config("database.connections.{$driver}.database")),
            '@@DB_USERNAME@@' => $this->escapeRadiusString((string) config("database.connections.{$driver}.username")),
            '@@DB_PASSWORD@@' => $this->escapeRadiusString((string) config("database.connections.{$driver}.password")),
            '@@RADIUS_CREDENTIAL_KEY@@' => $this->requiredConfig('radius.credential_key'),
            '@@RADIUS_LOCAL_SECRET@@' => $this->escapeRadiusString($this->requiredConfig('radius.local_secret')),
        ];

        $files = [
            resource_path("freeradius/{$dialect}/sql") => "{$output}/mods-enabled/sql",
            resource_path("freeradius/{$dialect}/queries.conf") => "{$output}/mods-config/sql/main/{$dialect}/queries.conf",
            resource_path('freeradius/common/clients.conf') => "{$output}/clients.conf",
            resource_path('freeradius/common/default') => "{$output}/sites-enabled/default",
        ];

        foreach ($files as $source => $destination) {
            if (! is_file($source)) {
                throw new RuntimeException("Template FreeRADIUS não encontrado: {$source}");
            }

            $directory = dirname($destination);
            if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
                throw new RuntimeException("Não foi possível criar {$directory}");
            }

            $rendered = strtr((string) file_get_contents($source), $values);
            if (preg_match('/@@[A-Z0-9_]+@@/', $rendered)) {
                throw new RuntimeException("Existem placeholders não resolvidos em {$source}");
            }

            file_put_contents($destination, $rendered, LOCK_EX);
            chmod($destination, str_contains($destination, 'sites-enabled') ? 0640 : 0600);
        }

        file_put_contents(
            "{$output}/README.txt",
            "Configuração gerada pelo RadiusHub ".config('app.version')." para {$dialect}.\n".
            "Contém segredos. Mantenha permissões restritas e não versione este diretório.\n",
            LOCK_EX,
        );
        chmod("{$output}/README.txt", 0600);

        $this->components->info("Configuração FreeRADIUS gerada em {$output}");
        $this->line("Dialeto: {$dialect}");

        return self::SUCCESS;
    }

    private function escapeRadiusString(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function requiredConfig(string $key): string
    {
        $value = trim((string) config($key));
        if ($value === '' || str_starts_with($value, 'change-this')) {
            throw new RuntimeException("Configuração obrigatória inválida: {$key}");
        }

        return $value;
    }

    private function deleteDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
