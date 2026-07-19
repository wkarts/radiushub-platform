@php($u=$user??null)
<div class="form-grid">
    <div class="col-6"><label>Nome</label><input name="name" value="{{ old('name',$u?->name) }}" required></div>
    <div class="col-6"><label>E-mail</label><input type="email" name="email" value="{{ old('email',$u?->email) }}" required></div>
    <div class="col-4"><label>Perfil</label><select name="role" required>
        @foreach(['tenant_admin'=>'Administrador da empresa','network_admin'=>'Administrador de rede','billing'=>'Financeiro','operator'=>'Operador','viewer'=>'Somente leitura'] as $value=>$label)
            <option value="{{ $value }}" @selected(old('role',$u?->pivot?->role??'operator')===$value)>{{ $label }}</option>
        @endforeach
    </select></div>
    <div class="col-4"><label>Senha {{ $u?'(vazio mantém)':'' }}</label><input type="password" name="password" @required(!$u) autocomplete="new-password"></div>
    <div class="col-4"><label>Confirmar senha</label><input type="password" name="password_confirmation" @required(!$u) autocomplete="new-password"></div>
    <div class="col-12"><label class="checkbox"><input type="checkbox" name="active" value="1" @checked(old('active',$u?->active??true))> Usuário ativo</label></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button><button class="btn btn-primary" type="submit">Salvar usuário</button></div>
