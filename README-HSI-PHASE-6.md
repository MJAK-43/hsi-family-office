# HSI Family Office — Phase 6 : Financement projets

## Objectif

Passer un projet `approved_by_family` à `funded` en débitant la poche fonds **Projets familiaux** (`family-projects`).

## Workflow

```
approved_by_family
        │  PATCH /projects/{project}/fund  (trésorier / président / super_admin)
        ▼
funded  +  mouvement project_funding (débit)
```

## Changements techniques

| Élément | Détail |
|---------|--------|
| Migration | `project_id` sur `fund_movements` ; `funded_at`, `funded_by`, `funded_amount` sur `projects` |
| Enum | `FundMovementType::ProjectFunding` |
| Service | `ProjectService::fund` + `FundService::createProjectFundingMovement` |
| Permission | `fund projects` |

## Règles

- Financement **manuel** uniquement (pas d’auto-financement à la clôture du vote)
- Montant = `requested_amount` du projet
- Poche source : slug `family-projects`
- Idempotence : refus si déjà financé ou mouvement `project_funding` validé existant
- Solde insuffisant → erreur, projet inchangé
- Les mouvements `project_funding` ne sont pas annulables depuis le module fonds

## Permissions

| Permission | Rôles |
|------------|-------|
| fund projects | treasurer, president, super_admin |

## Routes

```
PATCH  /projects/{project}/fund   → projects.fund
```

## Activity log

- Canal `projects` : `project.funded`
- Canal `funds` : `fund.movement.created` (type `project_funding`)

## UI

- Bouton **Financer** sur `Projects/Show` si `can.fund`
- Affichage du montant financé + date / auteur
- Dashboard : compteur « à financer » + solde poche projets

## Comptes de test

Mot de passe : `password`

| Email | Usage |
|-------|-------|
| isabelle.m@hsi-family-office.test | trésorier — financer |
| president@hsi-family-office.test | président — financer |
| marc.d@hsi-family-office.test | membre — pas d’accès financement |

## Limites (Phase 6)

- Pas de partial funding / multi-tranches
- Pas d’annulation / remboursement de financement
- Pas de passage automatique vers `in_progress` / `completed`

## Prochaine phase recommandée

**Phase 7 — Solidarité** ou suivi d’exécution projet (`in_progress` → `completed`).
