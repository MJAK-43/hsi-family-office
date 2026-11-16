# HSI Family Office — Roadmap d'implémentation

**Date :** 10 août 2026  
**Point de départ réel :** phases 0 à 4A livrées, Votes v1 livré (phase 5), financement projet v1 livré (phase 6).  
**Unité d'estimation :** semaines-personne, incluant backend, frontend, migrations, tests et documentation. Marge indicative : ±30 % après cadrage détaillé.

## 1. Règle de séquencement

Aucun nouveau module financier ne doit démarrer avant la fermeture des CRITICAL et des HIGH qui touchent le ledger, les identités et le déploiement. Le travail avance en lots livrables ; chaque lot doit laisser la production migrable et les 94 tests existants verts.

## 2. Vue d'ensemble

| Ordre | Phase | Objet | Complexité | Estimation | Dépend de |
|---:|---|---|---|---:|---|
| 0 | Lot 2A | Gate sécurité et déploiement | XL | 2–3 | Audit validé |
| 1 | Lot 2B | Intégrité financière v1 | XXL | 4–6 | 2A |
| 2 | Phase 4B | Finalisation projets et transitions | L | 2–3 | 2B |
| 3 | Phase 5B | Votes v2 et conflits d'intérêts | XL | 3–5 | 2A, Members |
| 4 | Phase 5C | Notifications internes + email | L | 2–3 | 2A, événements 4B/5B |
| 5 | Phase 6B | Ledger v2 et financement projet consolidé | XXL | 5–8 | 2B, 4B |
| 6 | Phase 7 | Investissements HSI | XXL | 5–7 | 5B, 5C, 6B, Documents minimal |
| 7 | Phase 8 | Prêts familiaux | XXL | 6–8 | 5C, 6B, Documents minimal |
| 8 | Phase 9 | Solidarité | XL | 4–5 | 5B, 5C, 6B, Governance minimal |
| 9 | Phase 10 | Tontines digitales | XXL | 6–8 | 5C, 6B |
| 10 | Phase 11 | Documents & Constitution | XL | 4–6 | 5B pour approbation Constitution |
| 11 | Phase 12 | Gouvernance avancée | XL | 4–6 | 5B, 11 |
| 12 | Phase 13 | HSI Academy | L | 3–5 | Members, 5C, 11 |
| 13 | Phase 14 | Reporting global, UX et optimisation | XL | 4–6 | Tous les domaines concernés |

Les phases 5C et 4B peuvent partiellement se chevaucher une fois le gate 2A fermé. Les travaux Ledger v2 restent séquentiels avec toute nouvelle écriture financière.

## 3. Lot 2A — Gate sécurité et déploiement

**Objectif :** retirer les risques pouvant exposer ou effacer la production avant toute autre évolution.

**État au 10 août 2026 :** implémentation locale terminée et validée. Le gate production reste conditionné à la sauvegarde/restauration, à la validation MySQL, au déploiement et à la rotation contrôlée de `APP_KEY`. Voir [HSI-LOT-2A-SECURITY-HARDENING.md](HSI-LOT-2A-SECURITY-HARDENING.md).

### Contenu

1. Retirer `APP_KEY` du script, inventorier son usage et préparer une rotation sûre.
2. Neutraliser `migrate:fresh --force` et `db:seed --force` sur production.
3. Séparer seeders référentiels et seeders démo ; bloquer les démos en production.
4. Fermer l'inscription publique ; définir invitation/création administrative.
5. Ajouter middleware de statut membre et révocation des sessions à la suspension.
6. Remplacer suppression compte/membre par désactivation/anonymisation ; corriger les FK destructrices.
7. Protéger le dashboard et filtrer tous ses widgets par permission.
8. Corriger l'auto-escalade de rôle et protéger le dernier super-admin.
9. Retirer l'archive de release dangereuse et mettre en place une construction à liste blanche.
10. Mettre à jour Node/Composer d'outillage et corriger les deux dépendances npm HIGH dans une PR contrôlée.

### Tests obligatoires

- inscription impossible sans invitation ;
- compte suspendu refusé à la connexion et sur session existante ;
- suppression/anonymisation conserve cotisations, mouvements, projets et bulletins ;
- secrétaire incapable de modifier son rôle ou d'attribuer un rôle financier ;
- dashboard refuse les comptes sans permission et masque chaque bloc ;
- test du script de déploiement sans commande destructive ni seeder démo.

### Gate de sortie

Zéro CRITICAL de sécurité/déploiement ouvert. Audit de secrets terminé et procédure de rotation documentée.

## 4. Lot 2B — Intégrité financière v1

**Objectif :** sécuriser `FundMovement` avant d'introduire le ledger v2.

### Contenu

1. Créer `Money`/`Currency` sans `float` pour tous les chemins financiers.
2. Transaction atomique cotisation validée + allocation + audit.
3. Clés d'idempotence et références uniques pour allocation, transfert et financement.
4. Verrouillage des comptes dans la transaction et ordre déterministe.
5. Contraintes de devise compte/mouvement/source ; rapports séparés par devise.
6. FK restrictives et interdiction de supprimer/modifier une écriture validée.
7. Contre-écriture d'annulation ou première étape compatible vers ce modèle.
8. Unicité/version de la règle d'allocation par défaut.
9. Commande de réconciliation soldes/mouvements avec rapport et alerte.
10. Suite MySQL d'intégration pour concurrence et verrous.

### Tests obligatoires

- deux validations/allocation simultanées ;
- deux débits/transferts simultanés sur le même solde ;
- retry avec même clé et payload identique/différent ;
- échec au milieu de l'allocation = rollback complet ;
- incompatibilité de devise ;
- écarts d'arrondi ;
- réconciliation détecte une projection altérée ;
- écriture comptabilisée non supprimable/non modifiable.

### Gate de sortie

Aucun sur-débit ni doublon possible dans les scénarios testés sur MySQL. Tous les soldes locaux se réconcilient.

## 5. Phase 4B — Finalisation projets

Le projet possède déjà le workflow comité → vote → financement. Cette phase complète l'exécution sans réécrire Phase 4A/6.

### Contenu

- transitions `funded -> in_progress -> completed/failed/cancelled` ;
- dates de décision persistées et timeline exacte ;
- amendement auditée au lieu d'édition libre après soumission ;
- jalons, suivi d'utilisation et rapports simples ;
- procédure de correction/remboursement d'un financement via ledger ;
- conflits d'intérêts des évaluateurs préparés pour Phase 5B ;
- tableau « À traiter » comité/président/trésorier.

### Gate de sortie

Un projet financé peut être suivi jusqu'à clôture et toute modification post-vote laisse une trace explicable.

## 6. Phase 5B — Votes v2 & conflits d'intérêts

### Contenu

- statut `scheduled` et jobs ouverture/clôture avec `withoutOverlapping` ;
- options personnalisées ;
- snapshot `VoteEligibility` ;
- quorum et majorité figés ;
- conflits d'intérêts génériques et exclusion ;
- verrou vote/bulletin/clôture ;
- politique de visibilité/anonymat ;
- sujets : projets, budget, investissement, adhésion, gouvernance, Constitution, autre ;
- décision liée au domaine cible par événement/Action ;
- reprise des votes v1 sans perte de données.

### Tests obligatoires

- éligible/non éligible, membre créé après ouverture, membre suspendu ;
- conflit déclaré/validé/rejeté ;
- bulletin concurrent avec clôture ;
- double vote concurrent ;
- simple/absolue/qualifiée, abstention, égalité, absence de quorum ;
- ouverture/clôture automatique idempotente ;
- audit sans fuite non autorisée du choix.

## 7. Phase 5C — Notifications

### Contenu

- migration notifications Laravel et préférences ;
- centre unread/read, catégorie, ressource, lien, tout marquer comme lu ;
- cloche réelle HSI ;
- email via jobs ;
- événements contribution, vote et projet ;
- relances vote avant clôture ;
- acteur/système et idempotence des listeners ;
- adaptateurs futurs WhatsApp/SMS/push définis, non implémentés.

### Gate de sortie

Aucun compteur factice ; une notification n'est créée qu'après commit et une relance n'est pas dupliquée.

## 8. Phase 6B — Ledger v2 & financement consolidé

### Contenu

- `ledger_accounts`, `ledger_transactions`, `ledger_entries`, réservations ;
- backfill `FundMovement` et rapport de réconciliation ;
- double écriture atomique temporaire ;
- soldes comptable/disponible/réservé ;
- contre-écritures ;
- financement projet via réservation puis capture ;
- rapprochement et journal quotidien ;
- bascule progressive des lectures ;
- conservation de `FundMovement` comme projection compatible.

### Gate de sortie

Tous les mouvements historiques sont rapprochés, toute transaction est équilibrée par devise, et les nouvelles lectures correspondent aux anciennes projections pendant la période de double écriture.

## 9. Phase 7 — HSI Invest

### Contenu

- catégories et actifs ;
- achats, cessions, revenus et frais saisis manuellement ;
- valorisations historisées ;
- documents et due diligence ;
- lien obligatoire à une poche/compte ledger ;
- portefeuille, allocation, coût, valeur et performance par devise ;
- vote optionnel avant engagement selon seuil ;
- aucun broker, prix temps réel ou FX fictif.

### Gate de sortie

Une position est reconstruisible depuis ses transactions et valorisations ; toute sortie de fonds est ledgerisée et autorisée.

## 10. Phase 8 — Prêts familiaux

### Contenu

- workflow complet demandé ;
- échéancier généré avec fréquence, durée et taux y compris 0 % ;
- documents/contrat non réglementaire ;
- approbation et financement depuis compte autorisé ;
- paiements idempotents, ventilation et solde restant ;
- retards/défauts et notifications ;
- remboursements comme écritures ledger ;
- vue emprunteur, trésorier et comité.

### Gate de sortie

Financement et remboursements se rapprochent au centime, un retry ne double pas le paiement et l'échéancier reste historisé.

## 11. Phase 9 — Solidarité

### Contenu

- demandes, bénéficiaires, urgence, justificatifs ;
- comité et décision, approbation partielle ;
- conflits d'intérêts ;
- réservation/décaissement depuis la poche Solidarité ;
- notification du demandeur ;
- confidentialité renforcée des pièces médicales/personnelles.

### Gate de sortie

La décision et le paiement sont séparés, autorisés, auditables et accessibles uniquement aux personnes habilitées.

## 12. Phase 10 — Tontines digitales

### Contenu

- types `rotating`, `voted`, `savings_pool` ;
- membres, cycles, ordre et calendrier figés ;
- contributions distinctes des cotisations HSI ;
- retard, bénéficiaire, payout et historique ;
- sous-comptes ledger dédiés ;
- notifications de tour et échéance.

### Gate de sortie

Chaque cycle se réconcilie, le bénéficiaire est déterminé par la règle persistée et aucune contribution HSI n'est mélangée.

## 13. Phase 11 — Documents & Constitution

### Contenu

- catégories globales ;
- document/version/access ;
- stockage privé, hash, scan, quarantaine ;
- workflow validation/publication/archivage ;
- Constitution versionnée et approbation liée à Vote v2 ;
- journal des téléchargements sensibles ;
- politique de rétention et restauration.

### Gate de sortie

Une version publiée n'est jamais écrasée ; accès et téléchargement sont testés contre les IDOR.

## 14. Phase 12 — Gouvernance

### Contenu

- comités, membres, postes et mandats ;
- réunions, ordres du jour, procès-verbaux ;
- décisions liées aux votes/documents ;
- historique sans chevauchement invalide de mandat ;
- comités Investissement, Solidarité, Éducation, Sages, Exécutif et Audit.

## 15. Phase 13 — HSI Academy

### Contenu

- profils Academy, mentorat, bourses, stages, formations et inscriptions ;
- permissions et confidentialité adaptées aux mineurs si le besoin est confirmé ;
- documents et notifications réutilisés ;
- financement des bourses seulement après cadrage ledger/Éducation.

Le périmètre reste volontairement sobre pour ne pas retarder les fondations financières.

## 16. Phase 14 — Reporting, UX et optimisation

### Contenu

- rapports membres, cotisations, fonds, mouvements, projets, votes, investissements, prêts, solidarité, tontines ;
- CSV en premier, PDF ensuite ;
- dashboard par rôle et bloc « À traiter » ;
- recherche globale autorisée ;
- design system documenté, composants et modales accessibles ;
- tableaux mobiles, skeletons, empty states ;
- lazy loading, budget de bundle et mesure des requêtes ;
- observabilité, sauvegardes/restauration et runbooks.

Certaines améliorations UX doivent être livrées au fil des phases ; Phase 14 consolide et mesure l'ensemble.

## 17. Graphe de dépendances

```text
Lot 2A Sécurité
  |-- Lot 2B Intégrité financière
  |     |-- Phase 4B Projets
  |     |     \-- Phase 6B Ledger v2
  |     |             |-- Investissements
  |     |             |-- Prêts
  |     |             |-- Solidarité
  |     |             \-- Tontines
  |     \-- Phase 6B Ledger v2
  |
  |-- Phase 5B Votes + Conflits
  |     |-- Projets finalisés
  |     |-- Investissements
  |     |-- Solidarité
  |     |-- Documents/Constitution
  |     \-- Gouvernance
  |
  \-- Phase 5C Notifications
        |-- tous les workflows ultérieurs
        \-- Dashboard « À traiter »

Documents minimal ----> Investissements / Prêts / Solidarité
Gouvernance minimal --> Solidarité
Tous les domaines ----> Reporting global
```

## 18. Discipline de livraison par lot

Après chaque lot, fournir obligatoirement :

- fichiers modifiés ;
- migrations ajoutées et stratégie de rollback ;
- permissions ajoutées/modifiées ;
- tests ajoutés ;
- résultat `php artisan test` ;
- résultat de la suite MySQL concernée ;
- résultat `npm run build` ;
- audits de dépendances ;
- plan de déploiement et réconciliation ;
- problèmes rencontrés ;
- dette restante et risques acceptés.

## 19. Ordre immédiat recommandé après validation

1. Sauvegarder l'environnement et restaurer une vraie gestion Git avant mutation.
2. Ouvrir Lot 2A avec un changement dédié aux scripts/secrets, puis comptes/suspension, puis RBAC.
3. Exécuter un audit de données avant de modifier les FK destructrices.
4. Livrer Lot 2B par petites migrations compatibles et tests MySQL.
5. Revalider l'architecture ledger sur un jeu de mouvements réconcilié.
6. Seulement ensuite commencer Phase 4B/5B/5C.

La prochaine action n'est donc pas « créer Investissements » : c'est **fermer le gate sécurité et intégrité** tout en conservant les fonctionnalités déjà validées.
