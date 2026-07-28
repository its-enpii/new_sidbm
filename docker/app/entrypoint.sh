#!/bin/sh
set -eu

mkdir -p \
    bootstrap/cache \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

exec "$@"
