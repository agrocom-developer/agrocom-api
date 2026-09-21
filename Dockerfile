# Imagen de desarrollo LOCAL (docs/decisiones/0010-entorno-local-docker-compose.md).
# No es la imagen de producción — eso se define cuando el servidor esté definido.

FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
        libpq-dev \
        libzip-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_pgsql zip gd exif \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Margen sobre el límite de negocio de subida de archivos (ver docker/uploads.ini).
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html
