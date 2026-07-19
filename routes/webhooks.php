<?php

declare(strict_types=1);

use App\Http\Controllers\Webhooks\AsaasWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/asaas/{token}', AsaasWebhookController::class)
    ->where('token', '[a-fA-F0-9]{96}')
    ->middleware('throttle:asaas-webhook')
    ->name('asaas');
