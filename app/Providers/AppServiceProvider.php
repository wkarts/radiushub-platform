<?php

namespace App\Providers;

use App\Services\Tenancy\TenantContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn (): TenantContext => new TenantContext());
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        View::share('currentTenant', null);
        View::share('availableTenants', collect());
    }
}
