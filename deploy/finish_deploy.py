import os
from pathlib import Path

import paramiko

deploy_password = os.environ["DEPLOY_PASSWORD"]
php = "/opt/plesk/php/8.2/bin/php"
remote = "/var/www/vhosts/inovixora.com/app.hsi.inovixora.com"
sys_user = "inovixora.com_osj41sno7cl"

files = [
    "2026_07_05_020002_create_fund_allocation_rule_items_table.php",
    "2026_07_05_030002_create_project_evaluations_table.php",
]

project_root = Path(__file__).resolve().parent.parent

c = paramiko.SSHClient()
c.load_system_host_keys()
c.set_missing_host_key_policy(paramiko.RejectPolicy())
c.connect(os.environ["DEPLOY_HOST"], username="root", password=deploy_password, timeout=30, allow_agent=False, look_for_keys=False)

sftp = c.open_sftp()
for name in files:
    local = (project_root / "database" / "migrations" / name).read_bytes()
    with sftp.file(f"{remote}/database/migrations/{name}", "wb") as f:
        f.write(local)
sftp.close()

script = f"""#!/bin/bash
set -e
PHP={php}
cd {remote}
chown -R {sys_user}:psacln .
$PHP artisan migrate --force
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
chown -R {sys_user}:psacln .
echo DEPLOY_OK
"""
sftp = c.open_sftp()
with sftp.file("/tmp/hsi_migrate3.sh", "w") as f:
    f.write(script)
sftp.close()

_, o, _ = c.exec_command("chmod +x /tmp/hsi_migrate3.sh && bash /tmp/hsi_migrate3.sh 2>&1; echo EXIT:$?")
out = o.read().decode()
print(out[-15000:])
if "DEPLOY_OK" not in out:
    raise SystemExit(1)
c.close()
