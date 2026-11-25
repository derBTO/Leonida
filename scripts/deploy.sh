#!/usr/bin/env bash
# Baut Leonida lokal und spielt es per SSH auf das Hetzner-Webhosting ein.
#
#   scripts/deploy.sh            neue Release bauen, hochladen, migrieren, umschalten
#   scripts/deploy.sh --rollback auf die vorige Release zurückschalten (ohne Datenbank-Rückbau)
#   scripts/deploy.sh --dry-run  nur bauen, nichts hochladen
#
# Voraussetzungen lokal: git, rsync, ssh und entweder php 8.3+/composer/node 20+ oder Docker.
# Zugangsdaten: deploy.env (Vorlage deploy.env.example). Ablauf: docs/INSTALLATION.md

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
MODE="deploy"
case "${1:-}" in
    --rollback) MODE="rollback" ;;
    --dry-run) MODE="dry-run" ;;
    "") ;;
    *) sed -n '2,9p' "$0" | sed 's/^# \{0,1\}//'; exit 2 ;;
esac

CONFIG="$PROJECT_DIR/deploy.env"
[[ -f "$CONFIG" ]] || { echo "FEHLER: $CONFIG fehlt (Vorlage: deploy.env.example)." >&2; exit 1; }
# shellcheck source=/dev/null
source "$CONFIG"
: "${LEONIDA_SSH_HOST:?}" "${LEONIDA_SSH_USER:?}" "${LEONIDA_REMOTE_DIR:?}" "${LEONIDA_PUBLIC_DIR:?in deploy.env setzen}"
PUB="$LEONIDA_PUBLIC_DIR"
PORT="${LEONIDA_SSH_PORT:-222}"
RPHP="${LEONIDA_REMOTE_PHP:-php}"
KEEP="${LEONIDA_KEEP_RELEASES:-5}"
R="$LEONIDA_REMOTE_DIR"
SSH=(ssh -p "$PORT" "$LEONIDA_SSH_USER@$LEONIDA_SSH_HOST")
remote() { "${SSH[@]}" "set -e; $*"; }

if [[ "$MODE" == "rollback" ]]; then
    PREVIOUS="$(remote "cd '$R/releases' && ls -1 | sort | tail -n 2 | head -n 1")"
    CURRENT="$(remote "basename \$(readlink '$R/current')")"
    [[ -n "$PREVIOUS" && "$PREVIOUS" != "$CURRENT" ]] || { echo "Keine ältere Release vorhanden." >&2; exit 1; }
    remote "ln -sfn 'releases/$PREVIOUS' '$R/current.tmp' && mv -T '$R/current.tmp' '$R/current' \
        && rsync -a --delete --exclude=/media --exclude=/map --exclude=/index.php '$R/current/public/' '$PUB/'"
    echo "Zurückgeschaltet: $CURRENT → $PREVIOUS (Datenbank unverändert)."
    exit 0
fi

# composer/npm lokal oder – falls nicht installiert – über Docker
TOOLS=(git)
[[ "$MODE" == "deploy" ]] && TOOLS+=(rsync ssh)
USE_DOCKER_COMPOSER=0
USE_DOCKER_NODE=0
command -v composer >/dev/null && command -v php >/dev/null || USE_DOCKER_COMPOSER=1
command -v npm >/dev/null || USE_DOCKER_NODE=1
if [[ $USE_DOCKER_COMPOSER -eq 1 || $USE_DOCKER_NODE -eq 1 ]]; then TOOLS+=(docker); fi
for tool in "${TOOLS[@]}"; do
    command -v "$tool" >/dev/null || { echo "FEHLER: '$tool' fehlt lokal." >&2; exit 1; }
done
if [[ -n "$(git -C "$PROJECT_DIR" status --porcelain -- backend frontend)" ]]; then
    echo "WARNUNG: backend/ oder frontend/ enthält nicht committete Änderungen – sie werden NICHT ausgeliefert." >&2
fi

RELEASE="$(date +%Y%m%d-%H%M%S)-$(git -C "$PROJECT_DIR" rev-parse --short HEAD)"
BUILD="$(mktemp -d)/leonida-$RELEASE"
mkdir -p "$BUILD"
echo "==> Release $RELEASE bauen in $BUILD"
git -C "$PROJECT_DIR" archive HEAD backend | tar -x -C "$BUILD" --strip-components=1
git -C "$PROJECT_DIR" archive HEAD frontend | tar -x -C "$(dirname "$BUILD")"
git -C "$PROJECT_DIR" archive HEAD imports | tar -x -C "$BUILD"
WORK="$(dirname "$BUILD")"
if [[ $USE_DOCKER_NODE -eq 1 ]]; then
    docker run --rm -v "$WORK:/w" -w /w/frontend node:22-alpine sh -c \
        "npm ci --no-audit --no-fund --silent && npx vite build --outDir /w/$(basename "$BUILD")/public/spa --emptyOutDir >/dev/null"
else
    (cd "$WORK/frontend" && npm ci --no-audit --no-fund --silent && npx vite build --outDir "$BUILD/public/spa" --emptyOutDir >/dev/null)
fi
if [[ $USE_DOCKER_COMPOSER -eq 1 ]]; then
    docker run --rm -v "$BUILD:/app" -w /app composer:2 install --no-dev --prefer-dist --optimize-autoloader \
        --no-interaction --quiet --ignore-platform-req=ext-intl --ignore-platform-req=ext-gd
else
    (cd "$BUILD" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet)
fi
rm -rf "$BUILD/storage" "$BUILD/tests" "$BUILD/.env" "$BUILD/public/media" "$BUILD/public/map"
sed "s#__LEONIDA_APP__#$R/current#" "$PROJECT_DIR/scripts/templates/index.php" > "$BUILD/public/index.web.php"
echo "$RELEASE" > "$BUILD/RELEASE"

if [[ "$MODE" == "dry-run" ]]; then
    echo "==> Probelauf fertig: $BUILD"
    exit 0
fi

echo "==> Hochladen nach $LEONIDA_SSH_HOST:$R/releases/$RELEASE"
remote "mkdir -p '$R/releases' '$R/shared/storage/app' '$R/shared/storage/framework/cache/data' '$R/shared/storage/framework/sessions' \
    '$R/shared/storage/framework/views' '$R/shared/storage/logs' '$R/shared/backups' '$PUB/media' '$PUB/map' \
    && chmod 755 '$PUB' '$PUB/media' '$PUB/map' && chmod 700 '$R/shared'"
remote "test ! -L '$PUB'" || { echo "FEHLER: $PUB ist ein Symlink. Bitte entfernen (rm, ohne Schrägstrich am Ende) und erneut starten." >&2; exit 1; }
remote "test -f '$R/shared/.env'" || { echo "FEHLER: $R/shared/.env fehlt auf dem Server (siehe docs/INSTALLATION.md)." >&2; exit 1; }
LINK_DEST=""
PREV="$(remote "if [ -L '$R/current' ]; then readlink -f '$R/current'; fi")"
[[ -n "$PREV" ]] && LINK_DEST="--link-dest=$PREV/"
rsync -a --delete --exclude=.git ${LINK_DEST:+"$LINK_DEST"} -e "ssh -p $PORT" "$BUILD/" "$LEONIDA_SSH_USER@$LEONIDA_SSH_HOST:$R/releases/$RELEASE/"

echo "==> Einrichten, migrieren, umschalten"
remote "cd '$R/releases/$RELEASE' \
    && ln -sfn '$R/shared/.env' .env \
    && ln -sfn '$R/shared/storage' storage \
    && $RPHP artisan migrate --force \
    && $RPHP artisan leonida:bootstrap-admin \
    && $RPHP artisan leonida:link \
    && $RPHP artisan leonida:map-register \
    && $RPHP artisan config:cache && $RPHP artisan route:cache \
    && ln -sfn 'releases/$RELEASE' '$R/current.tmp' && mv -T '$R/current.tmp' '$R/current' \
    && rsync -a --delete --exclude=/media --exclude=/map --exclude=/index.php --exclude=/index.web.php '$R/current/public/' '$PUB/' \
    && cp '$R/current/public/index.web.php' '$PUB/index.php.tmp' && mv '$PUB/index.php.tmp' '$PUB/index.php'"

echo "==> Prüfen"
remote "cd '$R/current' && $RPHP artisan leonida:check" || echo "WARNUNG: leonida:check meldet Probleme (siehe Tabelle oben)." >&2

echo "==> Alte Releases aufräumen (behalte $KEEP)"
remote "cd '$R/releases' && ls -1 | sort | head -n -$KEEP | xargs -r rm -rf"
echo "==> Fertig: https://leonida.derbto.de (Release $RELEASE)"
