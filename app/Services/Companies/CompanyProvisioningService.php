<?php

namespace App\Services\Companies;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CompanyAdministratorWelcome;
use App\Services\Limits\UsageLimitService;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CompanyProvisioningService
{
    public function __construct(private readonly AuditLogger $audit, private readonly UsageLimitService $limits) {}

    public function create(array $data): Company
    {
        return DB::transaction(function () use ($data): Company {
            $company = Company::query()->create(collect($data)->except([
                'create_admin', 'admin_name', 'admin_email', 'admin_login',
                'admin_password', 'admin_role_id', 'send_password_link',
            ])->all());

            if (! empty($data['create_admin'])) {
                $user = User::query()->where('email', Str::lower($data['admin_email']))->first();
                $createdUser = false;

                if ($user && ($user->is_super_admin || ! $company->tenant->users()->whereKey($user->id)->exists())) {
                    throw ValidationException::withMessages([
                        'admin_email' => 'Este e-mail já pertence a outro escopo. Vincule o usuário ao tenant por um Superadministrador antes de associá-lo à empresa.',
                    ]);
                }

                if (! $user) {
                    $user = User::query()->create([
                        'name' => $data['admin_name'],
                        'email' => Str::lower($data['admin_email']),
                        'login' => $data['admin_login'] ?: null,
                        'password' => $data['admin_password'],
                        'must_change_password' => true,
                        'active' => true,
                        'email_verified_at' => now(),
                    ]);
                    $createdUser = true;
                }

                $this->limits->assertCompanyLocal($company, 'users');
                if (! $company->tenant->users()->whereKey($user->id)->exists()) {
                    $this->limits->assertTenant($company->tenant, 'users');
                }

                $role = Role::query()->findOrFail($data['admin_role_id']);
                $company->users()->syncWithoutDetaching([
                    $user->id => ['role_id' => $role->id, 'is_primary' => true, 'active' => true],
                ]);
                $company->tenant->users()->syncWithoutDetaching([$user->id => ['role' => 'operator']]);

                if (! empty($data['send_password_link'])) {
                    $token = Password::broker()->createToken($user);
                    $user->notify(new CompanyAdministratorWelcome($company, $token));
                }

                $this->audit->record('company.administrator-linked', $user, [], [
                    'company_id' => $company->id,
                    'role_id' => $role->id,
                    'new_user' => $createdUser,
                    'password_link_sent' => ! empty($data['send_password_link']),
                ]);
            }

            $this->audit->record('company.created', $company, [], $company->toArray());

            return $company;
        });
    }
}
