#!/usr/bin/env python3
"""
Script pour télécharger automatiquement toutes les années disponibles
"""

import requests
from requests.auth import HTTPBasicAuth
import yaml
import logging
import re
from datetime import datetime
import os
import sys
from pathlib import Path

# Configuration du logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)

def get_available_years():
    """Récupère toutes les années disponibles dans l'API"""
    config_file = 'sample_FV_Portal_api_conf.yml'
    
    try:
        with open(config_file) as cf:
            config = yaml.load(cf, Loader=yaml.FullLoader)
        
        login = config['LOGIN']
        passwd = config['PASSWD']
        auth = HTTPBasicAuth(login, passwd)
        
        API_URL = config['API_URL']
        ORGA_CODE_REF = config['ORGA_CODE_REF']
        
        logging.info(f"Connexion à l'API pour récupérer les années disponibles...")
        r = requests.get(API_URL + '/' + ORGA_CODE_REF + '/', auth=auth)
        
        if r.status_code != 200:
            logging.error(f"Erreur API : Status code {r.status_code}")
            return []
        
        results = r.json()
        years = set()
        
        # Extraire les années des delivery_name
        for result in results:
            delivery_name = result.get('delivery_name', '')
            study_code = result.get('study_code_ref', '')
            file_name = result.get('file_name', '')
            
            if (study_code == 'CANT-150' and 
                file_name and file_name.lower().endswith('.zip')):
                
                # Chercher les patterns B1 2019, B2 2020, etc.
                year_match = re.search(r'B\d+\s+(\d{4})', delivery_name)
                if year_match:
                    years.add(year_match.group(1))
        
        return sorted(list(years))
    
    except Exception as e:
        logging.error(f"Erreur lors de la récupération des années : {e}")
        return []

def download_year(year):
    """Télécharge une année spécifique"""
    print(f"\n🔄 Téléchargement de l'année {year}...")
    
    # Importer la fonction depuis le script existant
    import download_FV_Portal_zip
    success = download_FV_Portal_zip.download_zip_files(year)
    
    if success:
        print(f"✅ Année {year} téléchargée avec succès")
    else:
        print(f"❌ Échec du téléchargement pour l'année {year}")
    
    return success

def main():
    """Fonction principale"""
    print("=" * 60)
    print("🚀 TÉLÉCHARGEMENT AUTOMATIQUE - TOUTES LES ANNÉES")
    print("=" * 60)
    
    # Récupérer les années disponibles
    print("\n🔍 Recherche des années disponibles...")
    available_years = get_available_years()
    
    if not available_years:
        print("❌ Aucune année trouvée ou erreur de connexion")
        return
    
    print(f"📅 Années disponibles : {', '.join(available_years)}")
    print(f"📊 Total : {len(available_years)} années")
    
    # Demander confirmation
    response = input(f"\n❓ Télécharger toutes ces {len(available_years)} années ? (oui/non): ")
    if response.lower() not in ['oui', 'o', 'yes', 'y']:
        print("❌ Téléchargement annulé")
        return
    
    # Télécharger chaque année
    start_time = datetime.now()
    success_count = 0
    failed_years = []
    
    for i, year in enumerate(available_years, 1):
        print(f"\n📦 [{i}/{len(available_years)}] Traitement de l'année {year}")
        
        if download_year(year):
            success_count += 1
        else:
            failed_years.append(year)
    
    # Résumé final
    end_time = datetime.now()
    duration = end_time - start_time
    
    print("\n" + "=" * 60)
    print("📋 RÉSUMÉ FINAL")
    print("=" * 60)
    print(f"⏱️  Durée totale : {duration}")
    print(f"✅ Téléchargements réussis : {success_count}/{len(available_years)}")
    
    if failed_years:
        print(f"❌ Échecs : {', '.join(failed_years)}")
    else:
        print("🎉 Tous les téléchargements ont réussi !")

if __name__ == "__main__":
    main() 