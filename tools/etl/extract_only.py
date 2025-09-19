#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script de décompression simple - CantalDestination
Extrait uniquement les archives ZIP vers data_extracted
S'arrête à l'étape data_extracted sans traitement supplémentaire
"""

import os
import zipfile
import shutil
import logging
import hashlib
import json
import sys
import argparse
from pathlib import Path
from datetime import datetime, timezone

# Répertoire racine du projet (où se trouve ce script)
BASE_DIR = Path(__file__).resolve().parent
FV_AUT_DIR = BASE_DIR / 'fluxvision_automation'

# Configuration par défaut (répertoires relatifs à fluxvision_automation)
DEFAULT_SRC_DIR = 'data/data_zip'
DEFAULT_DST_DIR = 'data/data_extracted'

# Fichiers d'état/verrou
DATA_EXTRACTED = str(BASE_DIR / DEFAULT_DST_DIR)
STATE_FILE = str(FV_AUT_DIR / '.extract_state.json')
LOCK_FILE = str(FV_AUT_DIR / '.extract.lock')
LEGACY_STATE_FILE = str(BASE_DIR / 'data' / '.extract_state.json')

# Configuration du logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(str(BASE_DIR / 'extract_only.log'), encoding='utf-8'),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

def cleanup_temp_dirs(destination_dir):
    """Nettoie les dossiers temporaires"""
    temp_dirs = [destination_dir + '_tmp']
    for dir_path in temp_dirs:
        if os.path.exists(dir_path):
            try:
                shutil.rmtree(dir_path)
                logger.info(f"Dossier temporaire supprimé: {dir_path}")
            except Exception as e:
                logger.error(f"Erreur lors de la suppression du dossier temporaire {dir_path}: {e}")

def acquire_lock():
    """Acquiert un verrou pour éviter les exécutions simultanées"""
    os.makedirs(os.path.dirname(LOCK_FILE), exist_ok=True)
    
    try:
        lock = open(LOCK_FILE, 'w+')
        if os.name == 'nt':  # Windows
            import msvcrt
            msvcrt.locking(lock.fileno(), msvcrt.LK_NBLCK, 1)
        else:  # Linux/Mac
            import fcntl
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        
        # Écrire un timestamp dans le fichier de verrouillage
        lock.write(f"Locked at: {datetime.now(timezone.utc).isoformat()}\n")
        lock.flush()
        return lock
    except Exception as e:
        logger.error(f'Erreur lors de l\'acquisition du verrou: {e}')
        sys.exit(1)

def release_lock(lock):
    """Libère le verrou"""
    try:
        if os.name == 'nt':  # Windows
            import msvcrt
            try:
                msvcrt.locking(lock.fileno(), msvcrt.LK_UNLCK, 1)
            except OSError as e:
                logger.warning(f"Problème lors de la libération du verrou (Windows): {e}")
        else:  # Linux/Mac
            import fcntl
            try:
                fcntl.flock(lock, fcntl.LOCK_UN)
            except OSError as e:
                logger.warning(f"Problème lors de la libération du verrou (Unix): {e}")
    except Exception as e:
        logger.warning(f"Problème inattendu lors de la libération du verrou: {e}")
    finally:
        try:
            lock.close()
            if os.path.exists(LOCK_FILE):
                os.remove(LOCK_FILE)
                logger.info("Fichier de verrouillage supprimé")
        except Exception as e:
            logger.warning(f"Problème lors de la suppression du fichier de verrouillage: {e}")

def load_state():
    """Charge l'état de traitement (avec migration automatique depuis l'ancien emplacement)."""
    # Nouvel emplacement (fluxvision_automation)
    if os.path.exists(STATE_FILE):
        with open(STATE_FILE, 'r', encoding='utf-8') as f:
            return json.load(f)
    # Ancien emplacement (data/.extract_state.json)
    if os.path.exists(LEGACY_STATE_FILE):
        try:
            with open(LEGACY_STATE_FILE, 'r', encoding='utf-8') as f:
                data = json.load(f)
            os.makedirs(os.path.dirname(STATE_FILE), exist_ok=True)
            with open(STATE_FILE, 'w', encoding='utf-8') as f:
                json.dump(data, f, indent=2)
            try:
                os.remove(LEGACY_STATE_FILE)
            except Exception:
                pass
            logger.info("Fichier d'état migré vers fluxvision_automation/.extract_state.json")
            return data
        except Exception as e:
            logger.warning(f"Impossible de migrer l'état depuis l'ancien emplacement: {e}")
    return {'hashes': {}, 'history': {}}

def save_state(state):
    """Sauvegarde l'état de traitement"""
    os.makedirs(os.path.dirname(STATE_FILE), exist_ok=True)
    with open(STATE_FILE, 'w', encoding='utf-8') as f:
        json.dump(state, f, indent=2)

def file_hash(path):
    """Calcule le hash SHA256 d'un fichier"""
    h = hashlib.sha256()
    with open(path, 'rb') as f:
        for chunk in iter(lambda: f.read(8192), b''):
            h.update(chunk)
    return h.hexdigest()

def extract_recursive(src_dir, dst_dir, old_hashes):
    """
    Extrait récursivement tous les fichiers ZIP
    S'arrête à l'extraction, pas de traitement supplémentaire
    """
    if os.path.exists(dst_dir):
        shutil.rmtree(dst_dir)
    os.makedirs(dst_dir, exist_ok=True)

    new_hashes = {}
    to_process = []
    changed_roots = []

    # Recherche récursive des fichiers ZIP
    logger.info(f"Recherche des archives ZIP dans {src_dir}")
    for root, _, files in os.walk(src_dir):
        for fname in files:
            if not fname.lower().endswith('.zip'):
                continue
            path = os.path.join(root, fname)
            h = file_hash(path)
            rel_path = os.path.relpath(path, src_dir)
            new_hashes[rel_path] = h
            if old_hashes.get(rel_path) == h:
                logger.info(f"Archive inchangée: {fname}")
                continue
            stem = os.path.splitext(fname)[0]
            changed_roots.append(stem)
            to_process.append((path, os.path.join(dst_dir, stem)))

    if not to_process:
        logger.info(f"Aucune nouvelle archive ZIP trouvée dans {src_dir}")
        return new_hashes, changed_roots

    logger.info(f"Archives à traiter: {len(to_process)}")
    
    # Extraction des archives
    abs_dst = os.path.abspath(dst_dir)
    while to_process:
        zip_path, extract_folder = to_process.pop()
        os.makedirs(extract_folder, exist_ok=True)
        base = os.path.basename(zip_path)
        
        try:
            with zipfile.ZipFile(zip_path, 'r') as zf:
                logger.info(f"Extraction de {base} -> {extract_folder}")
                
                extract_folder_abs = os.path.abspath(extract_folder)
                for member in zf.infolist():
                    # Normaliser le chemin pour prévenir les traversals (../) et chemins absolus
                    normalized_name = os.path.normpath(member.filename).lstrip('/\\')
                    target = os.path.join(extract_folder, normalized_name)
                    target_abs = os.path.abspath(target)

                    # Sécurité: ignorer toute entrée qui sortirait du dossier d'extraction
                    if not (target_abs == extract_folder_abs or target_abs.startswith(extract_folder_abs + os.sep)):
                        logger.warning(f"Chemin dangereux ignoré dans l'archive: {member.filename}")
                        continue

                    if member.is_dir():
                        os.makedirs(target_abs, exist_ok=True)
                    elif member.filename.lower().endswith('.zip'):
                        # Archive imbriquée - l'ajouter à la liste de traitement
                        data = zf.read(member)
                        os.makedirs(os.path.dirname(target_abs), exist_ok=True)
                        with open(target_abs, 'wb') as nf:
                            nf.write(data)
                        to_process.append((target_abs, target_abs[:-4]))
                    else:
                        # Fichier normal - l'extraire
                        os.makedirs(os.path.dirname(target_abs), exist_ok=True)
                        with zf.open(member) as src, open(target_abs, 'wb') as dst:
                            shutil.copyfileobj(src, dst)
                            
        except zipfile.BadZipFile:
            logger.error(f"Archive corrompue ignorée: {base}")
        except Exception as e:
            logger.error(f"Erreur lors de l'extraction de {base}: {e}")
        finally:
            # Supprimer l'archive après extraction (si elle est dans le dossier de destination)
            try:
                if os.path.commonpath([os.path.abspath(zip_path), abs_dst]) == abs_dst:
                    os.remove(zip_path)
            except Exception:
                pass

    logger.info("Extraction terminée")
    return new_hashes, changed_roots

def parse_args():
    parser = argparse.ArgumentParser(description='Extracteur ZIP sécurisé (FluxVision)')
    parser.add_argument('--src', '--source', dest='src', default=None, help="Dossier source des archives ZIP (relatif à 'fluxvision_automation' ou absolu dans ce répertoire)")
    parser.add_argument('--dst', '--dest', dest='dst', default=None, help="Dossier de destination de l'extraction (relatif à 'fluxvision_automation' ou absolu dans ce répertoire)")
    return parser.parse_args()

def find_moved_data_zip() -> str:
    """Tente de détecter automatiquement le nouveau chemin de 'data_zip' sous 'fluxvision_automation'.
    Retourne un chemin ABSOLU si trouvé, sinon le fallback par défaut 'fluxvision_automation/data_zip'.
    """
    known_candidates = [
        FV_AUT_DIR / 'data' / 'data_zip',
        FV_AUT_DIR / 'data_zip',
    ]
    for candidate in known_candidates:
        if candidate.exists() and candidate.is_dir():
            return str(candidate.resolve())

    # Recherche générique (profondeur quelconque) du premier dossier nommé 'data_zip'
    try:
        def has_zip_files(directory: Path) -> bool:
            for walk_root, _, files in os.walk(directory):
                for name in files:
                    if name.lower().endswith('.zip'):
                        return True
                break
            return False

        found_candidate = None
        for root, dirs, _ in os.walk(FV_AUT_DIR):
            if 'data_zip' in dirs:
                candidate_path = Path(root) / 'data_zip'
                if has_zip_files(candidate_path):
                    return str(candidate_path.resolve())
                if found_candidate is None:
                    found_candidate = candidate_path
        if found_candidate is not None:
            return str(found_candidate.resolve())
    except Exception:
        pass

    # Fallback par défaut
    return str((FV_AUT_DIR / 'data_zip').resolve())

def main():
    """Fonction principale"""
    print("FluxVision - Extraction d'archives uniquement")
    print("=" * 50)
    print("Ce script ne fait QUE décompresser les archives ZIP")
    print("S'arrête à l'étape data_extracted")
    print("=" * 50)
    
    # Résolution des répertoires source/destination (CLI > ENV > défauts)
    args = parse_args()
    env_src = os.getenv('EXTRACT_SRC')
    env_dst = os.getenv('EXTRACT_DST')

    default_src = DEFAULT_SRC_DIR
    autodetected_src_abs = find_moved_data_zip()
    src_dir = args.src or env_src or autodetected_src_abs or default_src
    dst_dir = args.dst or env_dst or DEFAULT_DST_DIR

    # Convertir en chemins absolus basés sur FV_AUT_DIR si relatifs
    if not os.path.isabs(src_dir):
        src_dir = str((FV_AUT_DIR / src_dir).resolve())
    if not os.path.isabs(dst_dir):
        dst_dir = str((FV_AUT_DIR / dst_dir).resolve())

    # Enforcer: les chemins doivent être sous fluxvision_automation
    fv_aut_abs = str(FV_AUT_DIR.resolve())
    src_abs = os.path.abspath(src_dir)
    dst_abs = os.path.abspath(dst_dir)
    if os.path.commonpath([src_abs, fv_aut_abs]) != fv_aut_abs:
        raise ValueError("Le dossier source doit se trouver sous 'fluxvision_automation'")
    if os.path.commonpath([dst_abs, fv_aut_abs]) != fv_aut_abs:
        raise ValueError("Le dossier destination doit se trouver sous 'fluxvision_automation'")

    print(f"Source: {src_dir}")
    print(f"Destination: {dst_dir}")

    # Nettoyer les dossiers temporaires au démarrage
    cleanup_temp_dirs(dst_dir)
    
    lock = None
    try:
        lock = acquire_lock()
        state = load_state()
        old_hashes = state.get('hashes', {})
        history = state.get('history', {})

        # Vérifier si le dossier source existe
        if not os.path.exists(src_dir):
            os.makedirs(src_dir, exist_ok=True)
            logger.info(f"Dossier {src_dir} créé")
            print(f"📁 Dossier {src_dir} créé - Placez-y vos archives ZIP")

        # Extraction récursive
        new_hashes, changed_roots = extract_recursive(src_dir, dst_dir, old_hashes)
        
        # Mise à jour de l'état
        state['hashes'] = new_hashes
        current_time_utc = datetime.now(timezone.utc).isoformat()
        
        for stem in changed_roots:
            history[stem] = {
                'processed_at': current_time_utc, 
                'status': 'extracted',
                'extracted_to': dst_dir
            }
        
        state['history'] = history
        save_state(state)
        
        # Statistiques finales
        if os.path.exists(dst_dir):
            total_files = sum(len(files) for _, _, files in os.walk(dst_dir))
            logger.info(f"Extraction terminée: {total_files} fichiers extraits dans {dst_dir}")
            print("Extraction terminée!")
            print(f"Fichiers extraits dans: {dst_dir}")
            print(f"Total: {total_files} fichiers")
        else:
            print("Aucun fichier extrait - vérifiez le dossier source spécifié")
        
    except Exception as e:
        logger.error(f"Une erreur est survenue: {e}", exc_info=True)
        print(f"Erreur: {e}")
        # En cas d'erreur, on nettoie les dossiers temporaires
        cleanup_temp_dirs(dst_dir)
    finally:
        if lock:
            release_lock(lock)
        # Nettoyage final des dossiers temporaires
        cleanup_temp_dirs(dst_dir)

if __name__ == '__main__':
    main()
