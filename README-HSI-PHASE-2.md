# HSI Family Office — Phase 2 : Module Cotisations

Documentation du module **Cotisations** (plans, paiements, preuves, validation trésorier).

## Tables créées

| Table | Description |
|-------|-------------|
| `contribution_plans` | Plans de cotisation (montant, fréquence, pays, actif/défaut) |
| `contribution_payments` | Paiements soumis par les membres avec statut workflow |
| `payment_proofs` | Fichiers preuve de paiement (stockage privé) |

## Modèles

- `App\Models\ContributionPlan`
- `App\Models\ContributionPayment`
- `App\Models\PaymentProof`

## Enums

- `ContributionFrequency` — monthly, quarterly, yearly, one_time
- `ContributionPaymentStatus` — pending, validated, rejected, late, cancelled
- `PaymentMethod` — bank_transfer, cash, paypal, stripe, mobile_money, etc.

## Permissions ajoutées

| Permission | super_admin | president | treasurer | secretary | member |
|------------|:-----------:|:---------:|:---------:|:---------:|:------:|
| view contributions | ✓ | ✓ | ✓ | ✓ | — |
| create contributions | ✓ | — | ✓ | — | — |
| edit contributions | ✓ | — | ✓ | — | — |
| delete contributions | ✓ | — | — | — | — |
| submit contribution payments | ✓ | — | ✓ | — | ✓ |
| validate contribution payments | ✓ | — | ✓ | — | — |
| reject contribution payments | ✓ | — | ✓ | — | — |
| view all contribution payments | ✓ | ✓ | ✓ | ✓ | — |
| view own contribution payments | ✓ | — | ✓ | — | ✓ |
| manage contribution plans | ✓ | — | ✓ | — | — |

## Règles métier

1. **Soumission** : un membre soumet un paiement + preuve optionnelle → statut `pending`.
2. **Validation/refus** : réservé au trésorier et super_admin.
3. **Visibilité** : les membres voient uniquement leurs propres paiements ; president/secretary/treasurer voient tout.
4. **Modification** : un membre peut modifier une cotisation `pending`, `rejected` ou `late` ; une cotisation `validated` est verrouillée.
5. **Resoumission** : une cotisation refusée repasse en `pending` à la modification.
6. **Preuves** : stockées dans `storage/app/private/contribution-proofs`, téléchargement via route contrôlée.
7. **Suppression** : réservée au super_admin (`delete contributions`).

## Routes

```
GET    /contributions                              contributions.index
GET    /contributions/create                       contributions.create
POST   /contributions                              contributions.store
GET    /contributions/{payment}                    contributions.show
GET    /contributions/{payment}/edit               contributions.edit
PUT    /contributions/{payment}                    contributions.update
PATCH  /contributions/{payment}/validate         contributions.validate
PATCH  /contributions/{payment}/reject           contributions.reject
DELETE /contributions/{payment}                    contributions.destroy
GET    /contributions/proofs/{proof}/download      contributions.proofs.download

GET    /contribution-plans                         contribution-plans.index
GET    /contribution-plans/create                  contribution-plans.create
POST   /contribution-plans                         contribution-plans.store
GET    /contribution-plans/{plan}/edit             contribution-plans.edit
PUT    /contribution-plans/{plan}                  contribution-plans.update
PATCH  /contribution-plans/{plan}/activate         contribution-plans.activate
PATCH  /contribution-plans/{plan}/deactivate       contribution-plans.deactivate
DELETE /contribution-plans/{plan}                  contribution-plans.destroy
```

## Activity log (événements)

- `contribution.payment.submitted`
- `contribution.payment.updated`
- `contribution.payment.validated`
- `contribution.payment.rejected`
- `contribution.payment.deleted`
- `contribution.plan.created`
- `contribution.plan.updated`
- `contribution.plan.deleted`

## Pages Vue/Inertia

- `Contributions/Index`, `Show`, `Create`, `Edit`, `ContributionForm`
- `ContributionPlans/Index`, `Create`, `Edit`, `ContributionPlanForm`

## Commandes

```bash
php artisan migrate:fresh --seed
npm run build
php artisan test
php artisan serve --port=9000
```

## Comptes de test

| Email | Rôle | Mot de passe | Usage |
|-------|------|--------------|-------|
| admin@hsi-family-office.test | super_admin | password | Accès total |
| isabelle.m@hsi-family-office.test | treasurer | password | Valider/refuser cotisations |
| marc.d@hsi-family-office.test | member | password | Soumettre ses cotisations |
| claire.d@hsi-family-office.test | secretary | password | Consulter toutes les cotisations |

## Données de démonstration

4 plans : Cotisation Europe (20 EUR), Afrique (10 EUR), Amérique du Nord (25 EUR), Contribution libre.

6 paiements demo : validated, pending, rejected, late répartis sur les membres existants.

## Limites connues

- Pas de génération automatique des échéances mensuelles (marquage `late` manuel/seeder).
- Pas de notification email à la validation/refus.
- President n'a pas `manage contribution plans` (uniquement trésorier/super_admin).
- Modules Fonds familial, Projets, Votes, Solidarité, Academy, Documents : non développés (Phase 3+).

## Prochaine phase recommandée

**Phase 3 — Fonds familial** : comptes, allocations, mouvements financiers liés aux cotisations validées, avec reporting et intégration dashboard.
