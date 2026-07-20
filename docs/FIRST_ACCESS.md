# Primeiro acesso, Superadministrador e contexto inicial

O RadiusHub precisa de três elementos coerentes para que o primeiro login termine em um painel utilizável:

1. um usuário ativo com `is_super_admin=true`;
2. ao menos um tenant ativo;
3. ao menos uma empresa ativa vinculada ao tenant.

A versão 1.4.1 reconcilia esses elementos de forma idempotente com:

```bash
php artisan radiushub:bootstrap-platform
```

## Identidade da conta principal

A conta principal é definida no `.env`:

```env
PLATFORM_BOOTSTRAP_ENABLED=true
SEED_ADMIN_NAME="Administrador Master"
SEED_ADMIN_EMAIL=admin@exemplo.com
SEED_ADMIN_LOGIN=admin
SEED_ADMIN_PASSWORD="senha-inicial-forte"
SEED_ADMIN_FORCE_PASSWORD=false
SEED_ADMIN_MUST_CHANGE_PASSWORD=true
```

O login aceita `SEED_ADMIN_LOGIN` ou `SEED_ADMIN_EMAIL`. A senha de um usuário já existente é preservada enquanto `SEED_ADMIN_FORCE_PASSWORD=false`.

## Tenant e empresa padrão

```env
SEED_DEFAULT_TENANT=true
SEED_TENANT_NAME="RadiusHub Principal"
SEED_TENANT_SLUG=principal
SEED_DEFAULT_COMPANY=true
SEED_COMPANY_LEGAL_NAME="Empresa Principal"
SEED_COMPANY_TRADE_NAME="Empresa Principal"
```

O bootstrap reutiliza um contexto ativo existente quando possível. Caso não exista, cria o tenant e a empresa padrão, vincula o Superadministrador como `tenant_admin` e atribui o papel `company_admin`.

## Recuperar instalação CloudPanel antiga com erro 403

Após substituir os arquivos pela versão 1.4.1:

```bash
cd /home/USUARIO/htdocs/DOMINIO
chmod +x scripts/*.sh artisan
bash scripts/repair-cloudpanel-bootstrap.sh
```

O reparo:

- preserva `.env`, `APP_KEY`, banco e credenciais;
- atualiza `APP_VERSION` e corrige configuração Redis herdada do Docker em instalação nativa;
- instala dependências;
- executa migrations pendentes;
- cria ou repara a conta principal;
- cria/reutiliza tenant e empresa padrão;
- vincula todos os Superadministradores ativos;
- recria os caches e reinicia as filas;
- não redefine a senha de usuário existente.

## Redefinição deliberada da senha principal

A forma preferencial é editar temporariamente o `.env`:

```env
SEED_ADMIN_PASSWORD="NovaSenhaForte"
SEED_ADMIN_FORCE_PASSWORD=true
```

Depois:

```bash
php artisan optimize:clear
php artisan radiushub:bootstrap-platform
```

Volte imediatamente a flag:

```env
SEED_ADMIN_FORCE_PASSWORD=false
```

Não coloque senhas diretamente em comandos armazenados no histórico do shell.

## Comportamento de navegação

- Superadministradores são enviados diretamente para `/platform/dashboard` após login ou 2FA, ignorando uma URL antiga armazenada na sessão.
- Um Superadministrador sem tenant não recebe mais 403; ele é direcionado ao painel global para cadastrar ou reparar o contexto.
- Administradores de tenant sem empresa são enviados ao cadastro de empresas.
- A página 403 não cria mais um ciclo de retorno para `/` quando o contexto está ausente.
