<?php

namespace App\Providers;

use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn (): TenantContext => new TenantContext());
        $this->app->singleton(CompanyContext::class, fn (): CompanyContext => new CompanyContext());
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::before(fn ($user): ?bool => $user->is_super_admin ? true : null);

        RateLimiter::for('asaas-webhook', function (Request $request): Limit {
            $token = (string) $request->route('token', '');

            return Limit::perMinute(300)->by($token !== '' ? hash('sha256', $token) : $request->ip());
        });

        View::share('currentTenant', null);
        View::share('availableTenants', collect());
        View::share('currentCompany', null);
        View::share('availableCompanies', collect());
    }
}
