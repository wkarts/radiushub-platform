<?php
namespace App\Http\Controllers;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\MikrotikDevice;
use App\Models\RadiusAccounting;
use App\Models\RadiusAuthAttempt;
use App\Models\ServiceContract;
use App\Models\Subscriber;
use Illuminate\View\View;
class DashboardController extends Controller { public function __invoke(): View { $metrics=['subscribers'=>Subscriber::query()->where('status','active')->count(),'contracts'=>ServiceContract::query()->where('status','active')->count(),'online'=>RadiusAccounting::query()->whereNull('acct_stop_time')->count(),'overdue'=>Invoice::query()->where('status',InvoiceStatus::Overdue)->count(),'month_revenue'=>(float)Invoice::query()->where('status',InvoiceStatus::Paid)->whereBetween('paid_at',[now()->startOfMonth(),now()->endOfMonth()])->sum('paid_amount'),'devices_online'=>MikrotikDevice::query()->where('status','online')->count()]; $authAttempts=RadiusAuthAttempt::query()->latest('created_at')->limit(10)->get(); $devices=MikrotikDevice::query()->orderBy('name')->get(); return view('dashboard',compact('metrics','authAttempts','devices')); } }
