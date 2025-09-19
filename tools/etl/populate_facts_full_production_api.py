#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
populate_facts_full_production_api.py

ETL Fluxvision — API ONLY
- Lit des CSV avec polars
- Upsert des dimensions via POST /api/database/dim.php
- (Optionnel) Initialise/valide le schéma via POST /api/database/schema_ensure.php (si exposé, token admin)
- Upsert des facts via POST /api/database/facts/upsert
- Mode test → écrit dans les tables *_test côté serveur (options.test_mode=true)
- Corrige le remplissage des colonnes nom_region / nom_nouvelle_region pour dim_departements(_test)

Dépendances :
    pip install polars requests urllib3 python-dotenv

Variables d'environnement (facultatif, sinon arguments/inputs) :
    API_BASE_URL              ex: https://ton-domaine.tld
    ETL_API_TOKEN             jeton (Bearer) autorisé pour /api/database/*
    ETL_ADMIN_API_TOKEN       jeton admin pour /api/database/schema_ensure.php (optionnel)
    ETL_DATA_PATH             dossier des CSV (par défaut fluxvision_automation/data/data_extracted)
    ETL_BATCH_SIZE            taille des lots d’upsert facts (défaut 2000)
    ETL_STRIP_ACCENTS         0/1 — normalisation légère (défaut 0)

Exemples :
    python populate_facts_full_production_api.py --mode test
    python populate_facts_full_production_api.py --mode prod --base-url http://localhost/fluxvision --token tok_admin_1
"""

import os
import re
import sys
import json
import time
import argparse
import logging
from pathlib import Path
from typing import Optional, Tuple, List, Dict, Iterable
from collections import defaultdict

import polars as pl
import requests
from urllib3.util.retry import Retry
from requests.adapters import HTTPAdapter


# =========================
# Logging
# =========================

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s",
    handlers=[
        logging.FileHandler("etl_fluxvision_api.log", encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger("etl_api")


# =========================
# Utils
# =========================

def normalize_str_light(s: object, strip_accents: bool = False) -> Optional[str]:
    if s is None:
        return None
    s = str(s).strip()
    if not s:
        return None
    s = " ".join(s.split())
    if strip_accents:
        import unicodedata
        s = "".join(
            c for c in unicodedata.normalize("NFKD", s) if unicodedata.category(c) != "Mn"
        )
    return s.upper()


def parse_date_string(date_str: object) -> Optional[str]:
    """Retourne 'YYYY-MM-DD' si plausible, sinon None (ne lève pas)."""
    if not date_str:
        return None
    s = str(date_str).strip()
    if re.match(r"^\d{4}-\d{2}-\d{2}$", s):
        return s
    return None


def normalize_jour_semaine(s: object) -> str:
    v = normalize_str_light(s) or ""
    mapping = {
        "LUNDI": "LUNDI", "LUN": "LUNDI",
        "MARDI": "MARDI", "MAR": "MARDI",
        "MERCREDI": "MERCREDI", "MER": "MERCREDI",
        "JEUDI": "JEUDI", "JEU": "JEUDI",
        "VENDREDI": "VENDREDI", "VEN": "VENDREDI",
        "SAMEDI": "SAMEDI", "SAM": "SAMEDI",
        "DIMANCHE": "DIMANCHE", "DIM": "DIMANCHE",
    }
    return mapping.get(v, v)


# =========================
# Constants (fichiers Lieu*)
# =========================

ALLOWED_LIEU_PREFIXES = {
    "LieuActivite_Soir", "LieuActivite_Soir_Departement", "LieuActivite_Soir_Pays",
    "LieuActivite_Veille", "LieuActivite_Veille_Departement", "LieuActivite_Veille_Pays",
    "LieuNuitee_Soir", "LieuNuitee_Soir_Departement", "LieuNuitee_Soir_Pays",
    "LieuNuitee_Veille", "LieuNuitee_Veille_Departement", "LieuNuitee_Veille_Pays",
}

EPCI_COLS = (
    "EPCIZoneNuiteeSoir", "EPCIZoneDiurneSoir", "EPCIZoneNuiteeVeille",
    "EPCIZoneDiurneVeille", "EPCI", "NomEPCI"
)
INSEE_COLS = (
    "CodeInseeNuiteeSoir", "CodeInseeDiurneSoir", "CodeInseeNuiteeVeille",
    "CodeInseeDiurneVeille", "CodeInsee", "CodeINSEE"
)
DEPT_FALLBACK_COLS = (
    "NomDepartement", "DeptZoneDiurneSoir", "DeptZoneNuiteeSoir",
    "DeptZoneDiurneVeille", "DeptZoneNuiteeVeille", "Departement"
)


# =========================
# Client API
# =========================

class CantalApi:
    def __init__(self, base_url: str, token: str, admin_token: Optional[str] = None, test_mode: bool = False, timeout=60):
        self.base = base_url.rstrip("/")
        self.default_token = token
        self.admin_token = admin_token
        self.test_mode = bool(test_mode)
        self.timeout = timeout

        self.s = requests.Session()
        self._set_auth_header(self.default_token)
        self.s.headers.update({
            "Content-Type": "application/json",
            "Accept": "application/json",
            "Accept-Encoding": "gzip, deflate",
        })
        retry = Retry(
            total=6, connect=3, read=3, backoff_factor=0.5,
            status_forcelist=[429, 500, 502, 503, 504],
            allowed_methods=["GET", "POST"]
        )
        adapter = HTTPAdapter(max_retries=retry, pool_maxsize=8)
        self.s.mount("https://", adapter)
        self.s.mount("http://", adapter)

    def _set_auth_header(self, token: str):
        self.s.headers["Authorization"] = f"Bearer {token}"

    def _post(self, path: str, payload: dict, *, use_admin: bool = False) -> dict:
        url = f"{self.base}{path}"
        if use_admin and self.admin_token:
            self._set_auth_header(self.admin_token)
        else:
            self._set_auth_header(self.default_token)

        attempts = 0
        while True:
            attempts += 1
            r = self.s.post(url, data=json.dumps(payload).encode("utf-8"), timeout=self.timeout)
            if r.status_code == 429:
                ra = r.headers.get("Retry-After")
                wait_s = float(ra) if ra and re.match(r"^\d+(\.\d+)?$", ra) else min(30.0, 1.5 * attempts)
                logger.warning("Rate-limited (429). Sleep %.2fs (attempt %d).", wait_s, attempts)
                time.sleep(wait_s)
                if attempts < 6:
                    continue
            if r.status_code >= 400:
                raise RuntimeError(f"API {path} {r.status_code}: {r.text[:500]}")
            try:
                return r.json()
            except Exception:
                raise RuntimeError(f"API {path} - invalid JSON response")

    # --------- Schema ensure (optionnel) ----------
    def ensure_schema_if_available(self, *, test_mode: bool):
        """
        Utilise /api/database/schema_ensure.php si présent (API_SCHEMA_ENABLE=1 côté serveur).
        - Crée/valide schéma + tables *_test si test_mode=True.
        - Utilise le token admin si fourni.
        """
        path = "/api/database/schema_ensure.php"
        payload = {"preset": "all", "test_mode": bool(test_mode)}
        try:
            logger.info("Tentative de création/validation du schéma via %s ...", path)
            resp = self._post(path, payload, use_admin=True)
            if resp.get("success"):
                logger.info("Schéma OK via API.")
            else:
                logger.warning("schema_ensure sans success: %s", resp)
        except RuntimeError as e:
            msg = str(e)
            if "404" in msg or "Not Found" in msg:
                logger.warning("Endpoint %s non disponible (404). On continue.", path)
            else:
                logger.warning("schema_ensure indisponible: %s (on continue).", msg)

    # --------- DIM simples (chaînes) ----------
    def dim_upsert_simple(self, dim_type: str, labels: Iterable[str]) -> Dict[str, int]:
        items = []
        seen = set()
        for x in labels:
            v = normalize_str_light(x)
            if not v or v in seen:
                continue
            items.append(v)
            seen.add(v)
        if not items:
            return {}
        payload = {"type": dim_type, "items": items, "options": {"test_mode": self.test_mode}}
        resp = self._post("/api/database/dim.php", payload)
        return resp.get("mapped", {})

    # --------- DIM communes (objets) ----------
    def dim_upsert_communes(self, rows: List[dict]) -> Dict[str, int]:
        clean = []
        seen = set()
        for r in rows:
            code = normalize_str_light(r.get("code_insee"))
            if not code or code in seen:
                continue
            clean.append({
                "code_insee": code,
                "nom_commune": normalize_str_light(r.get("nom_commune")) or "",
                "nom_departement": normalize_str_light(r.get("nom_departement")) or None,
            })
            seen.add(code)
        if not clean:
            return {}
        payload = {"type": "communes", "items": clean, "options": {"test_mode": self.test_mode}}
        resp = self._post("/api/database/dim.php", payload)
        return resp.get("mapped", {})

    # --------- DIM durées (objets) ----------
    def dim_upsert_durees(self, rows: List[dict]) -> Dict[str, int]:
        clean = []
        seen = set()
        for r in rows:
            lib = normalize_str_light(r.get("libelle"))
            if not lib or lib in seen:
                continue
            nb = r.get("nb_nuits")
            try:
                nb = int(nb) if nb not in (None, "") else None
            except Exception:
                nb = None
            clean.append({"libelle": lib, "nb_nuits": nb})
            seen.add(lib)
        if not clean:
            return {}
        payload = {"type": "durees", "items": clean, "options": {"test_mode": self.test_mode}}
        resp = self._post("/api/database/dim.php", payload)
        return resp.get("mapped", {})

    # --------- DIM dates (objets simples) ----------
    def dim_upsert_dates(self, date_strings: Iterable[str]) -> None:
        items = sorted({d for d in date_strings if d and re.match(r"^\d{4}-\d{2}-\d{2}$", d)})
        if not items:
            return
        payload = {"type": "dates", "items": items, "options": {"test_mode": self.test_mode}}
        try:
            self._post("/api/database/dim.php", payload)
        except RuntimeError as e:
            logger.warning("Upsert dim_dates ignoré: %s", e)

    # --------- DIM départements avec régions (objets) ----------
    def dim_upsert_departements(self, rows: List[dict]) -> Dict[str, int]:
        clean, seen = [], set()
        for r in rows:
            nd = normalize_str_light(r.get("nom_departement"))
            if not nd or nd in seen:
                continue
            clean.append({
                "nom_departement": nd,
                "nom_region": normalize_str_light(r.get("nom_region")) or None,
                "nom_nouvelle_region": normalize_str_light(r.get("nom_nouvelle_region")) or None,
            })
            seen.add(nd)
        if not clean:
            return {}
        payload = {"type": "departements", "items": clean, "options": {"test_mode": self.test_mode}}
        resp = self._post("/api/database/dim.php", payload)
        return resp.get("mapped", {})

    # --------- FACTS ----------
    def _map_table_from_subtype(self, subtype: str) -> str:
        m = {
            # historiques
            "Nuitee": "fact_nuitees",
            "Diurne": "fact_diurnes",
            "Nuitee_Departement": "fact_nuitees_departements",
            "Diurne_Departement": "fact_diurnes_departements",
            "Nuitee_Pays": "fact_nuitees_pays",
            "Diurne_Pays": "fact_diurnes_pays",
            "SejourDuree": "fact_sejours_duree",
            "SejourDuree_Departement": "fact_sejours_duree_departements",
            "SejourDuree_Pays": "fact_sejours_duree_pays",
            # Lieu*
            "LieuActivite_Soir": "fact_lieu_activite_soir",
            "LieuActivite_Soir_Departement": "fact_lieu_activite_soir_departement",
            "LieuActivite_Soir_Pays": "fact_lieu_activite_soir_pays",
            "LieuActivite_Veille": "fact_lieu_activite_veille",
            "LieuActivite_Veille_Departement": "fact_lieu_activite_veille_departement",
            "LieuActivite_Veille_Pays": "fact_lieu_activite_veille_pays",
            "LieuNuitee_Soir": "fact_lieu_nuitee_soir",
            "LieuNuitee_Soir_Departement": "fact_lieu_nuitee_soir_departement",
            "LieuNuitee_Soir_Pays": "fact_lieu_nuitee_soir_pays",
            "LieuNuitee_Veille": "fact_lieu_nuitee_veille",
            "LieuNuitee_Veille_Departement": "fact_lieu_nuitee_veille_departement",
            "LieuNuitee_Veille_Pays": "fact_lieu_nuitee_veille_pays",
        }
        t = m.get(subtype)
        if not t:
            raise ValueError(f"Subtype inconnu: {subtype}")
        return t

    def facts_upsert(self, subtype: str, rows: list[dict], *, batch_size: int = 2000) -> int:
        """Upsert des facts via /api/database/facts_upsert.php (table=…, rows=…, options.test_mode)."""
        if not rows:
            return 0
        table = self._map_table_from_subtype(subtype)
        total_processed = 0
        for i in range(0, len(rows), batch_size):
            chunk = rows[i:i + batch_size]
            payload = {
                "table": table,
                "rows": chunk,
                "options": {"test_mode": self.test_mode}
            }
            # NOTE: endpoint PHP = facts_upsert.php (et non /facts/upsert)
            resp = self._post("/api/database/facts_upsert.php", payload)
            counts = resp.get("counts") or {}
            total_processed += int(counts.get("processed", 0))
        return total_processed


# =========================
# ETL API-Only
# =========================

class ApiOnlyETL:
    def __init__(self,
                 base_url: str,
                 token: str,
                 admin_token: Optional[str],
                 data_path: Path,
                 test_mode: bool = False,
                 batch_size: int = 2000,
                 strip_accents: bool = False):
        self.api = CantalApi(base_url=base_url, token=token, admin_token=admin_token, test_mode=test_mode, timeout=90)
        self.data_path = Path(data_path)
        self.test_mode = bool(test_mode)
        self.batch_size = int(batch_size)
        self.strip_accents = bool(strip_accents)

        # stats
        self.stats = defaultdict(int)

        # caches mappings libellé -> id (retournés par l'API)
        self.map_zone: Dict[str, int] = {}
        self.map_prov: Dict[str, int] = {}
        self.map_cat: Dict[str, int] = {}
        self.map_dep: Dict[str, int] = {}
        self.map_pays: Dict[str, int] = {}
        self.map_epci: Dict[str, int] = {}
        self.map_commune_by_insee: Dict[str, int] = {}
        self.map_duree: Dict[str, int] = {}

    # --------- Détection fichiers ----------
    @staticmethod
    def determine_file_type(filename: str) -> Optional[str]:
        base_name = filename.replace(".csv", "")
        if re.search(r"semaine", base_name, re.IGNORECASE):
            return None

        if re.match(r"^SejourDuree_(20\d{2}B\d+).*$", base_name, re.IGNORECASE):
            return "SejourDuree"
        if re.match(r"^SejourDuree_Departement_(20\d{2}B\d+).*$", base_name, re.IGNORECASE):
            return "SejourDuree_Departement"
        if re.match(r"^SejourDuree_Pays_(20\d{2}B\d+).*$", base_name, re.IGNORECASE):
            return "SejourDuree_Pays"

        if re.match(r"^Nuitee_20\d{2}B\d+_[^_]+$", base_name):
            return "Nuitee"
        if re.match(r"^Diurne_20\d{2}B\d+_[^_]+$", base_name):
            return "Diurne"

        if re.match(r"^Nuitee_Pays_20\d{2}B\d+", base_name):
            return "Nuitee_Pays"
        if re.match(r"^Diurne_Pays_20\d{2}B\d+", base_name):
            return "Diurne_Pays"

        if re.match(r"^Nuitee_Departement_20\d{2}B\d+", base_name, re.IGNORECASE):
            return "Nuitee_Departement"
        if re.match(r"^Diurne_Departement_20\d{2}B\d+", base_name, re.IGNORECASE):
            return "Diurne_Departement"

        # Ignorer fichiers régions
        if re.match(r"^Nuitee_Regions_20\d{2}B\d+", base_name):
            return None
        if re.match(r"^Diurne_Regions_20\d{2}B\d+", base_name):
            return None

        return None

    @staticmethod
    def determine_lieu_file_type_strict(filename: str) -> Optional[str]:
        if re.search(r"semaine", filename, re.IGNORECASE):
            return None
        base = filename[:-4] if filename.lower().endswith(".csv") else filename
        parts = re.split(r"_(?=20\d{2}B)", base)
        if len(parts) < 2:
            return None
        prefix = parts[0]
        return prefix if prefix in ALLOWED_LIEU_PREFIXES else None

    # --------- Lecture CSV minimaliste ----------
    @staticmethod
    def _read_csv_useful(csv_file: Path, use_cols: set) -> Optional[pl.DataFrame]:
        df = pl.read_csv(csv_file, separator=";", infer_schema_length=300)
        cols = [c for c in df.columns if c in use_cols]
        if not cols:
            return None
        df = df.select(cols)
        if "Volume" in df.columns:
            df = df.with_columns(pl.col("Volume").cast(pl.Int32, strict=False))
        return df

    # --------- Pré-scan pour construire les dims ----------
    def prescan_collect_dims(self, files_hist: Dict[str, List[Path]], files_lieu: Dict[str, List[Path]]) -> dict:
        dims = {
            "zones": set(),
            "provs": set(),
            "cats": set(),
            "deps": set(),
            "pays": set(),
            "epcis": set(),
            "communes": {},   # code_insee -> {"nom_commune":..., "nom_departement":...}
            "durees": {},     # libelle -> nb_nuits (ou None)
            "dates": set(),
            "deps_meta": {},  # dep -> {nom_region, nom_nouvelle_region}
        }

        use_cols_hist = {
            "Date", "ZoneObservation", "Zone", "Provenance", "CategorieVisiteur", "Volume",
            "DureeSejour", "DureeSejourNum", "NomDepartement", "Pays",
            "VacancesA", "VacancesB", "VacancesC", "Ferie", "JourDeLaSemaine",
            "vacances_a", "vacances_b", "vacances_c", "ferie", "jour_semaine",
            "NomRegion", "NomNouvelleRegion"
        }
        use_cols_lieu = {
            "Date", "VacancesA", "VacancesB", "VacancesC", "Ferie", "JourDeLaSemaine",
            "vacances_a", "vacances_b", "vacances_c", "ferie", "jour_semaine",
            "Provenance", "ZoneObservation", "Zone", "CategorieVisiteur", "Volume",
            "NomDepartement", "Departement", "Pays",
            "DeptZoneDiurneSoir", "DeptZoneNuiteeSoir", "DeptZoneDiurneVeille", "DeptZoneNuiteeVeille",
            "EPCIZoneNuiteeSoir", "EPCIZoneDiurneSoir", "EPCIZoneNuiteeVeille", "EPCIZoneDiurneVeille", "EPCI", "NomEPCI",
            "CodeInseeNuiteeSoir", "CodeInseeDiurneSoir", "CodeInseeNuiteeVeille", "CodeInseeDiurneVeille", "CodeInsee", "CodeINSEE",
            "NomRegion", "NomNouvelleRegion"
        }

        # Historiques
        for subtype, files in files_hist.items():
            for p in files:
                try:
                    df = self._read_csv_useful(p, use_cols_hist)
                    if df is None or df.height == 0:
                        continue

                    if "ZoneObservation" in df.columns:
                        dims["zones"].update([normalize_str_light(x, self.strip_accents) for x in df["ZoneObservation"].to_list()])
                    if "Provenance" in df.columns:
                        dims["provs"].update([normalize_str_light(x, self.strip_accents) for x in df["Provenance"].to_list()])
                    if "CategorieVisiteur" in df.columns:
                        dims["cats"].update([normalize_str_light(x, self.strip_accents) for x in df["CategorieVisiteur"].to_list()])
                    if "NomDepartement" in df.columns:
                        dims["deps"].update([normalize_str_light(x, self.strip_accents) for x in df["NomDepartement"].to_list()])
                    if "Pays" in df.columns:
                        dims["pays"].update([normalize_str_light(x, self.strip_accents) for x in df["Pays"].to_list()])

                    # Métadonnées de département (regions)
                    if "NomDepartement" in df.columns and ("NomRegion" in df.columns or "NomNouvelleRegion" in df.columns):
                        deps = [normalize_str_light(x, self.strip_accents) for x in df["NomDepartement"].to_list()]
                        regs = [normalize_str_light(x, self.strip_accents) for x in (df["NomRegion"].to_list() if "NomRegion" in df.columns else [None]*len(deps))]
                        nregs = [normalize_str_light(x, self.strip_accents) for x in (df["NomNouvelleRegion"].to_list() if "NomNouvelleRegion" in df.columns else [None]*len(deps))]
                        for d, r, nr in zip(deps, regs, nregs):
                            if not d:
                                continue
                            meta = dims["deps_meta"].setdefault(d, {"nom_region": None, "nom_nouvelle_region": None})
                            if r and not meta["nom_region"]:
                                meta["nom_region"] = r
                            if nr and not meta["nom_nouvelle_region"]:
                                meta["nom_nouvelle_region"] = nr

                    if "DureeSejour" in df.columns:
                        lib = [normalize_str_light(x, self.strip_accents) for x in df["DureeSejour"].to_list()]
                        nb  = df["DureeSejourNum"].to_list() if "DureeSejourNum" in df.columns else [None] * len(lib)
                        for L, N in zip(lib, nb):
                            if not L:
                                continue
                            if L not in dims["durees"] or (dims["durees"][L] is None and N not in (None, "")):
                                try:
                                    dims["durees"][L] = int(N) if N not in (None, "") else None
                                except Exception:
                                    dims["durees"][L] = None

                    if "Date" in df.columns:
                        dims["dates"].update([d for d in df["Date"].to_list() if parse_date_string(d)])
                except Exception as e:
                    logger.warning("prescan(hist) %s: %s", p.name, e)

        # Lieu*
        for subtype, files in files_lieu.items():
            for p in files:
                try:
                    df = self._read_csv_useful(p, use_cols_lieu)
                    if df is None or df.height == 0:
                        continue

                    if "ZoneObservation" in df.columns:
                        dims["zones"].update([normalize_str_light(x, self.strip_accents) for x in df["ZoneObservation"].to_list()])
                    if "Provenance" in df.columns:
                        dims["provs"].update([normalize_str_light(x, self.strip_accents) for x in df["Provenance"].to_list()])
                    if "CategorieVisiteur" in df.columns:
                        dims["cats"].update([normalize_str_light(x, self.strip_accents) for x in df["CategorieVisiteur"].to_list()])

                    # deps (plusieurs colonnes de fallback)
                    for col in DEPT_FALLBACK_COLS:
                        if col in df.columns:
                            dims["deps"].update([normalize_str_light(x, self.strip_accents) for x in df[col].to_list()])

                    # métadonnées de région si présentes dans ces fichiers (rare)
                    if "NomDepartement" in df.columns and ("NomRegion" in df.columns or "NomNouvelleRegion" in df.columns):
                        deps = [normalize_str_light(x, self.strip_accents) for x in df["NomDepartement"].to_list()]
                        regs = [normalize_str_light(x, self.strip_accents) for x in (df["NomRegion"].to_list() if "NomRegion" in df.columns else [None]*len(deps))]
                        nregs = [normalize_str_light(x, self.strip_accents) for x in (df["NomNouvelleRegion"].to_list() if "NomNouvelleRegion" in df.columns else [None]*len(deps))]
                        for d, r, nr in zip(deps, regs, nregs):
                            if not d:
                                continue
                            meta = dims["deps_meta"].setdefault(d, {"nom_region": None, "nom_nouvelle_region": None})
                            if r and not meta["nom_region"]:
                                meta["nom_region"] = r
                            if nr and not meta["nom_nouvelle_region"]:
                                meta["nom_nouvelle_region"] = nr

                    # pays
                    if "Pays" in df.columns:
                        dims["pays"].update([normalize_str_light(x, self.strip_accents) for x in df["Pays"].to_list()])

                    # epci
                    for col in EPCI_COLS:
                        if col in df.columns:
                            dims["epcis"].update([normalize_str_light(x, self.strip_accents) for x in df[col].to_list()])

                    # communes (via code INSEE + 1er département dispo)
                    code_series = None
                    for col in INSEE_COLS:
                        if col in df.columns:
                            code_series = df[col]
                            break
                    dep_col = None
                    for col in DEPT_FALLBACK_COLS:
                        if col in df.columns:
                            dep_col = df[col]
                            break
                    if code_series is not None:
                        codes = [normalize_str_light(x, self.strip_accents) for x in code_series.to_list()]
                        deps = [normalize_str_light(x, self.strip_accents) for x in (dep_col.to_list() if dep_col is not None else [None]*len(codes))]
                        for code, dep in zip(codes, deps):
                            if not code:
                                continue
                            if code not in dims["communes"]:
                                dims["communes"][code] = {"nom_commune": None, "nom_departement": dep}
                            else:
                                if not dims["communes"][code]["nom_departement"] and dep:
                                    dims["communes"][code]["nom_departement"] = dep

                    # dates
                    if "Date" in df.columns:
                        dims["dates"].update([d for d in df["Date"].to_list() if parse_date_string(d)])
                except Exception as e:
                    logger.warning("prescan(lieu) %s: %s", p.name, e)

        # Nettoyage sets None
        for k in ["zones", "provs", "cats", "deps", "pays", "epcis"]:
            dims[k] = {x for x in dims[k] if x}

        logger.info(
            "Pré-scan dims → zones=%d, provs=%d, cats=%d, deps=%d, pays=%d, epcis=%d, communes=%d, durees=%d, dates=%d",
            len(dims["zones"]), len(dims["provs"]), len(dims["cats"]), len(dims["deps"]), len(dims["pays"]),
            len(dims["epcis"]), len(dims["communes"]), len(dims["durees"]), len(dims["dates"])
        )
        return dims

    # --------- Build mappings via API ----------
    def build_dimension_mappings(self, dims: dict):
        if dims["zones"]:
            self.map_zone = self.api.dim_upsert_simple("zones", dims["zones"])
        if dims["provs"]:
            self.map_prov = self.api.dim_upsert_simple("provenances", dims["provs"])
        if dims["cats"]:
            self.map_cat = self.api.dim_upsert_simple("categories", dims["cats"])

        # Départements : envoyer d'abord avec régions si dispo
        if dims.get("deps_meta"):
            rows = [
                {
                    "nom_departement": dep,
                    "nom_region": meta.get("nom_region"),
                    "nom_nouvelle_region": meta.get("nom_nouvelle_region"),
                }
                for dep, meta in dims["deps_meta"].items()
            ]
            self.map_dep = self.api.dim_upsert_departements(rows)
        # Compléter les départements restants (sans meta)
        restants = set(dims.get("deps", set())) - set(self.map_dep.keys())
        if restants:
            self.map_dep.update(self.api.dim_upsert_simple("departements", restants))

        if dims["pays"]:
            self.map_pays = self.api.dim_upsert_simple("pays", dims["pays"])
        if dims["epcis"]:
            self.map_epci = self.api.dim_upsert_simple("epci", dims["epcis"])
        if dims["communes"]:
            rows = [{"code_insee": k, "nom_commune": v.get("nom_commune"), "nom_departement": v.get("nom_departement")} for k, v in dims["communes"].items()]
            self.map_commune_by_insee = self.api.dim_upsert_communes(rows)
        if dims["durees"]:
            rows = [{"libelle": k, "nb_nuits": v} for k, v in dims["durees"].items()]
            self.map_duree = self.api.dim_upsert_durees(rows)
        if dims["dates"]:
            self.api.dim_upsert_dates(dims["dates"])

        logger.info(
            "Mappings chargés: zones=%d, provs=%d, cats=%d, deps=%d, pays=%d, epci=%d, communes=%d, durees=%d",
            len(self.map_zone), len(self.map_prov), len(self.map_cat), len(self.map_dep), len(self.map_pays),
            len(self.map_epci), len(self.map_commune_by_insee), len(self.map_duree)
        )

    # --------- Helpers mapping ----------
    def _id_from(self, mapping: Dict[str, int], label: object, *, allow_zero=False) -> Optional[int]:
        k = normalize_str_light(label, self.strip_accents)
        if not k:
            return 0 if allow_zero else None
        v = mapping.get(k)
        return (v if v is not None else (0 if allow_zero else None))

    def _pick_departement_name(self, row: dict) -> Optional[str]:
        for col in DEPT_FALLBACK_COLS:
            v = row.get(col)
            if v and str(v).strip():
                return v
        return None

    def _pick_epci_name(self, row: dict) -> Optional[str]:
        for col in EPCI_COLS:
            v = row.get(col)
            if v and str(v).strip():
                return v
        return None

    def _pick_insee(self, row: dict) -> Optional[str]:
        for col in INSEE_COLS:
            v = row.get(col)
            if v and str(v).strip():
                return v
        return None

    # --------- Construction des lignes de facts ----------
    def make_fact_tuple_hist(self, row: dict, subtype: str) -> Optional[dict]:
        date_str = parse_date_string(row.get("Date"))
        if not date_str:
            return None

        id_zone = self._id_from(self.map_zone, row.get("ZoneObservation"))
        id_prov = self._id_from(self.map_prov, row.get("Provenance"))
        id_cat  = self._id_from(self.map_cat,  row.get("CategorieVisiteur"))
        if not all([id_zone, id_prov, id_cat]):
            return None

        vol = row.get("Volume", 0)
        try:
            vol = int(vol) if vol is not None else 0
        except Exception:
            vol = 0
        if vol <= 0:
            return None

        if subtype == "SejourDuree":
            lib = row.get("DureeSejour")
            id_duree = self._id_from(self.map_duree, lib)
            if not id_duree:
                return None
            return {"date": date_str, "id_zone": id_zone, "id_provenance": id_prov, "id_categorie": id_cat, "id_duree": id_duree, "volume": vol}

        if subtype == "SejourDuree_Departement":
            dep_name = row.get("NomDepartement")
            id_dep = self._id_from(self.map_dep, dep_name)
            if not id_dep:
                return None
            lib = row.get("DureeSejour")
            id_duree = self._id_from(self.map_duree, lib)
            if not id_duree:
                return None
            return {"date": date_str, "id_zone": id_zone, "id_provenance": id_prov, "id_categorie": id_cat, "id_departement": id_dep, "id_duree": id_duree, "volume": vol}

        if subtype == "SejourDuree_Pays":
            id_pays = self._id_from(self.map_pays, row.get("Pays"))
            if not id_pays:
                return None
            lib = row.get("DureeSejour")
            id_duree = self._id_from(self.map_duree, lib)
            if not id_duree:
                return None
            return {"date": date_str, "id_zone": id_zone, "id_provenance": id_prov, "id_categorie": id_cat, "id_pays": id_pays, "id_duree": id_duree, "volume": vol}

        if subtype in ("Nuitee_Departement", "Diurne_Departement"):
            dep_name = row.get("NomDepartement")
            id_dep = self._id_from(self.map_dep, dep_name)
            if not id_dep:
                return None
            return {"date": date_str, "id_zone": id_zone, "id_provenance": id_prov, "id_categorie": id_cat, "id_departement": id_dep, "volume": vol}

        if subtype in ("Nuitee_Pays", "Diurne_Pays"):
            id_pays = self._id_from(self.map_pays, row.get("Pays"))
            if not id_pays:
                return None
            return {"date": date_str, "id_zone": id_zone, "id_provenance": id_prov, "id_categorie": id_cat, "id_pays": id_pays, "volume": vol}

        # simples: Nuitee / Diurne
        return {"date": date_str, "id_zone": id_zone, "id_provenance": id_prov, "id_categorie": id_cat, "volume": vol}

    def make_fact_tuple_lieu(self, row: dict, subtype: str) -> Optional[dict]:
        date_str = parse_date_string(row.get("Date"))
        if not date_str:
            return None

        id_zone = self._id_from(self.map_zone, row.get("ZoneObservation"))
        id_prov = self._id_from(self.map_prov, row.get("Provenance"))
        id_cat  = self._id_from(self.map_cat,  row.get("CategorieVisiteur"))
        if not all([id_zone, id_prov, id_cat]):
            return None

        vol = row.get("Volume", 0)
        try:
            vol = int(vol) if vol is not None else 0
        except Exception:
            vol = 0
        if vol <= 0:
            return None

        js = normalize_jour_semaine(row.get("JourDeLaSemaine") or row.get("jour_semaine"))

        epci_name = self._pick_epci_name(row)
        id_epci = self._id_from(self.map_epci, epci_name, allow_zero=True) or 0

        code_insee = self._pick_insee(row)
        id_commune = 0
        if code_insee:
            code_key = normalize_str_light(code_insee, self.strip_accents)
            if code_key and code_key in self.map_commune_by_insee:
                id_commune = int(self.map_commune_by_insee[code_key])

        if subtype.endswith("_Departement"):
            dep_name = self._pick_departement_name(row)
            id_dep = self._id_from(self.map_dep, dep_name)
            if not id_dep:
                return None
            return {
                "date": date_str, "jour_semaine": js,
                "id_zone": id_zone, "id_provenance": id_prov, "id_categorie": id_cat,
                "id_departement": id_dep, "id_epci": id_epci, "id_commune": id_commune,
                "volume": vol
            }

        if subtype.endswith("_Pays"):
            id_p = self._id_from(self.map_pays, row.get("Pays"))
            if not id_p:
                return None
            return {
                "date": date_str, "jour_semaine": js,
                "id_zone": id_zone, "id_provenance": id_prov, "id_categorie": id_cat,
                "id_pays": id_p, "id_epci": id_epci, "id_commune": id_commune,
                "volume": vol
            }

        # sans dep/pays
        return {
            "date": date_str, "jour_semaine": js,
            "id_zone": id_zone, "id_provenance": id_prov, "id_categorie": id_cat,
            "id_epci": id_epci, "id_commune": id_commune, "volume": vol
        }

    # --------- Traitement d'un fichier ----------
    def process_hist_file(self, csv_file: Path, subtype: str) -> int:
        logger.info("[HIST] %s → %s", csv_file.name, subtype)
        use_cols = {
            "Date", "ZoneObservation", "Zone", "Provenance", "CategorieVisiteur", "Volume",
            "DureeSejour", "DureeSejourNum", "NomDepartement", "Pays",
            "VacancesA", "VacancesB", "VacancesC", "Ferie", "JourDeLaSemaine",
            "vacances_a", "vacances_b", "vacances_c", "ferie", "jour_semaine",
            "NomRegion", "NomNouvelleRegion"
        }
        try:
            df = self._read_csv_useful(csv_file, use_cols)
            if df is None or df.height == 0:
                return 0
            rows, total = [], 0
            for r in df.iter_rows(named=True):
                d = self.make_fact_tuple_hist(r, subtype)
                if d:
                    rows.append(d)
                if len(rows) >= self.batch_size:
                    total += self.api.facts_upsert(subtype, rows, batch_size=self.batch_size)
                    rows.clear()
            if rows:
                total += self.api.facts_upsert(subtype, rows, batch_size=self.batch_size)
            self.stats[f"files_processed_{subtype}"] += 1
            self.stats[f"rows_inserted_{subtype}"] += total
            logger.info("  -> %s lignes upsertées", f"{total:,}")
            return total
        except Exception as e:
            logger.error("Erreur HIST %s: %s", csv_file.name, e)
            return 0

    def process_lieu_file(self, csv_file: Path, subtype: str) -> int:
        logger.info("[Lieu*] %s → %s", csv_file.name, subtype)
        use_cols = {
            "Date", "VacancesA", "VacancesB", "VacancesC", "Ferie", "JourDeLaSemaine",
            "vacances_a", "vacances_b", "vacances_c", "ferie", "jour_semaine",
            "Provenance", "ZoneObservation", "Zone", "CategorieVisiteur", "Volume",
            "NomDepartement", "Departement", "Pays",
            "DeptZoneDiurneSoir", "DeptZoneNuiteeSoir", "DeptZoneDiurneVeille", "DeptZoneNuiteeVeille",
            "EPCIZoneNuiteeSoir", "EPCIZoneDiurneSoir", "EPCIZoneNuiteeVeille", "EPCIZoneDiurneVeille", "EPCI", "NomEPCI",
            "CodeInseeNuiteeSoir", "CodeInseeDiurneSoir", "CodeInseeNuiteeVeille", "CodeInseeDiurneVeille", "CodeInsee", "CodeINSEE",
            "NomRegion", "NomNouvelleRegion"
        }
        try:
            df = self._read_csv_useful(csv_file, use_cols)
            if df is None or df.height == 0:
                return 0
            rows, total = [], 0
            for r in df.iter_rows(named=True):
                d = self.make_fact_tuple_lieu(r, subtype)
                if d:
                    rows.append(d)
                if len(rows) >= self.batch_size:
                    total += self.api.facts_upsert(subtype, rows, batch_size=self.batch_size)
                    rows.clear()
            if rows:
                total += self.api.facts_upsert(subtype, rows, batch_size=self.batch_size)
            self.stats[f"files_processed_{subtype}"] += 1
            self.stats[f"rows_inserted_{subtype}"] += total
            logger.info("  -> %s lignes upsertées", f"{total:,}")
            return total
        except Exception as e:
            logger.error("Erreur Lieu* %s: %s", csv_file.name, e)
            return 0

    # --------- Orchestration ----------
    def run(self) -> bool:
        logger.info("=== DÉBUT ETL FLUXVISION — MODE %s ===", "TEST" if self.test_mode else "PROD")

        # 0) ensure schéma si possible (créera *_test si test_mode True)
        self.api.ensure_schema_if_available(test_mode=self.test_mode)

        if not self.data_path.exists():
            logger.error("Dossier introuvable: %s", self.data_path)
            return False

        # 1) répertoire → fichiers par sous-type
        files_hist = defaultdict(list)
        files_lieu = defaultdict(list)
        for csv_file in self.data_path.rglob("*.csv"):
            if re.search(r"semaine", csv_file.name, re.IGNORECASE):
                continue
            ft = self.determine_file_type(csv_file.name)
            if ft:
                files_hist[ft].append(csv_file)
                continue
            fl = self.determine_lieu_file_type_strict(csv_file.name)
            if fl:
                files_lieu[fl].append(csv_file)

        # test_mode : limitation de fichiers pour accélérer
        if self.test_mode:
            for k in list(files_hist.keys()):
                files_hist[k] = files_hist[k][:3]
            for k in list(files_lieu.keys()):
                files_lieu[k] = files_lieu[k][:10]
            logger.info("Mode test : limitation de fichiers (hist: max 3 / type, lieu: max 10 / type)")

        # 2) pré-scan pour collecter toutes les dims et dates
        dims = self.prescan_collect_dims(files_hist, files_lieu)

        # 3) upsert des dims → récup mappings (libellé → id)
        self.build_dimension_mappings(dims)

        # 4) traitement des fichiers : HIST puis LIEU
        for subtype, files in files_hist.items():
            logger.info("\n=== HIST %s (%d fichiers) ===", subtype, len(files))
            for p in files:
                self.process_hist_file(p, subtype)

        for subtype, files in files_lieu.items():
            logger.info("\n=== LIEU %s (%d fichiers) ===", subtype, len(files))
            for p in files:
                self.process_lieu_file(p, subtype)

        # 5) stats
        self.print_stats()
        logger.info("=== FIN ETL ===")
        return True

    def print_stats(self):
        total_files = sum(v for k, v in self.stats.items() if k.startswith("files_processed_"))
        total_rows = sum(v for k, v in self.stats.items() if k.startswith("rows_inserted_"))
        logger.info("=== STATISTIQUES ===")
        logger.info("Total fichiers traités: %s", f"{total_files:,}")
        logger.info("Total lignes upsertées: %s", f"{total_rows:,}")
        for k in sorted(self.stats):
            if k.startswith("files_processed_"):
                ft = k.replace("files_processed_", "")
                files = self.stats[k]
                rows = self.stats.get(f"rows_inserted_{ft}", 0)
                logger.info("  %s: %d fichiers, %s lignes", ft, files, f"{rows:,}")


# =========================
# MAIN
# =========================

def main():
    # Charge .env si dispo (optionnel)
    try:
        from dotenv import load_dotenv
        load_dotenv()
    except Exception:
        pass

    parser = argparse.ArgumentParser(description="ETL Fluxvision — API ONLY")
    parser.add_argument("--mode", choices=["test", "prod"], help="Mode d'exécution (test=*_test, prod=production)")
    parser.add_argument("--base-url", dest="base_url", help="API base URL, ex: https://ton-domaine.tld")
    parser.add_argument("--token", dest="etl_token", help="Jeton API (Bearer) pour l'ETL")
    parser.add_argument("--admin-token", dest="admin_token", help="Jeton admin pour schema_ensure (optionnel)")
    parser.add_argument("--data-path", dest="data_path", help="Dossier des CSV")
    parser.add_argument("--batch-size", dest="batch_size", type=int, help="Taille des lots (facts)")
    parser.add_argument("--strip-accents", dest="strip_accents", type=int, choices=[0, 1], help="Normalisation sans accents (0/1)")
    args, unknown = parser.parse_known_args()

    print("=== ETL FLUXVISION — API ONLY (overwrite/upsert, anti-doublon via DB) ===\n")

    # Mode
    mode_cli = args.mode or os.getenv("ETL_MODE")
    if mode_cli not in ("test", "prod"):
        # fallback interactif si non fourni
        try:
            mode_cli = input("Mode? (t=test, p=production): ").lower().strip()
        except EOFError:
            mode_cli = "p"
        mode_cli = "test" if mode_cli == "t" else ("prod" if mode_cli == "p" else "prod")
    test_mode = (mode_cli == "test")

    # Confirm prod si interactif et pas d'argument explicite
    if not test_mode and args.mode is None:
        try:
            confirm = input("PRODUCTION - Continuer? (oui/non): ").lower().strip()
        except EOFError:
            confirm = "oui"
        if confirm != "oui":
            sys.exit(0)

    # Paramètres API
    base_url = args.base_url or os.getenv("API_BASE_URL")
    if not base_url:
        base_url = input("API_BASE_URL (ex: https://votredomaine.tld): ").strip()

    etl_token = args.etl_token or os.getenv("ETL_API_TOKEN") or os.getenv("API_TOKEN")
    if not etl_token:
        etl_token = input("ETL_API_TOKEN (Bearer): ").strip()

    admin_tok = args.admin_token or os.getenv("ETL_ADMIN_API_TOKEN")  # optionnel

    data_path = Path(args.data_path or os.getenv("ETL_DATA_PATH") or "fluxvision_automation/data/data_extracted")
    batch_size = int(args.batch_size or os.getenv("ETL_BATCH_SIZE") or 2000)
    strip_acc = bool(int(args.strip_accents if args.strip_accents is not None else (os.getenv("ETL_STRIP_ACCENTS") or 0)))

    etl = ApiOnlyETL(
        base_url=base_url,
        token=etl_token,
        admin_token=admin_tok,
        data_path=data_path,
        test_mode=test_mode,
        batch_size=batch_size,
        strip_accents=strip_acc
    )

    ok = False
    try:
        ok = etl.run()
        if ok:
            print("\n🎉 ETL TERMINÉ (API-only)")
            print("✅ Dimensions upsertées (mappings id) via /api/database/dim.php")
            print("✅ Faits upsertés en lots via /api/database/facts/upsert")
            if test_mode:
                print("✅ Mode test: cibles *_test (schema_ensure si disponible)")
        else:
            print("💥 ÉCHEC ETL")
            sys.exit(1)
    except KeyboardInterrupt:
        print("⚠️ Interrompu par l'utilisateur")
        sys.exit(1)
    except Exception as e:
        logger.error("💥 Erreur critique: %s", e)
        sys.exit(1)


if __name__ == "__main__":
    main()
