# Gerenciamento de vouchers

## Modelos de validade

### Validade fixa

O voucher possui `valid_from` e `expires_at` definidos na geração. Ele só autentica dentro dessa janela.

### Validade iniciada no primeiro acesso

O voucher é criado sem expiração efetiva. No primeiro accounting/autenticação registrada, o Scheduler grava `first_access_at`, ativa o voucher e calcula `expires_at` usando `validity_duration_minutes`.

O comando `vouchers:reconcile` é executado a cada minuto e também atualiza último acesso, dispositivo, tempo, dados consumidos e situação.

## Geração em lote

A tela permite:

- 1 a 5.000 vouchers por lote;
- alfabeto legível, numérico ou alfanumérico;
- prefixo, sufixo e tamanho;
- senha aleatória individual;
- plano, perfil e MikroTik;
- velocidade, limite de dados, tempo e dispositivos;
- validade fixa ou no primeiro acesso;
- sincronização automática.

Os códigos são gerados com `random_int`; as senhas são armazenadas com criptografia reversível exclusiva do FreeRADIUS.

## Estados

- `available`: disponível para o primeiro acesso;
- `active`: em uso e dentro da validade;
- `used`: esgotou limite de dados/tempo;
- `expired`: expirou;
- `blocked`: bloqueado administrativamente;
- `cancelled`: cancelado.

## Impressão e exportação

Cada lote pode ser:

- impresso pelo navegador;
- exportado em CSV UTF-8 com separador `;`;
- exportado em PDF A4 via DomPDF.

As senhas só são descriptografadas durante a geração da resposta autorizada de impressão/exportação. Essas rotas exigem permissão de vouchers e respeitam empresa/tenant ativos.

## Operações

Bloqueio, cancelamento, reativação, renovação e sincronização produzem auditoria. Quando há MikroTik vinculado, o estado também é enviado por SSH.

## FreeRADIUS

O FreeRADIUS identifica o tenant/empresa pelo NAS (`radius_source_ip`) e aceita o voucher somente quando:

- empresa e MikroTik estão ativos;
- código existe na mesma empresa;
- estado é `available` ou `active`;
- janela de validade é válida;
- senha é correta;
- limite simultâneo não foi excedido.
