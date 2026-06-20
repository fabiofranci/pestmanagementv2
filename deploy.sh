#!/usr/bin/env bash

set -euo pipefail

APP_DIR="/var/www/apppestmanagement"
APP_USER="www-data"
BRANCH="main"

cd "$APP_DIR"

echo "==> Deploy Pest Management V2"
echo "==> Directory: $APP_DIR"
echo "==> Branch: $BRANCH"
echo "==> User Laravel: $APP_USER"

if [ "$(id -u)" -ne 0 ]; then
    echo "ERRORE: eseguire questo script come root."
    exit 1
fi

run_as_app_user() {
    sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && $*"
}

echo "==> Metto applicazione in maintenance mode"
run_as_app_user "php artisan down || true"

echo "==> Aggiorno repository"
git fetch origin
git checkout "$BRANCH"
git pull origin "$BRANCH"

echo "==> Installo dipendenze Composer"
run_as_app_user "composer install --no-dev --optimize-autoloader"

echo "==> Installo dipendenze Node e build assets"
run_as_app_user "npm ci --include=dev"
run_as_app_user "npm run build"

echo "==> Migration database centrale"
run_as_app_user "php artisan migrate --force"

echo "==> Migration database tenant"
run_as_app_user "php artisan tenants:migrate"

echo "==> Pulizia e cache Laravel"
run_as_app_user "php artisan optimize:clear"
run_as_app_user "php artisan config:cache"
run_as_app_user "php artisan route:cache"
run_as_app_user "php artisan view:cache"
run_as_app_user "php artisan event:cache"

echo "==> Storage link"
run_as_app_user "php artisan storage:link || true"

echo "==> Permessi storage/cache"
chown -R "$APP_USER":"$APP_USER" storage bootstrap/cache
chmod -R ug+rw storage bootstrap/cache

echo "==> Restart queue"
run_as_app_user "php artisan queue:restart || true"

echo "==> Riattivo applicazione"
run_as_app_user "php artisan up"

echo "==> Deploy completato"