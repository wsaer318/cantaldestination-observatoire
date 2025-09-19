import requests
from requests.auth import HTTPBasicAuth
import yaml
import logging
from datetime import datetime
import os
import sys
import re

# Configuration du logging pour le debug uniquement
logging.basicConfig(level=logging.INFO)

def clean_string(text):
    """
    Nettoie une chaîne de caractères pour éviter les erreurs d'encodage
    """
    if text is None:
        return 'N/A'
    
    # Convertir en string si ce n'est pas déjà le cas
    text = str(text)
    
    # Remplacer les caractères surrogates et autres caractères problématiques
    try:
        # Encoder puis décoder pour nettoyer les surrogates
        text = text.encode('utf-8', errors='replace').decode('utf-8')
        # Remplacer les caractères de remplacement par des espaces
        text = text.replace('\ufffd', ' ')
        return text.strip()
    except Exception:
        return 'N/A'

def format_zip_results_to_txt(target_year):
    """
    Récupère les données de l'API FluxVision et formate dans un fichier txt
    uniquement les deliveries CANT-150 contenant des fichiers .zip avec BX année
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
        DELIVERY_CODE_REF = config['STUDY_CODE_REF']
        
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
        pattern = r'B\d+\s+' + target_year  # Pattern pour matcher BX année (ex: B6 2019, B5 2019)
        
        for result in results:
            file_name = result.get('file_name', '')
            study_code = result.get('study_code_ref', '')
            delivery_name = result.get('delivery_name', '')
            
            if (file_name and file_name.lower().endswith('.zip') and 
                study_code == 'CANT-150' and
                re.search(pattern, delivery_name, re.IGNORECASE)):
                zip_results.append(result)
        
        logging.info(f"Nombre de deliveries ZIP CANT-150 B{target_year} trouvées : {len(zip_results)}")
        
        # Fonction pour extraire le numéro de bimestre
        def extract_bimester_number(delivery_name):
            match = re.search(r'B(\d+)', delivery_name)
            return int(match.group(1)) if match else 0
        
        # Fonction pour convertir la date de modification en objet datetime
        def parse_modification_date(date_string):
            try:
                # Format: 2025-05-27T11:26:48.864911+02:00
                # On enlève les microsecondes et le timezone pour simplifier
                if not date_string:
                    return datetime.min
                
                # Enlever le timezone (+02:00 ou Z)
                clean_date = date_string.split('+')[0].split('Z')[0]
                
                # Enlever les microsecondes si présentes
                if '.' in clean_date:
                    clean_date = clean_date.split('.')[0]
                
                # Parser la date ISO format
                return datetime.fromisoformat(clean_date)
            except Exception:
                return datetime.min  # Date très ancienne si parsing échoue
        
        # Tri combiné : d'abord par bimestre décroissant, puis par date de modification décroissante
        zip_results.sort(key=lambda x: (
            extract_bimester_number(x.get('delivery_name', '')),  # Tri principal : bimestre
            parse_modification_date(x.get('file_last_modified_date', ''))  # Tri secondaire : date
        ), reverse=True)
        logging.info("Résultats triés par ordre décroissant de bimestre, puis par date de modification décroissante")
        
        # Création du nom du fichier de sortie avec timestamp
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        output_file = f"FluxVision_ZIP_CANT150_B{target_year}_{ORGA_CODE_REF}_{timestamp}.txt"
        
        # Écriture formatée dans le fichier (avec gestion des erreurs d'encodage)
        with open(output_file, 'w', encoding='utf-8', errors='replace') as f:
            f.write("=" * 80 + "\n")
            f.write(f"RAPPORT FLUXVISION - FICHIERS ZIP CANT-150 B{target_year} UNIQUEMENT\n")
            f.write("=" * 80 + "\n\n")
            
            f.write(f"Date de génération : {datetime.now().strftime('%d/%m/%Y %H:%M:%S')}\n")
            f.write(f"Organisation : {clean_string(ORGA_CODE_REF)}\n")
            f.write(f"Étude : {clean_string(STUDY_CODE_REF)}\n")
            f.write(f"URL API : {clean_string(API_URL)}\n")
            f.write(f"Statut de la requête : {r.status_code} (OK)\n")
            f.write(f"Nombre total de deliveries : {len(results)}\n")
            f.write(f"Année filtrée : {target_year}\n")
            f.write(f"Pattern de filtrage : B* {target_year}\n")
            f.write(f"Tri : Par bimestre décroissant (B6 → B1), puis par date de modification décroissante\n")
            f.write(f"Nombre de deliveries ZIP CANT-150 B{target_year} : {len(zip_results)}\n")
            f.write(f"Pourcentage de fichiers ZIP CANT-150 B{target_year} : {len(zip_results)/len(results)*100:.1f}%\n\n")
            
            f.write("-" * 80 + "\n")
            f.write(f"DÉTAIL DES DELIVERIES ZIP CANT-150 B{target_year} UNIQUEMENT\n")
            f.write("-" * 80 + "\n\n")
            
            if len(zip_results) == 0:
                f.write(f"Aucune delivery ZIP CANT-150 B{target_year} trouvée.\n")
            else:
                for i, result in enumerate(zip_results, 1):
                    f.write(f"DELIVERY ZIP CANT-150 B{target_year} #{i}\n")
                    f.write(f"  Code de référence : {clean_string(result.get('delivery_code_ref'))}\n")
                    f.write(f"  Date de modification : {clean_string(result.get('file_last_modified_date'))}\n")
                    f.write(f"  Nom du fichier ZIP : {clean_string(result.get('file_name'))}\n")
                    
                    # Ajout d'autres champs s'ils existent
                    for key, value in result.items():
                        if key not in ['delivery_code_ref', 'file_last_modified_date', 'file_name']:
                            clean_key = clean_string(key.replace('_', ' ').title())
                            clean_value = clean_string(value)
                            f.write(f"  {clean_key} : {clean_value}\n")
                    
                    f.write(f"  URL de téléchargement : {clean_string(result.get('url', 'N/A'))}\n")
                    
                    f.write("\n" + "-" * 40 + "\n\n")
            
            # Statistiques supplémentaires
            f.write("\n" + "=" * 80 + "\n")
            f.write("STATISTIQUES\n")
            f.write("=" * 80 + "\n\n")
            
            # Comptage par bimestre
            bimesters = {}
            for result in zip_results:
                delivery_name = result.get('delivery_name', '')
                bimester_num = extract_bimester_number(delivery_name)
                bimester_key = f"B{bimester_num}" if bimester_num > 0 else "Autre"
                
                if bimester_key not in bimesters:
                    bimesters[bimester_key] = 0
                bimesters[bimester_key] += 1
            
            f.write("Répartition par bimestre :\n")
            f.write("-" * 40 + "\n")
            
            # Tri par numéro de bimestre décroissant
            sorted_bimesters = sorted(bimesters.items(), 
                                    key=lambda x: int(x[0][1:]) if x[0].startswith('B') and x[0][1:].isdigit() else -1, 
                                    reverse=True)
            
            for bimester, count in sorted_bimesters:
                # Trouver la date la plus récente pour ce bimestre
                bimester_files = [r for r in zip_results if f"B{extract_bimester_number(r.get('delivery_name', ''))}" == bimester]
                if bimester_files:
                    latest_date = max(parse_modification_date(r.get('file_last_modified_date', '')) for r in bimester_files)
                    date_str = latest_date.strftime('%d/%m/%Y') if latest_date != datetime.min else 'N/A'
                    f.write(f"  {bimester} {target_year} : {count} fichier(s) ZIP (dernière modif: {date_str})\n")
                else:
                    f.write(f"  {bimester} {target_year} : {count} fichier(s) ZIP\n")
            
            f.write("\n" + "-" * 40 + "\n")
            
            # Comptage par étude (gardé pour information)
            studies = {}
            for result in zip_results:
                study_code = result.get('study_code_ref', 'Inconnu')
                study_name = result.get('study_name', 'Nom inconnu')
                if study_code not in studies:
                    studies[study_code] = {'name': study_name, 'count': 0}
                studies[study_code]['count'] += 1
            
            f.write(f"\nNombre d'études différentes avec des fichiers ZIP : {len(studies)}\n\n")
            f.write("Répartition par étude :\n")
            f.write("-" * 40 + "\n")
            
            # Tri par nombre de fichiers décroissant
            sorted_studies = sorted(studies.items(), key=lambda x: x[1]['count'], reverse=True)
            
            for study_code, study_info in sorted_studies:
                f.write(f"  {study_code} - {study_info['name'][:50]}{'...' if len(study_info['name']) > 50 else ''}\n")
                f.write(f"    → {study_info['count']} fichier(s) ZIP\n\n")
            
            f.write("\n" + "=" * 80 + "\n")
            f.write("FIN DU RAPPORT ZIP\n")
            f.write("=" * 80 + "\n")
        
        print(f"✅ Résultats ZIP formatés avec succès dans le fichier : {output_file}")
        print(f"📁 Emplacement : {os.path.abspath(output_file)}")
        print(f"📦 {len(zip_results)} deliveries ZIP CANT-150 B{target_year} sur {len(results)} au total")
        print(f"📊 Pourcentage de fichiers ZIP CANT-150 B{target_year} : {len(zip_results)/len(results)*100:.1f}%")
        
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
        print("❌ Usage: python format_FV_Portal_zip_only.py <année>")
        print("   Exemple: python format_FV_Portal_zip_only.py 2019")
        print("   Cela filtrera les deliveries avec 'B* année' (ex: B6 2019, B5 2019)")
        sys.exit(1)
    
    target_year = sys.argv[1]
    
    # Validation de l'année
    try:
        year_int = int(target_year)
        if year_int < 2000 or year_int > 2030:
            print(f"❌ Année invalide: {target_year}. Utilisez une année entre 2000 et 2030.")
            sys.exit(1)
    except ValueError:
        print(f"❌ Année invalide: {target_year}. Utilisez un nombre (ex: 2019).")
        sys.exit(1)
    
    print(f"🚀 Démarrage du filtrage des résultats FluxVision (ZIP CANT-150 B{target_year})...")
    success = format_zip_results_to_txt(target_year)
    
    if success:
        print("✨ Traitement terminé avec succès !")
    else:
        print("💥 Le traitement a échoué. Consultez les logs pour plus d'informations.") 