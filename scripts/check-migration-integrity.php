<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$migrationDirectory = $root . '/database/migrations';
$files = glob($migrationDirectory . '/*.php') ?: [];
sort($files, SORT_STRING);

$errors = [];
$sequences = [];
$names = [];

foreach ($files as $file) {
    $name = basename($file);

    if (isset($names[$name])) {
        $errors[] = "Arquivo de migration duplicado: {$name}";
    }
    $names[$name] = true;

    if (! preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_.+\.php$/', $name, $matches)) {
        $errors[] = "Nome de migration fora do padrão esperado: {$name}";
        continue;
    }

    $sequence = $matches[1];
    $sequences[$sequence][] = $name;
}

foreach ($sequences as $sequence => $group) {
    if (count($group) > 1) {
        $errors[] = sprintf(
            'Sequência de migration duplicada %s: %s',
            $sequence,
            implode(', ', $group),
        );
    }
}

$obsolete = '2026_07_19_000800_secure_asaas_webhooks_by_gateway.php';
if (is_file($migrationDirectory . '/' . $obsolete)) {
    $errors[] = "Migration obsoleta ainda presente: {$obsolete}";
}

if ($errors !== []) {
    fwrite(STDERR, "Falha na integridade das migrations:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Integridade das migrations validada: %d arquivo(s), %d sequência(s) única(s).\n",
    count($files),
    count($sequences),
));
