# PHP 8.2 Alpine + Node/pnpm (Vite) + Python (pypdf, pytest) for bundle dev and tests
FROM php:8.2-cli-alpine

RUN apk add --no-cache \
    git \
    unzip \
    bash \
    libzip-dev \
    nodejs \
    npm \
    python3 \
    py3-pip

RUN docker-php-ext-install -j$(nproc) zip

# PCOV for code coverage
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del $PHPIZE_DEPS

# Python deps for PDF/PoC tests
RUN python3 -m pip install --break-system-packages --no-cache-dir pypdf pytest \
    && python3 -c "from pypdf import PdfReader, PdfWriter; print('pypdf OK')"

# pnpm for front (Vite)
RUN npm install -g pnpm@9

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN git config --global --add safe.directory /app

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/app/vendor/bin:${PATH}"
