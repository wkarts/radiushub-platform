<?php

return [
    'credential_key' => env('RADIUS_CREDENTIAL_KEY'),
    'local_secret' => env('RADIUS_LOCAL_SECRET'),
    'auth_port' => (int) env('RADIUS_AUTH_PORT', 1812),
    'acct_port' => (int) env('RADIUS_ACCT_PORT', 1813),
    'coa_port' => (int) env('RADIUS_COA_PORT', 3799),
    'timeout' => (int) env('RADIUS_TIMEOUT_SECONDS', 3),
    'radclient_binary' => env('RADIUS_RADCLIENT_BINARY', '/usr/bin/radclient'),
    'close_stale_after_hours' => (int) env('RADIUS_CLOSE_STALE_AFTER_HOURS', 48),
];
