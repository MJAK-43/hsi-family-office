# Compte rendu de déploiement — Lot 2A

## Résultat

- Date : 11 août 2026
- Release activée : `20260811T183958Z`
- Sauvegarde pré-déploiement : `/var/backups/hsi-family-office/20260811T183312Z`
- Empreinte du ZIP : `c7d271f81f6c772cf8bce3766070cc2a3bd35fb31d6ee06128ea3d169cda0535`
- État final : production active, hors maintenance
- Archive chiffrée créée : non

## Contrôles réussis

- Sauvegarde pré-déploiement et release validées par SHA-256.
- Extraction Linux validée avant activation.
- Cinq migrations du Lot 2A appliquées dans le batch 2.
- Audit critique : zéro orphelin et cinq contraintes `ON DELETE RESTRICT` conformes.
- Caches Laravel de configuration, routes et vues reconstruits.
- Page de connexion et manifeste Vite accessibles.
- Tableau de bord non authentifié redirigé vers la connexion (`HTTP 302`).
- Route publique d'inscription absente.
- Marqueur serveur `ACTIVATED` créé dans le staging de la release.

## Incident résolu pendant la fenêtre

Le premier contrôle HTTP après activation a répondu `403`. La synchronisation `rsync -a` avait repris le mode `700` du répertoire de staging sur la racine applicative, empêchant Nginx de la traverser. Aucun problème Laravel, aucune perte de données et aucune anomalie de migration n'ont été constatés.

La racine applicative a été rétablie en `755`, avec le propriétaire Plesk attendu. Le générateur d'activation et le runbook ont été corrigés pour appliquer explicitement ce mode lors des prochains déploiements. Tous les contrôles HTTP et d'intégrité ont ensuite réussi.

## Rollback disponible

La sauvegarde `/var/backups/hsi-family-office/20260811T183312Z` reste disponible en mode root-only. Aucun rollback n'a été nécessaire.
