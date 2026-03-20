#!/bin/bash
# ═══════════════════════════════════════════════════════════════════
#  YL Laravel — Container Entrypoint
#
#  Runs on every container start (both web servers and workers).
#  Sequence:
#    1. Generate APP_KEY if missing
#    2. Wait for MySQL to be accepting connections
#    3. Run migrations (idempotent — safe to run on every boot)
#    4. Hand off to supervisor (web) OR the CMD override (worker)
# ═══════════════════════════════════════════════════════════════════
set -e

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " YL Laravel Container Starting..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Ensure storage is writable at runtime
chmod -R 775 /var/www/html/storage
chown -R www-data:www-data /var/www/html/storage
touch /var/www/html/storage/logs/laravel.log
chmod 664 /var/www/html/storage/logs/laravel.log
chown www-data:www-data /var/www/html/storage/logs/laravel.log

# ── 1. Application key ────────────────────────────────────────────
# key:generate is safe to run repeatedly; --force skips the prompt
if grep -q "^APP_KEY=$" /var/www/html/.env 2>/dev/null || [ -z "$APP_KEY" ]; then
    echo "→ Generating APP_KEY..."
    cd /var/www/html && php artisan key:generate --force
fi

# ── 2. Wait for MySQL ─────────────────────────────────────────────
echo "→ Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
until php -r "
    new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD')
    );
" 2>/dev/null; do
    echo "  MySQL not ready — retrying in 3s..."
    sleep 3
done
echo "→ MySQL is ready."

# ── 3. Run migrations ─────────────────────────────────────────────
# Each module's ServiceProvider registers its migrations via loadMigrationsFrom(),
# so artisan migrate automatically picks up all installed modules.

# ── 4. Publish Swagger UI assets and config ────────────────────────
if [ "${SKIP_MIGRATE}" != "true" ]; then
    echo "→ Running migrations..."
    cd /var/www/html && php artisan migrate --force --graceful
    echo "→ Migrations complete."

    echo "→ Publishing Swagger assets..."
    cd /var/www/html && php artisan vendor:publish \
        --provider="L5Swagger\L5SwaggerServiceProvider"

    echo "→ Generating Swagger docs..."
    cd /var/www/html && php artisan l5-swagger:generate || true
else
    echo "→ Skipping migrations and Swagger (worker container)."
fi

# ── 5. Start process ──────────────────────────────────────────────
# If a CMD was provided (e.g. worker containers override with `php artisan queue:work`)
# use that; otherwise start supervisord which runs php-fpm + nginx.
if [ "$#" -gt 0 ]; then
    echo "→ Executing custom command: $@"
    cd /var/www/html && exec "$@"
else
    echo "→ Starting supervisord (php-fpm + nginx)..."
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi
