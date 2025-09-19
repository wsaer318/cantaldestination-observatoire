#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
ETL FluxVision — Version vectorisée (Polars) et optimisée pour i5-1240P
- Correction: .dt.isoweek() -> .dt.week() (ISO week)
- Normalisation, mapping des dimensions et coalesce en vecteur
- Insertion dim_dates et dimensions en batch
- Agrégation avant UPSERT pour réduire les conflits/IO MySQL
"""

import os
import re
import sys
import json
import time
import psutil
import logging
import polars as pl
import mysql.connector
from datetime import datetime
from pathlib import Path
from typing import Optional, Tuple, List, Dict, Any
from collections import defaultdict
from mysql.connector.pooling import MySQLConnectionPool
from mysql.connector import Error

# =========================
# Logging
# =========================

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s | %(levelname)s | %(message)s",
    handlers=[
        logging.FileHandler("etl_fluxvision_production.log", encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger(__name__)

# =========================
# Constants & machine hints
# =========================

CPU_COUNT = 12               # i5-1240P
CONNECTION_POOL_SIZE = 10    # MySQL pool
OPTIMIZED_BATCH_SIZE = 5000  # Taille d’insertions par chunks
CHECKPOINT_FILE = "etl_checkpoint.json"

ALLOWED_LIEU_PREFIXES = {
    "LieuActivite_Soir","LieuActivite_Soir_Departement","LieuActivite_Soir_Pays",
    "LieuActivite_Veille","LieuActivite_Veille_Departement","LieuActivite_Veille_Pays",
    "LieuNuitee_Soir","LieuNuitee_Soir_Departement","LieuNuitee_Soir_Pays",
    "LieuNuitee_Veille","LieuNuitee_Veille_Departement","LieuNuitee_Veille_Pays",
}

EPCI_COLS = (
    "EPCIZoneNuiteeSoir","EPCIZoneDiurneSoir","EPCIZoneNuiteeVeille","EPCIZoneDiurneVeille","EPCI","NomEPCI"
)
INSEE_COLS = (
    "CodeInseeNuiteeSoir","CodeInseeDiurneSoir","CodeInseeNuiteeVeille","CodeInseeDiurneVeille","CodeInsee","CodeINSEE"
)
COMMUNE_COLS = (
    "ZoneNuiteeSoir","ZoneDiurneSoir","ZoneNuiteeVeille","ZoneDiurneVeille","Zone","Commune","NomCommune"
)
DEPT_FALLBACK_COLS = (
    "NomDepartement","DeptZoneDiurneSoir","DeptZoneNuiteeSoir","DeptZoneDiurneVeille","DeptZoneNuiteeVeille","Departement"
)

# =========================
# Utils
# =========================

def normalize_str_light(s: object) -> Optional[str]:
    """Upper, strip and collapse spaces (accents conservés pour rester simple/rapide)."""
    if s is None:
        return None
    s = str(s).strip()
    if not s:
        return None
    return " ".join(s.split()).upper()

def _normalize_expr(col: str) -> pl.Expr:
    """Expr Polars pour normaliser une colonne (trim + collapse spaces + upper)."""
    return (
        pl.col(col)
        .cast(pl.String, strict=False)
        .str.strip_chars()
        .str.replace_all(r"\s+", " ")
        .str.to_uppercase()
    )

def _coalesce_existing(df: pl.DataFrame, cols: List[str]) -> pl.Expr:
    existing = [pl.col(c).cast(pl.String, strict=False) for c in cols if c in df.columns]
    if not existing:
        return pl.lit(None, dtype=pl.String)
    return pl.coalesce(existing)

def _safe_cast_int(col: str) -> pl.Expr:
    return pl.col(col).cast(pl.Int64, strict=False).fill_null(0)

def _parse_date_expr(col: str = "Date") -> pl.Expr:
    return pl.col(col).cast(pl.String, strict=False).str.strptime(pl.Date, format="%Y-%m-%d", strict=False)

# =========================
# Main class
# =========================

class FactTablePopulator:
    def __init__(
        self,
        host="localhost",
        port=3307,
        user="root",
        password="",
        database="fluxvision",
        data_path: Path = Path("fluxvision_automation/data/data_extracted"),
        test_mode: bool = False,
        batch_size: int = OPTIMIZED_BATCH_SIZE,
        resume_from_checkpoint: bool = True,
    ):
        self.host = host
        self.port = port
        self.user = user
        self.password = password
        self.database = database
        self.data_path = Path(data_path)

        self.test_mode = test_mode
        self.batch_size = int(batch_size)
        self.table_suffix = "_test" if test_mode else ""
        self.resume_from_checkpoint = resume_from_checkpoint

        self.connection_pool: Optional[MySQLConnectionPool] = None
        self.connection = None
        self.cursor = None

        # Checkpoint & stats
        self.checkpoint = self._load_checkpoint() if resume_from_checkpoint else {}
        self.processed_files = set(self.checkpoint.get("processed_files", []))
        self.stats = defaultdict(int, self.checkpoint.get("stats", {}))

        # Dimension caches (nom normalisé -> id)
        self.dim_cache: Dict[str, Dict[str, int]] = {
            "zones": {}, "provenances": {}, "categories": {},
            "pays": {}, "departements": {}
        }
        self.dim_communes_by_insee: Dict[str, int] = {}
        self.dim_epci_by_name: Dict[str, int] = {}

        # Mappings fichiers -> tables
        self.file_to_table_mapping = {
            # "Nuitee": f"fact_nuitees{self.table_suffix}",
            # "Diurne": f"fact_diurnes{self.table_suffix}",
            # "Nuitee_Pays": f"fact_nuitees_pays{self.table_suffix}",
            # "Diurne_Pays": f"fact_diurnes_pays{self.table_suffix}",
            # "Nuitee_Departement": f"fact_nuitees_departements{self.table_suffix}",
            # "Diurne_Departement": f"fact_diurnes_departements{self.table_suffix}",
            # "SejourDuree": f"fact_sejours_duree{self.table_suffix}",
            # "SejourDuree_Departement": f"fact_sejours_duree_departements{self.table_suffix}",
            # "SejourDuree_Pays": f"fact_sejours_duree_pays{self.table_suffix}",
        }
        self.lieu_file_to_table_mapping = {
            "LieuActivite_Soir": f"fact_lieu_activite_soir{self.table_suffix}",
            # "LieuActivite_Soir_Departement": f"fact_lieu_activite_soir_departement{self.table_suffix}",
            # "LieuActivite_Soir_Pays": f"fact_lieu_activite_soir_pays{self.table_suffix}",
            # "LieuActivite_Veille": f"fact_lieu_activite_veille{self.table_suffix}",
            # "LieuActivite_Veille_Departement": f"fact_lieu_activite_veille_departement{self.table_suffix}",
            # "LieuActivite_Veille_Pays": f"fact_lieu_activite_veille_pays{self.table_suffix}",
            # "LieuNuitee_Soir": f"fact_lieu_nuitee_soir{self.table_suffix}",
            # "LieuNuitee_Soir_Departement": f"fact_lieu_nuitee_soir_departement{self.table_suffix}",
            # "LieuNuitee_Soir_Pays": f"fact_lieu_nuitee_soir_pays{self.table_suffix}",
            # "LieuNuitee_Veille": f"fact_lieu_nuitee_veille{self.table_suffix}",
            # "LieuNuitee_Veille_Departement": f"fact_lieu_nuitee_veille_departement{self.table_suffix}",
            # "LieuNuitee_Veille_Pays": f"fact_lieu_nuitee_veille_pays{self.table_suffix}",
        }

        self._insert_stmt_cache: Dict[str, Tuple[str, int]] = {}

        logger.info("Mode test=%s, batch=%s, resume=%s", self.test_mode, self.batch_size, self.resume_from_checkpoint)
        logger.info("Machine: i5-1240P (%d cœurs), pool connexions: %d", CPU_COUNT, CONNECTION_POOL_SIZE)

    # --------------- Checkpoint ---------------

    def _load_checkpoint(self) -> Dict[str, Any]:
        if os.path.exists(CHECKPOINT_FILE):
            try:
                with open(CHECKPOINT_FILE, "r", encoding="utf-8") as f:
                    ck = json.load(f)
                    logger.info("Checkpoint chargé: %d fichiers déjà traités", len(ck.get("processed_files", [])))
                    return ck
            except Exception as e:
                logger.warning("Impossible de charger checkpoint: %s", e)
        return {}

    def _save_checkpoint(self):
        data = {
            "processed_files": list(self.processed_files),
            "stats": dict(self.stats),
            "timestamp": datetime.now().isoformat(),
        }
        try:
            with open(CHECKPOINT_FILE, "w", encoding="utf-8") as f:
                json.dump(data, f, indent=2, ensure_ascii=False)
        except Exception as e:
            logger.warning("Impossible de sauvegarder checkpoint: %s", e)

    def _is_file_processed(self, p: str) -> bool:
        return p in self.processed_files

    def _mark_file_processed(self, p: str):
        self.processed_files.add(p)
        if len(self.processed_files) % 10 == 0:
            self._save_checkpoint()

    def cleanup_checkpoint_on_success(self):
        if os.path.exists(CHECKPOINT_FILE):
            try:
                os.remove(CHECKPOINT_FILE)
                logger.info("Checkpoint nettoyé après succès complet")
            except Exception as e:
                logger.warning("Impossible de nettoyer checkpoint: %s", e)

    # --------------- DB Connexions ---------------

    def create_connection_pool(self) -> bool:
        try:
            self.connection_pool = MySQLConnectionPool(
                pool_name="fluxvision_pool",
                pool_size=CONNECTION_POOL_SIZE,
                host=self.host,
                port=self.port,
                user=self.user,
                password=self.password,
                database=self.database,
                charset="utf8mb4",
                collation="utf8mb4_unicode_ci",
                autocommit=False,
            )
            self.connection = self.connection_pool.get_connection()
            self.cursor = self.connection.cursor()
            logger.info("Pool de connexions créé")
            return True
        except Error as e:
            logger.error("Erreur pool de connexions: %s", e)
            return False

    def connect(self) -> bool:
        return self.create_connection_pool()

    # --------------- DDL ---------------

    def _ddl_fact_simple(self, table: str) -> str:
        return f"""
        CREATE TABLE IF NOT EXISTS {table}(
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            id_zone INT NOT NULL,
            id_provenance INT NOT NULL,
            id_categorie INT NOT NULL,
            volume INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq(date,id_zone,id_provenance,id_categorie),
            KEY idx_date_zone(date,id_zone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """

    def _ddl_fact_dep(self, table: str) -> str:
        return f"""
        CREATE TABLE IF NOT EXISTS {table}(
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            id_zone INT NOT NULL,
            id_provenance INT NOT NULL,
            id_categorie INT NOT NULL,
            id_departement INT NOT NULL,
            volume INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq(date,id_zone,id_provenance,id_categorie,id_departement),
            KEY idx_date_zone_dep(date,id_zone,id_departement)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """

    def _ddl_fact_pays(self, table: str) -> str:
        return f"""
        CREATE TABLE IF NOT EXISTS {table}(
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            id_zone INT NOT NULL,
            id_provenance INT NOT NULL,
            id_categorie INT NOT NULL,
            id_pays INT NOT NULL,
            volume INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq(date,id_zone,id_provenance,id_categorie,id_pays),
            KEY idx_date_zone_pays(date,id_zone,id_pays)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """

    def _ddl_lieu(self, table: str, with_dep=False, with_pays=False) -> str:
        extra_geo = "id_departement INT NOT NULL," if with_dep else ("id_pays INT NOT NULL," if with_pays else "")
        uq_cols = ["date","id_zone","id_provenance","id_categorie"]
        if with_dep:  uq_cols.append("id_departement")
        if with_pays: uq_cols.append("id_pays")
        uq_cols += ["id_epci","id_commune"]
        uq = ",".join(uq_cols)
        idx_geo = "id_departement" if with_dep else ("id_pays" if with_pays else "id_zone")
        return f"""
        CREATE TABLE IF NOT EXISTS {table}(
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            jour_semaine VARCHAR(10) NOT NULL,
            id_zone INT NOT NULL,
            id_provenance INT NOT NULL,
            id_categorie INT NOT NULL,
            {extra_geo}
            id_epci INT NOT NULL DEFAULT 0,
            id_commune INT NOT NULL DEFAULT 0,
            volume INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq({uq}),
            KEY idx_date_zone(date,id_zone),
            KEY idx_geo({idx_geo}),
            KEY idx_jour(jour_semaine),
            KEY idx_epci(id_epci),
            KEY idx_commune(id_commune)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """

    def _ensure_dims(self):
        c = self.cursor
        # Dims
        c.execute("""
            CREATE TABLE IF NOT EXISTS dim_zones_observation(
                id_zone INT AUTO_INCREMENT PRIMARY KEY,
                nom_zone VARCHAR(255) UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        c.execute("""
            CREATE TABLE IF NOT EXISTS dim_provenances(
                id_provenance INT AUTO_INCREMENT PRIMARY KEY,
                nom_provenance VARCHAR(255) UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        c.execute("""
            CREATE TABLE IF NOT EXISTS dim_categories_visiteur(
                id_categorie INT AUTO_INCREMENT PRIMARY KEY,
                nom_categorie VARCHAR(255) UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        c.execute("""
            CREATE TABLE IF NOT EXISTS dim_pays(
                id_pays INT AUTO_INCREMENT PRIMARY KEY,
                nom_pays VARCHAR(255) UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        c.execute("""
            CREATE TABLE IF NOT EXISTS dim_departements(
                id_departement INT AUTO_INCREMENT PRIMARY KEY,
                nom_departement VARCHAR(255) UNIQUE,
                nom_region VARCHAR(255) NULL,
                nom_nouvelle_region VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        c.execute("""
            CREATE TABLE IF NOT EXISTS dim_communes(
                id_commune INT AUTO_INCREMENT PRIMARY KEY,
                code_insee VARCHAR(10) NOT NULL,
                nom_commune VARCHAR(255) NOT NULL,
                id_departement INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_commune_insee(code_insee),
                KEY idx_commune_dept(id_departement)
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        c.execute("""
            CREATE TABLE IF NOT EXISTS dim_epci(
                id_epci INT AUTO_INCREMENT PRIMARY KEY,
                nom_epci VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_epci_nom(nom_epci)
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        c.execute("""
            CREATE TABLE IF NOT EXISTS dim_dates(
                date DATE PRIMARY KEY,
                vacances_a TINYINT(1) DEFAULT 0,
                vacances_b TINYINT(1) DEFAULT 0,
                vacances_c TINYINT(1) DEFAULT 0,
                ferie TINYINT(1) DEFAULT 0,
                jour_semaine VARCHAR(10),
                mois TINYINT,
                annee SMALLINT,
                trimestre TINYINT,
                semaine TINYINT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        # Faits historiques
        c.execute(self._ddl_fact_simple(f"fact_diurnes{self.table_suffix}"))
        c.execute(self._ddl_fact_simple(f"fact_nuitees{self.table_suffix}"))
        c.execute(self._ddl_fact_dep(f"fact_diurnes_departements{self.table_suffix}"))
        c.execute(self._ddl_fact_dep(f"fact_nuitees_departements{self.table_suffix}"))
        c.execute(self._ddl_fact_pays(f"fact_diurnes_pays{self.table_suffix}"))
        c.execute(self._ddl_fact_pays(f"fact_nuitees_pays{self.table_suffix}"))
        # Faits Séjours / durée
        c.execute(f"""
            CREATE TABLE IF NOT EXISTS fact_sejours_duree{self.table_suffix}(
                id INT AUTO_INCREMENT PRIMARY KEY,
                date DATE NOT NULL,
                id_zone INT NOT NULL,
                id_provenance INT NOT NULL,
                id_categorie INT NOT NULL,
                id_duree INT NOT NULL,
                volume INT NOT NULL,
                KEY idx1(date,id_zone,id_duree),
                UNIQUE KEY uq_fact_sejours_duree(date,id_zone,id_provenance,id_categorie,id_duree)
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        c.execute(f"""
            CREATE TABLE IF NOT EXISTS fact_sejours_duree_departements{self.table_suffix}(
                id INT AUTO_INCREMENT PRIMARY KEY,
                date DATE NOT NULL,
                id_zone INT NOT NULL,
                id_provenance INT NOT NULL,
                id_categorie INT NOT NULL,
                id_departement INT NOT NULL,
                id_duree INT NOT NULL,
                volume INT NOT NULL,
                KEY idx1(date,id_zone,id_departement,id_duree),
                UNIQUE KEY uq_fact_sejours_duree_dept(date,id_zone,id_provenance,id_categorie,id_departement,id_duree)
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        c.execute(f"""
            CREATE TABLE IF NOT EXISTS fact_sejours_duree_pays{self.table_suffix}(
                id INT AUTO_INCREMENT PRIMARY KEY,
                date DATE NOT NULL,
                id_zone INT NOT NULL,
                id_provenance INT NOT NULL,
                id_categorie INT NOT NULL,
                id_pays INT NOT NULL,
                id_duree INT NOT NULL,
                volume INT NOT NULL,
                KEY idx1(date,id_zone,id_pays,id_duree),
                UNIQUE KEY uq_fact_sejours_duree_pays(date,id_zone,id_provenance,id_categorie,id_pays,id_duree)
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        # Nouvelles tables Lieu*
        for key, tbl in self.lieu_file_to_table_mapping.items():
            if key.endswith("_Departement"):
                c.execute(self._ddl_lieu(tbl, with_dep=True))
            elif key.endswith("_Pays"):
                c.execute(self._ddl_lieu(tbl, with_pays=True))
            else:
                c.execute(self._ddl_lieu(tbl))
        self.connection.commit()
        logger.info("Création/validation des tables OK (dims + faits).")

    # --------------- Uniques & cleanup ---------------

    def _dedupe_fact_table(self, table: str, uniq_cols: List[str]):
        on_clause = " AND ".join([f"a.{c}=b.{c}" for c in uniq_cols])
        try:
            self.cursor.execute(f"DELETE a FROM {table} a JOIN {table} b ON {on_clause} AND a.id>b.id")
            self.connection.commit()
        except Exception as e:
            self.connection.rollback()
            logger.warning("[%s] dédup: %s", table, e)
        try:
            self.cursor.execute(f"ALTER TABLE {table} ADD UNIQUE KEY uq({','.join(uniq_cols)})")
            self.connection.commit()
        except Exception:
            self.connection.rollback()

    def enforce_unique_constraints_and_cleanup(self):
        fact_defs = [
            (f"fact_diurnes{self.table_suffix}",               ["date","id_zone","id_provenance","id_categorie"]),
            (f"fact_nuitees{self.table_suffix}",               ["date","id_zone","id_provenance","id_categorie"]),
            (f"fact_diurnes_departements{self.table_suffix}",  ["date","id_zone","id_provenance","id_categorie","id_departement"]),
            (f"fact_nuitees_departements{self.table_suffix}",  ["date","id_zone","id_provenance","id_categorie","id_departement"]),
            (f"fact_diurnes_pays{self.table_suffix}",          ["date","id_zone","id_provenance","id_categorie","id_pays"]),
            (f"fact_nuitees_pays{self.table_suffix}",          ["date","id_zone","id_provenance","id_categorie","id_pays"]),
            (f"fact_sejours_duree{self.table_suffix}",         ["date","id_zone","id_provenance","id_categorie","id_duree"]),
            (f"fact_sejours_duree_departements{self.table_suffix}", ["date","id_zone","id_provenance","id_categorie","id_departement","id_duree"]),
            (f"fact_sejours_duree_pays{self.table_suffix}",    ["date","id_zone","id_provenance","id_categorie","id_pays","id_duree"]),
        ]
        for t, cols in fact_defs:
            self._dedupe_fact_table(t, cols)

    # --------------- Dimension caches ---------------

    def _fetch_cache_generic(self, table: str, id_col: str, name_col: str) -> Dict[str, int]:
        self.cursor.execute(f"SELECT {id_col},{name_col} FROM {table}")
        return {normalize_str_light(name): idv for idv, name in self.cursor.fetchall() if name}

    def load_dimension_cache(self):
        logger.info("Chargement cache dimensions...")
        self.dim_cache["zones"]        = self._fetch_cache_generic("dim_zones_observation", "id_zone", "nom_zone")
        self.dim_cache["provenances"]  = self._fetch_cache_generic("dim_provenances", "id_provenance", "nom_provenance")
        self.dim_cache["categories"]   = self._fetch_cache_generic("dim_categories_visiteur", "id_categorie", "nom_categorie")
        self.dim_cache["pays"]         = self._fetch_cache_generic("dim_pays", "id_pays", "nom_pays")
        self.dim_cache["departements"] = self._fetch_cache_generic("dim_departements", "id_departement", "nom_departement")

        # communes
        try:
            self.cursor.execute("SELECT id_commune, code_insee FROM dim_communes")
            self.dim_communes_by_insee = {normalize_str_light(c): i for i, c in self.cursor.fetchall() if c}
        except Exception:
            self.dim_communes_by_insee = {}

        # epci
        try:
            self.cursor.execute("SELECT id_epci, nom_epci FROM dim_epci")
            self.dim_epci_by_name = {normalize_str_light(n): i for i, n in self.cursor.fetchall() if n}
        except Exception:
            self.dim_epci_by_name = {}

        logger.info(
            "Dims chargées: zones=%d, prov=%d, cat=%d, pays=%d, dep=%d, communes=%d, epci=%d",
            len(self.dim_cache["zones"]),
            len(self.dim_cache["provenances"]),
            len(self.dim_cache["categories"]),
            len(self.dim_cache["pays"]),
            len(self.dim_cache["departements"]),
            len(self.dim_communes_by_insee),
            len(self.dim_epci_by_name),
        )

    # ---- Batch UPSERT dims ----

    def _batch_insert_names(self, table: str, name_col: str, values: List[str]):
        if not values:
            return
        q = f"INSERT IGNORE INTO {table} ({name_col}) VALUES (%s)"
        self.cursor.executemany(q, [(v,) for v in values])
        self.connection.commit()

    def _batch_upsert_communes(self, commune_data: List[Tuple[str, str, Optional[int]]]):
        """commune_data: List of (code_insee, nom_commune, id_departement) tuples"""
        if not commune_data:
            return
        q = """
        INSERT INTO dim_communes (code_insee, nom_commune, id_departement)
        VALUES (%s, %s, %s)
        ON DUPLICATE KEY UPDATE
            nom_commune = VALUES(nom_commune),
            id_departement = COALESCE(VALUES(id_departement), id_departement)
        """
        self.cursor.executemany(q, commune_data)
        self.connection.commit()

    def _batch_upsert_dep_regions(self, dep_triples: List[Tuple[str, Optional[str], Optional[str]]]):
        """dep_triples: (dep_norm, region_norm, nouvelle_region_norm)"""
        if not dep_triples:
            return
        q = """
        UPDATE dim_departements
           SET nom_region = COALESCE(%s, nom_region),
               nom_nouvelle_region = COALESCE(%s, nom_nouvelle_region)
         WHERE nom_departement = %s
        """
        self.cursor.executemany(q, [(r, rn, d) for d, r, rn in dep_triples])
        self.connection.commit()

    # ---- Helpers mapping dims via joins ----

    def _df_map_dim(self, df: pl.DataFrame, src_col: str, dim_key: str, out_id_col: str) -> pl.DataFrame:
        """Normalise, insère les manquants en dim, recharge le cache et join pour id."""
        df = df.with_columns(pl.when(pl.col(src_col).is_null()).then(None).otherwise(_normalize_expr(src_col)).alias(f"{src_col}_norm"))

        uniq_vals = (
            df.select(pl.col(f"{src_col}_norm"))
              .drop_nulls()
              .unique()
              .to_series()
              .to_list()
        )
        cache = self.dim_cache[dim_key]
        to_create = [v for v in uniq_vals if v not in cache]
        if to_create:
            table_name, name_col, id_col = {
                "zones": ("dim_zones_observation", "nom_zone", "id_zone"),
                "provenances": ("dim_provenances", "nom_provenance", "id_provenance"),
                "categories": ("dim_categories_visiteur", "nom_categorie", "id_categorie"),
                "pays": ("dim_pays", "nom_pays", "id_pays"),
                "departements": ("dim_departements", "nom_departement", "id_departement"),
            }[dim_key]
            self._batch_insert_names(table_name, name_col, to_create)
            self.dim_cache[dim_key] = self._fetch_cache_generic(table_name, id_col, name_col)

        map_df = pl.DataFrame({
            f"{src_col}_norm": list(self.dim_cache[dim_key].keys()),
            out_id_col: list(self.dim_cache[dim_key].values()),
        })
        return df.join(map_df, on=f"{src_col}_norm", how="left")

    def _df_map_commune_by_insee(self, df: pl.DataFrame, insee_col_out: str = "code_insee_norm") -> pl.DataFrame:
        df = df.with_columns(
            pl.when(pl.col(insee_col_out).is_null()).then(None).otherwise(_normalize_expr(insee_col_out)).alias(insee_col_out)
        )

        # Get commune names using the same coalesce logic as INSEE codes
        commune_expr = _coalesce_existing(df, list(COMMUNE_COLS))
        df = df.with_columns(commune_expr.alias("nom_commune_raw"))

        # Get department names for communes (for Lieu* files)
        dep_expr = _coalesce_existing(df, list(DEPT_FALLBACK_COLS))
        df = df.with_columns(dep_expr.alias("nom_departement_raw"))

        # Create unique mapping of (code_insee, nom_commune, nom_departement) triples
        mapping_df = df.select([insee_col_out, "nom_commune_raw", "nom_departement_raw"]).drop_nulls().unique()

        commune_data = []
        for row in mapping_df.iter_rows():
            code_insee = row[0]
            nom_commune = row[1] if row[1] else ""  # Empty string if None
            nom_departement = row[2] if len(row) > 2 and row[2] else None

            # Get id_departement from department cache if department name is available
            id_departement = None
            if nom_departement:
                dep_norm = normalize_str_light(nom_departement)
                id_departement = self.dim_cache["departements"].get(dep_norm)

            if code_insee and code_insee not in self.dim_communes_by_insee:
                commune_data.append((code_insee, nom_commune, id_departement))

        # Insert new communes with their names and department ids
        if commune_data:
            self._batch_upsert_communes(commune_data)

            # Update existing communes that don't have id_departement but now have department info
            update_data = [(id_dep, code_insee) for code_insee, _, id_dep in commune_data if id_dep is not None]
            if update_data:
                self.cursor.executemany(
                    "UPDATE dim_communes SET id_departement = %s WHERE code_insee = %s AND id_departement IS NULL",
                    update_data
                )
                self.connection.commit()

            # reload cache
            self.cursor.execute("SELECT id_commune, code_insee FROM dim_communes")
            self.dim_communes_by_insee = {normalize_str_light(c): i for i, c in self.cursor.fetchall() if c}

        # Only create mapping if cache is not empty
        if self.dim_communes_by_insee:
            map_df = pl.DataFrame({
                insee_col_out: list(self.dim_communes_by_insee.keys()),
                "id_commune": list(self.dim_communes_by_insee.values())
            })
            # Ensure consistent data types
            map_df = map_df.with_columns(pl.col(insee_col_out).cast(pl.Utf8))
            df = df.with_columns(pl.col(insee_col_out).cast(pl.Utf8))
            return df.join(map_df, on=insee_col_out, how="left")
        else:
            # If no communes in cache, return df with null id_commune
            return df.with_columns(pl.lit(None).alias("id_commune"))

    def _df_map_epci_by_name(self, df: pl.DataFrame, epci_norm_col: str = "nom_epci_norm") -> pl.DataFrame:
        df = df.with_columns(
            pl.when(pl.col(epci_norm_col).is_null()).then(None).otherwise(_normalize_expr(epci_norm_col)).alias(epci_norm_col)
        )
        uniq = df.select(pl.col(epci_norm_col)).drop_nulls().unique().to_series().to_list()
        to_create = [n for n in uniq if n not in self.dim_epci_by_name]
        if to_create:
            self._batch_insert_names("dim_epci", "nom_epci", to_create)
            self.cursor.execute("SELECT id_epci, nom_epci FROM dim_epci")
            self.dim_epci_by_name = {normalize_str_light(n): i for i, n in self.cursor.fetchall() if n}
        map_df = pl.DataFrame({
            epci_norm_col: list(self.dim_epci_by_name.keys()),
            "id_epci": list(self.dim_epci_by_name.values())
        })
        return df.join(map_df, on=epci_norm_col, how="left")

    # --------------- Insert helpers ---------------

    def _prepare_insert_statement(self, table: str) -> Tuple[str, int]:
        if table in self._insert_stmt_cache:
            return self._insert_stmt_cache[table]
        if table.endswith("_pays") and "sejours_duree" in table:
            cols = "(date,id_zone,id_provenance,id_categorie,id_pays,id_duree,volume)"; arity = 7
        elif table.endswith("_departements") and "sejours_duree" in table:
            cols = "(date,id_zone,id_provenance,id_categorie,id_departement,id_duree,volume)"; arity = 7
        elif "sejours_duree" in table:
            cols = "(date,id_zone,id_provenance,id_categorie,id_duree,volume)"; arity = 6
        elif table.endswith("_pays"):
            cols = "(date,id_zone,id_provenance,id_categorie,id_pays,volume)"; arity = 6
        elif table.endswith("_departements"):
            cols = "(date,id_zone,id_provenance,id_categorie,id_departement,volume)"; arity = 6
        else:
            cols = "(date,id_zone,id_provenance,id_categorie,volume)"; arity = 5
        q = f"INSERT INTO {table} {cols} VALUES ({','.join(['%s']*arity)}) ON DUPLICATE KEY UPDATE volume=VALUES(volume)"
        self._insert_stmt_cache[table] = (q, arity)
        return q, arity

    def _prepare_insert_statement_lieu(self, table: str) -> Tuple[str, int]:
        if table in self._insert_stmt_cache:
            return self._insert_stmt_cache[table]
        if table.endswith("_departement"):
            cols = "(date,jour_semaine,id_zone,id_provenance,id_categorie,id_departement,id_epci,id_commune,volume)"; arity=9
        elif table.endswith("_pays"):
            cols = "(date,jour_semaine,id_zone,id_provenance,id_categorie,id_pays,id_epci,id_commune,volume)"; arity=9
        else:
            cols = "(date,jour_semaine,id_zone,id_provenance,id_categorie,id_epci,id_commune,volume)"; arity=8
        q = f"INSERT INTO {table} {cols} VALUES ({','.join(['%s']*arity)}) ON DUPLICATE KEY UPDATE volume=VALUES(volume)"
        self._insert_stmt_cache[table] = (q, arity)
        return q, arity

    def insert_batch_tuples(self, table: str, rows: List[Tuple]) -> int:
        if not rows:
            return 0
        conn = self.connection_pool.get_connection() if self.connection_pool else self.connection
        cur = conn.cursor()
        q, _ = self._prepare_insert_statement(table)
        total = 0
        chunk = 10000
        for i in range(0, len(rows), chunk):
            part = rows[i:i+chunk]
            cur.executemany(q, part)
            conn.commit()
            total += len(part)
        cur.close()
        if conn != self.connection:
            conn.close()
        return total

    def insert_batch_tuples_lieu(self, table: str, rows: List[Tuple]) -> int:
        if not rows:
            return 0
        conn = self.connection_pool.get_connection() if self.connection_pool else self.connection
        cur = conn.cursor()
        q, _ = self._prepare_insert_statement_lieu(table)
        total = 0
        chunk = 10000
        for i in range(0, len(rows), chunk):
            part = rows[i:i+chunk]
            cur.executemany(q, part)
            conn.commit()
            total += len(part)
        cur.close()
        if conn != self.connection:
            conn.close()
        return total

    # --------------- Date dim (batch) ---------------

    def upsert_dim_dates_from_df(self, df: pl.DataFrame):
        if "date" not in df.columns:
            return
        # Agréger par date (max des flags ; premier jour_semaine)
        cols_present = set(df.columns)
        base = df.select(
            "date",
            *(c for c in ["vacances_a","vacances_b","vacances_c","ferie","jour_semaine"] if c in cols_present)
        )
        g = (
            base.group_by("date")
                .agg([
                    pl.max("vacances_a").alias("vacances_a") if "vacances_a" in cols_present else pl.lit(0).alias("vacances_a"),
                    pl.max("vacances_b").alias("vacances_b") if "vacances_b" in cols_present else pl.lit(0).alias("vacances_b"),
                    pl.max("vacances_c").alias("vacances_c") if "vacances_c" in cols_present else pl.lit(0).alias("vacances_c"),
                    pl.max("ferie").alias("ferie")           if "ferie" in cols_present      else pl.lit(0).alias("ferie"),
                    pl.first("jour_semaine").alias("jour_semaine") if "jour_semaine" in cols_present else pl.lit(None, dtype=pl.String).alias("jour_semaine"),
                ])
                .with_columns([
                    pl.col("date").dt.month().alias("mois"),
                    pl.col("date").dt.year().alias("annee"),
                    ((pl.col("date").dt.month() - 1) // 3 + 1).alias("trimestre"),
                    pl.col("date").dt.week().alias("semaine"),  # <- ISO week (fix)
                ])
        )
        rows = g.select(
            "date","vacances_a","vacances_b","vacances_c","ferie","jour_semaine","mois","annee","trimestre","semaine"
        ).rows()
        if rows:
            q = """
            INSERT IGNORE INTO dim_dates(date,vacances_a,vacances_b,vacances_c,ferie,jour_semaine,mois,annee,trimestre,semaine)
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            """
            self.cursor.executemany(q, rows)
            self.connection.commit()

    # --------------- File type detection ---------------

    @staticmethod
    def determine_file_type(filename: str) -> Optional[str]:
        base = filename.replace(".csv", "")
        if re.search(r"semaine", base, re.IGNORECASE):
            return None
        if re.match(r"^SejourDuree_(20\d{2}B\d+).*$", base, re.IGNORECASE):
            return "SejourDuree"
        if re.match(r"^SejourDuree_Departement_(20\d{2}B\d+).*$", base, re.IGNORECASE):
            return "SejourDuree_Departement"
        if re.match(r"^SejourDuree_Pays_(20\d{2}B\d+).*$", base, re.IGNORECASE):
            return "SejourDuree_Pays"
        if re.match(r"^Nuitee_20\d{2}B\d+_[^_]+$", base):
            return "Nuitee"
        if re.match(r"^Diurne_20\d{2}B\d+_[^_]+$", base):
            return "Diurne"
        if re.match(r"^Nuitee_Pays_20\d{2}B\d+", base):
            return "Nuitee_Pays"
        if re.match(r"^Diurne_Pays_20\d{2}B\d+", base):
            return "Diurne_Pays"
        if re.match(r"^Nuitee_Departement_20\d{2}B\d+", base, re.IGNORECASE):
            return "Nuitee_Departement"
        if re.match(r"^Diurne_Departement_20\d{2}B\d+", base, re.IGNORECASE):
            return "Diurne_Departement"
        if re.match(r"^Nuitee_Regions_20\d{2}B\d+", base) or re.match(r"^Diurne_Regions_20\d{2}B\d+", base):
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

    # --------------- CSV reading ---------------

    def _read_csv_useful(self, csv_file: Path, use_cols: set) -> Optional[pl.DataFrame]:
        df = pl.read_csv(csv_file, separator=";", infer_schema_length=300)
        cols = [c for c in df.columns if c in use_cols]
        if not cols:
            return None
        df = df.select(cols)
        # cast volume
        if "Volume" in df.columns:
            df = df.with_columns(_safe_cast_int("Volume").alias("Volume"))
        # date
        if "Date" in df.columns:
            df = df.with_columns(_parse_date_expr("Date").alias("date"))
        # jour_semaine: garder si fourni
        if "JourDeLaSemaine" in df.columns:
            df = df.with_columns(pl.col("JourDeLaSemaine").cast(pl.String, strict=False).alias("jour_semaine"))
        elif "jour_semaine" in df.columns:
            df = df.rename({"jour_semaine": "jour_semaine"})
        else:
            df = df.with_columns(pl.lit(None, dtype=pl.String).alias("jour_semaine"))
        # flags vacances/ferie si présents
        for src, dst in [("VacancesA","vacances_a"),("VacancesB","vacances_b"),("VacancesC","vacances_c"),("Ferie","ferie")]:
            if src in df.columns:
                df = df.with_columns(pl.col(src).cast(pl.Int8, strict=False).fill_null(0).alias(dst))
        return df

    # --------------- Vectorized processors ---------------

    def _prepare_common_id_mapping(self, df: pl.DataFrame) -> pl.DataFrame:
        # Zone / Provenance / Catégorie
        if "ZoneObservation" in df.columns:
            df = self._df_map_dim(df, "ZoneObservation", "zones", "id_zone")
        else:
            df = df.with_columns(pl.lit(None).alias("id_zone"))

        if "Provenance" in df.columns:
            df = self._df_map_dim(df, "Provenance", "provenances", "id_provenance")
        else:
            df = df.with_columns(pl.lit(None).alias("id_provenance"))

        if "CategorieVisiteur" in df.columns:
            df = self._df_map_dim(df, "CategorieVisiteur", "categories", "id_categorie")
        else:
            df = df.with_columns(pl.lit(None).alias("id_categorie"))

        # Contrôle minimum
        df = df.filter(pl.col("id_zone").is_not_null() & pl.col("id_provenance").is_not_null() & pl.col("id_categorie").is_not_null())
        return df

    def _prepare_dep_mapping_and_update_regions(self, df: pl.DataFrame) -> pl.DataFrame:
        dep_expr = _coalesce_existing(df, list(DEPT_FALLBACK_COLS))
        df = df.with_columns(dep_expr.alias("NomDepartementEff"))
        df = self._df_map_dim(df, "NomDepartementEff", "departements", "id_departement")

        dep_trip = (
            df.select([
                pl.col("NomDepartementEff").alias("dep"),
                _normalize_expr("NomRegion").alias("region") if "NomRegion" in df.columns else pl.lit(None).alias("region"),
                _normalize_expr("NomNouvelleRegion").alias("nregion") if "NomNouvelleRegion" in df.columns else pl.lit(None).alias("nregion"),
            ])
            .drop_nulls(subset=["dep"])
            .unique()
        )
        triples = [(r["dep"], r["region"], r["nregion"]) for r in dep_trip.iter_rows(named=True)]
        if triples:
            self._batch_upsert_dep_regions(triples)
        return df

    def _prepare_pays_mapping(self, df: pl.DataFrame) -> pl.DataFrame:
        if "Pays" not in df.columns:
            return df.with_columns(pl.lit(None).alias("id_pays"))
        df = self._df_map_dim(df, "Pays", "pays", "id_pays")
        return df

    def _prepare_epci_commune_mapping(self, df: pl.DataFrame) -> pl.DataFrame:
        epci_expr = _coalesce_existing(df, list(EPCI_COLS))
        df = df.with_columns(epci_expr.alias("NomEPCIEff"))
        df = self._df_map_epci_by_name(df.with_columns(pl.col("NomEPCIEff").alias("nom_epci_norm")), "nom_epci_norm")
        df = df.with_columns(pl.col("id_epci").fill_null(0).cast(pl.Int64))

        insee_expr = _coalesce_existing(df, list(INSEE_COLS))
        df = df.with_columns(insee_expr.alias("code_insee_norm"))

        df = self._df_map_commune_by_insee(df, "code_insee_norm")
        df = df.with_columns(pl.col("id_commune").fill_null(0).cast(pl.Int64))
        return df

    def _aggregate_and_build_rows(self, df: pl.DataFrame, keys: List[str], table: str) -> List[Tuple]:
        agg = (
            df.select(keys + ["Volume"])
              .group_by(keys)
              .agg(pl.col("Volume").sum().alias("volume"))
        )
        if "sejours_duree" in table:
            if table.endswith("_pays"):
                cols = ["date","id_zone","id_provenance","id_categorie","id_pays","id_duree","volume"]
            elif table.endswith("_departements"):
                cols = ["date","id_zone","id_provenance","id_categorie","id_departement","id_duree","volume"]
            else:
                cols = ["date","id_zone","id_provenance","id_categorie","id_duree","volume"]
        elif table.endswith("_pays"):
            cols = ["date","id_zone","id_provenance","id_categorie","id_pays","volume"]
        elif table.endswith("_departements"):
            cols = ["date","id_zone","id_provenance","id_categorie","id_departement","volume"]
        else:
            cols = ["date","id_zone","id_provenance","id_categorie","volume"]
        return agg.select(cols).rows()

    def _aggregate_and_build_rows_lieu(self, df: pl.DataFrame, keys: List[str], table: str) -> List[Tuple]:
        agg = df.select(keys + ["Volume"]).group_by(keys).agg(pl.col("Volume").sum().alias("volume"))
        if table.endswith("_departement"):
            cols = ["date","jour_semaine","id_zone","id_provenance","id_categorie","id_departement","id_epci","id_commune","volume"]
        elif table.endswith("_pays"):
            cols = ["date","jour_semaine","id_zone","id_provenance","id_categorie","id_pays","id_epci","id_commune","volume"]
        else:
            cols = ["date","jour_semaine","id_zone","id_provenance","id_categorie","id_epci","id_commune","volume"]
        return agg.select(cols).rows()

    # --------------- Process HISTORIC files (vectorized) ---------------

    def process_csv_file(self, csv_file: Path, file_type: str) -> int:
        path_str = str(csv_file.resolve())
        if self._is_file_processed(path_str):
            logger.info("[HIST] %s -> DÉJÀ TRAITÉ", csv_file.name)
            return 0

        logger.info("[HIST] %s -> %s", csv_file.name, file_type)
        table = self.file_to_table_mapping[file_type]

        use_cols = {
            "Date","ZoneObservation","Zone","Provenance","CategorieVisiteur","Volume",
            "DureeSejour","DureeSejourNum","NomDepartement","Pays",
            "VacancesA","VacancesB","VacancesC","Ferie","JourDeLaSemaine",
            "vacances_a","vacances_b","vacances_c","ferie","jour_semaine",
            "NomRegion","NomNouvelleRegion"
        }

        df = self._read_csv_useful(csv_file, use_cols)
        if df is None or df.height == 0:
            return 0

        df = self._prepare_common_id_mapping(df)
        self.upsert_dim_dates_from_df(df.select("date","jour_semaine","vacances_a","vacances_b","vacances_c","ferie"))

        if file_type == "SejourDuree":
            if "DureeSejour" in df.columns:
                df = df.with_columns(_normalize_expr("DureeSejour").alias("DureeSejour_norm"))
                uniq = df.select("DureeSejour_norm").drop_nulls().unique().to_series().to_list()
                if uniq:
                    self.cursor.executemany(
                        """
                        INSERT INTO dim_durees_sejour(libelle, nb_nuits, ordre)
                        VALUES (%s, NULL, NULL)
                        ON DUPLICATE KEY UPDATE id_duree=LAST_INSERT_ID(id_duree)
                        """,
                        [(u,) for u in uniq]
                    )
                    self.connection.commit()
                self.cursor.execute("SELECT id_duree, libelle FROM dim_durees_sejour")
                map_d = {normalize_str_light(n): i for i, n in self.cursor.fetchall() if n}
                map_df = pl.DataFrame({"DureeSejour_norm": list(map_d.keys()), "id_duree": list(map_d.values())})
                df = df.join(map_df, on="DureeSejour_norm", how="left")
            keys = ["date","id_zone","id_provenance","id_categorie","id_duree"]
        elif file_type == "SejourDuree_Departement":
            df = self._prepare_dep_mapping_and_update_regions(df)
            if "DureeSejour" in df.columns:
                df = df.with_columns(_normalize_expr("DureeSejour").alias("DureeSejour_norm"))
                uniq = df.select("DureeSejour_norm").drop_nulls().unique().to_series().to_list()
                if uniq:
                    self.cursor.executemany(
                        """
                        INSERT INTO dim_durees_sejour(libelle, nb_nuits, ordre)
                        VALUES (%s, NULL, NULL)
                        ON DUPLICATE KEY UPDATE id_duree=LAST_INSERT_ID(id_duree)
                        """,
                        [(u,) for u in uniq]
                    )
                    self.connection.commit()
                self.cursor.execute("SELECT id_duree, libelle FROM dim_durees_sejour")
                map_d = {normalize_str_light(n): i for i, n in self.cursor.fetchall() if n}
                map_df = pl.DataFrame({"DureeSejour_norm": list(map_d.keys()), "id_duree": list(map_d.values())})
                df = df.join(map_df, on="DureeSejour_norm", how="left")
            keys = ["date","id_zone","id_provenance","id_categorie","id_departement","id_duree"]
        elif file_type == "SejourDuree_Pays":
            df = self._prepare_pays_mapping(df)
            if "DureeSejour" in df.columns:
                df = df.with_columns(_normalize_expr("DureeSejour").alias("DureeSejour_norm"))
                uniq = df.select("DureeSejour_norm").drop_nulls().unique().to_series().to_list()
                if uniq:
                    self.cursor.executemany(
                        """
                        INSERT INTO dim_durees_sejour(libelle, nb_nuits, ordre)
                        VALUES (%s, NULL, NULL)
                        ON DUPLICATE KEY UPDATE id_duree=LAST_INSERT_ID(id_duree)
                        """,
                        [(u,) for u in uniq]
                    )
                    self.connection.commit()
                self.cursor.execute("SELECT id_duree, libelle FROM dim_durees_sejour")
                map_d = {normalize_str_light(n): i for i, n in self.cursor.fetchall() if n}
                map_df = pl.DataFrame({"DureeSejour_norm": list(map_d.keys()), "id_duree": list(map_d.values())})
                df = df.join(map_df, on="DureeSejour_norm", how="left")
            keys = ["date","id_zone","id_provenance","id_categorie","id_pays","id_duree"]
        elif file_type in ("Nuitee_Departement","Diurne_Departement"):
            df = self._prepare_dep_mapping_and_update_regions(df)
            keys = ["date","id_zone","id_provenance","id_categorie","id_departement"]
        elif file_type in ("Nuitee_Pays","Diurne_Pays"):
            df = self._prepare_pays_mapping(df)
            keys = ["date","id_zone","id_provenance","id_categorie","id_pays"]
        else:
            keys = ["date","id_zone","id_provenance","id_categorie"]

        df = df.filter(pl.col("Volume") > 0)
        rows = self._aggregate_and_build_rows(df, keys, table)
        inserted = self.insert_batch_tuples(table, rows)

        self.stats[f"files_processed_{file_type}"] += 1
        self.stats[f"rows_inserted_{file_type}"] += inserted
        logger.info(" -> %s lignes upsertées", f"{inserted:,}")

        self._mark_file_processed(path_str)
        return inserted

    # --------------- Process Lieu* files (vectorized) ---------------

    def process_lieu_csv_file(self, csv_file: Path, file_type: str) -> int:
        path_str = str(csv_file.resolve())
        if self._is_file_processed(path_str):
            logger.info("[Lieu*] %s -> DÉJÀ TRAITÉ", csv_file.name)
            return 0

        logger.info("[Lieu*] %s -> %s", csv_file.name, file_type)
        table = self.lieu_file_to_table_mapping[file_type]

        use_cols = {
            "Date","VacancesA","VacancesB","VacancesC","Ferie","JourDeLaSemaine",
            "vacances_a","vacances_b","vacances_c","ferie","jour_semaine",
            "Provenance","ZoneObservation","Zone","CategorieVisiteur","Volume",
            "NomDepartement","Departement","Pays",
            "DeptZoneDiurneSoir","DeptZoneNuiteeSoir","DeptZoneDiurneVeille","DeptZoneNuiteeVeille",
            "EPCIZoneNuiteeSoir","EPCIZoneDiurneSoir","EPCIZoneNuiteeVeille","EPCIZoneDiurneVeille","EPCI","NomEPCI",
            "CodeInseeNuiteeSoir","CodeInseeDiurneSoir","CodeInseeNuiteeVeille","CodeInseeDiurneVeille","CodeInsee","CodeINSEE",
            "ZoneNuiteeSoir","ZoneDiurneSoir","ZoneNuiteeVeille","ZoneDiurneVeille","Zone","Commune","NomCommune",
            "NomRegion","NomNouvelleRegion"
        }

        df = self._read_csv_useful(csv_file, use_cols)
        if df is None or df.height == 0:
            return 0

        df = self._prepare_common_id_mapping(df)
        self.upsert_dim_dates_from_df(df.select("date","jour_semaine","vacances_a","vacances_b","vacances_c","ferie"))
        df = self._prepare_epci_commune_mapping(df)

        if file_type.endswith("_Departement"):
            df = self._prepare_dep_mapping_and_update_regions(df)
            keys = ["date","jour_semaine","id_zone","id_provenance","id_categorie","id_departement","id_epci","id_commune"]
        elif file_type.endswith("_Pays"):
            df = self._prepare_pays_mapping(df)
            keys = ["date","jour_semaine","id_zone","id_provenance","id_categorie","id_pays","id_epci","id_commune"]
        else:
            keys = ["date","jour_semaine","id_zone","id_provenance","id_categorie","id_epci","id_commune"]

        df = df.filter(pl.col("Volume") > 0)
        rows = self._aggregate_and_build_rows_lieu(df, keys, table)
        inserted = self.insert_batch_tuples_lieu(table, rows)

        self.stats[f"files_processed_{file_type}"] += 1
        self.stats[f"rows_inserted_{file_type}"] += inserted
        logger.info(" -> %s lignes upsertées", f"{inserted:,}")

        self._mark_file_processed(path_str)
        return inserted

    # --------------- Orchestration ---------------

    def process_all_csv_files(self):
        logger.info("=== PASSE HISTORIQUE VECTORISÉE ===")
        if not self.data_path.exists():
            logger.error("Dossier absent: %s", self.data_path)
            return False

        files_by_type = defaultdict(list)
        for p in self.data_path.rglob("*.csv"):
            ft = self.determine_file_type(p.name)
            if ft and ft in self.file_to_table_mapping and not self._is_file_processed(str(p.resolve())):
                files_by_type[ft].append(p)

        total = sum(len(v) for v in files_by_type.values())
        if total == 0:
            logger.info("Aucun fichier historique à traiter (checkpoint).")
            return True

        logger.info("Fichiers à traiter (historiques): %d", total)

        for ft, files in files_by_type.items():
            logger.info("\n=== TYPE: %s (%d fichiers) ===", ft, len(files))
            if self.test_mode:
                files = files[:3]
            for p in files:
                self.process_csv_file(p, ft)

        return True

    def process_lieu_files(self):
        logger.info("=== PASSE Lieu* VECTORISÉE ===")
        if not self.data_path.exists():
            logger.error("Dossier absent: %s", self.data_path)
            return False

        lst: List[Tuple[str, Path]] = []
        for p in self.data_path.rglob("*.csv"):
            if re.search(r"semaine", p.name, re.IGNORECASE):
                continue
            ft = self.determine_lieu_file_type_strict(p.name)
            if ft:
                lst.append((ft, p))

        if self.test_mode:
            lst = lst[:max(1, min(30, len(lst)))]
            logger.info("Mode test: %d fichiers Lieu*", len(lst))

        if not lst:
            logger.info("Aucun fichier Lieu* détecté.")
            return True

        by_type = defaultdict(list)
        for ft, p in lst:
            by_type[ft].append(p)

        for ft, files in by_type.items():
            logger.info("\n=== TYPE Lieu* %s (%d fichiers) ===", ft, len(files))
            for p in files:
                self.process_lieu_csv_file(p, ft)

        return True

    def print_final_stats(self):
        logger.info("=== STATISTIQUES FINALES ===")
        total_files = sum(v for k, v in self.stats.items() if k.startswith("files_processed_"))
        total_rows = sum(v for k, v in self.stats.items() if k.startswith("rows_inserted_"))
        logger.info("Total fichiers traités: %s", f"{total_files:,}")
        logger.info("Total lignes upsertées: %s", f"{total_rows:,}")

        for table in self.lieu_file_to_table_mapping.values():
            try:
                self.cursor.execute(f"SELECT COUNT(*) FROM {table}")
                n = self.cursor.fetchone()[0]
                logger.info("%s: %s enregistrements", table, f"{n:,}")
            except Exception as e:
                logger.warning("%s: %s", table, e)

    def run_population(self):
        start = time.time()
        logger.info("=== DÉBUT ETL FLUXVISION VECTORISÉ ===")
        logger.info("Checkpoint: %s", "ACTIVÉ" if self.resume_from_checkpoint else "DÉSACTIVÉ")

        if not self.connect():
            return False
        try:
            self._ensure_dims()
            self.enforce_unique_constraints_and_cleanup()
            self.load_dimension_cache()

            ok_hist = self.process_all_csv_files()
            ok_lieu = self.process_lieu_files()
            self.print_final_stats()

            if ok_hist and ok_lieu:
                self.cleanup_checkpoint_on_success()
                self._save_checkpoint()

            logger.info("=== FIN ETL en %.2f sec ===", time.time() - start)
            return ok_hist and ok_lieu
        except KeyboardInterrupt:
            logger.warning("Interruption — sauvegarde checkpoint…")
            self._save_checkpoint()
            raise
        except Exception as e:
            logger.error("Erreur critique: %s", e)
            self._save_checkpoint()
            raise

    def close(self):
        try:
            if self.cursor:
                self.cursor.close()
            if self.connection:
                self.connection.close()
        finally:
            logger.info("Connexions fermées")


# =========================
# Script principal
# =========================

if __name__ == "__main__":
    print("=== ETL FLUXVISION VECTORISÉ — i5-1240P ===\n")
    print(f"Machine détectée: Intel i5-1240P ({CPU_COUNT} cœurs), {psutil.virtual_memory().total // (1024**3)} Go RAM")
    print(f"Optimisations: agrégation avant UPSERT, joins vectorisés, batch_size={OPTIMIZED_BATCH_SIZE}, pool_connexions={CONNECTION_POOL_SIZE}\n")

    try:
        mode = input("Mode? (t=test, p=production, f=force sans checkpoint): ").lower().strip()
    except EOFError:
        mode = "p"
    test_mode = (mode == "t")
    force_no_checkpoint = (mode == "f")
    resume_checkpoint = not force_no_checkpoint

    if not test_mode and not force_no_checkpoint:
        try:
            confirm = input("PRODUCTION - Continuer? (oui/non): ").lower().strip()
        except EOFError:
            confirm = "oui"
        if confirm != "oui":
            sys.exit(0)

    pop = FactTablePopulator(
        test_mode=test_mode,
        batch_size=OPTIMIZED_BATCH_SIZE,
        resume_from_checkpoint=resume_checkpoint,
    )
    try:
        ok = pop.run_population()
        if ok:
            print("\n🎉 ETL VECTORISÉ TERMINÉ AVEC SUCCÈS")
            print("✅ Normalisation & mapping via Polars (sans boucles)")
            print("✅ Agrégation avant UPSERT (moins de conflits/IO)")
            print("✅ dim_dates & dimensions insérées en batch")
            print("✅ Checkpoint anti-doublon")
        else:
            print("💥 ÉCHEC ETL - Voir logs")
            sys.exit(1)
    except KeyboardInterrupt:
        print("⚠️ Interrompu — checkpoint sauvegardé")
        sys.exit(1)
    except Exception as e:
        logger.error("💥 Erreur critique: %s", e)
        print("   Checkpoint sauvegardé - relancer pour reprendre")
        sys.exit(1)
    finally:
        pop.close()
