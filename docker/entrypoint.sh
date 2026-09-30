#!/usr/bin/env bash
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

echo "Waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
until mysqladmin ping -h "${DB_HOST:-mysql}" -P "${DB_PORT:-3306}" -u"${DB_USERNAME:-root}" -p"${DB_PASSWORD:-root}" --ssl=0 --silent; do
    sleep 2
done
echo "MySQL is up."

# APP_KEY is supplied via docker-compose's shared environment (the same key
# must be used by every service, since they decrypt the same encrypted
# columns) — only fall back to generating one if it's genuinely missing,
# which would only happen running this image outside docker-compose.
if [ -z "${APP_KEY}" ] && ! grep -q "^APP_KEY=base64" .env; then
    php artisan key:generate --force
fi

# Only one service needs to run migrations/seeders; the others (queue,
# scheduler) just wait for MySQL and exec their own command.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
    # These seeders use firstOrCreate/updateOrCreate, so re-running on every
    # container start is safe and never duplicates data.
    php artisan db:seed --force
fi

exec "$@"
