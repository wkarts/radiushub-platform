<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}"><link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script>try{const t=localStorage.getItem('radiushub-theme');if(t)document.documentElement.dataset.theme=t}catch(e){}</script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand"><img src="{{ asset('brand/logo-mark.svg') }}" alt=""><div><div class="brand-name">{{ config('app.name') }}</div><div class="brand-caption">Rede, RADIUS e cobrança</div></div></div>
        <nav class="sidebar-scroll">
            @if($currentTenant)
            <a class="nav-link {{ request()->routeIs('dashboard')?'active':'' }}" href="{{ route('dashboard') }}"><span class="nav-icon">◫</span>Dashboard</a>
            <div class="nav-section">Cadastros</div>
            @if(auth()->user()->is_super_admin || auth()->user()->roleForTenant($currentTenant->id??null)==='tenant_admin')
            <a class="nav-link {{ request()->routeIs('users.*')?'active':'' }}" href="{{ route('users.index') }}"><span class="nav-icon">♙</span>Usuários</a>
            @endif
            <a class="nav-link {{ request()->routeIs('subscribers.*')?'active':'' }}" href="{{ route('subscribers.index') }}"><span class="nav-icon">◎</span>Clientes</a>
            <a class="nav-link {{ request()->routeIs('contracts.*')?'active':'' }}" href="{{ route('contracts.index') }}"><span class="nav-icon">▤</span>Contratos</a>
            <a class="nav-link {{ request()->routeIs('plans.*')?'active':'' }}" href="{{ route('plans.index') }}"><span class="nav-icon">⇅</span>Planos</a>
            <a class="nav-link {{ request()->routeIs('accesses.*')?'active':'' }}" href="{{ route('accesses.index') }}"><span class="nav-icon">⌁</span>Acessos</a>
            <div class="nav-section">Rede</div>
            <a class="nav-link {{ request()->routeIs('mikrotiks.*')?'active':'' }}" href="{{ route('mikrotiks.index') }}"><span class="nav-icon">◉</span>MikroTiks</a>
            <a class="nav-link {{ request()->routeIs('sessions.*')?'active':'' }}" href="{{ route('sessions.index') }}"><span class="nav-icon">↔</span>Sessões</a>
            <a class="nav-link {{ request()->routeIs('system.health')?'active':'' }}" href="{{ route('system.health') }}"><span class="nav-icon">♡</span>Saúde do sistema</a>
            <div class="nav-section">Financeiro</div>
            <a class="nav-link {{ request()->routeIs('invoices.*')?'active':'' }}" href="{{ route('invoices.index') }}"><span class="nav-icon">$</span>Faturas</a>
            <a class="nav-link {{ request()->routeIs('gateways.*')?'active':'' }}" href="{{ route('gateways.index') }}"><span class="nav-icon">◇</span>Gateways</a>
            @endif
            @if(auth()->user()->is_super_admin)
            <div class="nav-section">Plataforma</div>
            <a class="nav-link {{ request()->routeIs('platform.tenants.*')?'active':'' }}" href="{{ route('platform.tenants.index') }}"><span class="nav-icon">▦</span>Empresas</a>
            @endif
        </nav>
        <div class="sidebar-footer">v{{ config('app.version') }} · {{ config('app.author') }}</div>
    </aside>
    <div class="mobile-overlay"></div>
    <main class="main-column">
        <header class="topbar">
            <div class="topbar-left"><button class="icon-button mobile-menu" data-menu-toggle aria-label="Menu">☰</button><div><strong>{{ $currentTenant->name ?? 'Plataforma' }}</strong><div class="cell-subtitle">{{ $currentTenant->document ?? 'Operação multi-tenant' }}</div></div></div>
            <div class="topbar-right">
                @if($availableTenants->isNotEmpty())
                <form class="tenant-switch" action="{{ route('tenant.switch') }}" method="post">@csrf<select name="tenant_id" data-autosubmit aria-label="Empresa">@foreach($availableTenants as $tenant)<option value="{{ $tenant->id }}" @selected(($currentTenant->id??null)===$tenant->id)>{{ $tenant->name }}</option>@endforeach</select></form>
                @endif
                <button class="icon-button" data-theme-toggle title="Alternar tema">◐</button>
                <a class="user-chip" href="{{ route('profile.edit') }}" title="Meu perfil"><div class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</div><div class="user-meta"><div class="user-name">{{ auth()->user()->name }}</div><div class="user-role">{{ auth()->user()->is_super_admin?'Superadministrador':(['tenant_admin'=>'Administrador da empresa','network_admin'=>'Administrador de rede','billing'=>'Financeiro','operator'=>'Operador','viewer'=>'Somente leitura'][auth()->user()->roleForTenant($currentTenant->id??null)] ?? 'Usuário da empresa') }}</div></div></a>
                <form action="{{ route('logout') }}" method="post">@csrf<button class="btn btn-ghost btn-sm" type="submit">Sair</button></form>
            </div>
        </header>
        <div class="content"><x-flash />@yield('content')</div>
    </main>
</div>
<script src="{{ asset('js/app.js') }}" defer></script>
@stack('scripts')
</body></html>
