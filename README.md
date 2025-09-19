# CantalDestination Observatoire - Documentation Projet

## Apercu
CantalDestination Observatoire est la plateforme web et data de l'Observatoire touristique du Cantal. Elle expose un portail PHP securise pour les tableaux de bord, un ensemble d'APIs d'analyse, ainsi qu'une chaine ETL permettant d'ingester les fichiers Flux Vision Orange et de les consolider dans MySQL.

- Portail dashboards (tableaux de bord, infographies, comparaisons).
- Outils d'administration (espaces partages, periodes, gestion des comptes).
- APIs REST pour l'alimentation des frontaux et des automatisations.
- Scripts CLI et ETL pour import, migration, nettoyage et verification des donnees.

## Pile technologique
| Domaine                | Technologies principales                                                  |
| ---------------------- | ------------------------------------------------------------------------ |
| Application web        | PHP 8.1+, Apache 2.4, templates PHP, classes metier dans `classes/`      |
| Base de donnees        | MySQL 8.x, PDO, scripts SQL cibles (`sql/`, `tools/diagnostic`)          |
| API et middleware      | Endpoints PHP dans `api/`, protections `.htaccess`, tokens Bearer        |
| Front-end              | JavaScript ES6, D3.js (`static/js/lib`), modules sur mesure `static/js/` |
| ETL                    | Python 3.11+, Polars, mysql-connector-python, journaux `logs/`           |
| Collecte saisons       | Node.js 18+, Axios, Cheerio (`scrap_date.js`)                            |

## Environnements cibles
- `dev-local` : instance XAMPP/Apache sur `http://localhost/fluxvision_fin`, MySQL 8 local, debug actif.
- `production` : hebergement Cantal Destination, reverse proxy HTTPS, MySQL 8, tokens d'API obligatoires et audit centralise.

## Prerequis
- PHP 8.1 ou superieur avec les extensions `pdo_mysql`, `intl`, `mbstring`, `openssl`, `json`.
- MySQL 8.x (schema `fluxvision` pour le developpement, base `observatoire` en production).
- Node.js 18+ et npm pour le scraping des saisons astronomiques.
- Python 3.11+ avec `pip install polars mysql-connector-python python-dotenv` (idealement dans un environnement virtuel).
- Git et acces SSH/HTTPS au depot.
- Acces aux exports Flux Vision Orange (CSV) et aux identifiants SFTP/FTP associes.

## Mise en place (developpement local)
1. Cloner le depot puis se placer dans `fluxvision_fin`.
2. Copier le fichier `.env` et ajuster les tokens, limites et allowlists selon votre contexte.
3. Verifier la configuration `config/database.php` (port 3307 par defaut pour XAMPP) et adapter si besoin.
4. Configurer Apache : `DocumentRoot` pointe vers ce dossier et activer les directives `.htaccess`.
5. Creer la base MySQL `fluxvision` puis provisionner le schema minimal via `php tools/migration/migrate_temp_to_main.php` (mode test possible avec `--test`).
6. Installer les dependances Node : `npm install`, puis executer `npm run start` pour regenir `saisons_data.php` si necessaire.
7. Creer un environnement Python (`python -m venv .venv`, activation) et installer les dependances ETL : `pip install -r requirements_etl.txt` ou `pip install polars mysql-connector-python requests urllib3 python-dotenv`.
8. Generer les bundles front-end : `python tools/build_front_assets.py` (regroupe les scripts critiques en production).
9. Lancer Apache et MySQL, ensuite ouvrir `http://localhost/fluxvision_fin/login` pour verifier l'interface.

## Configuration cle
| Fichier / dossier           | Sujet principal                                                                |
| --------------------------- | ------------------------------------------------------------------------------ |
| `.env`                      | Tokens API, rate limit, allowlist IP, audit log                                |
| `config/database.php`       | Detection environnement (local vs production), credentials MySQL               |
| `config/app.php`            | Chargement `.env`, chemins `DATA_PATH`, helpers de reponses JSON               |
| `config/session_config.php` | Durcissement des sessions PHP (cookies, timeout, headers)                      |
| `config/email.php`          | Parametrage SMTP pour les notifications                                        |
| `logs/` et `data/`          | Journaux applicatifs et fichiers temporaires pour les traitements              |
| `etl_checkpoint.json`       | Etat de progression ETL, utile pour les relances                              |


## Variables d'environnement
- `APP_ENV` : `local`, `production`, etc. Contrôle la configuration chargée (affichage d'erreurs désactivé en production).
- `DB_HOST_PROD`, `DB_PORT_PROD`, `DB_NAME_PROD`, `DB_USER_PROD`, `DB_PASSWORD_PROD` : informations de connexion MySQL pour la production (obligatoires, aucune valeur par défaut dans le code).
- `DB_HOST_LOCAL`, `DB_PORT_LOCAL`, `DB_NAME_LOCAL`, `DB_USER_LOCAL`, `DB_PASSWORD_LOCAL` : overrides facultatifs pour un environnement de développement.
- `DB_TIMEOUT_PROD` (optionnel) : délai de connexion PDO en secondes (défaut 30).
- `FORCE_SECURE_COOKIE` : fixer à `1` pour forcer `session.cookie_secure` si l’application tourne derrière un proxy TLS.
- `API_TOKENS`, `ADMIN_API_TOKENS`, `API_RATE_LIMIT`, `API_IP_ALLOWLIST`, `API_AUDIT_LOG` : paramètres de sécurisation des endpoints API (voir `docs/api_guide.md`).
## Lancement et flux principaux
- Import complet : suivre le runbook `docs/operations.md` (outils `tools/import`, `tools/migration`, diagnostics).
- ETL Python : scripts `tools/etl/populate_facts_*` alimentent MySQL via `api/database/facts_upsert.php`.
- Interface web : routage `index.php` -> templates `templates/*.php` -> assets `static/`.
- Infographies et dashboards consomment les APIs `api/infographie/*.php` et `api/database/*.php`.
- Les espaces partages et exports utilisent les endpoints `api/shared-spaces/*.php`.

## Structure du depot
| Chemin                     | Description rapide                                                                 |
| -------------------------- | ----------------------------------------------------------------------------------- |
| `api/`                     | Endpoints REST (database, infographie, shared-spaces, maintenance, securite)       |
| `classes/`                 | Services PHP : Auth, Security, TempTablesManager, ZoneMapper, etc.                 |
| `config/`                  | Parametrage applicatif, base de donnees, emails, sessions                         |
| `data/`                    | Fichiers data locaux (CSV temporaires, exports, checkpoints)                      |
| `docs/`                    | Documentation detaillee (architecture, operations, API, ETL)                      |
| `fluxvision_automation/`   | Scripts historiques et donnees sources Flux Vision                               |
| `includes/`                | Helpers globaux (`calendar_data_provider.php`, etc.)                              |
| `logs/`                    | Journaux web (`etl_fluxvision_production.log`, `etl_fluxvision_api.log`, ...)     |
| `scripts/`                 | Scripts d'administration ponctuels (securite, cache, utilitaires)                 |
| `sql/`                     | Requetes SQL d'analyse et de verification                                         |
| `static/`                  | Assets front (CSS, JS, images, librairies)                                        |
| `templates/`               | Vues PHP pour dashboards, administration, login                                   |
| `tools/`                   | CLI organises par categories (import, migration, diagnostic, maintenance, saisons, etl, dev) |
| `tools.php`                | Lanceur centralise des scripts `tools/`                                           |
| `cache/` et `temp/`        | Donnees mises en cache et fichiers temporaires                                    |
| `ftp_downloads/`           | Depot des fichiers recuperes depuis les sources externes                         |

## Outils CLI et automatisations
Les scripts `tools/` couvrent l'import des fichiers Flux Vision, la migration vers les tables cibles, les diagnostics de qualite et les operations de maintenance. Voir `docs/operations.md` pour les workflows detaillees et `tools/README.md` pour la liste des commandes.

## APIs et integrations
Les endpoints PHP exposes par `api/` fournissent l'acces aux donnees (infographies, periodes, espaces partages, administration). Les details d'authentification, de parametrage et d'exemples d'appels sont regroupes dans `docs/api_guide.md`.

## ETL et alimentation
Les scripts Python de `tools/etl/` orchestrent la transformation des fichiers CSV en faits et dimensions MySQL. Le fonctionnement, les parametres et les checkpoints sont documentes dans `docs/etl_pipeline.md`. Le scraping des saisons astronomiques se fait via `scrap_date.js`.

## Maintenance et observabilite
- Logs applicatifs : `logs/`, `api/database/storage/logs`, journaux cron eventuels.
- Surveillance ETL : fichiers `etl_fluxvision_production.log`, `etl_checkpoint.json`.
- Caches : verifier `cache/tableau_bord/` et utiliser `tools/maintenance/cleanup_temp_tables.php` apres migration.
- Sauvegardes : scripts `tools/maintenance/backup_critical.php` et procedures `scripts/`.

## Securite
- Authentification robuste : `classes/Auth.php`, chiffrage via `EncryptionManager`.
- Hardening HTTP : `.htaccess`, headers CORS, rate limiting configurable dans `.env`.
- Tokens Bearer pour les APIs critiques (`API_TOKENS`, `ADMIN_API_TOKENS`).
- Surveiller les acces via `SecurityManager::logSecurityEvent` et journaux dedies.

## Documentation detaillee
- [Architecture technique](docs/architecture.md)
- [Guide d'operations et runbooks](docs/operations.md)
- [Reference API](docs/api_guide.md)
- [Chaine ETL FluxVision](docs/etl_pipeline.md)
- [Liste des outils CLI](tools/README.md)



