#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script de création et d'alimentation de la base de données FluxVision
Utilise Polars pour le traitement des données CSV

CORRECTION IMPORTANTE:
- Les tables de faits des régions (fact_nuitees_regions, fact_diurnes_regions) 
  sont créées à partir des données des fichiers départements
- Les données sont agrégées par région au lieu d'utiliser des fichiers Regions.csv inexistants
- L'agrégation se fait par (date, zone, provenance, categorie, region)


Date: 2025
"""

import polars as pl
import mysql.connector
from mysql.connector import Error
import logging
import sys
from pathlib import Path
from datetime import datetime
import time
import os
import gc
# import psutil  # Commented out for now

# Configuration du logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('fluxvision_migration.log'),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

class FluxVisionDatabaseCreator:
    def __init__(self, host='localhost', port=3307, user='root', password='', database='fluxvision', 
                 low_memory=True, batch_size=5000, chunk_size=10000, 
                 test_mode=False, test_rows=100):
        self.host = host
        self.port = port
        self.user = user
        self.password = password
        self.database = database
        self.connection = None
        self.cursor = None
        self.data_path = Path('static/data/data_clean/data_merged')
        
        # Optimisations mémoire
        self.low_memory = low_memory
        self.batch_size = batch_size if low_memory else 1000
        self.chunk_size = chunk_size  # Taille des chunks de lecture CSV
        
        # Mode test
        self.test_mode = test_mode
        self.test_rows = test_rows
        self.table_suffix = '_test' if test_mode else ''
        
        # Statistiques
        self.stats = {
            'files_processed': 0,
            'total_records': 0,
            'dimension_records': 0,
            'fact_records': 0
        }
        
        logger.info(f"Mode mémoire faible: {low_memory}")
        logger.info(f"Batch size: {self.batch_size}")
        logger.info(f"Chunk size: {self.chunk_size}")
        
        if test_mode:
            logger.info(f"[TEST] MODE TEST ACTIVE: {test_rows} lignes par fichier")
            logger.info(f"Suffixe tables: '{self.table_suffix}'")
        else:
            logger.info("[PRODUCTION] MODE PRODUCTION: Tous les fichiers complets")
        
    def connect(self):
        """Connexion à MySQL"""
        try:
            self.connection = mysql.connector.connect(
                host=self.host,
                port=self.port,
                user=self.user,
                password=self.password,
                charset='utf8mb4',
                collation='utf8mb4_unicode_ci'
            )
            self.cursor = self.connection.cursor()
            logger.info(f"Connexion réussie à MySQL {self.host}")
            return True
        except Error as e:
            logger.error(f"Erreur de connexion MySQL: {e}")
            return False
    
    def create_database(self):
        """Sélection de la base de données existante"""
        try:
            self.cursor.execute(f"USE {self.database}")
            self.connection.commit()
            logger.info(f"Base de données '{self.database}' sélectionnée")
            return True
        except Error as e:
            logger.error(f"Erreur sélection base de données '{self.database}': {e}")
            logger.error("Vérifiez que la base de données 'fluxvision' existe")
            return False
    
    def get_table_name(self, base_name):
        """Génère le nom de table avec suffixe selon le mode"""
        return f"{base_name}{self.table_suffix}"
    
    def clean_test_tables(self):
        """Nettoie les tables de test existantes"""
        if not self.test_mode:
            return True
            
        logger.info("[CLEANUP] Nettoyage des tables de test existantes...")
        
        # Liste de toutes les tables potentielles
        all_tables = [
            'dim_dates', 'dim_zones_observation', 'dim_provenances', 
            'dim_categories_visiteur', 'dim_departements', 'dim_regions', 'dim_pays',
            'dim_tranches_age', 'dim_segments_geolife',
            'fact_nuitees', 'fact_diurnes', 'fact_nuitees_departements',
            'fact_diurnes_departements', 'fact_nuitees_regions', 'fact_diurnes_regions',
            'fact_nuitees_pays', 'fact_diurnes_pays', 'fact_nuitees_age', 'fact_diurnes_age', 
            'fact_nuitees_geolife', 'fact_diurnes_geolife'
        ]
        
        for table in all_tables:
            test_table_name = self.get_table_name(table)
            try:
                self.cursor.execute(f"DROP TABLE IF EXISTS {test_table_name}")
                self.connection.commit()
            except Error as e:
                logger.warning(f"Impossible de supprimer {test_table_name}: {e}")
        
        logger.info("[CLEANUP] Nettoyage termine")
        return True
    
    def clean_all_tables(self):
        """Supprime toutes les tables dans le bon ordre (faits puis dimensions)"""
        try:
            logger.info("=== SUPPRESSION COMPLÈTE DE TOUTES LES TABLES ===")
            
            # Désactiver les contraintes de clés étrangères temporairement
            self.cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
            
            # Tables de faits à supprimer en premier
            fact_tables = [
                'fact_nuitees', 'fact_diurnes', 
                'fact_nuitees_departements', 'fact_nuitees_regions', 'fact_nuitees_pays', 'fact_nuitees_age', 'fact_nuitees_geolife',
                'fact_diurnes_departements', 'fact_diurnes_regions', 'fact_diurnes_pays', 'fact_diurnes_age', 'fact_diurnes_geolife'
            ]
            
            # Tables de dimension à supprimer après
            dimension_tables = [
                'dim_regions', 'dim_segments_geolife', 'dim_tranches_age', 'dim_pays', 
                'dim_departements', 'dim_categories_visiteur', 'dim_provenances', 
                'dim_zones_observation', 'dim_dates'
            ]
            
            # Supprimer toutes les tables de faits
            for table_name in fact_tables:
                try:
                    drop_query = f"DROP TABLE IF EXISTS {self.get_table_name(table_name)}"
                    self.cursor.execute(drop_query)
                    logger.info(f"Table de faits {table_name} supprimée")
                except Error as e:
                    logger.debug(f"Table {table_name} n'existait pas: {e}")
            
            # Supprimer toutes les tables de dimension
            for table_name in dimension_tables:
                try:
                    drop_query = f"DROP TABLE IF EXISTS {self.get_table_name(table_name)}"
                    self.cursor.execute(drop_query)
                    logger.info(f"Table de dimension {table_name} supprimée")
                except Error as e:
                    logger.debug(f"Table {table_name} n'existait pas: {e}")
            
            # Réactiver les contraintes
            self.cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
            self.connection.commit()
            
            logger.info("Suppression complète des tables terminée")
            return True
            
        except Error as e:
            logger.error(f"Erreur lors de la suppression complète: {e}")
            # Toujours réactiver les contraintes en cas d'erreur
            try:
                self.cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
                self.connection.commit()
            except:
                pass
            return False
    
    def create_dimension_tables(self):
        """Création des tables de dimension"""
        
        logger.info("=== CRÉATION DES TABLES DE DIMENSION ===")
        
        # Créer chaque table individuellement avec le bon nom
        tables_to_create = [
            ('dim_dates', f"""CREATE TABLE {self.get_table_name('dim_dates')} (
                    date DATE PRIMARY KEY COMMENT 'Date observation',
                    vacances_a BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Vacances zone A',
                    vacances_b BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Vacances zone B', 
                    vacances_c BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Vacances zone C',
                    ferie BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Jour ferie',
                    jour_semaine VARCHAR(10) NOT NULL COMMENT 'Jour de la semaine',
                    mois TINYINT NOT NULL COMMENT 'Mois 1-12',
                    annee SMALLINT NOT NULL COMMENT 'Annee',
                    trimestre TINYINT NOT NULL COMMENT 'Trimestre 1-4',
                    semaine TINYINT NOT NULL COMMENT 'Semaine annee 1-53',
                    INDEX idx_annee_mois (annee, mois),
                    INDEX idx_trimestre (annee, trimestre),
                    INDEX idx_jour_semaine (jour_semaine)
                ) ENGINE=InnoDB COMMENT='Dimension temporelle'"""),
            
            ('dim_zones_observation', f"""CREATE TABLE {self.get_table_name('dim_zones_observation')} (
                    id_zone INT AUTO_INCREMENT PRIMARY KEY,
                    nom_zone VARCHAR(50) NOT NULL UNIQUE COMMENT 'Zone observation',
                    INDEX idx_nom_zone (nom_zone)
                ) ENGINE=InnoDB COMMENT='Zones geographiques observation'"""),
            
            ('dim_provenances', f"""CREATE TABLE {self.get_table_name('dim_provenances')} (
                    id_provenance INT AUTO_INCREMENT PRIMARY KEY,
                    nom_provenance VARCHAR(50) NOT NULL UNIQUE COMMENT 'Origine geographique',
                    INDEX idx_nom_provenance (nom_provenance)
                ) ENGINE=InnoDB COMMENT='Origines geographiques des visiteurs'"""),
            
            ('dim_categories_visiteur', f"""CREATE TABLE {self.get_table_name('dim_categories_visiteur')} (
                    id_categorie INT AUTO_INCREMENT PRIMARY KEY,
                    nom_categorie VARCHAR(50) NOT NULL UNIQUE COMMENT 'Type de visiteur',
                    INDEX idx_nom_categorie (nom_categorie)
                ) ENGINE=InnoDB COMMENT='Types de visiteurs'"""),
            
            ('dim_departements', f"""CREATE TABLE {self.get_table_name('dim_departements')} (
                    id_departement INT AUTO_INCREMENT PRIMARY KEY,
                    nom_departement VARCHAR(100) NOT NULL COMMENT 'Nom du departement',
                    nom_region VARCHAR(100) NOT NULL COMMENT 'Ancienne region',
                    nom_nouvelle_region VARCHAR(100) NOT NULL COMMENT 'Nouvelle region administrative',
                    UNIQUE KEY uk_departement_regions (nom_departement, nom_region, nom_nouvelle_region),
                    INDEX idx_nom_departement (nom_departement),
                    INDEX idx_nouvelle_region (nom_nouvelle_region)
                ) ENGINE=InnoDB COMMENT='Departements et regions'"""),
            
            ('dim_pays', f"""CREATE TABLE {self.get_table_name('dim_pays')} (
                    id_pays INT AUTO_INCREMENT PRIMARY KEY,
                    nom_pays VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nom du pays',
                    INDEX idx_nom_pays (nom_pays)
                ) ENGINE=InnoDB COMMENT='Pays de provenance'"""),
            
            ('dim_tranches_age', f"""CREATE TABLE {self.get_table_name('dim_tranches_age')} (
                    id_age INT AUTO_INCREMENT PRIMARY KEY,
                    tranche_age VARCHAR(20) NOT NULL UNIQUE COMMENT 'Tranche age',
                    age_min TINYINT COMMENT 'Age minimum de la tranche',
                    age_max TINYINT COMMENT 'Age maximum de la tranche',
                    INDEX idx_tranche_age (tranche_age),
                    INDEX idx_age_range (age_min, age_max)
                ) ENGINE=InnoDB COMMENT='Tranches age des visiteurs'"""),
            
            ('dim_segments_geolife', f"""CREATE TABLE {self.get_table_name('dim_segments_geolife')} (
                    id_geolife INT AUTO_INCREMENT PRIMARY KEY,
                    nom_segment VARCHAR(100) NOT NULL UNIQUE COMMENT 'Segment geolife',
                    description TEXT COMMENT 'Description du segment',
                    INDEX idx_nom_segment (nom_segment)
                ) ENGINE=InnoDB COMMENT='Segments de style de vie geographique'"""),
            
            ('dim_regions', f"""CREATE TABLE {self.get_table_name('dim_regions')} (
                    id_region INT AUTO_INCREMENT PRIMARY KEY,
                    nom_region VARCHAR(100) NOT NULL COMMENT 'Ancienne region',
                    nom_nouvelle_region VARCHAR(100) NOT NULL COMMENT 'Nouvelle region administrative',
                    UNIQUE KEY uk_regions (nom_region, nom_nouvelle_region),
                    INDEX idx_nom_region (nom_region),
                    INDEX idx_nom_nouvelle_region (nom_nouvelle_region)
                ) ENGINE=InnoDB COMMENT='Regions administratives anciennes et nouvelles'""")
        ]
        
        for table_name, query in tables_to_create:
            try:
                self.cursor.execute(query)
                self.connection.commit()
                logger.info(f"Table {self.get_table_name(table_name)} creee avec succes")
            except Error as e:
                logger.error(f"Erreur creation table {table_name}: {e}")
                return False
        
        return True
    
    def create_fact_tables(self):
        """Création des tables de faits"""
        
        logger.info("=== CRÉATION DES TABLES DE FAITS ===")
        
        fact_queries = {
            'fact_nuitees': f"""CREATE TABLE {self.get_table_name('fact_nuitees')} (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    date DATE NOT NULL COMMENT 'Date observation',
                    id_zone INT NOT NULL COMMENT 'Zone observation',
                    id_provenance INT NOT NULL COMMENT 'Provenance des visiteurs',
                    id_categorie INT NOT NULL COMMENT 'Categorie de visiteur',
                    volume INT NOT NULL DEFAULT 0 COMMENT 'Nombre de nuitees',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (date) REFERENCES {self.get_table_name('dim_dates')}(date) ON DELETE CASCADE,
                    FOREIGN KEY (id_zone) REFERENCES {self.get_table_name('dim_zones_observation')}(id_zone) ON DELETE CASCADE,
                    FOREIGN KEY (id_provenance) REFERENCES {self.get_table_name('dim_provenances')}(id_provenance) ON DELETE CASCADE,
                    FOREIGN KEY (id_categorie) REFERENCES {self.get_table_name('dim_categories_visiteur')}(id_categorie) ON DELETE CASCADE,
                    INDEX idx_date_zone (date, id_zone),
                    INDEX idx_volume (volume),
                    INDEX idx_date_provenance (date, id_provenance),
                    INDEX idx_categorie_volume (id_categorie, volume),
                    CONSTRAINT chk_volume_positive CHECK (volume >= 0)
                ) ENGINE=InnoDB COMMENT='Donnees de nuitees principales'""",
            
            'fact_diurnes': f"""CREATE TABLE {self.get_table_name('fact_diurnes')} (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    date DATE NOT NULL COMMENT 'Date observation',
                    id_zone INT NOT NULL COMMENT 'Zone observation',
                    id_provenance INT NOT NULL COMMENT 'Provenance des visiteurs',
                    id_categorie INT NOT NULL COMMENT 'Categorie de visiteur',
                    volume INT NOT NULL DEFAULT 0 COMMENT 'Nombre de visiteurs diurnes',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (date) REFERENCES {self.get_table_name('dim_dates')}(date) ON DELETE CASCADE,
                    FOREIGN KEY (id_zone) REFERENCES {self.get_table_name('dim_zones_observation')}(id_zone) ON DELETE CASCADE,
                    FOREIGN KEY (id_provenance) REFERENCES {self.get_table_name('dim_provenances')}(id_provenance) ON DELETE CASCADE,
                    FOREIGN KEY (id_categorie) REFERENCES {self.get_table_name('dim_categories_visiteur')}(id_categorie) ON DELETE CASCADE,
                    INDEX idx_date_zone (date, id_zone),
                    INDEX idx_volume (volume),
                    INDEX idx_date_provenance (date, id_provenance),
                    INDEX idx_categorie_volume (id_categorie, volume),
                    CONSTRAINT chk_volume_positive CHECK (volume >= 0)
                ) ENGINE=InnoDB COMMENT='Donnees de visiteurs diurnes principales'"""
        }
        
        # Tables de faits avec dimensions - générer avec les bons noms
        dimension_fact_tables = [
            ('fact_nuitees_departements', 'id_departement', 'dim_departements', 'Nuitees par departement'),
            ('fact_nuitees_regions', 'id_region', 'dim_regions', 'Nuitees par region'),
            ('fact_nuitees_pays', 'id_pays', 'dim_pays', 'Nuitees par pays'),
            ('fact_nuitees_age', 'id_age', 'dim_tranches_age', 'Nuitees par age avec categorie'),
            ('fact_nuitees_geolife', 'id_geolife', 'dim_segments_geolife', 'Nuitees par segment geolife avec categorie'),
            ('fact_diurnes_departements', 'id_departement', 'dim_departements', 'Visiteurs diurnes par departement'),
            ('fact_diurnes_regions', 'id_region', 'dim_regions', 'Visiteurs diurnes par region'),
            ('fact_diurnes_pays', 'id_pays', 'dim_pays', 'Visiteurs diurnes par pays'),
            ('fact_diurnes_age', 'id_age', 'dim_tranches_age', 'Visiteurs diurnes par age avec categorie'),
            ('fact_diurnes_geolife', 'id_geolife', 'dim_segments_geolife', 'Visiteurs diurnes par segment geolife avec categorie')
        ]
        
        for table_name, fk_column, dim_table, comment in dimension_fact_tables:
            # Toutes les tables de faits ont besoin de id_categorie
            has_categorie = True
            categorie_column = "id_categorie INT NOT NULL COMMENT 'Categorie de visiteur',"
            categorie_fk = f"FOREIGN KEY (id_categorie) REFERENCES {self.get_table_name('dim_categories_visiteur')}(id_categorie) ON DELETE CASCADE,"
            
            # Ajouter une contrainte unique pour les tables de régions créées à partir des départements
            unique_constraint = ""
            if 'regions' in table_name:
                unique_constraint = f"UNIQUE KEY uk_aggregation (date, id_zone, id_provenance, id_categorie, {fk_column}),"
            
            # Ajouter un index sur id_categorie pour les tables d'âge et geolife
            categorie_index = ""
            if 'age' in table_name or 'geolife' in table_name:
                categorie_index = "INDEX idx_categorie (id_categorie),"
            
            query = f"""CREATE TABLE {self.get_table_name(table_name)} (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    date DATE NOT NULL,
                    id_zone INT NOT NULL,
                    id_provenance INT NOT NULL,
                    {categorie_column}
                    {fk_column} INT NOT NULL,
                    volume INT NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    {unique_constraint}
                    FOREIGN KEY (date) REFERENCES {self.get_table_name('dim_dates')}(date) ON DELETE CASCADE,
                    FOREIGN KEY (id_zone) REFERENCES {self.get_table_name('dim_zones_observation')}(id_zone) ON DELETE CASCADE,
                    FOREIGN KEY (id_provenance) REFERENCES {self.get_table_name('dim_provenances')}(id_provenance) ON DELETE CASCADE,
                    {categorie_fk}
                    FOREIGN KEY ({fk_column}) REFERENCES {self.get_table_name(dim_table)}({fk_column}) ON DELETE CASCADE,
                    INDEX idx_date_zone_{fk_column.replace('id_', '')} (date, id_zone, {fk_column}),
                    INDEX idx_{fk_column.replace('id_', '')}_volume ({fk_column}, volume),
                    INDEX idx_volume (volume),
                    {categorie_index}
                    CONSTRAINT chk_volume_positive CHECK (volume >= 0)
                ) ENGINE=InnoDB COMMENT='{comment}'"""
            fact_queries[table_name] = query
        
        for table_name, query in fact_queries.items():
            try:
                self.cursor.execute(query)
                self.connection.commit()
                logger.info(f"Table {table_name} créée avec succès")
            except Error as e:
                logger.error(f"Erreur création table {table_name}: {e}")
                return False
        
        return True
    
    def load_csv_with_polars(self, filename, streaming=False):
        """Chargement CSV avec Polars optimisé mémoire et mode test"""
        file_path = self.data_path / filename
        try:
            if streaming and self.low_memory:
                # Mode streaming pour économiser la mémoire
                df = pl.scan_csv(
                    file_path,
                    separator=',',
                    try_parse_dates=True,
                    null_values=['', 'NULL', 'null']
                )
                
                # Limiter en mode test
                if self.test_mode:
                    df = df.head(self.test_rows)
                    
                logger.info(f"Fichier {filename} scanné en mode streaming")
                return df
            else:
                # Lecture normale avec optimisations mémoire
                read_params = {
                    'separator': ',',
                    'try_parse_dates': True,
                    'null_values': ['', 'NULL', 'null'],
                    'low_memory': self.low_memory
                }
                
                # Limiter les lignes en mode test
                if self.test_mode:
                    read_params['n_rows'] = self.test_rows
                
                df = pl.read_csv(file_path, **read_params)
                
                # Log avec indication du mode
                mode_info = f"(TEST: {self.test_rows} lignes)" if self.test_mode else "(COMPLET)"
                logger.info(f"Fichier {filename} chargé: {df.shape[0]} lignes, {df.shape[1]} colonnes {mode_info}")
                return df
        except Exception as e:
            logger.error(f"Erreur chargement {filename}: {e}")
            return None
    
    def load_csv_in_chunks(self, filename):
        """Chargement CSV par chunks pour économiser la mémoire"""
        file_path = self.data_path / filename
        try:
            # Lecture par chunks
            chunks = []
            with open(file_path, 'r', encoding='utf-8') as f:
                chunk_reader = pl.read_csv_batched(
                    f,
                    separator=',',
                    batch_size=self.chunk_size,
                    try_parse_dates=True,
                    null_values=['', 'NULL', 'null']
                )
                for chunk in chunk_reader:
                    yield chunk
                    
        except Exception as e:
            logger.error(f"Erreur chargement par chunks {filename}: {e}")
            return None
    
    def populate_dimensions(self):
        """Alimentation des tables de dimension optimisée mémoire"""
        logger.info("=== ALIMENTATION DES DIMENSIONS ===")
        
        csv_files = [
            'Nuitee.csv', 'Diurne.csv', 'Nuitee_Departement.csv', 'Diurne_Departement.csv',
            'Nuitee_Regions.csv', 'Diurne_Regions.csv',
            'Nuitee_Pays.csv', 'Diurne_Pays.csv', 'Nuitee_Age.csv', 'Diurne_Age.csv',
            'Nuitee_Geolife.csv', 'Diurne_Geolife.csv'
        ]
        
        if self.low_memory:
            # Mode économe : traiter fichier par fichier
            logger.info("Mode mémoire faible: traitement fichier par fichier")
            self.populate_dimensions_low_memory(csv_files)
        else:
            # Mode normal : charger tous les fichiers
            all_data = {}
            for filename in csv_files:
                df = self.load_csv_with_polars(filename)
                if df is not None:
                    all_data[filename] = df
            
            if not all_data:
                logger.error("Aucun fichier CSV chargé")
                return False
            
            self.populate_dimensions_normal_memory(all_data)
        
        return True
    
    def populate_dimensions_low_memory(self, csv_files):
        """Alimentation dimensions en mode mémoire faible"""
        # Collecter dimensions par type en traitant fichier par fichier
        all_dates = set()
        all_zones = set()
        all_provenances = set()
        all_categories = set()
        all_departements = set()
        all_regions = set()
        all_pays = set()
        all_ages = set()
        all_geolife = set()
        
        for filename in csv_files:
            logger.info(f"Traitement des dimensions de {filename}...")
            df = self.load_csv_with_polars(filename)
            if df is None:
                continue
                
            # Collecte des dimensions uniques par fichier
            if all(col in df.columns for col in ['Date', 'VacancesA', 'VacancesB', 'VacancesC', 'Ferie', 'JourDeLaSemaine']):
                date_df = df.select('Date', 'VacancesA', 'VacancesB', 'VacancesC', 'Ferie', 'JourDeLaSemaine').unique()
                for row in date_df.rows():
                    if row[0]:
                        all_dates.add((
                            row[0], 
                            self.clean_vacation_value(row[1]), 
                            self.clean_vacation_value(row[2]), 
                            self.clean_vacation_value(row[3]), 
                            row[4], 
                            row[5]
                        ))
            
            if 'ZoneObservation' in df.columns:
                zone_df = df.select('ZoneObservation').unique()
                for row in zone_df.rows():
                    if row[0]:
                        all_zones.add(str(row[0]).upper().strip())
            
            if 'Provenance' in df.columns:
                prov_df = df.select('Provenance').unique()
                for row in prov_df.rows():
                    if row[0]:
                        all_provenances.add(str(row[0]).upper().strip())
            
            if 'CategorieVisiteur' in df.columns:
                cat_df = df.select('CategorieVisiteur').unique()
                for row in cat_df.rows():
                    if row[0]:
                        all_categories.add(str(row[0]).upper().strip())
            
            if 'Departement' in filename and all(col in df.columns for col in ['NomDepartement', 'NomRegion', 'NomNouvelleRegion']):
                dept_df = df.select('NomDepartement', 'NomRegion', 'NomNouvelleRegion').unique()
                for row in dept_df.rows():
                    if all(row):
                        all_departements.add((
                            str(row[0]).upper().strip(),
                            str(row[1]).upper().strip(),
                            str(row[2]).upper().strip()
                        ))
                        # Collecter aussi les régions
                        all_regions.add((
                            str(row[1]).upper().strip(),  # NomRegion
                            str(row[2]).upper().strip()   # NomNouvelleRegion
                        ))
            
            if 'Pays' in filename and 'Pays' in df.columns:
                pays_df = df.select('Pays').unique()
                for row in pays_df.rows():
                    if row[0]:
                        all_pays.add(str(row[0]).upper().strip())
            
            if 'Age' in filename and 'Age' in df.columns:
                age_df = df.select('Age').unique()
                for row in age_df.rows():
                    if row[0]:
                        all_ages.add(str(row[0]).upper().strip())
            
            if 'Geolife' in filename and 'Geolife' in df.columns:
                geo_df = df.select('Geolife').unique()
                for row in geo_df.rows():
                    if row[0]:
                        all_geolife.add(str(row[0]).upper().strip())
            
            # Libérer la mémoire
            del df
            
        # Insérer toutes les dimensions collectées
        self.insert_collected_dimensions(all_dates, all_zones, all_provenances, all_categories,
                                       all_departements, all_regions, all_pays, all_ages, all_geolife)
    
    def populate_dimensions_normal_memory(self, all_data):
        """Alimentation dimensions en mode mémoire normale"""
        
        # 1. Dimension dates
        self.populate_dim_dates(all_data)
        
        # 2. Dimension zones
        self.populate_dim_zones(all_data)
        
        # 3. Dimension provenances
        self.populate_dim_provenances(all_data)
        
        # 4. Dimension catégories visiteur
        self.populate_dim_categories(all_data)
        
        # 5. Dimension départements
        self.populate_dim_departements(all_data)
        
        # 6. Dimension régions
        self.populate_dim_regions(all_data)
        
        # 7. Dimension pays
        self.populate_dim_pays(all_data)
        
        # 8. Dimension âges
        self.populate_dim_ages(all_data)
        
        # 9. Dimension géolife
        self.populate_dim_geolife(all_data)
    
    def insert_collected_dimensions(self, all_dates, all_zones, all_provenances, all_categories,
                                  all_departements, all_regions, all_pays, all_ages, all_geolife):
        """Insertion groupée des dimensions collectées"""
        logger.info("Insertion des dimensions collectées...")
        
        # Dates
        if all_dates:
            self.insert_dates_batch(all_dates)
        
        # Zones
        if all_zones:
            self.insert_zones_batch(all_zones)
        
        # Provenances
        if all_provenances:
            self.insert_provenances_batch(all_provenances)
        
        # Catégories
        if all_categories:
            self.insert_categories_batch(all_categories)
        
        # Départements
        if all_departements:
            self.insert_departements_batch(all_departements)
        
        # Régions
        if all_regions:
            self.insert_regions_batch(all_regions)
        
        # Pays
        if all_pays:
            self.insert_pays_batch(all_pays)
        
        # Âges
        if all_ages:
            self.insert_ages_batch(all_ages)
        
        # Géolife
        if all_geolife:
            self.insert_geolife_batch(all_geolife)
    
    def insert_dates_batch(self, all_dates):
        """Insertion optimisée des dates"""
        insert_query = f"""
            INSERT IGNORE INTO {self.get_table_name('dim_dates')} 
            (date, vacances_a, vacances_b, vacances_c, ferie, jour_semaine, mois, annee, trimestre, semaine)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        
        batch_data = []
        for date_info in all_dates:
            date_val = date_info[0]
            if isinstance(date_val, str):
                date_obj = datetime.strptime(date_val, '%Y-%m-%d').date()
            else:
                date_obj = date_val
            
            mois = date_obj.month
            annee = date_obj.year
            trimestre = (mois - 1) // 3 + 1
            semaine = date_obj.isocalendar()[1]
            
            batch_data.append((
                date_obj, date_info[1], date_info[2], date_info[3], 
                date_info[4], date_info[5], mois, annee, trimestre, semaine
            ))
        
        try:
            self.cursor.executemany(insert_query, batch_data)
            self.connection.commit()
            logger.info(f"dim_dates: {len(batch_data)} dates insérées")
        except Error as e:
            logger.error(f"Erreur insertion dim_dates: {e}")
    
    def insert_zones_batch(self, all_zones):
        """Insertion optimisée des zones"""
        insert_query = f"INSERT IGNORE INTO {self.get_table_name('dim_zones_observation')} (nom_zone) VALUES (%s)"
        batch_data = [(zone,) for zone in all_zones]
        
        try:
            self.cursor.executemany(insert_query, batch_data)
            self.connection.commit()
            logger.info(f"dim_zones_observation: {len(batch_data)} zones insérées")
        except Error as e:
            logger.error(f"Erreur insertion dim_zones_observation: {e}")
    
    def insert_provenances_batch(self, all_provenances):
        """Insertion optimisée des provenances"""
        insert_query = f"INSERT IGNORE INTO {self.get_table_name('dim_provenances')} (nom_provenance) VALUES (%s)"
        batch_data = [(prov,) for prov in all_provenances]
        
        try:
            self.cursor.executemany(insert_query, batch_data)
            self.connection.commit()
            logger.info(f"dim_provenances: {len(batch_data)} provenances insérées")
        except Error as e:
            logger.error(f"Erreur insertion dim_provenances: {e}")
    
    def insert_categories_batch(self, all_categories):
        """Insertion optimisée des catégories"""
        insert_query = f"INSERT IGNORE INTO {self.get_table_name('dim_categories_visiteur')} (nom_categorie) VALUES (%s)"
        batch_data = [(cat,) for cat in all_categories]
        
        try:
            self.cursor.executemany(insert_query, batch_data)
            self.connection.commit()
            logger.info(f"dim_categories_visiteur: {len(batch_data)} catégories insérées")
        except Error as e:
            logger.error(f"Erreur insertion dim_categories_visiteur: {e}")
    
    def insert_departements_batch(self, all_departements):
        """Insertion optimisée des départements"""
        insert_query = f"""
            INSERT IGNORE INTO {self.get_table_name('dim_departements')} 
            (nom_departement, nom_region, nom_nouvelle_region) VALUES (%s, %s, %s)
        """
        
        try:
            self.cursor.executemany(insert_query, list(all_departements))
            self.connection.commit()
            logger.info(f"dim_departements: {len(all_departements)} départements insérés")
        except Error as e:
            logger.error(f"Erreur insertion dim_departements: {e}")
    
    def insert_regions_batch(self, all_regions):
        """Insertion optimisée des régions"""
        insert_query = f"""
            INSERT IGNORE INTO {self.get_table_name('dim_regions')} 
            (nom_region, nom_nouvelle_region) VALUES (%s, %s)
        """
        
        try:
            self.cursor.executemany(insert_query, list(all_regions))
            self.connection.commit()
            logger.info(f"dim_regions: {len(all_regions)} régions insérées")
        except Error as e:
            logger.error(f"Erreur insertion dim_regions: {e}")
    
    def insert_pays_batch(self, all_pays):
        """Insertion optimisée des pays"""
        insert_query = f"INSERT IGNORE INTO {self.get_table_name('dim_pays')} (nom_pays) VALUES (%s)"
        batch_data = [(pays,) for pays in all_pays]
        
        try:
            self.cursor.executemany(insert_query, batch_data)
            self.connection.commit()
            logger.info(f"dim_pays: {len(batch_data)} pays insérés")
        except Error as e:
            logger.error(f"Erreur insertion dim_pays: {e}")
    
    def insert_ages_batch(self, all_ages):
        """Insertion optimisée des âges"""
        insert_query = f"INSERT IGNORE INTO {self.get_table_name('dim_tranches_age')} (tranche_age) VALUES (%s)"
        batch_data = [(age,) for age in all_ages]
        
        try:
            self.cursor.executemany(insert_query, batch_data)
            self.connection.commit()
            logger.info(f"dim_tranches_age: {len(batch_data)} tranches d'âge insérées")
        except Error as e:
            logger.error(f"Erreur insertion dim_tranches_age: {e}")
    
    def insert_geolife_batch(self, all_geolife):
        """Insertion optimisée des segments géolife"""
        insert_query = f"INSERT IGNORE INTO {self.get_table_name('dim_segments_geolife')} (nom_segment) VALUES (%s)"
        batch_data = [(geo,) for geo in all_geolife]
        
        try:
            self.cursor.executemany(insert_query, batch_data)
            self.connection.commit()
            logger.info(f"dim_segments_geolife: {len(batch_data)} segments géolife insérés")
        except Error as e:
            logger.error(f"Erreur insertion dim_segments_geolife: {e}")
    
    def populate_dim_dates(self, all_data):
        """Alimentation dimension dates"""
        logger.info("Alimentation dim_dates...")
        
        # Collecter toutes les dates uniques
        all_dates = set()
        for filename, df in all_data.items():
            if 'Date' in df.columns:
                dates = df.select('Date', 'VacancesA', 'VacancesB', 'VacancesC', 'Ferie', 'JourDeLaSemaine').unique()
                for row in dates.rows():
                    date_val = row[0]
                    if date_val:
                        # Nettoyer les valeurs de vacances (convertir 'o' en '0')
                        vacances_a = self.clean_vacation_value(row[1])
                        vacances_b = self.clean_vacation_value(row[2])
                        vacances_c = self.clean_vacation_value(row[3])
                        ferie = self.clean_vacation_value(row[4])
                        
                        all_dates.add((
                            date_val,
                            bool(vacances_a),
                            bool(vacances_b), 
                            bool(vacances_c),
                            bool(ferie),
                            row[5]
                        ))
        
        # Insérer dans la base
        insert_query = """
            INSERT IGNORE INTO dim_dates 
            (date, vacances_a, vacances_b, vacances_c, ferie, jour_semaine, mois, annee, trimestre, semaine)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        
        batch_data = []
        for date_info in all_dates:
            date_val = date_info[0]
            if isinstance(date_val, str):
                date_obj = datetime.strptime(date_val, '%Y-%m-%d').date()
            else:
                date_obj = date_val
            
            mois = date_obj.month
            annee = date_obj.year
            trimestre = (mois - 1) // 3 + 1
            semaine = date_obj.isocalendar()[1]
            
            batch_data.append((
                date_obj,
                date_info[1],  # vacances_a
                date_info[2],  # vacances_b
                date_info[3],  # vacances_c
                date_info[4],  # ferie
                date_info[5],  # jour_semaine
                mois, annee, trimestre, semaine
            ))
        
        try:
            self.cursor.executemany(insert_query, batch_data)
            self.connection.commit()
            logger.info(f"dim_dates: {len(batch_data)} dates insérées")
        except Error as e:
            logger.error(f"Erreur insertion dim_dates: {e}")
    
    def populate_dim_zones(self, all_data):
        """Alimentation dimension zones"""
        logger.info("Alimentation dim_zones_observation...")
        
        zones = set()
        for df in all_data.values():
            if 'ZoneObservation' in df.columns:
                unique_zones = df.select('ZoneObservation').unique()
                for row in unique_zones.rows():
                    if row[0]:
                        zones.add(str(row[0]).upper().strip())
        
        insert_query = "INSERT IGNORE INTO dim_zones_observation (nom_zone) VALUES (%s)"
        batch_data = [(zone,) for zone in zones]
        
        try:
            self.cursor.executemany(insert_query, batch_data)
            self.connection.commit()
            logger.info(f"dim_zones_observation: {len(batch_data)} zones insérées")
        except Error as e:
            logger.error(f"Erreur insertion dim_zones_observation: {e}")
    
    def populate_dim_provenances(self, all_data):
        """Alimentation dimension provenances"""
        logger.info("Alimentation dim_provenances...")
        
        provenances = set()
        for df in all_data.values():
            if 'Provenance' in df.columns:
                unique_provenances = df.select('Provenance').unique()
                for row in unique_provenances.rows():
                    if row[0]:
                        provenances.add(str(row[0]).upper().strip())
        
        insert_query = "INSERT IGNORE INTO dim_provenances (nom_provenance) VALUES (%s)"
        batch_data = [(prov,) for prov in provenances]
        
        try:
            self.cursor.executemany(insert_query, batch_data)
            self.connection.commit()
            logger.info(f"dim_provenances: {len(batch_data)} provenances insérées")
        except Error as e:
            logger.error(f"Erreur insertion dim_provenances: {e}")
    
    def populate_dim_categories(self, all_data):
        """Alimentation dimension catégories visiteur"""
        logger.info("Alimentation dim_categories_visiteur...")
        
        categories = set()
        for df in all_data.values():
            if 'CategorieVisiteur' in df.columns:
                unique_categories = df.select('CategorieVisiteur').unique()
                for row in unique_categories.rows():
                    if row[0]:
                        categories.add(str(row[0]).upper().strip())
        
        insert_query = "INSERT IGNORE INTO dim_categories_visiteur (nom_categorie) VALUES (%s)"
        batch_data = [(cat,) for cat in categories]
        
        try:
            self.cursor.executemany(insert_query, batch_data)
            self.connection.commit()
            logger.info(f"dim_categories_visiteur: {len(batch_data)} catégories insérées")
        except Error as e:
            logger.error(f"Erreur insertion dim_categories_visiteur: {e}")
    
    def populate_dim_departements(self, all_data):
        """Alimentation dimension départements"""
        logger.info("Alimentation dim_departements...")
        
        departements = set()
        for filename, df in all_data.items():
            if 'Departement' in filename and all(col in df.columns for col in ['NomDepartement', 'NomRegion', 'NomNouvelleRegion']):
                unique_depts = df.select('NomDepartement', 'NomRegion', 'NomNouvelleRegion').unique()
                for row in unique_depts.rows():
                    if all(row):
                        departements.add((
                            str(row[0]).upper().strip(),
                            str(row[1]).upper().strip(),
                            str(row[2]).upper().strip()
                        ))
        
        insert_query = """
            INSERT IGNORE INTO dim_departements 
            (nom_departement, nom_region, nom_nouvelle_region) VALUES (%s, %s, %s)
        """
        
        try:
            self.cursor.executemany(insert_query, list(departements))
            self.connection.commit()
            logger.info(f"dim_departements: {len(departements)} départements insérés")
        except Error as e:
            logger.error(f"Erreur insertion dim_departements: {e}")
    
    def populate_dim_regions(self, all_data):
        """Alimentation dimension régions"""
        logger.info("Alimentation dim_regions...")
        
        regions = set()
        for filename, df in all_data.items():
            if 'Departement' in filename and all(col in df.columns for col in ['NomRegion', 'NomNouvelleRegion']):
                unique_regions = df.select('NomRegion', 'NomNouvelleRegion').unique()
                for row in unique_regions.rows():
                    if all(row):
                        regions.add((
                            str(row[0]).upper().strip(),
                            str(row[1]).upper().strip()
                        ))
        
        insert_query = f"""
            INSERT IGNORE INTO {self.get_table_name('dim_regions')} 
            (nom_region, nom_nouvelle_region) VALUES (%s, %s)
        """
        
        try:
            self.cursor.executemany(insert_query, list(regions))
            self.connection.commit()
            logger.info(f"dim_regions: {len(regions)} régions insérées")
        except Error as e:
            logger.error(f"Erreur insertion dim_regions: {e}")
    
    def populate_dim_pays(self, all_data):
        """Alimentation dimension pays"""
        logger.info("Alimentation dim_pays...")
        
        pays = set()
        for filename, df in all_data.items():
            if 'Pays' in filename and 'Pays' in df.columns:
                unique_pays = df.select('Pays').unique()
                for row in unique_pays.rows():
                    if row[0]:
                        pays.add(str(row[0]).upper().strip())
        
        insert_query = "INSERT IGNORE INTO dim_pays (nom_pays) VALUES (%s)"
        batch_data = [(p,) for p in pays]
        
        try:
            self.cursor.executemany(insert_query, batch_data)
            self.connection.commit()
            logger.info(f"dim_pays: {len(batch_data)} pays insérés")
        except Error as e:
            logger.error(f"Erreur insertion dim_pays: {e}")
    
    def populate_dim_ages(self, all_data):
        """Alimentation dimension âges"""
        logger.info("Alimentation dim_tranches_age...")
        
        ages = set()
        for filename, df in all_data.items():
            if 'Age' in filename and 'Age' in df.columns:
                unique_ages = df.select('Age').unique()
                for row in unique_ages.rows():
                    if row[0]:
                        ages.add(str(row[0]).upper().strip())
        
        insert_query = "INSERT IGNORE INTO dim_tranches_age (tranche_age) VALUES (%s)"
        batch_data = [(age,) for age in ages]
        
        try:
            self.cursor.executemany(insert_query, batch_data)
            self.connection.commit()
            logger.info(f"dim_tranches_age: {len(batch_data)} tranches d'âge insérées")
        except Error as e:
            logger.error(f"Erreur insertion dim_tranches_age: {e}")
    
    def populate_dim_geolife(self, all_data):
        """Alimentation dimension géolife"""
        logger.info("Alimentation dim_segments_geolife...")
        
        geolife = set()
        for filename, df in all_data.items():
            if 'Geolife' in filename and 'Geolife' in df.columns:
                unique_geolife = df.select('Geolife').unique()
                for row in unique_geolife.rows():
                    if row[0]:
                        geolife.add(str(row[0]).upper().strip())
        
        insert_query = "INSERT IGNORE INTO dim_segments_geolife (nom_segment) VALUES (%s)"
        batch_data = [(geo,) for geo in geolife]
        
        try:
            self.cursor.executemany(insert_query, batch_data)
            self.connection.commit()
            logger.info(f"dim_segments_geolife: {len(batch_data)} segments géolife insérés")
        except Error as e:
            logger.error(f"Erreur insertion dim_segments_geolife: {e}")
    
    def get_dimension_mappings(self):
        """Récupération des mappings des dimensions"""
        mappings = {}
        
        # Zones
        self.cursor.execute(f"SELECT id_zone, nom_zone FROM {self.get_table_name('dim_zones_observation')}")
        mappings['zones'] = {nom: id_val for id_val, nom in self.cursor.fetchall()}
        
        # Provenances
        self.cursor.execute(f"SELECT id_provenance, nom_provenance FROM {self.get_table_name('dim_provenances')}")
        mappings['provenances'] = {nom: id_val for id_val, nom in self.cursor.fetchall()}
        
        # Catégories
        self.cursor.execute(f"SELECT id_categorie, nom_categorie FROM {self.get_table_name('dim_categories_visiteur')}")
        mappings['categories'] = {nom: id_val for id_val, nom in self.cursor.fetchall()}
        
        # Départements
        self.cursor.execute(f"SELECT id_departement, nom_departement, nom_region, nom_nouvelle_region FROM {self.get_table_name('dim_departements')}")
        mappings['departements'] = {}
        for id_val, nom_dept, nom_region, nom_nouvelle_region in self.cursor.fetchall():
            key = f"{nom_dept}|{nom_region}|{nom_nouvelle_region}"
            mappings['departements'][key] = id_val
        
        # Régions
        self.cursor.execute(f"SELECT id_region, nom_region, nom_nouvelle_region FROM {self.get_table_name('dim_regions')}")
        mappings['regions'] = {}
        for id_val, nom_region, nom_nouvelle_region in self.cursor.fetchall():
            key = f"{nom_region}|{nom_nouvelle_region}"
            mappings['regions'][key] = id_val
        
        # Pays
        self.cursor.execute(f"SELECT id_pays, nom_pays FROM {self.get_table_name('dim_pays')}")
        mappings['pays'] = {nom: id_val for id_val, nom in self.cursor.fetchall()}
        
        # Âges
        self.cursor.execute(f"SELECT id_age, tranche_age FROM {self.get_table_name('dim_tranches_age')}")
        mappings['ages'] = {nom: id_val for id_val, nom in self.cursor.fetchall()}
        
        # Géolife
        self.cursor.execute(f"SELECT id_geolife, nom_segment FROM {self.get_table_name('dim_segments_geolife')}")
        mappings['geolife'] = {nom: id_val for id_val, nom in self.cursor.fetchall()}
        
        logger.info("Mappings des dimensions récupérés")
        return mappings
    
    def populate_facts(self):
        """Alimentation des tables de faits"""
        logger.info("=== ALIMENTATION DES FAITS ===")
        
        mappings = self.get_dimension_mappings()
        
        # Mapping des fichiers vers tables
        file_table_mapping = [
            ('Nuitee.csv', 'fact_nuitees', []),
            ('Diurne.csv', 'fact_diurnes', []),
            ('Nuitee_Departement.csv', 'fact_nuitees_departements', ['departements']),
            ('Diurne_Departement.csv', 'fact_diurnes_departements', ['departements']),
            ('Nuitee_Departement.csv', 'fact_nuitees_regions', ['regions']),  # Utiliser départements pour régions
            ('Diurne_Departement.csv', 'fact_diurnes_regions', ['regions']),  # Utiliser départements pour régions
            ('Nuitee_Pays.csv', 'fact_nuitees_pays', ['pays']),
            ('Diurne_Pays.csv', 'fact_diurnes_pays', ['pays']),
            ('Nuitee_Age.csv', 'fact_nuitees_age', ['ages']),
            ('Diurne_Age.csv', 'fact_diurnes_age', ['ages']),
            ('Nuitee_Geolife.csv', 'fact_nuitees_geolife', ['geolife']),
            ('Diurne_Geolife.csv', 'fact_diurnes_geolife', ['geolife'])
        ]
        
        for filename, table_name, extra_dimensions in file_table_mapping:
            success = self.populate_fact_table(filename, table_name, extra_dimensions, mappings)
            if not success:
                logger.error(f"❌ ÉCHEC sur {filename} - ARRÊT COMPLET")
                return False
        
        return True
    
    def populate_fact_table(self, filename, table_name, extra_dimensions, mappings):
        """Alimentation d'une table de faits spécifique"""
        logger.info(f"Alimentation {table_name} depuis {filename}...")
        
        df = self.load_csv_with_polars(filename)
        if df is None:
            return False
        
        # Si on traite les régions à partir des départements, agrégation nécessaire
        if 'regions' in table_name and 'Departement' in filename:
            return self.populate_fact_table_regions_from_departments(df, table_name, mappings)
        
        # Traitement normal pour les autres tables
        
        # Colonnes de base communes
        base_columns = ['date', 'id_zone', 'id_provenance']
        
        # Ajouter id_categorie pour toutes les tables de faits
        base_columns.append('id_categorie')
        
        # Ajouter les dimensions spécifiques
        dimension_columns = []
        for dim in extra_dimensions:
            if dim == 'departements':
                dimension_columns.append('id_departement')
            elif dim == 'regions':
                dimension_columns.append('id_region')
            elif dim == 'pays':
                dimension_columns.append('id_pays')
            elif dim == 'ages':
                dimension_columns.append('id_age')
            elif dim == 'geolife':
                dimension_columns.append('id_geolife')
        
        all_columns = base_columns + dimension_columns + ['volume']
        
        # Construire la requête d'insertion
        placeholders = ', '.join(['%s'] * len(all_columns))
        insert_query = f"""
            INSERT IGNORE INTO {self.get_table_name(table_name)} ({', '.join(all_columns)})
            VALUES ({placeholders})
        """
        
        # Préparer les données
        batch_data = []
        skipped_rows = 0
        
        for row in df.rows():
            try:
                # Correspondances colonnes CSV -> DataFrame
                date_val = row[df.get_column_index('Date')]
                zone_val = str(row[df.get_column_index('ZoneObservation')]).upper().strip()
                provenance_val = str(row[df.get_column_index('Provenance')]).upper().strip()
                volume_val = row[df.get_column_index('Volume')]
                
                # Conversion des IDs de base
                id_zone = mappings['zones'].get(zone_val)
                id_provenance = mappings['provenances'].get(provenance_val)
                
                # Vérifier que les données de base sont présentes
                if not all([date_val, id_zone, id_provenance]):
                    print(f"🔥 DONNÉES DE BASE MANQUANTES:")
                    print(f"   Fichier: {filename}")
                    print(f"   Date: '{date_val}'")
                    print(f"   Zone: '{zone_val}' -> ID: {id_zone}")
                    print(f"   Provenance: '{provenance_val}' -> ID: {id_provenance}")
                    if not id_zone:
                        print(f"   🔍 Zones disponibles (5 premières):")
                        available_zones = list(mappings['zones'].keys())[:5]
                        for i, zone in enumerate(available_zones):
                            print(f"      {i+1}. '{zone}'")
                    if not id_provenance:
                        print(f"   🔍 Provenances disponibles (5 premières):")
                        available_prov = list(mappings['provenances'].keys())[:5]
                        for i, prov in enumerate(available_prov):
                            print(f"      {i+1}. '{prov}'")
                    print("   ❌ ARRÊT DU PROGRAMME")
                    return False
                
                # Initialiser row_data avec les données de base
                row_data = [date_val, id_zone, id_provenance]
                
                # Catégorie visiteur (pour toutes les tables)
                categorie_val = str(row[df.get_column_index('CategorieVisiteur')]).upper().strip()
                id_categorie = mappings['categories'].get(categorie_val)
                if not id_categorie:
                    print(f"🔥 DIMENSION MANQUANTE - Catégorie visiteur:")
                    print(f"   Fichier: {filename}")
                    print(f"   Valeur recherchée: '{categorie_val}'")
                    print(f"   🔍 Valeurs disponibles:")
                    available_values = list(mappings['categories'].keys())
                    for i, val in enumerate(available_values):
                        print(f"      {i+1}. '{val}'")
                    print("   ❌ ARRÊT DU PROGRAMME")
                    return False
                row_data.append(id_categorie)
                
                # Valider toutes les dimensions spécifiques AVANT de construire row_data
                dimension_ids = []
                
                for dim in extra_dimensions:
                    if dim == 'departements':
                        dept_val = str(row[df.get_column_index('NomDepartement')]).upper().strip()
                        region_val = str(row[df.get_column_index('NomRegion')]).upper().strip()
                        nouvelle_region_val = str(row[df.get_column_index('NomNouvelleRegion')]).upper().strip()
                        dept_key = f"{dept_val}|{region_val}|{nouvelle_region_val}"
                        id_dept = mappings['departements'].get(dept_key)
                        if not id_dept:
                            print(f"🔥 DIMENSION MANQUANTE - Département:")
                            print(f"   Fichier: {filename}")
                            print(f"   Clé recherchée: '{dept_key}'")
                            print(f"   Département: '{dept_val}'")
                            print(f"   Région: '{region_val}'")
                            print(f"   Nouvelle région: '{nouvelle_region_val}'")
                            print(f"   🔍 Clés disponibles (5 premières):")
                            available_keys = list(mappings['departements'].keys())[:5]
                            for i, key in enumerate(available_keys):
                                print(f"      {i+1}. '{key}'")
                            print("   ❌ ARRÊT DU PROGRAMME")
                            return False
                        dimension_ids.append(id_dept)
                    elif dim == 'regions':
                        # Pour les régions, utiliser les colonnes des fichiers départements
                        region_val = str(row[df.get_column_index('NomRegion')]).upper().strip()
                        nouvelle_region_val = str(row[df.get_column_index('NomNouvelleRegion')]).upper().strip()
                        region_key = f"{region_val}|{nouvelle_region_val}"
                        id_region = mappings['regions'].get(region_key)
                        if not id_region:
                            print(f"🔥 DIMENSION MANQUANTE - Région:")
                            print(f"   Fichier: {filename}")
                            print(f"   Clé recherchée: '{region_key}'")
                            print(f"   Région: '{region_val}'")
                            print(f"   Nouvelle région: '{nouvelle_region_val}'")
                            print(f"   🔍 Clés disponibles (5 premières):")
                            available_keys = list(mappings['regions'].keys())[:5]
                            for i, key in enumerate(available_keys):
                                print(f"      {i+1}. '{key}'")
                            print("   ❌ ARRÊT DU PROGRAMME")
                            return False
                        dimension_ids.append(id_region)
                    elif dim == 'pays':
                        pays_val = str(row[df.get_column_index('Pays')]).upper().strip()
                        id_pays = mappings['pays'].get(pays_val)
                        if not id_pays:
                            print(f"🔥 DIMENSION MANQUANTE - Pays:")
                            print(f"   Fichier: {filename}")
                            print(f"   Valeur recherchée: '{pays_val}'")
                            print(f"   🔍 Valeurs disponibles (5 premières):")
                            available_values = list(mappings['pays'].keys())[:5]
                            for i, val in enumerate(available_values):
                                print(f"      {i+1}. '{val}'")
                            print("   ❌ ARRÊT DU PROGRAMME")
                            return False
                        dimension_ids.append(id_pays)
                    elif dim == 'ages':
                        age_val = str(row[df.get_column_index('Age')]).upper().strip()
                        id_age = mappings['ages'].get(age_val)
                        if not id_age:
                            print(f"🔥 DIMENSION MANQUANTE - Âge:")
                            print(f"   Fichier: {filename}")
                            print(f"   Valeur recherchée: '{age_val}'")
                            print(f"   🔍 Valeurs disponibles (5 premières):")
                            available_values = list(mappings['ages'].keys())[:5]
                            for i, val in enumerate(available_values):
                                print(f"      {i+1}. '{val}'")
                            print("   ❌ ARRÊT DU PROGRAMME")
                            return False
                        dimension_ids.append(id_age)
                    elif dim == 'geolife':
                        geolife_val = str(row[df.get_column_index('Geolife')]).upper().strip()
                        id_geolife = mappings['geolife'].get(geolife_val)
                        if not id_geolife:
                            print(f"🔥 DIMENSION MANQUANTE - Geolife:")
                            print(f"   Fichier: {filename}")
                            print(f"   Valeur recherchée: '{geolife_val}'")
                            print(f"   🔍 Valeurs disponibles (5 premières):")
                            available_values = list(mappings['geolife'].keys())[:5]
                            for i, val in enumerate(available_values):
                                print(f"      {i+1}. '{val}'")
                            print("   ❌ ARRÊT DU PROGRAMME")
                            return False
                        dimension_ids.append(id_geolife)
                
                # Ajouter les IDs des dimensions dans l'ordre
                row_data.extend(dimension_ids)
                
                # Volume
                row_data.append(volume_val if volume_val else 0)
                
                # Vérifier que le nombre de paramètres est correct
                if len(row_data) != len(all_columns):
                    logger.error(f"Erreur construction ligne - Attendu: {len(all_columns)}, Reçu: {len(row_data)}")
                    logger.error(f"Colonnes: {all_columns}")
                    logger.error(f"Données: {row_data}")
                    skipped_rows += 1
                    continue
                
                batch_data.append(tuple(row_data))
                
                # Insertion par batch
                if len(batch_data) >= self.batch_size:
                    self.cursor.executemany(insert_query, batch_data)
                    self.connection.commit()
                    logger.info(f"{table_name}: {len(batch_data)} lignes insérées (batch)")
                    batch_data = []
                    
            except Exception as e:
                logger.warning(f"Erreur ligne dans {filename}: {e}")
                skipped_rows += 1
                continue
        
        # Insertion du dernier batch
        if batch_data:
            try:
                self.cursor.executemany(insert_query, batch_data)
                self.connection.commit()
                logger.info(f"{table_name}: {len(batch_data)} lignes insérées (dernier batch)")
            except Error as e:
                logger.error(f"Erreur insertion finale {table_name}: {e}")
        
        # Compter le total inséré
        self.cursor.execute(f"SELECT COUNT(*) FROM {self.get_table_name(table_name)}")
        total_rows = self.cursor.fetchone()[0]
        logger.info(f"{table_name}: {total_rows} lignes au total")
        
        if skipped_rows > 0:
            logger.warning(f"{table_name}: {skipped_rows} lignes ignorées (dimensions manquantes)")
        
        return True
    
    def populate_fact_table_regions_from_departments(self, df, table_name, mappings):
        """Alimentation des tables de faits de régions à partir des données départements avec agrégation"""
        logger.info(f"Agrégation des données départements pour {table_name}...")
        
        # Construire la requête d'insertion pour les régions
        base_columns = ['date', 'id_zone', 'id_provenance', 'id_categorie', 'id_region', 'volume']
        placeholders = ', '.join(['%s'] * len(base_columns))
        insert_query = f"""
            INSERT INTO {self.get_table_name(table_name)} ({', '.join(base_columns)})
            VALUES ({placeholders})
            ON DUPLICATE KEY UPDATE volume = volume + VALUES(volume)
        """
        
        # Dictionnaire pour agréger les données par clé (date, zone, provenance, categorie, region)
        aggregated_data = {}
        skipped_rows = 0
        
        for row in df.rows():
            try:
                # Extraire les données de base
                date_val = row[df.get_column_index('Date')]
                zone_val = str(row[df.get_column_index('ZoneObservation')]).upper().strip()
                provenance_val = str(row[df.get_column_index('Provenance')]).upper().strip()
                categorie_val = str(row[df.get_column_index('CategorieVisiteur')]).upper().strip()
                volume_val = row[df.get_column_index('Volume')] or 0
                
                # Extraire les données de région
                region_val = str(row[df.get_column_index('NomRegion')]).upper().strip()
                nouvelle_region_val = str(row[df.get_column_index('NomNouvelleRegion')]).upper().strip()
                
                # Mapper vers les IDs
                id_zone = mappings['zones'].get(zone_val)
                id_provenance = mappings['provenances'].get(provenance_val)
                id_categorie = mappings['categories'].get(categorie_val)
                region_key = f"{region_val}|{nouvelle_region_val}"
                id_region = mappings['regions'].get(region_key)
                
                # Vérifier que toutes les dimensions sont présentes
                if not all([date_val, id_zone, id_provenance, id_categorie, id_region]):
                    skipped_rows += 1
                    continue
                
                # Clé d'agrégation
                agg_key = (date_val, id_zone, id_provenance, id_categorie, id_region)
                
                # Agréger les volumes
                if agg_key in aggregated_data:
                    aggregated_data[agg_key] += volume_val
                else:
                    aggregated_data[agg_key] = volume_val
                    
            except Exception as e:
                logger.warning(f"Erreur ligne dans {table_name}: {e}")
                skipped_rows += 1
                continue
        
        # Insérer les données agrégées
        batch_data = []
        for (date_val, id_zone, id_provenance, id_categorie, id_region), volume in aggregated_data.items():
            batch_data.append((date_val, id_zone, id_provenance, id_categorie, id_region, volume))
            
            # Insertion par batch
            if len(batch_data) >= self.batch_size:
                try:
                    self.cursor.executemany(insert_query, batch_data)
                    self.connection.commit()
                    logger.info(f"{table_name}: {len(batch_data)} lignes agrégées insérées (batch)")
                    batch_data = []
                except Error as e:
                    logger.error(f"Erreur insertion batch {table_name}: {e}")
                    return False
        
        # Insertion du dernier batch
        if batch_data:
            try:
                self.cursor.executemany(insert_query, batch_data)
                self.connection.commit()
                logger.info(f"{table_name}: {len(batch_data)} lignes agrégées insérées (dernier batch)")
            except Error as e:
                logger.error(f"Erreur insertion finale {table_name}: {e}")
                return False
        
        # Compter le total inséré
        self.cursor.execute(f"SELECT COUNT(*) FROM {self.get_table_name(table_name)}")
        total_rows = self.cursor.fetchone()[0]
        total_volume = sum(aggregated_data.values())
        logger.info(f"{table_name}: {total_rows} régions uniques, {total_volume:,} volume total agrégé")
        
        if skipped_rows > 0:
            logger.warning(f"{table_name}: {skipped_rows} lignes ignorées (dimensions manquantes)")
        
        return True
    
    def create_and_populate(self):
        """Processus complet de création et alimentation"""
        start_time = time.time()
        logger.info("=== DEBUT ALIMENTATION BASE FLUXVISION ===")
        
        # Connexion
        if not self.connect():
            return False
        
        # Sélection base existante
        if not self.create_database():
            return False
        
        # Nettoyage complet de toutes les tables
        if not self.clean_all_tables():
            return False
        
        # Création tables dimensions
        if not self.create_dimension_tables():
            return False
        
        # Création tables faits
        if not self.create_fact_tables():
            return False
        
        # Alimentation dimensions
        if not self.populate_dimensions():
            return False
        
        # Alimentation faits
        if not self.populate_facts():
            return False
        
        # Statistiques finales
        self.print_final_stats()
        
        end_time = time.time()
        duration = end_time - start_time
        logger.info(f"=== MIGRATION TERMINÉE EN {duration:.2f} SECONDES ===")
        
        return True
    
    def print_final_stats(self):
        """Affichage des statistiques finales"""
        title = "=== STATISTIQUES FINALES ===" if not self.test_mode else "=== STATISTIQUES DE TEST ==="
        logger.info(title)
        
        # Tables de dimensions
        dim_tables = [
            'dim_dates', 'dim_zones_observation', 'dim_provenances', 
            'dim_categories_visiteur', 'dim_departements', 'dim_regions', 'dim_pays',
            'dim_tranches_age', 'dim_segments_geolife'
        ]
        
        for table in dim_tables:
            table_name = self.get_table_name(table)
            try:
                self.cursor.execute(f"SELECT COUNT(*) FROM {table_name}")
                count = self.cursor.fetchone()[0]
                logger.info(f"{table_name}: {count:,} enregistrements")
            except Exception as e:
                logger.warning(f"{table_name}: Erreur - {e}")
        
        # Tables de faits
        fact_tables = [
            'fact_nuitees', 'fact_diurnes', 'fact_nuitees_departements',
            'fact_diurnes_departements', 'fact_nuitees_regions', 'fact_diurnes_regions',
            'fact_nuitees_pays', 'fact_diurnes_pays', 'fact_nuitees_age', 'fact_diurnes_age', 
            'fact_nuitees_geolife', 'fact_diurnes_geolife'
        ]
        
        total_facts = 0
        for table in fact_tables:
            table_name = self.get_table_name(table)
            try:
                self.cursor.execute(f"SELECT COUNT(*) FROM {table_name}")
                count = self.cursor.fetchone()[0]
                total_facts += count
                logger.info(f"{table_name}: {count:,} enregistrements")
            except Exception as e:
                logger.warning(f"{table_name}: Erreur - {e}")
        
        logger.info(f"TOTAL FAITS: {total_facts:,} enregistrements")
        
        # Taille de la base (filtrée pour les tables de test si nécessaire)
        if self.test_mode:
            where_clause = f"AND table_name LIKE '%{self.table_suffix}'"
        else:
            where_clause = "AND table_name NOT LIKE '%_test'"
            
        self.cursor.execute(f"""
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size_MB'
            FROM information_schema.tables 
            WHERE table_schema = '{self.database}' {where_clause}
        """)
        result = self.cursor.fetchone()
        size_mb = result[0] if result and result[0] else 0
        mode_text = "TABLES DE TEST" if self.test_mode else "BASE DE DONNÉES"
        logger.info(f"TAILLE {mode_text}: {size_mb} MB")
    
    def print_test_stats(self):
        """Statistiques spécifiques au mode test"""
        if not self.test_mode:
            return
            
        logger.info("[TEST] === RESUME DU TEST ===")
        logger.info(f"Lignes testees par fichier: {self.test_rows}")
        logger.info(f"Suffixe des tables: '{self.table_suffix}'")
        logger.info("Tables creees avec succes:")
        
        # Vérifier quelques tables clés
        key_tables = ['dim_dates', 'dim_zones_observation', 'dim_regions', 'fact_nuitees', 'fact_diurnes']
        for table in key_tables:
            table_name = self.get_table_name(table)
            try:
                self.cursor.execute(f"SELECT COUNT(*) FROM {table_name}")
                count = self.cursor.fetchone()[0]
                status = "[OK]" if count > 0 else "[WARN]"
                logger.info(f"  {status} {table_name}: {count} enregistrements")
            except Exception:
                logger.info(f"  [ERROR] {table_name}: Erreur")
        
        logger.info("[INFO] Prochaines etapes:")
        logger.info("  1. Verifier les resultats avec l'option 4 du menu")
        logger.info("  2. Si satisfait, lancer la migration complete")
        logger.info("  3. Nettoyer les tables de test avec l'option 5")
    
    def close(self):
        """Fermeture des connexions"""
        if self.cursor:
            self.cursor.close()
        if self.connection:
            self.connection.close()
        logger.info("Connexions fermées")

    def clean_vacation_value(self, value):
        """
        Nettoie les valeurs des colonnes de vacances en convertissant 'o' en '0'
        
        Args:
            value: Valeur à nettoyer (peut être str, int, ou autre)
            
        Returns:
            int: 0 ou 1 selon la valeur nettoyée
        """
        if value is None:
            return 0
        
        # Convertir en string pour traitement
        str_value = str(value).strip().lower()
        
        # Remplacer 'o' par '0'
        if str_value == 'o':
            str_value = '0'
        
        # Convertir en int puis bool
        try:
            return int(float(str_value))
        except (ValueError, TypeError):
            # Si conversion impossible, retourner 0 par défaut
            logger.warning(f"Valeur vacation non reconnue: '{value}', défaut à 0")
            return 0

# Script principal
if __name__ == "__main__":
    creator = FluxVisionDatabaseCreator()
    
    try:
        success = creator.create_and_populate()
        if success:
            logger.info("[SUCCESS] Base de donnees FluxVision creee et alimentee avec succes!")
        else:
            logger.error("[ERROR] Echec lors de la creation/alimentation")
            sys.exit(1)
    except KeyboardInterrupt:
        logger.warning("[WARN] Processus interrompu par l'utilisateur")
        sys.exit(1)
    except Exception as e:
        logger.error(f"[ERROR] Erreur critique: {e}")
        sys.exit(1)
    finally:
        creator.close() 