<?php

return [
    'enabled' => filter_var(env('PLAYGROUND_MODE', false), FILTER_VALIDATE_BOOL),
    'banner' => filter_var(env('PLAYGROUND_BANNER', true), FILTER_VALIDATE_BOOL),
    'mikrotik_simulator' => filter_var(env('PLAYGROUND_MIKROTIK_SIMULATOR', false), FILTER_VALIDATE_BOOL),
    'allow_production' => filter_var(env('PLAYGROUND_ALLOW_PRODUCTION', false), FILTER_VALIDATE_BOOL),
    'seed' => [
        'tenant_slug' => env('PLAYGROUND_TENANT_SLUG', 'playground'),
        'company_document' => env('PLAYGROUND_COMPANY_DOCUMENT', '99999999000199'),
        'admin_email' => env('PLAYGROUND_ADMIN_EMAIL', env('SEED_ADMIN_EMAIL', 'admin@playground.local')),
        'admin_password' => env('PLAYGROUND_ADMIN_PASSWORD', env('SEED_ADMIN_PASSWORD', 'Playground@123!')),
        'operator_email' => env('PLAYGROUND_OPERATOR_EMAIL', 'operador@playground.local'),
        'operator_password' => env('PLAYGROUND_OPERATOR_PASSWORD', 'Operador@123!'),
        'technician_email' => env('PLAYGROUND_TECHNICIAN_EMAIL', 'tecnico@playground.local'),
        'technician_password' => env('PLAYGROUND_TECHNICIAN_PASSWORD', 'Tecnico@123!'),
        'network_username' => env('PLAYGROUND_NETWORK_USERNAME', 'cliente.demo'),
        'network_password' => env('PLAYGROUND_NETWORK_PASSWORD', 'ClienteDemo@123'),
        'nas_ip_address' => env('PLAYGROUND_NAS_IP_ADDRESS', '127.0.0.10'),
    ],
];
