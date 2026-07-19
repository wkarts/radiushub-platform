@extends('layouts.app')
@section('title','Meu perfil')
@section('content')
<x-page-header title="Meu perfil" description="Atualize seus dados de acesso e substitua a senha inicial." />
<div class="card" style="max-width:760px"><div class="card-body"><form method="post" action="{{ route('profile.update') }}">@csrf @method('put')
<div class="form-grid">
    <div class="col-6"><label>Nome</label><input name="name" value="{{ old('name',auth()->user()->name) }}" required></div>
    <div class="col-6"><label>E-mail</label><input type="email" name="email" value="{{ old('email',auth()->user()->email) }}" required></div>
    <div class="col-4"><label>Senha atual</label><input type="password" name="current_password" autocomplete="current-password"><div class="form-help">Obrigatória apenas ao trocar a senha.</div></div>
    <div class="col-4"><label>Nova senha</label><input type="password" name="password" autocomplete="new-password"></div>
    <div class="col-4"><label>Confirmar nova senha</label><input type="password" name="password_confirmation" autocomplete="new-password"></div>
</div>
<div class="modal-footer"><button class="btn btn-primary" type="submit">Salvar perfil</button></div>
</form></div></div>
@endsection
