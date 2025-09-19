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
from pathlib import Path
from datetime import datetime, timezone
from concurrent.futures import ThreadPoolExecutor, as_completed

# Configuration
DATA_ZIP_DIR   = 'downloads'
DATA_EXTRACTED = 'data/data_extracted'
DATA_CLEAN     = 'data/data_clean'
STATE_FILE     = 'data/.file_state.json'
LOCK_FILE      = 'data/.process.lock'
TMP_EXTRACTED  = DATA_EXTRACTED + '_tmp'
TMP_CLEAN      = DATA_CLEAN + '_tmp'

logging.basicConfig(level=logging.INFO, format="%(message)s")

def cleanup_temp_dirs():
    """Nettoie les dossiers temporaires"""
    temp_dirs = [TMP_EXTRACTED, TMP_CLEAN]
    for dir_path in temp_dirs:
        if os.path.exists(dir_path):
            try:
                shutil.rmtree(dir_path)
                logging.info(f"Dossier temporaire supprimé: {dir_path}")
            except Exception as e:
                logging.error(f"Erreur lors de la suppression du dossier temporaire {dir_path}: {e}")

def acquire_lock():
    """Acquiert un verrou pour éviter les exécutions simultanées"""
    os.makedirs(os.path.dirname(LOCK_FILE), exist_ok=True)
    
    # Vérifier si le fichier de verrouillage existe et n'est pas vide
    if os.path.exists(LOCK_FILE) and os.path.getsize(LOCK_FILE) == 0:
        try:
            os.remove(LOCK_FILE)
            logging.info("Fichier de verrouillage vide supprimé")
        except Exception as e:
            logging.error(f"Erreur lors de la suppression du fichier de verrouillage vide: {e}")
    
    try:
    lock = open(LOCK_FILE, 'w+')
        if os.name == 'nt':
            import msvcrt
            msvcrt.locking(lock.fileno(), msvcrt.LK_NBLCK, 1)
        else:
            import fcntl
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        # Écrire un timestamp dans le fichier de verrouillage
        lock.write(f"Locked at: {datetime.now(timezone.utc).isoformat()}\n")
        lock.flush()
        return lock
    except Exception as e:
        logging.error(f'Erreur lors de l\'acquisition du verrou: {e}')
        sys.exit(1)

def release_lock(lock):
    """Libère le verrou"""
    try:
        if os.name == 'nt':
            import msvcrt
            msvcrt.locking(lock.fileno(), msvcrt.LK_UNLCK, 1)
        else:
            import fcntl
            fcntl.flock(lock, fcntl.LOCK_UN)
    except Exception as e:
        logging.error(f"Erreur lors de la libération du verrou: {e}")
    finally:
        try:
        lock.close()
            if os.path.exists(LOCK_FILE):
                os.remove(LOCK_FILE)
                logging.info("Fichier de verrouillage supprimé")
        except Exception as e:
            logging.error(f"Erreur lors de la suppression du fichier de verrouillage: {e}")

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
        return csv.Sniffer().sniff(sample).delimiter
    except Exception:
        return ',' # Default delimiter

def read_polars_csv_safely(path, expected_cols=None):
    delim = detect_delimiter(path)
    try:
        df = pl.read_csv(path, separator=delim, infer_schema_length=1000, try_parse_dates=True)
        # Normaliser les noms de colonnes en minuscules
        df = df.rename({col: col.lower() for col in df.columns})
    except Exception as e:
        logging.warning(f"Polars direct read_csv failed for {path} with delimiter '{delim}': {e}. Retrying with ignore_errors=True.")
        try:
            df = pl.read_csv(path, separator=delim, infer_schema_length=1000, ignore_errors=True, try_parse_dates=True)
            # Normaliser les noms de colonnes en minuscules
            df = df.rename({col: col.lower() for col in df.columns})
        except Exception as read_err:
            logging.error(f"Critical error reading {path} with Polars: {read_err}")
            raise
            
    if expected_cols is not None:
        expected_cols = [col.lower() for col in expected_cols]  # Normaliser aussi les colonnes attendues
        if df.columns != expected_cols:
        raise ValueError(f"Unexpected columns in {path}: {df.columns} vs {expected_cols}")
    return df

# ----------------- Fusion intelligente (CSV -> CSV) -----------------
def merge_to_csv(csv_paths, out_csv_path, incremental=False):
    if not csv_paths:
        logging.warning(f"Aucun fichier CSV à fusionner pour {out_csv_path}.")
        return

    dfs = []
    if incremental and os.path.exists(out_csv_path):
        try:
            old_df = read_polars_csv_safely(out_csv_path)
            dfs.append(old_df)
            logging.info(f"Chargé existant {out_csv_path} ({len(old_df)} lignes, {len(old_df.columns)} colonnes)")
        except Exception as e:
            logging.error(f"Impossible de charger existant {out_csv_path}: {e}")

    max_workers = min(os.cpu_count() or 1, 8)
    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        future_to_path = {executor.submit(read_polars_csv_safely, p): p for p in csv_paths}
        for future in as_completed(future_to_path):
            p = future_to_path[future]
            try:
                df = future.result()
                dfs.append(df)
                logging.info(f"Chargé {p} ({len(df)} lignes, {len(df.columns)} colonnes)")
            except Exception as e:
                logging.error(f"Erreur lecture {p}: {e}")

    if not dfs:
        logging.warning(f"Aucun DataFrame Polars disponible pour fusionner vers {out_csv_path}.")
        return

    try:
        merged = pl.concat(dfs, how="diagonal_relaxed")
    except TypeError:
        merged = pl.concat(dfs, how="diagonal")

    # S'assurer que toutes les colonnes sont en minuscules
    merged = merged.rename({col: col.lower() for col in merged.columns})

    before = len(merged)
    merged = merged.unique(keep="first", maintain_order=True)
    after = len(merged)
    logging.info(f"Fusion: {before} → {after} lignes après suppression des doublons.")

    try:
        merged.write_csv(out_csv_path, separator=',')
        logging.info(f"Fusion enregistrée (CSV) dans {out_csv_path}")
    except Exception as e:
        logging.error(f"Erreur lors de l'écriture CSV vers {out_csv_path}: {e}")

# ----------------- Extraction incrémentale -----------------
def extract_recursive(src_dir, dst_dir, old_hashes):
    if os.path.exists(dst_dir):
        shutil.rmtree(dst_dir)
    os.makedirs(dst_dir, exist_ok=True)

    new_hashes = {}
    to_process = []
    changed_roots = []

    # Recherche récursive des fichiers ZIP
    for root, _, files in os.walk(src_dir):
        for fname in files:
        if not fname.lower().endswith('.zip'):
            continue
            path = os.path.join(root, fname)
        h = file_hash(path)
        new_hashes[fname] = h
        if old_hashes.get(fname) == h:
            logging.info(f"Unchanged archive: {fname}")
            continue
        stem = os.path.splitext(fname)[0]
        changed_roots.append(stem)
        to_process.append((path, os.path.join(dst_dir, stem)))

    if not to_process:
        logging.warning(f"No new/modified ZIP in {src_dir} or its subdirectories")

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
                if os.path.commonpath([os.path.abspath(zip_path), abs_dst]) == abs_dst:
                    os.remove(zip_path)
            except Exception:
                pass

    return new_hashes, changed_roots

def extract_year(name):
    m = re.search(r"\d{4}", name)
    return m.group(0) if m else 'unknown'

# ----------------- Traitement incrémental avec parallélisme -----------------
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

    for d in items:
        path = os.path.join(src_root, d)
        if not os.path.isdir(path):
            continue

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

        if 'Tourisme' in d:
            all_csv_paths = [os.path.join(r, f)
                             for r, _, files in os.walk(path)
                             for f in files if f.lower().endswith('.csv')]
            national_csv = os.path.join(dst_root, 'TourismeNational.csv')
            tasks.append((all_csv_paths, national_csv, not full_rebuild))
            continue

        file_map = {}
        # CORRECTION: Recherche récursive dans tous les sous-dossiers
        for root, dirs, files in os.walk(path):
            for filename in files:
                if filename.lower().endswith('.csv'):
                    filepath = os.path.join(root, filename)
                    parts = filename.split('_')
                    idx = next((i for i, p in enumerate(parts) if 'B' in p and any(c.isdigit() for c in p)), None)
                    clean_name_base = '_'.join(parts[:idx]) if idx is not None else os.path.splitext(filename)[0]
                    out_csv_fname = clean_name_base + '.csv'
                    file_map.setdefault(out_csv_fname, []).append(filepath)
        
        for csv_fname, csv_paths_list in file_map.items():
            out_csv_file = os.path.join(merged_dir, csv_fname)
            tasks.append((csv_paths_list, out_csv_file, not full_rebuild))
            
        logging.info(f"Traitement territoire {d}: {len(file_map)} types de fichiers trouvés")

    max_workers = min(os.cpu_count() or 1, 8)
    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        futures = [executor.submit(merge_to_csv, paths, out_path, inc)
                   for paths, out_path, inc in tasks]
        for future in as_completed(futures):
            try:
                future.result()
            except Exception as e:
                logging.error(f"Erreur lors de la fusion parallèle (Polars/CSV): {e}", exc_info=True)

def is_dir_empty(dir_path):
    """Vérifie si un dossier est vide"""
    try:
        return len(os.listdir(dir_path)) == 0
    except Exception:
        return True

def remove_empty_dirs(dir_path):
    """Supprime un dossier s'il est vide"""
    try:
        if os.path.exists(dir_path) and is_dir_empty(dir_path):
            os.rmdir(dir_path)
            logging.info(f"Dossier vide supprimé: {dir_path}")
            return True
    except Exception as e:
        logging.error(f"Erreur lors de la suppression du dossier {dir_path}: {e}")
    return False

# ----------------- Main -----------------
if __name__ == '__main__':
    print("🚀 FluxVision - Traitement des fichiers ZIP")
    print("=" * 40)
    
    # Nettoyer les dossiers temporaires au démarrage
    cleanup_temp_dirs()
    
    lock = None
    try:
        lock = acquire_lock()
        state = load_state()
        old_hashes = state.get('hashes', {})
        history = state.get('history', {})

        # Vérifier si le dossier downloads existe
        if not os.path.exists(DATA_ZIP_DIR):
            os.makedirs(DATA_ZIP_DIR)
            logging.info(f"Dossier {DATA_ZIP_DIR} créé")

        new_hashes, changed_roots = extract_recursive(DATA_ZIP_DIR, TMP_EXTRACTED, old_hashes)
        
        if os.path.exists(DATA_EXTRACTED):
            shutil.rmtree(DATA_EXTRACTED)
        os.rename(TMP_EXTRACTED, DATA_EXTRACTED)
        logging.info('Extraction completed')

        removed_archives = set(old_hashes) - set(new_hashes)
        full_rebuild = bool(removed_archives)
        if removed_archives:
            logging.info(f"Deleted archives detected, full rebuild triggered: {removed_archives}")
        
        # Force un full_rebuild pour s'assurer que toutes les années sont traitées
        full_rebuild = True
        logging.info("Force full rebuild to ensure all years (2019-2025) are processed")

        process_data(DATA_EXTRACTED, TMP_CLEAN, changed_roots, full_rebuild=full_rebuild)
        
        if os.path.exists(DATA_CLEAN):
            shutil.rmtree(DATA_CLEAN)
        os.rename(TMP_CLEAN, DATA_CLEAN)
        logging.info('Processing completed. Clean data in CSV format is in %s', DATA_CLEAN)

        # Nettoyage des dossiers vides
        logging.info("\nNettoyage des dossiers vides...")
        
        # Suppression du dossier extracted s'il est vide
        if os.path.exists(DATA_EXTRACTED):
            if is_dir_empty(DATA_EXTRACTED):
                shutil.rmtree(DATA_EXTRACTED)
                logging.info(f"Dossier vide supprimé: {DATA_EXTRACTED}")
            else:
                logging.info(f"Dossier non vide conservé: {DATA_EXTRACTED}")

        # Vérification et suppression des sous-dossiers vides
        for root_dir in ['/extracted', '/carte', '/organized']:
            dir_path = os.path.join(DATA_CLEAN, root_dir.lstrip('/'))
            if os.path.exists(dir_path):
                if is_dir_empty(dir_path):
                    shutil.rmtree(dir_path)
                    logging.info(f"Dossier vide supprimé: {dir_path}")
                else:
                    logging.info(f"Dossier non vide conservé: {dir_path}")

        state['hashes'] = new_hashes
        current_time_utc = datetime.now(timezone.utc).isoformat()
        for stem in changed_roots:
            history[stem] = {'processed_at': current_time_utc, 'status': 'updated'}
        for stem in removed_archives:
            if stem in history:
                history[stem]['status'] = 'removed_archive'
                history[stem]['removed_at'] = current_time_utc
            else:
                history[stem] = {'status': 'removed_archive_untracked', 'removed_at': current_time_utc}
        
        if full_rebuild:
            for stem in new_hashes.keys():
                if stem not in changed_roots and stem not in removed_archives:
                     if stem in history:
                        history[stem]['last_rebuild_processed_at'] = current_time_utc
                        history[stem]['status'] = 'rebuilt_due_to_full_rebuild'
                     else:
                        history[stem] = {'processed_at': current_time_utc, 'status': 'initial_full_rebuild'}

        state['history'] = history
        save_state(state)
        
        print("\n✅ Traitement terminé avec succès!")
        
    except Exception as e:
        logging.error(f"Une erreur est survenue: {e}", exc_info=True)
        # En cas d'erreur, on nettoie les dossiers temporaires
        cleanup_temp_dirs()
    finally:
        if lock:
        release_lock(lock) 
        # Nettoyage final des dossiers temporaires
        cleanup_temp_dirs() 