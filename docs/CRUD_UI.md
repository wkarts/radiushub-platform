# Padrão de CRUD e interface

## Convenção

Todos os módulos usam:

- `index` com pesquisa, filtros e paginação;
- cadastro/edição em modal;
- Form Request no backend;
- mensagens flash padronizadas;
- confirmação em ações críticas;
- exclusão bloqueada quando existem vínculos;
- Policies/RBAC;
- auditoria;
- estado vazio;
- tabela desktop e cards mobile quando necessário.

Não há edição inline por padrão.

## Menu lateral

- expansão/recolhimento manual;
- estado persistido em `localStorage`;
- apenas ícones no desktop recolhido;
- tooltips;
- submenus como flyout no desktop recolhido;
- drawer com overlay no mobile;
- fechamento automático após navegação mobile;
- rolagem interna;
- item ativo destacado.

## Responsividade

Breakpoints principais:

- acima de 900 px: sidebar fixa;
- até 900 px: menu móvel;
- até 760 px: tabelas marcadas como `desktop-table` são substituídas por `card-list`;
- até 480 px: ações e cards em uma coluna.

Botões possuem área mínima de toque, modais limitam altura e rolam internamente, formulários usam grid fluido e nenhum conteúdo deve forçar rolagem horizontal da página.

## Novos módulos

Para manter consistência:

1. use `<x-page-header>`;
2. use `.toolbar`, `.filter-form`, `.card`, `.table-wrap`;
3. inclua equivalente `.card-list` no mobile para tabelas extensas;
4. use `<x-modal>` e `.section-card` nos formulários;
5. use `<x-empty-state>` e `<x-status-badge>`;
6. não coloque lógica de autorização apenas na view.
