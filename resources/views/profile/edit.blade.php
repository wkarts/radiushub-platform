@extends('layouts.app')
@section('title','Meu perfil')
@section('content')
<x-page-header title="Meu perfil" description="Atualize seus dados, substitua a senha inicial e proteja a conta com autenticação em dois fatores." />
<div class="dashboard-grid">
<div class="card"><div class="card-header"><div><div class="card-title">Dados de acesso</div><div class="card-subtitle">Informações pessoais e credenciais</div></div></div><div class="card-body"><form method="post" action="{{ route('profile.update') }}">@csrf @method('put')
<div class="form-grid">
    <div class="col-4"><label>Nome</label><input name="name" value="{{ old('name',auth()->user()->name) }}" required></div>
    <div class="col-4"><label>Login</label><input name="login" value="{{ old('login',auth()->user()->login) }}" autocomplete="username"></div>
    <div class="col-4"><label>E-mail</label><input type="email" name="email" value="{{ old('email',auth()->user()->email) }}" required></div>
    <div class="col-4"><label>Senha atual</label><input type="password" name="current_password" autocomplete="current-password"><div class="form-help">Obrigatória ao trocar a senha.</div></div>
    <div class="col-4"><label>Nova senha</label><input type="password" name="password" autocomplete="new-password"></div>
    <div class="col-4"><label>Confirmar nova senha</label><input type="password" name="password_confirmation" autocomplete="new-password"></div>
</div>
<div class="modal-footer"><button class="btn btn-primary" type="submit">Salvar perfil</button></div>
</form></div></div>
<div class="card"><div class="card-header"><div><div class="card-title">Autenticação em dois fatores</div><div class="card-subtitle">TOTP compatível com aplicativos autenticadores</div></div></div><div class="card-body">
@if(auth()->user()->two_factor_confirmed_at)
    <div class="alert alert-success">2FA está ativo desde {{ auth()->user()->two_factor_confirmed_at->format('d/m/Y H:i') }}.</div>
    <form method="post" action="{{ route('profile.2fa.disable') }}">@csrf @method('delete')<label>Senha atual</label><input type="password" name="current_password" required><button class="btn btn-danger" style="margin-top:12px" data-confirm="Desativar o segundo fator?">Desativar 2FA</button></form>
@else
    <form method="post" action="{{ route('profile.2fa.begin') }}">@csrf<button class="btn btn-primary">Iniciar configuração</button></form>
    @if($pendingTwoFactorSecret)
    <div style="margin-top:18px"><label>Chave secreta</label><div class="code">{{ $pendingTwoFactorSecret }}</div><div class="form-help">Cadastre esta chave no aplicativo autenticador. URI: {{ $pendingTwoFactorUri }}</div></div>
    <form method="post" action="{{ route('profile.2fa.confirm') }}" style="margin-top:14px">@csrf<label>Código atual</label><input name="code" inputmode="numeric" required><button class="btn btn-success" style="margin-top:10px">Confirmar e ativar</button></form>
    @if($recoveryCodes)<div style="margin-top:18px"><label>Códigos de recuperação</label><div class="code">{{ implode("\n",$recoveryCodes) }}</div><div class="form-help">Guarde-os em local seguro. Cada código só pode ser utilizado uma vez.</div></div>@endif
    @endif
@endif
</div></div>
</div>
@endsection
