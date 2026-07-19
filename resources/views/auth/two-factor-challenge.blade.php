@extends('layouts.guest')
@section('content')
<div class="auth-form-side" style="min-height:100vh"><div class="auth-card"><div class="auth-logo"><img src="{{ asset('brand/logo-mark.svg') }}" alt=""><div><strong style="font-size:20px">{{ config('app.name') }}</strong><div class="cell-subtitle">Verificação em duas etapas</div></div></div><h2>Código de segurança</h2><p class="lead">Informe o código do aplicativo autenticador ou um código de recuperação.</p><x-flash /><form class="form-stack" method="post" action="{{ route('two-factor.verify') }}">@csrf<div><label for="code">Código</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus></div><button class="btn btn-primary">Verificar</button></form></div></div>
@endsection
