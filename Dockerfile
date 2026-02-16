FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    curl \
    && docker-php-ext-install zip \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && rm -rf /var/lib/apt/lists/*

RUN apt-get update && apt-get install -y python3 python3-pip \
    && python3 -m pip install --break-system-packages --no-cache-dir pypdf pytest \
    && python3 -c "from pypdf import PdfReader, PdfWriter; print('pypdf OK')" \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js and pnpm (corepack)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && corepack enable \
    && corepack prepare pnpm@9.15.0 --activate \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Allow git when project is mounted at /app (e.g. different host ownership)
RUN git config --global --add safe.directory /app

COPY composer.json composer.lock* ./

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

COPY . .

CMD ["tail", "-f", "/dev/null"]
