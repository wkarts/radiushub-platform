@extends('layouts.app')
@section('title','Par de chaves SSH')
@section('content')
<x-page-header title="Novo par de chaves SSH" description="A chave privada é exibida somente nesta tela. Guarde-a com segurança e importe a pública no MikroTik."><x-slot:actions><a class="btn btn-secondary" href="{{ route('mikrotiks.index') }}">Voltar</a></x-slot:actions></x-page-header>
<div class="alert alert-warning">Esta chave privada não foi salva no banco. Copie e armazene antes de sair.</div>
<div class="card"><div class="card-body"><label>Chave pública para o RouterOS</label><textarea id="public-key" rows="5" readonly>{{ $keyPair['public_key'] }}</textarea><button class="btn btn-secondary btn-sm" data-copy-target="#public-key">Copiar pública</button><label style="margin-top:18px">Chave privada para cadastrar no RadiusHub</label><textarea id="private-key" rows="14" readonly>{{ $keyPair['private_key'] }}</textarea><button class="btn btn-primary btn-sm" data-copy-target="#private-key">Copiar privada</button><div class="secret-box" style="margin-top:18px">Fingerprint: <span class="kbd">{{ $keyPair['fingerprint'] }}</span></div></div></div>
@endsection
