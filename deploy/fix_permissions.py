import os
import paramiko

deploy_password = os.environ["DEPLOY_PASSWORD"]
remote = "/var/www/vhosts/inovixora.com/app.hsi.inovixora.com"
sys_user = "inovixora.com_osj41sno7cl"

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(os.environ["DEPLOY_HOST"], username="root", password=deploy_password, timeout=30, allow_agent=False, look_for_keys=False)

script = f"""#!/bin/bash
set -e
chown -R {sys_user}:psacln {remote}
find {remote} -type d -exec chmod 755 {{}} \\;
find {remote} -type f -exec chmod 644 {{}} \\;
chmod -R 775 {remote}/storage {remote}/bootstrap/cache
chmod +x {remote}/artisan
curl -sk -o /dev/null -w 'HTTP %{{http_code}}\\n' https://app.hsi.inovixora.com/login
"""
_, o, _ = c.exec_command(script)
print(o.read().decode())
c.close()
