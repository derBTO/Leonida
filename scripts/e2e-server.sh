#!/usr/bin/env bash
# Startet das Backend für die Browser-Tests auf Port 8010 mit eigener Datenbank leonida_e2e.
# Die Datenbank wird bei jedem Start geleert und mit festen Testdaten gefüllt.
# Wird von frontend/playwright.config.ts gestartet; DB-Zugang wie in backend/.env (DB_HOST, DB_USERNAME, …).
set -euo pipefail
cd "$(dirname "$0")/../backend"
export DB_DATABASE="${E2E_DB_DATABASE:-leonida_e2e}" APP_ENV=local APP_DEBUG=false SESSION_SECURE_COOKIE=false \
       APP_URL=http://127.0.0.1:8010 CACHE_STORE=array LEONIDA_PUBLIC_DIR= \
       LEONIDA_UPLOAD_DIR="$PWD/storage/framework/testing/e2e-uploads"
# Datenbank anlegen, falls nötig (Zugang aus .env, Verbindung zur Standard-Datenbank postgres)
php -r '
  $env = parse_ini_file(".env", false, INI_SCANNER_RAW) ?: [];
  $g = fn ($k, $d) => getenv($k) !== false && getenv($k) !== "" ? getenv($k) : ($env[$k] ?? $d);
  $pdo = new PDO(sprintf("pgsql:host=%s;port=%s;dbname=postgres", $g("DB_HOST", "127.0.0.1"), $g("DB_PORT", "5432")), $g("DB_USERNAME", "postgres"), $g("DB_PASSWORD", ""));
  $db = getenv("DB_DATABASE");
  if (! $pdo->query("SELECT 1 FROM pg_database WHERE datname = " . $pdo->quote($db))->fetchColumn()) { $pdo->exec("CREATE DATABASE \"$db\""); }
'
rm -rf "$LEONIDA_UPLOAD_DIR" && mkdir -p "$LEONIDA_UPLOAD_DIR"
php artisan config:clear >/dev/null
php artisan migrate:fresh --force >/dev/null
php artisan leonida:e2e-seed
exec php artisan serve --host=127.0.0.1 --port=8010
