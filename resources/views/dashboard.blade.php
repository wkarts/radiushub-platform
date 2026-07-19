@extends('layouts.app')
@section('title','Dashboard')
@section('content')
<x-page-header title="Visão geral" description="Indicadores operacionais, financeiros e de disponibilidade da empresa selecionada." />
<div class="metric-grid">
<x-metric-card label="Clientes ativos" :value="number_format($metrics['subscribers'],0,',','.')" note="Cadastros aptos para operação" />
<x-metric-card label="Contratos ativos" :value="number_format($metrics['contracts'],0,',','.')" note="Serviços em vigência" />
<x-metric-card label="Sessões online" :value="number_format($metrics['online'],0,',','.')" note="Hotspot e PPPoE" />
<x-metric-card label="Faturas vencidas" :value="number_format($metrics['overdue'],0,',','.')" note="Exigem acompanhamento" />
<x-metric-card label="Recebido no mês" :value="'R$ '.number_format($metrics['month_revenue'],2,',','.')" note="Pagamentos confirmados" />
<x-metric-card label="MikroTiks online" :value="number_format($metrics['devices_online'],0,',','.')" note="Último teste de API" />
</div>
<div class="dashboard-grid">
<div class="card"><div class="card-header"><div><div class="card-title">Autenticações recentes</div><div class="card-subtitle">Respostas processadas pelo FreeRADIUS</div></div></div><div class="table-wrap"><table><thead><tr><th>Usuário</th><th>NAS</th><th>Caller ID</th><th>Resposta</th><th>Data</th></tr></thead><tbody>@forelse($authAttempts as $attempt)<tr><td class="cell-title">{{ $attempt->username }}</td><td>{{ $attempt->nas_ip_address }}</td><td>{{ $attempt->calling_station_id ?: '—' }}</td><td><x-status-badge :value="$attempt->reply" /></td><td>{{ optional($attempt->created_at)->format('d/m/Y H:i:s') }}</td></tr>@empty<tr><td colspan="5"><x-empty-state title="Sem autenticações recentes" text="Os eventos aparecerão após o primeiro Access-Request." /></td></tr>@endforelse</tbody></table></div></div>
<div class="card"><div class="card-header"><div><div class="card-title">Equipamentos</div><div class="card-subtitle">Saúde da API RouterOS</div></div><a class="btn btn-secondary btn-sm" href="{{ route('mikrotiks.index') }}">Gerenciar</a></div><div class="card-body"><div class="health-list">@forelse($devices as $device)<div class="health-item"><div><div class="cell-title">{{ $device->name }}</div><div class="cell-subtitle">{{ $device->management_host }} · {{ $device->site_name ?: 'Sem local' }}</div></div><x-status-badge :value="$device->status" /></div>@empty<x-empty-state title="Nenhum MikroTik" text="Cadastre um equipamento para iniciar o monitoramento." />@endforelse</div></div></div>
</div>
@endsection
