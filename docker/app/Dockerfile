FROM php:8.2-fpm

# Paquetes necesarios
RUN apt-get update && apt-get install -y \
    git curl unzip libpng-dev libonig-dev libxml2-dev libzip-dev \
 && docker-php-ext-install pdo_mysql mbstring zip gd \
 && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
