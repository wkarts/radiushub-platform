<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$read = static function (string $relative) use ($root, &$errors): string {
    $path = $root.'/'.$relative;

    if (! is_file($path)) {
        $errors[] = "Arquivo obrigatório ausente: {$relative}";

        return '';
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        $errors[] = "Não foi possível ler: {$relative}";

        return '';
    }

    return $contents;
};

$version = trim($read('VERSION'));

if (! preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version)) {
    $errors[] = "VERSION inválido: {$version}";
}

$expectLine = static function (
    string $relative,
    string $prefix,
    string $expected
) use ($read, &$errors): void {
    $contents = $read($relative);

    if ($contents === '') {
        return;
    }

    if (! preg_match('/^'.preg_quote($prefix, '/').'(.*)$/m', $contents, $match)) {
        $errors[] = "{$relative}: variável {$prefix} não encontrada";

        return;
    }

    $actual = trim($match[1]);

    if ($actual !== $expected) {
        $errors[] = "{$relative}: {$prefix}{$actual}; esperado {$prefix}{$expected}";
    }
};

foreach ([
    '.env.example',
    '.env.cloudpanel.example',
    '.env.docker.mysql.example',
    '.env.docker.postgres.example',
    '.env.playground.example',
    '.env.cloudpanel.playground.example',
] as $file) {
    $expectLine($file, 'APP_VERSION=', $version);
}

foreach ([
    '.env.docker.mysql.example',
    '.env.docker.postgres.example',
    '.env.playground.example',
] as $file) {
    $expectLine($file, 'RADIUSHUB_TAG=', $version);
}

$checks = [
    'config/app.php' => ["env('APP_VERSION', '{$version}')"],
    'docker/app/Dockerfile' => ["ARG VERSION=\"{$version}\""],
    'docker/nginx/Dockerfile' => ["ARG VERSION=\"{$version}\""],
    'docker/freeradius/Dockerfile' => ["ARG VERSION=\"{$version}\""],
    'docker-compose.yml' => [
        "radiushub-app:\${RADIUSHUB_TAG:-{$version}}",
        "radiushub-web:\${RADIUSHUB_TAG:-{$version}}",
        "radiushub-freeradius:\${RADIUSHUB_TAG:-{$version}}",
    ],
    'docker-compose.playground.yml' => [
        'PLAYGROUND_MODE:',
        'PLAYGROUND_MIKROTIK_SIMULATOR:',
        '127.0.0.1',
    ],
    'scripts/playground.sh' => [
        'radiushub:playground:verify',
        '/health/ready',
        'docker-compose.playground.yml',
        'smoke-radius.sh',
    ],
    'scripts/smoke-radius.sh' => [
        'Access-Accept',
        'Accounting-Response',
        'radiushub:playground:verify',
    ],
    'scripts/validate-deployment.sh' => [
        'radiushub:health --ready',
        'radiushub:doctor',
        'DEPLOYMENT_VALIDATION_OK',
    ],
    '.dockerignore' => [
        '!.env.playground.example',
        '!.env.cloudpanel.playground.example',
    ],
    '.gitignore' => [
        '!/.env.playground.example',
        '!/.env.cloudpanel.playground.example',
    ],
    '.github/workflows/ci.yml' => [
        'test -f .env.playground.example',
        'test -f .env.cloudpanel.playground.example',
        'chmod +x scripts/*.sh artisan',
        'bash ./scripts/playground.sh',
        'bash ./scripts/install-cloudpanel-playground.sh',
        'Construir imagem FreeRADIUS',
        'Docker Playground / smoke completo',
        'CloudPanel nativo / smoke de aplicação',
    ],
    'docker/freeradius/entrypoint.sh' => [
        'detect_config_root',
        'radiusd.conf',
        'Ignoring "sql"',
        'Loaded module rlm_sql',
    ],
    'scripts/install-freeradius-native.sh' => [
        'detect_freeradius_config_root',
        'Ignoring "sql"',
        'Loaded module rlm_sql',
    ],
    'scripts/upgrade-1.4.0-to-1.4.1.sh' => [
        'APP_VERSION',
        'radiushub:bootstrap-platform',
        'radiushub:health --ready',
    ],
    'docs/UPGRADE_1.4.0_TO_1.4.1.md' => [
        '1.4.1',
        'Ignoring "sql"',
    ],
    '.github/workflows/release.yml' => [
        "workflow_run:",
        "workflows: ['CI']",
        'gh release create',
        'gh release upload',
        'packages: write',
        'GH_REPO: ${{ github.repository }}',
        'Checkout do commit da release',
        '--repo "${GH_REPO}"',
        'Verificar release publicada',
        'VERSION',
    ],
];

foreach ($checks as $file => $needles) {
    $contents = $read($file);

    foreach ($needles as $needle) {
        if ($contents !== '' && ! str_contains($contents, $needle)) {
            $errors[] = "{$file}: referência obrigatória ausente: {$needle}";
        }
    }
}

$ciContents = $read('.github/workflows/ci.yml');
foreach (['full-validation', 'github.event.pull_request.labels', 'inputs.full_validation', 'actions/upload-artifact', 'gh release create'] as $forbidden) {
    if ($ciContents !== '' && str_contains($ciContents, $forbidden)) {
        $errors[] = ".github/workflows/ci.yml: conteúdo proibido no CI integral do PR: {$forbidden}";
    }
}

$radiusEntrypoint = $read('docker/freeradius/entrypoint.sh');
if ($radiusEntrypoint !== '' && str_contains($radiusEntrypoint, 'FREERADIUS_CONFIG_ROOT:-/etc/freeradius/3.0')) {
    $errors[] = 'docker/freeradius/entrypoint.sh: diretório FreeRADIUS não pode permanecer fixo em /etc/freeradius/3.0';
}

if ($errors !== []) {
    $errors = array_values(array_unique($errors));
    fwrite(STDERR, "Falha na integridade da versão/release:\n");

    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }

    exit(1);
}

fwrite(
    STDOUT,
    "Versão {$version} consistente; release automática e imagens semânticas configuradas.\n",
);
