# Integração MikroTik por SSH Key

## Princípio

Toda operação administrativa executada pelo RadiusHub utiliza SSH com chave assimétrica. A API RouterOS antiga permanece apenas como estrutura legada de banco e não é utilizada por controllers, jobs ou serviços ativos.

A chave privada fica criptografada pelo Laravel usando `APP_KEY`. Ela nunca é retornada pela interface e é removida de logs/auditoria. A chave pública é instalada no RouterOS.

## Criar usuário técnico no RouterOS

Crie um usuário exclusivo para a plataforma e um grupo com apenas as políticas necessárias ao seu cenário. Evite usar `admin`.

Exemplo inicial, que deve ser revisado conforme os comandos habilitados:

```routeros
/user group add name=radiushub policy=read,write,test,sensitive
/user add name=radiushub group=radiushub password="UMA-SENHA-LONGA-DE-CONTINGENCIA"
```

O RouterOS pode exigir permissões adicionais conforme os recursos Hotspot/PPP usados. Não conceda `full` sem necessidade.

## Gerar o par de chaves

Na tela **Rede → MikroTiks**, use **Gerar par de chaves**. O RadiusHub gera RSA de 3072 bits por padrão. Baixe/copiei a chave privada uma única vez e mantenha-a protegida até concluir o cadastro.

Também é possível gerar externamente:

```bash
ssh-keygen -t rsa -b 3072 -a 100 -f radiushub_mikrotik
```

## Importar a chave pública no RouterOS

Envie o arquivo público para **Files** e execute:

```routeros
/user ssh-keys import public-key-file=radiushub_mikrotik.pub user=radiushub
/user ssh-keys print detail
```

Em versões que suportam inclusão direta de chave OpenSSH, também é possível adicionar a chave no menu `/user ssh-keys`.

Documentação oficial: <https://help.mikrotik.com/docs/spaces/ROS/pages/132350014/SSH>

## Restringir o serviço SSH

Restrinja o serviço ao IP da VPS ou à rede VPN de gerenciamento:

```routeros
/ip service set ssh address=IP_DA_VPS/32 port=22 disabled=no
/ip ssh set strong-crypto=yes
```

Use WireGuard/IPsec ou uma rede privada sempre que possível. Não libere SSH globalmente na WAN.

## Fingerprint do host

O RadiusHub obtém e valida a chave pública do host **antes de enviar a chave privada, passphrase ou senha de contingência**. A fingerprint SHA256 é então comparada. Existem duas estratégias:

- **Fixação prévia:** informe a fingerprint no cadastro e use `MIKROTIK_SSH_REQUIRE_HOST_FINGERPRINT=true`.
- **TOFU controlado:** com a variável em `false`, o primeiro teste autenticado salva automaticamente a fingerprint observada; revise-a por um canal independente antes de considerar o equipamento confiável. Depois de fixada, qualquer mudança bloqueia novas conexões.

Qualquer alteração posterior da chave do host bloqueia a conexão para evitar MITM. Após regenerar legitimamente as host keys no MikroTik, valide a nova fingerprint fora de banda e atualize o cadastro.

## Fallback por senha

O fallback é desativado em duas camadas:

```env
MIKROTIK_SSH_ALLOW_PASSWORD_FALLBACK=false
```

E por equipamento. Para contingência temporária, habilite as duas opções, registre o motivo em auditoria e desative após a manutenção.

## Comandos permitidos

A aplicação não recebe comandos RouterOS arbitrários. `RouterOsCommandBuilder` aceita apenas chaves conhecidas:

- identidade e saúde;
- listagem de usuários/perfis/sessões;
- sincronização de perfil, acesso ou voucher;
- bloqueio controlado de acesso/voucher;
- desconexão de sessões Hotspot/PPPoE;
- aplicação de limite temporário por fila de sessão.

Parâmetros são limitados, escapados e rejeitados quando contêm caracteres de controle. Reinicialização, alteração de firewall e comandos livres não são permitidos.

## Teste e sincronização

1. Cadastre host, porta, usuário, chave e segredo RADIUS.
2. Clique em **Testar SSH**.
3. Confira identidade, modelo, RouterBOARD e versão.
4. Clique em **Sincronizar** para enviar perfis, usuários e vouchers vinculados.
5. Consulte **Histórico** para conexões e comandos.

Sincronização automática de alterações:

```env
MIKROTIK_AUTO_SYNC_ON_CHANGE=true
MIKROTIK_SYNC_BATCH_SIZE=100
MIKROTIK_SYNC_CONTINUE_ON_ERROR=true
```

O worker deve consumir a fila `network`.
