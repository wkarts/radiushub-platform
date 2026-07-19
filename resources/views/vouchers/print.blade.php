@php
    $settings = $batch->settings ?? [];
    $columns = max(1, min(4, (int) ($settings['print_columns'] ?? 3)));
    $showCompany = (bool) ($settings['print_show_company'] ?? true);
    $showPassword = (bool) ($settings['print_show_password'] ?? true);
    $title = trim((string) ($settings['print_title'] ?? $batch->name));
    $footer = trim((string) ($settings['print_footer'] ?? ''));
@endphp
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $batch->name }}</title>
<style>body{font:14px Arial,sans-serif;margin:20px;color:#111}.toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.grid{display:grid;grid-template-columns:repeat({{ $columns }},minmax(0,1fr));gap:10px}.ticket{border:1px dashed #333;padding:14px;break-inside:avoid;border-radius:6px}.code{font:700 20px monospace;letter-spacing:.06em;margin:8px 0}.muted{font-size:11px;color:#555;margin-top:4px}.footer{text-align:center;margin-top:18px;font-size:11px;color:#555}@media print{.toolbar{display:none}body{margin:5mm}}@media(max-width:700px){.grid{grid-template-columns:1fr}}</style></head>
<body><div class="toolbar"><h1>{{ $title ?: $batch->name }}</h1><button onclick="window.print()">Imprimir</button></div><div class="grid">@foreach($batch->vouchers as $voucher)<div class="ticket">@if($showCompany)<strong>{{ $currentCompany?->trade_name ?: $currentCompany?->legal_name ?: config('app.name') }}</strong>@endif<div class="code">{{ $credentials[$voucher->id]['code'] }}</div>@if($showPassword)<div>Senha: <b>{{ $credentials[$voucher->id]['password'] }}</b></div>@endif<div class="muted">{{ $voucher->profile?->name ?: $voucher->plan?->name ?: 'Acesso temporário' }}</div><div class="muted">Validade: {{ optional($voucher->expires_at)->format('d/m/Y H:i') ?: ($voucher->validity_duration_minutes ? $voucher->validity_duration_minutes.' min após primeiro acesso' : 'Sem expiração') }}</div>@if($voucher->speed_limit)<div class="muted">Velocidade: {{ $voucher->speed_limit }}</div>@endif</div>@endforeach</div>@if($footer)<div class="footer">{{ $footer }}</div>@endif</body></html>
