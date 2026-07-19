# Validação da entrega 1.2.0

Executado no ambiente de geração:

- sintaxe de 245 arquivos PHP com `php -l`;
- sintaxe de todos os scripts Bash com `bash -n`;
- parsing de `docker-compose.yml` e workflows GitHub com PyYAML;
- parsing dos arquivos Composer JSON;
- busca por `ILIKE`, `jsonb` e `after()` incompatíveis com MySQL;
- busca pelas credenciais expostas nos logs e prints fornecidos;
- geração de árvore do projeto, manifesto SHA-256 e validação do ZIP.

Não executado neste ambiente:

- `composer install`, pois Composer e acesso ao Packagist não estavam disponíveis;
- build real das imagens, pois Docker Engine não estava disponível;
- migrations reais em MySQL/PostgreSQL;
- `freeradius -XC`, pois o binário FreeRADIUS não estava instalado;
- chamadas reais ao Asaas, que exigem credenciais Sandbox do tenant.

Essas validações são executadas pelos workflows GitHub incluídos quando o repositório for publicado. O primeiro deploy deve ser feito em homologação antes de apontar MikroTiks de produção.
