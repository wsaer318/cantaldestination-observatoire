import requests
from requests.auth import HTTPBasicAuth
import yaml
import logging
from datetime import datetime
import os

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

def format_results_to_txt():
    """
    Récupère les données de l'API FluxVision et les formate dans un fichier txt
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
        
        # Création du nom du fichier de sortie avec timestamp
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        output_file = f"FluxVision_Results_{ORGA_CODE_REF}_{timestamp}.txt"
        
        # Écriture formatée dans le fichier (avec gestion des erreurs d'encodage)
        with open(output_file, 'w', encoding='utf-8', errors='replace') as f:
            f.write("=" * 80 + "\n")
            f.write("RAPPORT FLUXVISION - RÉSULTATS API\n")
            f.write("=" * 80 + "\n\n")
            
            f.write(f"Date de génération : {datetime.now().strftime('%d/%m/%Y %H:%M:%S')}\n")
            f.write(f"Organisation : {clean_string(ORGA_CODE_REF)}\n")
            f.write(f"Étude : {clean_string(STUDY_CODE_REF)}\n")
            f.write(f"URL API : {clean_string(API_URL)}\n")
            f.write(f"Statut de la requête : {r.status_code} (OK)\n")
            f.write(f"Nombre total de deliveries : {len(results)}\n\n")
            
            f.write("-" * 80 + "\n")
            f.write("DÉTAIL DES DELIVERIES\n")
            f.write("-" * 80 + "\n\n")
            
            if len(results) == 0:
                f.write("Aucune delivery trouvée.\n")
            else:
                for i, result in enumerate(results, 1):
                    f.write(f"DELIVERY #{i}\n")
                    f.write(f"  Code de référence : {clean_string(result.get('delivery_code_ref'))}\n")
                    f.write(f"  Date de modification : {clean_string(result.get('file_last_modified_date'))}\n")
                    f.write(f"  Nom du fichier : {clean_string(result.get('file_name'))}\n")
                    
                    # Ajout d'autres champs s'ils existent
                    for key, value in result.items():
                        if key not in ['delivery_code_ref', 'file_last_modified_date', 'file_name']:
                            clean_key = clean_string(key.replace('_', ' ').title())
                            clean_value = clean_string(value)
                            f.write(f"  {clean_key} : {clean_value}\n")
                    
                    f.write("\n" + "-" * 40 + "\n\n")
            
            f.write("\n" + "=" * 80 + "\n")
            f.write("FIN DU RAPPORT\n")
            f.write("=" * 80 + "\n")
        
        print(f"✅ Résultats formatés avec succès dans le fichier : {output_file}")
        print(f"📁 Emplacement : {os.path.abspath(output_file)}")
        print(f"📊 {len(results)} deliveries traitées")
        
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
    print("🚀 Démarrage du formatage des résultats FluxVision...")
    success = format_results_to_txt()
    
    if success:
        print("✨ Traitement terminé avec succès !")
    else:
        print("💥 Le traitement a échoué. Consultez les logs pour plus d'informations.") 