# Compte rendu de déploiement — 20 septembre 2026

## Résultat

- Domaine : `https://app.hsi.inovixora.com/`
- Release : `20260920T161710Z`
- État final : production active, hors maintenance
- Sauvegarde pré-déploiement : `/var/backups/hsi-family-office/20260920T161620Z`
- SHA-256 de la release : `756102d241c66f8ce8dba57924ce0a376c30384ae5cd1fc74f34bac526bc01e4`

## Contrôles réussis

- Build Vite de production réussi.
- 125 tests PHP réussis, pour 340 assertions.
- Archive validée : 9 448 entrées, aucun fichier `.env`, aucune base locale et aucun log embarqué.
- Sauvegarde SQL et applicative validée par SHA-256 avant activation.
- Migration `2026_08_13_010000_add_financial_operation_idempotency` appliquée dans le batch 3.
- Audit critique conforme : aucun orphelin et cinq contraintes `ON DELETE RESTRICT` conformes.
- Volumes métier strictement identiques avant et après migration : 10 utilisateurs, 8 profils, 6 cotisations, 8 mouvements, 6 projets, 0 vote et 0 bulletin.
- Page de connexion : HTTP 200.
- Tableau de bord non authentifié : HTTP 302.
- Manifeste Vite : HTTP 200.

## Incident résolu

Le premier contrôle HTTP post-activation a répondu `403`. Les fichiers extraits dans le staging avaient hérité de permissions root-only. Les propriétaires et modes Plesk/Nginx ont été normalisés, les caches Laravel ont été reconstruits et le site a immédiatement retrouvé une réponse HTTP 200.

Le script d'activation archivé avec la release a été corrigé pour normaliser les permissions avant le contrôle HTTP et conserver le rollback automatique actif jusqu'à la réussite de ce contrôle. Les marqueurs serveur `FAILED`, `RECOVERED_AFTER_PERMISSION_FIX` et `ACTIVATED` conservent la chronologie complète de l'intervention.

## Rollback

- Sauvegarde complète : `/var/backups/hsi-family-office/20260920T161620Z`
- Release applicative précédente : `/var/backups/hsi-family-office/releases/20260811T183958Z/candidate`
