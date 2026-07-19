# syntax=docker/dockerfile:1.7
FROM composer:2.10 AS vendor
WORKDIR /app
COPY composer.json ./
COPY packages/asaas-sdk-argws ./packages/asaas-sdk-argws
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction

FROM php:8.4-fpm-bookworm
ARG DEBIAN_FRONTEND=noninteractive
ARG VCS_REF=""
ARG BUILD_DATE=""
ARG VERSION="1.2.0"
LABEL org.opencontainers.image.title="RadiusHub Platform" \
      org.opencontainers.image.description="Laravel multi-tenant para MikroTik, FreeRADIUS e cobrança Asaas" \
      org.opencontainers.image.version="$VERSION" \
      org.opencontainers.image.revision="$VCS_REF" \
      org.opencontainers.image.created="$BUILD_DATE" \
      org.opencontainers.image.source="https://github.com/wkarts/radiushub-platform"

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl git gosu unzip freeradius-utils \
    libpq-dev default-libmysqlclient-dev libicu-dev libzip-dev libonig-dev \
    libcurl4-openssl-dev libxml2-dev \
    && docker-php-ext-install -j"$(nproc)" \
       pdo_mysql mysqli pdo_pgsql pgsql bcmath pcntl intl zip opcache mbstring curl dom \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY docker/app/php.ini /usr/local/etc/php/conf.d/99-radiushub.ini
COPY docker/app/opcache.ini /usr/local/etc/php/conf.d/10-opcache.ini
COPY docker/app/entrypoint.sh /usr/local/bin/radiushub-entrypoint
RUN chmod +x /usr/local/bin/radiushub-entrypoint \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["radiushub-entrypoint"]
CMD ["php-fpm", "-F"]
