@extends('layouts.app')
@section('title','Tenants')
@section('content')
<x-page-header title="Tenants da plataforma" description="Organizações isoladas que podem administrar uma ou mais empresas.">
    <x-slot:actions><button class="btn btn-primary" data-modal-open="tenant-create">＋ Novo tenant</button></x-slot:actions>
</x-page-header>
<div class="card">
    <div class="table-wrap desktop-table">
        <table>
            <thead><tr><th>Tenant</th><th>Slug</th><th>Plano</th><th>Empresas</th><th>Usuários</th><th>Contato</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($tenants as $tenant)
                <tr>
                    <td><div class="cell-title">{{ $tenant->name }}</div><div class="cell-subtitle">{{ $tenant->document ?: 'Sem documento' }}</div></td>
                    <td><span class="kbd">{{ $tenant->slug }}</span></td>
                    <td>{{ $tenant->subscription_plan ?: 'Sem plano' }}</td>
                    <td>{{ $tenant->companies_count }}</td>
                    <td>{{ $tenant->users_count }}</td>
                    <td>{{ $tenant->email ?: $tenant->phone ?: '—' }}</td>
                    <td><x-status-badge :value="$tenant->status ?: ($tenant->active?'active':'inactive')" /></td>
                    <td><div class="table-actions"><button class="btn btn-secondary btn-sm" data-modal-open="tenant-edit-{{ $tenant->id }}">Editar</button><form method="post" action="{{ route('platform.tenants.destroy',$tenant) }}">@csrf @method('delete')<button class="btn btn-ghost btn-sm" data-confirm="Excluir tenant vazio?">Excluir</button></form></div></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state title="Nenhum tenant" text="Cadastre o primeiro tenant para criar suas empresas." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-list">
        @foreach($tenants as $tenant)
            <div class="mobile-card"><div class="cell-title">{{ $tenant->name }}</div><div class="cell-subtitle">{{ $tenant->slug }} · {{ $tenant->companies_count }} empresa(s)</div><div style="margin-top:10px"><x-status-badge :value="$tenant->status" /></div><button class="btn btn-secondary btn-sm" style="margin-top:12px" data-modal-open="tenant-edit-{{ $tenant->id }}">Editar</button></div>
        @endforeach
    </div>
    @include('partials.pagination',['paginator'=>$tenants])
</div>
<x-modal id="tenant-create" title="Novo tenant" size="lg"><form method="post" action="{{ route('platform.tenants.store') }}">@csrf @include('tenants._form',['tenant'=>null])</form></x-modal>
@foreach($tenants as $tenant)<x-modal id="tenant-edit-{{ $tenant->id }}" title="Editar tenant" size="lg"><form method="post" action="{{ route('platform.tenants.update',$tenant) }}">@csrf @method('put') @include('tenants._form',['tenant'=>$tenant])</form></x-modal>@endforeach
@endsection
