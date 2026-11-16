import os
import paramiko
import secrets

password = os.environ["DEPLOY_PASSWORD"]
db_pass = secrets.token_urlsafe(14)
c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(os.environ["DEPLOY_HOST"], username="root", password=password, timeout=20, allow_agent=False, look_for_keys=False)
cmds = [
    "plesk bin database --update hsi_app -help 2>&1 | head -40",
    f"PSA_PASSWORD='{db_pass}' plesk bin database --create-dbuser hsi_app -domain inovixora.com -passwd '' 2>&1",
    f"PSA_PASSWORD='{db_pass}' plesk bin database --update hsi_app -domain inovixora.com -add-dbuser hsi_app 2>&1",
    f"PSA_PASSWORD='{db_pass}' plesk bin database --update hsi_app -domain inovixora.com -login hsi_app -passwd '' 2>&1",
]
for cmd in cmds:
    print("===", cmd[:110])
    _, o, _ = c.exec_command(cmd)
    print(o.read().decode() or "(empty)")
print("DB_PASS", db_pass)
c.close()
