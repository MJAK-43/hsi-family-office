#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

APP="/var/www/vhosts/inovixora.com/app.hsi.inovixora.com"
STAGE="/var/backups/hsi-family-office/releases/20260920T161710Z"
CANDIDATE="$STAGE/candidate"
ZIP="$STAGE/hsi-family-office-release.zip"
BACKUP="/var/backups/hsi-family-office/20260920T161620Z"
ROLLBACK_CANDIDATE="/var/backups/hsi-family-office/releases/20260811T183958Z/candidate"
PHP="/opt/plesk/php/8.2/bin/php"
SYSTEM_USER="inovixora.com_osj41sno7cl"
EXPECTED_SHA256="756102d241c66f8ce8dba57924ce0a376c30384ae5cd1fc74f34bac526bc01e4"
CONFIRMATION="ACTIVATE-20260920T161710Z"
MAINTENANCE_STARTED=0

if [[ "${1:-}" != "$CONFIRMATION" ]]; then
    echo "Refus : jeton de confirmation absent ou invalide." >&2
    exit 2
fi

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Refus : activation reservee a root." >&2
    exit 2
fi

if [[ "$(realpath --canonicalize-existing "$APP")" != "$APP" ]]; then
    echo "Refus : chemin applicatif inattendu." >&2
    exit 2
fi

test -x "$PHP"
test -f "$APP/.env"
test -f "$ZIP"
test -f "$ROLLBACK_CANDIDATE/artisan"
test -f "$ROLLBACK_CANDIDATE/vendor/autoload.php"
test ! -e "$STAGE/ACTIVATED"

printf '%s  %s\n' "$EXPECTED_SHA256" "$(basename "$ZIP")" > "$STAGE/SHA256SUMS"
(
    cd "$STAGE"
    sha256sum -c SHA256SUMS
)

(
    cd "$BACKUP"
    test ! -e FAILED
    sha256sum -c SHA256SUMS
)

test ! -e "$CANDIDATE"
install -d -m 700 "$CANDIDATE"
unzip -q "$ZIP" -d "$CANDIDATE"
test -f "$CANDIDATE/artisan"
test -f "$CANDIDATE/vendor/autoload.php"
test -f "$CANDIDATE/public/build/manifest.json"
test -f "$CANDIDATE/database/migrations/2026_08_13_010000_add_financial_operation_idempotency.php"
test ! -f "$CANDIDATE/.env"

DB_NAME="$($PHP -r '$env = parse_ini_file($argv[1], false, INI_SCANNER_RAW); echo $env["DB_DATABASE"] ?? "";' "$APP/.env")"
if [[ ! "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "Refus : nom de base invalide." >&2
    exit 2
fi

record_counts() {
    local destination="$1"
    MYSQL_PWD="$(< /etc/psa/.psa.shadow)" mysql \
        --user=admin \
        --host=localhost \
        --batch \
        --skip-column-names \
        "$DB_NAME" \
        --execute="SELECT 'users', COUNT(*) FROM users
            UNION ALL SELECT 'member_profiles', COUNT(*) FROM member_profiles
            UNION ALL SELECT 'contribution_payments', COUNT(*) FROM contribution_payments
            UNION ALL SELECT 'fund_movements', COUNT(*) FROM fund_movements
            UNION ALL SELECT 'projects', COUNT(*) FROM projects
            UNION ALL SELECT 'votes', COUNT(*) FROM votes
            UNION ALL SELECT 'vote_ballots', COUNT(*) FROM vote_ballots" \
        > "$destination"
}

restore_previous_release() {
    rsync -a --delete \
        --exclude='/.env' \
        --exclude='/storage/' \
        --exclude='/public/storage' \
        --exclude='/bootstrap/cache/' \
        "$ROLLBACK_CANDIDATE/" "$APP/"
    normalize_permissions
    cd "$APP"
    "$PHP" artisan optimize:clear || true
    "$PHP" artisan config:cache || true
    "$PHP" artisan route:cache || true
    "$PHP" artisan view:cache || true
    "$PHP" artisan up || true
}

normalize_permissions() {
    chown -R "$SYSTEM_USER":psacln "$APP"
    find "$APP" -type d \
        ! -path "$APP/storage*" \
        ! -path "$APP/bootstrap/cache*" \
        -exec chmod 755 {} +
    find "$APP" -type f \
        ! -path "$APP/storage*" \
        ! -path "$APP/bootstrap/cache*" \
        ! -name .env \
        -exec chmod 644 {} +
    chmod 600 "$APP/.env"
    chmod 755 "$APP/artisan"
    find "$APP/storage" "$APP/bootstrap/cache" -type d -exec chmod 775 {} +
    find "$APP/storage" "$APP/bootstrap/cache" -type f -exec chmod 664 {} +
}

on_error() {
    status=$?
    touch "$STAGE/FAILED"
    chmod 600 "$STAGE/FAILED"
    echo "ACTIVATION_FAILED status=$status" >&2
    if [[ "$MAINTENANCE_STARTED" -eq 1 ]]; then
        echo "Restauration automatique de la release precedente." >&2
        restore_previous_release
    fi
    exit "$status"
}
trap on_error ERR

record_counts "$STAGE/counts-before.tsv"

cd "$APP"
"$PHP" artisan down --retry=60
MAINTENANCE_STARTED=1

rsync -a --delete \
    --exclude='/.env' \
    --exclude='/storage/' \
    --exclude='/public/storage' \
    --exclude='/bootstrap/cache/' \
    "$CANDIDATE/" "$APP/"

normalize_permissions

cd "$APP"
"$PHP" artisan optimize:clear
"$PHP" artisan migrate --force
"$PHP" artisan hsi:audit-critical-integrity
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan storage:link || test -L "$APP/public/storage"

normalize_permissions

record_counts "$STAGE/counts-after.tsv"
diff -u "$STAGE/counts-before.tsv" "$STAGE/counts-after.tsv"

"$PHP" artisan up
/usr/bin/curl -fsS -o /dev/null https://app.hsi.inovixora.com/login
MAINTENANCE_STARTED=0
"$PHP" artisan about --only=environment --no-ansi
"$PHP" artisan migrate:status --no-ansi

touch "$STAGE/ACTIVATED"
chmod 600 "$STAGE/ACTIVATED" "$STAGE/counts-before.tsv" "$STAGE/counts-after.tsv" "$STAGE/SHA256SUMS"
trap - ERR
echo "ACTIVATION_OK"
