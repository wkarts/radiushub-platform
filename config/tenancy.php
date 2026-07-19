<?php

return [
    'header' => env('TENANCY_HEADER', 'X-Tenant-ID'),
    'company_header' => env('COMPANY_HEADER', 'X-Company-ID'),
    'session_key' => 'current_tenant_id',
    'company_session_key' => 'current_company_id',
    'roles' => [
        'platform_super_admin' => 'Superadministrador da plataforma',
        'tenant_admin' => 'Administrador do tenant',
        'company_admin' => 'Administrador da empresa',
        'operator' => 'Operador',
        'attendant' => 'Atendente',
        'technician' => 'Técnico',
        'billing' => 'Financeiro',
        'viewer' => 'Consulta',
    ],
];
