<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\MikrotikConnectionLog;
use App\Models\MikrotikDevice;
use App\Models\RadiusAccounting;
use App\Models\RadiusAuthAttempt;
use App\Models\ServiceContract;
use App\Models\Subscriber;
use App\Models\Voucher;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $metrics = [
            'subscribers' => Subscriber::query()->where('status', 'active')->count(),
            'contracts' => ServiceContract::query()->where('status', 'active')->count(),
            'online' => RadiusAccounting::query()->whereNull('acct_stop_time')->count(),
            'overdue' => Invoice::query()->where('status', InvoiceStatus::Overdue)->count(),
            'month_revenue' => (float) Invoice::query()->where('status', InvoiceStatus::Paid)->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('paid_amount'),
            'devices_online' => MikrotikDevice::query()->where('status', 'online')->count(),
            'vouchers_available' => Voucher::query()->where('status', 'available')->count(),
            'vouchers_active' => Voucher::query()->where('status', 'active')->count(),
            'vouchers_expired' => Voucher::query()->where('status', 'expired')->count(),
            'ssh_failures' => MikrotikConnectionLog::query()->where('company_id', session(config('tenancy.company_session_key')))->where('result', 'failed')->where('created_at', '>=', now()->subDay())->count(),
        ];

        return view('dashboard', [
            'metrics' => $metrics,
            'authAttempts' => RadiusAuthAttempt::query()->latest('created_at')->limit(10)->get(),
            'devices' => MikrotikDevice::query()->orderBy('name')->get(),
            'events' => AuditLog::query()->where('company_id', session(config('tenancy.company_session_key')))->latest('created_at')->limit(8)->get(),
        ]);
    }
}
