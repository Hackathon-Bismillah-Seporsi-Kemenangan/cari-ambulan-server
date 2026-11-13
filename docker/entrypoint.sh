#!/bin/sh
# Prepares a fresh checkout, then runs the container's command.
set -e

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# Only the web container (RUN_MIGRATIONS) builds the caches; the others share
# bootstrap/cache and must not wipe them. A cache built on the host would
# point at the host's database, so start from a clean slate first.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan optimize:clear >/dev/null
    php artisan migrate --force
    # Skipping the framework's file scans on every request is worth ~10 ms.
    php artisan config:cache >/dev/null
    php artisan route:cache >/dev/null || echo "route cache skipped (closure routes)"
    php artisan event:cache >/dev/null
    php artisan view:cache >/dev/null
fi

# Test accounts for the mobile apps (driver@ / pasien@cariambulan.id). Only
# creates what is missing, so it is safe on every start.
if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
    php artisan db:seed --class=DemoDataSeeder --force
fi

exec "$@"
