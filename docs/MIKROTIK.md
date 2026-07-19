# MikroTik, RADIUS, Hotspot, PPPoE e controle de sessões

## Separação de responsabilidades

O RadiusHub utiliza dois canais distintos:

- **RADIUS (UDP 1812/1813):** autenticação, autorização e accounting de Hotspot/PPPoE;
- **SSH Key (TCP):** cadastro técnico, inventário, sincronização, bloqueio, desconexão e aplicação de limite temporário.

A aplicação Laravel não implementa o protocolo RADIUS. O FreeRADIUS consulta o mesmo banco e mantém o fluxo AAA. As ações administrativas iniciadas pelo painel usam SSH por padrão.

## RADIUS

Aponte o MikroTik para o IP privado/VPN da VPS:

```routeros
/radius add service=hotspot,ppp address=IP_RADIUS secret="SEGREDO-DO-EQUIPAMENTO" authentication-port=1812 accounting-port=1813 timeout=1s
/ppp aaa set use-radius=yes accounting=yes interim-update=5m
/ip hotspot profile set [find where name="PERFIL_HOTSPOT"] use-radius=yes radius-accounting=yes radius-interim-update=5m
```

O segredo deve ser igual ao cadastro do MikroTik no RadiusHub. O IP visto pelo FreeRADIUS deve coincidir com `radius_source_ip`.

## Desconexão e limite por SSH

O painel localiza a sessão por usuário, endereço IP e identificador de accounting. A desconexão remove a sessão Hotspot ou PPP ativa por comando autorizado. O limite temporário cria ou atualiza uma fila simples identificada pelo ID da sessão.

Variáveis:

```env
MIKROTIK_SESSION_CONTROL_DRIVER=ssh
MIKROTIK_ALLOW_COA_FALLBACK=false
```

O fallback CoA/radclient foi preservado somente para compatibilidade. Ele permanece desabilitado por padrão e não é necessário no fluxo administrativo normal.

Caso o fallback seja habilitado conscientemente:

```routeros
/radius incoming set accept=yes port=3799
```

Restrinja UDP 3799 à origem da VPS/VPN.

## Validação

```routeros
/radius monitor [find] once
/ip hotspot active print detail
/ppp active print detail
/queue simple print where name~"RadiusHub-session-"
```

Resultados saudáveis: `accepts` cresce, `timeouts=0`, `bad-replies=0`. Usuários RADIUS aparecem com flag `R`.

## Usuários locais e RADIUS

O RouterOS pode priorizar registros locais. Evite duplicar no MikroTik uma credencial que deva ser controlada exclusivamente pelo FreeRADIUS. A sincronização local deve ser utilizada de forma consciente em ambientes que adotam espelhamento ou contingência.

A configuração administrativa por chave está em [MIKROTIK_SSH.md](MIKROTIK_SSH.md).
