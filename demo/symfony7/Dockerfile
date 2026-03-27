# FrankenPHP (Dunglas) — Symfony 7 demo, HTTPS on localhost
# https://frankenphp.dev/docs/docker/
FROM node:22-alpine AS node
RUN npm install -g pnpm@10

FROM dunglas/frankenphp:1-php8.4-alpine

# System deps + Python (for AcroForm apply/process scripts)
# Prefer apk py3-pypdf so Python finds it regardless of subprocess env (e.g. under FrankenPHP).
RUN apk add --no-cache git unzip bash python3 && \
    (apk add --no-cache py3-pypdf || \
     (apk add --no-cache py3-pip && python3 -m pip install --break-system-packages --no-cache-dir pypdf)) && \
    python3 -c "from pypdf import PdfReader, PdfWriter; print('pypdf OK')"

# PHP extensions (script provided by base image)
RUN install-php-extensions zip xdebug

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node + pnpm (full prefix so npm/pnpm resolve)
COPY --from=node /usr/local /opt/node
ENV PATH="/opt/node/bin:$PATH"

RUN git config --global --add safe.directory /var/pdf-signable-bundle && \
    git config --global --add safe.directory /app

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

# Caddyfile: HTTPS :443 (prod). Caddyfile.dev used when APP_ENV=dev (no worker, no-cache).
COPY docker/caddy/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/caddy/Caddyfile.dev /etc/frankenphp/Caddyfile.dev

# Xdebug (IDE on port 9003)
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Entrypoint: when APP_ENV=dev copy Caddyfile.dev (no worker), var dirs, then run FrankenPHP
RUN echo '#!/bin/sh' > /usr/local/bin/docker-entrypoint.sh && \
    echo 'set -e' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'if [ "${APP_ENV:-prod}" = "dev" ]; then cp /etc/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile; fi' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'mkdir -p /app/var/cache /app/var/log' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'chmod -R 777 /app/var' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile' >> /usr/local/bin/docker-entrypoint.sh && \
    chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
