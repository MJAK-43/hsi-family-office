# HSI Family Office — Phase 3 : Module Fonds familial

Documentation du module **Fonds familial** (poches, mouvements, allocation automatique, transferts).

## Tables créées

| Table | Description |
|-------|-------------|
| `fund_accounts` | Poches du fonds (investissement, projets, solidarité, éducation, trésorerie) |
| `fund_allocation_rules` | Règles de répartition des cotisations validées |
| `fund_allocation_rule_items` | Lignes de pourcentage par poche |
| `fund_movements` | Mouvements financiers (crédits/débits, soft delete) |

## Modèles

- `App\Models\FundAccount`
- `App\Models\FundAllocationRule`
- `App\Models\FundAllocationRuleItem`
- `App\Models\FundMovement`

## Service métier

`App\Services\FundService` :
- `allocateContributionPayment()` — allocation automatique 40/30/20/10
- `createMovement()` — mouvement manuel
- `transfer()` — transfert entre poches (2 mouvements liés)
- `recalculateAccountBalance()` / `recalculateAllBalances()`
- `cancelMovement()` — annulation avec recalcul solde

## Workflow cotisation → fonds

1. Trésorier valide une cotisation (`ContributionController@validatePayment`)
2. `FundService::allocateContributionPayment()` est appelé
3. Vérification anti-doublon via `contribution_payment_id`
4. Application de la règle par défaut active
5. Création de mouvements `contribution_allocation` (crédits)
6. Recalcul des soldes des poches concernées

## Permissions

| Permission | super_admin | treasurer | president | secretary | member |
|------------|:-----------:|:---------:|:---------:|:---------:|:------:|
| view funds | ✓ | ✓ | ✓ | ✓ | ✓ |
| view fund movements | ✓ | ✓ | ✓ | ✓ | — |
| create fund movements | ✓ | ✓ | — | — | — |
| edit fund movements | ✓ | ✓ | — | — | — |
| delete fund movements | ✓ | — | — | — | — |
| transfer funds | ✓ | ✓ | ✓ | — | — |
| manage fund accounts | ✓ | ✓ | — | — | — |
| manage allocation rules | ✓ | ✓ | — | — | — |

## Règle d'allocation par défaut

| Poche | Pourcentage |
|-------|-------------|
| Investissement long terme | 40% |
| Projets familiaux | 30% |
| Solidarité | 20% |
| Éducation | 10% |
| Trésorerie | 0% (hors allocation auto) |

## Routes

```
GET    /funds                                    funds.index
GET    /funds/movements                          funds.movements
GET    /funds/movements/create                   funds.movements.create
POST   /funds/movements                          funds.movements.store
GET    /funds/transfer                           funds.transfer.form
POST   /funds/transfer                           funds.transfer
PATCH  /funds/movements/{movement}/cancel        funds.movements.cancel
POST   /funds/recalculate                        funds.recalculate
GET    /funds/{account}                          funds.show

GET    /fund-allocation-rules                    fund-allocation-rules.index
GET    /fund-allocation-rules/create             fund-allocation-rules.create
POST   /fund-allocation-rules                    fund-allocation-rules.store
GET    /fund-allocation-rules/{rule}/edit        fund-allocation-rules.edit
PUT    /fund-allocation-rules/{rule}             fund-allocation-rules.update
PATCH  /fund-allocation-rules/{rule}/activate    fund-allocation-rules.activate
PATCH  /fund-allocation-rules/{rule}/deactivate  fund-allocation-rules.deactivate
PATCH  /fund-allocation-rules/{rule}/set-default fund-allocation-rules.set-default
```

## Activity log

- `fund.movement.created`
- `fund.movement.cancelled`
- `fund.transfer.created`
- `fund.balance.recalculated`
- `fund.allocation.created`
- `fund.allocation.rule.created`
- `fund.allocation.rule.updated`
- `fund.allocation.rule.default_changed`

## Pages Vue/Inertia

- `Funds/Index`, `Show`, `Movements`, `CreateMovement`, `Transfer`
- `FundAllocationRules/Index`, `Create`, `Edit`, `Form`

## Commandes

```bash
php artisan migrate:fresh --seed
npm run build
php artisan test
```

## Comptes de test

| Email | Rôle | Usage fonds |
|-------|------|-------------|
| admin@hsi-family-office.test | super_admin | Accès total |
| isabelle.m@hsi-family-office.test | treasurer | Mouvements, transferts, règles |
| marc.d@hsi-family-office.test | member | Consultation fonds uniquement |
| claire.d@hsi-family-office.test | secretary | Consultation fonds + mouvements |

Mot de passe : `password`

## Limites connues

- Pas de multi-devises avancé (EUR par défaut)
- Trésorerie exclue de l'allocation automatique par défaut
- Pas de rapprochement bancaire
- Modules Projets, Votes, Solidarité, Academy, Documents : non développés

## Prochaine phase recommandée

**Phase 4 — Projets familiaux** : soumission, analyse, financement depuis les poches du fonds, workflow de validation.
