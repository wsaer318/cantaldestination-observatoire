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
import subprocess
from pathlib import Path
from datetime import datetime, timezone
from concurrent.futures import ThreadPoolExecutor, as_completed

# Auto-installation des dépendances
def install_if_needed(package):
    try:
        __import__(package)
        return True
    except ImportError:
        print(f"📦 Installation de {package}...")
        try:
            subprocess.check_call([sys.executable, "-m", "pip", "install", package])
            print(f"✅ {package} installé avec succès")
            return True
        except Exception as e:
            print(f"❌ Erreur installation {package}: {e}")
            print("💡 Vérifiez que Python et pip sont installés")
            return False

# Installation des dépendances pour le téléchargement
print("🔍 Vérification des dépendances de téléchargement...")
if not install_if_needed("requests"):
    print("❌ Impossible d'installer requests - Fonctionnalité téléchargement désactivée")
    HAS_DOWNLOAD = False
else:
    HAS_DOWNLOAD = True
    
if not install_if_needed("pyyaml"):
    print("❌ Impossible d'installer pyyaml - Fonctionnalité téléchargement désactivée")
    HAS_DOWNLOAD = False

if HAS_DOWNLOAD:
    print("✅ Dépendances de téléchargement prêtes")
    import requests
    from requests.auth import HTTPBasicAuth
    import yaml
else:
    print("⚠️  Mode traitement uniquement (pas de téléchargement)")

# Windows-specific locking
if os.name == 'nt':
    import msvcrt
else:
    import fcntl

# Configuration
DATA_ZIP_DIR   = 'downloads'
DATA_EXTRACTED = 'data/data_extracted'
DATA_CLEAN     = 'data/data_clean'
STATE_FILE     = 'data/.file_state.json'
LOCK_FILE      = 'data/.process.lock'
TMP_EXTRACTED  = DATA_EXTRACTED + '_tmp'
TMP_CLEAN      = DATA_CLEAN + '_tmp'

logging.basicConfig(level=logging.INFO, format="%(message)s")

# ----------------- Configuration et téléchargement -----------------
class FluxVisionDownloader:
    def __init__(self):
        """Initialisation du téléchargeur"""
        self.base_dir = Path(__file__).parent
        self.downloads_dir = Path(DATA_ZIP_DIR)
        
        # Filtres de téléchargement (personnalisables)
        self.filters_enabled = True
        self.exclude_images = True
        self.exclude_dept15 = True
        self.exclude_pdfs = True
        self.exclude_tourisme_national = True
        self.exclude_cartes = True
        self.exclude_geospatial = True
        self.filter_year = None
        
        # Créer le dossier de téléchargement
        self.downloads_dir.mkdir(exist_ok=True)
        
        # Config et session
        self.config = None
        self.session = requests.Session() if HAS_DOWNLOAD else None
    
    def setup_config(self):
        """Configuration depuis config.yml"""
        if not HAS_DOWNLOAD:
            return False
            
        config_file = self.base_dir / "config.yml"
        
        if not config_file.exists():
            # Créer template
            template = {
                'LOGIN': 'your_login@example.com',
                'PASSWD': 'your_password',
                'API_URL': 'https://download.flux-vision.orange-business.com/api_file/v1',
                'ORGA_CODE_REF': 'YOUR_ORGA',
                'STUDY_CODE_REF': 'YOUR_STUDY',
                'DELIVERY_CODE_REF': 'YOUR_DELIVERY'
            }
            
            with open(config_file, 'w') as f:
                yaml.dump(template, f)
            
            logging.info(f"📝 Config créée: {config_file}")
            logging.info("⚠️  Modifiez avec vos identifiants!")
            return False
        
        # Charger config
        with open(config_file, 'r') as f:
            self.config = yaml.safe_load(f)
        
        # Vérifier
        if 'your_login' in self.config.get('LOGIN', ''):
            logging.error("❌ Configurez vos identifiants dans config.yml")
            return False
        
        # Auth
        self.session.auth = HTTPBasicAuth(self.config['LOGIN'], self.config['PASSWD'])
        
        logging.info("✅ Config OK")
        return True
    
    def should_download_file(self, delivery):
        """Détermine si un fichier doit être téléchargé selon les filtres"""
        if not self.filters_enabled:
            return True, "OK"
            
        file_name = delivery.get('file_name', '')
        file_name_lower = file_name.lower()
        
        # Filtre par année (plus spécifique)
        if self.filter_year:
            file_date = delivery.get('file_last_modified_date', '')
            if file_date:
                file_year = file_date[:4] if len(file_date) >= 4 else 'unknown'
            else:
                file_year = extract_year(file_name)
            
            if file_year != 'unknown' and file_year != self.filter_year:
                return False, f"Exclu: Année {file_year} (filtre: {self.filter_year})"
        
        # Filtres d'exclusion
        if self.exclude_images and file_name_lower.endswith(('.png', '.jpg', '.jpeg', '.gif', '.bmp', '.svg')):
            return False, "Exclu: Image"
        
        if self.exclude_dept15 and file_name.startswith('Dept_15'):
            return False, "Exclu: Dept_15"
        
        if self.exclude_pdfs and file_name_lower.endswith('.pdf'):
            return False, "Exclu: PDF"
        
        if self.exclude_tourisme_national and file_name.startswith('TourismeNational_'):
            return False, "Exclu: TourismeNational_"
        
        if self.exclude_cartes and ('carte' in file_name_lower or 'map' in file_name_lower):
            return False, "Exclu: Carte"
        
        if self.exclude_geospatial and ('geojson' in file_name_lower or 'kml' in file_name_lower):
            return False, "Exclu: Géospatial"
        
        return True, "OK"
    
    def get_deliveries(self):
        """Récupérer livraisons depuis l'API FluxVision"""
        if not HAS_DOWNLOAD or not self.config:
            return []
            
        logging.info("🔍 Recherche livraisons...")
        
        url = f"{self.config['API_URL']}/{self.config['ORGA_CODE_REF']}/"
        
        try:
            logging.info(f"🌐 Appel API: {url}")
            response = self.session.get(url, timeout=30)
            response.raise_for_status()
            
            all_deliveries = response.json()
            logging.info(f"✅ {len(all_deliveries)} livraison(s) trouvée(s)")
            
            # Filtrer par STUDY_CODE_REF si spécifié
            if 'STUDY_CODE_REF' in self.config and self.config['STUDY_CODE_REF']:
                study_filter = self.config['STUDY_CODE_REF']
                all_deliveries = [d for d in all_deliveries if d.get('study_code_ref') == study_filter]
                logging.info(f"📋 {len(all_deliveries)} livraison(s) filtrée(s) pour {study_filter}")
            
            # Appliquer les filtres
            deliveries = []
            excluded_stats = {'Image': 0, 'Dept_15': 0, 'PDF': 0, 'TourismeNational_': 0, 'Carte': 0, 'Géospatial': 0, 'Année': 0}
            
            for delivery in all_deliveries:
                should_download, reason = self.should_download_file(delivery)
                if should_download:
                    deliveries.append(delivery)
                else:
                    # Compter les exclusions par type
                    if 'Image' in reason:
                        excluded_stats['Image'] += 1
                    elif 'Dept_15' in reason:
                        excluded_stats['Dept_15'] += 1
                    elif 'PDF' in reason:
                        excluded_stats['PDF'] += 1
                    elif 'TourismeNational_' in reason:
                        excluded_stats['TourismeNational_'] += 1
                    elif 'Carte' in reason:
                        excluded_stats['Carte'] += 1
                    elif 'Géospatial' in reason:
                        excluded_stats['Géospatial'] += 1
                    elif 'Année' in reason:
                        excluded_stats['Année'] += 1
            
            total_excluded = sum(excluded_stats.values())
            if total_excluded > 0:
                logging.info(f"🚫 {total_excluded} fichier(s) exclus par les filtres:")
                for filter_type, count in excluded_stats.items():
                    if count > 0:
                        logging.info(f"  ❌ {filter_type}: {count} fichier(s)")
            
            logging.info(f"✅ {len(deliveries)} livraison(s) à télécharger")
            
            # Afficher quelques exemples
            if deliveries:
                logging.info(f"\nExemples de livraisons à télécharger:")
                for i, d in enumerate(deliveries[:5], 1):
                    study = d.get('study_code_ref', 'N/A')
                    delivery_code = d.get('delivery_code_ref', 'N/A')
                    name = d.get('file_name', 'N/A')
                    date = d.get('file_last_modified_date', 'N/A')[:10]  # Juste la date
                    logging.info(f"  {i}. {delivery_code}: {name} ({date})")
                if len(deliveries) > 5:
                    logging.info(f"  ... et {len(deliveries) - 5} autres")
            
            return deliveries
            
        except Exception as e:
            logging.error(f"❌ Erreur API: {e}")
            return []
    
    def download(self, delivery):
        """Télécharger un fichier"""
        if not HAS_DOWNLOAD:
            return None
            
        file_name = delivery.get('file_name', 'data.zip')
        url = delivery.get('url')
        
        if not url:
            logging.error(f"❌ Pas d'URL pour {file_name}")
            return None
        
        logging.info(f"📥 Téléchargement {file_name}...")
        
        try:
            response = self.session.get(url, stream=True, timeout=60)
            response.raise_for_status()
            
            file_path = self.downloads_dir / file_name
            
            # Téléchargement avec progress
            total_size = int(response.headers.get('content-length', 0))
            downloaded = 0
            
            with open(file_path, 'wb') as f:
                for chunk in response.iter_content(chunk_size=8192):
                    if chunk:
                        f.write(chunk)
                        downloaded += len(chunk)
                        if total_size > 0:
                            percent = (downloaded / total_size) * 100
                            print(f"\r📥 {percent:.1f}% ({downloaded:,} / {total_size:,} bytes)", end="")
            
            print(f"\n✅ Téléchargé: {file_path}")
            return str(file_path)
            
        except Exception as e:
            logging.error(f"❌ Erreur téléchargement: {e}")
            return None
    
    def choose_deliveries(self, deliveries):
        """Interface de choix des livraisons à télécharger"""
        if len(deliveries) == 1:
            delivery = deliveries[0]
            logging.info(f"📦 Livraison unique sélectionnée: {delivery.get('delivery_code_ref')}")
            return [delivery]
        
        # Afficher les livraisons disponibles
        logging.info(f"\nLivraisons disponibles:")
        for i, d in enumerate(deliveries, 1):
            study = d.get('study_code_ref', 'N/A')
            delivery_code = d.get('delivery_code_ref', 'N/A')
            name = d.get('file_name', 'N/A')
            date = d.get('file_last_modified_date', 'N/A')
            logging.info(f"{i}. {study} > {delivery_code}")
            logging.info(f"   📄 {name}")
            logging.info(f"   📅 {date}")
        
        try:
            print(f"\nChoisissez une livraison (1-{len(deliveries)}):")
            print("💡 Tapez 0 pour télécharger TOUTES les livraisons")
            print("💡 Tapez -10 pour les 10 plus récentes")
            print("💡 Tapez -20 pour les 20 plus récentes")
            choice = int(input("Votre choix: "))
            
            if choice == 0:
                # Télécharger toutes
                logging.info(f"📦 Téléchargement de TOUTES les {len(deliveries)} livraisons!")
                return deliveries
            elif choice < 0:
                # Télécharger les N plus récentes
                n = abs(choice)
                # Trier par date (plus récentes en premier)
                sorted_deliveries = sorted(deliveries, 
                                         key=lambda x: x.get('file_last_modified_date', ''), 
                                         reverse=True)
                selected = sorted_deliveries[:n]
                logging.info(f"📦 Téléchargement des {len(selected)} livraisons les plus récentes!")
                return selected
            elif 1 <= choice <= len(deliveries):
                delivery = deliveries[choice - 1]
                logging.info(f"📦 Livraison sélectionnée: {delivery.get('delivery_code_ref')}")
                return [delivery]
            else:
                logging.error("❌ Choix invalide")
                return []
        except (ValueError, EOFError):
            logging.error("❌ Choix invalide")
            return []
    
    def download_selected(self, deliveries_to_download):
        """Télécharger les livraisons sélectionnées"""
        if not deliveries_to_download:
            return []
        
        logging.info(f"🚀 Début du téléchargement de {len(deliveries_to_download)} livraison(s)...")
        
        downloaded_files = []
        failed_downloads = []
        
        for i, delivery in enumerate(deliveries_to_download, 1):
            logging.info(f"\n--- Livraison {i}/{len(deliveries_to_download)} ---")
            logging.info(f"📦 {delivery.get('delivery_code_ref')} - {delivery.get('file_name')}")
            
            file_path = self.download(delivery)
            if file_path:
                downloaded_files.append(file_path)
            else:
                failed_downloads.append(delivery.get('file_name', 'Fichier inconnu'))
        
        # Résumé
        logging.info(f"\n{'='*50}")
        logging.info("🎉 TÉLÉCHARGEMENT TERMINÉ")
        logging.info(f"{'='*50}")
        logging.info(f"✅ Fichiers téléchargés: {len(downloaded_files)}")
        logging.info(f"❌ Échecs téléchargement: {len(failed_downloads)}")
        
        if failed_downloads:
            logging.info(f"\n⚠️  Échecs téléchargement:")
            for failed in failed_downloads:
                logging.info(f"  ❌ {failed}")
        
        return downloaded_files
    
    def download_all(self):
        """Télécharger toutes les livraisons filtrées (mode automatique)"""
        if not HAS_DOWNLOAD:
            logging.error("❌ Fonctionnalité téléchargement non disponible")
            return []
            
        if not self.setup_config():
            return []
        
        deliveries = self.get_deliveries()
        if not deliveries:
            logging.info("❌ Aucune livraison trouvée")
            return []
        
        return self.download_selected(deliveries)
    
    def download_interactive(self):
        """Téléchargement interactif avec choix"""
        if not HAS_DOWNLOAD:
            logging.error("❌ Fonctionnalité téléchargement non disponible")
            return []
            
        if not self.setup_config():
            return []
        
        deliveries = self.get_deliveries()
        if not deliveries:
            logging.info("❌ Aucune livraison trouvée")
            return []
        
        selected_deliveries = self.choose_deliveries(deliveries)
        return self.download_selected(selected_deliveries)
    
    def show_download_status(self):
        """Afficher le statut des téléchargements"""
        logging.info("📊 STATUT DES TÉLÉCHARGEMENTS FluxVision")
        logging.info("=" * 40)
        
        # Vérifier les fichiers dans downloads
        if self.downloads_dir.exists():
            zip_files = list(self.downloads_dir.glob("*.zip"))
            if zip_files:
                logging.info(f"📦 Fichiers ZIP présents: {len(zip_files)}")
                for zip_file in zip_files:
                    size_mb = zip_file.stat().st_size / (1024 * 1024)
                    logging.info(f"  📄 {zip_file.name} ({size_mb:.1f} MB)")
            else:
                logging.info("📦 Aucun fichier ZIP présent")
        else:
            logging.info("📁 Dossier downloads non trouvé")
        
        # Vérifier la config
        config_file = self.base_dir / "config.yml"
        if config_file.exists():
            logging.info(f"⚙️  Configuration: {config_file}")
            if HAS_DOWNLOAD:
                logging.info("✅ Dépendances téléchargement OK")
            else:
                logging.info("❌ Dépendances téléchargement manquantes")
        else:
            logging.info("⚙️  Configuration: Non configurée")
        
        # Filtres actifs
        if self.filters_enabled:
            active_filters = []
            if self.exclude_images:
                active_filters.append("Images")
            if self.exclude_dept15:
                active_filters.append("Dept_15")
            if self.exclude_pdfs:
                active_filters.append("PDF")
            if self.exclude_tourisme_national:
                active_filters.append("TourismeNational_")
            if self.exclude_cartes:
                active_filters.append("Cartes")
            if self.exclude_geospatial:
                active_filters.append("Géospatial")
            if self.filter_year:
                active_filters.append(f"Année {self.filter_year}")
            
            if active_filters:
                logging.info(f"🚫 Filtres actifs: {', '.join(active_filters)}")
        else:
            logging.info("⚠️  Tous les filtres sont désactivés")
        
        logging.info("")

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
        return csv.Sniffer().sniff(sample).delimiter
    except Exception:
        return ',' # Default delimiter

def read_polars_csv_safely(path, expected_cols=None):
    delim = detect_delimiter(path)
    try:
        # Polars est généralement bon pour inférer les types.
        # infer_schema_length=0 pour lire tout le fichier pour l'inférence si nécessaire (plus lent)
        # ou un nombre plus élevé de lignes.
        # ignore_errors=True peut skipper les lignes malformées silencieusement
        df = pl.read_csv(path, separator=delim, infer_schema_length=1000, try_parse_dates=True)
    except Exception as e:
        logging.warning(f"Polars direct read_csv failed for {path} with delimiter '{delim}': {e}. Retrying with ignore_errors=True.")
        try:
            df = pl.read_csv(path, separator=delim, infer_schema_length=1000, ignore_errors=True, try_parse_dates=True)
        except Exception as read_err:
            logging.error(f"Critical error reading {path} with Polars: {read_err}")
            raise
            
    if expected_cols is not None and df.columns != expected_cols: # Polars columns est une liste
        raise ValueError(f"Unexpected columns in {path}: {df.columns} vs {expected_cols}")
    return df

# ----------------- Fusion intelligente (CSV -> CSV) -----------------
def merge_to_csv(csv_paths, out_csv_path, incremental=False):
    """
    Fusionne plusieurs CSV en mémoire avec union des colonnes,
    contrôle de contenu, et en mode incrémental si demandé.
    Écrit le résultat au format CSV.
    """
    if not csv_paths:
        logging.warning(f"Aucun fichier CSV à fusionner pour {out_csv_path}.")
        return

    dfs = []
    # En mode incrémental, charger l'existant (CSV) pour le réintégrer
    if incremental and os.path.exists(out_csv_path):
        try:
            old_df = read_polars_csv_safely(out_csv_path)
            dfs.append(old_df)
            logging.info(f"Chargé existant {out_csv_path} ({len(old_df)} lignes, {len(old_df.columns)} colonnes)")
        except Exception as e:
            logging.error(f"Impossible de charger existant {out_csv_path}: {e}")

    # Lecture parallèle des nouveaux CSV
    # Utiliser os.cpu_count() peut être excessif si les fichiers sont très petits et nombreux
    # ou si la machine a beaucoup de coeurs mais est limitée en I/O.
    # Un nombre fixe (ex: 4-8) ou os.cpu_count() // 2 peut être plus stable.
    max_workers = min(os.cpu_count() or 1, 8) # Limite pour éviter la surcharge
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

    # Concaténation avec Polars
    # `how="diagonal"` (ou "vertical_relaxed" selon Polars >= 0.17.0) permet l'union des colonnes
    try:
        merged = pl.concat(dfs, how="diagonal_relaxed") # diagonal_relaxed est plus récent et flexible
    except TypeError: # Pour des versions plus anciennes de Polars
        merged = pl.concat(dfs, how="diagonal")


    # Vérification des types sur colonnes communes (optionnel avec Polars, mais peut être utile)
    # Polars gère bien les types mixtes lors de la concaténation (souvent en castant vers pl.Object ou pl.String)
    # Cette vérification est moins critique qu'avec Pandas mais peut aider à débusquer des incohérences.
    if len(dfs) > 1:
        all_cols = set()
        for df_item in dfs:
            all_cols.update(df_item.columns)
        
        for col_name in all_cols:
            dtypes_in_col = set()
            for df_item in dfs:
                if col_name in df_item.columns:
                    dtypes_in_col.add(str(df_item[col_name].dtype)) # str() pour la comparabilité
            if len(dtypes_in_col) > 1:
                 logging.warning(f"Incohérence de type détectée pour la colonne '{col_name}' avant concaténation: {dtypes_in_col}. Polars tentera de les unifier.")


    before = len(merged)
    merged = merged.unique(keep="first", maintain_order=True) # maintain_order=True est important ici
    after = len(merged)
    logging.info(f"Fusion: {before} → {after} lignes après suppression des doublons.")

    # Écriture du résultat en CSV
    try:
        merged.write_csv(out_csv_path, separator=',')
        logging.info(f"Fusion enregistrée (CSV) dans {out_csv_path}")
    except Exception as e:
        logging.error(f"Erreur lors de l'écriture CSV vers {out_csv_path}: {e}")


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

# ----------------- Traitement incrémental avec parallélisme -----------------
def process_data(src_root, dst_root, changed_dirs, full_rebuild=False):
    if os.path.exists(dst_root):
        if full_rebuild:
            shutil.rmtree(dst_root)
            os.makedirs(dst_root, exist_ok=True)
        # else: # La copie temporaire n'est pas strictement nécessaire avec CSV si on écrase
              # mais peut être une sauvegarde si on veut être ultra prudent.
              # Pour la simplicité, on va juste s'assurer que les répertoires existent.
    os.makedirs(dst_root, exist_ok=True)


    carte_root = os.path.join(dst_root, 'carte') # Ceux-ci ne sont pas des CSV
    merged_dir = os.path.join(dst_root, 'data_merged_csv') # Contiendra des CSV
    os.makedirs(carte_root, exist_ok=True)
    os.makedirs(merged_dir, exist_ok=True)

    items = os.listdir(src_root) if full_rebuild else changed_dirs
    tasks = [] # (list_of_csv_paths, output_csv_path, incremental_bool)

    for d in items:
        path = os.path.join(src_root, d)
        if not os.path.isdir(path):
            continue

        # Cartes : liens annuels (pas de changement de format ici, ce sont des liens)
        if d.startswith('Cartes'):
            year = extract_year(d)
            tmp_carte_year_dir = os.path.join(carte_root, year + '_tmp') # dir temporaire
            shutil.rmtree(tmp_carte_year_dir, ignore_errors=True)
            os.makedirs(tmp_carte_year_dir, exist_ok=True)
            
            for dirpath, _, files in os.walk(path):
                rel = os.path.relpath(dirpath, path)
                # base est le répertoire cible dans tmp_carte_year_dir
                base = tmp_carte_year_dir if rel == '.' else os.path.join(tmp_carte_year_dir, rel)
                os.makedirs(base, exist_ok=True)
                for f in files:
                    src_file = os.path.join(dirpath, f)
                    dst_file = os.path.join(base, f)
                    try:
                        # Tenter un hard link d'abord (plus efficace)
                        os.link(src_file, dst_file)
                    except OSError: # Peut échouer cross-device ou si non supporté
                        try:
                            os.symlink(src_file, dst_file) # Tenter un symlink
                        except OSError as e_sym: # Peut échouer si pas les permissions (Windows)
                            logging.warning(f"Could not link/symlink {src_file} to {dst_file}, copying instead. Error: {e_sym}")
                            shutil.copy2(src_file, dst_file) # Fallback vers copie
            
            final_carte_year_dir = os.path.join(carte_root, year)
            if os.path.exists(final_carte_year_dir): # S'assurer que la destination est vide
                shutil.rmtree(final_carte_year_dir)
            os.rename(tmp_carte_year_dir, final_carte_year_dir) # Renommer atomiquement (ou presque)
            logging.info(f"Processed Cartes for year {year}, linked to {final_carte_year_dir}")
            continue

        # Tourisme : fusion unique -> TourismeNational.csv
        if 'Tourisme' in d:
            all_csv_paths = [os.path.join(r, f)
                             for r, _, files in os.walk(path)
                             for f in files if f.lower().endswith('.csv')]
            # Le fichier de sortie sera dans dst_root, pas merged_dir pour TourismeNational
            national_csv = os.path.join(dst_root, 'TourismeNational.csv')
            tasks.append((all_csv_paths, national_csv, not full_rebuild))
            continue

        # Traitement de nos données FluxVision : CSV directement dans les dossiers territoires
        file_map = {} # clef: nom_fichier_sortie.csv, valeur: [chemins_csv_entree]
        
        # Parcourir directement les fichiers CSV dans le dossier territoire
        for f in os.scandir(path):
            if f.is_file() and f.name.lower().endswith('.csv'):
                parts = f.name.split('_')
                # Rechercher l'index du bimestre (B6, B5, etc.)
                idx = next((i for i, p in enumerate(parts) if 'B' in p and any(c.isdigit() for c in p)), None)
                # Le nom du fichier de sortie sera .csv
                clean_name_base = '_'.join(parts[:idx]) if idx is not None else os.path.splitext(f.name)[0]
                out_csv_fname = clean_name_base + '.csv'
                file_map.setdefault(out_csv_fname, []).append(f.path)
        
        # Créer les tâches de fusion pour ce territoire
        for csv_fname, csv_paths_list in file_map.items():
            out_csv_file = os.path.join(merged_dir, csv_fname)
            tasks.append((csv_paths_list, out_csv_file, not full_rebuild))
            
        logging.info(f"Traitement territoire {d}: {len(file_map)} types de fichiers trouvés")

    # Exécution des fusions en parallèle
    # max_workers = min(os.cpu_count() or 1, 4) # Limiter pour les tâches de fusion qui peuvent être intensives
    max_workers = min(os.cpu_count() or 1, 8)
    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        futures = [executor.submit(merge_to_csv, paths, out_path, inc)
                   for paths, out_path, inc in tasks]
        for future in as_completed(futures):
            try:
                future.result() # Récupère le résultat ou lève l'exception
            except Exception as e:
                logging.error(f"Erreur lors de la fusion parallèle (Polars/CSV): {e}", exc_info=True)


# ----------------- Main -----------------
if __name__ == '__main__':
    # Gestion des arguments de ligne de commande
    download_first = False
    download_only = False
    interactive_download = False
    status_only = False
    
    # Initialiser le downloader pour la configuration des filtres
    downloader = FluxVisionDownloader() if HAS_DOWNLOAD else None
    
    # Traiter TOUS les arguments
    for arg in sys.argv[1:]:
        # Modes de téléchargement
        if arg in ['--download', '-d']:
            download_first = True
            print("🚀 Mode: Téléchargement puis traitement")
        elif arg in ['--download-only']:
            download_only = True
            print("🚀 Mode: Téléchargement uniquement")
        elif arg in ['--interactive', '-i']:
            interactive_download = True
            print("🚀 Mode: Téléchargement interactif puis traitement")
        elif arg in ['--status', '-s']:
            status_only = True
            print("🚀 Mode: Affichage du statut")
        
        # Contrôle des filtres
        elif arg in ['--no-filters', '--all']:
            if downloader:
                downloader.filters_enabled = False
                print("⚠️  Tous les filtres désactivés - téléchargement de TOUT")
        elif arg in ['--with-images']:
            if downloader:
                downloader.exclude_images = False
                print("📷 Images incluses dans le téléchargement")
        elif arg in ['--with-dept15']:
            if downloader:
                downloader.exclude_dept15 = False
                print("📁 Fichiers Dept_15 inclus dans le téléchargement")
        elif arg in ['--with-pdfs']:
            if downloader:
                downloader.exclude_pdfs = False
                print("📄 PDF inclus dans le téléchargement")
        elif arg in ['--with-tourisme-national']:
            if downloader:
                downloader.exclude_tourisme_national = False
                print("📊 Fichiers TourismeNational_ inclus dans le téléchargement")
        elif arg in ['--with-cartes']:
            if downloader:
                downloader.exclude_cartes = False
                print("🗺️  Cartes incluses dans le téléchargement")
        elif arg in ['--with-geospatial']:
            if downloader:
                downloader.exclude_geospatial = False
                print("🗺️  Fichiers géospatiaux inclus dans le téléchargement")
        elif arg.startswith('--year='):
            year = arg.split('=')[1]
            if year.isdigit() and len(year) == 4 and year.startswith('20'):
                if downloader:
                    downloader.filter_year = year
                    print(f"📅 Filtre par année: {year}")
            else:
                print(f"❌ Année invalide: {year}. Format attendu: --year=2024")
                sys.exit(1)
        elif arg in ['--help', '-h']:
            print("""
🚀 FluxVision Downloader & Processor - Aide
==========================================

UTILISATION:
  python downloader.py [OPTIONS]

MODES DE TÉLÉCHARGEMENT:
  --download, -d         Télécharger d'abord, puis traiter
  --download-only        Télécharger uniquement (pas de traitement)
  --interactive, -i      Téléchargement interactif avec choix
  --status, -s           Afficher le statut des téléchargements

CONTRÔLE DES FILTRES:
  --no-filters, --all    Désactiver tous les filtres (télécharge tout)
  --with-images          Inclure les images (PNG, JPG, etc.)
  --with-dept15          Inclure les fichiers Dept_15
  --with-pdfs            Inclure les fichiers PDF
  --with-tourisme-national Inclure les fichiers TourismeNational_
  --with-cartes          Inclure les fichiers cartes
  --with-geospatial      Inclure les fichiers géospatiaux (KML/GeoJSON)
  --year=YYYY            Filtrer par année spécifique (ex: --year=2024)

FILTRES PAR DÉFAUT (RECOMMANDÉS):
  ❌ Images exclues       (PNG, JPG, GIF, etc.)
  ❌ Dept_15 exclus       (fichiers commençant par "Dept_15")
  ❌ PDF exclus           (fichiers PDF)
  ❌ TourismeNational_ exclus (fichiers commençant par "TourismeNational_")
  ❌ Cartes exclues       (fichiers cartes)
  ❌ Géospatial exclus    (fichiers KML/GeoJSON)

EXEMPLES:
  python downloader.py                    # Traitement normal des ZIP existants
  python downloader.py --download         # Télécharger puis traiter
  python downloader.py --interactive      # Téléchargement interactif
  python downloader.py --download-only    # Télécharger uniquement
  python downloader.py --status           # Voir le statut
  python downloader.py --no-filters       # Télécharger TOUT (attention!)
  python downloader.py --with-images      # Inclure les images
  python downloader.py --year=2024        # Seulement les fichiers 2024

MODE PAR DÉFAUT:
  Traite les fichiers ZIP déjà présents dans le dossier 'downloads'

CONFIGURATION:
  Le fichier config.yml sera créé automatiquement s'il n'existe pas.
  Modifiez-le avec vos identifiants FluxVision pour le téléchargement.

PENDANT LE TÉLÉCHARGEMENT INTERACTIF:
  0      Télécharger TOUTES les livraisons
  -10    Télécharger les 10 plus récentes
  -20    Télécharger les 20 plus récentes
  1-N    Télécharger une livraison spécifique
            """)
            sys.exit(0)
        else:
            print(f"❌ Option inconnue: {arg}")
            print("Utilisez --help pour voir toutes les options disponibles")
            sys.exit(1)
    
    # Mode statut uniquement
    if status_only:
        if downloader:
            downloader.show_download_status()
        else:
            print("❌ Fonctionnalité téléchargement non disponible")
        sys.exit(0)
    
    # Mode téléchargement uniquement
    if download_only:
        if HAS_DOWNLOAD and downloader:
            print("🚀 Mode: Téléchargement uniquement")
            downloaded_files = downloader.download_all()
            if downloaded_files:
                print(f"✅ {len(downloaded_files)} fichier(s) téléchargés dans {DATA_ZIP_DIR}")
            else:
                print("❌ Aucun fichier téléchargé")
        else:
            print("❌ Fonctionnalité téléchargement non disponible")
        sys.exit(0)
    
    lock = acquire_lock()
    try:
        # Phase 1: Téléchargement si demandé
        if download_first or interactive_download:
            mode_name = "interactif" if interactive_download else "automatique"
            print(f"📥 Phase 1: Téléchargement {mode_name}")
            print("=" * 30)
            if HAS_DOWNLOAD and downloader:
                if interactive_download:
                    downloaded_files = downloader.download_interactive()
                else:
                    downloaded_files = downloader.download_all()
                
                if downloaded_files:
                    print(f"✅ {len(downloaded_files)} fichier(s) téléchargés")
                else:
                    print("⚠️  Aucun nouveau fichier téléchargé")
            else:
                print("❌ Fonctionnalité téléchargement non disponible")
            
            print("\n📂 Phase 2: Traitement")
            print("=" * 30)
        
        state = load_state()
        old_hashes = state.get('hashes', {})
        history    = state.get('history', {})

        # L'extraction produit toujours des CSV et autres fichiers bruts
        new_hashes, changed_roots = extract_recursive('downloads', TMP_EXTRACTED, old_hashes)
        
        # Remplacer l'ancien répertoire extrait par le nouveau temporaire
        if os.path.exists(DATA_EXTRACTED):
            shutil.rmtree(DATA_EXTRACTED)
        os.rename(TMP_EXTRACTED, DATA_EXTRACTED)
        logging.info('Extraction completed')

        removed_archives = set(old_hashes) - set(new_hashes)
        full_rebuild = bool(removed_archives)
        if removed_archives:
            logging.info(f"Deleted archives detected, full rebuild triggered: {removed_archives}")

        # Process_data va lire depuis DATA_EXTRACTED (CSV, etc.) et écrire dans TMP_CLEAN (CSV, liens)
        process_data(DATA_EXTRACTED, TMP_CLEAN, changed_roots, full_rebuild=full_rebuild)
        
        # Remplacer l'ancien répertoire clean par le nouveau temporaire
        if os.path.exists(DATA_CLEAN):
            shutil.rmtree(DATA_CLEAN)
        os.rename(TMP_CLEAN, DATA_CLEAN)
        logging.info('Processing completed. Clean data in CSV format is in %s', DATA_CLEAN)

        # Supprimer le répertoire des données extraites pour économiser l'espace
        if os.path.exists(DATA_EXTRACTED):
            shutil.rmtree(DATA_EXTRACTED)
            logging.info('Cleaned up extracted data directory: %s', DATA_EXTRACTED)

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