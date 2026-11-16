# HSI Family Office — Phase 1

Module **Membres** : rôles, permissions, pays, branches familiales et profils membres.

## Stack ajoutée

| Package | Rôle |
|---------|------|
| `spatie/laravel-permission` | Rôles et permissions |
| `spatie/laravel-activitylog` | Journal d'audit |

## Rôles créés

`super_admin` · `president` · `vice_president` · `treasurer` · `secretary` · `investment_committee` · `solidarity_committee` · `academy_manager` · `member` · `guest`

## Permissions créées

- view dashboard
- view members
- create members
- edit members
- suspend members
- delete members
- export members
- access administration
- manage roles
- manage permissions

## Modèles & tables

| Table / Modèle | Description |
|----------------|-------------|
| `countries` / `Country` | Pays (code, nom, drapeau emoji) |
| `family_branches` / `FamilyBranch` | Branches familiales |
| `member_profiles` / `MemberProfile` | Profil membre lié à un User |

### Relations

- `User` hasOne `MemberProfile`
- `MemberProfile` belongsTo `User`, `Country`, `FamilyBranch`

### Enum `MemberStatus`

`pending` · `active` · `suspended` · `inactive`

## Compte super admin (seed)

| Champ | Valeur |
|-------|--------|
| Email | `admin@hsi-family-office.test` |
| Mot de passe | `password` |
| Rôle | `super_admin` |

8 membres de démonstration sont également créés (mot de passe : `password`).

## Routes Membres

| Méthode | URI | Action |
|---------|-----|--------|
| GET | `/members` | Liste + filtres |
| GET | `/members/create` | Formulaire création |
| POST | `/members` | Enregistrer |
| GET | `/members/{id}` | Fiche membre |
| GET | `/members/{id}/edit` | Formulaire édition |
| PUT | `/members/{id}` | Mettre à jour |
| PATCH | `/members/{id}/suspend` | Suspendre |
| PATCH | `/members/{id}/activate` | Activer |
| DELETE | `/members/{id}` | Supprimer |

## Pages Vue

- `resources/js/Pages/Members/Index.vue` — annuaire (inspiré maquette)
- `resources/js/Pages/Members/Show.vue` — fiche membre
- `resources/js/Pages/Members/Create.vue` — création
- `resources/js/Pages/Members/Edit.vue` — modification
- `resources/js/Components/Members/MemberForm.vue` — formulaire partagé

## Audit log

Événements enregistrés dans Spatie Activitylog (log `members`) :

- `member.created` — création membre
- `member.updated` — modification membre
- `member.suspended` — suspension membre
- `member.activated` — activation membre

## Lancer le projet

```bash
composer install
npm install
php artisan migrate:fresh --seed
npm run dev
php artisan serve --port=9000
```

**WAMP :** `http://localhost/inovixora/hsi-family-office/public`

Connexion : `admin@hsi-family-office.test` / `password` → Menu **Membres**

## Commandes utiles

```bash
php artisan migrate:fresh --seed   # Réinitialiser + données demo
php artisan test                   # Tests (33 tests)
npm run build                      # Build production
php artisan permission:cache-reset # Vider cache permissions
```

## Limites de la Phase 1

- Pas de module Cotisations, Projets, Votes ou export CSV
- Pas de gestion des rôles via interface admin
- Pas de photo de profil uploadée
- Suppression membre = suppression définitive user + soft delete profil
- Rôles `super_admin`, `president`, `vice_president` réservés aux utilisateurs avec permission `manage roles`

## Vérification Phase 1 (audit)

**Statut : Phase 1 validée**

- [x] 10 rôles + 10 permissions Spatie
- [x] Migrations countries, family_branches, member_profiles (FK, soft deletes, index)
- [x] CRUD Membres + suspend/activate
- [x] Activitylog sur create, update, suspend, activate
- [x] Restriction attribution rôles privilégiés
- [x] 33 tests PHPUnit passent
- [x] `npm run build` sans erreur

## Prochaine phase recommandée — Phase 2

1. Module Cotisations
2. Export CSV membres (`export members`)
3. Gestion des rôles via interface Administration
4. Upload photo de profil membre

---

*Phase 1 — Module Membres uniquement.*
