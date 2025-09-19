# Architecture technique

## Vue d'ensemble
La plateforme suit une architecture trois couches : presentation web, services PHP et traitements de donnees. Les composants communiquent principalement via MySQL et les APIs PHP.

```
Flux Vision CSV -> tools/etl -> api/database -> MySQL -> api/infographie -> templates/static -> Navigateur
```

| Composant            | Role principal                                                     | Dossiers clefs                   |
| -------------------- | ------------------------------------------------------------------ | -------------------------------- |
| Portail web          | Interface dashboards, administration, espaces partages             | `index.php`, `templates/`, `static/`
| Services metiers      | Authentification, securite, gestion des periodes et tables temp    | `classes/`, `includes/`          |
| APIs                 | Exposition des donnees (database, infographie, shared spaces)      | `api/`                           |
| ETL et scripts       | Import CSV, migration, diagnostics, saisons astronomiques          | `tools/`, `scripts/`, `scrap_date.js` |
| Donnees et stockage  | Base MySQL, fichiers temporaires, caches, journaux                 | `data/`, `cache/`, `logs/`       |

## Couches applicatives

### Presentation (front)
- Templates PHP dans `templates/` (dashboards, infographie, administration, login).
- Modules JavaScript dans `static/js/` (chargement des filtres, graphiques D3.js, interactions UI).
- Ressources statiques `static/css/`, `static/images/` et librairies dans `static/js/lib/`.

### Application serveur PHP
- `index.php` sert de routeur principal et charge `config/app.php` et les classes de securite.
- `classes/` fournit les services : `Auth`, `SecurityManager`, `TempTablesManager`, `ZoneMapper`, `PeriodesController`, `InfographicManager`, etc.
- `includes/` contient des helpers orientes presentation (`calendar_data_provider.php`).
- `config/session_config.php` durcit les sessions (cookies HTTPOnly, meme site, regeneration).

### APIs HTTP
- `api/database/` : endpoints d'administration schema (`schema_ensure.php`), `tables_info.php`, upsert de dimensions/faits, stockage logs.
- `api/infographie/` : endpoints specifiques aux infographies (indicateurs cle, origines geographiques, periodes).
- `api/shared-spaces/` : gestion des espaces partages et des exports personnalises.
- `api/users/available.php` et scripts racine (`apply_zone_mapper.php`, `filters_mysql.php`, scripts bloc Dn) completent le perimetre historique.
- Les APIs reutilisent `config/app.php` pour le chargement `.env`, la detection environnement et les reponses JSON normalisees.

## Donnees et persistance
- MySQL est pilote via PDO (`classes/Database.php`), avec detection auto local vs production (`config/database.php`).
- Les donnees sources temporaires sont stockees dans `data/data_temp/` ou `fluxvision_automation/data/data_temp/` (helper `resolve_data_temp_dir`).
- Les caches d'infographie sont ranges dans `cache/tableau_bord/` et nettoyes via `tools/maintenance/cleanup_temp_tables.php` ou scripts dedies.
- Les scripts SQL de controle sont centralises dans `sql/` et `tools/diagnostic/`.

## Outils et automatisations
- Les scripts CLI sont organises par categorie dans `tools/` avec un lanceur commun `tools.php`.
- Categories principales : `import/`, `migration/`, `diagnostic/`, `maintenance/`, `saisons/`, `etl/`, `dev/`.
- Des scripts ponctuels supplementaires residant dans `scripts/` couvrent chiffrement, verification securite, automation cache.

## ETL et collecte
- Les scripts Python `tools/etl/populate_facts_*` transforment les CSV Flux Vision (Polars) et appellent `api/database/facts_upsert.php`.
- `etl_checkpoint.json` trace l'etat des imports pour permettre reprise et monitoring.
- `scrap_date.js` (Node.js) collecte les dates d'equinoxes et solstices depuis icalendrier.fr et alimente `saisons_data.php`.
- Les extractions historiques residant dans `fluxvision_automation/` servent de depot aux fichiers sources et a des scripts legacy.

## Caches et fichiers temporaires
- `cache/` contient les caches front-end (infographie, tableau de bord) et doit etre purge en cas de redeploiement.
- `temp/` et `ftp_downloads/` accueillent les extractions brutes avant traitement ETL.
- Les scripts PowerShell/BAT (`scripts/clear_cache_midnight.*`) peuvent etre integres a des taches planifiees.

## Journalisation et observabilite
- Journaux applicatifs : `logs/`, `etl_fluxvision_production.log`, `etl_fluxvision_api.log`.
- API database : journaux specifiques dans `api/database/storage/logs/`.
- La securite enregistre ses evenements via `SecurityManager::logSecurityEvent` (logs PHP standard).
- Prevoir une rotation externe (cron ou systeme d'exploitation) pour ces fichiers.

## Gestion des environnements
- `config/database.php` active le mode production des que l'hote correspond au domaine officiel ou a une IP non locale.
- `config/app.php` derive `DEBUG` et les chemins de donnees en fonction de l'environnement detecte.
- `.env` controle les tokens API, limites de debit, allowlist IP et journaux audit.
- Les scripts CLI acceptent un mode test (tables suffixees `_test`) afin d'isoler les charges de validation.
