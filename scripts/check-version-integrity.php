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
] as $file) {
    $expectLine($file, 'APP_VERSION=', $version);
}

foreach ([
    '.env.docker.mysql.example',
    '.env.docker.postgres.example',
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
    '.github/workflows/release.yml' => [
        "workflow_run:",
        "workflows: ['CI']",
        'gh release create',
        'gh release upload',
        'packages: write',
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

if ($errors !== []) {
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
