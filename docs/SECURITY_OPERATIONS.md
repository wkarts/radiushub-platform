# Segurança, logs e auditoria

## Credenciais

- `APP_KEY`: criptografa chaves SSH, passphrases, credenciais de gateway e segredos de aplicação.
- `RADIUS_CREDENTIAL_KEY`: criptografia compatível com leitura SQL pelo FreeRADIUS.
- Chaves e senhas não são incluídas em responses, `toArray()`, logs ou auditoria.
- A rotação de `APP_KEY` deve usar `APP_PREVIOUS_KEYS` e regravação controlada.

## Auditoria

Eventos relevantes registram:

- usuário, tenant e empresa;
- IP e user-agent;
- ação e resultado;
- recurso e ID;
- valores anteriores/novos sanitizados;
- metadata e request ID;
- data/hora.

`SensitiveDataSanitizer` remove senhas, tokens, segredos, passphrases e blocos de chave privada.

## Autenticação

- login por e-mail ou login;
- usuário ativo obrigatório;
- rate limit por login hash + IP;
- regeneração de sessão;
- recuperação de senha;
- troca obrigatória da senha inicial;
- senha mínima de 12 caracteres;
- TOTP com códigos de recuperação;
- cookie `Secure` em HTTPS;
- CSRF em rotas web;
- webhooks autenticados e idempotentes.

## Sessões e cache

No CloudPanel nativo, use cache/limiter/database para não depender de hostname Docker:

```env
SESSION_DRIVER=database
CACHE_STORE=database
CACHE_LIMITER=database
QUEUE_CONNECTION=database
REDIS_HOST=127.0.0.1
```

No Docker, Redis pode ser usado para cache/fila, mantendo limitador no banco e cache failover.

## Checklist de produção

```bash
php artisan radiushub:doctor --strict
php artisan migrate:status
php artisan route:list
php artisan queue:failed
php artisan schedule:list
```

Restrinja permissões de `.env`, `storage`, backups e configurações FreeRADIUS. Não versione `.env`, chaves privadas nem arquivos renderizados com segredos.
