# Upgrade 1.1.0 para 1.3.0

A versão 1.3.0 corrige o login quebrado por `REDIS_HOST=redis` em instalação nativa, adiciona MySQL completo e torna a infraestrutura FreeRADIUS portável.

## Antes de atualizar

- mantenha a `APP_KEY` existente;
- faça backup do banco e do `.env`;
- não substitua o `.env` pelo arquivo de exemplo;
- mantenha as credenciais Asaas cadastradas, pois são criptografadas com a `APP_KEY`.

## Execução

Substitua os arquivos do projeto pela versão 1.3.0, preservando `.env` e `storage`, e execute:

```bash
./scripts/upgrade-1.1-to-1.2.sh
```

O script corrige cache/fila no CloudPanel nativo, limpa caches antigos, executa migrations e converte credenciais RADIUS legadas para o formato do banco atual.

## Validação

```bash
php artisan radiushub:doctor --strict
php artisan about
php artisan migrate:status
```
