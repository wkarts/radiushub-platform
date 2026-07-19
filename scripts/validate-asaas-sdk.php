<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$packageRoot = $root.'/packages/asaas-sdk-argws';
$errors = [];

$readJson = static function (string $path) use (&$errors): array {
    if (! is_file($path)) {
        $errors[] = "Arquivo ausente: {$path}";

        return [];
    }

    try {
        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        $errors[] = "JSON inválido em {$path}: {$exception->getMessage()}";

        return [];
    }
};

$rootComposer = $readJson($root.'/composer.json');
$sdkComposer = $readJson($packageRoot.'/composer.json');
$openApi = $readJson($packageRoot.'/resources/openapi.json');

if (($rootComposer['require']['argws/asaas-sdk-php'] ?? null) !== '0.2.62') {
    $errors[] = 'O projeto deve exigir argws/asaas-sdk-php na versão 0.2.62.';
}

if (($sdkComposer['name'] ?? null) !== 'argws/asaas-sdk-php') {
    $errors[] = 'Nome Composer inesperado no SDK local.';
}

if (($sdkComposer['version'] ?? null) !== '0.2.62') {
    $errors[] = 'A versão explícita do SDK local deve ser 0.2.62.';
}

$serviceChecks = [
    'CustomerService.php' => ['listCustomers', 'createNewCustomer', 'updateExistingCustomer'],
    'PaymentService.php' => [
        'listPayments',
        'createNewPayment',
        'updateExistingPayment',
        'retrieveASinglePayment',
        'deletePayment',
        'getDigitableBillLine',
        'getQrCodeForPixPayments',
        'refundPayment',
    ],
    'WebhookService.php' => ['listWebhooks', 'createNewWebhook', 'updateExistingWebhook'],
];

foreach ($serviceChecks as $file => $methods) {
    $path = $packageRoot.'/src/Service/Generated/'.$file;
    $source = is_file($path) ? (string) file_get_contents($path) : '';

    foreach ($methods as $method) {
        if (! preg_match('/function\s+'.preg_quote($method, '/').'\s*\(/', $source)) {
            $errors[] = "Método {$method} não encontrado em {$file}.";
        }
    }
}

$customerRequired = $openApi['paths']['/v3/customers']['post']['requestBody']['content']['application/json']['schema']['required'] ?? [];
$paymentRequired = $openApi['paths']['/v3/payments']['post']['requestBody']['content']['application/json']['schema']['required'] ?? [];

foreach (['name', 'cpfCnpj'] as $field) {
    if (! in_array($field, $customerRequired, true)) {
        $errors[] = "Campo obrigatório de cliente ausente na OpenAPI: {$field}.";
    }
}

foreach (['customer', 'billingType', 'value', 'dueDate'] as $field) {
    if (! in_array($field, $paymentRequired, true)) {
        $errors[] = "Campo obrigatório de cobrança ausente na OpenAPI: {$field}.";
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}

echo "Asaas SDK ARGWS 0.2.62 validado: pacote, métodos e contratos OpenAPI compatíveis.".PHP_EOL;
