# Upgrade RadiusHub 1.2.x para 1.3.0

## Alterações principais

- empresas dentro do tenant;
- RBAC por empresa;
- SSH Key e histórico de comandos;
- vouchers dinâmicos;
- perfis de rede;
- 2FA;
- dashboard global/empresa;
- auditoria ampliada;
- interface responsiva e menu colapsável;
- FreeRADIUS multiempresa;
- sincronização automática pela fila `network`;
- endpoints Asaas secretos por empresa/gateway, sem tenant ou slug na URL.

## Procedimento nativo

```bash
cd /caminho/do/radiushub
chmod +x scripts/*.sh
./scripts/upgrade-1.2-to-1.3.sh
```

O script faz backup do banco, `.env` e arquivos persistentes antes da manutenção e não substitui `APP_KEY`. As migrations criam uma empresa padrão por tenant legado, preenchem `company_id`, geram um token público criptografado para cada gateway Asaas e preservam os campos existentes. O script também executa `php artisan asaas:webhooks:sync` para substituir as URLs antigas no Asaas.

Após o upgrade, instale/recarregue a configuração atualizada do worker para incluir a fila `network`:

```bash
sudo cp storage/app/deploy/supervisor-radiushub.conf /etc/supervisor/conf.d/radiushub.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart radiushub-worker:*
```

Reinstale/renderize o FreeRADIUS para obter as queries de vouchers e empresa:

```bash
sudo ./scripts/install-freeradius-native.sh
sudo freeradius -XC
sudo systemctl restart freeradius
```

## Docker

```bash
./scripts/update-docker.sh --build
```

## Pós-upgrade

1. execute `php artisan migrate:status` e confirme as migrations `2026_07_19_000700` e `2026_07_19_000800`;
2. execute `php artisan radiushub:doctor`;
3. confirme a empresa padrão criada para cada tenant antigo;
4. revise papéis dos usuários;
5. configure SSH Key nos MikroTiks;
6. teste SSH e fixe fingerprint;
7. valide autenticação RADIUS em homologação;
8. valide desconexão e alteração de limite pela sessão SSH;
9. gere um lote pequeno de vouchers;
10. execute `php artisan asaas:webhooks:sync` novamente caso algum gateway tenha ficado pendente;
11. valide worker `network`, fila `webhooks` e Scheduler.

## Rollback

O backup é criado antes das migrations. Para rollback, coloque a aplicação em manutenção, restaure código/banco e mantenha a mesma `APP_KEY`. Não execute `migrate:rollback` em produção sem analisar os dados criados na versão 1.3.
