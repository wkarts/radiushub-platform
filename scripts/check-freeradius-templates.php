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

$requiredPoolOptions = [
    'start',
    'min',
    'max',
    'spare',
    'uses',
    'max_retries',
    'retry_delay',
    'lifetime',
    'cleanup_interval',
    'idle_timeout',
];

foreach (['mysql', 'postgresql'] as $dialect) {
    $relative = "resources/freeradius/{$dialect}/sql";
    $contents = $read($relative);

    if ($contents === '') {
        continue;
    }

    if (! preg_match('/^\s*sql\s*\{\s*$/m', $contents)) {
        $errors[] = "{$relative}: bloco sql ausente ou inválido";
    }

    if (preg_match('/^\s*pool\s*\{[^\r\n]*\S/m', $contents)) {
        $errors[] = "{$relative}: o bloco pool deve começar em uma linha própria";
    }

    $lines = preg_split('/\R/', $contents) ?: [];
    $insidePool = false;
    $poolClosed = false;
    $options = [];
    $braceBalance = 0;

    foreach ($lines as $index => $line) {
        $trimmed = trim($line);
        $braceBalance += substr_count($line, '{') - substr_count($line, '}');

        if (! $insidePool && preg_match('/^pool\s*\{$/', $trimmed)) {
            $insidePool = true;

            continue;
        }

        if (! $insidePool) {
            continue;
        }

        if ($trimmed === '}') {
            $insidePool = false;
            $poolClosed = true;

            continue;
        }

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (! preg_match('/^([a-z_]+)\s*=\s*([0-9]+)$/', $trimmed, $match)) {
            $errors[] = sprintf(
                '%s:%d: diretiva inválida no pool: %s',
                $relative,
                $index + 1,
                $trimmed,
            );

            continue;
        }

        $name = $match[1];
        $value = (int) $match[2];

        if (array_key_exists($name, $options)) {
            $errors[] = "{$relative}: diretiva pool duplicada: {$name}";
        }

        $options[$name] = $value;
    }

    if ($insidePool || ! $poolClosed) {
        $errors[] = "{$relative}: bloco pool não foi encerrado";
    }

    if ($braceBalance !== 0) {
        $errors[] = "{$relative}: chaves desbalanceadas";
    }

    foreach ($requiredPoolOptions as $option) {
        if (! array_key_exists($option, $options)) {
            $errors[] = "{$relative}: diretiva pool ausente: {$option}";
        }
    }

    if (
        isset($options['min'], $options['start'], $options['max'])
        && ! ($options['min'] <= $options['start'] && $options['start'] <= $options['max'])
    ) {
        $errors[] = "{$relative}: esperado min <= start <= max";
    }

    if (isset($options['spare'], $options['max']) && $options['spare'] > $options['max']) {
        $errors[] = "{$relative}: spare não pode ser maior que max";
    }

    foreach ([
        'dialect = "'.$dialect.'"',
        'driver = "rlm_sql_${dialect}"',
        '$INCLUDE ${modconfdir}/sql/main/${dialect}/queries.conf',
        'read_clients = yes',
        'client_table = mikrotik_devices',
    ] as $needle) {
        if (! str_contains($contents, $needle)) {
            $errors[] = "{$relative}: referência obrigatória ausente: {$needle}";
        }
    }
}

$default = $read('resources/freeradius/common/default');
foreach (['authorize {', 'accounting {', 'session {', 'post-auth {'] as $section) {
    if ($default !== '' && ! str_contains($default, $section)) {
        $errors[] = "resources/freeradius/common/default: seção ausente: {$section}";
    }
}

if ($default !== '' && substr_count($default, "\n        sql") < 4) {
    $errors[] = 'resources/freeradius/common/default: SQL deve participar de authorize, accounting, session e post-auth';
}

$clients = $read('resources/freeradius/common/clients.conf');
foreach (['client localhost {', '@@RADIUS_LOCAL_SECRET@@'] as $needle) {
    if ($clients !== '' && ! str_contains($clients, $needle)) {
        $errors[] = "resources/freeradius/common/clients.conf: referência obrigatória ausente: {$needle}";
    }
}

$dockerValidator = $read('docker/freeradius/validate-templates.sh');
foreach (['freeradius -d "$config_root" -XC', 'rlm_sql_null', 'mysql', 'postgresql'] as $needle) {
    if ($dockerValidator !== '' && ! str_contains($dockerValidator, $needle)) {
        $errors[] = "docker/freeradius/validate-templates.sh: referência obrigatória ausente: {$needle}";
    }
}

if ($errors !== []) {
    $errors = array_values(array_unique($errors));
    fwrite(STDERR, "Falha nos templates FreeRADIUS:\n");

    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }

    exit(1);
}

fwrite(STDOUT, "Templates FreeRADIUS MySQL/PostgreSQL estruturalmente válidos.\n");
