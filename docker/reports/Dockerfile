ARG BASE_IMAGE=php:8.5-fpm

FROM ${BASE_IMAGE}

USER root

RUN set -eux; \
    docker-php-ext-install -j1 pcntl; \
    extension_dir="$(php-config --extension-dir)"; \
    test -s "${extension_dir}/pcntl.so"; \
    mkdir -p /opt/bexia-worker/extensions; \
    mv "${extension_dir}/pcntl.so" \
       /opt/bexia-worker/extensions/pcntl.so; \
    rm -f \
       /usr/local/etc/php/conf.d/docker-php-ext-pcntl.ini; \
    chmod 0755 /opt/bexia-worker/extensions/pcntl.so; \
    php -d extension=/opt/bexia-worker/extensions/pcntl.so \
        -r 'exit(extension_loaded("pcntl") ? 0 : 1);'; \
    php -r 'exit(extension_loaded("pcntl") ? 1 : 0);'

USER www-data
