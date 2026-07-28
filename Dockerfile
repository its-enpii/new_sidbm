FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libonig-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath intl mbstring opcache pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupmod -o -g "$GID" www-data \
    && usermod -o -u "$UID" -g www-data www-data

WORKDIR /var/www/html

COPY docker/app/php.ini /usr/local/etc/php/conf.d/99-sidbm.ini
COPY docker/app/entrypoint.sh /usr/local/bin/sidbm-entrypoint

RUN chmod +x /usr/local/bin/sidbm-entrypoint

USER www-data

ENTRYPOINT ["sidbm-entrypoint"]
CMD ["php-fpm"]
