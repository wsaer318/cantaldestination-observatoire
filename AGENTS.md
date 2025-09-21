# AGENTS.md — Configuration de l’agent Codex (Autonomie Totale)

## 1) Objectif & Champ d’action

**But :** permettre à *Codex* de réaliser des tâches de développement **de bout en bout** sans solliciter d’approbation humaine : lire/comprendre le dépôt, planifier, modifier le code, lancer les tests/ETL/scripts, diagnostiquer, corriger, commiter et publier des artefacts.

**Périmètre :**

* Code PHP (PSR-12), JS/TS (ESM), Python outillage, SQL migrations, assets front.
* Outils CLI du dépôt (`tools.php`, scripts Python/Node, ETL).
* Intégration Git (branche de travail + PR automatisée).
* Exécution locale *ou* sandbox cloud de Codex (équivalentes côté agent). ([OpenAI][2])

---

## 2) Mode d’approbation & modèle

* **mode\_approbation:** `AGENT`
* **interactions\_humaines:** `none` (aucune confirmation requise)
* **modèle\_recommandé:** `gpt-5-codex` (raisonnement agentique, par défaut dans CLI/IDE)
* **fallback:** `gpt-5` si indisponible (mêmes outils, raisonner moins longtemps si simple). ([OpenAI Développeurs][1])

---

## 3) Boucle d’agent & stratégie

* **Planifier → Agir → Observer → Itérer** (ReAct) avec mémoire de session.
* **max\_iterations:** 50 (soft), **hard\_timeout:** 45 min par tâche.
* **parallelism:** jusqu’à 3 sous-tâches concurrentes (ex : build, tests, lint).
* **auto-decomposition:** l’agent segmente un objectif en sous-objectifs, assigne outils et vérifie la complétude (tests/diagnostics).
* **critères d’arrêt:** tous les checks verts **ou** blocage non résolu après 3 tentatives différentes. ([OpenAI Platform][3])

---

## 4) Outils & commandes autorisées

### 4.1 Registre d’outils

* **Fichiers:** lecture/écriture/renommage dans le dépôt (hors `.env` en écriture).
* **Git:** `git switch -c`, `git add -A`, `git commit -m`, `git push` (clé déjà configurée).
* **PHP:** `php -l`, `php -S localhost:8000 -t .`, `php tools.php <cmd>`.
* **Node/NPM:** `npm ci`, `npm run <script>`.
* **Python:** `python tools/<…>.py [--test|--dry-run]`.
* **MySQL/MariaDB:** *lecture seule* par défaut ; écriture **uniquement** via scripts du dépôt (migrations/ETL) avec drapeaux `--test` ou fenêtre de maintenance déclarée.
* **Système:** `ls/dir`, `cat/type`, `grep/findstr`, `curl` (domaine autorisé), `tar/zip`.

### 4.2 Liste d’autorisations (allowlist)

L’agent **peut exécuter** sans demander :

* **Build & QA :** `npm ci && npm run build`, `python tools/build_front_assets.py`, `php -l`, linters.
* **Diagnostics :** `php tools.php diagnostic *`, `php tools.php dev simple_test`, `php tools.php maintenance check_integrity`.
* **ETL/Import :** `python tools/etl/populate_facts_optimized.py [--test|--dry-run]`, `php tools.php import update_temp_tables` puis `php tools.php migration migrate_temp_to_main --test`.
* **Serveur local :** `php -S localhost:8000 -t .` (test fumigène).
* **Cache & logs :** purge `cache/` & `temp/` **seulement** via `php tools.php maintenance clear_cache`.
  *(Toutes ces commandes existent déjà dans les guides du dépôt.)*

### 4.3 Interdictions (denylist) — jamais exécuté

* Destruction/disques : `rm -rf /`, suppression hors dépôt, formatage, kill de services OS.
* Réseaux non approuvés, exfiltration de secrets, scanners.
* Écritures DB **hors** scripts de migration/ETL du dépôt.
* Modification de `.env` et clés/API.
  *(Garde-fous appliqués côté agent + sandbox Codex Cloud.)* ([OpenAI][2])

---

## 5) Sandboxing, sécurité & secrets

* **Sandbox Codex Cloud** *ou* environnement local verrouillé (pas d’accès root).
* **Réseau allowlist :** `github.com`, `npmjs.org`, `packagist.org`, `composer.github.io`, miroirs OS. Bloquer le reste par défaut.
* **Secrets :** lecture seule de `.env`; injections de secrets interdites dans commits, logs et issues.
* **Sortie contrôlée :** l’agent masque tokens/MDP dans journaux.

> Référence : Codex/Agents SDK & doc produits OpenAI (agents, modèles, CLI/IDE/Cloud). ([OpenAI Platform][3])

---

## 6) Observabilité & journaux

* **Chemins :** `logs/agent/` (exécutions), `logs/etl/`, `etl_checkpoint.json`.
* **Traçage :** chaque action consigne : *heure, commande, durée, RC, résumé stdout/stderr, fichiers touchés*.
* **Artefacts :** rapports Markdown sous `docs/agent_reports/YYYY-MM/`.

---

## 7) Politique Git & PR automatiques

* **Branche de travail :** `agent/<date>-<slug>`.
* **Conventions commit :** préfixe court (`api:`, `migration:`, `etl:`, `diagnostic:`), <60 caractères en sujet ; corps = contexte, commandes exécutées, sources de données, TODO.
* **PR :** créée automatiquement si tests/diagnostics OK, avec checklist :

  * [x] Lint/Build OK
  * [x] Diagnostics `check_status` OK
  * [x] Migrations appliquées en `--test`
  * [x] Screenshots si UI

---

## 8) Qualité & tests (zéro clic)

* **Avant merge :**

  1. `php -l` sur fichiers PHP modifiés
  2. `npm run build` (ou `start` pour `saisons_data.php`)
  3. `python tools/etl/populate_facts_optimized.py --dry-run` (si impact ETL)
  4. `php tools.php diagnostic check_status` & `check_zones`
* **Échec →** rollback des fichiers modifiés et nouveau plan.

*(Aligné avec les pratiques Agents SDK/function calling : boucles outillées et validations explicites.)* ([OpenAI Platform][3])

---

## 9) Tolérance aux pannes & reprise

* **Retries :** 3 tentatives exponentielles (1×, 2×, 4×) pour réseaux/build.
* **Replanification :** si RC≠0, proposer correctif + re-exécuter partiellement.
* **Checkpoints :** `etl_checkpoint.json` pour relance idempotente.
* **Auto-rollback :** si diagnostics échouent après modifications, restaurer état précédent (stash/checkout HEAD).

---

## 10) Limites de ressources

* **CPU max :** 80% *nproc* (concurrence=3).
* **RAM max :** 70% total (abandon si dépassement).
* **Espace disque min :** 2 Go libres requis avant ETL/build.

---

## 11) Kill-switch & modes

* **Désactivation instantanée :** créer le fichier `AGENT_DISABLE` à la racine → l’agent stoppe toute action non terminée proprement.
* **Mode *read-only* :** `AGENT_READONLY` présent → l’agent n’écrit pas (audit only).
* **Fenêtre de maintenance :** fichier `MAINTENANCE_WINDOW` pour autoriser les opérations destructrices encadrées (migrations réelles).

---

## 12) Structure du dépôt (rappel)

* `templates/`, `includes/`, `classes/`, `api/` (par feature), `static/`, `data/`, `docs/`, `tools/` (import, migration, diagnostic, etl, maintenance, saisons, dev), `logs/`, `etl_checkpoint.json`.
* Serveur : racine du repo ; helpers dans `includes/`; logique domaine `classes/`. *(Conforme aux guidelines projet.)*

---

## 13) Configuration déclarative (exemple YAML)

```yaml
agent:
  name: codex-autonomous
  approval_mode: AGENT
  human_confirmations: none
  model: gpt-5-codex   # fallback: gpt-5
  max_iterations: 50
  hard_timeout_minutes: 45
  parallel_tasks: 3
  stop_on:
    - all_checks_green
    - blocked_after_3_distinct_strategies
safety:
  sandbox: true
  network_allowlist:
    - github.com
    - npmjs.org
    - packagist.org
  secrets:
    redact_in_logs: true
  denylist_commands:
    - "rm -rf /"
    - "sudo *"
    - "shutdown *"
    - "drop database *"
fs:
  readwrite_globs:
    - "**/*"
  readonly_globs:
    - ".env"
git:
  branch_pattern: "agent/{date}-{slug}"
  pr_auto_create: true
  commit_convention: "{scope}: {message}"
qa:
  commands:
    - "php -l $(git diff --name-only -- '*.php')"
    - "npm ci && npm run build"
    - "php tools.php diagnostic check_status"
    - "python tools/etl/populate_facts_optimized.py --dry-run"
etl:
  checkpoint_file: "etl_checkpoint.json"
  logs_dir: "logs/etl"
maintenance:
  clear_cache_cmd: "php tools.php maintenance clear_cache"
kill_switch:
  disable_file: "AGENT_DISABLE"
  readonly_file: "AGENT_READONLY"
```

---

## 14) Prompt système *Codex* (coller tel quel)

> **Rôle :** Tu es un agent Codex autonome.
> **Objectif :** Résoudre la tâche demandée en modifiant le dépôt, en exécutant les commandes autorisées et en vérifiant les résultats (tests/diagnostics) sans demander d’approbation.
> **Contraintes :**
>
> * Respecte strictement l’allowlist/delist de `AGENTS.md`.
> * Utilise la boucle *Planifier → Agir → Observer → Itérer*.
> * Avant de pousser du code : lints, build, diagnostics, dry-run ETL si pertinent.
> * N’expose jamais de secrets, nettoie les logs.
> * Si bloqué 3 fois, reformule la stratégie et crée un rapport dans `docs/agent_reports/`.
>   **Sorties attendues :** commits atomiques bien nommés, PR auto avec checklist et trace des commandes exécutées.

---

## 15) Procédures type (zéro clic)

### 15.1 Correction API + rebuild

1. Lire logs d’erreur → ajuster `api/*` & tests légers.
2. `php -l`, `npm ci && npm run build`.
3. `php tools.php diagnostic check_status`.
4. Commit `api:` + PR auto.

### 15.2 ETL lean de production (sécurisé)

1. `php tools.php import update_temp_tables`.
2. `php tools.php migration migrate_temp_to_main --test`.
3. `python tools/etl/populate_facts_optimized.py --dry-run`.
4. Si OK **et** fenêtre maintenance ouverte → exécution réelle.
5. Rapporter résultats + checkpoints.

---

## 16) Conformité & références

* **Codex** (agent coding : CLI, IDE extension, Cloud) — *OpenAI* ; choix du modèle `gpt-5-codex`, continuité CLI/IDE/cloud. ([OpenAI][2])
* **Agents SDK & function calling** — boucles agentiques, outils, validations et handoffs. ([OpenAI Platform][3])
* **Mises à jour Codex** — performances, collaboration temps réel, autonomie renforcée. ([OpenAI][4])

---

[1]: https://developers.openai.com/codex/?utm_source=chatgpt.com "Codex"
[2]: https://openai.com/codex/?utm_source=chatgpt.com "Codex"
[3]: https://platform.openai.com/docs/guides/agents-sdk?utm_source=chatgpt.com "OpenAI Agents SDK"
[4]: https://openai.com/index/introducing-upgrades-to-codex/?utm_source=chatgpt.com "Introducing upgrades to Codex"
