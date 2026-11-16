# Déploiement du Lot 2A — procédure d'activation et de rollback

## État préparé

- Release : `20260811T183958Z`
- Staging serveur : `/var/backups/hsi-family-office/releases/20260811T183958Z`
- ZIP : `hsi-family-office-release.zip`
- SHA-256 : `c7d271f81f6c772cf8bce3766070cc2a3bd35fb31d6ee06128ea3d169cda0535`
- Sauvegarde de rollback : `/var/backups/hsi-family-office/20260811T183312Z`
- Application : `/var/www/vhosts/inovixora.com/app.hsi.inovixora.com`
- PHP : `/opt/plesk/php/8.2/bin/php`
- Utilisateur système Plesk : `inovixora.com_osj41sno7cl:psacln`

La release n'est pas chiffrée. Elle est stockée dans un répertoire `root:root` en mode `700`, avec des fichiers en mode `600`. Aucune extraction, migration ou activation n'a encore été effectuée.

## Conditions obligatoires avant activation

1. Obtenir une autorisation explicite pour la fenêtre de maintenance et l'exécution des migrations.
2. Vérifier que le site est disponible et que Laravel n'est pas déjà en maintenance.
3. Vérifier de nouveau la sauvegarde et la release :

```bash
cd /var/backups/hsi-family-office/20260811T183312Z
sha256sum -c SHA256SUMS

cd /var/backups/hsi-family-office/releases/20260811T183958Z
sha256sum -c SHA256SUMS-20260811T183958Z
```

4. Créer une nouvelle sauvegarde serveur immédiatement avant l'activation :

```bash
bash /root/create_server_backup.sh
```

Noter le chemin de cette nouvelle sauvegarde dans le journal d'intervention. Ne pas créer d'archive chiffrée locale.

## Activation contrôlée

Toutes les commandes suivantes sont à exécuter en tant que `root`. Les variables sont volontairement bornées à des chemins fixes.

```bash
set -Eeuo pipefail

APP=/var/www/vhosts/inovixora.com/app.hsi.inovixora.com
STAGE=/var/backups/hsi-family-office/releases/20260811T183958Z
CANDIDATE=/var/backups/hsi-family-office/releases/20260811T183958Z/candidate
PHP=/opt/plesk/php/8.2/bin/php

test "$(realpath "$APP")" = /var/www/vhosts/inovixora.com/app.hsi.inovixora.com
test "$(id -u)" -eq 0
cd "$STAGE"
sha256sum -c SHA256SUMS-20260811T183958Z
test ! -e "$CANDIDATE"
install -d -m 700 "$CANDIDATE"
unzip -q hsi-family-office-release.zip -d "$CANDIDATE"
test -f "$CANDIDATE/artisan"
test -f "$CANDIDATE/vendor/autoload.php"
test -f "$CANDIDATE/public/build/manifest.json"

cd "$APP"
"$PHP" artisan down --retry=60

rsync -a --delete \
  --exclude=.env \
  --exclude=storage/ \
  --exclude=public/storage \
  --exclude=bootstrap/cache/ \
  "$CANDIDATE/" "$APP/"

chown -R inovixora.com_osj41sno7cl:psacln "$APP"
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
"$PHP" artisan storage:link
"$PHP" artisan up

/usr/bin/curl -fsS -o /dev/null https://app.hsi.inovixora.com/login
"$PHP" artisan about --only=environment --no-ansi
"$PHP" artisan migrate:status --no-ansi
```

Le déploiement n'est validé qu'après un test manuel de connexion avec un compte actif et un contrôle des pages Tableau de bord, Membres, Contributions, Projets et Votes.

## Rollback applicatif immédiat

Les migrations du Lot 2A ajoutent des structures et renforcent des clés étrangères ; elles ont été simulées sur une restauration de production et restent compatibles avec l'ancien code. Pour un défaut applicatif sans corruption de données, restaurer d'abord le code sans revenir en arrière sur la base.

Remplacer `BACKUP` par la sauvegarde créée juste avant l'activation :

```bash
set -Eeuo pipefail

APP=/var/www/vhosts/inovixora.com/app.hsi.inovixora.com
BACKUP=/var/backups/hsi-family-office/REMPLACER_PAR_LA_SAUVEGARDE_PRE_DEPLOIEMENT
ROLLBACK=/var/backups/hsi-family-office/rollback-lot-2a
PHP=/opt/plesk/php/8.2/bin/php

test "$(realpath "$APP")" = /var/www/vhosts/inovixora.com/app.hsi.inovixora.com
test -f "$BACKUP/application-files.tar.gz"
cd "$BACKUP"
sha256sum -c SHA256SUMS
test ! -e "$ROLLBACK"
install -d -m 700 "$ROLLBACK"
tar -xzf "$BACKUP/application-files.tar.gz" -C "$ROLLBACK"
test -f "$ROLLBACK/artisan"

cd "$APP"
"$PHP" artisan down --retry=60 || true

rsync -a --delete \
  --exclude=.env \
  --exclude=storage/ \
  --exclude=public/storage \
  --exclude=bootstrap/cache/ \
  "$ROLLBACK/" "$APP/"

chown -R inovixora.com_osj41sno7cl:psacln "$APP"
chmod 755 "$APP"
find "$APP/storage" "$APP/bootstrap/cache" -type d -exec chmod 775 {} +
find "$APP/storage" "$APP/bootstrap/cache" -type f -exec chmod 664 {} +

cd "$APP"
"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan up
/usr/bin/curl -fsS -o /dev/null https://app.hsi.inovixora.com/login
```

## Restauration complète de la base

La restauration de la base est destructive pour les écritures survenues après la sauvegarde. Elle ne doit être exécutée qu'en cas d'anomalie d'intégrité confirmée, avec une autorisation distincte. Avant toute restauration, conserver le dump de l'état en échec pour permettre l'analyse et la récupération éventuelle des transactions récentes.

## Critères d'arrêt

Laisser l'application en maintenance et déclencher le rollback si l'une de ces étapes échoue : migration, audit d'intégrité, génération des caches, réponse HTTP, connexion ou contrôle fonctionnel. Ne jamais lancer `migrate:fresh`, `db:seed` ou une rotation de `APP_KEY` en production.
