<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InternetPlanController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MikrotikDeviceController;
use App\Http\Controllers\NetworkAccessController;
use App\Http\Controllers\NetworkProfileController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\PlatformDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RadiusSessionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServiceContractController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\SystemHealthController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantSwitchController;
use App\Http\Controllers\TenantUserController;
use App\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->name('two-factor.verify');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::post('/tenant/switch', TenantSwitchController::class)->name('tenant.switch');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/two-factor', [ProfileController::class, 'beginTwoFactor'])->name('profile.2fa.begin');
    Route::post('/profile/two-factor/confirm', [ProfileController::class, 'confirmTwoFactor'])->name('profile.2fa.confirm');
    Route::delete('/profile/two-factor', [ProfileController::class, 'disableTwoFactor'])->name('profile.2fa.disable');

    Route::middleware('role:super_admin')->prefix('platform')->name('platform.')->group(function (): void {
        Route::get('/dashboard', PlatformDashboardController::class)->name('dashboard');
        Route::get('/audit', [AuditLogController::class, 'platform'])->name('audit.index');
        Route::resource('tenants', TenantController::class)->except(['create', 'show', 'edit']);
    });

    Route::middleware(['tenant'])->group(function (): void {
        Route::post('/company/switch', CompanySwitchController::class)->name('company.switch');

        Route::middleware('scope.bindings')->group(function (): void {
            Route::resource('companies', CompanyController::class)->except(['create', 'show', 'edit']);
            Route::resource('roles', RoleController::class)->except(['create', 'show', 'edit']);
        });

        Route::middleware(['company', 'scope.bindings', 'password.changed', 'tenant.permission'])->group(function (): void {
            Route::get('/', DashboardController::class)->name('dashboard');
            Route::resource('users', TenantUserController::class)->except(['create', 'show', 'edit']);
            Route::resource('subscribers', SubscriberController::class)->except(['create', 'show', 'edit']);
            Route::resource('plans', InternetPlanController::class)->except(['create', 'show', 'edit'])->parameters(['plans' => 'plan']);
            Route::post('plans/{plan}/sync', [InternetPlanController::class, 'sync'])->name('plans.sync');
            Route::resource('profiles', NetworkProfileController::class)->except(['create', 'show', 'edit']);

            Route::resource('mikrotiks', MikrotikDeviceController::class)->except(['create', 'show', 'edit'])->parameters(['mikrotiks' => 'mikrotik']);
            Route::post('mikrotiks/key-pair', [MikrotikDeviceController::class, 'generateKeyPair'])->name('mikrotiks.key-pair');
            Route::post('mikrotiks/{mikrotik}/test', [MikrotikDeviceController::class, 'test'])->name('mikrotiks.test');
            Route::post('mikrotiks/{mikrotik}/execute', [MikrotikDeviceController::class, 'execute'])->name('mikrotiks.execute');
            Route::post('mikrotiks/{mikrotik}/sync', [MikrotikDeviceController::class, 'sync'])->name('mikrotiks.sync');
            Route::get('mikrotiks/{mikrotik}/logs', [MikrotikDeviceController::class, 'logs'])->name('mikrotiks.logs');

            Route::resource('accesses', NetworkAccessController::class)->except(['create', 'show', 'edit'])->parameters(['accesses' => 'access']);
            Route::post('accesses/{access}/sync', [NetworkAccessController::class, 'sync'])->name('accesses.sync');
            Route::resource('contracts', ServiceContractController::class)->except(['create', 'show', 'edit'])->parameters(['contracts' => 'contract']);

            Route::get('vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
            Route::post('vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
            Route::post('vouchers/{voucher}/block', [VoucherController::class, 'block'])->name('vouchers.block');
            Route::post('vouchers/{voucher}/reactivate', [VoucherController::class, 'reactivate'])->name('vouchers.reactivate');
            Route::post('vouchers/{voucher}/cancel', [VoucherController::class, 'cancel'])->name('vouchers.cancel');
            Route::post('vouchers/{voucher}/renew', [VoucherController::class, 'renew'])->name('vouchers.renew');
            Route::post('vouchers/{voucher}/sync', [VoucherController::class, 'sync'])->name('vouchers.sync');
            Route::get('voucher-batches/{batch}/print', [VoucherController::class, 'printBatch'])->name('vouchers.print');
            Route::get('voucher-batches/{batch}/csv', [VoucherController::class, 'exportCsv'])->name('vouchers.csv');
            Route::get('voucher-batches/{batch}/pdf', [VoucherController::class, 'exportPdf'])->name('vouchers.pdf');

            Route::get('sessions', [RadiusSessionController::class, 'index'])->name('sessions.index');
            Route::post('sessions/{session}/disconnect', [RadiusSessionController::class, 'disconnect'])->name('sessions.disconnect');
            Route::post('sessions/{session}/rate-limit', [RadiusSessionController::class, 'rateLimit'])->name('sessions.rate-limit');

            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
            Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
            Route::post('invoices/{invoice}/sync', [InvoiceController::class, 'synchronize'])->name('invoices.sync');
            Route::post('invoices/{invoice}/paid', [InvoiceController::class, 'markPaid'])->name('invoices.paid');
            Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
            Route::post('invoices/{invoice}/refund', [InvoiceController::class, 'refund'])->name('invoices.refund');
            Route::post('gateways/{gateway}/test', [PaymentGatewayController::class, 'test'])->name('gateways.test');
            Route::post('gateways/{gateway}/sync-webhook', [PaymentGatewayController::class, 'synchronizeWebhook'])->name('gateways.sync-webhook');
            Route::post('gateways/{gateway}/rotate-webhook-endpoint', [PaymentGatewayController::class, 'rotateWebhookEndpoint'])->name('gateways.rotate-webhook-endpoint');
            Route::resource('gateways', PaymentGatewayController::class)->except(['create', 'show', 'edit']);

            Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
            Route::get('system/health', SystemHealthController::class)->name('system.health');
        });
    });
});
