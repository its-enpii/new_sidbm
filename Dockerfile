# syntax=docker/dockerfile:1.7
#
# Base image for new_sidbm (PHP-FPM 8.4). All app source, vendor/, and .env
# are mounted at runtime from the host via the `app` service's volumes, so
# this image only ships the runtime — system packages, PHP extensions, and
# the entrypoint. Code edits on the host take effect after `docker compose
# restart app` (or auto-reload in dev) without rebuilding the image.

FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

# System packages + PHP extensions in a single layer. The list is kept
# alphabetised inside each group so diffs are minimal.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
    && rm -rf /var/lib/apt/lists/*

# Composer (pinned to v2 to match the lock file format).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Match the host UID/GID so bind-mounted files are not owned by root.
RUN groupmod -o -g "$GID" www-data \
    && usermod -o -u "$UID" -g www-data www-data

# Project config & entrypoint.
WORKDIR /var/www/html
COPY docker/app/php.ini       /usr/local/etc/php/conf.d/99-sidbm.ini
COPY docker/app/entrypoint.sh /usr/local/bin/sidbm-entrypoint
RUN chmod +x /usr/local/bin/sidbm-entrypoint

USER www-data

ENTRYPOINT ["sidbm-entrypoint"]
CMD ["php-fpm"]
