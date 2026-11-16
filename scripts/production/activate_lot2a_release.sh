#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

APP="/var/www/vhosts/inovixora.com/app.hsi.inovixora.com"
STAGE="/var/backups/hsi-family-office/releases/20260811T183958Z"
CANDIDATE="$STAGE/candidate"
BACKUP="/var/backups/hsi-family-office/20260811T183312Z"
PHP="/opt/plesk/php/8.2/bin/php"
SYSTEM_USER="inovixora.com_osj41sno7cl"
CONFIRMATION="ACTIVATE-LOT-2A-20260811T183958Z"

if [[ "${1:-}" != "$CONFIRMATION" ]]; then
    echo "Refus : jeton de confirmation absent ou invalide." >&2
    exit 2
fi

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Refus : activation réservée à root." >&2
    exit 2
fi

if [[ "$(realpath --canonicalize-existing "$APP")" != "$APP" ]]; then
    echo "Refus : chemin applicatif inattendu." >&2
    exit 2
fi

test -x "$PHP"
test -f "$APP/.env"
test -f "$CANDIDATE/artisan"
test -f "$CANDIDATE/vendor/autoload.php"
test -f "$CANDIDATE/public/build/manifest.json"
test ! -f "$CANDIDATE/.env"
test ! -e "$STAGE/ACTIVATED"

(
    cd "$STAGE"
    sha256sum -c SHA256SUMS-20260811T183958Z
)

(
    cd "$BACKUP"
    test ! -e FAILED
    sha256sum -c SHA256SUMS
)

on_error() {
    status=$?
    echo "ACTIVATION_FAILED status=$status" >&2
    echo "L'application reste en maintenance pour diagnostic ou rollback." >&2
    exit "$status"
}
trap on_error ERR

cd "$APP"
"$PHP" artisan down --retry=60

rsync -a --delete \
    --exclude='/.env' \
    --exclude='/storage/' \
    --exclude='/public/storage' \
    --exclude='/bootstrap/cache/' \
    "$CANDIDATE/" "$APP/"

chown -R "$SYSTEM_USER":psacln "$APP"
chmod 755 "$APP"
find "$APP/storage" "$APP/bootstrap/cache" -type d -exec chmod 775 {} +
find "$APP/storage" "$APP/bootstrap/cache" -type f -exec chmod 664 {} +

cd "$APP"
"$PHP" artisan optimize:clear
"$PHP" artisan migrate --force
"$PHP" artisan hsi:audit-critical-integrity
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan storage:link || test -L "$APP/public/storage"

chown -R "$SYSTEM_USER":psacln "$APP"
find "$APP/storage" "$APP/bootstrap/cache" -type d -exec chmod 775 {} +
find "$APP/storage" "$APP/bootstrap/cache" -type f -exec chmod 664 {} +

"$PHP" artisan up
/usr/bin/curl -fsS -o /dev/null https://app.hsi.inovixora.com/login
"$PHP" artisan about --only=environment --no-ansi
"$PHP" artisan migrate:status --no-ansi

touch "$STAGE/ACTIVATED"
chmod 600 "$STAGE/ACTIVATED"
trap - ERR
echo "ACTIVATION_OK"
