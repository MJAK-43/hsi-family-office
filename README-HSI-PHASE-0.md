# HSI Family Office — Phase 0

Socle technique et visuel de l'application **HSI Family Office** (Héritage & Sagesse International).

## Stack

| Couche | Technologie |
|--------|-------------|
| Backend | Laravel 12 |
| Frontend | Vue 3 + Inertia.js |
| Auth | Laravel Breeze (Vue/Inertia) |
| Styles | Tailwind CSS + charte HSI |
| Graphiques | ApexCharts (vue3-apexcharts) |
| Icônes | lucide-vue-next |

## Charte visuelle

Couleurs principales : `hsi-navy`, `hsi-gold`, `hsi-cream`  
Polices : **Playfair Display** (titres), **Inter** (corps)

Configuration Tailwind : `tailwind.config.js`  
Styles globaux : `resources/css/app.css`

## Composants HSI

Dossier : `resources/js/Components/Hsi/`

| Composant | Rôle |
|-----------|------|
| `HsiSidebar.vue` | Navigation latérale responsive |
| `HsiTopbar.vue` | Barre supérieure (recherche, notifications, profil) |
| `HsiPageHeader.vue` | En-tête de page avec titre et action |
| `HsiStatCard.vue` | Carte statistique avec variation |
| `HsiButton.vue` | Bouton (primary, secondary, ghost, danger, outline) |
| `HsiBadge.vue` | Badge de statut |
| `HsiDataTable.vue` | Tableau de données responsive |
| `HsiChartCard.vue` | Carte conteneur pour graphiques |
| `HsiActionCard.vue` | Carte d'action rapide |
| `HsiSearchInput.vue` | Champ de recherche |
| `HsiAvatar.vue` | Avatar utilisateur |
| `HsiEmptyState.vue` | État vide |
| `HsiCard.vue` | Carte générique |
| `HsiSectionTitle.vue` | Titre de section |

## Layout

- **Connecté** : `resources/js/Layouts/AuthenticatedLayout.vue` (sidebar + topbar HSI)
- **Invité** : `resources/js/Layouts/GuestLayout.vue` (pages auth Breeze)

## Pages

- **Dashboard** : `resources/js/Pages/Dashboard.vue` — vue statique conforme à la maquette

## Modules prévus (Phase 1+)

Membres · Cotisations · Fonds familial · Projets · Votes · Solidarité · Academy · Documents · Gouvernance · Administration

## Lancer le projet

```bash
# 1. Dépendances PHP
composer install

# 2. Environnement (si première installation)
cp .env.example .env
php artisan key:generate

# 3. Base de données
php artisan migrate

# 4. Dépendances frontend
npm install

# 5. Serveur de développement (2 terminaux)
npm run dev
php artisan serve --port=9000
```

Accès : [http://127.0.0.1:9000](http://127.0.0.1:9000)

> **Note Windows/WAMP** : si le port 8000 est bloqué, utilisez `--port=9000` ou accédez via WAMP :
> `http://localhost/inovixora/hsi-family-office/public`

Créer un compte via `/register`, puis accéder au dashboard via `/dashboard`.

## Commandes utiles

```bash
composer install          # Dépendances PHP
npm install               # Dépendances Node
npm run dev               # Vite en mode développement
npm run build             # Build production
php artisan serve --port=9000  # Serveur Laravel (port 9000 si 8000 bloqué)
php artisan migrate       # Migrations
php artisan route:list    # Liste des routes
```

## Maquettes de référence

Dossier `maquettes/` — images de direction artistique pour les futures phases.

## Problèmes connus

| Problème | Solution |
|----------|----------|
| `php artisan serve` bloqué sur port 8000 (Windows) | Utiliser `--port=9000` |
| Node.js 22.4.x — avertissement Vite | Mettre à jour vers Node 22.12+ ou 20.19+ |
| Vérification email requise pour `/dashboard` | Vérifier l'email après inscription, ou consulter les logs mail (`storage/logs`) en local |
| Assets Vite via WAMP | Lancer `npm run dev` en dev, ou `npm run build` pour la prod sans Vite |
| `APP_URL` incorrect | Doit correspondre à l'URL réelle (ex. sous-dossier WAMP) |

## Vérification Phase 0 (audit)

**Statut : Phase 0 validée** (après corrections du 2026-07-05)

### Checklist

- [x] Laravel 12 + Breeze Vue/Inertia installés
- [x] 14 composants HSI présents et fonctionnels
- [x] Tailwind configuré avec charte HSI complète
- [x] Layout responsive (sidebar drawer mobile + fixe desktop)
- [x] Dashboard statique avec stats, graphique, tableaux, actions
- [x] Routes auth protégées (`auth` + `verified` sur dashboard)
- [x] `npm run build` sans erreur
- [x] Compatible WAMP via `public/` + `APP_URL` configuré

## Prochaine phase recommandée — Phase 1

1. Schéma base de données (membres, rôles, permissions)
2. Authentification avancée et gestion des rôles
3. Module Membres (CRUD)
4. Seeders de démonstration
5. Connexion des composants UI aux données réelles

---

*Phase 0 — Socle UI uniquement, sans logique métier.*
