<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\ServiceContract;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'billing:generate-recurring {--date=}';
    protected $description = 'Gera faturas recorrentes dos contratos ativos.';

    public function handle(TenantContext $context): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'))->startOfDay()
            : CarbonImmutable::today();

        Tenant::query()->where('active', true)->each(function (Tenant $tenant) use ($context, $date): void {
            $context->set($tenant);

            try {
                ServiceContract::query()
                    ->where('status', 'active')
                    ->whereDate('next_invoice_at', '<=', $date)
                    ->with('subscriber')
                    ->each(function (ServiceContract $contract) use ($date): void {
                        $billingDay = min(max((int) $contract->billing_day, 1), 28);
                        $dueDate = $date->setDay($billingDay);

                        if ($dueDate->lt($date)) {
                            $dueDate = $dueDate->addMonthNoOverflow()->setDay($billingDay);
                        }

                        $reference = $date->format('Ym');
                        $number = sprintf('%s-%s', $reference, strtoupper(Str::random(8)));

                        $invoice = Invoice::query()->firstOrCreate(
                            [
                                'service_contract_id' => $contract->id,
                                'issue_date' => $date->toDateString(),
                            ],
                            [
                                'subscriber_id' => $contract->subscriber_id,
                                'number' => $number,
                                'description' => 'Mensalidade '.$contract->number,
                                'due_date' => $dueDate->toDateString(),
                                'amount' => $contract->amount,
                                'paid_amount' => 0,
                                'status' => InvoiceStatus::Pending,
                                'gateway_driver' => 'manual',
                            ]
                        );

                        if ($invoice->wasRecentlyCreated) {
                            $invoice->items()->create([
                                'description' => $invoice->description,
                                'quantity' => 1,
                                'unit_price' => $invoice->amount,
                                'total' => $invoice->amount,
                            ]);
                        }

                        $contract->update([
                            'next_invoice_at' => $date->addMonthNoOverflow()->setDay($billingDay)->toDateString(),
                        ]);
                    });
            } finally {
                $context->clear();
            }
        });

        return self::SUCCESS;
    }
}
