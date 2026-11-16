#!/usr/bin/env python3
import os
import sys
import zipfile
from pathlib import Path

import paramiko

HOST = os.environ["DEPLOY_HOST"]
USER = "root"
PASSWORD = os.environ.get("DEPLOY_PASSWORD", "")
REMOTE = "/var/www/vhosts/inovixora.com/app.hsi.inovixora.com"
ZIP_PATH = Path(__file__).resolve().parent / "output" / "hsi-family-office-release.zip"
PHP = "/opt/plesk/php/8.2/bin/php"
SYS_USER = "inovixora.com_osj41sno7cl"


def run(client, cmd, check=True):
    print(f"$ {cmd[:120]}...")
    _, stdout, stderr = client.exec_command(cmd, get_pty=True)
    code = stdout.channel.recv_exit_status()
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")
    if out.strip():
        print(out[-4000:])
    if check and code != 0:
        raise RuntimeError(f"Failed ({code}): {err or out[-2000:]}")
    return code, out, err


def main():
    if not PASSWORD:
        print("DEPLOY_PASSWORD required", file=sys.stderr)
        return 1
    if not ZIP_PATH.exists():
        print(f"Missing {ZIP_PATH}", file=sys.stderr)
        return 1

    client = paramiko.SSHClient()
    client.load_system_host_keys()
    client.set_missing_host_key_policy(paramiko.RejectPolicy())
    print(f"Connecting {USER}@{HOST}...")
    client.connect(HOST, username=USER, password=PASSWORD, timeout=30, allow_agent=False, look_for_keys=False)

    sftp = client.open_sftp()
    remote_zip = f"{REMOTE}/hsi-family-office-release.zip"
    print(f"Uploading {ZIP_PATH.name}...")
    sftp.put(str(ZIP_PATH), remote_zip)
    sftp.close()

    deploy_sh = f"""#!/bin/bash
set -e
PHP={PHP}
REMOTE={REMOTE}
SYS={SYS_USER}
cd "$REMOTE"
mkdir -p tmp_deploy
unzip -oq hsi-family-office-release.zip -d tmp_deploy || test $? -le 1
if [ -d tmp_deploy/hsi-family-office-release ]; then SRC=tmp_deploy/hsi-family-office-release; else SRC=tmp_deploy; fi
cp -a "$SRC"/. .
rm -rf tmp_deploy hsi-family-office-release.zip index.html public/hot 2>/dev/null || true

DB_NAME=hsi_app
DB_USER=hsi_app
DB_PASS=$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 16)

if ! plesk bin database --info "$DB_NAME" -domain app.hsi.inovixora.com >/dev/null 2>&1; then
  plesk bin database --create "$DB_NAME" -domain app.hsi.inovixora.com -type mysql -passwd "$DB_PASS" -add-dbuser "$DB_USER"
else
  DB_PASS=$(plesk bin database --info "$DB_NAME" -domain app.hsi.inovixora.com | awk -F': ' '/Password/ {{print $2; exit}}')
fi

if [ ! -f .env ]; then
  cp .env.production.example .env
fi
cat > /tmp/hsi_env.php <<'PHPEOF'
<?php
$env = file_get_contents('.env');
$repl = [
  '/^APP_URL=.*/m' => 'APP_URL=https://app.hsi.inovixora.com',
  '/^APP_ENV=.*/m' => 'APP_ENV=production',
  '/^APP_DEBUG=.*/m' => 'APP_DEBUG=false',
  '/^DB_CONNECTION=.*/m' => 'DB_CONNECTION=mysql',
  '/^DB_HOST=.*/m' => 'DB_HOST=127.0.0.1',
  '/^DB_DATABASE=.*/m' => 'DB_DATABASE=' . getenv('DB_NAME'),
  '/^DB_USERNAME=.*/m' => 'DB_USERNAME=' . getenv('DB_USER'),
  '/^DB_PASSWORD=.*/m' => 'DB_PASSWORD=' . getenv('DB_PASS'),
];
foreach ($repl as $pattern => $value) {{
  $env = preg_replace($pattern, $value, $env);
}}
file_put_contents('.env', $env);
PHPEOF
DB_NAME="$DB_NAME" DB_USER="$DB_USER" DB_PASS="$DB_PASS" $PHP /tmp/hsi_env.php
rm -f /tmp/hsi_env.php

if grep -Eq '^APP_KEY=$' .env; then
  $PHP artisan key:generate --force
fi

plesk bin site --update app.hsi.inovixora.com -www-root "$REMOTE/public"

chown -R $SYS:psacln "$REMOTE"
find "$REMOTE/storage" "$REMOTE/bootstrap/cache" -type d -exec chmod 775 {{}} \\;
find "$REMOTE/storage" "$REMOTE/bootstrap/cache" -type f -exec chmod 664 {{}} \\;

cd "$REMOTE"
$PHP artisan storage:link || true
$PHP artisan migrate --force
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

chown -R $SYS:psacln "$REMOTE"
echo DEPLOY_OK
"""

    sftp = client.open_sftp()
    with sftp.file("/tmp/hsi_deploy.sh", "w") as f:
        f.write(deploy_sh)
    sftp.close()

    run(client, "chmod +x /tmp/hsi_deploy.sh && bash /tmp/hsi_deploy.sh")
    client.close()
    print("Done: https://app.hsi.inovixora.com/login")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
