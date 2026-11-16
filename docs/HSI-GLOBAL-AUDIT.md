# HSI Family Office — Audit global

**Date :** 10 août 2026  
**Périmètre :** dépôt local complet, code Laravel/Vue, migrations, configuration, tests, documentation, scripts de déploiement et huit maquettes.  
**Nature du lot :** audit uniquement ; aucun nouveau module métier n'a été implémenté.

## Synthèse exécutive

HSI Family Office est aujourd'hui un monolithe Laravel 12 / Vue 3 / Inertia cohérent et déjà fonctionnel sur six jalons : socle UI, membres, cotisations, fonds, projets, votes, puis financement de projets. L'application possède 87 routes applicatives, 24 migrations, 17 modèles, 42 pages Vue et une suite de 94 tests réussis (213 assertions). Les documents `README-HSI-PHASE-5.md` et `README-HSI-PHASE-6.md` montrent que l'état réel est plus avancé que l'état « Phase 4A » décrit dans le brief.

Le produit est néanmoins encore un **prototype métier robuste**, pas encore un système financier ou de gouvernance apte à porter des données familiales réelles. Les principaux risques sont concentrés dans quatre zones : déploiement, cycle de vie des utilisateurs, autorisation transverse et intégrité du fonds.

Décision recommandée : **geler l'ajout de nouveaux domaines** jusqu'à la correction du Lot 2. Les modules Votes et financement projet doivent être consolidés, pas recréés. La future architecture doit rester un monolithe modulaire et faire évoluer `FundMovement` progressivement vers un ledger interne append-only.

## Méthode et vérifications réalisées

| Vérification | Résultat |
|---|---|
| `php artisan test` | **PASS** — 94 tests, 213 assertions, 84,36 s |
| `npm run build` | **PASS** — 2 547 modules, 11,39 s |
| Build frontend | Avertissement Node 22.4.1 inférieur au minimum Vite pour Node 22 (22.12+) |
| Taille frontend | Deux chunks > 500 kB ; maximum observé ~628 kB |
| `npm audit --omit=dev` | **2 vulnérabilités HIGH** : `nanoid <=3.3.16`, `postcss <=8.5.22` |
| `composer audit --locked` | Non disponible : Composer 2.2.6 ne fournit pas la commande `audit` |
| Laravel installé | 12.62.0 ; au-dessus des versions corrigées 12.60.0 et 12.61.1 des avis [GHSA-5vg9-5847-vvmq](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq) et [GHSA-crmm-hgp2-wgrp](https://github.com/laravel/framework/security/advisories/GHSA-crmm-hgp2-wgrp) |
| Migrations | 24/24 exécutées sur la base SQLite locale |
| Base locale | 31 tables, données de démonstration présentes |
| Git | Aucun répertoire `.git` détecté ; historique et diff de référence indisponibles |
| Design system texte | Fichier présent mais vide (0 octet) |
| Maquettes | 8 PNG inspectés |

Le dossier `public/build` généré par le build a été retiré après vérification, car le volume C: était arrivé à 0 octet libre. Il s'agit d'un artefact régénérable ; aucun code source ni donnée applicative n'a été supprimé.

## 1. Architecture actuelle

### 1.1 Stack

- Laravel 12.62.0, PHP 8.2.18 ;
- Vue 3.4+, Inertia 2, Tailwind CSS 3, Vite 7 ;
- Spatie Permission 6.25 et Activitylog 4.12 ;
- ApexCharts / vue3-apexcharts ;
- SQLite en local et en tests, MySQL prévu en production ;
- stockage privé local pour preuves et documents projet ;
- queue, cache et sessions configurés sur base de données hors tests.

### 1.2 Organisation

Le code suit une organisation Laravel classique :

- modèles dans `app/Models` ;
- enums dans `app/Enums` ;
- trois services métier (`FundService`, `ProjectService`, `VoteService`) ;
- règles d'accès dans des classes statiques `app/Support/*Access.php` ;
- autorisation principalement dans les Form Requests et contrôleurs ;
- transformation des données Inertia dans les contrôleurs ;
- pages Vue par ressource et 14 composants HSI partagés ;
- activité métier enregistrée avec Spatie Activitylog.

Cette structure est adaptée à la taille actuelle, mais les contrôleurs commencent à cumuler requêtes, statistiques, formatage et orchestration. Les frontières de domaine ne sont pas explicites et le fonds est déjà une dépendance critique de Cotisations et Projets.

## 2. Modules existants

| Domaine | État réel | Appréciation |
|---|---|---|
| Authentification | Breeze complet, inscription publique encore active | À durcir pour une plateforme privée |
| Membres | CRUD, statuts, rôles, permissions, activité | Fonctionnel ; cycle de vie dangereux |
| Cotisations | Plans, soumission, preuves, validation/refus | Fonctionnel ; atomicité fonds à corriger |
| Fonds | Poches, allocations, mouvements, transferts, annulations, recalcul | Fonctionnel ; pas encore un ledger fiable |
| Projets | Dépôt, règle 50/50, documents, évaluation, workflow comité | Fonctionnel |
| Votes | Oui/Non/Abstention, quorum, majorité, clôture | Partiel : essentiellement vote projet |
| Financement projet | Débit de la poche projets et statut `funded` | Fonctionnel ; pas de reprise/contre-écriture |
| Documents globaux | Absent | Seuls documents projet et preuves existent |
| Notifications | Absent | Cloche et compteur sont factices |
| Investissements | Absent | — |
| Prêts | Absent | — |
| Solidarité | Absent | — |
| Tontines | Absent | — |
| Gouvernance avancée | Absent | Rôles uniquement |
| Academy | Absent | — |
| Reporting global | Absent | Statistiques locales uniquement |

## 3. Dépendances entre modules

1. `User` et `MemberProfile` portent l'identité, les rôles et l'éligibilité aux votes.
2. Une `ContributionPayment` validée déclenche `FundService::allocateContributionPayment()`.
3. Une règle d'allocation crédite plusieurs `FundAccount` via `FundMovement`.
4. Un `Project` approuvé par le comité crée un `Vote` lors de `submitToVote()`.
5. La clôture du vote modifie le statut du projet.
6. Un projet approuvé par la famille débite le compte `family-projects` via `FundMovement`.
7. Le dashboard agrège directement Membres, Cotisations, Fonds, Projets et Votes.

Le graphe est sain dans son intention, mais plusieurs opérations inter-domaines ne possèdent ni événement de domaine stable, ni clé d'idempotence, ni invariant de base de données.

## 4. Points forts

- Base Laravel récente et dépendances structurantes bien choisies.
- Enums PHP pour les principaux statuts et types.
- Form Requests généralisées et permissions Spatie présentes sur les actions sensibles.
- Stockage privé et téléchargement contrôlé pour les fichiers.
- Transactions SQL déjà utilisées dans plusieurs services.
- `lockForUpdate()` employé sur le financement de projet.
- Contrainte unique sur un bulletin par vote et utilisateur.
- Workflow projet lisible et règle 50/50 testée.
- Séparation visuelle HSI réussie : navy, or, cream, Playfair/Inter, composants réutilisables.
- Tests Feature nombreux sur happy paths, permissions, IDOR documentaires, solde insuffisant et doubles soumissions séquentielles.
- Documentation par phase utile et maquettes riches, cohérentes et institutionnelles.

## 5. Dette technique

La dette est principalement concentrée dans le couplage contrôleur/formatage/requêtes, l'absence de frontières de domaine, les transitions dispersées, l'usage de `float`, l'absence de qualité statique, le décalage SQLite/MySQL et le packaging manuel. Le registre ci-dessous constitue la liste actionnable : chaque entrée contient la criticité, les fichiers concernés, le risque et la recommandation attendue pour le Lot 2 ou une phase ultérieure.

### 5.1 CRITICAL

| ID | Fichier(s) concerné(s) | Risque | Recommandation |
|---|---|---|---|
| C-01 | `deploy/remote_deploy.py` | Une `APP_KEY` est inscrite en dur dans le code. Toute personne ayant accès au fichier peut tenter de déchiffrer ou forger des données protégées si cette clé a été utilisée en production. | Retirer la clé du code et de tout artefact, rechercher son exposition, vérifier les données chiffrées dépendantes, puis effectuer une rotation planifiée depuis un secret d'environnement. Invalider les sessions après rotation. |
| C-02 | `deploy/finish_deploy.py`, `deploy/remote_deploy.py`, `database/seeders/DatabaseSeeder.php`, seeders `Demo*` | `migrate:fresh --force` peut effacer toute la production ; `db:seed --force` crée des comptes de démonstration avec mot de passe documenté `password`. | Désactiver immédiatement ces chemins de production. Séparer seeders référentiels et démo, rendre les seeders démo impossibles en production, utiliser `migrate --force`, sauvegarde préalable et procédure de rollback. |
| C-03 | `ProfileController::destroy`, `MemberController::destroy`, migrations `users`, `contribution_payments`, `projects`, `vote_ballots` | Tout utilisateur peut supprimer son compte ; un administrateur peut supprimer un membre. Le `User` est hard-deleted et les FK `cascadeOnDelete()` effacent cotisations, projets, bulletins et autres traces métier. Cela viole l'auditabilité financière et de gouvernance. | Interdire le hard delete d'identités liées à des écritures. Introduire désactivation/anonymisation contrôlée, FK `restrict`/`nullOnDelete` adaptées, conservation des acteurs historiques et tests de non-régression. |
| C-04 | `MemberController`, `MemberStatus`, middleware applicatif | Suspendre un profil ne révoque ni sessions ni permissions. Un trésorier suspendu peut encore valider, transférer ou financer tant qu'il est connecté. | Ajouter un middleware global d'état membre, révoquer les sessions à la suspension, bloquer l'authentification des comptes suspendus/inactifs et tester toutes les actions financières. |
| C-05 | `ContributionController::validatePayment`, `FundService::allocateContributionPayment` | La cotisation est validée et commitée avant l'allocation. Si l'allocation échoue, elle reste validée sans mouvements ; la même route n'est plus autorisée pour la relancer. Le flash `warning` n'est en plus pas affiché. | Verrouiller la cotisation et effectuer validation + allocation + audit dans une transaction unique. Ajouter un état/retry explicite si une orchestration asynchrone est retenue et un contrôle de réconciliation. |
| C-06 | `FundService`, migration `fund_movements`, `FundAccount`, requêtes financières | Débits et transferts vérifient parfois le solde hors transaction et sans verrou de compte. L'anti-doublon d'allocation n'a pas de contrainte unique. Deux requêtes concurrentes peuvent sur-débiter ou doubler une allocation. | Mettre tous les contrôles et écritures dans une même transaction, verrouiller les comptes dans un ordre déterministe, ajouter clés d'idempotence/contraintes uniques et tests concurrents sur MySQL. |
| C-07 | `routes/auth.php`, `RegisteredUserController`, `DashboardController`, route `dashboard` | L'inscription publique est active sur une application familiale privée. Après vérification d'email, un compte sans rôle accède au dashboard, lequel retourne des totaux financiers, noms de cotisants, mouvements et projets sans vérifier `view dashboard` ni permissions par bloc. | Fermer l'inscription libre au profit d'invitations/approuvations, protéger le dashboard par permission et filtrer chaque widget et chaque requête. Ajouter tests d'accès sans rôle/guest/membre. |

### 5.2 HIGH

| ID | Fichier(s) concerné(s) | Risque | Recommandation |
|---|---|---|---|
| H-01 | `MemberRoleHelper`, `UpdateMemberRequest`, `MemberController::update`, `RolePermissionSeeder` | Un secrétaire possède `edit members`, peut éditer sa propre fiche et s'attribuer `treasurer`, car seuls trois rôles sont réservés. C'est une escalade horizontale vers des permissions financières. | Séparer `edit member profile` et `assign roles`, interdire l'auto-modification de rôle, définir une matrice explicite de délégation et auditer ancien/nouveau rôle. |
| H-02 | `FundService`, `ProjectBudgetRequest`, formatters/controllers financiers | Le code convertit les `decimal` en `float` pour allocation, soldes, règles 50/50 et reporting, contrairement à l'invariant financier demandé. | Utiliser des montants en unités mineures ou `brick/math`/value object Money, avec stratégie d'arrondi explicite et tests de bords. |
| H-03 | `FundService`, `FundController`, `DashboardController` | Aucune invariant ne garantit que mouvement, paiement, projet et compte ont la même devise. Les totaux additionnent EUR/USD/GBP sans conversion et les affichent souvent comme EUR. | Interdire toute opération inter-devise implicite ; agréger par devise ; associer chaque compte à une seule devise ; exiger une opération FX explicite future. |
| H-04 | migrations `fund_accounts`/`fund_movements`, modèle `FundMovement` | Un compte de fonds peut cascader ses mouvements ; les mouvements sont soft-deletable et largement mass-assignables. Une écriture financière peut disparaître des calculs. | Rendre les écritures append-only, retirer `SoftDeletes`, utiliser FK restrictives, interdire update/delete après comptabilisation et annuler par contre-écriture. |
| H-05 | `FundService::cancelMovement` | L'annulation modifie le statut de l'écriture initiale plutôt que produire une contre-écriture. L'historique existe dans Activitylog mais le grand livre n'est pas auto-explicatif. | Introduire `reversal_of_id` et une transaction inverse immuable ; conserver l'écriture originale comptabilisée. |
| H-06 | `FundService`, migration `fund_movements` | `reference` est nullable et non unique ; les transferts reposent sur une référence à la seconde. Aucune clé d'idempotence généralisée ni transaction financière parente n'existe. | Ajouter `ledger_transaction_id`, référence unique, `idempotency_key` unique et paire de lignes atomique. |
| H-07 | `VoteService`, tables `votes`/`vote_ballots`, `VoteAccess` | Seul le nombre d'éligibles est figé. Il n'existe pas de liste d'éligibilité : un membre créé après ouverture peut voter sans entrer dans le dénominateur ; un utilisateur sans profil est éligible ; la suppression d'un user efface son bulletin. | Créer un snapshot `vote_eligibilities`, conserver le bulletin malgré le départ du membre, gérer exclusions/conflits et versionner la règle d'éligibilité. |
| H-08 | `VoteService::castBallot`, `VoteService::close`, `openForProject` | Vote, clôture et création de vote actif ne verrouillent pas la consultation. Un bulletin peut courir avec la clôture ; deux votes actifs peuvent être créés en concurrence. | Verrouiller le vote dans les transactions, vérifier le statut après verrou, ajouter contrainte d'unicité logique, gérer proprement les conflits de contrainte. |
| H-09 | `VoteStatus`, `StoreVoteRequest`, `VoteService` | Le module annoncé comme générique ne gère ni statut programmé, ni options personnalisées, ni éligibilité, ni pondération future explicite, ni conflit d'intérêt. | Le traiter comme Votes v1 « projet Oui/Non ». Concevoir Votes v2 avant de le réutiliser pour gouvernance, budgets ou investissements. |
| H-10 | `ProjectAccess::canModify`, `UpdateProjectRequest` | `edit all projects` contourne les états du workflow. Un super-admin peut modifier budget et montant demandé après vote ou financement, créant une divergence avec le mouvement déjà comptabilisé. | Appliquer des champs et transitions autorisés par état, même aux administrateurs ; utiliser une procédure d'amendement auditée. |
| H-11 | `deploy/output/hsi-family-office-release.zip`, scripts de déploiement | L'archive de release est ancienne (elle ne reflète pas toutes les phases actuelles) et embarque `database/database.sqlite`, `storage/logs/laravel.log`, cache, vendor et fichiers de test. Risque de fuite et de déploiement incohérent. | Supprimer l'archive du code source, construire les releases en CI depuis une liste blanche, exclure DB/logs/tests/cache et produire un manifeste/version. |
| H-12 | `package-lock.json` | `npm audit` signale deux vulnérabilités HIGH dans `nanoid` ([GHSA-28wg-ghj8-5hjv](https://github.com/advisories/GHSA-28wg-ghj8-5hjv), [GHSA-2v37-7h3g-55p8](https://github.com/advisories/GHSA-2v37-7h3g-55p8)) et `postcss` ([GHSA-r28c-9q8g-f849](https://github.com/advisories/GHSA-r28c-9q8g-f849), [GHSA-fxqj-rqcc-2cmp](https://github.com/advisories/GHSA-fxqj-rqcc-2cmp)). | Mettre à jour via une PR dédiée, relancer tests/build/audit, vérifier les changements de lockfile ; ne pas appliquer aveuglément `npm audit fix` en production. |
| H-13 | uploads et contrôleurs de documents | Validation extension/MIME et taille seulement ; pas d'antivirus, de hash, de politique de conservation ni de contrôle de contenu. Les suppressions physiques diffèrent entre preuves et documents projet. | Ajouter hash SHA-256, scan antivirus asynchrone/quarantaine, noms serveur, politique de rétention, audit download/delete et tests de fichiers trompeurs. |
| H-14 | `FundAllocationRuleController`, table `fund_allocation_rules` | L'unicité de la règle par défaut n'est garantie qu'en code ; certaines bascules ne sont pas transactionnelles. Une concurrence peut laisser plusieurs règles par défaut. | Ajouter invariant DB compatible MySQL, transaction/verrou et historique/version d'application de la règle. |
| H-15 | configuration de tests (`phpunit.xml`) vs production MySQL | Les tests financiers utilisent SQLite mémoire ; ils ne valident pas les verrous, isolation et contraintes réellement utilisés par MySQL. | Ajouter une suite d'intégration MySQL pour concurrence, transactions, index uniques et migrations. Garder SQLite pour la boucle rapide. |

### 5.3 MEDIUM

| ID | Fichier(s) concerné(s) | Risque | Recommandation |
|---|---|---|---|
| M-01 | `routes/console.php`, `CloseExpiredVotesCommand` | La commande existe mais n'est pas planifiée ; les votes expirés restent ouverts sans cron manuel. | Planifier la commande avec `withoutOverlapping`, monitoring et acteur système dédié. |
| M-02 | `VoteService::closeExpired` | Le système attribue la clôture automatique au premier super-admin ou président trouvé, ce qui falsifie l'acteur réel. | Introduire un acteur système/audit technique distinct et journaliser exécution, résultat et erreurs. |
| M-03 | `HandleInertiaRequests`, `AuthenticatedLayout`, `ContributionController` | Seuls `success` et `error` sont partagés ; le `warning` d'échec d'allocation n'apparaît pas. | Standardiser les flashs `success/warning/error/info`, composants accessibles et corrélation avec l'opération. |
| M-04 | `HsiTopbar.vue` | Recherche sans action, rôle affiché « Administrateur » par défaut, compteur de notifications fixé à 3 et cloche inactive. | Connecter les données réelles ou masquer les fonctions factices jusqu'au module correspondant. |
| M-05 | `HsiSidebar.vue` | Les menus ne sont pas filtrés par permissions ; cinq entrées absentes pointent vers `#`. | Générer une navigation permission-aware et afficher « bientôt » sans lien trompeur si nécessaire. |
| M-06 | pages Vue | Les confirmations sont des `window.confirm`/`prompt`, sans état de chargement uniforme ni contexte détaillé pour actions sensibles. | Utiliser une modale HSI accessible avec impact, montant, ressource, confirmation et état `processing`. |
| M-07 | `ProjectController::buildTimeline` | Les dates d'approbation/rejet famille sont parfois remplacées par `now()`, donc changent à chaque affichage. | Persister les dates de décision et ne jamais synthétiser une date historique à la lecture. |
| M-08 | `Project::averageCommitteeScore`, `ProjectController::index` | La moyenne appelle une requête par projet malgré l'eager load : risque N+1. | Utiliser `withAvg`/agrégat SQL et mesurer les requêtes des pages principales. |
| M-09 | Activitylog dans contrôleurs/services | Couverture irrégulière, ancien/nouvel état rarement enregistrés, suppression membre non journalisée, téléchargements non audités. | Définir un catalogue d'événements sensibles et une politique de propriétés minimales sans données inutiles. |
| M-10 | `Design System HSI Family Office.txt` | Le référentiel demandé est vide ; les décisions visuelles vivent dans Tailwind, CSS, composants et maquettes sans document canonique. | Reconstituer un design system versionné depuis les maquettes et le code lors d'un lot UX dédié. |
| M-11 | `resources/css/app.css` | Polices chargées depuis Google à chaque visite : dépendance externe, confidentialité et rendu dégradé hors ligne. | Auto-héberger les fontes approuvées et définir une politique CSP. |
| M-12 | `composer` local | Composer 2.2.6 est ancien et ne permet pas `composer audit`; plusieurs notices de dépréciation apparaissent. | Mettre à niveau l'outil CI/de développement et rendre les audits Composer/NPM bloquants selon sévérité. |
| M-13 | `resources/js/Pages/Dashboard.vue`, build Vite | Chunks >500 kB et chargement ApexCharts coûteux. | Lazy-load des graphiques/pages, analyser le bundle et fixer un budget de performance. |
| M-14 | `.env.example`, environnement local | Nom/locale/fallback restent génériques en anglais ; `APP_DEBUG=true` et niveau debug sont adaptés au local mais dangereux si l'exemple est copié tel quel. | Aligner les exemples HSI et documenter explicitement les valeurs production sûres. |
| M-15 | architecture générale | Pas de PHPStan/Larastan, ESLint, formatage frontend ou couverture mesurée ; test unitaire encore factice. | Ajouter qualité statique et CI, sans remplacer les tests Feature existants. |

### 5.4 LOW

## 6. Risques sécurité

Les risques de sécurité dominants sont la clé applicative exposée, les scripts de déploiement destructifs, l'inscription publique, le dashboard non cloisonné, l'absence de blocage global des membres suspendus, l'auto-escalade de rôle et le cycle de vie destructif des utilisateurs. Les contrôles CSRF, le stockage privé et la majorité des vérifications IDOR locales constituent une bonne base, mais ne compensent pas ces failles transverses. Les constats C-01 à C-04, C-07, H-01, H-11 à H-13 doivent être traités avant ouverture à de vraies données familiales.

| ID | Fichier(s) concerné(s) | Risque | Recommandation |
|---|---|---|---|
| L-01 | `HsiDataTable.vue` | Clé de ligne basée sur l'index ; rendu instable lors de tris/mises à jour. | Accepter une `rowKey` stable, par défaut `id`. |
| L-02 | composants HSI/pages | Accessibilité partielle : boutons icône sans libellé partout, absence de caption tableau, focus/modales non uniformes. | Audit WCAG 2.2 AA et tests clavier/lecteur d'écran ciblés. |
| L-03 | dépôt local | Absence de `.git` empêche traçabilité, revue des changements et rollback fiable. | Restaurer le dépôt Git ou initialiser une gouvernance de version après sauvegarde et validation. |
| L-04 | README historiques | Certaines sections « limites » sont obsolètes depuis les phases suivantes. | Ajouter un index documentaire et marquer les statuts livrés/obsolètes sans réécrire l'historique. |

## 7. Risques financiers

### 7.1 État de `FundMovement`

`FundMovement` représente aujourd'hui à la fois une écriture, une opération métier et une projection de solde. C'est suffisant pour les phases 3 à 6, mais insuffisant pour Investissements, Prêts, Tontines et Solidarité.

Les faiblesses majeures sont :

- une seule ligne par mouvement au lieu d'une transaction équilibrée ;
- paire de transfert reliée seulement par une référence texte ;
- solde matérialisé modifiable sur `FundAccount.current_balance` ;
- absence de disponible/réservé ;
- annulation par mutation de statut ;
- aucune clé d'idempotence obligatoire ;
- absence de contrainte de devise ;
- usage de `float` ;
- FK destructrices et soft delete ;
- pas de rapprochement ni de contrôle quotidien automatique.

### 7.2 Invariants à imposer avant les nouveaux modules financiers

1. Chaque opération possède une référence et une clé d'idempotence uniques.
2. Les écritures comptabilisées sont immuables.
3. Toute annulation est une contre-écriture liée à l'originale.
4. Débits et crédits d'une transaction sont équilibrés par devise.
5. Aucun mélange de devise sans transaction FX explicite.
6. Le solde est calculable depuis les écritures ; le cache de solde est reconstructible.
7. Le disponible tient compte des réservations actives.
8. Les contrôles, verrous et écritures sont dans une transaction SQL unique.
9. Aucune suppression utilisateur/compte ne cascade vers le ledger.
10. Une réconciliation automatique détecte toute divergence.

## 8. Problèmes d'intégrité des données

Les suppressions en cascade peuvent effacer des faits historiques ; la validation de cotisation peut diverger de l'allocation ; les soldes et références n'ont pas toutes les contraintes nécessaires ; les agrégats mélangent potentiellement les devises ; l'éligibilité d'un vote n'est pas matérialisée ; certaines dates de timeline sont calculées avec `now()`. Les migrations correctives doivent être précédées d'un audit de données et suivies de rapports de réconciliation.

### Complément d'analyse sécurité applicative

### Autorisation et IDOR

Les routes métier sont groupées sous `auth` + `verified`, et les ressources importantes ont généralement une vérification d'accès. Les téléchargements privés sont correctement servis par contrôleur. Les lacunes sont transverses : dashboard, statut suspendu, inscription publique, rôle modifiable et menus non filtrés.

### Mass assignment

Les Form Requests réduisent le risque côté HTTP. Les modèles financiers exposent toutefois des champs sensibles dans `$fillable`; l'immuabilité doit être imposée au modèle/service et en base, pas dépendre de la discipline des contrôleurs.

### CSRF, XSS et injection SQL

Laravel/Inertia fournit CSRF et binding paramétré. Les recherches utilisent Eloquent et des paramètres. Aucun `v-html` alimenté par une donnée métier n'a été trouvé ; son usage est limité aux libellés de pagination Laravel. Le risque principal n'est pas l'injection SQL, mais la gouvernance des accès et des transitions.

### Sessions et secrets

Les cookies sont `HttpOnly` et `SameSite=lax`; l'exemple production impose HTTPS. `SESSION_ENCRYPT=false` n'est pas une vulnérabilité directe avec stockage serveur, mais la clé exposée dans le script de déploiement est critique. Il manque une procédure de rotation et de gestion des secrets.

## 9. Manques fonctionnels

- ledger interne double entrée, réservations et rapprochement ;
- vote générique avec éligibilité et conflits d'intérêts ;
- notifications internes/email et centre de notifications ;
- suivi d'exécution/achèvement/amendement des projets financés ;
- Investissements ;
- Prêts familiaux ;
- Solidarité ;
- Tontines ;
- bibliothèque documentaire et Constitution versionnée ;
- gouvernance, comités, mandats et réunions ;
- Academy ;
- reporting et exports CSV ;
- administration des rôles/permissions ;
- échéances automatiques de cotisation.

## 10. Manques UX

- dashboard non adapté par rôle et absence d'un vrai bloc « À traiter » ;
- topbar factice pour recherche, rôle et notifications ;
- écrans vides parfois remplacés par des lignes « — » plutôt que `HsiEmptyState` ;
- confirmations natives peu adaptées aux montants et actions irréversibles ;
- absence de skeletons et chargements partiels ;
- tableaux seulement scrollables sur mobile, sans vue compacte/card ;
- feedback `warning` absent ;
- navigation vers modules non livrés trompeuse ;
- accessibilité clavier et annonces ARIA incomplètes ;
- manque de breadcrumbs sur les workflows profonds ;
- aucun traitement visible des fuseaux horaires pour les votes internationaux.

## 11. Manques tests

La suite existante est un actif à conserver. Il manque prioritairement :

- concurrence débit/transfert/allocation/financement ;
- transaction validation cotisation + allocation et rollback ;
- devises incompatibles et agrégats multi-devises ;
- suppression compte/membre sans perte de traces ;
- utilisateur suspendu sur chaque action sensible ;
- auto-escalade et délégation de rôles ;
- accès dashboard sans permission ;
- vote concurrent avec clôture, snapshot d'éligibilité et conflit ;
- contraintes sur MySQL ;
- fichiers à MIME trompeur, quarantaine et suppression ;
- audit ancien/nouvel état ;
- tests frontend/composants et accessibilité ;
- tests de déploiement non destructif.

## 12. Opportunités de refactorisation

1. Extraire progressivement des **Actions** métier transactionnelles plutôt que déplacer tous les modèles.
2. Introduire des value objects `Money`/`Currency` et un service de ledger.
3. Centraliser les transitions dans des machines d'état explicites ou méthodes de domaine.
4. Remplacer les classes `*Access` par Policies au fil des modules, sans big-bang.
5. Extraire les read models/statistiques des contrôleurs vers des Query Services.
6. Standardiser les DTO/Resources Inertia au lieu de grands tableaux privés de contrôleur.
7. Publier des événements métier après commit pour notifications et reporting.
8. Créer une matrice de permissions versionnée et testée.

## 13. Fonctionnalités à conserver

- identité visuelle HSI et composants existants ;
- Laravel/Vue/Inertia monolithique ;
- enums et workflows validés ;
- stockage privé contrôlé ;
- activité métier Spatie ;
- règle projet 50/50 ;
- services `FundService`, `ProjectService`, `VoteService` comme points de migration ;
- permissions fines et tests Feature existants ;
- allocation 40/30/20/10 configurable ;
- financement projet manuel après décision familiale.

## 14. Fonctionnalités à améliorer

- `FundMovement` vers un ledger append-only ;
- Votes v1 vers un module générique avec snapshot d'éligibilité ;
- cycle de vie du membre vers désactivation/anonymisation ;
- dashboard vers une composition par permission ;
- audit vers ancien/nouvel état et acteur système ;
- documents vers versioning, hash, scan et rétention ;
- déploiement vers pipeline reproductible et non destructif ;
- UI vers feedback et accessibilité cohérents.

## 15. Fonctionnalités à ajouter

L'ordre recommandé n'est pas une création simultanée : notifications et conflits d'intérêts d'abord, puis ledger v2, exécution projet, Investissements, Prêts, Solidarité, Tontines, Documents/Constitution, Gouvernance, Academy et enfin reporting consolidé. Chaque ajout doit réutiliser Members, Audit, Documents, Notifications et Ledger plutôt que recréer ces capacités localement.

## Conclusion et gate de validation

L'application a une bonne base fonctionnelle et visuelle. Elle peut évoluer vers les domaines demandés sans microservices ni réécriture complète. En revanche, **Investissements, Prêts, Solidarité et Tontines ne doivent pas commencer avant la fermeture des CRITICAL et des HIGH financiers**.

Le prochain lot recommandé est exclusivement un lot de stabilisation : secrets/déploiement, cycle de vie des comptes, middleware de suspension, RBAC dashboard/rôles, atomicité cotisation-fonds, concurrence/idempotence/devise du fonds, puis tests MySQL. L'architecture cible et le séquencement détaillé figurent dans les deux documents associés.
