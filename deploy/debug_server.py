import os
import paramiko

deploy_password = os.environ["DEPLOY_PASSWORD"]
remote = "/var/www/vhosts/inovixora.com/app.hsi.inovixora.com"
c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(os.environ["DEPLOY_HOST"], username="root", password=deploy_password, timeout=30, allow_agent=False, look_for_keys=False)

script = f"""
curl -sk https://app.hsi.inovixora.com/login >/dev/null
sleep 1
tail -30 {remote}/storage/logs/laravel.log
echo '---PHP TEST---'
cd {remote}/public && REQUEST_METHOD=GET REQUEST_URI=/login SCRIPT_NAME=/index.php /opt/plesk/php/8.2/bin/php -d display_errors=1 index.php 2>&1 | head -40
echo '---FIND ERROR LOG---'
find /var/www/vhosts/inovixora.com/logs -name '*app.hsi*' 2>/dev/null | head -5
ls /var/www/vhosts/system/app.hsi.inovixora.com/logs/ 2>/dev/null
tail -10 /var/www/vhosts/system/app.hsi.inovixora.com/logs/error_log 2>/dev/null
"""
_, o, _ = c.exec_command(script)
print(o.read().decode()[-8000:])
c.close()
