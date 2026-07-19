<?php

return [
    'ssh' => [
        'connection_timeout' => (int) env('MIKROTIK_SSH_CONNECTION_TIMEOUT', 10),
        'command_timeout' => (int) env('MIKROTIK_SSH_COMMAND_TIMEOUT', 30),
        'allow_password_fallback' => filter_var(env('MIKROTIK_SSH_ALLOW_PASSWORD_FALLBACK', false), FILTER_VALIDATE_BOOL),
        'require_host_fingerprint' => filter_var(env('MIKROTIK_SSH_REQUIRE_HOST_FINGERPRINT', false), FILTER_VALIDATE_BOOL),
        'key_bits' => (int) env('MIKROTIK_SSH_KEY_BITS', 3072),
    ],
    'session_control' => [
        'driver' => env('MIKROTIK_SESSION_CONTROL_DRIVER', 'ssh'),
        'allow_coa_fallback' => filter_var(env('MIKROTIK_ALLOW_COA_FALLBACK', false), FILTER_VALIDATE_BOOL),
    ],
    'auto_sync_on_change' => filter_var(env('MIKROTIK_AUTO_SYNC_ON_CHANGE', true), FILTER_VALIDATE_BOOL),
    'synchronization' => [
        'batch_size' => (int) env('MIKROTIK_SYNC_BATCH_SIZE', 100),
        'continue_on_error' => filter_var(env('MIKROTIK_SYNC_CONTINUE_ON_ERROR', true), FILTER_VALIDATE_BOOL),
    ],
];
