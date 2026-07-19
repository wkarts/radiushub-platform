<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
Route::middleware(['auth','tenant'])->get('/me', fn(Request $request) => response()->json(['user'=>$request->user(),'tenant'=>app(App\Services\Tenancy\TenantContext::class)->tenant()]));
