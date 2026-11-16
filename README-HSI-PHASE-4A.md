# HSI Family Office — Phase 4A : Module Projets familiaux

## Objectif

Dépôt, analyse et suivi des projets proposés par les membres, avec règle de financement 50/50, documents privés, évaluation par le comité investissement et préparation du statut « soumis au vote » (sans module Votes complet).

## Tables créées

| Table | Description |
|-------|-------------|
| `projects` | Projets familiaux (budget, apports, statuts, décisions comité) |
| `project_documents` | Documents joints (stockage privé) |
| `project_evaluations` | Notations comité (6 critères × 20 pts, max 120) |

## Modèles

- `App\Models\Project`
- `App\Models\ProjectDocument`
- `App\Models\ProjectEvaluation`

## Enums

- `ProjectStatus` — 13 statuts (draft → cancelled)
- `ProjectSector` — 10 secteurs
- `ProjectDocumentType` — 8 types de documents

## Service métier

`App\Services\ProjectService` :
- `submit()` — soumission pour analyse
- `evaluate()` — notation comité
- `approveByCommittee()` / `rejectByCommittee()`
- `submitToVote()` — passage au statut `submitted_to_vote`

## Règles métier 50/50

- Apport personnel ≥ 50 % du budget total
- Montant demandé à HSI ≤ 50 % du budget total
- Apport + demandé ≤ budget total
- Modification propriétaire : uniquement en `draft` ou `rejected_by_committee`
- Vote familial : uniquement depuis `approved_by_committee`, par président ou super_admin

## Permissions

| Permission | Rôles principaux |
|------------|------------------|
| view projects | member |
| view all projects | president, treasurer, secretary, investment_committee |
| create / edit own / submit | member |
| review / approve / reject | investment_committee, president |
| submit projects to vote | president, super_admin |
| manage project documents | investment_committee, super_admin |
| delete projects | super_admin |

## Routes

```
GET    /projects
GET    /projects/create
POST   /projects
GET    /projects/{project}
GET    /projects/{project}/edit
PUT    /projects/{project}
PATCH  /projects/{project}/submit
POST   /projects/{project}/evaluate
PATCH  /projects/{project}/approve-by-committee
PATCH  /projects/{project}/reject-by-committee
PATCH  /projects/{project}/submit-to-vote
DELETE /projects/{project}
POST   /projects/{project}/documents
DELETE /projects/documents/{projectDocument}
GET    /projects/documents/{projectDocument}/download
```

## Workflow projet

```
draft → submitted → under_review → approved_by_committee → submitted_to_vote
                              ↘ rejected_by_committee → (correction) → submitted
```

## Activity log (canal `projects`)

- `project.created`, `project.updated`, `project.submitted`
- `project.evaluated`, `project.approved_by_committee`, `project.rejected_by_committee`
- `project.submitted_to_vote`, `project.deleted`
- `project.document.uploaded`, `project.document.deleted`

## Comptes de test

Mot de passe : `password`

| Email | Rôle |
|-------|------|
| admin@hsi-family-office.test | super_admin |
| president@hsi-family-office.test | president |
| comite@hsi-family-office.test | investment_committee |
| marc.d@hsi-family-office.test | member (porteur projets) |
| isabelle.m@hsi-family-office.test | treasurer (lecture) |
| claire.d@hsi-family-office.test | secretary (lecture) |

## Commandes

```bash
php artisan migrate:fresh --seed
php artisan test
npm run build
```

## Limites connues (Phase 4A)

- ~~Pas de module Votes familial (Phase 5)~~ → **livré en Phase 5**
- Pas de financement automatique depuis les poches du fonds
- Pas de Solidarité, Academy ou Documents globaux
- Visibilité « projets publics » limitée aux statuts post-comité approuvés

## Prochaine phase recommandée

**Phase 5 — Votes familiaux** : voir `README-HSI-PHASE-5.md` (livré).
