<?php

return [
    'bootstrap' => [
        'enabled' => filter_var(env('PLATFORM_BOOTSTRAP_ENABLED', true), FILTER_VALIDATE_BOOL),
        'prefer_existing_context' => filter_var(env('SEED_PREFER_EXISTING_CONTEXT', true), FILTER_VALIDATE_BOOL),
        'attach_all_super_admins' => filter_var(env('SEED_ATTACH_ALL_SUPER_ADMINS', true), FILTER_VALIDATE_BOOL),
        'admin' => [
            'name' => env('SEED_ADMIN_NAME', 'Administrador'),
            'email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
            'login' => env('SEED_ADMIN_LOGIN', 'admin'),
            'password' => env('SEED_ADMIN_PASSWORD', 'ChangeMe@123!'),
            'force_password' => filter_var(env('SEED_ADMIN_FORCE_PASSWORD', false), FILTER_VALIDATE_BOOL),
            'must_change_password' => filter_var(env('SEED_ADMIN_MUST_CHANGE_PASSWORD', true), FILTER_VALIDATE_BOOL),
        ],
        'tenant' => [
            'enabled' => filter_var(env('SEED_DEFAULT_TENANT', true), FILTER_VALIDATE_BOOL),
            'name' => env('SEED_TENANT_NAME', 'RadiusHub Principal'),
            'slug' => env('SEED_TENANT_SLUG', 'principal'),
            'document' => env('SEED_TENANT_DOCUMENT'),
            'email' => env('SEED_TENANT_EMAIL', env('SEED_ADMIN_EMAIL', 'admin@example.com')),
            'phone' => env('SEED_TENANT_PHONE'),
            'timezone' => env('SEED_TENANT_TIMEZONE', env('APP_TIMEZONE', 'America/Bahia')),
            'subscription_plan' => env('SEED_TENANT_PLAN', 'platform'),
        ],
        'company' => [
            'enabled' => filter_var(env('SEED_DEFAULT_COMPANY', true), FILTER_VALIDATE_BOOL),
            'legal_name' => env('SEED_COMPANY_LEGAL_NAME', 'Empresa Principal'),
            'trade_name' => env('SEED_COMPANY_TRADE_NAME', 'Empresa Principal'),
            'document' => env('SEED_COMPANY_DOCUMENT'),
            'email' => env('SEED_COMPANY_EMAIL', env('SEED_ADMIN_EMAIL', 'admin@example.com')),
            'phone' => env('SEED_COMPANY_PHONE'),
            'subscription_plan' => env('SEED_COMPANY_PLAN', 'platform'),
        ],
    ],
];
