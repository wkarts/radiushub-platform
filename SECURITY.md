# Segurança

- Não versione `.env`, chaves Asaas, `APP_KEY`, segredos RADIUS ou backups.
- Restrinja UDP 1812/1813 aos endereços dos MikroTiks ou à VPN.
- UDP 3799 é tráfego de saída da plataforma para os MikroTiks; não exponha uma porta de entrada desnecessária na VPS.
- Restrinja RouterOS API 8728/8729 à VPS/VPN.
- Use HTTPS e `SESSION_SECURE_COOKIE=true`.
- Use `CACHE_LIMITER=database` para que o login não dependa da disponibilidade do Redis.
- Mantenha permissões 600 no `.env` e nos arquivos FreeRADIUS gerados.
- Troque imediatamente credenciais compartilhadas em logs, imagens ou conversas.

Falhas devem ser reportadas de forma privada ao responsável pelo repositório, sem publicar segredos ou dados de clientes.
