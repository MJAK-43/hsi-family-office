# HSI Family Office — Lot 2A, sécurisation et déploiement

**État du code :** implémenté et validé localement le 10 août 2026  
**État production :** déploiement, sauvegarde et rotation des secrets non exécutés

## Résultat

Le code ferme les chemins critiques identifiés par l'audit : déploiement destructif, données de démonstration en production, inscription libre, comptes suspendus encore actifs, suppression d'identités historiques, escalade de rôles et fuite de données via le dashboard.

Le gate ne doit être déclaré fermé en production qu'après les opérations manuelles décrites à la fin de ce document.

## Changements livrés

### Déploiement et secrets

- suppression de la clé `APP_KEY` codée en dur dans `deploy/remote_deploy.py` ;
- conservation du `.env` existant et génération d'une clé uniquement lors d'une première installation sans clé ;
- suppression de `migrate:fresh --force` et `db:seed --force` des scripts distants ;
- vérification stricte de la clé SSH hôte au lieu d'une acceptation automatique ;
- génération de l'archive dans `deploy/output`, désormais ignoré ;
- exclusion de `.env`, SQLite, logs, sessions, vues et caches de l'archive ;
- suppression de l'ancienne archive locale de 20,4 Mo qui contenait des artefacts sensibles.

### Seeders

- `DatabaseSeeder` ne charge que les référentiels en production ;
- `DemoDataSeeder` regroupe les données de démonstration ;
- une exécution de `DemoDataSeeder` hors `local` ou `testing` lève une exception.

La création d'utilisateurs de production passe par l'administration des membres. Aucun compte de démonstration n'est créé automatiquement.

### Identités et sessions

- routes publiques `GET/POST /register` supprimées ;
- route et interface d'auto-suppression du profil supprimées ;
- middleware `active_member` appliqué à tout l'espace privé ;
- les profils `pending`, `suspended` ou `inactive` sont déconnectés et leur session courante invalidée ;
- la suspension et l'archivage révoquent les sessions en base, les jetons personnels éventuels et le jeton « remember me » ;
- une suppression administrative archive le profil en statut `inactive` et conserve l'identité ainsi que l'historique.

### Intégrité référentielle

La migration `2026_08_10_060000_harden_critical_foreign_keys.php` remplace les cascades destructrices par `RESTRICT` pour :

- `member_profiles.user_id` ;
- `contribution_payments.user_id` ;
- `projects.owner_id` ;
- `vote_ballots.user_id` ;
- `fund_movements.fund_account_id`.

Le rollback restaure les règles `CASCADE`. Avant rollback en production, vérifier explicitement que cette perte de protection est voulue.

### RBAC et dashboard

- un gestionnaire de membres sans permission `manage roles` ne peut attribuer que `member` ou `guest` ;
- le rôle actuel d'une cible reste disponible pour permettre la modification de ses autres champs ;
- aucun utilisateur ne peut modifier son propre rôle ;
- le dernier `super_admin` ne peut être rétrogradé ou archivé ;
- le dashboard exige `view dashboard` ;
- chaque widget est conditionné par ses permissions côté serveur et côté Vue ;
- les cotisations sont limitées au propriétaire sans permission globale ;
- les projets respectent le même périmètre propriétaire/statuts publics que leur contrôleur ;
- la navigation masque les modules non autorisés et les liens vers des modules non implémentés ;
- les fausses notifications et la recherche factice ont été retirées/désactivées.

### Dépendances et outillage

- `npm audit fix` a mis à jour le verrouillage de `nanoid` et `postcss` ;
- `npm audit --omit=dev` retourne désormais `0 vulnerabilities` ;
- la version Node supportée est déclarée dans `package.json` et `.nvmrc` (`22.12.0`).

Le poste courant utilise encore Node `22.4.1` et Composer `2.2.6`. Ces exécutables globaux n'ont pas été modifiés automatiquement. Le détail du gate MySQL est documenté dans [HSI-MYSQL-PREPRODUCTION-GATE.md](HSI-MYSQL-PREPRODUCTION-GATE.md).

## Validation locale

- `php artisan test` : **101 tests réussis, 271 assertions** ;
- suite MySQL 8.3 : **101 tests réussis, 271 assertions** ;
- audit MySQL : **zéro orphelin, cinq contraintes critiques en `RESTRICT`, 31 tables InnoDB** ;
- `npm run build` : réussi ;
- `npm audit --omit=dev` : **0 vulnérabilité** ;
- lint PHP des fichiers critiques : réussi.

Le build signale encore que Node `22.4.1` est inférieur à `22.12.0` et que deux chunks dépassent 500 kB. Ces avertissements ne bloquent pas le Lot 2A mais doivent être traités avant l'optimisation finale.

## Procédure de mise en production

1. Mettre en place ou vérifier la gestion Git et identifier exactement la révision à livrer.
2. Mettre à niveau Node vers `22.12.0` ou une version compatible et Composer vers une version maintenue.
3. Effectuer une sauvegarde complète : base MySQL, `.env`, stockage privé et version applicative actuelle.
4. Restaurer cette sauvegarde dans un environnement isolé et vérifier qu'elle est exploitable.
5. Auditer les contraintes et les éventuels enregistrements orphelins avant la nouvelle migration FK.
6. Construire une nouvelle archive avec `deploy/plesk-deploy.ps1`, puis en inspecter la liste des fichiers.
7. Déployer le code sans seeding et exécuter `php artisan migrate --force`.
8. Exécuter les smoke tests : connexion, permissions par rôle, suspension, dashboard, cotisations, fonds, projets et votes.
9. Inventorier les usages chiffrés par `APP_KEY` (cookies, données chiffrées, jetons) et planifier leur invalidation.
10. Faire tourner `APP_KEY` dans une fenêtre dédiée, purger les caches Laravel, redémarrer les workers et forcer une reconnexion contrôlée.
11. Surveiller les erreurs, les refus d'autorisation et les opérations financières ; conserver le rollback applicatif et la restauration DB prêts.

## Points restant à autoriser ou vérifier

- aucun déploiement distant n'a été exécuté ;
- aucune sauvegarde/restauration réelle n'a été réalisée ;
- aucune clé de production n'a été tournée ;
- la migration doit encore être validée sur une copie MySQL de production ;
- l'hôte SSH doit être présent dans `known_hosts`, faute de quoi les scripts refuseront la connexion ;
- Composer doit être mis à niveau avant qu'un audit Composer natif puisse être intégré au gate.
