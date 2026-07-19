# Validação da entrega 1.3.0

Executado no ambiente de geração:

- sintaxe de 320 arquivos PHP com `php -l`, incluindo aplicação, testes e SDK Asaas embarcado;
- validação interna do Asaas SDK ARGWS 0.2.62 e dos métodos utilizados pela plataforma;
- sintaxe de todos os scripts Bash com `bash -n`;
- parsing de `docker-compose.yml` e workflows GitHub com PyYAML;
- parsing de `composer.json` e arquivos JSON;
- busca por referências antigas ao webhook baseado em tenant/slug;
- busca pelas credenciais expostas nos logs e prints fornecidos;
- revisão estática do endpoint secreto por gateway, dupla autenticação, idempotência e escopo tenant/empresa/gateway;
- geração da árvore do projeto, manifesto SHA-256 e validação do ZIP.

Não executado neste ambiente:

- `composer install` e PHPUnit, pois Composer e acesso DNS ao Packagist não estavam disponíveis;
- build real das imagens, pois Docker Engine não estava disponível;
- migrations reais em MySQL/PostgreSQL;
- `freeradius -XC`, pois o binário FreeRADIUS não estava instalado;
- chamadas reais ao Asaas, que exigem credenciais Sandbox da empresa.

Os workflows GitHub incluídos executam instalação Composer, testes, migrations em MySQL/PostgreSQL e build das três imagens Docker quando o repositório for publicado. O primeiro deploy deve ocorrer em homologação antes de apontar MikroTiks ou contas Asaas de produção.
