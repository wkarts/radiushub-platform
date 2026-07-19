<?php

namespace App\Services\Mikrotik;

use App\Models\MikrotikDevice;

class MikrotikService
{
    public function __construct(private readonly MikrotikSshService $ssh) {}

    public function test(MikrotikDevice $device): array
    {
        return $this->ssh->test($device);
    }
}
