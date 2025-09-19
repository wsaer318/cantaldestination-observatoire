#!/usr/bin/env python3
"""
Script pour alimenter les tables principales de FluxVision
avec les données du dossier data_clean/data_merged_csv
"""

import os
import sys
import logging
import argparse
import polars as pl
import mysql.connector
from datetime import datetime
from pathlib import Path

# Configuration du logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('populate_main_tables.log'),
        logging.StreamHandler()
    ]
)

def setup_database_connection(host, port, user, password, database):
    """Établit la connexion à la base de données MySQL"""
    try:
        connection = mysql.connector.connect(
            host=host,
            port=port,
            user=user,
            password=password,
            database=database,
            autocommit=False
        )
        logging.info(f"Connexion réussie à MySQL {host}")
        return connection
    except mysql.connector.Error as e:
        logging.error(f"Erreur de connexion MySQL: {e}")
        sys.exit(1)

def get_dimension_mappings(cursor):
    """Récupère les mappings des tables de dimensions"""
    mappings = {}
    
    # Zones d'observation
    cursor.execute("SELECT id_zone, nom_zone FROM dim_zones_observation")
    mappings['zones'] = {row[1].upper().strip(): row[0] for row in cursor.fetchall()}
    
    # Provenances
    cursor.execute("SELECT id_provenance, nom_provenance FROM dim_provenances")
    mappings['provenances'] = {row[1].upper().strip(): row[0] for row in cursor.fetchall()}
    
    # Catégories visiteur
    cursor.execute("SELECT id_categorie, nom_categorie FROM dim_categories_visiteur")
    mappings['categories'] = {row[1].upper().strip(): row[0] for row in cursor.fetchall()}
    
    # Départements
    cursor.execute("SELECT id_departement, nom_departement FROM dim_departements")
    mappings['departements'] = {row[1].upper().strip(): row[0] for row in cursor.fetchall()}
    
    # Pays
    cursor.execute("SELECT id_pays, nom_pays FROM dim_pays")
    mappings['pays'] = {row[1].upper().strip(): row[0] for row in cursor.fetchall()}
    
    # Tranches d'âge
    cursor.execute("SELECT id_age, tranche_age FROM dim_tranches_age")
    mappings['tranches_age'] = {row[1].upper().strip(): row[0] for row in cursor.fetchall()}
    
    # Segments geolife
    cursor.execute("SELECT id_geolife, nom_segment FROM dim_segments_geolife")
    mappings['geolife'] = {row[1].upper().strip(): row[0] for row in cursor.fetchall()}
    
    # Dates (format YYYY-MM-DD -> date)
    cursor.execute("SELECT date FROM dim_dates")
    mappings['dates'] = {row[0].strftime('%Y-%m-%d'): row[0].strftime('%Y-%m-%d') for row in cursor.fetchall()}
    
    return mappings

def clear_today_records(cursor, table_mappings):
    """Supprime les enregistrements ajoutés aujourd'hui dans les tables de faits"""
    logging.info("=== SUPPRESSION DES ENREGISTREMENTS D'AUJOURD'HUI ===")
    
    today = datetime.now().strftime('%Y-%m-%d')
    
    for table_name in table_mappings.values():
        try:
            cursor.execute(f"DELETE FROM {table_name} WHERE DATE(created_at) = %s", (today,))
            deleted_count = cursor.rowcount
            if deleted_count > 0:
                logging.info(f"[OK] {table_name}: {deleted_count} enregistrements d'aujourd'hui supprimés")
            else:
                logging.info(f"[OK] {table_name}: aucun enregistrement d'aujourd'hui trouvé")
        except Exception as e:
            logging.warning(f"Erreur suppression {table_name}: {e}")

def populate_dim_dates(cursor, csv_files, data_dir):
    """Alimente dim_dates avec les nouvelles dates des fichiers CSV"""
    logging.info("=== ALIMENTATION DIM_DATES AVEC NOUVELLES DATES ===")
    
    all_dates = set()
    
    for csv_file in csv_files:
        file_path = data_dir / csv_file
        if file_path.exists():
            try:
                df = pl.read_csv(file_path)
                if 'Date' in df.columns:
                    dates = df['Date'].unique().to_list()
                    all_dates.update(dates)
                    logging.info(f"Dates collectées de {csv_file}: {len(dates)} dates uniques")
            except Exception as e:
                logging.warning(f"Erreur lecture {csv_file}: {e}")
    
    logging.info(f"Total dates uniques collectées: {len(all_dates)}")
    
    # Récupérer les dates existantes
    cursor.execute("SELECT date FROM dim_dates")
    existing_dates = {row[0].strftime('%Y-%m-%d') for row in cursor.fetchall()}
    
    # Nouvelles dates à insérer
    new_dates = all_dates - existing_dates
    logging.info(f"Nouvelles dates à insérer: {len(new_dates)}")
    
    if new_dates:
        insert_query = """
        INSERT INTO dim_dates (date, annee, mois, vacances_a, vacances_b, vacances_c, ferie, jour_semaine, trimestre)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        
        jours_semaine = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche']
        
        for date_str in sorted(new_dates):
            try:
                date_obj = datetime.strptime(date_str, '%Y-%m-%d')
                trimestre = (date_obj.month - 1) // 3 + 1
                jour_semaine_nom = jours_semaine[date_obj.weekday()]
                
                cursor.execute(insert_query, (
                    date_obj.date(),
                    date_obj.year,
                    date_obj.month,
                    False,  # vacances_a par défaut
                    False,  # vacances_b par défaut
                    False,  # vacances_c par défaut
                    False,  # ferie par défaut
                    jour_semaine_nom,
                    trimestre
                ))
            except Exception as e:
                logging.warning(f"Erreur insertion date {date_str}: {e}")
        
        logging.info(f"[OK] {len(new_dates)} nouvelles dates insérées")
    else:
        logging.info("Aucune nouvelle date à insérer")

def process_csv_file(cursor, mappings, csv_file, data_dir, table_name, batch_size=5000):
    """Traite un fichier CSV et l'insère dans la table correspondante"""
    file_path = data_dir / csv_file
    
    if not file_path.exists():
        logging.warning(f"Fichier {csv_file} non trouvé")
        return 0
    
    try:
        df = pl.read_csv(file_path)
        total_rows = len(df)
        logging.info(f"Fichier {csv_file} chargé: {total_rows} lignes, {len(df.columns)} colonnes")
        
        # Définir la requête d'insertion selon le type de table
        if csv_file == 'Nuitee.csv':
            insert_query = f"""
            INSERT INTO {table_name} (date, id_zone, id_provenance, id_categorie, volume)
            VALUES (%s, %s, %s, %s, %s)
            """
        elif csv_file == 'Diurne.csv':
            insert_query = f"""
            INSERT INTO {table_name} (date, id_zone, id_provenance, id_categorie, volume)
            VALUES (%s, %s, %s, %s, %s)
            """
        elif 'Departement' in csv_file:
            insert_query = f"""
            INSERT INTO {table_name} (date, id_zone, id_provenance, id_categorie, id_departement, volume)
            VALUES (%s, %s, %s, %s, %s, %s)
            """
        elif 'Pays' in csv_file:
            insert_query = f"""
            INSERT INTO {table_name} (date, id_zone, id_provenance, id_categorie, id_pays, volume)
            VALUES (%s, %s, %s, %s, %s, %s)
            """
        elif 'Geolife' in csv_file:
            insert_query = f"""
            INSERT INTO {table_name} (date, id_zone, id_provenance, id_categorie, id_geolife, volume)
            VALUES (%s, %s, %s, %s, %s, %s)
            """
        elif 'Age' in csv_file:
            insert_query = f"""
            INSERT INTO {table_name} (date, id_zone, id_provenance, id_categorie, id_age, volume)
            VALUES (%s, %s, %s, %s, %s, %s)
            """
        
        # Traitement par batch
        inserted_count = 0
        
        for i in range(0, total_rows, batch_size):
            batch_df = df.slice(i, batch_size)
            batch_data = []
            
            for row in batch_df.iter_rows(named=True):
                try:
                    # Mappings de base
                    date_value = row['Date']  # Utiliser directement la date
                    id_zone = mappings['zones'].get(row['ZoneObservation'].upper().strip())
                    id_provenance = mappings['provenances'].get(row['Provenance'].upper().strip())
                    id_categorie = mappings['categories'].get(row['CategorieVisiteur'].upper().strip())
                    
                    if not all([date_value, id_zone, id_provenance, id_categorie]):
                        continue
                    
                    # Volume
                    volume = int(float(row.get('Volume', 0)))
                    
                    # Mapping spécifique selon le type de fichier
                    if 'Departement' in csv_file:
                        id_departement = mappings['departements'].get(row['NomDepartement'].upper().strip())
                        if id_departement:
                            batch_data.append((date_value, id_zone, id_provenance, id_categorie, id_departement, volume))
                    elif 'Pays' in csv_file:
                        id_pays = mappings['pays'].get(row['Pays'].upper().strip())
                        if id_pays:
                            batch_data.append((date_value, id_zone, id_provenance, id_categorie, id_pays, volume))
                    elif 'Geolife' in csv_file:
                        geolife_value = row.get('Geolife', '').strip()
                        if geolife_value:
                            id_geolife = mappings['geolife'].get(geolife_value.upper().strip())
                            if id_geolife:
                                batch_data.append((date_value, id_zone, id_provenance, id_categorie, id_geolife, volume))
                    elif 'Age' in csv_file:
                        id_age = mappings['tranches_age'].get(row['Age'].upper().strip())
                        if id_age:
                            batch_data.append((date_value, id_zone, id_provenance, id_categorie, id_age, volume))
                    else:  # Nuitee.csv ou Diurne.csv simple
                        batch_data.append((date_value, id_zone, id_provenance, id_categorie, volume))
                        
                except Exception as e:
                    logging.warning(f"Erreur traitement ligne: {e}")
                    continue
            
            # Insertion du batch
            if batch_data:
                cursor.executemany(insert_query, batch_data)
                inserted_count += len(batch_data)
        
        logging.info(f"[OK] {table_name}: {inserted_count}/{total_rows} lignes insérées")
        return inserted_count
        
    except Exception as e:
        logging.error(f"Erreur traitement {csv_file}: {e}")
        return 0

def main():
    parser = argparse.ArgumentParser(description='Alimente les tables principales FluxVision')
    parser.add_argument('--host', default='localhost', help='Host MySQL')
    parser.add_argument('--port', type=int, default=3306, help='Port MySQL')
    parser.add_argument('--user', default='root', help='Utilisateur MySQL')
    parser.add_argument('--password', default='', help='Mot de passe MySQL')
    parser.add_argument('--database', default='fluxvision', help='Nom de la base de données')
    parser.add_argument('--batch-size', type=int, default=5000, help='Taille des batches')
    parser.add_argument('--data-dir', default='data/data_clean/data_merged_csv', help='Répertoire des fichiers CSV')
    parser.add_argument('--clear-today', action='store_true', help='Supprimer les enregistrements d\'aujourd\'hui avant insertion')
    
    args = parser.parse_args()
    
    logging.info(f"Batch size: {args.batch_size}")
    
    # Connexion à la base de données
    connection = setup_database_connection(args.host, args.port, args.user, args.password, args.database)
    cursor = connection.cursor()
    
    try:
        logging.info(f"Base de données '{args.database}' sélectionnée")
        logging.info("=== DÉBUT ALIMENTATION TABLES PRINCIPALES ===")
        
        start_time = datetime.now()
        data_dir = Path(args.data_dir)
        
        # Fichiers CSV à traiter
        csv_files = [
            'Nuitee.csv',
            'Diurne.csv', 
            'Nuitee_Departement.csv',
            'Diurne_Departement.csv',
            'Nuitee_Pays.csv',
            'Diurne_Pays.csv',
            'Diurne_Geolife.csv',
            'Nuitee_Geolife.csv',
            'Diurne_Age.csv',
            'Nuitee_Age.csv'
        ]
        
        # Tables correspondantes
        table_mappings = {
            'Nuitee.csv': 'fact_nuitees',
            'Diurne.csv': 'fact_diurnes',
            'Nuitee_Departement.csv': 'fact_nuitees_departements',
            'Diurne_Departement.csv': 'fact_diurnes_departements',
            'Nuitee_Pays.csv': 'fact_nuitees_pays',
            'Diurne_Pays.csv': 'fact_diurnes_pays',
            'Diurne_Geolife.csv': 'fact_diurnes_geolife',
            'Nuitee_Geolife.csv': 'fact_nuitees_geolife',
            'Diurne_Age.csv': 'fact_diurnes_age',
            'Nuitee_Age.csv': 'fact_nuitees_age'
        }
        
        # Supprimer les enregistrements d'aujourd'hui si demandé
        if args.clear_today:
            clear_today_records(cursor, table_mappings)
            connection.commit()
        
        # Alimenter dim_dates avec nouvelles dates
        populate_dim_dates(cursor, csv_files, data_dir)
        connection.commit()
        
        # Récupérer les mappings des dimensions
        mappings = get_dimension_mappings(cursor)
        
        logging.info("=== ALIMENTATION DES TABLES PRINCIPALES ===")
        
        total_inserted = 0
        
        for csv_file in csv_files:
            if csv_file in table_mappings:
                table_name = table_mappings[csv_file]
                logging.info(f"Alimentation {table_name} depuis {csv_file}...")
                
                inserted = process_csv_file(cursor, mappings, csv_file, data_dir, table_name, args.batch_size)
                total_inserted += inserted
                connection.commit()
        
        end_time = datetime.now()
        duration = (end_time - start_time).total_seconds()
        
        logging.info("=== STATISTIQUES FINALES ===")
        logging.info(f"Total enregistrements insérés: {total_inserted}")
        logging.info(f"=== ALIMENTATION TABLES PRINCIPALES TERMINÉE EN {duration:.2f}s ===")
        logging.info("[SUCCES] Tables principales alimentées")
        
    except Exception as e:
        logging.error(f"Erreur générale: {e}")
        connection.rollback()
        sys.exit(1)
    finally:
        cursor.close()
        connection.close()
        logging.info("Connexions fermées")

if __name__ == "__main__":
    main() 