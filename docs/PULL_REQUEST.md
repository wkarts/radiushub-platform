# Pull Request — RadiusHub Platform 1.4.0

## Branch

`feat/v1.4.0-playground-deployment-readiness`

## Título

`feat(deploy): adicionar playground funcional para Docker e CloudPanel`

## Commit

`feat(platform): adicionar playground, healthchecks e smoke tests de deploy`

## Merge

`feat: publicar RadiusHub Platform v1.4.0 com deploy verificável`

## Tag

`v1.4.0`

## Descrição

### Objetivo

Confirmar a aderência do RadiusHub ao planejamento funcional e disponibilizar ambientes de implantação verificáveis para Docker e CloudPanel, preservando a arquitetura Laravel/Blade, o banco existente, o multi-tenant, o RBAC, o FreeRADIUS, o MikroTik por SSH Key, os vouchers, o financeiro e a integração Asaas.

### Evoluções

- adiciona playground Docker descartável com PostgreSQL, Redis, PHP-FPM, Nginx, worker, Scheduler e FreeRADIUS;
- adiciona playground nativo para CloudPanel;
- adiciona instalador Docker integrado ao reverse proxy do CloudPanel;
- adiciona dados demonstrativos multiempresa, usuários, papéis, cliente, plano, acesso, contrato, fatura, vouchers, accounting e auditoria;
- adiciona simulador MikroTik restrito ao playground e integrado ao mesmo serviço usado pelos controllers;
- adiciona `/health/live`, `/health/ready` e `radiushub:health --ready`;
- adiciona smoke test de login com CSRF e sessão autenticada;
- adiciona smoke test FreeRADIUS exigindo `Access-Accept` e `Accounting-Response`;
- adiciona confirmação da sessão de accounting no banco;
- adiciona verificação pós-deploy e auditoria estática do planejamento;
- adiciona matriz de conformidade funcional e documentação operacional;
- amplia o CI para PHP 8.3/8.4, MySQL, PostgreSQL, imagens Docker, playground completo e instalação nativa equivalente ao CloudPanel.

### Segurança

- mantém SSH Key como transporte principal e fallback por senha desabilitado por padrão;
- impede simulador fora do playground;
- impede playground em produção sem autorização explícita;
- mantém portas web e RADIUS do playground vinculadas a `127.0.0.1`;
- desativa `APP_DEBUG` automaticamente em playground publicado por domínio;
- gera segredos localmente e protege o arquivo de ambiente com modo `600`;
- mantém chaves privadas, credenciais RADIUS e tokens fora dos logs;
- corrige o backfill RBAC para não promover operadores e técnicos a administradores da empresa.

### Compatibilidade

- nenhuma funcionalidade existente removida;
- nenhuma migration nova e nenhuma tabela removida;
- atualização incremental a partir da versão 1.3.5;
- suporte preservado para MySQL e PostgreSQL;
- Docker, CloudPanel, FreeRADIUS, MikroTik SSH, vouchers e Asaas preservados;
- release automática e imagens GHCR preservadas.

### Validação

- lint PHP e Bash;
- parsing JSON/YAML;
- integridade de migrations e versão;
- contrato estático de conformidade do planejamento;
- PHPUnit em PHP 8.3 e 8.4 no CI;
- migrations, seed, Doctor e renderização FreeRADIUS em MySQL/PostgreSQL no CI;
- build das imagens app, web e FreeRADIUS;
- login autenticado, RADIUS e accounting no playground Docker;
- fluxo de instalação nativa semelhante ao CloudPanel;
- geração dos snippets Nginx, Supervisor e Cron.

### Homologação externa

RouterOS real, Hotspot/PPPoE em rede física, Asaas Sandbox, SMTP e revisão visual em dispositivos físicos continuam exigindo credenciais e infraestrutura externas. Esses itens não são apresentados como validados pelo simulador.
