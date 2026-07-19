<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Voucher;

class VoucherPolicy
{
    public function before(User $user): ?bool { return $user->is_super_admin ? true : null; }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('vouchers.view', $this->tenantId(), $this->companyId());
    }

    public function view(User $user, Voucher $voucher): bool
    {
        return $this->inCurrentScope($voucher) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vouchers.manage', $this->tenantId(), $this->companyId());
    }

    public function update(User $user, Voucher $voucher): bool
    {
        return $this->inCurrentScope($voucher) && $this->create($user);
    }

    public function delete(User $user, Voucher $voucher): bool
    {
        return $this->update($user, $voucher);
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('vouchers.export', $this->tenantId(), $this->companyId());
    }

    private function inCurrentScope(Voucher $voucher): bool
    {
        return hash_equals((string) $this->tenantId(), (string) $voucher->tenant_id)
            && hash_equals((string) $this->companyId(), (string) $voucher->company_id);
    }

    private function tenantId(): ?string { return session(config('tenancy.session_key')); }
    private function companyId(): ?string { return session(config('tenancy.company_session_key')); }
}
