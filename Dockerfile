# Imagen de desarrollo LOCAL (docs/decisiones/0010-entorno-local-docker-compose.md).
# No es la imagen de producción — eso se define cuando el servidor esté definido.

FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
        libpq-dev \
        libzip-dev \
        unzip \
        git \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
