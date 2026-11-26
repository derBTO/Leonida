#!/bin/sh
# Start des Backends im Entwicklungs-Container
set -e
cd /app/backend
if [ ! -f .env ]; then
    cp .env.example .env
    sed -i -e 's/^APP_ENV=.*/APP_ENV=local/' -e 's/^APP_DEBUG=.*/APP_DEBUG=true/' \
        -e 's#^APP_URL=.*#APP_URL=http://localhost:5173#' -e 's/^LOG_LEVEL=.*/LOG_LEVEL=debug/' \
        -e 's/^SESSION_SECURE_COOKIE=.*/SESSION_SECURE_COOKIE=false/' \
        -e 's/^DB_HOST=.*/DB_HOST=db/' -e 's/^DB_DATABASE=.*/DB_DATABASE=leonida/' \
        -e 's/^DB_USERNAME=.*/DB_USERNAME=leonida/' -e 's/^DB_PASSWORD=.*/DB_PASSWORD=leonida/' \
        -e 's/^LEONIDA_ADMIN_INITIAL_PASSWORD=.*/LEONIDA_ADMIN_INITIAL_PASSWORD=entwicklung-123/' \
        -e 's#^LEONIDA_UPLOAD_DIR=.*#LEONIDA_UPLOAD_DIR=/app/data/uploads#' \
        -e 's#^LEONIDA_MAP_DIR=.*#LEONIDA_MAP_DIR=/app/data/map#' \
        -e 's#^LEONIDA_BACKUP_DIR=.*#LEONIDA_BACKUP_DIR=/app/data/backups#' .env
    composer install --no-interaction
    php artisan key:generate --force
fi
[ -d vendor ] || composer install --no-interaction
until pg_isready -h db -U leonida >/dev/null 2>&1; do sleep 1; done
# Neueste Server-Sicherung aus data/server-backup einspielen, falls neu (scripts/backup-pull.sh legt sie dort ab)
php artisan leonida:restore-latest --dir=/app/data/server-backup || echo "WARNUNG: Server-Sicherung konnte nicht eingespielt werden, starte mit vorhandenen Daten."
php artisan migrate --force
php artisan leonida:bootstrap-admin
php artisan leonida:link
php artisan leonida:map-register
exec php artisan serve --host=0.0.0.0 --port=8000
