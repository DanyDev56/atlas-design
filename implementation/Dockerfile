# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.4

FROM php:${PHP_VERSION}-cli-bookworm AS php-extensions

RUN apt-get update && apt-get install -y --no-install-recommends \
    $PHPIZE_DEPS \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install -j"$(nproc)" opcache pcntl pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

FROM php-extensions AS development

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    git \
    jq \
    ripgrep \
    unzip \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /workspace/implementation/app

FROM php-extensions AS composer-dependencies

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /build

COPY app/composer.json app/composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

COPY app/ ./

RUN APP_ENV=production \
    APP_KEY=base64:MDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA= \
    composer dump-autoload \
    --no-dev \
    --classmap-authoritative \
    --no-interaction \
    && composer check-platform-reqs --no-dev

FROM node:22-bookworm-slim AS web-assets

WORKDIR /build

COPY app/package.json app/package-lock.json ./

RUN npm ci --ignore-scripts

COPY app/ ./

RUN npm run build

FROM php:${PHP_VERSION}-cli-bookworm AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq5 \
    libzip4 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=php-extensions /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-extensions /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY docker/php/runtime.ini /usr/local/etc/php/conf.d/99-atlas-runtime.ini
COPY --chmod=755 docker/runtime-entrypoint.sh /usr/local/bin/atlas-runtime-entrypoint

WORKDIR /app

COPY --from=composer-dependencies /build/ /app/
COPY --from=web-assets /build/public/build/ /app/public/build/

RUN mkdir -p \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    && find /app -type d -exec chmod 0755 {} + \
    && find /app -type f -exec chmod 0644 {} + \
    && chmod 0755 artisan \
    && chown -R www-data:www-data bootstrap/cache storage

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stack \
    LOG_STACK=json_stderr

USER www-data

EXPOSE 8000

STOPSIGNAL SIGTERM

ENTRYPOINT ["atlas-runtime-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
