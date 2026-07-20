# Pull Request — RadiusHub Platform 1.4.0

## Branch

`feat/v1.4.0-playground-deployment-readiness`

## Título

`feat(deploy): entregar RadiusHub 1.4.0 com playground e bootstrap funcional`

## Commit corretivo

`fix(platform): corrigir distribuição do playground e bootstrap inicial`

## Mensagem de merge

`feat: publicar RadiusHub Platform v1.4.0 com deploy e primeiro acesso validados`

## Resumo

O PR entrega os ambientes Docker/CloudPanel e corrige as falhas encontradas na primeira validação da 1.4.0: arquivos `.env` de playground ausentes após checkout, scripts sem permissão executável e instalação anterior sem Superadministrador/tenant/empresa coerentes.

Consulte a descrição completa apresentada junto ao pacote da entrega.

## Correções da revisão 3

- Corrige falha do teste `PlaygroundSeederTest` causada pelo fallback de `SEED_ADMIN_EMAIL`.
- Corrige o binding do `MikrotikSimulatorService`, que fazia o container principal encerrar no smoke do playground, preservando o construtor compatível.
- Corrige `optimize:clear` antes da migration da tabela `cache` no instalador CloudPanel nativo.
- Adiciona proteção de regressão para os três cenários.

Análise técnica detalhada: `docs/LOG_ANALYSIS_80442689140.md`.
