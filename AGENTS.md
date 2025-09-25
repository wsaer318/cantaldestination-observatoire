# Repository Guidelines

## Project Structure & Module Organization
Serve the PHP portal from the repository root; routed pages live in `templates/` and shared helpers in `includes/`. Domain logic sits in `classes/`, REST endpoints in `api/` grouped by feature (infographie, database, shared-spaces). Assets live in `static/`, datasets in `data/`, docs in `docs/`, and automation notes in `docs/operations.md`. CLI tooling resides in `tools/` (import, migration, diagnostic, etl, maintenance, saisons, dev), and long-running jobs write checkpoints to `logs/` and `etl_checkpoint.json`.

## Build, Test & Development Commands
Serve via Apache or `php -S localhost:8000 -t .` for smoke checks. Run `npm install`, then `npm run start` to rebuild `saisons_data.php`. Bundle assets with `python tools/build_front_assets.py`. Import CSVs through `php tools.php import update_temp_tables`, validate migrations via `php tools.php migration migrate_temp_to_main --test`, and trigger lean ETL with `python tools/etl/populate_facts_optimized.py`. Finish with `php tools.php diagnostic check_status` to confirm integrity.

## Coding Style & Naming Conventions
Follow PSR-12: four-space indentation, braces on the next line, one class per file named in PascalCase (for example `classes/TempTablesManager.php`). Methods stay camelCase, configuration keys snake_case. Keep templates PHP-light, keep `static/js/` modules ES6, and pick descriptive names like `mobility_trends.js`. Run `php -l path/to/file.php` on touched files, respect `.gitignore`, and keep `.env` out of commits.

## Testing Guidelines
Automated suites are light, so lean on operational checks. Use `php tools.php dev simple_test` to validate database connectivity, `php tools.php diagnostic check_zones` to verify mappings, and selective ETL dry runs such as `python tools/etl/populate_facts_full_production.py --dry-run` when available. New scripts should expose a `--test` or `--dry-run` flag aligned with current tooling. Capture manual QA steps in `docs/operations.md`.

## Commit & Pull Request Guidelines
History shows compact summaries with a short prefix and colon (for example `Initial commit:` or `Mise a jour:`), so keep the first line under 60 characters and mention the impacted area (`migration:`, `api:`). Use the body for context, data sources, and follow-ups. Pull requests should link tickets, list CLI commands run (import, migration, diagnostics), and attach screenshots for UI changes. Flag security-sensitive edits and confirm secrets stay out of the diff.

## Security & Configuration Tips
Keep `.env` aligned with `config/database.php` so environment detection stays correct, and never commit credentials. Run `php tools.php maintenance backup_critical` before destructive actions, then purge `cache/` and `temp/` after heavy migrations. Audit API tokens and archive ETL outputs in `logs/`. Prefer `signed_url('/api/...', [...])` so `SecureUrl` can validate tokens. Leverage `CantalDestinationCacheManager` (file cache + memory layer) to avoid cache stampedes.

