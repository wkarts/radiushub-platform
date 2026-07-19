@extends('layouts.app')
@section('title','Usuários')
@section('content')
<x-page-header title="Usuários e permissões" description="Acessos administrativos isolados por empresa e perfil operacional.">
    <x-slot:actions><button class="btn btn-primary" data-modal-open="user-create">＋ Novo usuário</button></x-slot:actions>
</x-page-header>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Usuário</th><th>E-mail</th><th>Perfil</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($users as $user)
<tr><td class="cell-title">{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ ['tenant_admin'=>'Administrador da empresa','network_admin'=>'Administrador de rede','billing'=>'Financeiro','operator'=>'Operador','viewer'=>'Somente leitura'][$user->pivot->role] ?? $user->pivot->role }}</td><td><x-status-badge :value="$user->active?'active':'inactive'" /></td><td><div class="table-actions"><button class="btn btn-secondary btn-sm" data-modal-open="user-edit-{{ $user->id }}">Editar</button>@unless($user->is(auth()->user()) || $user->is_super_admin)<form method="post" action="{{ route('users.destroy',$user) }}">@csrf @method('delete')<button class="btn btn-ghost btn-sm" data-confirm="Remover este usuário da empresa?">Remover</button></form>@endunless</div></td></tr>
@empty<tr><td colspan="5"><x-empty-state /></td></tr>@endforelse
</tbody></table></div>@include('partials.pagination',['paginator'=>$users])</div>
<x-modal id="user-create" title="Novo usuário" size="lg"><form method="post" action="{{ route('users.store') }}">@csrf @include('users._form',['user'=>null])</form></x-modal>
@foreach($users as $user)<x-modal id="user-edit-{{ $user->id }}" title="Editar usuário" size="lg"><form method="post" action="{{ route('users.update',$user) }}">@csrf @method('put') @include('users._form',['user'=>$user])</form></x-modal>@endforeach
@endsection
