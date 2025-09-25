# Reference API

## Authentification et formats
- Jetons Bearer : `Authorization: Bearer <token>` renseign�s dans `.env` (`API_TOKENS`, `ADMIN_API_TOKENS`).
- Sessions portail : utilisateurs connect�s via `Auth::isAuthenticated()` / `Auth::requireAuth()`.
- Restrictions r�seau : `API_IP_ALLOWLIST`, `API_FACTS_RESTRICT_IPS` et quota `API_RATE_LIMIT` (`requ�tes/minute`).
- Toutes les r�ponses passent par `jsonResponse()` avec `{success, data, message, errors}`; activer `API_AUDIT_LOG` trace les �checs dans `logs/api/*.log`.

## Domaines principaux

### Base de donn�es (`api/database/`)
| M�thode | Endpoint | Usage | Auth | Particularit�s |
| --- | --- | --- | --- | --- |
| GET | `/api/database/tables_info.php` | Inventaire tables/colonnes | Token ou session | `structure=true`, `stats=true` pour le sch�ma complet |
| POST | `/api/database/dim.php` | Upsert des dimensions (zones, cat�gories, provenances) | Token | JSON `skip_conflicts`, `strict`, `test_mode` |
| POST | `/api/database/facts_upsert.php` | Insertion batch des faits (nuit�es, diurnes, s�jours) | Token | `rows[]`, `options.batch_size`, support `_test` |
| POST | `/api/database/schema_ensure.php` | Provisionnement du sch�ma cible | Token admin + IP | `preset=all`, `dry_run=1` |
| POST | `/api/database/schema_drop_test.php` | Nettoyage des tables `_test` | Token admin + IP | � limiter aux environnements de recette |

### Infographie (`api/infographie/`)
| Endpoint | R�le | Param�tres clefs | Notes |
| --- | --- | --- | --- |
| `infographie_indicateurs_cles.php` | KPIs g�n�raux | `annee`, `periode`, `zone` | S'appuie sur `InfographicManager` |
| `infographie_departements_*` | Tops d�partements touristes / excursionnistes | `annee`, `periode`, `zone`, `limit` | Suffixes `_touristes`, `_excursionnistes` |
| `infographie_regions_*` | R�partition par r�gion | m�mes param�tres | |
| `infographie_pays_*` | R�partition par pays | idem | |
| `infographie_communes_excursion.php` | Mobilit� interne | `annee`, `periode`, `zone`, `limit` | |
| `infographie_duree_sejour.php` | Histogramme des s�jours | `annee`, `periode`, `zone` | |
| `infographie_periodes.php` | Catalogue des p�riodes | `action=all` | |
| `cache_admin.php`, `cleanup_old_cache.php`, `migrate_cache.php` | Gestion du cache tableau de bord | Session admin | Pilote `cache/tableau_bord/` |

### Espaces partag�s (`api/shared-spaces/`)
| M�thode | Endpoint | Usage | Auth |
| --- | --- | --- | --- |
| GET | `/api/shared-spaces/spaces.php` | Lister les espaces | Session admin |
| POST | `/api/shared-spaces/spaces.php` | Cr�er / mettre � jour | Session admin |
| GET | `/api/shared-spaces/members.php` | Voir les membres | Session admin |
| POST | `/api/shared-spaces/members.php` | Ajouter / retirer un membre | Session admin |
| POST | `/api/shared-spaces/infographics.php` | Associer des infographies | Session admin |
| POST | `/api/shared-spaces/router.php` | Op�rations combin�es | Session admin |

### Utilisateurs (`api/users/available.php`)
| M�thode | Endpoint | Usage | Auth |
| --- | --- | --- | --- |
| GET | `/api/users/available.php` | Retourne les comptes actifs hors utilisateur courant | Session (`Auth::requireAuth()`) |

## Filtres & p�riodes (`api/filters/`)
| Endpoint | R�le | Param�tres clefs | Auth |
| --- | --- | --- | --- |
| `/api/filters/filters.php` | Filtres statiques pour maquettes | aucun | Token ou session |
| `/api/filters/filters_mysql.php` | Filtres dynamiques MySQL avec `metadata` | auto | Token |
| `/api/filters/filters_mysql_backup.php` | Version de secours legacy | identiques � `filters_mysql` | Token |
| `/api/filters/calendar_periods.php` | P�riodes pour widgets calendrier | `zone`, `annee` optionnels | Token ou session |
| `/api/filters/get_periodes.php` | Catalogue de p�riodes (simplifi�) | `annee`, `zone` | Token |
| `/api/filters/period_options.php` | P�riodes filtr�es pour formulaires | `annee`, `zone`, `type` | Token |
| `/api/filters/periodes_dates.php` | Jalons calendaires enrichis | `annee`, `zone` | Token |

## Analytics & mobilit� (`api/analytics/`)
| Endpoint | R�le | Param�tres clefs | Notes |
| --- | --- | --- | --- |
| `/api/analytics/communes_excursion.php` | Mobilit� par commune | `annee`, `periode`, `zone`, `limit`, `export` | `export=csv` disponible |
| `/api/analytics/departements_excursion.php` | Classement d�partements excursionnistes | `annee`, `periode`, `zone`, `metric` | |
| `/api/analytics/regions_excursion.php` | R�partition par r�gion | `annee`, `periode`, `zone` | |
| `/api/analytics/comparison.php` | Comparaison multi-zones | `zones[]`, `periodes[]`, `metrics[]` | |
| `/api/analytics/comparison_departements.php` | Comparaison cibl�e par d�partement | idem | |
| `/api/analytics/comparison_detailed.php` | Comparaison d�taill�e (s�ries longues) | idem + `granularite` | |
| `/api/analytics/comparison_debug.php` | Version verbeuse pour QA | m�mes param�tres + `debug=1` | |
| `/api/analytics/current_period_info.php` | P�riode active du portail | `zone` | |
| `/api/analytics/fiches.php` | M�tadonn�es pour fiches PDF | `zone`, `periode` | |
| `/api/analytics/example_with_provisional.php` | Exemple mixte (provisoire vs consolid�) | `zone`, `periode` | utile pour QA |

## Tableaux de bord legacy (`api/legacy/blocks/`)
- `bloc_a.php`, `bloc_a_working.php` : Données principales et variantes de travail
- `bloc_d1*.php` : Départements (variants : `_cached`, `_exc`, `_exc_cached`, `_mysql`)
- `bloc_d2*.php` : Régions (variants : `_exc`, `_exc_cached`, `_simple`)
- `bloc_d3*.php` : Pays (variants : `_exc`, `_exc_cached`, `_simple`)
- `bloc_d5*.php`, `bloc_d6*.php` : Autres analyses (variants : `_exc`, `_exc_cached`, `_simple`)
- `bloc_d7.php`, `bloc_d_unified.php`, `bloc_d_advanced_mysql.php` : Agrégations spécialisées

Param�tres communs : `zone`, `periode`, `annee`, `type`, `mode`, `limit` (pour les variants `_simple`).

## Maintenance & op�rations (`api/maintenance/`)
| Endpoint | R�le | Auth | Notes |
| --- | --- | --- | --- |
| `/api/maintenance/cache_admin_unifie.php` | Purge et warmup du cache portail | Session admin | agit sur `cache/tableau_bord/` |
| `/api/maintenance/cache_purge_daily.php` | Cron de purge quotidienne | Token admin + IP | alimente `logs/cache_purge_daily.log` |
| `/api/maintenance/cache_maintenance_auto.php` | Nettoyage automatique du cache infographie | Session admin | journalise succ�s/erreurs |
| `/api/maintenance/cleanup_old_tdb_cache.php` | Suppression des caches obsol�tes | Token admin + IP | |
| `/api/maintenance/optimize_database.php` | Optimisation des index | Token admin + IP | �crit `logs/maintenance.log` |
| `/api/maintenance/apply_zone_mapper.php` | Injection ZoneMapper dans les scripts | Token admin + IP | scanne `legacy/blocks` et `analytics` |

## Outils & debug (`api/tools/`)
| Endpoint | R�le | Auth | Notes |
| --- | --- | --- | --- |
| `/api/tools/db_explorer.php` | Explorateur SQL simplifi� | Cl� API interne | lecture seule |
| `/api/tools/db_explorer_secure.php` | Explorateur SQL s�curis� | Session admin + IP | renforce le contr�le d�acc�s |
| `/api/tools/remote_db_explorer.php` | Proxy BDD distantes | Session admin | exploite `config/database.php` |
| `/api/tools/debug_env.php` | Dump de la config active | Session admin | masque les secrets |
| `/api/tools/debug_bloc_d3.php` | Debug du bloc D3 | Session admin | active les traces SQL |
| `/api/tools/secure_all_apis.php` | Audit CLI des protections | Ex�cution CLI (`php api/tools/secure_all_apis.php`) | v�rifie tokens et middleware |

## Codes retour standards
| HTTP | Signification |
| --- | --- |
| 200 | Requ�te OK |
| 201 | Ressource cr��e |
| 204 | Pas de contenu |
| 400 | Param�tre manquant ou JSON invalide |
| 401 | Authentification requise |
| 403 | Refus (r�le, IP, token) |
| 404 | Ressource absente |
| 409 | Conflit / duplication |
| 429 | Quota d�pass� |
| 500 | Erreur interne (voir logs) |

## Exemples
```sh
# Inventaire complet avec sch�ma
curl -H "Authorization: Bearer $API_TOKEN" \
  "https://exemple/api/database/tables_info.php?structure=true&stats=true"

# Insertion de faits en mode test
curl -X POST -H "Authorization: Bearer $API_TOKEN" -H "Content-Type: application/json" \
  -d '{
        "table": "fact_nuitees",
        "rows": [{"date": "2025-08-01", "nom_zone": "CANTAL", "nom_provenance": "FRANCE", "volume": 1234}],
        "options": {"test_mode": true}
      }' \
  https://exemple/api/database/facts_upsert.php

# R�cup�rer les utilisateurs disponibles
curl -H "Cookie: PORTAIL_SESSION=..." \
  https://exemple/api/users/available.php
```
