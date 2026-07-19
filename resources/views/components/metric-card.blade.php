<div class="card metric-card">
    <div class="metric-label">{{ $label }}</div>
    <div class="metric-value">{{ $value }}</div>
    @isset($note)<div class="metric-note">{{ $note }}</div>@endisset
</div>
