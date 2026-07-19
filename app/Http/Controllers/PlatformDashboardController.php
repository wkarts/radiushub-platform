<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\MikrotikDevice;
use App\Models\MikrotikConnectionLog;
use App\Models\RadiusAccounting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlatformDashboardController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(auth()->user()?->is_super_admin, 403);

        return view('platform.dashboard', [
            'metrics' => [
                'tenants' => Tenant::query()->count(),
                'companies' => Company::query()->withoutGlobalScopes(['tenant', 'company'])->count(),
                'users' => User::query()->count(),
                'users_active' => User::query()->where('active', true)->count(),
                'devices_online' => MikrotikDevice::query()->withoutGlobalScopes(['tenant', 'company'])->where('status', 'online')->count(),
                'devices_offline' => MikrotikDevice::query()->withoutGlobalScopes(['tenant', 'company'])->whereIn('status', ['offline', 'error'])->count(),
                'vouchers_total' => Voucher::query()->withoutGlobalScopes(['tenant', 'company'])->count(),
                'vouchers_active' => Voucher::query()->withoutGlobalScopes(['tenant', 'company'])->whereIn('status', ['available', 'active'])->count(),
                'vouchers_expired' => Voucher::query()->withoutGlobalScopes(['tenant', 'company'])->where('status', 'expired')->count(),
                'online' => RadiusAccounting::query()->withoutGlobalScopes()->whereNull('acct_stop_time')->count(),
                'ssh_failures_24h' => MikrotikConnectionLog::query()->where('result', 'failed')->where('created_at', '>=', now()->subDay())->count(),
                'traffic_bytes' => (int) RadiusAccounting::query()->withoutGlobalScopes()->sum(DB::raw('acct_input_octets + acct_output_octets')),
            ],
            'events' => AuditLog::query()->latest('created_at')->limit(15)->get(),
            'alerts' => MikrotikDevice::query()->withoutGlobalScopes(['tenant', 'company'])->whereNotNull('last_error')->latest('updated_at')->limit(10)->get(),
        ]);
    }
}
