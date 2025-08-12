# docker/app/Dockerfile
FROM php:8.2-fpm

# Paquetes y extensiones PHP necesarias para Laravel
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
 && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Optimiza capas: primero composer.*
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction

# Copia el proyecto
COPY . .

# Autoload optimizado y permisos
RUN composer dump-autoload --optimize \
 && chown -R www-data:www-data /var/www \
 && chmod -R 775 storage bootstrap/cache
