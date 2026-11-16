# HSI Family Office — Preuve de sauvegarde et simulation production

**Exécution :** 10 août 2026  
**Production :** `server1.inovixora.com`  
**Application :** `/var/www/vhosts/inovixora.com/app.hsi.inovixora.com`

## Périmètre respecté

L'opération n'a exécuté aucun déploiement, seeding, migration, changement de configuration ou rotation de clé sur la production.

Les écritures distantes se limitent à la création de la sauvegarde root-only. Le script temporaire placé dans `/tmp` a été supprimé après validation.

## Sauvegarde serveur

Emplacement :

```text
/var/backups/hsi-family-office/20260810T214312Z
```

Contenu :

- `database.sql.gz` : dump transactionnel MariaDB, 16 960 octets ;
- `application-files.tar.gz` : `.env`, stockage privé et snapshot applicatif, 702 785 octets ;
- `database-summary.tsv` ;
- `MANIFEST.txt` ;
- `SHA256SUMS`.

Tous les fichiers sont `root:root` en mode `600`. `sha256sum -c SHA256SUMS`, `gzip -t` et `tar -tzf` ont réussi.

La sauvegarde se trouve encore sur le même serveur physique. Elle ne constitue donc pas, seule, une sauvegarde hors site.

## Copie locale chiffrée

Archive :

```text
C:\Users\mboni\AppData\Local\HSI\backups\20260810T214312Z\hsi-production-backup.secure.7z
```

Clé protégée par DPAPI :

```text
C:\Users\mboni\AppData\Local\HSI\backups\20260810T214312Z\hsi-production-backup.secure-key.dpapi
```

Empreinte SHA-256 de l'archive :

```text
4531B81A196134CB5318399D7DCF1EA3058584534B761E9840A8FCA4CC09A1BA
```

L'archive utilise AES-256 avec chiffrement des noms de fichiers. La clé contient un secret aléatoire de 256 bits protégé par DPAPI. Un test dans un nouveau processus a confirmé que DPAPI permet de vérifier l'archive.

La version locale intermédiaire insuffisamment protégée et les fichiers téléchargés en clair ont été supprimés. Cette suppression logique ne garantit pas l'effacement physique des blocs d'un SSD.

### Limite de récupération

DPAPI lie la clé au compte Windows et à cette machine. La perte du profil Windows peut rendre la copie locale inutilisable. Avant de considérer la stratégie de sauvegarde complète, il faut créer une seconde copie chiffrée avec un secret portable conservé dans un gestionnaire de mots de passe ou un coffre d'entreprise.

## Vérification locale de l'archive

```powershell
powershell -NoProfile -ExecutionPolicy Bypass `
  -File scripts\production\restore_encrypted_backup.ps1 `
  -ArchivePath "C:\Users\mboni\AppData\Local\HSI\backups\20260810T214312Z\hsi-production-backup.secure.7z" `
  -KeyPath "C:\Users\mboni\AppData\Local\HSI\backups\20260810T214312Z\hsi-production-backup.secure-key.dpapi" `
  -VerifyOnly
```

Une extraction exige un nouveau dossier dont le nom se termine par `_restore`. Le script lui applique une ACL limitée au compte Windows courant et à `SYSTEM`, puis vérifie `SHA256SUMS`.

## Restauration et audit réalisés

Le dump a été restauré par flux SSH chiffré, sans fichier SQL local en clair, dans deux bases temporaires suffixées `_test`.

État restauré :

- 28 tables InnoDB ;
- 20 migrations ;
- 10 utilisateurs ;
- 8 profils ;
- 6 cotisations ;
- 8 mouvements de fonds ;
- 6 projets.

Audit pré-migration : zéro orphelin sur les relations existantes. `vote_ballots` a été classée comme table future.

## Simulation de migration

Les migrations suivantes ont été appliquées sur le clone :

- création des votes, options et bulletins ;
- ajout des champs de financement projet ;
- remplacement des cinq cascades critiques par `RESTRICT`.

Résultats :

- cinq migrations réussies ;
- zéro orphelin après migration ;
- cinq contraintes critiques en `RESTRICT` ;
- zéro table non-InnoDB ;
- volumes utilisateurs, profils, cotisations, mouvements et projets strictement inchangés ;
- valeurs de statut présentes compatibles avec les enums applicatives.

Les deux bases locales contenant les données de production ont été supprimées après validation. Elles restent récupérables depuis les sauvegardes chiffrées.

## Décisions encore requises

1. Créer une copie hors site avec une clé de récupération portable et testée.
2. Définir la durée de rétention des sauvegardes serveur et locales.
3. Programmer la fenêtre de déploiement du Lot 2A.
4. Programmer séparément la rotation de `APP_KEY` et l'invalidation des sessions.
