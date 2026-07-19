<?php

return [
    'header' => env('TENANCY_HEADER', 'X-Tenant-ID'),
    'session_key' => 'current_tenant_id',
    'roles' => [
        'tenant_admin' => 'Administrador da empresa',
        'network_admin' => 'Administrador de rede',
        'billing' => 'Financeiro',
        'operator' => 'Operador',
        'viewer' => 'Somente leitura',
    ],
];
