@extends('layouts.guest')
@section('content')
<div class="auth-form-side" style="min-height:100vh"><div class="auth-card"><h2>Definir nova senha</h2><p class="lead">Use uma senha exclusiva com pelo menos 12 caracteres.</p><x-flash /><form class="form-stack" method="post" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><div><label>E-mail</label><input type="email" name="email" value="{{ old('email',$email) }}" required></div><div><label>Nova senha</label><input type="password" name="password" required></div><div><label>Confirmar senha</label><input type="password" name="password_confirmation" required></div><button class="btn btn-primary">Alterar senha</button></form></div></div>
@endsection
