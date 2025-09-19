#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Configuration du filtrage des fichiers pour FluxVision
Centralise les règles de filtrage des fichiers CSV
"""

# Préfixes de fichiers autorisés pour la base de données FluxVision
# Basé sur le fichier database_source_files.txt
ALLOWED_FILE_PREFIXES = [
    'Nuitee_',
    'Diurne_', 
    'Nuitee_Departement_',
    'Diurne_Departement_',
    'Nuitee_Pays_',
    'Diurne_Pays_',
    'Nuitee_Age_',
    'Diurne_Age_',
    'Nuitee_Geolife_',
    'Diurne_Geolife_'
]

# Mappage vers les noms de tables de la base de données
TABLE_MAPPING = {
    'Nuitee_': 'fact_nuitees',
    'Diurne_': 'fact_diurnes',
    'Nuitee_Departement_': 'fact_nuitees_departements', 
    'Diurne_Departement_': 'fact_diurnes_departements',
    'Nuitee_Pays_': 'fact_nuitees_pays',
    'Diurne_Pays_': 'fact_diurnes_pays',
    'Nuitee_Age_': 'fact_nuitees_age',
    'Diurne_Age_': 'fact_diurnes_age',
    'Nuitee_Geolife_': 'fact_nuitees_geolife',
    'Diurne_Geolife_': 'fact_diurnes_geolife'
}

# Exemples de fichiers qui seront IGNORÉS
IGNORED_FILE_EXAMPLES = [
    'SejourDuree_*',
    'Recurrence_*',
    'Arrivee_*', 
    'Depart_*',
    'LieuActivite_*',
    'LieuNuitee_*',
    'DureePresence_*',
    'Dispo_*'
]

def is_allowed_file(filename):
    """
    Vérifie si un fichier CSV correspond aux types autorisés pour la base de données
    
    Args:
        filename (str): Nom du fichier à vérifier
        
    Returns:
        bool: True si le fichier est autorisé, False sinon
    """
    if not filename.lower().endswith('.csv'):
        return False
    
    for prefix in ALLOWED_FILE_PREFIXES:
        if filename.startswith(prefix):
            return True
    
    return False

def get_table_name(filename):
    """
    Détermine le nom de la table de destination selon le préfixe du fichier
    
    Args:
        filename (str): Nom du fichier CSV
        
    Returns:
        str: Nom de la table de destination ou None si non trouvé
    """
    for prefix, table in TABLE_MAPPING.items():
        if filename.startswith(prefix):
            return table
    
    return None

def get_filtering_stats():
    """
    Retourne les statistiques de configuration du filtrage
    
    Returns:
        dict: Dictionnaire avec les statistiques
    """
    return {
        'total_allowed_types': len(ALLOWED_FILE_PREFIXES),
        'total_ignored_types': len(IGNORED_FILE_EXAMPLES),
        'allowed_prefixes': ALLOWED_FILE_PREFIXES,
        'ignored_examples': IGNORED_FILE_EXAMPLES,
        'table_mapping': TABLE_MAPPING
    }

if __name__ == "__main__":
    # Test de la configuration
    print("=== CONFIGURATION DU FILTRAGE FLUXVISION ===")
    print(f"Types autorisés: {len(ALLOWED_FILE_PREFIXES)}")
    for prefix in ALLOWED_FILE_PREFIXES:
        table = TABLE_MAPPING.get(prefix, "NON MAPPÉ")
        print(f"  {prefix} → {table}")
    
    print(f"\nExemples ignorés: {len(IGNORED_FILE_EXAMPLES)}")
    for example in IGNORED_FILE_EXAMPLES:
        print(f"  {example}")
    
    # Test avec des exemples de fichiers
    test_files = [
        "Diurne_2023B1_CABA0.csv",
        "Nuitee_Age_2023B1_CABA0.csv", 
        "SejourDuree_Pays_2023B1_CABA0.csv",
        "Arrivee_Age_2023B1_CABA0.csv"
    ]
    
    print("\n=== TESTS ===")
    for test_file in test_files:
        status = "✅ AUTORISÉ" if is_allowed_file(test_file) else "❌ IGNORÉ"
        table = get_table_name(test_file) or "AUCUNE"
        print(f"{test_file}: {status} → {table}") 