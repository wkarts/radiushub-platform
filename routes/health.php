<?php

use App\Http\Controllers\Health\LivenessController;
use App\Http\Controllers\Health\ReadinessController;
use Illuminate\Support\Facades\Route;

Route::get('/health/live', LivenessController::class)->name('health.live');
Route::get('/health/ready', ReadinessController::class)->name('health.ready');
