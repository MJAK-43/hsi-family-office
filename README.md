# HSI Family Office — Fonds familial JOKUNG

Application web de gestion du Fonds familial JOKUNG : membres et adhésions, cotisations trimestrielles et pénalités, projets (instruction par le comité, vote de la famille, décaissement, prêts et remboursements), comptes du fonds et diagrammes de flux, documents, rapports et exports.

Elle suit le règlement intérieur du fonds : taux de prêt de 4 % par an, plafond à 50 % du solde, vote à 80 %, pénalité de cotisation de 2 $ par semaine de retard…

| Couche | Technologie |
|---|---|
| Serveur | PHP 8.2+, Laravel 12 |
| Interface | Inertia 2, Vue 3, Tailwind CSS, Vite |
| Base de données | SQLite (développement) ou MySQL 8 / MariaDB 10.6+ (production) |
| Divers | spatie/laravel-permission (rôles), spatie/laravel-activitylog (journal), DomPDF et PhpSpreadsheet (exports), ECharts (graphiques) |

---

## Sommaire

1. [Prérequis](#1-prérequis)
2. [Installation pas à pas](#2-installation-pas-à-pas)
   - [Windows](#windows)
   - [macOS](#macos)
   - [Linux (Ubuntu / Debian)](#linux-ubuntu--debian)
3. [Configuration commune](#3-configuration-commune)
4. [Lancer l'application](#4-lancer-lapplication)
5. [Comptes de démonstration](#5-comptes-de-démonstration)
6. [Tests](#6-tests)
7. [Tâches planifiées et file d'attente](#7-tâches-planifiées-et-file-dattente)
8. [Variables d'environnement utiles](#8-variables-denvironnement-utiles)
9. [Mise en production](#9-mise-en-production)
10. [Problèmes fréquents](#10-problèmes-fréquents)

> **iPhone / iPad :** iOS ne peut pas faire tourner le serveur de l'application. Sur iPhone ou iPad, on l'utilise seulement comme client : ouvrir l'adresse de l'application (production, ou ordinateur du réseau local, voir [section 4](#accès-depuis-un-téléphone-ou-une-tablette)) dans Safari. L'interface est adaptée au mobile. Pour développer sur un Mac, suivre la partie [macOS](#macos).

---

## 1. Prérequis

| Outil | Version | Vérifier avec |
|---|---|---|
| PHP | 8.2 ou plus | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js | 20.19+ ou 22.12+ (LTS conseillée) | `node -v` |
| npm | fourni avec Node | `npm -v` |
| Git | toute version récente | `git --version` |
| MySQL / MariaDB | facultatif (SQLite suffit en local) | `mysql --version` |

**Extensions PHP nécessaires :** `pdo_sqlite` (ou `pdo_mysql`), `mbstring`, `openssl`, `curl`, `fileinfo`, `gd`, `intl`, `zip`, `bcmath`, `xml`, `dom`, `ctype`, `tokenizer`.

Pour vérifier les extensions chargées :

```bash
php -m
```

---

## 2. Installation pas à pas

### Windows

**Option A — Laravel Herd (le plus simple)**

1. Installer [Laravel Herd](https://herd.laravel.com/windows) : il fournit PHP, Composer et Node.
2. Installer [Git pour Windows](https://git-scm.com/download/win).
3. Ouvrir un terminal (PowerShell) et récupérer le projet :
   ```powershell
   cd $HOME\Herd
   git clone https://github.com/MJAK-43/hsi-family-office.git
   cd hsi-family-office
   ```
4. Continuer avec la [configuration commune](#3-configuration-commune). Herd sert ensuite le site sur `http://hsi-family-office.test`.

**Option B — WAMP / XAMPP (installation actuelle du projet)**

1. Installer [WampServer](https://www.wampserver.com/) (ou XAMPP) avec PHP 8.2+.
   - Activer les extensions `intl`, `gd`, `zip`, `fileinfo`, `pdo_sqlite` dans `php.ini` : enlever le `;` devant `extension=…`.
   - Ajouter le dossier PHP au `PATH` Windows, par exemple `C:\wamp64\bin\php\php8.2.18`.
2. Installer [Composer](https://getcomposer.org/Composer-Setup.exe) et [Node.js LTS](https://nodejs.org/).
3. Cloner le projet dans le dossier web :
   ```powershell
   cd C:\wamp64\www
   git clone https://github.com/MJAK-43/hsi-family-office.git
   cd hsi-family-office
   ```
4. Continuer avec la [configuration commune](#3-configuration-commune).

### macOS

1. Installer les outils en ligne de commande d'Apple :
   ```bash
   xcode-select --install
   ```
2. Installer PHP, Composer et Node, au choix :
   - **Laravel Herd** ([herd.laravel.com](https://herd.laravel.com)) : tout est inclus. C'est le plus simple.
   - **Homebrew** :
     ```bash
     /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
     brew install php@8.3 composer node@22 git
     brew link --force --overwrite php@8.3 node@22
     ```
     Les extensions demandées sont déjà incluses dans le PHP de Homebrew.
3. Récupérer le projet :
   ```bash
   cd ~/Sites   # ou ~/Herd avec Herd
   git clone https://github.com/MJAK-43/hsi-family-office.git
   cd hsi-family-office
   ```
4. Continuer avec la [configuration commune](#3-configuration-commune).

### Linux (Ubuntu / Debian)

```bash
sudo apt update
sudo apt install -y git unzip curl php8.3-cli php8.3-sqlite3 php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-gd php8.3-intl php8.3-zip php8.3-bcmath
# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
# Node.js 22 LTS
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs

git clone https://github.com/MJAK-43/hsi-family-office.git
cd hsi-family-office
```

Sur une version d'Ubuntu qui ne propose pas `php8.3`, ajouter le dépôt `ppa:ondrej/php` ou utiliser `php8.2-*`.

---

## 3. Configuration commune

Ces commandes sont identiques sur Windows (PowerShell), macOS et Linux, depuis le dossier du projet.

```bash
# 1. Dépendances PHP et JavaScript
composer install
npm install

# 2. Fichier d'environnement et clé de chiffrement
cp .env.example .env          # Windows PowerShell : Copy-Item .env.example .env
php artisan key:generate
```

### Base de données

**SQLite (recommandé en local, rien à installer)** : laisser `DB_CONNECTION=sqlite` dans `.env`, puis créer le fichier :

```bash
# macOS / Linux
touch database/database.sqlite
# Windows PowerShell
New-Item database\database.sqlite -ItemType File
```

**MySQL / MariaDB** : créer une base vide en `utf8mb4`, puis renseigner `.env` :

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hsi_family_office
DB_USERNAME=root
DB_PASSWORD=
```

### Tables et données de départ

```bash
php artisan migrate --seed
```

Cette commande crée les tables, puis les données de départ :
- rôles et permissions ;
- pays ;
- branches familiales ;
- plan de cotisation réglementaire ;
- comptes du fonds et règles de répartition ;
- catégories de documents ;
- en environnement `local`, des membres, projets et cotisations de démonstration.

Pour repartir de zéro :

```bash
php artisan migrate:fresh --seed
```

### Fichiers et interface

```bash
php artisan storage:link   # lien public/storage → storage/app/public
npm run build              # compile l'interface (Vue + Tailwind)
```

### Adresse de l'application

Dans `.env`, `APP_URL` doit correspondre à l'adresse utilisée dans le navigateur :

| Méthode | APP_URL |
|---|---|
| `php artisan serve` | `http://127.0.0.1:8000` |
| Herd | `http://hsi-family-office.test` |
| WAMP (sous-dossier) | `http://localhost/hsi-family-office/public` |

Pour une interface en français par défaut : `APP_LOCALE=fr`. Les utilisateurs peuvent ensuite changer de langue (FR / EN) dans l'application.

---

## 4. Lancer l'application

**Le plus simple :**

```bash
php artisan serve
```

Ouvrir ensuite <http://127.0.0.1:8000>.

**En développement**, avec rechargement automatique de l'interface, la file d'attente et les journaux en direct :

```bash
composer run dev
```

Cette commande lance en parallèle `php artisan serve`, la file d'attente, `php artisan pail` (journaux) et `npm run dev` (Vite).

Avec Herd ou WAMP, le serveur web est déjà fourni. Il suffit de lancer `npm run dev` pendant le développement, ou `npm run build` une fois pour toutes.

### Accès depuis un téléphone ou une tablette

Pour tester sur un iPhone, un iPad ou un Android du même réseau Wi-Fi :

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Ouvrir ensuite `http://<adresse-IP-de-l-ordinateur>:8000` sur l'appareil. Pour trouver cette adresse : `ipconfig` sous Windows, `ipconfig getifaddr en0` sur macOS.

Deux conditions :
- `APP_URL` doit utiliser cette même adresse IP ;
- l'interface doit être compilée (`npm run build`), sinon le serveur Vite devra aussi être exposé.

---

## 5. Comptes de démonstration

Ils sont créés par `php artisan migrate --seed` quand `APP_ENV=local`. Le mot de passe de tous ces comptes est **`password`**.

| Rôle | Email |
|---|---|
| Super administrateur | `admin@hsi-family-office.test` |
| Président | `president@hsi-family-office.test` |
| Comité d'investissement | `comite@hsi-family-office.test` |
| Membres | `claire.d@…`, `marc.d@…`, `sophie.n@…`, etc. (`@hsi-family-office.test`) |

> Ces comptes ne sont **jamais** créés en production. Ne pas lancer `DemoDataSeeder` sur un serveur réel.

---

## 6. Tests

```bash
php artisan test
```

Il y a environ 230 tests. Ils tournent sur une base SQLite en mémoire et ne touchent pas à la base de développement.

Pour lancer un seul fichier ou un seul test :

```bash
php artisan test --filter=RegulationWorkflowTest
```

Les tests sur MySQL utilisent `phpunit.mysql.xml`. Sous Windows, le script `scripts/test-mysql.ps1` les lance.

---

## 7. Tâches planifiées et file d'attente

L'application a besoin du planificateur Laravel :
- clôture des votes arrivés à échéance (chaque minute) ;
- échéances de cotisation et pénalités (chaque jour à 01:00) ;
- échéances de prêt et rappels (chaque jour à 01:15).

| Contexte | Ce qu'il faut lancer |
|---|---|
| Local | `php artisan schedule:work` dans un terminal séparé |
| Linux / macOS (serveur) | crontab : `* * * * * cd /chemin/du/projet && php artisan schedule:run >> /dev/null 2>&1` |
| Plesk | « Tâches planifiées » → type **Run a PHP script**, script `<domaine>/artisan`, argument `schedule:run`, chaque minute. Le type « Run a command » ne fonctionne pas sur ce serveur (shell restreint). |

Les emails et notifications passent par la file d'attente (`QUEUE_CONNECTION=database`) :
- en local, `composer run dev` la lance automatiquement, sinon lancer `php artisan queue:work` ;
- en local, `MAIL_MAILER=log` écrit les emails dans `storage/logs/laravel.log` au lieu de les envoyer.

---

## 8. Variables d'environnement utiles

`.env.example` (développement) et `.env.production.example` (production) servent de modèles.

| Variable | Rôle |
|---|---|
| `APP_LOCALE` | Langue par défaut (`fr` ou `en`) |
| `MAIL_MAILER`, `MAIL_FROM_ADDRESS` | Envoi des emails (`log` en local ; `brevo` en production avec `BREVO_API_KEY`) |
| `MAIL_ADMIN_ADDRESS` | Adresse prévenue des nouvelles demandes d'adhésion |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Connexion avec Google (facultatif) |
| `RECETTE_TOKEN` | Code secret de la page de recette `/recette/{code}` (vide = page désactivée) |

> Ne jamais committer le fichier `.env` : il contient la clé de l'application et les mots de passe. Il est exclu par `.gitignore`.

---

## 9. Mise en production

La production tourne sur un serveur Plesk (PHP 8.2, MySQL).

1. **Construire le paquet** :
   ```powershell
   .\deploy\plesk-deploy.ps1
   ```
   Il produit `deploy/output/hsi-family-office-release.zip`, avec `vendor/` et l'interface compilée, mais sans `.env`.
2. **Sur le serveur** :
   - sauvegarde de la base et des fichiers ;
   - `php artisan down` ;
   - décompression du paquet ;
   - `php artisan migrate --force` ;
   - `php artisan db:seed --class=RolePermissionSeeder --force` ;
   - `php artisan config:cache && php artisan route:cache && php artisan view:cache` ;
   - `php artisan up`.
3. **Scripts d'aide** : ceux de `deploy/` lisent l'adresse du serveur et le mot de passe dans les variables d'environnement `DEPLOY_HOST` et `DEPLOY_PASSWORD`. Aucun secret n'est écrit dans le dépôt.

Points d'attention :
- **`.env` du serveur** : il doit contenir `APP_ENV=production`, `APP_DEBUG=false`, une `APP_KEY` propre au serveur et les accès MySQL.
- **Droits d'écriture** : le serveur web doit pouvoir écrire dans `storage/` et `bootstrap/cache/`.
- **Fichiers PHP et `.env`** : les enregistrer en UTF-8 **sans BOM**. Un BOM casse PHP.

---

## 10. Problèmes fréquents

| Symptôme | Solution |
|---|---|
| Page blanche ou erreur « Vite manifest not found » | Lancer `npm run build`, ou `npm run dev` pendant le développement. |
| `could not find driver` | Activer `pdo_sqlite` ou `pdo_mysql` dans `php.ini`. |
| `No application encryption key` | Lancer `php artisan key:generate`. |
| Erreur sur `intl`, `gd` ou `zip` | Activer l'extension correspondante dans `php.ini`, puis redémarrer le serveur. |
| Les modifications de `.env` ne sont pas prises en compte | Lancer `php artisan config:clear`. |
| Les liens ou redirections pointent vers une mauvaise adresse | Vérifier `APP_URL`. |
| Documents ou pièces jointes introuvables | Lancer `php artisan storage:link` et vérifier les droits d'écriture de `storage/`. |
| Les votes ne se clôturent pas ou les pénalités ne se calculent pas | Le planificateur ne tourne pas : voir la [section 7](#7-tâches-planifiées-et-file-dattente). |
| `npm install` échoue | Vérifier la version de Node (`node -v`, 20.19+ ou 22.12+). |
