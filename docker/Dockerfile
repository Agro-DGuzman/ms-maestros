# Etapa de dependencias: composer aparte para que el cache de capas sobreviva
# a cualquier cambio de codigo que no toque composer.json/lock.
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./

# Sin scripts: artisan todavia no esta copiado y post-autoload-dump lo invoca.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --no-progress

# El último parche de la 8.4 en cada build, a propósito: así entra solo cada
# parche de seguridad. Debian sí va fijo, porque el driver de SQL Server
# depende de su versión.
FROM dunglas/frankenphp:php8.4-trixie AS runtime

RUN install-php-extensions pdo_sqlsrv sqlsrv intl opcache

# Sin X-Powered-By: la versión exacta de PHP es justo lo que busca quien
# rastrea servidores sin parchear. Solo se puede apagar desde el ini.
RUN echo 'expose_php = Off' > "$PHP_INI_DIR/conf.d/zz-ms-maestros.ini"

WORKDIR /app

COPY --from=vendor /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Recien ahora existe artisan: se completa lo que --no-scripts salteo.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# FrankenPHP sirve desde public/. Sin esto entrega el raiz del proyecto.
ENV SERVER_ROOT=/app/public
ENV SERVER_NAME=:8080

EXPOSE 8080

# /up es el health endpoint que ya declara bootstrap/app.php.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1
