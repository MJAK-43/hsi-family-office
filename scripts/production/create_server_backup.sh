#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

APP_DIR="${1:-/var/www/vhosts/inovixora.com/app.hsi.inovixora.com}"
BACKUP_ROOT="${2:-/var/backups/hsi-family-office}"
PHP_BIN="${PHP_BIN:-/opt/plesk/php/8.2/bin/php}"

APP_DIR="$(realpath --canonicalize-existing "$APP_DIR")"

if [[ "$APP_DIR" != "/var/www/vhosts/inovixora.com/app.hsi.inovixora.com" ]]; then
    echo "Refus : chemin applicatif inattendu." >&2
    exit 1
fi

if [[ ! -f "$APP_DIR/.env" || ! -x "$PHP_BIN" ]]; then
    echo "Refus : application ou runtime PHP introuvable." >&2
    exit 1
fi

DB_NAME="$($PHP_BIN -r '$env = parse_ini_file($argv[1], false, INI_SCANNER_RAW); echo $env["DB_DATABASE"] ?? "";' "$APP_DIR/.env")"

if [[ ! "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "Refus : nom de base invalide." >&2
    exit 1
fi

if [[ ! -r /etc/psa/.psa.shadow ]]; then
    echo "Refus : identifiants administrateur Plesk indisponibles." >&2
    exit 1
fi

install -d -m 700 "$BACKUP_ROOT"
TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP_DIR="$BACKUP_ROOT/$TIMESTAMP"

if [[ -e "$BACKUP_DIR" ]]; then
    echo "Refus : destination de sauvegarde déjà existante." >&2
    exit 1
fi

install -d -m 700 "$BACKUP_DIR"

on_error() {
    touch "$BACKUP_DIR/FAILED"
    chmod 600 "$BACKUP_DIR/FAILED"
    echo "Sauvegarde incomplète : $BACKUP_DIR" >&2
}
trap on_error ERR

export MYSQL_PWD="$(< /etc/psa/.psa.shadow)"

mysqldump \
    --user=admin \
    --host=localhost \
    --single-transaction \
    --quick \
    --skip-lock-tables \
    --routines \
    --triggers \
    --events \
    --hex-blob \
    --default-character-set=utf8mb4 \
    "$DB_NAME" | gzip -9 > "$BACKUP_DIR/database.sql.gz"

mysql \
    --user=admin \
    --host=localhost \
    --batch \
    --skip-column-names \
    --execute="SELECT TABLE_NAME, ENGINE, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA='${DB_NAME}' ORDER BY TABLE_NAME" \
    > "$BACKUP_DIR/database-summary.tsv"

unset MYSQL_PWD

ARCHIVE_ITEMS=(
    .env
    app
    artisan
    bootstrap
    composer.json
    composer.lock
    config
    database
    public
    resources
    routes
    storage/app
)

for optional_item in package.json package-lock.json; do
    if [[ -e "$APP_DIR/$optional_item" ]]; then
        ARCHIVE_ITEMS+=("$optional_item")
    fi
done

tar -czf "$BACKUP_DIR/application-files.tar.gz" \
    -C "$APP_DIR" \
    "${ARCHIVE_ITEMS[@]}"

gzip -t "$BACKUP_DIR/database.sql.gz"
tar -tzf "$BACKUP_DIR/application-files.tar.gz" > /dev/null

cat > "$BACKUP_DIR/MANIFEST.txt" <<EOF
created_at_utc=$TIMESTAMP
hostname=$(hostname -f)
application_path=$APP_DIR
database=$DB_NAME
database_server=$(mysql --version | head -n 1)
php=$($PHP_BIN -r 'echo PHP_VERSION;')
EOF

(
    cd "$BACKUP_DIR"
    sha256sum database.sql.gz application-files.tar.gz database-summary.tsv MANIFEST.txt > SHA256SUMS
    chmod 600 database.sql.gz application-files.tar.gz database-summary.tsv MANIFEST.txt SHA256SUMS
)

trap - ERR
echo "BACKUP_DIR=$BACKUP_DIR"
