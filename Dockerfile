FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    curl \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js and pnpm (corepack)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && corepack enable \
    && corepack prepare pnpm@9.15.0 --activate \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

COPY . .

CMD ["tail", "-f", "/dev/null"]
