# Chaine ETL FluxVision

## Vue generale
1. Reception des exports Flux Vision (CSV, XLSX) via SFTP/FTP.
2. Depose des fichiers dans `data/data_temp/` (ou `fluxvision_automation/data/data_temp/`).
3. Transformation via les scripts Python `tools/etl/` (normalisation, mapping dimensions).
4. Upsert des dimensions et faits dans MySQL via `api/database/dim.php` et `api/database/facts_upsert.php`.
5. Validation fonctionnelle et publication vers les dashboards.

```
Fichiers CSV -> tools/etl (Polars) -> API database -> MySQL -> Dashboards / APIs
```

## Sources et pretraitements
- **CSV bruts** : fichiers Flux Vision par famille (`Nuitee`, `Diurne`, `SejourDuree`, `Lieu*`).
- **Depots** : `ftp_downloads/` (fichiers entrants), `data/data_temp/` (zone de travail), `fluxvision_automation/data/` (historique).
- **Scripts d'assistance** : `scripts/populate_departements_only.py`, `scripts/python_db_client.py` pour des requetes rapides.

## Scripts Python principaux (`tools/etl/`)
| Script                                      | Objet principal                                              | Remarques |
| ------------------------------------------- | ------------------------------------------------------------ | --------- |
| `populate_facts_full_production.py`         | Import complet (historiques + familles Lieu*)                | Support batch, mode test |
| `populate_facts_full_production_api.py`     | Variante orientee appels API (optimisee pour reseau)        |           |
| `populate_facts_optimized.py`               | Chargement optimise (volumes reduits, perimetre cible)       |           |
| `cleanup_2025.py`                           | Nettoyage dedie pour l'annee 2025                           |           |
| `remote_db_explorer.py`, `secure_remote_explorer.py` | Exploration distante securisee                           | Necessite cred              |
| `test_commune_dept_linking.py`              | Tests unitaires de mapping communes/departements            |           |
| `extract_only.py`                           | Extraction sans chargement (debug ETL)                      |           |

Ces scripts reposent sur Polars pour la lecture CSV et `mysql.connector` pour les ecritures. Le logging est dirige vers `etl_fluxvision_production.log` ou `etl_fluxvision_api.log`.

## Configuration ETL
- `etl_checkpoint.json` : suivi des fichiers deja traites, relances, horodatages.
- Variables CLI courantes (arguments) :
  - `--host`, `--port`, `--user`, `--password`, `--database` pour MySQL.
  - `--data-path` pour surcharger le dossier de travail.
  - `--test-mode` pour cibler les tables `_test`.
  - `--batch-size` pour ajuster les insert multiples.
  - `--strip-accents` pour normaliser les libelles.
- Les scripts nettoient et normalisent les dimensions (zones, categories, provenances, departements, communes, EPCI) avant l'envoi.

## Sequence d'execution type
1. Activer l'environnement virtuel Python (`.venv`).
2. Mettre a jour les dependances : `pip install -r requirements_etl.txt` (ou `pip install polars mysql-connector-python requests urllib3 python-dotenv`).
3. Executer `python tools/etl/populate_facts_full_production.py --test-mode` pour une repetition a blanc.
4. Analyser `etl_fluxvision_production.log` et `etl_checkpoint.json`.
5. Lancer en production : `python tools/etl/populate_facts_full_production.py --batch-size 2000`.
6. Controler les retours API (`success`, `counts.processed`) et lancer les diagnostics (`docs/operations.md`).

## Integration avec les APIs
- **Dimensions** : `FactTablePopulator` resolue automatiquement via `/api/database/dim.php`.
- **Faits** : envoi lot par lot vers `/api/database/facts_upsert.php`.
- **Mode test** : `options.test_mode=true` pour charger les tables `_test` sans impacter la production.
- **Retry** : le script gere les doublons et reessaye en cas de conflits SQL (selon options).

## Qualite et verification
- Diagnostics CLI : `php tools/diagnostic/validation_complete_import.php`, `php tools/diagnostic/compare_mobility_2024_2023.php`.
- SQL : utiliser `api/database/tables_info.php?stats=true&structure=true` pour controler les volumes.
- Logs : `etl_fluxvision_production.log`, `etl_fluxvision_api.log`, `logs/`.
- Table `etl_checkpoint.json` : confirmer que tous les fichiers attendus sont en `status: done`.

## Automatisation
- Prevoir une tache planifiee (cron, planificateur Windows) pour :
  - Telecharger les nouveaux fichiers Flux Vision.
  - Lancer `python tools/etl/populate_facts_full_production.py`.
  - Executer le workflow de migration (`docs/operations.md`).
  - Purger les caches (`tools/maintenance/cleanup_temp_tables.php`).
- Surveiller le volume des logs et implementer une rotation (scripts `setup_cache_cleanup_task.ps1`, `test_automation.bat`).

## Saisons astronomiques (Node.js)
- `scrap_date.js` collecte les dates d'equinoxes et solstices (Axios + Cheerio) et genere `saisons_data.php`.
- Commande : `npm run start` (ou `node scrap_date.js`).
- Integre a `tools/saisons/update_saisons.php` pour charger les periodes dans MySQL.


