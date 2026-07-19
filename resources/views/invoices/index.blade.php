@extends('layouts.app')
@section('title', 'Faturas')
@section('content')
<x-page-header title="Faturas e recebimentos" description="Cobranças manuais ou integradas ao Asaas SDK ARGWS, com Pix, boleto, atualização e baixa por webhook.">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-open="invoice-create">＋ Emitir fatura</button>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Número</th>
                <th>Cliente</th>
                <th>Vencimento</th>
                <th>Valor</th>
                <th>Gateway</th>
                <th>Status remoto</th>
                <th>Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td class="cell-title">{{ $invoice->number }}<div class="form-help">{{ $invoice->description }}</div></td>
                    <td>{{ $invoice->subscriber->name }}</td>
                    <td>{{ $invoice->due_date->format('d/m/Y') }}</td>
                    <td>R$ {{ number_format((float) $invoice->amount, 2, ',', '.') }}</td>
                    <td>{{ $invoice->gateway?->name ?? strtoupper($invoice->gateway_driver) }}<div class="form-help">{{ $invoice->billing_type }}</div></td>
                    <td>{{ $invoice->gateway_status ?: '—' }}</td>
                    <td><x-status-badge :value="$invoice->status" /></td>
                    <td>
                        <div class="table-actions">
                            @if($invoice->payment_url)
                                <a class="btn btn-secondary btn-sm" href="{{ $invoice->payment_url }}" target="_blank" rel="noopener">Cobrança</a>
                            @endif
                            @if($invoice->pix_copy_paste || $invoice->bank_slip_line || $invoice->pix_qr_code)
                                <button class="btn btn-secondary btn-sm" data-modal-open="invoice-pay-{{ $invoice->id }}">Pix/Boleto</button>
                            @endif
                            @if($invoice->gateway_driver === 'asaas' && ($invoice->external_id || in_array($invoice->status->value, ['pending', 'overdue'], true)))
                                <form method="post" action="{{ route('invoices.sync', $invoice) }}">
                                    @csrf
                                    <button class="btn btn-secondary btn-sm">{{ $invoice->external_id ? 'Sincronizar' : 'Emitir no Asaas' }}</button>
                                </form>
                            @endif
                            @if(in_array($invoice->status->value, ['pending', 'overdue'], true))
                                <button class="btn btn-secondary btn-sm" data-modal-open="invoice-edit-{{ $invoice->id }}">Editar</button>
                                <form method="post" action="{{ route('invoices.paid', $invoice) }}">
                                    @csrf
                                    <button class="btn btn-success btn-sm" data-confirm="Baixar esta fatura manualmente?">Baixar</button>
                                </form>
                                <form method="post" action="{{ route('invoices.cancel', $invoice) }}">
                                    @csrf
                                    <button class="btn btn-ghost btn-sm" data-confirm="Cancelar esta fatura e a cobrança no gateway?">Cancelar</button>
                                </form>
                            @endif
                            @if(in_array($invoice->status->value, ['paid', 'partially_refunded'], true) && $invoice->gateway_driver === 'asaas')
                                <button class="btn btn-ghost btn-sm" data-modal-open="invoice-refund-{{ $invoice->id }}">Estornar</button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pagination', ['paginator' => $invoices])
</div>

<x-modal id="invoice-create" title="Emitir fatura" size="lg">
    <form method="post" action="{{ route('invoices.store') }}">
        @csrf
        <div class="form-grid">
            <div class="col-5">
                <label>Cliente</label>
                <select name="subscriber_id" required>
                    <option value="">Selecione</option>
                    @foreach($subscribers as $subscriber)
                        <option value="{{ $subscriber->id }}">{{ $subscriber->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-4">
                <label>Contrato</label>
                <select name="service_contract_id">
                    <option value="">Avulsa</option>
                    @foreach($contracts as $contract)
                        <option value="{{ $contract->id }}">{{ $contract->number }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-3">
                <label>Driver</label>
                <select name="gateway_driver" required>
                    <option value="manual">Manual</option>
                    <option value="asaas">Asaas</option>
                </select>
            </div>
            <div class="col-4">
                <label>Configuração Asaas</label>
                <select name="payment_gateway_config_id">
                    <option value="">Nenhuma/Manual</option>
                    @foreach($gateways->where('driver', 'asaas') as $gateway)
                        <option value="{{ $gateway->id }}">{{ $gateway->name }} — {{ $gateway->environment }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-3">
                <label>Forma de cobrança</label>
                <select name="billing_type">
                    <option value="UNDEFINED">Cliente escolhe</option>
                    <option value="PIX">Pix</option>
                    <option value="BOLETO">Boleto</option>
                    <option value="CREDIT_CARD">Cartão pelo link Asaas</option>
                </select>
            </div>
            <div class="col-5"><label>Descrição</label><input name="description" required></div>
            <div class="col-3"><label>Emissão</label><input type="date" name="issue_date" value="{{ today()->format('Y-m-d') }}" required></div>
            <div class="col-3"><label>Vencimento</label><input type="date" name="due_date" value="{{ today()->addDays(10)->format('Y-m-d') }}" required></div>
            <div class="col-3"><label>Valor</label><input type="number" step="0.01" min="0.01" name="amount" required></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
            <button class="btn btn-primary">Emitir cobrança</button>
        </div>
    </form>
</x-modal>

@foreach($invoices as $invoice)
    @if(in_array($invoice->status->value, ['pending', 'overdue'], true))
        <x-modal id="invoice-edit-{{ $invoice->id }}" title="Atualizar {{ $invoice->number }}" size="sm">
            <form method="post" action="{{ route('invoices.update', $invoice) }}">
                @csrf
                @method('put')
                <div class="form-stack">
                    <div><label>Descrição</label><input name="description" value="{{ $invoice->description }}" required></div>
                    <div><label>Vencimento</label><input type="date" name="due_date" value="{{ $invoice->due_date->format('Y-m-d') }}" required></div>
                    <div><label>Valor</label><input type="number" step="0.01" min="0.01" name="amount" value="{{ $invoice->amount }}" required></div>
                    <div>
                        <label>Forma de cobrança</label>
                        <select name="billing_type" required>
                            @foreach(['UNDEFINED' => 'Cliente escolhe', 'PIX' => 'Pix', 'BOLETO' => 'Boleto', 'CREDIT_CARD' => 'Cartão pelo link'] as $value => $label)
                                <option value="{{ $value }}" @selected($invoice->billing_type === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button><button class="btn btn-primary">Atualizar local e Asaas</button></div>
            </form>
        </x-modal>
    @endif

    @if($invoice->pix_copy_paste || $invoice->bank_slip_line || $invoice->pix_qr_code)
        <x-modal id="invoice-pay-{{ $invoice->id }}" title="Dados de pagamento {{ $invoice->number }}" size="sm">
            <div class="form-stack">
                @if($invoice->pix_qr_code)
                    <div style="text-align:center"><img src="data:image/png;base64,{{ $invoice->pix_qr_code }}" alt="QR Code Pix" style="max-width:260px;width:100%"></div>
                @endif
                @if($invoice->pix_copy_paste)
                    <div><label>Pix Copia e Cola</label><textarea rows="5" readonly>{{ $invoice->pix_copy_paste }}</textarea></div>
                @endif
                @if($invoice->bank_slip_line)
                    <div><label>Linha digitável</label><textarea rows="3" readonly>{{ $invoice->bank_slip_line }}</textarea></div>
                @endif
                @if($invoice->payment_url)
                    <a class="btn btn-primary" href="{{ $invoice->payment_url }}" target="_blank" rel="noopener">Abrir cobrança</a>
                @endif
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-modal-close>Fechar</button></div>
        </x-modal>
    @endif

    @if(in_array($invoice->status->value, ['paid', 'partially_refunded'], true) && $invoice->gateway_driver === 'asaas')
        <x-modal id="invoice-refund-{{ $invoice->id }}" title="Estornar {{ $invoice->number }}" size="sm">
            <form method="post" action="{{ route('invoices.refund', $invoice) }}">
                @csrf
                <div class="form-stack">
                    <div><label>Valor do estorno</label><input type="number" name="amount" min="0.01" max="{{ max(0, (float) $invoice->paid_amount - (float) $invoice->refunds->where('status', '!=', 'PAYMENT_REFUND_DENIED')->sum('amount')) }}" step="0.01" value="{{ max(0, (float) $invoice->paid_amount - (float) $invoice->refunds->where('status', '!=', 'PAYMENT_REFUND_DENIED')->sum('amount')) }}" required></div>
                    <div><label>Motivo</label><input name="description" maxlength="255"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button><button class="btn btn-ghost" data-confirm="Enviar estorno ao Asaas?">Confirmar estorno</button></div>
            </form>
        </x-modal>
    @endif
@endforeach
@endsection
