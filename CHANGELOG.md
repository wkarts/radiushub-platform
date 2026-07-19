# Changelog

## 1.2.0 - 2026-07-19

### Corrigido

- login não depende mais de hostname Docker `redis` em instalações nativas;
- cache/limiter/fila possuem configuração específica para CloudPanel e Docker;
- suporte real a MySQL e PostgreSQL no Laravel e no FreeRADIUS;
- remoção de `ILIKE`, `jsonb` e posicionamento de colunas incompatíveis com MySQL;
- credenciais RADIUS com criptografia nativa para MySQL/PostgreSQL;
- configuração FreeRADIUS renderizada e validável por comando Artisan;
- entrypoints Docker com espera de banco, migrations controladas e permissões;
- proxy confiável, cookies HTTPS e exceção CSRF restrita aos webhooks;
- configuração antiga e ambígua do FreeRADIUS removida.

### Adicionado

- instaladores completos para Docker e CloudPanel nativo;
- atualização, backup e diagnóstico para ambos os modos;
- imagens separadas `app`, `web` e `freeradius`;
- Docker Compose com profiles MySQL/PostgreSQL;
- workflows CI, GHCR, release e Dependabot;
- documentação de GitHub, Docker, CloudPanel e bancos;
- comando `radiushub:doctor`;
- comando `radiushub:credentials:reencrypt`;
- comando `radiushub:radius:render`.

## 1.1.0 - 2026-07-18

- integração do SDK Asaas ARGWS 0.2.62;
- clientes, cobranças, Pix, boleto, cartão, webhooks, reconciliação e estornos.

## 1.0.0 - 2026-07-18

- versão inicial da plataforma.
