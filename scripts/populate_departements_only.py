#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ETL FluxVision — Version DEPARTEMENTS uniquement
- Familles historiques: Nuitee_Departement, Diurne_Departement, SejourDuree_Departement
- Nouvelles familles Lieu* (Activité/Nuitée × Soir/Veille) — uniquement les variantes *_Departement
- Détection stricte des Lieu* (préfixe exact avant _20YYB?)
- Exclusion automatique des fichiers contenant "Semaine"
- Création/complétion des dimensions si nécessaire
- Idempotence : UNIQUE + ON DUPLICATE KEY UPDATE (overwrite)
- Optimisé 8 Go RAM : Polars + batches executemany
- Colonnes supplémentaires sur Lieu* _Departement : id_zone_detail, id_epci, id_commune
"""

import polars as pl
import mysql.connector
from mysql.connector import Error
import logging
import sys
from pathlib import Path
from datetime import datetime, date
import time
from collections import defaultdict
import re
from typing import Optional, Tuple, List, Dict

# =========================
# Utils & logging
# =========================

def normalize_str_light(s: object, strip_accents: bool = False) -> Optional[str]:
    if s is None:
        return None
    s = str(s).strip()
    if not s:
        return None
    s = ' '.join(s.split())
    if strip_accents:
        import unicodedata
        s = ''.join(c for c in unicodedata.normalize('NFKD', s) if unicodedata.category(c) != 'Mn')
    return s.upper()

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[logging.FileHandler('etl_fluxvision_departements.log', encoding='utf-8'),
              logging.StreamHandler(sys.stdout)]
)
logger = logging.getLogger(__name__)

# =========================
# Constants — *Département only*
# =========================

ALLOWED_LIEU_PREFIXES_DEPT = {
    "LieuActivite_Soir_Departement",
    "LieuActivite_Veille_Departement",
    "LieuNuitee_Soir_Departement",
    "LieuNuitee_Veille_Departement",
}

ZONE_DETAIL_COLS_BY_TYPE = {
    'Activite_Soir':   ('ZoneDiurneSoir',   'ZoneNuiteeSoir', 'ZoneObservation', 'Zone'),
    'Activite_Veille': ('ZoneDiurneVeille', 'ZoneNuiteeVeille','ZoneObservation', 'Zone'),
    'Nuitee_Soir':     ('ZoneNuiteeSoir',   'ZoneDiurneSoir', 'ZoneObservation', 'Zone'),
    'Nuitee_Veille':   ('ZoneNuiteeVeille', 'ZoneDiurneVeille','ZoneObservation', 'Zone'),
}

EPCI_COLS = (
    'EPCIZoneNuiteeSoir','EPCIZoneDiurneSoir','EPCIZoneNuiteeVeille','EPCIZoneDiurneVeille','EPCI','NomEPCI'
)
INSEE_COLS = (
    'CodeInseeNuiteeSoir','CodeInseeDiurneSoir','CodeInseeNuiteeVeille','CodeInseeDiurneVeille','CodeInsee','CodeINSEE'
)
DEPT_FALLBACK_COLS = (
    'NomDepartement','DeptZoneDiurneSoir','DeptZoneNuiteeSoir','DeptZoneDiurneVeille','DeptZoneNuiteeVeille','Departement'
)

# =========================
# Main class
# =========================

class FactTablePopulatorDept:
    def __init__(
        self,
        host='localhost',
        port=3307,
        user='root',
        password='',
        database='fluxvision',
        data_path: Path = Path('fluxvision_automation/data/data_extracted'),
        test_mode: bool = False,
        batch_size: int = 2000,
        strip_accents: bool = False
    ):
        self.host = host
        self.port = port
        self.user = user
        self.password = password
        self.database = database
        self.connection = None
        self.cursor = None
        self.data_path = Path(data_path)

        self.test_mode = test_mode
        self.batch_size = int(batch_size)
        self.table_suffix = '_test' if test_mode else ''
        self.strip_accents = strip_accents

        # Caches dimensions
        self.dimension_cache: Dict[str, Dict[str, int]] = {}
        self.dimension_cache_extended = {'durees': {}, 'communes': {}, 'epci_by_name': {}}
        self._miss_cache = {k: set() for k in ('zones', 'provenances', 'categories', 'departements', 'communes', 'epci')}

        # Mapping fichiers (familles historiques — uniquement Département)
        self.file_to_table_mapping = {
            'Nuitee_Departement': f'fact_nuitees_departements{self.table_suffix}',
            'Diurne_Departement': f'fact_diurnes_departements{self.table_suffix}',
            'SejourDuree_Departement': f'fact_sejours_duree_departements{self.table_suffix}',
        }

        # Mapping fichiers (Lieu* — uniquement *_Departement)
        self.lieu_file_to_table_mapping = {
            "LieuActivite_Soir_Departement":  f"fact_lieu_activite_soir_departement{self.table_suffix}",
            "LieuActivite_Veille_Departement":f"fact_lieu_activite_veille_departement{self.table_suffix}",
            "LieuNuitee_Soir_Departement":    f"fact_lieu_nuitee_soir_departement{self.table_suffix}",
            "LieuNuitee_Veille_Departement":  f"fact_lieu_nuitee_veille_departement{self.table_suffix}",
        }

        self.stats = defaultdict(int)
        self._insert_stmt_cache: Dict[Tuple[str, str, str], Tuple[str, int]] = {}

        logger.info(f"[DEPARTEMENT] Mode test={self.test_mode}, batch={self.batch_size}, strip_accents={self.strip_accents}")

    # -------------------------
    # Connexion / création
    # -------------------------
    def connect(self) -> bool:
        try:
            self.connection = mysql.connector.connect(
                host=self.host, port=self.port, user=self.user, password=self.password,
                database=self.database, charset='utf8mb4', collation='utf8mb4_unicode_ci', autocommit=False
            )
            self.cursor = self.connection.cursor()
            logger.info(f"Connecté à MySQL {self.host}/{self.database}")
            return True
        except Error as e:
            logger.error(f"Erreur de connexion MySQL: {e}")
            return False

    def create_tables_if_missing(self):
        """Crée les dimensions et SEULEMENT les tables de faits Département (+ Lieu*_Departement)."""
        cur = self.cursor
        try:
            # === Dimensions minimales ===
            cur.execute("""
                CREATE TABLE IF NOT EXISTS dim_zones_observation (
                    id_zone INT AUTO_INCREMENT PRIMARY KEY,
                    nom_zone VARCHAR(255) UNIQUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)
            cur.execute("""
                CREATE TABLE IF NOT EXISTS dim_provenances (
                    id_provenance INT AUTO_INCREMENT PRIMARY KEY,
                    nom_provenance VARCHAR(255) UNIQUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)
            cur.execute("""
                CREATE TABLE IF NOT EXISTS dim_categories_visiteur (
                    id_categorie INT AUTO_INCREMENT PRIMARY KEY,
                    nom_categorie VARCHAR(255) UNIQUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)
            cur.execute("""
                CREATE TABLE IF NOT EXISTS dim_departements (
                    id_departement INT AUTO_INCREMENT PRIMARY KEY,
                    nom_departement VARCHAR(255) UNIQUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)

            # Dim annexes utiles à Lieu*_Departement
            cur.execute("""
                CREATE TABLE IF NOT EXISTS dim_communes (
                    id_commune INT AUTO_INCREMENT PRIMARY KEY,
                    code_insee VARCHAR(10) NOT NULL,
                    nom_commune VARCHAR(255) NOT NULL,
                    id_departement INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_commune_insee (code_insee),
                    KEY idx_commune_dept (id_departement)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)
            cur.execute("""
                CREATE TABLE IF NOT EXISTS dim_epci (
                    id_epci INT AUTO_INCREMENT PRIMARY KEY,
                    nom_epci VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_epci_nom (nom_epci)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)

            # dim_dates
            cur.execute("""
                CREATE TABLE IF NOT EXISTS dim_dates (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)

            # Alias zones détaillées
            cur.execute("""
                CREATE TABLE IF NOT EXISTS zone_detail_alias_map (
                    alias_label VARCHAR(255) NOT NULL,
                    id_zone INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_alias (alias_label),
                    KEY idx_zone (id_zone)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)
            # Staging des libellés non résolus
            cur.execute("""
                CREATE TABLE IF NOT EXISTS staging_zone_detail_unresolved (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    label VARCHAR(255) NOT NULL,
                    source_col VARCHAR(64) DEFAULT NULL,
                    first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_seen_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    seen_count INT NOT NULL DEFAULT 1,
                    UNIQUE KEY uq_label_source (label, source_col)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)

            # === Dimension durée séjour (pour SejourDuree_Departement) ===
            cur.execute("""
                CREATE TABLE IF NOT EXISTS dim_durees_sejour (
                    id_duree INT AUTO_INCREMENT PRIMARY KEY,
                    libelle VARCHAR(50) NOT NULL,
                    nb_nuits INT NOT NULL,
                    ordre INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_duree_libelle (libelle),
                    KEY idx_duree_nb (nb_nuits)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)

            # === Faits Département (historiques) ===
            cur.execute(f"""
                CREATE TABLE IF NOT EXISTS fact_nuitees_departements{self.table_suffix} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    date DATE NOT NULL,
                    id_zone INT NOT NULL,
                    id_provenance INT NOT NULL,
                    id_categorie INT NOT NULL,
                    id_departement INT NOT NULL,
                    volume INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq (date, id_zone, id_provenance, id_categorie, id_departement)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)
            cur.execute(f"""
                CREATE TABLE IF NOT EXISTS fact_diurnes_departements{self.table_suffix} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    date DATE NOT NULL,
                    id_zone INT NOT NULL,
                    id_provenance INT NOT NULL,
                    id_categorie INT NOT NULL,
                    id_departement INT NOT NULL,
                    volume INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq (date, id_zone, id_provenance, id_categorie, id_departement)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)
            cur.execute(f"""
                CREATE TABLE IF NOT EXISTS fact_sejours_duree_departements{self.table_suffix} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    date DATE NOT NULL,
                    id_zone INT NOT NULL,
                    id_provenance INT NOT NULL,
                    id_categorie INT NOT NULL,
                    id_departement INT NOT NULL,
                    id_duree INT NOT NULL,
                    volume INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq (date, id_zone, id_provenance, id_categorie, id_departement, id_duree)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """)

            # === Lieu* Département (avec id_zone_detail, id_epci, id_commune) ===
            def _ddl_lieu_dept(tbl_base: str) -> str:
                return f"""
                CREATE TABLE IF NOT EXISTS {tbl_base} (
                  id BIGINT AUTO_INCREMENT PRIMARY KEY,
                  date DATE NOT NULL,
                  jour_semaine VARCHAR(10) NOT NULL,
                  id_zone INT NOT NULL,
                  id_zone_detail INT NULL,
                  id_provenance INT NOT NULL,
                  id_categorie INT NOT NULL,
                  id_departement INT NOT NULL,
                  id_epci INT NULL,
                  id_commune INT NULL,
                  volume INT NOT NULL,
                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                  UNIQUE KEY uq (date,id_zone,id_zone_detail,id_provenance,id_categorie,id_departement,id_epci,id_commune),
                  KEY idx_date_zone (date, id_zone, id_zone_detail),
                  KEY idx_geo (id_departement),
                  KEY idx_jour (jour_semaine),
                  KEY idx_epci (id_epci),
                  KEY idx_commune (id_commune)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                """

            for key, tbl in self.lieu_file_to_table_mapping.items():
                cur.execute(_ddl_lieu_dept(tbl))

            self.connection.commit()
        except Exception as e:
            logger.error(f"Erreur création/altération tables: {e}")
            self.connection.rollback()

    # -------------------------
    # Cache dimensions
    # -------------------------
    def _fetch_and_build_map(self, query: str, key_idx: int, val_idx: int):
        self.cursor.execute(query)
        result = {}
        for row in self.cursor.fetchall():
            key = normalize_str_light(row[key_idx], self.strip_accents)
            if key:
                result[key] = row[val_idx]
        return result

    def load_dimension_cache(self):
        logger.info("Chargement cache dimensions...")
        self.dimension_cache['zones'] = self._fetch_and_build_map(
            "SELECT id_zone, nom_zone FROM dim_zones_observation", 1, 0
        )
        self.dimension_cache['provenances'] = self._fetch_and_build_map(
            "SELECT id_provenance, nom_provenance FROM dim_provenances", 1, 0
        )
        self.dimension_cache['categories'] = self._fetch_and_build_map(
            "SELECT id_categorie, nom_categorie FROM dim_categories_visiteur", 1, 0
        )
        self.dimension_cache['departements'] = self._fetch_and_build_map(
            "SELECT id_departement, nom_departement FROM dim_departements", 1, 0
        )
        # Communes par code_insee
        try:
            self.dimension_cache_extended['communes'] = {}
            self.cursor.execute("SELECT id_commune, code_insee FROM dim_communes")
            for _id, code in self.cursor.fetchall():
                k = normalize_str_light(code, self.strip_accents)
                if k:
                    self.dimension_cache_extended['communes'][k] = _id
        except Exception:
            self.dimension_cache_extended['communes'] = {}
        # EPCI par nom
        try:
            self.dimension_cache_extended['epci_by_name'] = {}
            self.cursor.execute("SELECT id_epci, nom_epci FROM dim_epci")
            for _id, nom in self.cursor.fetchall():
                if nom:
                    k2 = normalize_str_light(nom, self.strip_accents)
                    if k2 and k2 not in self.dimension_cache_extended['epci_by_name']:
                        self.dimension_cache_extended['epci_by_name'][k2] = _id
        except Exception:
            self.dimension_cache_extended['epci_by_name'] = {}
        # Alias pour zone détaillée
        try:
            self.dimension_cache_extended['zone_alias_map'] = {}
            self.cursor.execute("SELECT alias_label, id_zone FROM zone_detail_alias_map")
            for alias, idz in self.cursor.fetchall():
                k = normalize_str_light(alias, self.strip_accents)
                if k:
                    self.dimension_cache_extended['zone_alias_map'][k] = idz
        except Exception:
            self.dimension_cache_extended['zone_alias_map'] = {}

        logger.info("Dims: zones=%d, prov=%d, cat=%d, dep=%d, communes=%d, epci=%d",
                    len(self.dimension_cache['zones']),
                    len(self.dimension_cache['provenances']),
                    len(self.dimension_cache['categories']),
                    len(self.dimension_cache['departements']),
                    len(self.dimension_cache_extended['communes']),
                    len(self.dimension_cache_extended['epci_by_name']))

    # Création si manquante
    def _get_or_create_dim(self, table: str, id_col: str, name_col: str, raw_val) -> Optional[int]:
        v = normalize_str_light(raw_val, self.strip_accents)
        if not v:
            return None
        cache_key = {
            'dim_zones_observation':'zones',
            'dim_provenances':'provenances',
            'dim_categories_visiteur':'categories',
            'dim_departements':'departements',
        }[table]

        if v in self.dimension_cache[cache_key]:
            return self.dimension_cache[cache_key][v]
        if v in self._miss_cache[cache_key]:
            return None

        try:
            self.cursor.execute(f"INSERT IGNORE INTO {table} ({name_col}) VALUES (%s)", (v,))
            if self.cursor.lastrowid:
                new_id = self.cursor.lastrowid
            else:
                self.cursor.execute(f"SELECT {id_col} FROM {table} WHERE {name_col}=%s", (v,))
                row = self.cursor.fetchone()
                new_id = row[0] if row else None
            if new_id:
                self.dimension_cache[cache_key][v] = new_id
                return new_id
        except Exception:
            pass
        self._miss_cache[cache_key].add(v)
        return None

    def get_or_create_zone(self, name): return self._get_or_create_dim('dim_zones_observation','id_zone','nom_zone', name)
    def get_zone_if_exists(self, name):
        v = normalize_str_light(name, self.strip_accents)
        if not v:
            return None
        return self.dimension_cache.get('zones', {}).get(v)
    def get_or_create_prov(self, name): return self._get_or_create_dim('dim_provenances','id_provenance','nom_provenance', name)
    def get_or_create_cat(self,  name): return self._get_or_create_dim('dim_categories_visiteur','id_categorie','nom_categorie', name)
    def get_or_create_dep(self,  name): return self._get_or_create_dim('dim_departements','id_departement','nom_departement', name)

    # Communes & EPCI
    def get_or_create_commune(self, code_insee, nom_commune=None, dep_name=None) -> Optional[int]:
        code_key = normalize_str_light(code_insee, self.strip_accents)
        if not code_key:
            return None
        if code_key in self.dimension_cache_extended['communes']:
            return self.dimension_cache_extended['communes'][code_key]
        id_departement = None
        if dep_name:
            id_departement = self.get_or_create_dep(dep_name)
        try:
            if nom_commune is None:
                nom_commune = ''
            nom_key = normalize_str_light(nom_commune, self.strip_accents) or ''
            self.cursor.execute(
                """
                INSERT INTO dim_communes (code_insee, nom_commune, id_departement)
                VALUES (%s,%s,%s)
                ON DUPLICATE KEY UPDATE nom_commune = VALUES(nom_commune), id_departement = COALESCE(VALUES(id_departement), id_departement)
                """,
                (code_key, nom_key, id_departement)
            )
            if self.cursor.lastrowid:
                new_id = self.cursor.lastrowid
            else:
                self.cursor.execute("SELECT id_commune FROM dim_communes WHERE code_insee=%s", (code_key,))
                row = self.cursor.fetchone()
                new_id = row[0] if row else None
            if new_id:
                self.dimension_cache_extended['communes'][code_key] = new_id
                return new_id
        except Exception as e:
            logger.warning(f"get_or_create_commune: {e}")
            return None
        return None

    def get_or_create_epci(self, nom_epci: Optional[str] = None) -> Optional[int]:
        if not nom_epci:
            return None
        k2 = normalize_str_light(nom_epci, self.strip_accents)
        if not k2:
            return None
        if k2 in self.dimension_cache_extended['epci_by_name']:
            return self.dimension_cache_extended['epci_by_name'][k2]
        try:
            self.cursor.execute(
                """
                INSERT INTO dim_epci (nom_epci) VALUES (%s)
                ON DUPLICATE KEY UPDATE id_epci = LAST_INSERT_ID(id_epci)
                """,
                (k2,)
            )
            new_id = self.cursor.lastrowid
            if new_id:
                self.dimension_cache_extended['epci_by_name'][k2] = new_id
                return new_id
        except Exception as e:
            logger.warning(f"get_or_create_epci: {e}")
            return None
        return None

    # Alias zones détaillées
    def get_zone_by_alias(self, alias_label: Optional[str]) -> Optional[int]:
        k = normalize_str_light(alias_label, self.strip_accents)
        if not k:
            return None
        return self.dimension_cache_extended.get('zone_alias_map', {}).get(k)

    def record_unresolved_zone_detail(self, label: Optional[str], source_col: Optional[str]):
        try:
            k = normalize_str_light(label, self.strip_accents)
            if not k:
                return
            self.cursor.execute(
                """
                INSERT INTO staging_zone_detail_unresolved (label, source_col, seen_count)
                VALUES (%s, %s, 1)
                ON DUPLICATE KEY UPDATE seen_count = seen_count + 1, last_seen_at = CURRENT_TIMESTAMP
                """,
                (k, source_col)
            )
            self.connection.commit()
        except Exception:
            self.connection.rollback()

    # -------------------------
    # Outils
    # -------------------------
    @staticmethod
    def parse_date(date_str) -> Optional[date]:
        if not date_str:
            return None
        try:
            if isinstance(date_str, str) and len(date_str) == 10:
                return datetime.strptime(date_str, '%Y-%m-%d').date()
            elif isinstance(date_str, date):
                return date_str
        except Exception:
            return None
        return None

    def insert_date_if_not_exists(self, date_obj, vacances_a=0, vacances_b=0, vacances_c=0, ferie=0, jour_semaine=''):
        if not date_obj:
            return
        try:
            self.cursor.execute(
                """
                INSERT IGNORE INTO dim_dates
                (date, vacances_a, vacances_b, vacances_c, ferie, jour_semaine, mois, annee, trimestre, semaine)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """,
                (
                    date_obj, vacances_a, vacances_b, vacances_c, ferie, jour_semaine,
                    date_obj.month, date_obj.year, (date_obj.month - 1) // 3 + 1, date_obj.isocalendar()[1]
                )
            )
        except Exception:
            pass

    def get_or_create_duree_id(self, libelle, nb_nuits=None) -> Optional[int]:
        try:
            libelle_key = normalize_str_light(libelle, self.strip_accents)
            if not libelle_key:
                return None
            if libelle_key in self.dimension_cache_extended['durees']:
                return self.dimension_cache_extended['durees'][libelle_key]

            try:
                nb_nuits_int = int(nb_nuits) if nb_nuits not in (None, '') else None
            except Exception:
                nb_nuits_int = None

            self.cursor.execute(
                """
                INSERT INTO dim_durees_sejour (libelle, nb_nuits, ordre)
                VALUES (%s, %s, %s)
                ON DUPLICATE KEY UPDATE id_duree = LAST_INSERT_ID(id_duree)
                """,
                (libelle_key, nb_nuits_int, nb_nuits_int)
            )
            new_id = self.cursor.lastrowid
            if new_id:
                self.dimension_cache_extended['durees'][libelle_key] = new_id
            return new_id
        except Exception:
            return None

    # -------------------------
    # Détection fichiers (Département only)
    # -------------------------
    @staticmethod
    def determine_file_type(filename: str) -> Optional[str]:
        base_name = filename.replace('.csv', '')
        if re.search(r'semaine', base_name, re.IGNORECASE):
            return None

        if re.match(r'^SejourDuree_Departement_(20\d{2}B\d+).*$', base_name, re.IGNORECASE):
            return 'SejourDuree_Departement'
        if re.match(r'^Nuitee_Departement_20\d{2}B\d+', base_name, re.IGNORECASE):
            return 'Nuitee_Departement'
        if re.match(r'^Diurne_Departement_20\d{2}B\d+', base_name, re.IGNORECASE):
            return 'Diurne_Departement'

        # Tout le reste (Pays, Global, Régions, etc.) est ignoré.
        return None

    @staticmethod
    def determine_lieu_file_type_strict(filename: str) -> Optional[str]:
        if re.search(r'semaine', filename, re.IGNORECASE):
            return None
        base = filename[:-4] if filename.lower().endswith('.csv') else filename
        parts = re.split(r'_(?=20\d{2}B)', base)
        if len(parts) < 2:
            return None
        prefix = parts[0]
        return prefix if prefix in ALLOWED_LIEU_PREFIXES_DEPT else None

    # -------------------------
    # Insert helpers (overwrite only)
    # -------------------------
    def _prepare_insert_statement(self, table_name: str, file_type: str) -> Tuple[str, int]:
        key = (table_name, file_type, 'overwrite')
        if key in self._insert_stmt_cache:
            return self._insert_stmt_cache[key]

        if file_type == 'SejourDuree_Departement':
            cols = "(date, id_zone, id_provenance, id_categorie, id_departement, id_duree, volume)"
            placeholders = "(%s,%s,%s,%s,%s,%s,%s)"
            arity = 7
        elif file_type in ('Nuitee_Departement','Diurne_Departement'):
            cols = "(date, id_zone, id_provenance, id_categorie, id_departement, volume)"
            placeholders = "(%s,%s,%s,%s,%s,%s)"
            arity = 6
        else:
            raise ValueError(f"Type historique inattendu: {file_type}")

        upd = "volume = VALUES(volume)"
        query = f"INSERT INTO {table_name} {cols} VALUES {placeholders} ON DUPLICATE KEY UPDATE {upd}"
        self._insert_stmt_cache[key] = (query, arity)
        return query, arity

    def _prepare_lieu_insert_statement(self, table_name: str) -> Tuple[str, int]:
        key = (table_name, 'lieu_dept', 'overwrite')
        if key in self._insert_stmt_cache:
            return self._insert_stmt_cache[key]
        cols = "(date,jour_semaine,id_zone,id_zone_detail,id_provenance,id_categorie,id_departement,id_epci,id_commune,volume)"
        arity = 10
        upd = "volume = VALUES(volume)"
        query = f"INSERT INTO {table_name} {cols} VALUES ({','.join(['%s']*arity)}) ON DUPLICATE KEY UPDATE {upd}"
        self._insert_stmt_cache[key] = (query, arity)
        return query, arity

    # -------------------------
    # Process historic rows
    # -------------------------
    def process_row(self, row, file_type):
        try:
            date_obj = self.parse_date(row.get('Date'))
            if not date_obj:
                return None

            self.insert_date_if_not_exists(
                date_obj,
                row.get('VacancesA', row.get('vacances_a', 0)),
                row.get('VacancesB', row.get('vacances_b', 0)),
                row.get('VacancesC', row.get('vacances_c', 0)),
                row.get('Ferie', row.get('ferie', 0)),
                row.get('JourDeLaSemaine', row.get('jour_semaine', ''))
            )

            zname = row.get('ZoneObservation')
            id_zone = self.get_or_create_zone(zname) if zname else None
            id_provenance = self.get_or_create_prov(row.get('Provenance'))
            id_categorie = self.get_or_create_cat(row.get('CategorieVisiteur'))
            if not all([id_zone, id_provenance, id_categorie]):
                return None

            dept_name = row.get('NomDepartement')
            if not dept_name:
                return None
            id_departement = self.get_or_create_dep(dept_name)
            if not id_departement:
                return None

            volume = row.get('Volume', 0)
            try:
                volume = int(volume) if volume is not None else 0
            except Exception:
                volume = 0
            if volume <= 0:
                return None

            if file_type == 'SejourDuree_Departement':
                libelle = row.get('DureeSejour')
                nb = row.get('DureeSejourNum')
                id_duree = self.get_or_create_duree_id(libelle, nb)
                if not id_duree:
                    return None
                return (date_obj, id_zone, id_provenance, id_categorie, id_departement, id_duree, volume)

            # Nuitee_Departement / Diurne_Departement
            return (date_obj, id_zone, id_provenance, id_categorie, id_departement, volume)

        except Exception:
            return None

    # -------------------------
    # Helpers Lieu*
    # -------------------------
    def _pick_zone_detail(self, row: dict, file_type: str) -> Optional[str]:
        core = None
        if 'Activite' in file_type:
            core = 'Activite_Soir' if 'Soir' in file_type else 'Activite_Veille'
        elif 'Nuitee' in file_type:
            core = 'Nuitee_Soir' if 'Soir' in file_type else 'Nuitee_Veille'
        cols = ZONE_DETAIL_COLS_BY_TYPE.get(core, ())
        for c in cols:
            v = row.get(c)
            if v and str(v).strip():
                return v
        return row.get('ZoneObservation', row.get('Zone'))

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

    # -------------------------
    # Process Lieu* rows (Département)
    # -------------------------
    def process_lieu_row_tuple(self, row: dict, file_type: str) -> Optional[Tuple]:
        try:
            date_obj = self.parse_date(row.get('Date'))
            if not date_obj:
                return None

            self.insert_date_if_not_exists(
                date_obj,
                row.get('VacancesA', row.get('vacances_a', 0)),
                row.get('VacancesB', row.get('vacances_b', 0)),
                row.get('VacancesC', row.get('vacances_c', 0)),
                row.get('Ferie', row.get('ferie', 0)),
                row.get('JourDeLaSemaine', row.get('jour_semaine', ''))
            )

            zname = row.get('ZoneObservation')
            id_zone = self.get_or_create_zone(zname) if zname else None
            id_provenance = self.get_or_create_prov(row.get('Provenance'))
            id_categorie = self.get_or_create_cat(row.get('CategorieVisiteur'))
            if not all([id_zone, id_provenance, id_categorie]):
                return None

            # Zone détaillée (commune le plus souvent)
            zone_detail = self._pick_zone_detail(row, file_type)
            id_zone_detail = None
            if zone_detail:
                id_zone_detail = self.get_zone_by_alias(zone_detail) or self.get_zone_if_exists(zone_detail)
                if not id_zone_detail:
                    src_col = 'Zone*Soir' if 'Soir' in file_type else 'Zone*Veille'
                    self.record_unresolved_zone_detail(zone_detail, src_col)

            # EPCI / Commune
            epci_name = self._pick_epci_name(row)
            id_epci = self.get_or_create_epci(nom_epci=epci_name) if epci_name else None

            code_insee = self._pick_insee(row)
            dep_name = self._pick_departement_name(row)
            commune_name = zone_detail
            id_commune = self.get_or_create_commune(code_insee, commune_name, dep_name) if code_insee else None

            volume = row.get('Volume', 0)
            try:
                volume = int(volume) if volume is not None else 0
            except Exception:
                volume = 0
            if volume <= 0:
                return None

            id_dep = self.get_or_create_dep(dep_name) if dep_name else None
            if not id_dep:
                return None
            js = row.get('JourDeLaSemaine', row.get('jour_semaine', ''))
            return (date_obj, js, id_zone, id_zone_detail, id_provenance, id_categorie, id_dep, id_epci, id_commune, volume)

        except Exception as e:
            logger.error(f"process_lieu_row_tuple: {e}")
            return None

    # -------------------------
    # Insert batches (overwrite)
    # -------------------------
    def insert_batch(self, table_name: str, file_type: str, values: List[Tuple]) -> int:
        if not values:
            return 0
        try:
            query, _arity = self._prepare_insert_statement(table_name, file_type)
            self.cursor.executemany(query, values)
            self.connection.commit()
            return len(values)
        except Exception as e:
            logger.error(f"Erreur insertion {table_name}: {e}")
            self.connection.rollback()
            return 0

    def insert_batch_lieu(self, table_name: str, values: List[Tuple]) -> int:
        if not values:
            return 0
        try:
            query, _arity = self._prepare_lieu_insert_statement(table_name)
            self.cursor.executemany(query, values)
            self.connection.commit()
            return len(values)
        except Exception as e:
            logger.error(f"Erreur insertion {table_name}: {e}")
            self.connection.rollback()
            return 0

    # -------------------------
    # CSV processors
    # -------------------------
    def process_csv_file(self, csv_file: Path, file_type: str) -> int:
        logger.info(f"[HIST-DEPT] {csv_file.name} -> {file_type}")
        target_table = self.file_to_table_mapping.get(file_type)
        if not target_table:
            return 0

        use_cols = {
            "Date","ZoneObservation","Zone","Provenance","CategorieVisiteur","Volume",
            "DureeSejour","DureeSejourNum","NomDepartement",
            "VacancesA","VacancesB","VacancesC","Ferie","JourDeLaSemaine",
            "vacances_a","vacances_b","vacances_c","ferie","jour_semaine"
        }

        try:
            df = pl.read_csv(csv_file, separator=';', infer_schema_length=300)
            if 'Volume' in df.columns:
                df = df.with_columns(pl.col('Volume').cast(pl.Int32, strict=False))
            avail = [c for c in df.columns if c in use_cols]
            if avail:
                df = df.select(avail)
            if df.height == 0:
                return 0

            batch_vals: List[Tuple] = []
            total_inserted = 0

            for row in df.iter_rows(named=True):
                tup = self.process_row(row, file_type)
                if tup:
                    batch_vals.append(tup)
                if len(batch_vals) >= self.batch_size:
                    total_inserted += self.insert_batch(target_table, file_type, batch_vals)
                    batch_vals.clear()
                if self.test_mode and total_inserted >= 10000:
                    break

            if batch_vals:
                total_inserted += self.insert_batch(target_table, file_type, batch_vals)

            self.stats[f'files_processed_{file_type}'] += 1
            self.stats[f'rows_inserted_{file_type}'] += total_inserted
            logger.info(f"  -> {total_inserted:,} lignes insérées/merge")

            return total_inserted

        except Exception as e:
            logger.error(f"Erreur traitement {csv_file.name}: {e}")
            return 0

    def process_lieu_csv_file(self, csv_file: Path, file_type: str) -> int:
        logger.info(f"[Lieu-DEPT] {csv_file.name} -> {file_type}")
        table_name = self.lieu_file_to_table_mapping.get(file_type)
        if not table_name:
            return 0

        use_cols = {
            'Date','VacancesA','VacancesB','VacancesC','Ferie','JourDeLaSemaine',
            'vacances_a','vacances_b','vacances_c','ferie','jour_semaine',
            'Provenance','ZoneObservation','Zone','CategorieVisiteur','Volume',
            'NomDepartement','Departement',
            'DeptZoneDiurneSoir','DeptZoneNuiteeSoir','DeptZoneDiurneVeille','DeptZoneNuiteeVeille',
            'ZoneDiurneSoir','ZoneNuiteeSoir','ZoneDiurneVeille','ZoneNuiteeVeille',
            'EPCIZoneNuiteeSoir','EPCIZoneDiurneSoir','EPCIZoneNuiteeVeille','EPCIZoneDiurneVeille','EPCI','NomEPCI',
            'CodeInseeNuiteeSoir','CodeInseeDiurneSoir','CodeInseeNuiteeVeille','CodeInseeDiurneVeille','CodeInsee','CodeINSEE'
        }
        try:
            df = pl.read_csv(csv_file, separator=';', infer_schema_length=300)
            if 'Volume' in df.columns:
                df = df.with_columns(pl.col('Volume').cast(pl.Int32, strict=False))
            cols = [c for c in df.columns if c in use_cols]
            if cols:
                df = df.select(cols)
            if df.height == 0:
                return 0

            vals, total = [], 0
            for row in df.iter_rows(named=True):
                tup = self.process_lieu_row_tuple(row, file_type)
                if tup:
                    vals.append(tup)
                if len(vals) >= self.batch_size:
                    total += self.insert_batch_lieu(table_name, vals)
                    vals.clear()
                if self.test_mode and total >= 10000:
                    break
            if vals:
                total += self.insert_batch_lieu(table_name, vals)

            self.stats[f'files_processed_{file_type}'] += 1
            self.stats[f'rows_inserted_{file_type}'] += total
            logger.info(f"  -> {total:,} lignes insérées/merge")
            return total
        except Exception as e:
            logger.error(f"Erreur Lieu* {csv_file.name}: {e}")
            self.connection.rollback()
            return 0

    # -------------------------
    # Orchestration (parcourt TOUS les sous-dossiers)
    # -------------------------
    def process_all_csv_files(self):
        logger.info("=== PASSE HISTORIQUE (Département uniquement) ===")
        if not self.data_path.exists():
            logger.error(f"Dossier absent: {self.data_path}")
            return False

        files_by_type = defaultdict(list)
        for csv_file in self.data_path.rglob("*.csv"):
            ft = self.determine_file_type(csv_file.name)
            if ft and ft in self.file_to_table_mapping:
                files_by_type[ft].append(csv_file)

        for file_type, files in files_by_type.items():
            logger.info(f"\n=== TYPE: {file_type} ({len(files)} fichiers) ===")
            if self.test_mode:
                files = files[:3]
                logger.info(f"Mode test: {len(files)} fichiers")
            for csv_file in files:
                self.process_csv_file(csv_file, file_type)

        return True

    def process_lieu_files(self) -> bool:
        logger.info("=== PASSE Lieu* DEPARTEMENT (Activité/Nuitée × Soir/Veille) ===")
        if not self.data_path.exists():
            logger.error(f"Dossier absent: {self.data_path}")
            return False

        files: List[Tuple[str, Path]] = []
        for csv_file in self.data_path.rglob("*.csv"):
            if re.search(r'semaine', csv_file.name, re.IGNORECASE):
                continue
            ft = self.determine_lieu_file_type_strict(csv_file.name)
            if ft:
                files.append((ft, csv_file))

        if self.test_mode:
            files = files[:max(1, min(30, len(files)))]
            logger.info(f"Mode test: {len(files)} fichiers Lieu* Dept")

        if not files:
            logger.info("Aucun fichier Lieu* Département détecté.")
            return True

        by_type = defaultdict(list)
        for ft, p in files:
            by_type[ft].append(p)

        for ft, lst in by_type.items():
            logger.info(f"\n=== TYPE Lieu* {ft} ({len(lst)} fichiers) ===")
            for p in lst:
                self.process_lieu_csv_file(p, ft)

        return True

    def print_final_stats(self):
        logger.info("=== STATISTIQUES FINALES (Département) ===")
        total_files = sum(v for k, v in self.stats.items() if k.startswith('files_processed_'))
        total_rows = sum(v for k, v in self.stats.items() if k.startswith('rows_inserted_'))
        logger.info(f"Total fichiers traités: {total_files:,}")
        logger.info(f"Total lignes insérées/merge: {total_rows:,}")
        for k in sorted(self.stats.keys()):
            if k.startswith('files_processed_'):
                ft = k.replace('files_processed_', '')
                files = self.stats[k]
                rows = self.stats.get(f'rows_inserted_{ft}', 0)
                logger.info(f"  {ft}: {files} fichiers, {rows:,} lignes")
        for table_name in list(self.file_to_table_mapping.values()) + list(self.lieu_file_to_table_mapping.values()):
            try:
                self.cursor.execute(f"SELECT COUNT(*) FROM {table_name}")
                count = self.cursor.fetchone()[0]
                logger.info(f"{table_name}: {count:,} enregistrements")
            except Exception as e:
                logger.warning(f"{table_name}: {e}")

    def run_population(self):
        start_time = time.time()
        logger.info("=== DÉBUT ETL FLUXVISION (DEPARTEMENTS) ===")

        if not self.connect():
            return False

        try:
            self.cursor.execute("SET FOREIGN_KEY_CHECKS=0")
            self.cursor.execute("SET UNIQUE_CHECKS=0")
        except Exception:
            pass

        self.create_tables_if_missing()
        self.load_dimension_cache()

        ok_hist = self.process_all_csv_files()
        ok_lieu = self.process_lieu_files()
        self.print_final_stats()

        try:
            self.cursor.execute("SET UNIQUE_CHECKS=1")
            self.cursor.execute("SET FOREIGN_KEY_CHECKS=1")
        except Exception:
            pass

        duration = time.time() - start_time
        logger.info(f"=== FIN ETL (DEPARTEMENTS) en {duration:.2f} sec ===")
        return ok_hist and ok_lieu

    def close(self):
        if self.cursor:
            self.cursor.close()
        if self.connection:
            self.connection.close()
        logger.info("Connexions fermées")


# =========================
# Script principal
# =========================
if __name__ == "__main__":
    print("=== ETL FLUXVISION — DEPARTEMENTS UNIQUEMENT ===")
    print("Alimente les tables département (historique + Lieu*_Departement)\n")

    try:
        mode = input("Mode? (t=test, p=production): ").lower().strip()
    except EOFError:
        mode = 'p'
    test_mode = (mode == 't')

    if not test_mode:
        try:
            confirm = input("PRODUCTION - Continuer? (oui/non): ").lower().strip()
        except EOFError:
            confirm = 'oui'
        if confirm != 'oui':
            sys.exit(0)

    populator = FactTablePopulatorDept(
        test_mode=test_mode,
        batch_size=2000,
        strip_accents=False  # passe à True si tu normalises sans accents côté SQL
    )

    try:
        if populator.run_population():
            print("\n🎉 ETL TERMINÉ (Départements)")
            print("✅ Fichiers historiques & Lieu*_Departement uniquement")
            print("✅ Overwrite idempotent")
            print("✅ Dimensions créées/complétées si nécessaire")
        else:
            print("💥 ÉCHEC ETL")
            sys.exit(1)
    except KeyboardInterrupt:
        print("⚠️ Interrompu par l'utilisateur")
        sys.exit(1)
    except Exception as e:
        logger.error(f"💥 Erreur critique: {e}")
        sys.exit(1)
    finally:
        populator.close()
