# HSI Family Office — Gate MySQL de préproduction

**Dernière validation locale :** 10 août 2026  
**Serveur validé :** MySQL 8.3.0, PHP 8.2.18

## Objectif

Ce gate vérifie sur MySQL réel ce que la suite SQLite ne peut pas garantir : création du schéma InnoDB, migrations de clés étrangères, règles `ON DELETE`, absence de références orphelines et comportement de la suite fonctionnelle.

Il travaille uniquement sur une base dont le nom se termine par `_test`. Le runner et `Tests\TestCase` refusent les autres noms.

## Exécution

Sous Wamp avec les valeurs par défaut :

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\test-mysql.ps1
```

Avec une autre installation :

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\test-mysql.ps1 `
  -MySqlBinary "C:\chemin\mysql.exe" `
  -HostName "127.0.0.1" `
  -Port 3306 `
  -Database "hsi_preproduction_test" `
  -Username "hsi_test" `
  -Password "mot-de-passe-local"
```

Pour vérifier uniquement migrations, contraintes et rollback :

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\test-mysql.ps1 -SkipTests
```

Le mot de passe est placé temporairement dans `MYSQL_PWD` pour l'appel au client puis supprimé du processus dans un bloc `finally`.

## Étapes automatisées

1. Vérification obligatoire du suffixe `_test`.
2. Création idempotente de la base dédiée.
3. Configuration Laravel en `testing`, MySQL et InnoDB.
4. `migrate:fresh` limité à cette base protégée.
5. Audit des références critiques et des règles `ON DELETE`.
6. Rollback de la dernière migration, puis réapplication.
7. Nouvel audit des contraintes.
8. Suite PHPUnit complète avec `phpunit.mysql.xml`.

## Commande d'audit

Avant migration sur une copie de production :

```bash
php artisan hsi:audit-critical-integrity --orphans-only
```

Après migration :

```bash
php artisan hsi:audit-critical-integrity
```

La commande contrôle :

- `member_profiles.user_id -> users.id` ;
- `contribution_payments.user_id -> users.id` ;
- `projects.owner_id -> users.id` ;
- `vote_ballots.user_id -> users.id` ;
- `fund_movements.fund_account_id -> fund_accounts.id`.

Après migration, chaque règle doit être `RESTRICT` ou `NO ACTION`, avec zéro orphelin.

## Résultat obtenu

- 25 migrations appliquées avec succès ;
- migration `2026_08_10_060000_harden_critical_foreign_keys` appliquée sur MySQL ;
- cinq relations critiques en `RESTRICT` ;
- zéro référence orpheline ;
- 31 tables, toutes en InnoDB ;
- 101 tests réussis, 271 assertions.

## Incident détecté et correction

Le MySQL Wamp local utilisait `MyISAM` comme moteur par défaut. La première migration échouait sur l'index unique `users.email` avec une limite de clé à 1 000 octets.

La configuration Laravel impose maintenant `InnoDB` par défaut pour les connexions MySQL et MariaDB via `DB_ENGINE`. La configuration globale de Wamp n'a pas été modifiée.

## Passage à une copie de production

Ce runner ne restaure pas et n'anonymise pas automatiquement une sauvegarde réelle. Pour la préproduction :

1. créer une base isolée suffixée `_test` ;
2. restaurer la sauvegarde avec un compte limité à cette base ;
3. anonymiser les données personnelles si l'environnement n'est pas strictement privé ;
4. exécuter l'audit `--orphans-only` avant toute migration ;
5. sauvegarder la copie restaurée ;
6. exécuter `php artisan migrate --force` ;
7. exécuter l'audit complet et les smoke tests ;
8. détruire la copie conformément à la politique de conservation après validation.
