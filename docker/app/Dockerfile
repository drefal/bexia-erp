FROM php:8.3-fpm

ARG TZ=America/Mexico_City
ENV TZ=${TZ}

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git unzip ca-certificates tzdata \
        libzip-dev zlib1g-dev \
        libicu-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        libonig-dev \
        pkg-config \
        libpq-dev; \
    ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo "$TZ" > /etc/timezone; \
    echo "date.timezone=${TZ}" > /usr/local/etc/php/conf.d/zz-timezone.ini; \
    \
    docker-php-ext-install -j"$(nproc)" intl pdo_mysql pdo_pgsql bcmath mbstring exif zip; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" gd; \
    \
    { \
      echo "memory_limit=512M"; \
      echo "opcache.enable=1"; \
      echo "opcache.validate_timestamps=0"; \
    } > /usr/local/etc/php/conf.d/zz-tuning.ini; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
CMD ["php-fpm"]

# BEXIA_V5523O1B_XSL
# Requerido para CFDI: cadena original SAT con XSLTProcessor / ext-xsl.
RUN if command -v apt-get >/dev/null 2>&1; then \
        apt-get update \
        && apt-get install -y --no-install-recommends libxslt1-dev \
        && docker-php-ext-install xsl \
        && rm -rf /var/lib/apt/lists/*; \
    elif command -v apk >/dev/null 2>&1; then \
        apk add --no-cache libxslt-dev \
        && docker-php-ext-install xsl; \
    else \
        echo "No apt-get/apk available to install xsl" && exit 1; \
    fi

# BEXIA_V5523P8_COMPOSER
# Composer requerido para deploy dentro del contenedor.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
