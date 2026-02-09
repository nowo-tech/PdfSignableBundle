FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    curl \
    && docker-php-ext-install zip \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js and pnpm (required for bundle assets build)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update \
    && apt-get install -y nodejs \
    && corepack enable \
    && corepack prepare pnpm@9.15.0 --activate \
    && rm -rf /var/lib/apt/lists/*

# Xdebug: default config (client_host is overridden via env in docker-compose)
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

ENV COMPOSER_ALLOW_SUPERUSER=1

# Entrypoint crea var/cache y var/log y ajusta permisos para el volumen montado
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

WORKDIR /workspace/demo/symfony7
CMD ["php-fpm", "-F"]
