<?php

namespace App\Policies;

use App\Models\MikrotikDevice;
use App\Models\User;

class MikrotikDevicePolicy
{
    public function before(User $user): ?bool { return $user->is_super_admin ? true : null; }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('mikrotiks.view', $this->tenantId(), $this->companyId());
    }

    public function view(User $user, MikrotikDevice $device): bool
    {
        return $this->inCurrentScope($device) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('mikrotiks.manage', $this->tenantId(), $this->companyId());
    }

    public function update(User $user, MikrotikDevice $device): bool
    {
        return $this->inCurrentScope($device) && $this->create($user);
    }

    public function delete(User $user, MikrotikDevice $device): bool
    {
        return $this->update($user, $device);
    }

    public function execute(User $user, MikrotikDevice $device): bool
    {
        return $this->inCurrentScope($device)
            && $user->hasPermission('mikrotiks.execute', $this->tenantId(), $this->companyId());
    }

    private function inCurrentScope(MikrotikDevice $device): bool
    {
        return hash_equals((string) $this->tenantId(), (string) $device->tenant_id)
            && hash_equals((string) $this->companyId(), (string) $device->company_id);
    }

    private function tenantId(): ?string { return session(config('tenancy.session_key')); }
    private function companyId(): ?string { return session(config('tenancy.company_session_key')); }
}
