<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InternetPlanController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MikrotikDeviceController;
use App\Http\Controllers\NetworkAccessController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RadiusSessionController;
use App\Http\Controllers\ServiceContractController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\SystemHealthController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantSwitchController;
use App\Http\Controllers\TenantUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::post('/tenant/switch', TenantSwitchController::class)->name('tenant.switch');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('role:super_admin')->prefix('platform')->name('platform.')->group(function (): void {
        Route::resource('tenants', TenantController::class)->except(['create', 'show', 'edit']);
    });

    Route::middleware(['tenant', 'tenant.permission'])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::resource('users', TenantUserController::class)->except(['create', 'show', 'edit']);
        Route::resource('subscribers', SubscriberController::class)->except(['create', 'show', 'edit']);
        Route::resource('plans', InternetPlanController::class)->except(['create', 'show', 'edit'])->parameters(['plans' => 'plan']);
        Route::resource('mikrotiks', MikrotikDeviceController::class)->except(['create', 'show', 'edit'])->parameters(['mikrotiks' => 'mikrotik']);
        Route::post('mikrotiks/{mikrotik}/test', [MikrotikDeviceController::class, 'test'])->name('mikrotiks.test');
        Route::resource('accesses', NetworkAccessController::class)->except(['create', 'show', 'edit'])->parameters(['accesses' => 'access']);
        Route::resource('contracts', ServiceContractController::class)->except(['create', 'show', 'edit'])->parameters(['contracts' => 'contract']);
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
        Route::resource('gateways', PaymentGatewayController::class)->except(['create', 'show', 'edit']);
        Route::get('system/health', SystemHealthController::class)->name('system.health');
    });
});
