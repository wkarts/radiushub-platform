<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script>
        try {
            const theme = localStorage.getItem('radiushub-theme');
            const collapsed = localStorage.getItem('radiushub-sidebar-collapsed') === '1';
            if (theme) document.documentElement.dataset.theme = theme;
            if (collapsed && window.innerWidth > 900) document.documentElement.classList.add('sidebar-collapsed');
        } catch (e) {}
    </script>
</head>
<body>
@php
    $tenantId = $currentTenant?->id;
    $companyId = $currentCompany?->id;
    $can = fn (string $permission): bool => auth()->user()->hasPermission($permission, $tenantId, $companyId);
@endphp
<div class="app-shell">
    <aside class="sidebar" id="app-sidebar" aria-label="Menu principal">
        <div class="sidebar-brand">
            <img src="{{ asset('brand/logo-mark.svg') }}" alt="RadiusHub">
            <div class="sidebar-label"><div class="brand-name">{{ config('app.name') }}</div><div class="brand-caption">Rede, RADIUS e cobrança</div></div>
            <button type="button" class="sidebar-collapse-button" data-sidebar-collapse title="Recolher menu" aria-label="Recolher menu">‹</button>
        </div>
        <nav class="sidebar-scroll">
            @if($currentTenant && $currentCompany)
                @if($can('dashboard.view'))
                    <a class="nav-link {{ request()->routeIs('dashboard')?'active':'' }}" href="{{ route('dashboard') }}" data-tooltip="Dashboard"><span class="nav-icon">▦</span><span class="nav-label">Dashboard</span></a>
                @endif

                <div class="nav-group {{ request()->routeIs('subscribers.*','contracts.*','users.*') ? 'open' : '' }}" data-nav-group="cadastros">
                    <button type="button" class="nav-group-toggle" data-nav-group-toggle data-tooltip="Cadastros"><span class="nav-icon">◎</span><span class="nav-label">Cadastros</span><span class="nav-caret">⌄</span></button>
                    <div class="nav-submenu">
                        @if($can('subscribers.view'))<a class="nav-link {{ request()->routeIs('subscribers.*')?'active':'' }}" href="{{ route('subscribers.index') }}"><span class="nav-icon">◉</span><span class="nav-label">Clientes</span></a>@endif
                        @if($can('contracts.view'))<a class="nav-link {{ request()->routeIs('contracts.*')?'active':'' }}" href="{{ route('contracts.index') }}"><span class="nav-icon">▤</span><span class="nav-label">Contratos</span></a>@endif
                        @if($can('users.view'))<a class="nav-link {{ request()->routeIs('users.*')?'active':'' }}" href="{{ route('users.index') }}"><span class="nav-icon">♙</span><span class="nav-label">Usuários</span></a>@endif
                    </div>
                </div>

                <div class="nav-group {{ request()->routeIs('plans.*','profiles.*','accesses.*','vouchers.*') ? 'open' : '' }}" data-nav-group="acessos">
                    <button type="button" class="nav-group-toggle" data-nav-group-toggle data-tooltip="Acessos"><span class="nav-icon">⌁</span><span class="nav-label">Acessos</span><span class="nav-caret">⌄</span></button>
                    <div class="nav-submenu">
                        @if($can('plans.view'))<a class="nav-link {{ request()->routeIs('plans.*')?'active':'' }}" href="{{ route('plans.index') }}"><span class="nav-icon">⇅</span><span class="nav-label">Planos</span></a>@endif
                        @if($can('profiles.view'))<a class="nav-link {{ request()->routeIs('profiles.*')?'active':'' }}" href="{{ route('profiles.index') }}"><span class="nav-icon">≋</span><span class="nav-label">Perfis</span></a>@endif
                        @if($can('accesses.view'))<a class="nav-link {{ request()->routeIs('accesses.*')?'active':'' }}" href="{{ route('accesses.index') }}"><span class="nav-icon">↳</span><span class="nav-label">Usuários de rede</span></a>@endif
                        @if($can('vouchers.view'))<a class="nav-link {{ request()->routeIs('vouchers.*')?'active':'' }}" href="{{ route('vouchers.index') }}"><span class="nav-icon">▣</span><span class="nav-label">Vouchers</span></a>@endif
                    </div>
                </div>

                <div class="nav-group {{ request()->routeIs('mikrotiks.*','sessions.*','system.health') ? 'open' : '' }}" data-nav-group="rede">
                    <button type="button" class="nav-group-toggle" data-nav-group-toggle data-tooltip="Rede"><span class="nav-icon">◈</span><span class="nav-label">Rede</span><span class="nav-caret">⌄</span></button>
                    <div class="nav-submenu">
                        @if($can('mikrotiks.view'))<a class="nav-link {{ request()->routeIs('mikrotiks.*')?'active':'' }}" href="{{ route('mikrotiks.index') }}"><span class="nav-icon">◉</span><span class="nav-label">MikroTiks</span></a>@endif
                        @if($can('sessions.view'))<a class="nav-link {{ request()->routeIs('sessions.*')?'active':'' }}" href="{{ route('sessions.index') }}"><span class="nav-icon">↔</span><span class="nav-label">Sessões</span></a>@endif
                        @if($can('system.view'))<a class="nav-link {{ request()->routeIs('system.health')?'active':'' }}" href="{{ route('system.health') }}"><span class="nav-icon">♡</span><span class="nav-label">Saúde do sistema</span></a>@endif
                    </div>
                </div>

                @if($can('invoices.view') || $can('gateways.view'))
                <div class="nav-group {{ request()->routeIs('invoices.*','gateways.*') ? 'open' : '' }}" data-nav-group="financeiro">
                    <button type="button" class="nav-group-toggle" data-nav-group-toggle data-tooltip="Financeiro"><span class="nav-icon">$</span><span class="nav-label">Financeiro</span><span class="nav-caret">⌄</span></button>
                    <div class="nav-submenu">
                        @if($can('invoices.view'))<a class="nav-link {{ request()->routeIs('invoices.*')?'active':'' }}" href="{{ route('invoices.index') }}"><span class="nav-icon">▤</span><span class="nav-label">Faturas</span></a>@endif
                        @if($can('gateways.view'))<a class="nav-link {{ request()->routeIs('gateways.*')?'active':'' }}" href="{{ route('gateways.index') }}"><span class="nav-icon">◇</span><span class="nav-label">Gateways</span></a>@endif
                    </div>
                </div>
                @endif

                @if($can('audit.view'))<a class="nav-link {{ request()->routeIs('audit.*')?'active':'' }}" href="{{ route('audit.index') }}" data-tooltip="Auditoria"><span class="nav-icon">◫</span><span class="nav-label">Auditoria</span></a>@endif
            @endif

            @if($currentTenant && (auth()->user()->is_super_admin || auth()->user()->roleForTenant($currentTenant->id)==='tenant_admin'))
                <div class="nav-group {{ request()->routeIs('companies.*','roles.*') ? 'open' : '' }}" data-nav-group="tenant">
                    <button type="button" class="nav-group-toggle" data-nav-group-toggle data-tooltip="Administração"><span class="nav-icon">⚙</span><span class="nav-label">Administração</span><span class="nav-caret">⌄</span></button>
                    <div class="nav-submenu">
                        <a class="nav-link {{ request()->routeIs('companies.*')?'active':'' }}" href="{{ route('companies.index') }}"><span class="nav-icon">▥</span><span class="nav-label">Empresas</span></a>
                        <a class="nav-link {{ request()->routeIs('roles.*')?'active':'' }}" href="{{ route('roles.index') }}"><span class="nav-icon">◆</span><span class="nav-label">Papéis e permissões</span></a>
                    </div>
                </div>
            @endif

            @if(auth()->user()->is_super_admin)
                <div class="nav-group {{ request()->routeIs('platform.*') ? 'open' : '' }}" data-nav-group="platform">
                    <button type="button" class="nav-group-toggle" data-nav-group-toggle data-tooltip="Plataforma"><span class="nav-icon">▦</span><span class="nav-label">Plataforma</span><span class="nav-caret">⌄</span></button>
                    <div class="nav-submenu">
                        <a class="nav-link {{ request()->routeIs('platform.dashboard')?'active':'' }}" href="{{ route('platform.dashboard') }}"><span class="nav-icon">◫</span><span class="nav-label">Dashboard global</span></a>
                        <a class="nav-link {{ request()->routeIs('platform.tenants.*')?'active':'' }}" href="{{ route('platform.tenants.index') }}"><span class="nav-icon">▦</span><span class="nav-label">Tenants</span></a>
                        <a class="nav-link {{ request()->routeIs('platform.audit.*')?'active':'' }}" href="{{ route('platform.audit.index') }}"><span class="nav-icon">◧</span><span class="nav-label">Auditoria global</span></a>
                    </div>
                </div>
            @endif
        </nav>
        <div class="sidebar-footer"><span class="nav-label">v{{ config('app.version') }} · {{ config('app.author') }}</span></div>
    </aside>
    <div class="mobile-overlay" data-mobile-overlay></div>
    <main class="main-column">
        <header class="topbar">
            <div class="topbar-left">
                <button class="icon-button mobile-menu" type="button" data-menu-toggle aria-label="Abrir menu">☰</button>
                <button class="icon-button desktop-collapse" type="button" data-sidebar-collapse aria-label="Recolher menu">☰</button>
                <div class="context-title"><strong>{{ $currentCompany->trade_name ?? $currentCompany->legal_name ?? $currentTenant->name ?? 'Plataforma' }}</strong><div class="cell-subtitle">{{ $currentTenant?->name ?? 'Administração global' }}</div></div>
            </div>
            <div class="topbar-right">
                @if($availableTenants->isNotEmpty())
                    <form class="context-switch tenant-switch" action="{{ route('tenant.switch') }}" method="post">@csrf<select name="tenant_id" data-autosubmit aria-label="Tenant">@foreach($availableTenants as $tenant)<option value="{{ $tenant->id }}" @selected($tenantId===$tenant->id)>{{ $tenant->name }}</option>@endforeach</select></form>
                @endif
                @if($currentTenant && $availableCompanies->isNotEmpty())
                    <form class="context-switch company-switch" action="{{ route('company.switch') }}" method="post">@csrf<select name="company_id" data-autosubmit aria-label="Empresa">@foreach($availableCompanies as $company)<option value="{{ $company->id }}" @selected($companyId===$company->id)>{{ $company->trade_name ?: $company->legal_name }}</option>@endforeach</select></form>
                @endif
                <button class="icon-button" type="button" data-theme-toggle title="Alternar tema">◐</button>
                <a class="user-chip" href="{{ route('profile.edit') }}" title="Meu perfil"><div class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</div><div class="user-meta"><div class="user-name">{{ auth()->user()->name }}</div><div class="user-role">{{ auth()->user()->is_super_admin ? 'Superadministrador' : (auth()->user()->roleForCompany($companyId)?->name ?? 'Usuário') }}</div></div></a>
                <form action="{{ route('logout') }}" method="post">@csrf<button class="btn btn-ghost btn-sm" type="submit">Sair</button></form>
            </div>
        </header>
        <div class="content"><x-flash />@yield('content')</div>
    </main>
</div>
<script src="{{ asset('js/app.js') }}" defer></script>
@stack('scripts')
</body>
</html>
