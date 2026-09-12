FROM ghcr.io/eventpoints/php:main AS composer
ENV APP_ENV=prod APP_DEBUG=0 PHP_OPCACHE_PRELOAD="/app/config/preload.php" PHP_EXPOSE_PHP=off PHP_OPCACHE_VALIDATE_TIMESTAMPS=0
WORKDIR /app
# Xdebug is compiled into the base image; drop any Xdebug conf.d file and let
# zz-prod.ini force xdebug.mode=off so it adds no runtime overhead in prod.
RUN rm -f /usr/local/etc/php/conf.d/*xdebug*.ini
COPY docker/php/zz-prod.ini /usr/local/etc/php/conf.d/zz-prod.ini
RUN mkdir -p var/cache var/log
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts

# Build front-end assets with Vite (the PHP base image has no Node).
FROM node:22-bookworm-slim AS node_builder
WORKDIR /app
COPY package.json package-lock.json ./
# @symfony/ux-turbo is a file: dependency on the composer vendor dir.
COPY --from=composer /app/vendor ./vendor
RUN npm ci
# Tailwind v4 scans templates/ and src/ for class names (@source in app.css);
# the JS/CSS entrypoints live in assets/.
COPY assets ./assets
COPY templates ./templates
COPY src ./src
COPY vite.config.js ./
RUN npm run build

FROM composer AS php
WORKDIR /app
COPY . .
# Vite build output (public/build is not committed).
COPY --from=node_builder /app/public/build ./public/build

RUN composer dump-autoload --classmap-authoritative
RUN composer symfony:dump-env prod

RUN php bin/console assets:install --no-interaction

# Warm the cache at build so nothing has to be written at runtime. Without this
# the first process to touch a template creates var/cache/prod, and console
# commands run as root via `docker exec` win that race with root:root 755, after
# which php-fpm (www-data) cannot create new Twig shards. 512M because compiling
# every Twig template (EasyAdmin's included) exhausts the default 128M.
RUN php -d memory_limit=512M bin/console cache:warmup --env=prod

# After the warmup, so it covers var/cache/prod. public/uploads must exist in
# the image so the named volume mounted over it inherits a writable mode.
RUN mkdir -p public/uploads/posters && chmod -R 777 var public/uploads

FROM php AS worker
RUN apt-get update && apt-get install -y --no-install-recommends \
    supervisor \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /var/log/supervisor

COPY .deployment/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD supervisorctl status | grep -E "RUNNING" || exit 1

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

FROM ghcr.io/eventpoints/caddy:sha-fc43d4e AS caddy
COPY --from=php /app/public public/
