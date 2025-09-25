# Repository Guidelines

## Project Structure & Module Organization
- Serve the PHP portal from the repository root; routed pages sit in `templates/`, shared helpers in `includes/`, and domain services in `classes/`.
- REST endpoints live under `api/` grouped by feature (for example `api/infographie/`), while CLI tooling is organized in `tools/`.
- Front-end assets stay in `static/`, datasets in `data/`, and operational documentation in `docs/` with automation notes in `docs/operations.md`.
- Long-running jobs record checkpoints in `logs/` and `etl_checkpoint.json`; keep these paths writable during ETL runs.

## Build, Test & Development Commands
- `php -S localhost:8000 -t .` — smoke-test the portal without Apache.
- `npm install && npm run start` — refresh generated resources such as `templates/saisons_data.php`.
- `python tools/build_front_assets.py` — rebuild bundled JS/CSS under `static/`.
- `php tools.php import update_temp_tables` — load CSVs into staging tables before migrations.
- `php tools.php migration migrate_temp_to_main --test` — dry-run database migrations, then follow with `php tools.php diagnostic check_status`.

## Coding Style & Naming Conventions
- PHP follows PSR-12: four-space indents, braces on the next line, one class per file named in PascalCase (e.g. `classes/TempTablesManager.php`).
- Methods remain camelCase, configuration keys snake_case; keep templates PHP-light and `static/js/` modules ES6.
- Run `php -l path/to/file.php` before committing touched PHP files.

## Testing Guidelines
- Use `php tools.php dev simple_test` to verify database connectivity and `php tools.php diagnostic check_zones` for mapping integrity.
- Prefer ETL dry-runs such as `python tools/etl/populate_facts_full_production.py --dry-run` when validating data changes.
- Document manual QA and repeatable checks in `docs/operations.md` after new scripts or migrations.

## Commit & Pull Request Guidelines
- Commit subjects stay under 60 characters with a scoped prefix like `api:` or `migration:` followed by a short action (e.g. `api: secure infographie routes`).
- Pull requests should link tickets, list CLI checks executed, and include screenshots for UI updates; flag security-sensitive edits and confirm secrets remain out of the diff.

## Security & Configuration Tips
- Keep `.env` aligned with `config/database.php`; never commit credentials or API tokens.
- Run `php tools.php maintenance backup_critical` before destructive changes, then purge `cache/` and `temp/` when migrations conclude.
- Prefer `signed_url('/api/...', [...])` so `SecureUrl` validates tokens and leverage `CantalDestinationCacheManager` to prevent cache stampedes.
