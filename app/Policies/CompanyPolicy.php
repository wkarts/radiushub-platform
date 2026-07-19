<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function before(User $user): ?bool { return $user->is_super_admin ? true : null; }
    public function viewAny(User $user): bool { return in_array($user->roleForTenant(session(config('tenancy.session_key'))), ['tenant_admin'], true); }
    public function view(User $user, Company $company): bool { return $this->viewAny($user) || $user->companies()->whereKey($company->id)->wherePivot('active', true)->exists(); }
    public function create(User $user): bool { return $this->viewAny($user); }
    public function update(User $user, Company $company): bool { return $this->viewAny($user) && $company->tenant_id === session(config('tenancy.session_key')); }
    public function delete(User $user, Company $company): bool { return $this->update($user, $company); }
}
