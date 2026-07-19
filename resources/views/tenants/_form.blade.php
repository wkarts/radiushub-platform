@php($t=$tenant??null)
<div class="section-card">
    <h3 class="section-title">Identificação do tenant</h3>
    <div class="form-grid">
        <div class="col-7"><label>Nome</label><input name="name" value="{{ old('name',$t?->name) }}" required></div>
        <div class="col-5"><label>Slug</label><input name="slug" value="{{ old('slug',$t?->slug) }}" required></div>
        <div class="col-4"><label>CNPJ/CPF</label><input name="document" value="{{ old('document',$t?->document) }}"></div>
        <div class="col-4"><label>E-mail</label><input type="email" name="email" value="{{ old('email',$t?->email) }}"></div>
        <div class="col-4"><label>Telefone</label><input name="phone" value="{{ old('phone',$t?->phone) }}"></div>
        <div class="col-6"><label>Fuso horário</label><input name="timezone" value="{{ old('timezone',$t?->timezone??'America/Bahia') }}" required></div>
        <div class="col-3"><label>Situação</label><select name="status">@foreach(['active'=>'Ativo','suspended'=>'Suspenso','cancelled'=>'Cancelado'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$t?->status??'active')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-3"><label class="checkbox"><input type="checkbox" name="active" value="1" @checked(old('active',$t?->active??true))> Tenant habilitado</label></div>
    </div>
</div>
<div class="section-card">
    <h3 class="section-title">Plano e limites da plataforma</h3>
    <div class="form-grid">
        <div class="col-5"><label>Plano contratado</label><input name="subscription_plan" value="{{ old('subscription_plan',$t?->subscription_plan) }}" placeholder="Ex.: Business"></div>
        <div class="col-7"><label>Limites em JSON</label><textarea name="usage_limits_json" rows="4" placeholder='{"companies":10,"mikrotiks":50,"users":100}'>{{ old('usage_limits_json',$t?->usage_limits ? json_encode($t->usage_limits, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '') }}</textarea><div class="form-help">Deixe vazio para não impor limites. O conteúdo é validado no servidor.</div></div>
    </div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button><button class="btn btn-primary">Salvar tenant</button></div>
