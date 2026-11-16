# Lot 2B — Intégrité financière v1

## État initial vérifié

Le contrôle post-déploiement du Lot 2A est conforme : production disponible, 25 migrations exécutées, aucun job échoué, worker de queue actif, login en `HTTP 200` et dashboard non authentifié en `HTTP 302`.

L'audit agrégé de production du 12 août 2026 donne :

- 5 comptes de fonds, tous en EUR ;
- 8 mouvements de fonds, tous en EUR ;
- 6 cotisations, toutes en EUR ;
- zéro incohérence entre la devise d'un mouvement et celle de son compte ;
- zéro écart entre `fund_accounts.current_balance` et la somme des mouvements validés.

Aucune donnée personnelle n'a été extraite pour cet audit.

## Risques constatés

### 1. Cotisation et allocation non atomiques

`ContributionController::validatePayment()` valide d'abord la cotisation, puis déclenche l'allocation dans une seconde transaction. Une erreur d'allocation laisse donc une cotisation validée sans mouvements correspondants.

### 2. Contrôles de solde concurrents

`FundService::createMovement()` et `FundService::transfer()` recalculent et contrôlent le solde avant d'avoir verrouillé les comptes concernés. Deux débits simultanés peuvent tous deux observer le même solde disponible.

### 3. Usage de nombres flottants

Les allocations, transferts, financements, validations budgétaires, comparaisons de soldes et plusieurs formatters convertissent les valeurs `DECIMAL` en `float`. Cela rend les règles d'arrondi implicites et fragilise les comparaisons exactes.

### 4. Idempotence incomplète

L'allocation recherche un mouvement existant mais ne dispose pas encore d'une contrainte unique garantissant l'idempotence en concurrence. Les références de transfert fondées sur la seconde courante peuvent entrer en collision et ne constituent pas une clé de requête rejouable.

### 5. Devises insuffisamment contraintes

Les formulaires acceptent tout code de trois caractères. Un mouvement manuel peut porter une devise différente de son compte et un transfert entre comptes de devises différentes n'est pas refusé explicitement.

### 6. Écritures validées encore mutables

Les mouvements utilisent le soft delete et l'annulation modifie leur statut. Le modèle cible doit rendre une écriture comptabilisée immuable et produire une contre-écriture liée pour toute correction.

## Premier incrément réalisé : fondation Money/Currency

Les objets immuables suivants ont été ajoutés :

- `App\ValueObjects\Currency` : normalisation et validation d'un code ISO alphabétique sur trois caractères ;
- `App\ValueObjects\Money` : stockage en unités mineures entières, parsing décimal strict, addition, soustraction et comparaison avec contrôle de devise.

Choix de sécurité :

- aucun `float` n'est accepté par la fabrique ;
- plus de deux décimales sont refusées au lieu d'être arrondies silencieusement ;
- toute opération entre devises différentes échoue ;
- les dépassements de capacité entière sont refusés ;
- aucune migration ni donnée de production n'est modifiée par cette fondation.

## Ordre d'implémentation recommandé

### Incrément 2B.1 — Montants et allocation exacte

1. Ajouter une allocation proportionnelle en unités mineures, avec stratégie explicite du reste.
2. Faire entrer les montants HTTP sous forme de chaînes et les convertir immédiatement en `Money`.
3. Remplacer les `float` dans `FundService`, puis dans le financement de projet.
4. Centraliser le formatage monétaire sans conversion flottante.
5. Ajouter les tests d'arrondi : `0.01`, tiers, reste sur dernière ligne et grands montants.

Gate : aucun `float` dans les chemins d'écriture de `FundMovement` et somme exacte des allocations égale à la cotisation.

### État de l'incrément 2B.1

L'incrément est implémenté localement et n'est pas encore déployé en production :

- allocation en points de base avec méthode des plus forts restes ;
- somme exacte des centimes alloués égale au montant de la cotisation ;
- montants des mouvements, transferts, recalculs et financements traités par `Money` ;
- verrou du compte avant tout contrôle de débit ;
- verrou des deux comptes de transfert dans l'ordre croissant des identifiants ;
- rejet d'un mouvement dont la devise diffère de celle du compte ;
- rejet des transferts entre devises différentes ;
- règle budgétaire 50/50 calculée en unités mineures entières ;
- validation HTTP limitée à deux décimales et codes de devise alphabétiques sur trois lettres.

Gates obtenus :

- SQLite : **120 tests, 304 assertions** ;
- MySQL 8.3 / InnoDB : **120 tests, 304 assertions** ;
- audit des contraintes critiques MySQL : conforme avant et après rollback/réapplication ;
- Pint : conforme sur tous les fichiers touchés.

Les conversions `float` restantes dans les contrôleurs concernent les projections d'affichage et les graphiques, pas les écritures financières. Leur suppression sera traitée avec la centralisation des formatters, sans modifier le contrat frontend dans cet incrément.

### Incrément 2B.2 — Atomicité et idempotence

1. Déplacer validation de cotisation, allocation et activité dans une transaction unique.
2. Verrouiller la cotisation, la règle et les comptes avant écriture.
3. Ajouter une clé d'idempotence unique aux opérations financières.
4. Ajouter des références de groupe uniques pour les deux jambes d'un transfert.
5. Utiliser des contraintes SQL comme dernière ligne de défense.

Gate : un retry identique renvoie le même résultat ; un payload différent avec la même clé est rejeté ; un échec intermédiaire annule toute l'opération.

### État de l'incrément 2B.2

L'incrément est implémenté localement et n'est pas encore déployé :

- validation de cotisation, activité et allocation regroupées dans une transaction unique ;
- rollback complet si une allocation, une devise ou un compte échoue ;
- registre `financial_operations` avec clé idempotente, empreinte canonique du payload, acteur, statut et résultat ;
- même clé et payload identique : résultat existant renvoyé sans nouvelle écriture ;
- même clé et payload différent : conflit explicite et aucune mutation ;
- transferts liés par une référence UUID et une opération financière commune ;
- financement de projet idempotent par projet ;
- FK restrictive entre mouvements et opérations ;
- contraintes uniques SQL pour allocations, financements et clé dédiée de chaque jambe de transfert ;
- migration additive précédée d'un audit bloquant des doublons existants ;
- clés de requête UUID générées côté Vue pour transferts et financements.

Gates obtenus :

- tests ciblés : **46 tests, 110 assertions** ;
- SQLite complet : **124 tests, 327 assertions** ;
- MySQL 8.3 / InnoDB complet : **124 tests, 327 assertions** ;
- migration fresh, rollback et réapplication : conformes sur SQLite et MySQL ;
- audit des contraintes critiques : conforme ;
- Pint : conforme.

Les tests de ce lot valident les retries séquentiels et les contraintes SQL. Les scénarios à deux connexions réellement simultanées restent prévus dans l'incrément 2B.5 afin de mesurer aussi les attentes de verrous et deadlocks.

Le préflight de production en lecture seule du 13 août 2026 est conforme avant déploiement : zéro incohérence de devise, zéro écart de solde, zéro doublon d'allocation, zéro doublon de financement projet et zéro doublon de jambe de transfert. Aucune migration ni donnée de production n'a été modifiée.

### Incrément 2B.3 — Verrous et devises

1. Verrouiller les comptes dans l'ordre croissant des identifiants pour éviter les deadlocks.
2. Recalculer et contrôler le solde sous verrou dans la même transaction.
3. Refuser tout mouvement dont la devise diffère de celle du compte.
4. Refuser les transferts entre devises différentes tant qu'aucun change explicite n'existe.
5. Séparer tous les agrégats et rapports par devise.

Gate : aucun sur-débit dans les tests MySQL concurrents et aucun agrégat multidevise.

### Incrément 2B.4 — Immutabilité et contre-écritures

1. Interdire update/delete d'une écriture validée au niveau domaine.
2. Remplacer l'annulation par une contre-écriture liée à l'originale.
3. Conserver l'original et la raison de correction sans altération.
4. Renforcer les FK financières en `RESTRICT` après audit des données.

Gate : une écriture comptabilisée n'est ni modifiable ni supprimable ; chaque correction est explicable par la chaîne original/contre-écriture.

### Incrément 2B.5 — Réconciliation et concurrence MySQL

1. Ajouter `hsi:reconcile-funds` en lecture seule par défaut, avec sortie exploitable par monitoring.
2. Comparer soldes projetés, mouvements validés et cohérence des paires de transfert.
3. Ajouter des tests MySQL avec deux connexions pour allocations, transferts et débits concurrents.
4. Prévoir une alerte sans correction automatique des soldes.

Gate final : zéro doublon, zéro sur-débit, zéro écart de réconciliation sur MySQL.

## Règles de livraison

- migrations additives et compatibles avec le code précédent ;
- aucun `migrate:fresh` ni seeding en production ;
- audit agrégé avant chaque contrainte unique ou FK ;
- sauvegarde serveur fraîche et rollback vérifié avant activation ;
- aucune nouvelle archive chiffrée ;
- chaque incrément passe SQLite puis le gate MySQL avant staging.
