@extends('layouts.guest')
@section('content')
<div class="auth-form-side" style="min-height:100vh"><div class="auth-card"><h2>Recuperar senha</h2><p class="lead">Enviaremos um link seguro para o e-mail cadastrado.</p><x-flash /><form class="form-stack" method="post" action="{{ route('password.email') }}">@csrf<div><label>E-mail</label><input type="email" name="email" value="{{ old('email') }}" required autofocus></div><button class="btn btn-primary">Enviar instruções</button><a class="btn btn-secondary" href="{{ route('login') }}">Voltar</a></form></div></div>
@endsection
