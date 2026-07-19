@php
    $homeUrl = route('login');
    $homeLabel = 'Voltar ao login';

    if (auth()->check()) {
        if (auth()->user()->is_super_admin) {
            $homeUrl = route('platform.dashboard');
            $homeLabel = 'Voltar ao painel global';
        } elseif (session(config('tenancy.session_key')) && session(config('tenancy.company_session_key'))) {
            $homeUrl = route('dashboard');
            $homeLabel = 'Voltar ao painel';
        } else {
            $homeUrl = route('profile.edit');
            $homeLabel = 'Abrir meu perfil';
        }
    }
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <title>Não encontrado</title>
</head>
<body>
<div class="auth-form-side" style="min-height:100vh">
    <div class="auth-card">
        <h2>404 · Não encontrado</h2>
        <p class="lead">O recurso solicitado não existe ou pertence a outro tenant ou empresa.</p>
        <a class="btn btn-primary" href="{{ $homeUrl }}">{{ $homeLabel }}</a>
    </div>
</div>
</body>
</html>
