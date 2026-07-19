# Upgrade RadiusHub 1.3.5 para 1.4.0

A versão 1.4.0 adiciona deploy verificável e modo playground sem alterar as tabelas ou remover recursos da versão 1.3.5.

## O que foi acrescentado

- liveness e readiness reais;
- playground Docker com PostgreSQL, Redis, aplicação, worker, Scheduler, Nginx e FreeRADIUS;
- simulador MikroTik isolado do modo de produção;
- dados demonstrativos multiempresa, usuários, acessos, vouchers, fatura e sessão;
- smoke test de login, autenticação RADIUS e accounting;
- instalador de playground para CloudPanel;
- verificação pós-deploy e matriz de conformidade do planejamento.

## CloudPanel existente

```bash
chmod +x scripts/upgrade-1.3.5-to-1.4.0.sh
./scripts/upgrade-1.3.5-to-1.4.0.sh
```

O script não habilita o modo playground na instalação existente. Para testes, crie outro domínio, outro banco e use `scripts/install-cloudpanel-playground.sh`.

## Docker existente

```bash
export RADIUSHUB_TAG=1.4.0
./scripts/update-docker.sh --build
```

## Verificação

```bash
./scripts/validate-deployment.sh --http
```

## Preservação

São preservados `.env`, `APP_KEY`, dados, chaves SSH, credenciais RADIUS, gateways Asaas e tokens de webhook.
