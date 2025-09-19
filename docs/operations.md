# Guide d'exploitation

## Vue rapide des outils
| Categorie    | Objectif principal                                      | Commandes pivot                                        |
| ------------ | ------------------------------------------------------- | ------------------------------------------------------ |
| Import       | Charger les CSV Flux Vision dans les tables temporaires | `php tools/import/update_temp_tables.php`              |
| Migration    | Deplacer les donnees temporaires vers les tables cibles | `php tools/migration/migrate_temp_to_main.php`         |
| Diagnostic   | Verifier la qualite et la coherence des donnees         | `php tools/diagnostic/check_zones.php`, `verify_migration.php` |
| Maintenance  | Nettoyer, sauvegarder, reajuster les donnees            | `php tools/maintenance/cleanup_temp_tables.php`        |
| Saisons      | Mettre a jour les periodes saisonnieres                 | `php tools/saisons/update_saisons.php`                 |
| ETL          | Alimenter MySQL via scripts Python                      | `python tools/etl/populate_facts_full_production.py`   |
| Dev / tests  | Scenarios de tests ponctuels                            | `php tools/dev/simple_test.php`                        |

## Workflow standard d'import Flux Vision
1. **Preparation des fichiers**
   - Deposer les CSV dans `data/data_temp/` (ou le dossier legacy `fluxvision_automation/data/data_temp/`).
   - Verifier la structure des fichiers et l'annee couverte.
2. **Import vers les tables temporaires**
   - `php tools/import/update_temp_tables.php` (options : `--year=2025`, `--skip-large` selon besoin).
   - Scripts specifiques : `import_sejours_duree_fr.php`, `import_sejours_duree_int.php`, `import_optimized.php`.
3. **Diagnostics immediats**
   - `php tools/diagnostic/check_status.php` pour le statut global.
   - `php tools/diagnostic/check_zones.php` pour la couverture geographique.
   - `php tools/diagnostic/validation_complete_import.php` pour une synthese.
4. **Migration vers les tables finales**
   - `php tools/migration/migrate_temp_to_main.php` (option `--test` disponible pour tables `_test`).
   - Migrations ciblees : `migrate_sejours_duree.php`, `migrate_optimized.php`.
5. **Validation post-migration**
   - `php tools/diagnostic/verify_migration.php` et `compare_mobility_2024_2023.php`.
   - Verifier les dashboards via l'interface (`/dashboard`, `/infographie`).
6. **Nettoyage et archivage**
   - `php tools/maintenance/cleanup_temp_tables.php` pour purger les tables temporaires.
   - `php tools/maintenance/backup_critical.php` pour sauvegarder les jeux critiques.
   - Archiver les CSV traites dans `data/archive/` (a creer si besoin).

## Diagnostics et supervision
- **Zones, periodes, mapping** : `check_zones.php`, `diagnostic_mobility.php`, `test_zone_mapping_api_production.php`.
- **API** : `test_api_simple.php`, `test_api_mobilite_directe.php`, `test_infographie_apis.php`.
- **Tables** : `analyse_zones_csv.php`, `etat_csv_complet.php`, scripts `diagnostic_migration*`.
- **Performances cache** : `cache_admin.php`, `cleanup_old_cache.php` (dans `api/infographie/`).
- Logs associes : `logs/`, `api/database/storage/logs/`, `etl_fluxvision_api.log`.

## Maintenance recurrente
- **Caches infographie** : `php tools/maintenance/improve_response.php` ou scripts `scripts/clear_cache_midnight.*` planifies.
- **Saisons astronomiques** :
  - Scraper : `npm run start` (execute `scrap_date.js`).
  - Appliquer en base : `php tools/saisons/update_saisons.php`, `php tools/saisons/status_saisons.php`.
- **Sauvegardes** : `php tools/maintenance/backup_critical.php` et export MySQL regulier.
- **Audit securite** : `scripts/security_cleanup_check.php`, `php tools/diagnostic/test_admin_restrictions.php`.
- **Nettoyage fichiers temporaires** : surveiller `temp/`, `ftp_downloads/`, `data/data_temp/`.

## Planification recommande
| Tache                                | Cadence conseillee | Commande / action                                           |
| ------------------------------------ | ------------------ | ----------------------------------------------------------- |
| Import Flux Vision (haute saison)    | Hebdomadaire       | Workflow complet ci-dessus                                  |
| Purge cache tableaux de bord         | Quotidien          | `php tools/maintenance/cleanup_temp_tables.php` ou scripts  |
| Regeneration saisons astronomiques   | Trimestriel        | `npm run start` puis `php tools/saisons/update_saisons.php` |
| Sauvegarde base et journaux          | Quotidien          | Dump MySQL + rotation `logs/`                               |
| Verification securite                | Mensuel            | `scripts/security_cleanup_check.php`                        |
| Mise a jour ETL et checkpoints       | Apres chaque run   | Verifier `etl_checkpoint.json` et les logs ETL              |

## Gestion des incidents
- **Echec ETL** : consulter `etl_fluxvision_production.log`, relancer avec `--batch-size` plus faible, verifier la connexion MySQL.
- **Incoherence dashboard** : vider `cache/`, reexecuter les diagnostics zones/periodes, controler les donnees dans MySQL (`api/database/tables_info.php`).
- **Problemes d'authentification** : controler `classes/Auth.php`, le chiffrement (`EncryptionManager`), et les tables `users`.
- **APIs rate limitees** : ajuster `API_RATE_LIMIT` et l'allowlist dans `.env`, consulter `api/database/storage/logs/`.

## Ressources complementaires
- [Architecture technique](architecture.md)
- [Reference API](api_guide.md)
- [Chaine ETL FluxVision](etl_pipeline.md)
- [Liste des outils CLI](../tools/README.md)


