# Outils CLI FluxVision

## Lancement rapide
```
php tools.php <categorie> <script> [arguments]
```

Exemples :
- `php tools.php import update_temp_tables`
- `php tools.php diagnostic check_zones`
- `php tools.php migration migrate_temp_to_main`

## Categories disponibles
| Categorie    | Scripts clefs                                                    | Objectif principal                               |
| ------------ | ---------------------------------------------------------------- | ------------------------------------------------ |
| import       | `update_temp_tables.php`, `import_sejours_duree_fr.php`, `import_optimized.php` | Charger les CSV Flux Vision dans les tables temporaires |
| migration    | `migrate_temp_to_main.php`, `migrate_sejours_duree.php`, `migrate_optimized.php` | Basculer les donnees temporaires vers les tables cibles |
| diagnostic   | `check_status.php`, `check_zones.php`, `verify_migration.php`    | Controler la qualite des donnees et des mappings |
| maintenance  | `cleanup_temp_tables.php`, `backup_critical.php`, `improve_response.php` | Nettoyer caches, sauvegarder et optimiser        |
| saisons      | `update_saisons.php`, `status_saisons.php`, `reset_saisons.php`  | Mettre a jour les periodes astronomiques         |
| etl          | `populate_facts_full_production.py`, `populate_facts_optimized.py`, `cleanup_2025.py` | Executer la chaine ETL Python                     |
| dev          | `simple_test.php`, `test_names_approach.php`                     | Tests ponctuels et debug                         |

## Details par categorie
### import/
- `update_temp_tables.php` : workflow complet d'import des CSV vers les tables temporaires.
- `import_sejours_duree_fr.php`, `import_sejours_duree_int.php` : import specialise par provenance.
- `import_optimized.php` : import cible avec filtres et optimisation memoire.
- `check_import_progress.php` : suivi de progression (ETL ou scripts planifies).

### migration/
- `migrate_temp_to_main.php` : migration principale des tables `_temp` vers les tables finales.
- `migrate_sejours_duree*.php` : migrations dediees aux sejours par duree.
- `migrate_optimized.php` : migration reduite (volumes selectionnes).
- Options : `--test` pour ecrire dans les tables `_test`, `--dry-run` pour simuler (si implemente).

### diagnostic/
- Verifications fonctionnelles : `check_status.php`, `check_zones.php`, `validation_complete_import.php`.
- Comparatifs : `compare_mobility_2024_2023.php`, `analyze_mobility_trends.php`.
- Debug API : `test_api_simple.php`, `test_infographie_apis.php`, `test_api_mobilite_directe.php`.
- Outils mapping : `test_zone_mapping_api_production.php`, `test_mapping_zones_csv.php`.

### maintenance/
- `cleanup_temp_tables.php` : purge des tables temporaires apres migration.
- `backup_critical.php` : sauvegarde des donnees sensibles (CSV + MySQL).
- `add_missing_zones.php` : ajout des zones absentes.
- `improve_response.php` : ajustements de performances pour les reponses API.

### saisons/
- `update_saisons.php` : injecte `saisons_data.php` dans MySQL.
- `status_saisons.php` : etat courant des saisons et periodes associees.
- `reset_saisons.php` : reinitialisation (a manipuler avec precaution).
- `verifier_saisons.php` : verification transversale post import.

### etl/
- Scripts Python (executer via `python` ou `pipenv`) :
  - `populate_facts_full_production.py` : flux complet.
  - `populate_facts_full_production_api.py` : flux oriente API distante. (en cours d'implementation)
  - `populate_facts_optimized.py` : scenario allege. (nouvelle de populate_facts_full_production.py)
  - `cleanup_2025.py`, `extract_only.py` : maintenance ponctuelle.
- Logs generes : `etl_fluxvision_production.log`, `etl_fluxvision_api.log`.

### dev/
- `simple_test.php` : verifications basiques de connectivite.
- `test_names_approach.php` : tests autour des conventions de nommage.

## Options generiques
- `php tools.php <categorie> <script> --help` affiche les options disponibles (si implementes dans le script cible).
- Les scripts critiques retournent un code different de zero en cas d'erreur : surveiller la sortie.
- Pour lancer une version test, utiliser les options `--test` ou variables d'environnement selon le script.

## Bonnes pratiques
- Toujours travailler sur une copie des CSV dans `data/data_temp/` (ne pas modifier les fichiers sources).
- Executer les diagnostics apres chaque import/migration.
- Purger `cache/` et `temp/` suite aux migrations massives.
- Consigner les executions critiques dans un journal d'exploitation.
- Tester les nouvelles versions en mode `_test` avant la production.

Pour des workflows complets et la planification, se reporter a `../docs/operations.md`.

