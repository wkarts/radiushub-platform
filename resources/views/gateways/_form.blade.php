@php($g = $gateway ?? null)
<div class="form-stack">
    <div>
        <label>Nome</label>
        <input name="name" value="{{ old('name', $g?->name) }}" required>
    </div>

    <div class="form-grid">
        <div class="col-6">
            <label>Driver</label>
            @if($g)
                <input type="hidden" name="driver" value="{{ $g->driver }}">
                <select disabled>
                    <option>{{ $g->driver === 'asaas' ? 'Asaas SDK ARGWS' : 'Manual' }}</option>
                </select>
                <div class="form-help">O driver é imutável para preservar os vínculos financeiros.</div>
            @else
                <select name="driver" required>
                    <option value="manual" @selected(old('driver') === 'manual')>Manual</option>
                    <option value="asaas" @selected(old('driver', 'asaas') === 'asaas')>Asaas SDK ARGWS</option>
                </select>
            @endif
        </div>
        <div class="col-6">
            <label>Ambiente</label>
            <select name="environment" required>
                <option value="sandbox" @selected(old('environment', $g?->environment ?? 'sandbox') === 'sandbox')>Sandbox</option>
                <option value="production" @selected(old('environment', $g?->environment) === 'production')>Produção</option>
            </select>
        </div>
    </div>

    <div>
        <label>API Key {{ $g ? '(vazio mantém a atual)' : '' }}</label>
        <input type="password" name="api_key" autocomplete="new-password">
        <div class="form-help">A chave é criptografada no banco e nunca é exibida novamente.</div>
    </div>

    <div>
        <label>E-mail técnico do webhook</label>
        <input type="email" name="webhook_email" value="{{ old('webhook_email', data_get($g?->settings, 'webhook_email')) }}">
    </div>

    <div>
        <label>Token do webhook {{ $g ? '(vazio mantém o atual)' : '' }}</label>
        <input type="password" name="webhook_token" autocomplete="new-password">
        <div class="form-help">O RadiusHub cadastra esse token no Asaas e valida o cabeçalho <span class="kbd">asaas-access-token</span>.</div>
    </div>

    <label class="checkbox">
        <input type="checkbox" name="notification_disabled" value="1" @checked(old('notification_disabled', data_get($g?->settings, 'notification_disabled', false)))>
        Desabilitar notificações do Asaas ao cliente
    </label>

    <label class="checkbox">
        <input type="checkbox" name="active" value="1" @checked(old('active', $g?->active ?? true))>
        Gateway ativo
    </label>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
    <button class="btn btn-primary">Salvar gateway</button>
</div>
