# HSI Family Office — Phase 5 : Votes familiaux

## Objectif

Consultation familiale sur les projets soumis au vote, avec quorum, majorité, bulletins et bascule automatique vers `approved_by_family` / `rejected_by_family`.

## Tables créées

| Table | Description |
|-------|-------------|
| `votes` | Consultations (statut, quorum, résultat, compteurs) |
| `vote_options` | Options Pour / Contre / Abstention |
| `vote_ballots` | Bulletins (1 par électeur et par vote) |

## Modèles & service

- `Vote`, `VoteOption`, `VoteBallot`
- `VoteService` : `openForProject`, `create`, `castBallot`, `close`, `cancel`, `closeExpired`
- `VoteAccess` : contrôle d’accès

## Workflow

```
approved_by_committee
        │  submitToVote (crée aussi un Vote open)
        ▼
submitted_to_vote  +  Vote ouvert
        │  bulletins des membres actifs
        ▼
close (manuel ou votes:close-expired)
        ├─ quorum OK + majorité Pour   → approved_by_family
        ├─ quorum OK + majorité Contre → rejected_by_family
        └─ no_quorum / tied            → projet reste submitted_to_vote
```

## Règles

- Électeurs : permission `cast votes` + profil actif (ou sans profil)
- Quorum défaut : **50 %** des électeurs éligibles
- Majorité défaut : **simple** (≥ 50 % des exprimés Pour/Contre)
- 1 seul bulletin par membre ; pas de double vote
- Abstention autorisée par défaut (non comptée dans la majorité simple)

## Permissions

| Permission | Rôles |
|------------|-------|
| view votes + cast votes | member, president, treasurer, secretary, investment_committee, … |
| view all votes | president, secretary, super_admin |
| create / manage / close votes | president, super_admin |

## Routes

```
GET    /votes
GET    /votes/create
POST   /votes
GET    /votes/{vote}
POST   /votes/{vote}/cast
PATCH  /votes/{vote}/close
PATCH  /votes/{vote}/cancel
```

## Activity log (canal `votes` + `projects`)

- `vote.created`, `vote.opened`, `vote.ballot_cast`
- `vote.closed`, `vote.finalized`, `vote.cancelled`
- `project.approved_by_family`, `project.rejected_by_family`

## Commande

```bash
php artisan votes:close-expired
```

## Comptes de test

Mot de passe : `password`

| Email | Usage |
|-------|-------|
| president@hsi-family-office.test | créer / clôturer |
| marc.d@hsi-family-office.test | voter |
| admin@hsi-family-office.test | super_admin |

## Limites (Phase 5)

- Pas de financement automatique depuis le fonds (`funded`)
- Pas de votes anonymes côté admin (détail visible avec `view all votes`)
- Pas de Solidarité / Academy / Documents globaux

## Prochaine phase recommandée

**Phase 6 — Financement projets** : passage `approved_by_family` → `funded` avec débit de la poche projets du fonds familial.
