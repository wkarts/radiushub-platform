@extends('layouts.app')
@section('title', 'Gateways')
@section('content')
<x-page-header title="Gateways bancários" description="Integração multi-tenant com o Asaas SDK ARGWS, credenciais criptografadas e webhook automatizado.">
    <x-slot:actions>
        <button class="btn btn-primary" data-modal-open="gateway-create">＋ Novo gateway</button>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Nome</th>
                <th>Driver</th>
                <th>Ambiente</th>
                <th>Último teste</th>
                <th>Webhook</th>
                <th>Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($gateways as $gateway)
                <tr>
                    <td class="cell-title">{{ $gateway->name }}</td>
                    <td>{{ $gateway->driver === 'asaas' ? 'Asaas SDK ARGWS' : 'Manual' }}</td>
                    <td>{{ $gateway->environment }}</td>
                    <td>
                        @php($testStatus = data_get($gateway->settings, 'last_test_status', 'unknown'))
                        <x-status-badge :value="$testStatus" />
                        @if(data_get($gateway->settings, 'last_tested_at'))
                            <div class="form-help">{{ \Illuminate\Support\Carbon::parse(data_get($gateway->settings, 'last_tested_at'))->format('d/m/Y H:i') }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="kbd">{{ rtrim(config('app.url'), '/') . route('webhooks.asaas', ['tenant' => $currentTenant->slug], false) }}</span>
                        <div class="form-help">{{ data_get($gateway->settings, 'webhook_sync_status') === 'success' ? 'Sincronizado' : 'Pendente de sincronização' }}</div>
                    </td>
                    <td><x-status-badge :value="$gateway->active ? 'active' : 'inactive'" /></td>
                    <td>
                        <div class="table-actions">
                            @if($gateway->driver === 'asaas')
                                <form method="post" action="{{ route('gateways.test', $gateway) }}">
                                    @csrf
                                    <button class="btn btn-secondary btn-sm">Testar</button>
                                </form>
                                <form method="post" action="{{ route('gateways.sync-webhook', $gateway) }}">
                                    @csrf
                                    <button class="btn btn-secondary btn-sm">Sincronizar webhook</button>
                                </form>
                            @endif
                            <button class="btn btn-secondary btn-sm" data-modal-open="gateway-edit-{{ $gateway->id }}">Editar</button>
                            <form method="post" action="{{ route('gateways.destroy', $gateway) }}">
                                @csrf
                                @method('delete')
                                <button class="btn btn-ghost btn-sm" data-confirm="Excluir gateway?">Excluir</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty-state title="Nenhum gateway configurado" text="Cadastre uma conta Asaas por tenant ou use o modo manual." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-modal id="gateway-create" title="Novo gateway" size="sm">
    <form method="post" action="{{ route('gateways.store') }}">
        @csrf
        @include('gateways._form', ['gateway' => null])
    </form>
</x-modal>

@foreach($gateways as $gateway)
    <x-modal id="gateway-edit-{{ $gateway->id }}" title="Editar gateway" size="sm">
        <form method="post" action="{{ route('gateways.update', $gateway) }}">
            @csrf
            @method('put')
            @include('gateways._form', ['gateway' => $gateway])
        </form>
    </x-modal>
@endforeach
@endsection
