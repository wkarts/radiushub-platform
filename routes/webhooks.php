<?php
use App\Http\Controllers\Webhooks\AsaasWebhookController;
use Illuminate\Support\Facades\Route;
Route::post('/asaas/{tenant:slug}', AsaasWebhookController::class)->name('asaas');
