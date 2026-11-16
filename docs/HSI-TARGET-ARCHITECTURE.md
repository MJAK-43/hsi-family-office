# HSI Family Office — Architecture cible

**Date :** 10 août 2026  
**Horizon :** 10 à 20 ans  
**Style retenu :** monolithe modulaire Laravel, évolution progressive de l'existant.

## 1. Décision d'architecture

HSI doit rester une application Laravel 12 + Vue 3 + Inertia. Le niveau de trafic et la nature transactionnelle des workflows ne justifient pas des microservices. Un monolithe modulaire donne au contraire des transactions SQL atomiques, des déploiements simples et une traçabilité centralisée.

Il ne faut pas déplacer immédiatement tous les modèles/controllers dans `app/Domains`. La cible est atteinte par extraction progressive autour des points de changement : Actions métier, Policies, Query Services, événements, puis sous-domaines. Les routes, pages et contrats déjà validés restent compatibles pendant la transition.

## 2. Principes directeurs

1. **Intégrité avant fonctionnalité** : aucun nouveau domaine financier sur un ledger non sécurisé.
2. **Monnaie explicite** : montant décimal/value object et devise toujours associés.
3. **Append-only financier** : aucune écriture comptabilisée modifiée ou supprimée.
4. **Idempotence** : toute commande critique porte une clé unique.
5. **Transactions locales** : une opération métier et ses écritures sont atomiques.
6. **Autorisation côté serveur** : UI filtrée, mais Policies et invariants restent la source de vérité.
7. **Workflow explicite** : transitions autorisées par état, acteur et condition.
8. **Audit utile** : acteur, action, ressource, avant/après, date et corrélation, sans secret inutile.
9. **Événements après commit** : notifications et reporting ne cassent pas la transaction principale.
10. **Lecture optimisée, vérité unique** : read models reconstructibles depuis les données canoniques.

## 3. Topologie logique cible

```text
Interface Vue / Inertia
        |
Controllers + Form Requests + Policies
        |
Application Actions / Commands / Queries
        |
Domaines métier et invariants
        |
Eloquent + Ledger + stockage privé
        |
MySQL / Queue / Mail / Audit
```

Les dépendances doivent pointer vers des contrats stables ou des Actions publiques, jamais directement vers les tables internes d'un autre domaine lorsque l'opération modifie plusieurs agrégats.

## 4. Organisation progressive du code

### 4.1 Étape de transition

Conserver les modèles dans `app/Models` et introduire seulement les répertoires utiles :

```text
app/
  Domains/
    Shared/
      Money/
      Idempotency/
      Workflow/
      Audit/
    Members/
      Actions/
      Policies/
      Queries/
    Contributions/
      Actions/
      Policies/
      Events/
    Funds/
      Actions/
      Policies/
      Queries/
    Ledger/
      Actions/
      Contracts/
      Exceptions/
      Queries/
    Projects/
      Actions/
      Policies/
      Events/
    Voting/
      Actions/
      Policies/
      Queries/
    Notifications/
    Documents/
```

Les contrôleurs deviennent minces : valider, autoriser, appeler une Action, retourner une réponse. Les formatages Inertia migrent vers des DTO/Resources ou Query Services.

### 4.2 Étape mature

À mesure que chaque domaine est réellement modifié, ses modèles peuvent migrer sous son namespace. Ce déplacement n'est justifié que s'il réduit les couplages et s'accompagne de tests de compatibilité. Aucun « grand déménagement » transversal n'est recommandé.

## 5. Carte des domaines

| Domaine | Responsabilité | Dépendances autorisées |
|---|---|---|
| Members | identité familiale, profil, statut, branches, rôles | Audit, Notifications |
| Contributions | plans, échéances, paiements, preuves, validation | Members, Ledger, Documents, Notifications |
| Funds | poches, règles d'allocation, disponibilité | Ledger, Audit |
| Ledger | transactions, écritures, réservations, soldes | Members comme acteur, Audit |
| Projects | dépôt, comité, exécution, financement | Members, Documents, Voting, Ledger, Notifications |
| Voting | consultations, options, éligibilité, bulletins, résultats | Members, Conflicts, Notifications, Audit |
| Conflicts | déclarations et exclusions réutilisables | Members, références polymorphes contrôlées |
| Investments | actifs, transactions, valorisations, portefeuille | Ledger, Documents, Voting, Notifications |
| Loans | demande, contrat, échéancier, paiements, défaut | Members, Ledger, Documents, Notifications |
| Solidarity | demande, décision, paiement | Members, Governance, Ledger, Documents, Notifications |
| Tontines | groupes, cycles, contributions, bénéficiaires | Members, Ledger logique séparé, Notifications |
| Documents | catégories, versions, accès, publication | Members, Audit |
| Governance | comités, mandats, réunions, procès-verbaux | Members, Documents, Voting |
| Academy | mentorat, bourses, stages, formations | Members, Documents, Notifications |
| Notifications | inbox, email et adaptateurs futurs | Événements de tous les domaines |
| Reporting | projections, exports et rapprochements | Lecture seule sur domaines/read models |
| Audit | événements sensibles et corrélation | Transverse |

## 6. Architecture financière cible

### 6.1 Modèle de ledger recommandé

Introduire un ledger double entrée indépendant du futur produit bancaire. Il s'agit d'une comptabilité interne logique, sans compte bancaire réel ni exécution de paiement.

Tables proposées :

#### `ledger_accounts`

- `id`, `uuid`, `code` unique, `name` ;
- `account_type` (asset, liability, income, expense, equity ou taxonomie interne validée) ;
- `currency` ISO 4217 ;
- `fund_account_id` nullable pour compatibilité ;
- `is_active`, dates, sans suppression destructive.

#### `ledger_transactions`

- `id`, `uuid`, `reference` unique ;
- `idempotency_key` unique ;
- `operation_type` (contribution, allocation, transfer, project_funding, etc.) ;
- `source_type` + `source_id` ou références explicites par domaine ;
- `status` (`pending`, `posted`, `reversed`, `failed`) ;
- `occurred_at`, `posted_at`, `created_by` ;
- `reversal_of_id` nullable ;
- métadonnées minimales et non sensibles ;
- aucun hard delete.

#### `ledger_entries`

- `id`, `ledger_transaction_id`, `ledger_account_id` ;
- `side` (`debit`/`credit`) ;
- `amount` décimal de précision décidée ;
- `currency` ;
- `sequence` ;
- description courte ;
- immuable après publication.

#### `fund_reservations`

- réservation liée à un projet, prêt, solidarité ou investissement ;
- montant/devise, compte source ;
- statut (`active`, `captured`, `released`, `expired`) ;
- clé d'idempotence et dates ;
- permet `available_balance = posted_balance - active_reservations`.

#### `account_balance_snapshots` (optionnel)

- cache/projection reconstruisible ;
- jamais seule source de vérité ;
- date de calcul et hash/version de séquence pour réconciliation.

### 6.2 Invariants

- somme des débits = somme des crédits pour chaque transaction et devise ;
- montant strictement positif ;
- une transaction publiée ne change plus ;
- une contre-écriture référence obligatoirement l'originale ;
- aucune transaction ne mélange les devises sans opération FX explicitement conçue ;
- compte et écriture ont la même devise ;
- clé d'idempotence obligatoire pour toute commande externe ou retryable ;
- verrous pris dans un ordre stable pour éviter les deadlocks ;
- écriture et transition métier commitent ensemble ;
- toute divergence de projection déclenche une alerte de réconciliation.

### 6.3 Stratégie de migration depuis `FundMovement`

1. **Sécuriser l'existant** : transactions, verrous, contraintes uniques, devise, pas de delete.
2. **Introduire le ledger v2** sans changer les écrans.
3. **Backfiller** les mouvements historiques dans une migration/commande contrôlée et produire un rapport de réconciliation.
4. **Double écriture atomique temporaire** dans la même transaction SQL, derrière une feature flag et avec comparaison quotidienne.
5. **Basculer les lectures** de soldes vers le ledger/projection.
6. **Conserver `FundMovement` comme projection de compatibilité** jusqu'à migration de tous les consommateurs.
7. **Retirer la double écriture** seulement après une période de réconciliation sans écart.

Cette approche évite un big-bang et permet de conserver les pages Fonds et les workflows validés.

### 6.4 Money et arrondis

Un value object `Money` doit encapsuler : montant exact, devise, échelle et mode d'arrondi. Deux stratégies sont possibles :

- unités mineures en entier pour les devises à 2 décimales ;
- `brick/math`/`BigDecimal` pour généraliser proprement.

Le code ne doit plus caster vers `float`. Les allocations répartissent l'écart d'arrondi selon une règle déterministe et testée. Les agrégats sont produits par devise, jamais fusionnés artificiellement.

## 7. Votes v2 et conflits d'intérêts

### 7.1 Modèle cible

Conserver `Vote`, `VoteOption`, `VoteBallot` et ajouter :

- `VoteEligibility` : snapshot d'un utilisateur, poids par défaut 1, raison d'inclusion/exclusion, statut ;
- `ConflictOfInterest` : déclarant, membre concerné, ressource typée, nature, décision, décideur, dates ;
- éventuellement `VoteDecisionLink` pour relier proprement le résultat à une commande du domaine cible.

`Vote` doit inclure `scheduled` et une règle de secret/visibilité du bulletin. Les options personnalisées doivent être persistées à la création. La pondération reste désactivée par défaut, mais le schéma peut la préparer.

### 7.2 Workflow

```text
draft -> scheduled -> open -> closed
   \          \          \-> cancelled
    \----------\------------> cancelled
```

À l'ouverture :

1. construire le snapshot d'éligibilité ;
2. appliquer les conflits décidés ;
3. figer quorum et règle de majorité ;
4. notifier les éligibles après commit.

À la clôture : verrouiller le vote, compter seulement les bulletins éligibles, persister un résultat détaillé et publier un événement. Le domaine cible décide ensuite de sa propre transition dans une transaction orchestrée.

## 8. Architecture par nouveau domaine

### 8.1 Investissements

- `InvestmentCategory`, `Investment`, `InvestmentTransaction`, `InvestmentValuation`, `InvestmentDocument` ;
- une transaction d'achat/cession/frais produit une transaction ledger ;
- valorisation manuelle historisée, jamais écrasée ;
- coût, valeur et performance calculés par devise ;
- aucune connexion broker dans cette étape.

### 8.2 Prêts familiaux

- `FamilyLoan`, `LoanInstallment`, `LoanPayment`, `LoanDocument` ;
- génération d'échéancier déterministe et versionné ;
- financement : réservation puis transaction ledger ;
- remboursement : transaction inverse avec ventilation capital/intérêt validée ;
- idempotence de paiement et transitions strictes ;
- job quotidien pour échéances/retards, sans signature réglementaire simulée.

### 8.3 Solidarité

- `SolidarityRequest`, documents, décisions et paiements ;
- comité/mandat issu de Governance ;
- réservation sur la poche Solidarité avant paiement ;
- décision et décaissement séparés, auditables.

### 8.4 Tontines

- ledger/sous-comptes distincts des cotisations HSI ;
- cycle et ordre bénéficiaire figés avant démarrage ;
- contribution par participant et cycle avec idempotence ;
- payout seulement lorsque les règles du cycle sont satisfaites ;
- calendrier et relances par notifications.

### 8.5 Documents et Constitution

- `DocumentCategory`, `Document`, `DocumentVersion`, `DocumentAccess` ;
- fichier immuable par version avec hash, taille, MIME détecté et scan ;
- publication/archivage sans écraser l'historique ;
- Constitution comme document versionné avec approbation liée à un vote ;
- autorisation par catégorie, rôle, comité ou liste explicite.

### 8.6 Gouvernance et Academy

Gouvernance doit précéder les décisions de comité avancées : `Committee`, `CommitteeMember`, `Mandate`, `GovernancePosition`, `Meeting`, `MeetingMinutes`. Academy reste découplé du ledger sauf pour les bourses, qui utiliseront ensuite la poche Éducation.

## 9. Notifications

Utiliser Laravel Notifications avec base de données et mail immédiatement. Les événements métier sont publiés après commit et consommés par des listeners idempotents.

Schéma minimal du centre :

- notifications Laravel `database` ;
- catégorie, priorité, titre, corps court ;
- URL interne validée côté serveur ;
- ressource typée ;
- `read_at`, date d'expiration éventuelle ;
- préférences utilisateur ;
- action « tout marquer comme lu » autorisée pour le propriétaire uniquement.

WhatsApp, SMS et push doivent être des adaptateurs futurs, sans logique métier dans le canal.

## 10. Reporting et lecture

Les rapports ne doivent pas recalculer toutes les opérations dans les contrôleurs. Créer des Query Services et, lorsque nécessaire, des tables de projection mises à jour après commit.

Règles :

- filtre de permissions dès la requête ;
- ventilation systématique par devise ;
- export CSV en streaming ;
- horodatage, filtres et auteur sur chaque export ;
- exports sensibles journalisés ;
- PDF seulement après stabilisation des données et gabarits.

## 11. Autorisation et cycle de vie des identités

- inscription sur invitation ou création administrative ;
- états `pending`, `active`, `suspended`, `inactive`, éventuellement `deceased`/`archived` après cadrage ;
- middleware global de statut ;
- suspension = révocation des sessions et jetons ;
- aucune suppression destructrice si des références existent ;
- rôles assignés uniquement par une permission dédiée avec matrice de délégation ;
- interdiction de modifier son propre rôle et protection du dernier super-admin ;
- Policies par ressource et tests de matrice.

## 12. Audit, observabilité et exploitation

### Audit métier

Chaque action sensible doit contenir : identifiant de corrélation, acteur réel ou système, ressource, commande, ancien état, nouvel état, date et référence métier. Les choix de bulletin ne doivent être exposés que selon la politique de confidentialité du vote.

### Observabilité

- logs structurés sans secrets ;
- alertes sur échec de job, divergence ledger, vote non clôturé et upload non scanné ;
- métriques de queue et délais ;
- health checks DB/queue/storage/mail ;
- sauvegardes chiffrées et tests réguliers de restauration.

### Déploiement

- build CI reproductible ;
- aucun secret dans le dépôt ;
- migrations non destructives et sauvegarde ;
- seeders référentiels séparés des données démo ;
- artefact construit par liste blanche ;
- `php artisan test`, analyse statique, audits de dépendances et `npm run build` comme gates ;
- smoke tests et rollback documenté.

## 13. Frontend et design system

Conserver la direction visuelle actuelle. Reconstituer le fichier de design system depuis les maquettes et le code : tokens, typographie, spacing, cartes, tableaux, badges, états, modales et responsive.

Principes frontend :

- pages et navigation filtrées par permissions ;
- dashboard composé de widgets autorisés ;
- vrai panneau « À traiter » ;
- `HsiEmptyState` plutôt que lignes factices ;
- modales accessibles pour décisions sensibles ;
- boutons désactivés et libellés de progression ;
- tableaux mobiles en cartes pour les actions principales ;
- composants créés seulement après deux usages concrets ;
- fontes auto-hébergées, CSP et budget de bundle.

## 14. Stratégie de tests cible

### Pyramide

- Unit : Money, arrondis, majorités, échéanciers, transitions.
- Feature : autorisation, IDOR, workflows, fichiers, audit.
- Integration MySQL : transactions, verrous, index, deadlocks, idempotence.
- Frontend : composants critiques, formulaires, modales, accessibilité.
- E2E ciblé : contribution → allocation, projet → vote → financement, prêt → remboursement.

### Matrice obligatoire par opération financière

- happy path ;
- permission refusée ;
- compte suspendu ;
- ressource tierce/IDOR ;
- transition invalide ;
- double requête séquentielle et concurrente ;
- solde insuffisant ;
- devise incompatible ;
- rollback sur échec intermédiaire ;
- contre-écriture ;
- audit et réconciliation.

## 15. Critères d'acceptation de l'architecture

L'architecture cible est atteinte lorsque :

1. chaque solde affiché est reconstructible et réconcilié ;
2. aucune suppression d'utilisateur n'efface une décision ou écriture ;
3. toute commande critique est idempotente et testée en concurrence ;
4. dashboard, navigation et données sont permission-aware ;
5. Votes possède une éligibilité figée et des conflits applicables ;
6. notifications et reporting consomment des événements après commit ;
7. les modules sont ajoutés par tranches verticales sans casser les routes existantes ;
8. les déploiements sont reproductibles, non destructifs et sans secret embarqué.
