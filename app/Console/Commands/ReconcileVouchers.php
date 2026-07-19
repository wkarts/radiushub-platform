<?php

namespace App\Console\Commands;

use App\Models\RadiusAccounting;
use App\Models\Voucher;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use App\Services\Vouchers\VoucherGeneratorService;
use Illuminate\Console\Command;

class ReconcileVouchers extends Command
{
    protected $signature = 'vouchers:reconcile';
    protected $description = 'Atualiza primeiro acesso, consumo, dispositivo, uso e expiração dos vouchers.';

    public function handle(TenantContext $tenantContext, CompanyContext $companyContext, VoucherGeneratorService $generator): int
    {
        Voucher::query()->withoutGlobalScopes(['tenant', 'company'])
            ->whereIn('status', ['available', 'active'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired', 'updated_at' => now()]);

        Voucher::query()->withoutGlobalScopes(['tenant', 'company'])
            ->whereIn('status', ['available', 'active'])
            ->orderBy('id')
            ->chunkById(200, function ($vouchers) use ($tenantContext, $companyContext, $generator): void {
                foreach ($vouchers as $voucher) {
                    $tenantContext->set($voucher->tenant);
                    $companyContext->set($voucher->company);

                    try {
                        $latest = RadiusAccounting::query()->withoutGlobalScopes()
                            ->where('company_id', $voucher->company_id)
                            ->where('username', $voucher->code)
                            ->latest('acct_update_time')
                            ->first();

                        if (! $latest) continue;

                        if (! $voucher->first_access_at) {
                            $generator->activateOnFirstAccess($voucher, $latest->calling_station_id);
                        }

                        $aggregate = RadiusAccounting::query()->withoutGlobalScopes()
                            ->where('company_id', $voucher->company_id)
                            ->where('username', $voucher->code)
                            ->selectRaw('COALESCE(SUM(acct_input_octets + acct_output_octets),0) as bytes_used, COALESCE(SUM(acct_session_time),0) as seconds_used')
                            ->first();

                        $used = ($voucher->data_limit_bytes && (int) $aggregate->bytes_used >= $voucher->data_limit_bytes)
                            || ($voucher->usage_time_limit_seconds && (int) $aggregate->seconds_used >= $voucher->usage_time_limit_seconds);

                        $voucher->forceFill([
                            'last_access_at' => $latest->acct_update_time ?: $latest->acct_start_time ?: now(),
                            'device_identifier' => $latest->calling_station_id ?: $voucher->device_identifier,
                            'status' => $used ? 'used' : $voucher->status,
                            'used_at' => $used ? now() : $voucher->used_at,
                        ])->save();
                    } finally {
                        $companyContext->clear();
                        $tenantContext->clear();
                    }
                }
            });

        $companyContext->clear();
        $tenantContext->clear();
        $this->info('Vouchers reconciliados.');

        return self::SUCCESS;
    }
}
