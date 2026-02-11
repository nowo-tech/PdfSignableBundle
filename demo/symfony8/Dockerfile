# FrankenPHP (Dunglas) — Symfony 8 demo, HTTPS on localhost
# https://frankenphp.dev/docs/docker/
FROM node:22-alpine AS node
RUN npm install -g pnpm@10

FROM dunglas/frankenphp:1-php8.4-alpine

# System deps
RUN apk add --no-cache git unzip bash

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

# Xdebug (IDE on port 9003)
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Entrypoint: var dirs + run FrankenPHP (absolute paths like TwigInspector)
RUN echo '#!/bin/sh' > /usr/local/bin/docker-entrypoint.sh && \
    echo 'set -e' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'mkdir -p /app/var/cache /app/var/log' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'chmod -R 777 /app/var' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile' >> /usr/local/bin/docker-entrypoint.sh && \
    chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
