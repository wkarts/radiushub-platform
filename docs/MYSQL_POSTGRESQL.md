# Compatibilidade MySQL e PostgreSQL

## Aplicação Laravel

As migrations, buscas, cache, sessões e filas são portáveis entre MySQL 8+ e PostgreSQL 15+.

## Credenciais RADIUS

As credenciais não são armazenadas em texto simples:

- PostgreSQL: `pgcrypto`/`pgp_sym_encrypt`, prefixo `pgp:`;
- MySQL: `AES_ENCRYPT` com chave SHA-256, prefixo `mysql:`;
- SQLite/testes: AES-256-GCM local, prefixo `local:`.

Ao trocar o driver do banco, regrave as credenciais:

```bash
php artisan radiushub:credentials:reencrypt --force
```

## FreeRADIUS

O comando abaixo gera o dialeto correto automaticamente:

```bash
php artisan radiushub:radius:render --force
```

No MySQL, atributos operacionais usam os campos dedicados do plano (`rate_limit`, `session_timeout`, `idle_timeout`, `address_pool`, IP estático). No PostgreSQL, atributos adicionais presentes no JSON do plano também são expandidos pelo FreeRADIUS.
