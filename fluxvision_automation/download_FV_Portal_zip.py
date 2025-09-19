import requests
from requests.auth import HTTPBasicAuth
import yaml
import logging
from datetime import datetime
import os
import sys
import re
from urllib.parse import unquote
from pathlib import Path

# Configuration du logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)

def clean_string(text):
    """
    Nettoie une chaîne de caractères pour éviter les erreurs d'encodage
    """
    if text is None:
        return 'N/A'
    text = str(text)
    try:
        text = text.encode('utf-8', errors='replace').decode('utf-8')
        text = text.replace('\ufffd', ' ')
        return text.strip()
    except Exception:
        return 'N/A'

def download_file(url, auth, output_path):
    """
    Télécharge un fichier depuis une URL avec authentification
    """
    try:
        response = requests.get(url, auth=auth, stream=True)
        response.raise_for_status()
        
        total_size = int(response.headers.get('content-length', 0))
        block_size = 8192
        downloaded = 0
        
        with open(output_path, 'wb') as f:
            for data in response.iter_content(block_size):
                downloaded += len(data)
                f.write(data)
                done = int(50 * downloaded / total_size) if total_size > 0 else 0
                sys.stdout.write(f"\r[{'=' * done}{' ' * (50-done)}] {downloaded}/{total_size} bytes")
                sys.stdout.flush()
        
        print()  # Nouvelle ligne après la barre de progression
        return True
    except Exception as e:
        logging.error(f"Erreur lors du téléchargement de {url}: {e}")
        return False

def download_zip_files(target_year):
    """
    Télécharge tous les fichiers ZIP de l'année spécifiée
    """
    config_file = 'sample_FV_Portal_api_conf.yml'
    
    try:
        # Lecture du fichier de configuration
        with open(config_file) as cf:
            config = yaml.load(cf, Loader=yaml.FullLoader)
        
        login = config['LOGIN']
        passwd = config['PASSWD']
        auth = HTTPBasicAuth(login, passwd)
        
        API_URL = config['API_URL']
        ORGA_CODE_REF = config['ORGA_CODE_REF']
        STUDY_CODE_REF = config['STUDY_CODE_REF']
        
        # Requête à l'API
        logging.info(f"Connexion à l'API : {API_URL}/{ORGA_CODE_REF}/")
        r = requests.get(API_URL + '/' + ORGA_CODE_REF + '/', auth=auth)
        
        if r.status_code != 200:
            logging.error(f"Erreur lors de la requête API : Status code {r.status_code}")
            return False
        
        results = r.json()
        logging.info(f"Nombre de deliveries trouvées : {len(results)}")
        
        # Filtrage pour ne garder que les fichiers .zip de l'étude CANT-150 avec BX année
        zip_results = []
        pattern = r'B\d+\s+' + target_year
        
        for result in results:
            file_name = result.get('file_name', '')
            study_code = result.get('study_code_ref', '')
            delivery_name = result.get('delivery_name', '')
            
            if (file_name and file_name.lower().endswith('.zip') and 
                study_code == 'CANT-150' and
                re.search(pattern, delivery_name, re.IGNORECASE)):
                zip_results.append(result)
        
        if not zip_results:
            logging.error(f"Aucun fichier ZIP trouvé pour l'année {target_year}")
            return False
        
        # Création du dossier de destination
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        download_dir = Path(f"downloads_{target_year}_{timestamp}")
        download_dir.mkdir(exist_ok=True)
        
        # Création du fichier de log
        log_file = download_dir / f"download_log_{timestamp}.txt"
        
        with open(log_file, 'w', encoding='utf-8') as f:
            f.write("=" * 80 + "\n")
            f.write(f"LOG DE TÉLÉCHARGEMENT - FICHIERS ZIP CANT-150 B{target_year}\n")
            f.write("=" * 80 + "\n\n")
            
            f.write(f"Date de début : {datetime.now().strftime('%d/%m/%Y %H:%M:%S')}\n")
            f.write(f"Nombre total de fichiers à télécharger : {len(zip_results)}\n\n")
            
            # Téléchargement des fichiers
            success_count = 0
            error_count = 0
            
            for i, result in enumerate(zip_results, 1):
                file_name = result.get('file_name', '')
                delivery_name = result.get('delivery_name', '')
                url = result.get('url', '')
                
                if not url:
                    logging.error(f"URL manquante pour {file_name}")
                    f.write(f"❌ ERREUR - {file_name} : URL manquante\n")
                    error_count += 1
                    continue
                
                # Création du nom de fichier unique avec le bimestre
                bimester = re.search(r'B\d+', delivery_name).group(0) if re.search(r'B\d+', delivery_name) else 'Unknown'
                safe_file_name = f"{bimester}_{clean_string(file_name)}"
                output_path = download_dir / safe_file_name
                
                f.write(f"\n[{i}/{len(zip_results)}] Téléchargement de {safe_file_name}...\n")
                f.write(f"URL : {url}\n")
                
                if download_file(url, auth, output_path):
                    f.write(f"✅ Succès - {safe_file_name}\n")
                    success_count += 1
                else:
                    f.write(f"❌ Échec - {safe_file_name}\n")
                    error_count += 1
            
            # Résumé final
            f.write("\n" + "=" * 80 + "\n")
            f.write("RÉSUMÉ DU TÉLÉCHARGEMENT\n")
            f.write("=" * 80 + "\n\n")
            f.write(f"Date de fin : {datetime.now().strftime('%d/%m/%Y %H:%M:%S')}\n")
            f.write(f"Total des fichiers : {len(zip_results)}\n")
            f.write(f"Téléchargements réussis : {success_count}\n")
            f.write(f"Échecs : {error_count}\n")
        
        print(f"\n✅ Téléchargement terminé !")
        print(f"📁 Fichiers téléchargés dans : {download_dir}")
        print(f"📝 Log disponible dans : {log_file}")
        print(f"📊 {success_count} fichiers téléchargés avec succès sur {len(zip_results)}")
        
        return True
        
    except FileNotFoundError:
        logging.error(f"Fichier de configuration non trouvé : {config_file}")
        print(f"❌ Erreur : Le fichier {config_file} est introuvable")
        return False
        
    except requests.exceptions.RequestException as e:
        logging.error(f"Erreur de requête : {e}")
        print(f"❌ Erreur de connexion à l'API : {e}")
        return False
        
    except Exception as e:
        logging.error(f"Erreur inattendue : {e}")
        print(f"❌ Erreur inattendue : {e}")
        return False

if __name__ == "__main__":
    # Vérification des arguments
    if len(sys.argv) != 2:
        print("❌ Usage: python download_FV_Portal_zip.py <année>")
        print("   Exemple: python download_FV_Portal_zip.py 2025")
        sys.exit(1)
    
    target_year = sys.argv[1]
    
    # Validation de l'année
    try:
        year_int = int(target_year)
        if year_int < 2000 or year_int > 2030:
            print(f"❌ Année invalide: {target_year}. Utilisez une année entre 2000 et 2030.")
            sys.exit(1)
    except ValueError:
        print(f"❌ Année invalide: {target_year}. Utilisez un nombre (ex: 2025).")
        sys.exit(1)
    
    print(f"🚀 Démarrage du téléchargement des fichiers ZIP CANT-150 B{target_year}...")
    success = download_zip_files(target_year)
    
    if not success:
        print("💥 Le téléchargement a échoué. Consultez les logs pour plus d'informations.")
        sys.exit(1) 