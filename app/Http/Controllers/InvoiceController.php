<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Exceptions\BillingGatewayException;
use App\Http\Requests\InvoiceRefundRequest;
use App\Http\Requests\InvoiceRequest;
use App\Http\Requests\InvoiceUpdateRequest;
use App\Models\Invoice;
use App\Models\PaymentGatewayConfig;
use App\Models\ServiceContract;
use App\Models\Subscriber;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class InvoiceController extends Controller
{
    public function index(): View
    {
        return view('invoices.index', [
            'invoices' => Invoice::query()
                ->with(['subscriber', 'gateway', 'refunds'])
                ->latest('due_date')
                ->paginate(25),
            'subscribers' => Subscriber::query()->orderBy('name')->get(),
            'contracts' => ServiceContract::query()
                ->whereIn('status', ['active', 'suspended'])
                ->orderBy('number')
                ->get(),
            'gateways' => PaymentGatewayConfig::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(InvoiceRequest $request, InvoiceService $service, AuditLogger $audit): RedirectResponse
    {
        $invoice = DB::transaction(function () use ($request): Invoice {
            do {
                $number = 'INV-'.now()->format('Ym').'-'.Str::upper(Str::random(10));
            } while (Invoice::query()->withTrashed()->where('number', $number)->exists());

            $invoice = Invoice::query()->create($request->safe()->except([]) + [
                'number' => $number,
                'paid_amount' => 0,
                'status' => InvoiceStatus::Pending,
            ]);

            $invoice->items()->create([
                'description' => $invoice->description,
                'quantity' => 1,
                'unit_price' => $invoice->amount,
                'total' => $invoice->amount,
            ]);

            return $invoice;
        });

        try {
            $invoice = $service->issue($invoice, $invoice->billing_type ?: 'UNDEFINED');
        } catch (BillingGatewayException $exception) {
            $invoice->update([
                'metadata' => array_replace_recursive($invoice->metadata ?? [], [
                    'gateway_error' => [
                        'message' => $exception->getMessage(),
                        'occurred_at' => now()->toIso8601String(),
                    ],
                ]),
            ]);

            return back()->withErrors([
                'gateway' => 'A fatura foi cadastrada, mas a cobrança externa não foi emitida: '.$exception->getMessage(),
            ]);
        }

        $audit->record('invoice.created', $invoice, [], $invoice->toArray());

        return back()->with('success', 'Fatura e cobrança emitidas.');
    }

    public function update(
        InvoiceUpdateRequest $request,
        Invoice $invoice,
        InvoiceService $service,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_if($invoice->status === InvoiceStatus::Paid, 422, 'Fatura paga não pode ser alterada diretamente.');
        abort_if($invoice->status === InvoiceStatus::Cancelled, 422, 'Fatura cancelada não pode ser alterada.');

        $old = $invoice->toArray();
        $invoice->update($request->validated());
        $invoice->items()->first()?->update([
            'description' => $invoice->description,
            'unit_price' => $invoice->amount,
            'total' => $invoice->amount,
        ]);

        try {
            $invoice = $service->updateRemoteCharge($invoice);
        } catch (BillingGatewayException $exception) {
            return back()->withErrors([
                'gateway' => 'Dados locais atualizados, mas a cobrança Asaas não foi sincronizada: '.$exception->getMessage(),
            ]);
        }

        $audit->record('invoice.updated', $invoice, $old, $invoice->toArray());

        return back()->with('success', 'Fatura e cobrança atualizadas.');
    }

    public function synchronize(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        try {
            if ($invoice->gateway_driver === 'asaas' && ! $invoice->external_id) {
                abort_unless(in_array($invoice->status, [InvoiceStatus::Pending, InvoiceStatus::Overdue], true), 422, 'Esta fatura não pode gerar uma nova cobrança.');
                $service->issue($invoice, $invoice->billing_type ?: 'UNDEFINED');

                return back()->with('success', 'Cobrança emitida no Asaas.');
            }

            $service->synchronize($invoice);

            return back()->with('success', 'Cobrança sincronizada com o Asaas.');
        } catch (BillingGatewayException $exception) {
            return back()->withErrors(['gateway' => $exception->getMessage()]);
        }
    }

    public function markPaid(Invoice $invoice, InvoiceService $service, AuditLogger $audit): RedirectResponse
    {
        $old = $invoice->toArray();
        $invoice = $service->markPaid($invoice, (float) $invoice->amount, 'manual');
        $audit->record('invoice.paid', $invoice, $old, $invoice->toArray());

        return back()->with('success', 'Fatura baixada.');
    }

    public function cancel(Invoice $invoice, InvoiceService $service, AuditLogger $audit): RedirectResponse
    {
        abort_if($invoice->status === InvoiceStatus::Paid, 422, 'Fatura paga não pode ser cancelada diretamente.');
        $old = $invoice->toArray();

        try {
            $invoice = $service->cancel($invoice);
        } catch (BillingGatewayException $exception) {
            return back()->withErrors(['gateway' => $exception->getMessage()]);
        }

        $audit->record('invoice.cancelled', $invoice, $old, $invoice->toArray());

        return back()->with('success', 'Fatura cancelada.');
    }

    public function refund(
        InvoiceRefundRequest $request,
        Invoice $invoice,
        InvoiceService $service,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless(in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::PartiallyRefunded], true), 422, 'Somente faturas pagas ou parcialmente estornadas podem ser estornadas.');
        abort_if($invoice->gateway_driver === 'manual', 422, 'Use uma baixa contábil manual para faturas sem gateway.');
        $old = $invoice->toArray();

        try {
            $invoice = $service->refund(
                $invoice,
                (float) $request->validated('amount'),
                $request->validated('description'),
            );
        } catch (BillingGatewayException $exception) {
            return back()->withErrors(['gateway' => $exception->getMessage()]);
        }

        $audit->record('invoice.refunded', $invoice, $old, $invoice->toArray());

        return back()->with('success', 'Solicitação de estorno enviada ao Asaas.');
    }
}
