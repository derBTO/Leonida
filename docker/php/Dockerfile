# Nur für die lokale Entwicklung. Produktion läuft auf dem Hetzner-Webhosting (siehe docs/INSTALLATION.md).
FROM php:8.3-cli-alpine
RUN apk add --no-cache postgresql-dev postgresql-client libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev icu-dev libzip-dev git unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql gd intl zip \
    && echo "upload_max_filesize=25M" > /usr/local/etc/php/conf.d/leonida.ini \
    && echo "post_max_size=30M" >> /usr/local/etc/php/conf.d/leonida.ini \
    && echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/leonida.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app/backend
