import os
import zipfile
import shutil
import logging
import polars as pl
import csv
import re
import hashlib
import json
import sys
from datetime import datetime, timezone
from concurrent.futures import ThreadPoolExecutor, as_completed
import gc
import psutil
import time

# Windows-specific locking
if os.name == 'nt':
    import msvcrt
else:
    import fcntl

# Configuration optimisée pour la mémoire
DATA_ZIP_DIR   = 'data/data_zip'
DATA_EXTRACTED = 'data/data_extracted'
DATA_CLEAN     = 'data/data_clean_csv'
STATE_FILE     = 'data/.file_state.json'
LOCK_FILE      = 'data/.process.lock'
SCHEMA_CACHE_FILE = 'data/.schema_cache.json'
TMP_EXTRACTED  = DATA_EXTRACTED + '_tmp'
TMP_CLEAN      = DATA_CLEAN + '_tmp'

# Configuration mémoire
MAX_MEMORY_USAGE_GB = 2.0  # Limite mémoire en GB
BATCH_SIZE = 20  # Nombre de fichiers traités par batch
MIN_WORKERS = 2  # Minimum de workers parallèles

logging.basicConfig(level=logging.INFO, format="%(message)s")

# ----------------- Concurrency / Lock -----------------
def acquire_lock():
    os.makedirs(os.path.dirname(LOCK_FILE), exist_ok=True)
    lock = open(LOCK_FILE, 'w+')
    try:
        if os.name == 'nt':
            msvcrt.locking(lock.fileno(), msvcrt.LK_NBLCK, 1)
        else:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except Exception:
        logging.error('Another instance is running. Aborting.')
        sys.exit(1)
    return lock

def release_lock(lock):
    try:
        if os.name == 'nt':
            msvcrt.locking(lock.fileno(), msvcrt.LK_UNLCK, 1)
        else:
            fcntl.flock(lock, fcntl.LOCK_UN)
    except Exception:
        pass
    finally:
        lock.close()

# ----------------- State / History -----------------
def load_state():
    if os.path.exists(STATE_FILE):
        with open(STATE_FILE, 'r', encoding='utf-8') as f:
            return json.load(f)
    return {'hashes': {}, 'history': {}}

def save_state(state):
    os.makedirs(os.path.dirname(STATE_FILE), exist_ok=True)
    with open(STATE_FILE, 'w', encoding='utf-8') as f:
        json.dump(state, f, indent=2)

# ----------------- Utils -----------------
def file_hash(path):
    h = hashlib.sha256()
    with open(path, 'rb') as f:
        for chunk in iter(lambda: f.read(8192), b''):
            h.update(chunk)
    return h.hexdigest()

def detect_delimiter(path):
    with open(path, 'r', newline='', encoding='utf-8', errors='ignore') as f:
        sample = f.read(2048)
    try:
        sniffer = csv.Sniffer()
        delimiter = sniffer.sniff(sample).delimiter
        # Vérifier si le délimiteur détecté semble raisonnable
        if delimiter in [',', ';', '\t', '|']:
            return delimiter
        else:
            # Forcer le point-virgule si pas de délimiteur évident
            return ';' if ';' in sample else ','
    except Exception:
        # Détection manuelle si sniffer échoue
        delimiters = [';', ',', '\t', '|']
        delimiter_counts = {}
        for delim in delimiters:
            delimiter_counts[delim] = sample.count(delim)
        
        # Choisir le délimiteur le plus fréquent
        best_delimiter = max(delimiter_counts, key=delimiter_counts.get)
        return best_delimiter if delimiter_counts[best_delimiter] > 0 else ','

def normalize_column_names(df):
    """Normalise les noms de colonnes en minuscules et supprime les espaces."""
    # Créer un dictionnaire de mapping ancien nom -> nouveau nom
    column_mapping = {}
    
    for col in df.columns:
        # Cas spécial : si la colonne contient plusieurs noms séparés par ';' (format mal parsé)
        if ';' in col and len(df.columns) == 1:
            # Le fichier n'a pas été correctement parsé - toutes les colonnes sont dans un seul champ
            logging.warning(f"Détection de format mal parsé - colonne unique contenant ';': {col[:100]}...")
            # Dans ce cas, on va essayer de renommer cette colonne de façon plus intelligente
            normalized = "merged_columns_data"
            column_mapping[col] = normalized
        else:
            # Normaliser : minuscules, remplacer espaces par underscores, supprimer caractères spéciaux
            normalized = col.lower().strip()
            normalized = normalized.replace(' ', '_')
            normalized = normalized.replace('-', '_')
            normalized = ''.join(c for c in normalized if c.isalnum() or c == '_')
            
            # Éviter les noms vides ou qui commencent par un chiffre
            if not normalized or normalized[0].isdigit():
                normalized = f"col_{normalized}" if normalized else f"col_{len(column_mapping)}"
            
            # Éviter les doublons
            original_normalized = normalized
            counter = 1
            while normalized in column_mapping.values():
                normalized = f"{original_normalized}_{counter}"
                counter += 1
                
            column_mapping[col] = normalized
    
    # Renommer les colonnes
    df = df.rename(column_mapping)
    return df, column_mapping

# ----------------- Système de mapping intelligent des colonnes -----------------
class ColumnMappingManager:
    """Gestionnaire intelligent des mappings de colonnes pour optimiser les lectures."""
    
    def __init__(self, cache_file=SCHEMA_CACHE_FILE):
        self.cache_file = cache_file
        self.schemas = {}  # file_type -> {master_schema: [col1, col2, ...], variants: [...]}
        self.load_cache()
    
    def load_cache(self):
        """Charge le cache des schémas depuis le disque."""
        if os.path.exists(self.cache_file):
            try:
                with open(self.cache_file, 'r', encoding='utf-8') as f:
                    self.schemas = json.load(f)
                logging.info(f"Cache des schémas chargé: {len(self.schemas)} types de fichiers")
            except Exception as e:
                logging.warning(f"Erreur chargement cache schémas: {e}")
                self.schemas = {}
    
    def save_cache(self):
        """Sauvegarde le cache des schémas sur le disque."""
        try:
            os.makedirs(os.path.dirname(self.cache_file), exist_ok=True)
            with open(self.cache_file, 'w', encoding='utf-8') as f:
                json.dump(self.schemas, f, indent=2, ensure_ascii=False)
            logging.info(f"Cache des schémas sauvegardé: {len(self.schemas)} types")
        except Exception as e:
            logging.error(f"Erreur sauvegarde cache schémas: {e}")
    
    def normalize_column_name(self, col_name):
        """Normalise un nom de colonne."""
        normalized = col_name.lower().strip()
        normalized = normalized.replace(' ', '_').replace('-', '_')
        normalized = ''.join(c for c in normalized if c.isalnum() or c == '_')
        if not normalized or normalized[0].isdigit():
            normalized = f"col_{normalized}" if normalized else "unnamed_col"
        return normalized
    
    def extract_file_type(self, file_path):
        """Extrait le type de fichier à partir du chemin."""
        filename = os.path.basename(file_path)
        parts = filename.split('_')
        
        # Trouver l'index du bimestre (B1, B2, etc.)
        bimestre_idx = None
        for i, part in enumerate(parts):
            if 'B' in part and any(c.isdigit() for c in part):
                bimestre_idx = i
                break
        
        if bimestre_idx is not None:
            return '_'.join(parts[:bimestre_idx])
        else:
            return os.path.splitext(filename)[0]
    
    def get_master_schema(self, file_type):
        """Retourne le schéma maître pour un type de fichier."""
        if file_type not in self.schemas:
            self.schemas[file_type] = {
                'master_schema': [],
                'variants': [],
                'column_mappings': {}
            }
        return self.schemas[file_type]['master_schema']
    
    def update_schema(self, file_path, original_columns):
        """Met à jour le schéma maître avec de nouvelles colonnes."""
        file_type = self.extract_file_type(file_path)
        normalized_columns = [self.normalize_column_name(col) for col in original_columns]
        
        # Créer le mapping original -> normalisé
        column_mapping = dict(zip(original_columns, normalized_columns))
        
        if file_type not in self.schemas:
            self.schemas[file_type] = {
                'master_schema': normalized_columns.copy(),
                'variants': [normalized_columns.copy()],
                'column_mappings': {str(normalized_columns): column_mapping}
            }
            logging.info(f"Nouveau schéma créé pour {file_type}: {len(normalized_columns)} colonnes")
            return normalized_columns, column_mapping, True  # True = nouveau schéma
        
        master_schema = self.schemas[file_type]['master_schema']
        
        # Vérifier si ce schéma exact existe déjà
        normalized_key = str(normalized_columns)
        if normalized_key in self.schemas[file_type]['column_mappings']:
            # Schéma connu, utiliser le mapping existant
            return normalized_columns, self.schemas[file_type]['column_mappings'][normalized_key], False
        
        # Nouveau variant, mettre à jour le schéma maître
        updated = False
        for col in normalized_columns:
            if col not in master_schema:
                master_schema.append(col)
                updated = True
        
        # Sauvegarder ce nouveau variant
        self.schemas[file_type]['variants'].append(normalized_columns.copy())
        self.schemas[file_type]['column_mappings'][normalized_key] = column_mapping
        
        if updated:
            logging.info(f"Schéma {file_type} mis à jour: {len(master_schema)} colonnes total")
        
        return normalized_columns, column_mapping, updated
    
    def align_dataframe_to_master(self, df, file_path):
        """Aligne un DataFrame sur le schéma maître."""
        file_type = self.extract_file_type(file_path)
        master_schema = self.get_master_schema(file_type)
        
        if not master_schema:
            # Pas de schéma maître, ce DataFrame devient la référence
            return df
        
        current_columns = df.columns
        
        # Ajouter les colonnes manquantes avec des valeurs nulles
        for col in master_schema:
            if col not in current_columns:
                df = df.with_columns(pl.lit(None).alias(col))
        
        # Réorganiser les colonnes selon le schéma maître
        # + garder les colonnes supplémentaires à la fin
        ordered_columns = []
        for col in master_schema:
            if col in df.columns:
                ordered_columns.append(col)
        
        # Ajouter les colonnes qui ne sont pas dans le schéma maître
        for col in df.columns:
            if col not in ordered_columns:
                ordered_columns.append(col)
        
        df = df.select(ordered_columns)
        return df

# Instance globale du gestionnaire
column_manager = ColumnMappingManager()

def read_polars_csv_safely(path, expected_cols=None, normalize_columns=True):
    """
    Lecture sécurisée des fichiers CSV avec gestion robuste des erreurs.
    """
    delim = detect_delimiter(path)
    
    # Vérifier la taille du fichier d'abord
    try:
        file_size = os.path.getsize(path)
        if file_size == 0:
            logging.warning(f"Fichier vide ignoré: {path}")
            return None, None
        elif file_size > 100 * 1024 * 1024:  # 100MB
            logging.info(f"Fichier volumineux détecté ({file_size/1024/1024:.1f}MB): {path}")
    except Exception as size_error:
        logging.warning(f"Impossible de vérifier la taille de {path}: {size_error}")
    
    # Tentatives de lecture avec différentes stratégies
    strategies = [
        # Stratégie 1: Lecture normale
        {'separator': delim, 'infer_schema_length': 1000, 'try_parse_dates': True},
        # Stratégie 2: Avec ignore_errors
        {'separator': delim, 'infer_schema_length': 1000, 'ignore_errors': True, 'try_parse_dates': True},
        # Stratégie 3: Délimiteur alternatif
        {'separator': ';' if delim != ';' else ',', 'infer_schema_length': 1000, 'try_parse_dates': True},
        # Stratégie 4: Mode très permissif
        {'separator': delim, 'infer_schema_length': 100, 'ignore_errors': True, 'try_parse_dates': False},
        # Stratégie 5: Lecture basique sans inférence
        {'separator': delim, 'infer_schema_length': 0, 'ignore_errors': True, 'try_parse_dates': False}
    ]
    
    df = None
    strategy_used = None
    
    for i, strategy in enumerate(strategies, 1):
        try:
            df = pl.read_csv(path, **strategy)
            strategy_used = i
            break
        except Exception as e:
            if i == len(strategies):
                logging.error(f"❌ Toutes les stratégies de lecture ont échoué pour {path}: {e}")
                return None, None
            else:
                logging.debug(f"Stratégie {i} échouée pour {path}: {e}")
                continue
    
    if df is None:
        return None, None
    
    # Vérifications post-lecture
    if len(df) == 0:
        logging.warning(f"DataFrame vide après lecture: {path}")
        return None, None
    
    # Vérifier si on a une seule colonne avec beaucoup de texte (mal parsé)
    if len(df.columns) == 1 and ';' in df.columns[0]:
        logging.warning(f"Fichier mal parsé détecté - colonne unique contenant ';': {path}")
        # Essayer de re-parser avec un délimiteur différent
        try:
            df = pl.read_csv(path, separator=';', infer_schema_length=100, ignore_errors=True)
            logging.info(f"✅ Re-parsing réussi avec ';' pour {path}")
        except Exception as reparse_error:
            logging.warning(f"Re-parsing échoué: {reparse_error}")
    
    # Utiliser le gestionnaire de mapping intelligent
    column_mapping = None
    if normalize_columns:
        try:
            original_columns = df.columns.copy()
            
            # Mettre à jour le schéma et obtenir le mapping
            normalized_columns, column_mapping, schema_updated = column_manager.update_schema(path, original_columns)
            
            # Renommer les colonnes
            rename_dict = dict(zip(original_columns, normalized_columns))
            df = df.rename(rename_dict)
            
            # Aligner sur le schéma maître
            df = column_manager.align_dataframe_to_master(df, path)
            
        except Exception as normalize_error:
            logging.error(f"❌ Erreur normalisation colonnes pour {path}: {normalize_error}")
            # Continuer sans normalisation
            pass
    
    # Vérification finale des colonnes attendues
    if expected_cols is not None and df.columns != expected_cols:
        logging.warning(f"Colonnes inattendues dans {path}: {df.columns} vs {expected_cols}")
        # Ne pas lever d'erreur, juste avertir
    
    if strategy_used > 1:
        logging.info(f"✅ Fichier lu avec stratégie {strategy_used}: {path} ({len(df)} lignes, {len(df.columns)} colonnes)")
    
    return df, column_mapping

def get_memory_usage():
    """Retourne l'utilisation mémoire actuelle en GB."""
    process = psutil.Process()
    return process.memory_info().rss / 1024 / 1024 / 1024

def optimize_workers_for_memory():
    """Calcule le nombre optimal de workers selon la mémoire disponible."""
    available_memory = psutil.virtual_memory().available / 1024 / 1024 / 1024
    current_memory = get_memory_usage()
    
    # Estimer combien de workers on peut se permettre
    if available_memory > 8:
        max_workers = 6
    elif available_memory > 4:
        max_workers = 4
    else:
        max_workers = MIN_WORKERS
    
    # Ajuster selon l'utilisation actuelle
    if current_memory > MAX_MEMORY_USAGE_GB:
        max_workers = max(MIN_WORKERS, max_workers // 2)
    
    logging.info(f"Mémoire: {current_memory:.1f}GB utilisée, {available_memory:.1f}GB disponible → {max_workers} workers")
    return max_workers

def merge_to_csv_memory_optimized(csv_paths, out_csv_path, incremental=False):
    """
    Version optimisée mémoire : traite les fichiers par batches et sauvegarde en CSV.
    Plus stable que Parquet, évite les bugs Polars LazyFrame.
    """
    if not csv_paths:
        logging.warning(f"Aucun fichier CSV à fusionner pour {out_csv_path}.")
        return

    logging.info(f"🚀 Starting memory-optimized CSV merge for {out_csv_path}: {len(csv_paths)} CSV files")
    
    # En mode incrémental, charger l'existant comme point de départ
    existing_df = None
    if incremental and os.path.exists(out_csv_path):
        try:
            existing_df = pl.read_csv(out_csv_path, separator=';', infer_schema_length=1000)
            logging.info(f"✅ Chargé existant: {len(existing_df)} lignes, {len(existing_df.columns)} colonnes")
        except Exception as e:
            logging.warning(f"⚠️  Impossible de charger l'existant {out_csv_path}: {e}")
            existing_df = None

    # Traitement par batches pour optimiser la mémoire
    total_files = len(csv_paths)
    total_rows_processed = 0
    all_batch_dfs = []
    
    # Ajouter l'existant en premier si disponible
    if existing_df is not None:
        all_batch_dfs.append(existing_df)
        total_rows_processed += len(existing_df)
        logging.info(f"📊 Données existantes ajoutées: {len(existing_df)} lignes")
    
    for batch_start in range(0, total_files, BATCH_SIZE):
        batch_end = min(batch_start + BATCH_SIZE, total_files)
        batch_files = csv_paths[batch_start:batch_end]
        
        logging.info(f"📦 Traitement batch {batch_start//BATCH_SIZE + 1}/{(total_files-1)//BATCH_SIZE + 1}: {len(batch_files)} fichiers")
        memory_before = get_memory_usage()
        
        # Traiter ce batch
        batch_df = process_batch_memory_safe(batch_files)
        
        if batch_df is not None and len(batch_df) > 0:
            all_batch_dfs.append(batch_df)
            total_rows_processed += len(batch_df)
            logging.info(f"✅ Batch ajouté: +{len(batch_df)} lignes (total accumulé: {total_rows_processed})")
        
        # Nettoyage mémoire explicite
        del batch_df
        gc.collect()
        
        memory_after = get_memory_usage()
        logging.info(f"💾 Mémoire batch: {memory_before:.1f}GB → {memory_after:.1f}GB")
        
        # Vérification mémoire et fusion intermédiaire si nécessaire
        if memory_after > MAX_MEMORY_USAGE_GB and len(all_batch_dfs) > 1:
            logging.warning(f"⚠️  Mémoire élevée ({memory_after:.1f}GB), fusion intermédiaire...")
            try:
                # Fusionner tous les DataFrames accumulés
                intermediate_df = pl.concat(all_batch_dfs, how="vertical_relaxed")
                
                # Sauvegarder temporairement en CSV
                intermediate_path = out_csv_path.replace('.csv', f'_intermediate_{batch_start}.csv')
                intermediate_df.write_csv(intermediate_path, separator=';')
                logging.info(f"✅ Fusion intermédiaire sauvegardée: {len(intermediate_df)} lignes")
                
                # Remplacer la liste par le fichier intermédiaire
                del all_batch_dfs, intermediate_df
                gc.collect()
                
                # Recharger le fichier intermédiaire
                all_batch_dfs = [pl.read_csv(intermediate_path, separator=';', infer_schema_length=1000)]
                os.remove(intermediate_path)  # Supprimer le fichier temporaire
                
                memory_after_cleanup = get_memory_usage()
                logging.info(f"💾 Mémoire après nettoyage: {memory_after:.1f}GB → {memory_after_cleanup:.1f}GB")
                
            except Exception as intermediate_error:
                logging.error(f"❌ Erreur fusion intermédiaire: {intermediate_error}")
                # Continuer sans fusion intermédiaire
        
        # Pause si mémoire toujours élevée
        if memory_after > MAX_MEMORY_USAGE_GB:
            logging.warning(f"⚠️  Pause pour nettoyage mémoire...")
            gc.collect()
            time.sleep(2)

    # Fusion finale de tous les DataFrames
    if not all_batch_dfs:
        logging.warning("Aucun DataFrame à fusionner")
        return
    
    logging.info(f"🔄 Fusion finale de {len(all_batch_dfs)} DataFrames...")
    memory_before_final = get_memory_usage()
    
    try:
        if len(all_batch_dfs) == 1:
            final_df = all_batch_dfs[0]
            logging.info("✅ Un seul DataFrame, pas de fusion nécessaire")
        else:
            # Fusion avec fallbacks
            try:
                final_df = pl.concat(all_batch_dfs, how="vertical_relaxed")
                logging.info("✅ Fusion vertical_relaxed réussie")
            except Exception as vertical_error:
                logging.warning(f"⚠️  Fusion vertical_relaxed échouée: {vertical_error}")
                try:
                    final_df = pl.concat(all_batch_dfs, how="diagonal_relaxed")
                    logging.info("✅ Fusion diagonal_relaxed réussie")
                except Exception as diagonal_error:
                    logging.error(f"❌ Toutes les méthodes de fusion ont échoué: {diagonal_error}")
                    raise diagonal_error
        
        # Déduplication
        before_dedup = len(final_df)
        final_df = final_df.unique(maintain_order=True)
        after_dedup = len(final_df)
        logging.info(f"🔄 Déduplication: {before_dedup} → {after_dedup} lignes")
        
        # Sauvegarde finale en CSV - BEAUCOUP plus stable que Parquet
        final_df.write_csv(out_csv_path, separator=';')
        logging.info(f"✅ Fusion terminée et sauvegardée en CSV: {len(final_df)} lignes, {len(final_df.columns)} colonnes")
        
        memory_after_final = get_memory_usage()
        logging.info(f"💾 Mémoire fusion finale: {memory_before_final:.1f}GB → {memory_after_final:.1f}GB")
        
    except Exception as final_error:
        logging.error(f"❌ Erreur fusion finale: {final_error}")
        
        # Fallback ultime: sauvegarder les DataFrames séparément puis les fusionner
        logging.info("🆘 Fallback ultime: sauvegarde séparée des DataFrames...")
        temp_files = []
        
        try:
            for i, df in enumerate(all_batch_dfs):
                temp_file = out_csv_path.replace('.csv', f'_part_{i}.csv')
                df.write_csv(temp_file, separator=';')
                temp_files.append(temp_file)
                logging.info(f"✅ Partie {i+1} sauvegardée: {len(df)} lignes")
            
            # Fusionner les fichiers CSV
            if temp_files:
                logging.info(f"🔄 Fusion des {len(temp_files)} fichiers CSV...")
                csv_dfs = []
                for temp_file in temp_files:
                    csv_df = pl.read_csv(temp_file, separator=';', infer_schema_length=1000)
                    csv_dfs.append(csv_df)
                    os.remove(temp_file)  # Supprimer après lecture
                
                # Fusion finale des CSV
                final_csv_df = pl.concat(csv_dfs, how="diagonal_relaxed")
                final_csv_df = final_csv_df.unique(maintain_order=True)
                final_csv_df.write_csv(out_csv_path, separator=';')
                
                logging.info(f"✅ Fallback réussi: {len(final_csv_df)} lignes sauvegardées")
                
                del csv_dfs, final_csv_df
                gc.collect()
            
        except Exception as fallback_error:
            logging.error(f"❌ Fallback ultime échoué: {fallback_error}")
            # Nettoyer les fichiers temporaires en cas d'erreur
            for temp_file in temp_files:
                try:
                    if os.path.exists(temp_file):
                        os.remove(temp_file)
                except:
                    pass
    
    finally:
        # Nettoyage final
        del all_batch_dfs
        if 'final_df' in locals():
            del final_df
        if 'existing_df' in locals() and existing_df is not None:
            del existing_df
        gc.collect()

def process_batch_memory_safe(csv_files):
    """Traite un batch de fichiers CSV en optimisant la mémoire."""
    if not csv_files:
        return None
    
    # Optimiser le nombre de workers selon la mémoire
    max_workers = optimize_workers_for_memory()
    
    dfs = []
    successful_reads = 0
    failed_reads = 0
    
    def read_and_align_csv_safe(path):
        """Version memory-safe de la lecture CSV."""
        try:
            result = read_polars_csv_safely(path, normalize_columns=True)
            if result is None or result[0] is None:
                return None, path, "Fichier non lisible ou vide"
            df, column_mapping = result
            return df, path, None
        except Exception as e:
            return None, path, str(e)
    
    # Lecture parallèle avec moins de workers
    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        future_to_path = {executor.submit(read_and_align_csv_safe, p): p for p in csv_files}
        
        for future in as_completed(future_to_path):
            p = future_to_path[future]
            try:
                df, file_path, error = future.result()
                if df is not None:
                    dfs.append(df)
                    successful_reads += 1
                else:
                    failed_reads += 1
                    if error:
                        logging.warning(f"⚠️  Lecture échouée {p}: {error}")
            except Exception as e:
                failed_reads += 1
                logging.error(f"❌ Erreur future {p}: {e}")
    
    if not dfs:
        logging.warning(f"Aucun DataFrame chargé dans ce batch ({failed_reads} échecs)")
        return None
    
    # Fusion des DataFrames du batch
    try:
        if len(dfs) == 1:
            merged = dfs[0]
        else:
            # Concaténation optimisée avec fallbacks
            try:
                merged = pl.concat(dfs, how="vertical")
                logging.debug("Concaténation vertical réussie")
            except Exception as vertical_error:
                logging.debug(f"Concaténation vertical échouée: {vertical_error}")
                try:
                    merged = pl.concat(dfs, how="vertical_relaxed")
                    logging.debug("Concaténation vertical_relaxed réussie")
                except Exception as relaxed_error:
                    logging.warning(f"Concaténation vertical_relaxed échouée: {relaxed_error}")
                    # Dernier recours: diagonal_relaxed
                    merged = pl.concat(dfs, how="diagonal_relaxed")
                    logging.debug("Concaténation diagonal_relaxed réussie")
        
        logging.info(f"✅ Batch traité: {successful_reads}/{len(csv_files)} fichiers, {len(merged)} lignes")
        if failed_reads > 0:
            logging.warning(f"⚠️  {failed_reads} fichiers n'ont pas pu être lus dans ce batch")
        
        return merged
        
    except Exception as e:
        logging.error(f"❌ Erreur fusion batch: {e}")
        return None
    finally:
        # Nettoyage explicite
        del dfs
        gc.collect()

# ----------------- Fusion intelligente (CSV -> Parquet) -----------------
def merge_to_parquet(csv_paths, out_parquet_path, incremental=False):
    """
    Fusionne plusieurs CSV en mémoire avec union des colonnes,
    contrôle de contenu, et en mode incrémental si demandé.
    Utilise le système de mapping intelligent pour optimiser les performances.
    """
    if not csv_paths:
        logging.warning(f"Aucun fichier CSV à fusionner pour {out_parquet_path}.")
        return

    logging.info(f"Starting merge for {out_parquet_path}: {len(csv_paths)} CSV files")
    dfs = []
    
    # En mode incrémental, charger l'existant (Parquet) pour le réintégrer
    if incremental and os.path.exists(out_parquet_path):
        try:
            old_df = pl.read_parquet(out_parquet_path)
            dfs.append(old_df)
            logging.info(f"Chargé existant {out_parquet_path} ({len(old_df)} lignes, {len(old_df.columns)} colonnes)")
        except Exception as e:
            logging.error(f"Impossible de charger existant {out_parquet_path}: {e}")

    # Lecture parallèle des nouveaux CSV avec mapping intelligent
    max_workers = min(os.cpu_count() or 1, 8)
    
    def read_and_align_csv(path):
        """Helper function pour la lecture et alignement automatique."""
        df, column_mapping = read_polars_csv_safely(path, normalize_columns=True)
        return df, path
    
    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        future_to_path = {executor.submit(read_and_align_csv, p): p for p in csv_paths}
        for future in as_completed(future_to_path):
            p = future_to_path[future]
            try:
                df, file_path = future.result()
                dfs.append(df)
                logging.info(f"Chargé et aligné {p} ({len(df)} lignes, {len(df.columns)} colonnes)")
            except Exception as e:
                logging.error(f"Erreur lecture {p}: {e}")

    if not dfs:
        logging.warning(f"Aucun DataFrame Polars disponible pour fusionner vers {out_parquet_path}.")
        return

    # Grâce au système de mapping, tous les DataFrames sont déjà alignés
    # La concaténation devrait être beaucoup plus simple et rapide
    try:
        merged = pl.concat(dfs, how="vertical")  # how="vertical" car colonnes alignées
        logging.info("✅ Concaténation réussie avec colonnes pré-alignées")
    except Exception as concat_err:
        logging.warning(f"Concaténation verticale échouée: {concat_err}")
        # Fallback vers la méthode diagonal en cas de problème
        try:
            merged = pl.concat(dfs, how="diagonal_relaxed")
            logging.info("✅ Concaténation réussie avec méthode diagonal_relaxed")
        except Exception as fallback_err:
            logging.error(f"Toutes les méthodes de concaténation ont échoué: {fallback_err}")
            raise fallback_err

    # Suppression des doublons
    before = len(merged)
    merged = merged.unique(keep="first", maintain_order=True)
    after = len(merged)
    logging.info(f"Fusion: {before} → {after} lignes après suppression des doublons.")

    # Écriture du résultat en Parquet
    try:
        merged.write_parquet(out_parquet_path, compression='zstd')
        logging.info(f"✅ Fusion enregistrée (Parquet) dans {out_parquet_path} - Final: {len(merged)} lignes, {len(merged.columns)} colonnes")
        
    except Exception as e:
        logging.error(f"Erreur lors de l'écriture Parquet vers {out_parquet_path}: {e}")

# Ajouter la sauvegarde du cache à la fin du processus principal
def finalize_column_mapping():
    """Finalise et sauvegarde le cache des mappings de colonnes."""
    column_manager.save_cache()
    
    # Optionnel: Afficher les statistiques du cache
    total_types = len(column_manager.schemas)
    total_variants = sum(len(schema['variants']) for schema in column_manager.schemas.values())
    logging.info(f"Cache des schémas finalisé: {total_types} types de fichiers, {total_variants} variants total")

# ----------------- Extraction incrémentale (inchangé) -----------------
def extract_recursive(src_dir, dst_dir, old_hashes):
    if os.path.exists(dst_dir):
        shutil.rmtree(dst_dir)
    os.makedirs(dst_dir, exist_ok=True)

    new_hashes = {}
    to_process = []
    changed_roots = []

    for fname in os.listdir(src_dir):
        if not fname.lower().endswith('.zip'):
            continue
        path = os.path.join(src_dir, fname)
        h = file_hash(path)
        new_hashes[fname] = h
        if old_hashes.get(fname) == h:
            logging.info(f"Unchanged archive: {fname}")
            continue
        stem = os.path.splitext(fname)[0]
        changed_roots.append(stem)
        to_process.append((path, os.path.join(dst_dir, stem)))

    if not to_process:
        logging.warning(f"No new/modified ZIP in {src_dir}")

    abs_dst = os.path.abspath(dst_dir)
    while to_process:
        zip_path, extract_folder = to_process.pop()
        os.makedirs(extract_folder, exist_ok=True)
        base = os.path.basename(zip_path)
        try:
            with zipfile.ZipFile(zip_path, 'r') as zf:
                logging.info(f"Extracting {base} → {extract_folder}")
                for member in zf.infolist():
                    target = os.path.join(extract_folder, member.filename)
                    if member.is_dir():
                        os.makedirs(target, exist_ok=True)
                    elif member.filename.lower().endswith('.zip'):
                        data = zf.read(member)
                        os.makedirs(os.path.dirname(target), exist_ok=True)
                        with open(target, 'wb') as nf:
                            nf.write(data)
                        to_process.append((target, target[:-4]))
                    else:
                        os.makedirs(os.path.dirname(target), exist_ok=True)
                        with zf.open(member) as src, open(target, 'wb') as dst:
                            shutil.copyfileobj(src, dst)
        except zipfile.BadZipFile:
            logging.error(f"Skipping bad zip: {base}")
        except Exception as e:
            logging.error(f"Error extracting {base}: {e}")
        finally:
            try:
                # Only remove if the zip_path is INSIDE the extraction destination (nested zips)
                if os.path.commonpath([os.path.abspath(zip_path), abs_dst]) == abs_dst:
                    os.remove(zip_path)
            except Exception: # os.path.commonpath can fail on Windows with different drives
                pass


    return new_hashes, changed_roots

def extract_year(name):
    m = re.search(r"\d{4}", name)
    return m.group(0) if m else 'unknown'

# ----------------- Traitement incrémental avec parallélisme optimisé mémoire -----------------
def process_data(src_root, dst_root, changed_dirs, full_rebuild=False):
    if os.path.exists(dst_root):
        if full_rebuild:
            shutil.rmtree(dst_root)
            os.makedirs(dst_root, exist_ok=True)
    os.makedirs(dst_root, exist_ok=True)

    carte_root = os.path.join(dst_root, 'carte')
    merged_dir = os.path.join(dst_root, 'data_merged_csv')
    os.makedirs(carte_root, exist_ok=True)
    os.makedirs(merged_dir, exist_ok=True)

    items = os.listdir(src_root) if full_rebuild else changed_dirs
    tasks = []
    
    # Dictionnaire global pour fusionner tous les fichiers de même type entre bimestres
    global_file_map = {}

    for d in items:
        path = os.path.join(src_root, d)
        if not os.path.isdir(path):
            continue

        # Cartes : liens annuels (pas de changement)
        if d.startswith('Cartes'):
            year = extract_year(d)
            tmp_carte_year_dir = os.path.join(carte_root, year + '_tmp')
            shutil.rmtree(tmp_carte_year_dir, ignore_errors=True)
            os.makedirs(tmp_carte_year_dir, exist_ok=True)
            
            for dirpath, _, files in os.walk(path):
                rel = os.path.relpath(dirpath, path)
                base = tmp_carte_year_dir if rel == '.' else os.path.join(tmp_carte_year_dir, rel)
                os.makedirs(base, exist_ok=True)
                for f in files:
                    src_file = os.path.join(dirpath, f)
                    dst_file = os.path.join(base, f)
                    try:
                        os.link(src_file, dst_file)
                    except OSError:
                        try:
                            os.symlink(src_file, dst_file)
                        except OSError as e_sym:
                            logging.warning(f"Could not link/symlink {src_file} to {dst_file}, copying instead. Error: {e_sym}")
                            shutil.copy2(src_file, dst_file)
            
            final_carte_year_dir = os.path.join(carte_root, year)
            if os.path.exists(final_carte_year_dir):
                shutil.rmtree(final_carte_year_dir)
            os.rename(tmp_carte_year_dir, final_carte_year_dir)
            logging.info(f"Processed Cartes for year {year}, linked to {final_carte_year_dir}")
            continue

        # Tourisme : fusion unique -> TourismeNational.csv
        if 'Tourisme' in d:
            all_csv_paths = [os.path.join(r, f)
                             for r, _, files in os.walk(path)
                             for f in files if f.lower().endswith('.csv')]
            national_csv = os.path.join(dst_root, 'TourismeNational.csv')
            tasks.append((all_csv_paths, national_csv, not full_rebuild))
            continue

        # Bimestres : collecter tous les fichiers pour fusion globale
        if d.startswith('B') and any(c.isdigit() for c in d):
            logging.info(f"Processing bimestre directory: {d}")
            for f in os.scandir(path):
                if f.is_file() and f.name.lower().endswith('.csv'):
                    parts = f.name.split('_')
                    idx = next((i for i, p in enumerate(parts) if 'B' in p and any(c.isdigit() for c in p)), None)
                    clean_name_base = '_'.join(parts[:idx]) if idx is not None else os.path.splitext(f.name)[0]
                    out_csv_fname = clean_name_base + '.csv'
                    global_file_map.setdefault(out_csv_fname, []).append(f.path)
                    
        # Traitement des autres dossiers de données
        elif not d.startswith('Cartes') and 'Tourisme' not in d:
            file_map = {}
        for sub in os.scandir(path):
            if not sub.is_dir():
                continue
            for f in os.scandir(sub.path):
                if f.is_file() and f.name.lower().endswith('.csv'):
                    parts = f.name.split('_')
                    idx = next((i for i, p in enumerate(parts) if 'B' in p and any(c.isdigit() for c in p)), None)
                    clean_name_base = '_'.join(parts[:idx]) if idx is not None else os.path.splitext(f.name)[0]
                    out_csv_fname = clean_name_base + '.csv'
                    file_map.setdefault(out_csv_fname, []).append(f.path)
            
            for csv_fname, csv_paths_list in file_map.items():
                out_csv_file = os.path.join(merged_dir, csv_fname)
                tasks.append((csv_paths_list, out_csv_file, not full_rebuild))

    # Ajouter les tâches de fusion globale pour les bimestres
    for csv_fname, csv_paths_list in global_file_map.items():
        out_csv_file = os.path.join(merged_dir, csv_fname)
        tasks.append((csv_paths_list, out_csv_file, not full_rebuild))
        logging.info(f"Scheduled global merge: {csv_fname} from {len(csv_paths_list)} CSV files")

    # Tri des tâches : les plus grandes d'abord pour optimiser la mémoire
    tasks.sort(key=lambda x: len(x[0]), reverse=True)
    
    # Statistiques initiales
    total_files = sum(len(paths) for paths, _, _ in tasks)
    logging.info(f"🚀 Traitement optimisé mémoire: {len(tasks)} tâches, {total_files} fichiers CSV total")
    memory_start = get_memory_usage()
    logging.info(f"💾 Mémoire initiale: {memory_start:.1f}GB")

    # Exécution séquentielle des tâches les plus lourdes pour éviter la surcharge mémoire
    heavy_tasks = [task for task in tasks if len(task[0]) >= BATCH_SIZE * 2]  # Tâches > 40 fichiers
    light_tasks = [task for task in tasks if len(task[0]) < BATCH_SIZE * 2]   # Tâches < 40 fichiers
    
    # Traiter d'abord les tâches lourdes en séquentiel
    if heavy_tasks:
        logging.info(f"⚡ Traitement séquentiel des {len(heavy_tasks)} tâches lourdes")
        for i, (paths, out_path, inc) in enumerate(heavy_tasks, 1):
            logging.info(f"📂 Tâche lourde {i}/{len(heavy_tasks)}: {len(paths)} fichiers → {os.path.basename(out_path)}")
            try:
                merge_to_csv_memory_optimized(paths, out_path, inc)
            except Exception as e:
                logging.error(f"❌ Erreur tâche lourde {out_path}: {e}", exc_info=True)

    # Traiter les tâches légères en parallèle (avec moins de workers)
    if light_tasks:
        logging.info(f"⚡ Traitement parallèle des {len(light_tasks)} tâches légères")
        max_workers = max(1, optimize_workers_for_memory() // 2)  # Réduire le parallélisme
        
        with ThreadPoolExecutor(max_workers=max_workers) as executor:
            futures = [executor.submit(merge_to_csv_memory_optimized, paths, out_path, inc)
                       for paths, out_path, inc in light_tasks]
            
            for i, future in enumerate(as_completed(futures), 1):
                try:
                    future.result()
                    logging.info(f"✅ Tâche légère {i}/{len(light_tasks)} terminée")
                except Exception as e:
                    logging.error(f"❌ Erreur tâche légère: {e}", exc_info=True)
    
    # Statistiques finales
    memory_end = get_memory_usage()
    logging.info(f"🎯 Traitement terminé - Mémoire: {memory_start:.1f}GB → {memory_end:.1f}GB")
    
    # Nettoyage final
    gc.collect()

# ----------------- Main -----------------
if __name__ == '__main__':
    lock = acquire_lock()
    try:
        state = load_state()
        old_hashes = state.get('hashes', {})
        history    = state.get('history', {})

        # L'extraction produit toujours des CSV et autres fichiers bruts
        new_hashes, changed_roots = extract_recursive(DATA_ZIP_DIR, TMP_EXTRACTED, old_hashes)
        
        # Remplacer l'ancien répertoire extrait par le nouveau temporaire
        if os.path.exists(DATA_EXTRACTED):
            shutil.rmtree(DATA_EXTRACTED)
        os.rename(TMP_EXTRACTED, DATA_EXTRACTED)
        logging.info('Extraction completed')

        removed_archives = set(old_hashes) - set(new_hashes)
        full_rebuild = bool(removed_archives)
        if removed_archives:
            logging.info(f"Deleted archives detected, full rebuild triggered: {removed_archives}")

        # Process_data va lire depuis DATA_EXTRACTED (CSV, etc.) et écrire dans TMP_CLEAN (Parquet, liens)
        process_data(DATA_EXTRACTED, TMP_CLEAN, changed_roots, full_rebuild=full_rebuild)
        
        # Remplacer l'ancien répertoire clean par le nouveau temporaire
        if os.path.exists(DATA_CLEAN):
            shutil.rmtree(DATA_CLEAN)
        os.rename(TMP_CLEAN, DATA_CLEAN)
        logging.info('Processing completed. Clean data in Parquet format is in %s', DATA_CLEAN)

        # Mise à jour de l'état
        state['hashes'] = new_hashes
        current_time_utc = datetime.now(timezone.utc).isoformat()
        for stem in changed_roots: # Archives modifiées ou nouvelles
            history[stem] = {'processed_at': current_time_utc, 'status': 'updated'}
        for stem in removed_archives: # Archives supprimées
            if stem in history:
                history[stem]['status'] = 'removed_archive'
                history[stem]['removed_at'] = current_time_utc
            else: # Devrait pas arriver si la logique est saine
                history[stem] = {'status': 'removed_archive_untracked', 'removed_at': current_time_utc}
        
        # Optionnel: marquer les archives non modifiées mais reprocesées lors d'un full_rebuild
        if full_rebuild:
            for stem in new_hashes.keys():
                if stem not in changed_roots and stem not in removed_archives: # Non changé mais reprocesé
                     if stem in history:
                        history[stem]['last_rebuild_processed_at'] = current_time_utc
                        history[stem]['status'] = 'rebuilt_due_to_full_rebuild'
                     else:
                        history[stem] = {'processed_at': current_time_utc, 'status': 'initial_full_rebuild'}


        state['history'] = history
        save_state(state)
    except Exception as e:
        logging.error(f"An error occurred in the main execution block: {e}", exc_info=True)
    finally:
        release_lock(lock)
        finalize_column_mapping()  # Sauvegarder le cache des schémas