# Reference API

## Principes d'authentification
- **Bearer token** : header `Authorization: Bearer <token>` (valeurs definies dans `.env` via `API_TOKENS`, `ADMIN_API_TOKENS`).
- **Session applicative** : utilisateurs connectes via l'interface (`Auth::isAuthenticated()`).
- **Restriction IP** : optionnelle via `API_IP_ALLOWLIST` et `API_FACTS_RESTRICT_IPS`.
- **Rate limiting** : `API_RATE_LIMIT` (ex. `600/60`) limite le nombre de requetes par minute.

Toutes les reponses sont au format JSON (`jsonResponse()` dans `config/app.php`). En cas d'erreur, le champ `error` fournit le message detaille.

## API database (`api/database/`)
| Methode | Endpoint                          | Description                                                   | Authentification |
| ------- | --------------------------------- | ------------------------------------------------------------- | ---------------- |
| GET     | `/api/database/tables_info.php`   | Liste structure et metadonnees des tables MySQL              | Token ou session |
| POST    | `/api/database/dim.php`           | Upsert des dimensions (zones, provenances, categories, etc.) | Token requis     |
| POST    | `/api/database/facts_upsert.php`  | Upsert batch de faits (nuitees, diurnes, sejours, lieu*)     | Token requis     |
| POST    | `/api/database/schema_ensure.php` | Provisionnement schema (option `preset=all`, mode test)      | Token admin + IP |
| POST    | `/api/database/schema_drop_test.php` | Suppression schema test (seulement en environnement controle) | Token admin + IP |
| GET     | `/api/database/tables_info.php?structure=true` | Inclut colonnes et index                                     | Token ou session |

**Options notables**
- `X-Test-Mode: 1` ou parametre `options.test_mode=true` pour cibler les tables suffixees `_test`.
- `options.batch_size`, `options.skip_conflicts`, `options.strict` dans `facts_upsert.php`.
- Journalisation dans `api/database/storage/logs/` (audit et traitements ETL).

## API infographie (`api/infographie/`)
| Endpoint                                  | Objet principal                                        | Parametres clefs                     |
| ----------------------------------------- | ------------------------------------------------------ | ------------------------------------ |
| `infographie_indicateurs_cles.php`        | Indicateurs cle et meta periodes                      | `annee`, `periode`, `zone`           |
| `infographie_departements_touristes.php`  | Top departements (touristes)                          | `annee`, `periode`, `zone`, `limit`  |
| `infographie_departements_excursionnistes.php` | Top departements (excursionnistes)                | `annee`, `periode`, `zone`, `limit`  |
| `infographie_regions_touristes.php`       | Repartition par region (touristes)                    | `annee`, `periode`, `zone`, `limit`  |
| `infographie_regions_excursionnistes.php` | Repartition par region (excursionnistes)              | `annee`, `periode`, `zone`, `limit`  |
| `infographie_pays_touristes.php`          | Repartition par pays (touristes)                      | `annee`, `periode`, `zone`, `limit`  |
| `infographie_pays_excursionnistes.php`    | Repartition par pays (excursionnistes)                | `annee`, `periode`, `zone`, `limit`  |
| `infographie_communes_excursion.php`      | Mobilite interne par communes                         | `annee`, `periode`, `zone`, `limit`  |
| `infographie_periodes.php`                | Catalogue des periodes disponibles                     | `action=all`                         |
| `cache_admin.php`, `cleanup_old_cache.php`| Administration cache infographie                       | Session admin                        |

Ces endpoints utilisent `periodes_manager_db.php` et les classes `InfographicManager`/`PeriodMapper`. Le cache est gere via `api/infographie/cache_admin.php` et `cache/tableau_bord/`.

## API shared spaces (`api/shared-spaces/`)
| Methode | Endpoint                | Role                                                        |
| ------- | ----------------------- | ----------------------------------------------------------- |
| GET     | `/api/shared-spaces/spaces.php`  | Liste des espaces partages disponibles                       |
| POST    | `/api/shared-spaces/spaces.php`  | Creation / mise a jour d'un espace partage                  |
| GET     | `/api/shared-spaces/members.php` | Gestion des membres rattaches                               |
| POST    | `/api/shared-spaces/members.php` | Ajout / retrait de membres                                  |
| POST    | `/api/shared-spaces/infographics.php` | Configuration des infographies partagees                |
| POST    | `/api/shared-spaces/router.php`  | Point d'entree unique pour operations combinees            |

Acces restreint aux comptes administrateurs (`Auth::requireAdmin()` dans les templates). Les reponses incluent `success`, `data`, `messages`.

## Autres endpoints notables
- `api/apply_zone_mapper.php` : recalcule les mappings zones/provenances.
- `api/filters_mysql.php` : renvoie les filtres dynamiques (periodes, categories, etc.).
- `api/calendar_periods.php` : expose les periodes pour les calendriers intelligents.
- `api/cache_purge_daily.php`, `api/cache_admin_unifie.php` : maintenance automatique.
- `api/security_middleware.php` : inclus pour renforcer les controles d'entree.

## Codes retour
| HTTP | Signification                                        |
| ---- | ---------------------------------------------------- |
| 200  | Requete reussie                                      |
| 201  | Ressource creee (shared spaces)                      |
| 204  | Aucune donnee a retourner (delete/update silencieux) |
| 400  | Parametres invalides ou JSON mal forme               |
| 401  | Authentification requise                             |
| 403  | Acces refuse (role ou IP)                            |
| 404  | Ressource inexistante                                |
| 405  | Methode non autorisee                                |
| 409  | Conflit (duplicate, contrainte)                      |
| 429  | Trop de requetes                                     |
| 500  | Erreur interne (consulter les logs)                  |

## Exemples d'appels
```
# Inventaire des tables avec structure
curl -H "Authorization: Bearer $API_TOKEN" \
  "https://votre-domaine/api/database/tables_info.php?stats=true&structure=true"

# Upsert dimensions
curl -X POST -H "Authorization: Bearer $API_TOKEN" -H "Content-Type: application/json" \
  -d '{"type":"zones","items":["CANTAL","PUY-DE-DOME"]}' \
  https://votre-domaine/api/database/dim.php

# Upsert faits (mode test)
curl -X POST -H "Authorization: Bearer $API_TOKEN" -H "Content-Type: application/json" \
  -d '{
        "table":"fact_nuitees",
        "rows":[{"date":"2025-08-01","nom_zone":"CANTAL","nom_provenance":"FRANCE","nom_categorie":"TOURISTE","volume":1234}],
        "options":{"test_mode":true}
      }' \
  https://votre-domaine/api/database/facts_upsert.php
```

## Bonnes pratiques
- Utiliser des tokens distincts pour les usages ETL et les acces back-office.
- Activer `API_AUDIT_LOG` pour tracer les appels critiques.
- Limiter les origines CORS au domaine officiel via `.htaccess`.
- Tester les evolutions en mode `_test` avant d'impacter les tables de production.
- Nettoyer regulierement les caches (`cache/tableau_bord/`) pour garantir la fraicheur des datas.

