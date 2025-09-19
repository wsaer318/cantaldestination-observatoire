#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import requests
import json
import sys
from datetime import datetime

class RemoteDatabaseExplorer:
    def __init__(self, base_url, api_key):
        self.base_url = base_url.rstrip('/')
        self.api_key = api_key
        self.session = requests.Session()
    
    def make_request(self, action, **params):
        """Faire une requête à l'API PHP"""
        url = f"{self.base_url}/api/db_explorer.php"
        params['key'] = self.api_key
        params['action'] = action
        
        try:
            response = self.session.get(url, params=params, timeout=30)
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            print(f"❌ Erreur de requête: {e}")
            return None
        except json.JSONDecodeError as e:
            print(f"❌ Erreur de décodage JSON: {e}")
            return None
    
    def get_database_info(self):
        """Obtenir les informations générales de la base"""
        print("🔍 INFORMATIONS DE LA BASE DE DONNÉES")
        print("=" * 50)
        
        result = self.make_request('info')
        if result and result.get('success'):
            info = result
            print(f"📊 Base de données: {info['database']}")
            print(f"📊 Version MySQL: {info['mysql_version']}")
            print(f"📊 Timestamp: {info['timestamp']}")
            return True
        else:
            print("❌ Impossible d'obtenir les informations")
            return False
    
    def list_tables(self):
        """Lister toutes les tables"""
        print("\n📋 TABLES DE LA BASE DE DONNÉES")
        print("=" * 50)
        
        result = self.make_request('tables')
        if result and result.get('success'):
            tables = result['tables']
            print(f"📊 Nombre total de tables: {result['count']}")
            print("\nTables trouvées:")
            for i, table in enumerate(tables, 1):
                print(f"  {i:2d}. {table}")
            return tables
        else:
            print("❌ Impossible de lister les tables")
            return []
    
    def get_table_structure(self, table_name):
        """Obtenir la structure d'une table"""
        print(f"\n🔍 STRUCTURE DE LA TABLE: {table_name}")
        print("=" * 50)
        
        result = self.make_request('structure', table=table_name)
        if result and result.get('success'):
            structure = result['structure']
            print(f"📊 Table: {result['table']}")
            print("\nColonnes:")
            for col in structure:
                print(f"  - {col['Field']} ({col['Type']}) - {col['Null']} - {col['Key']}")
            return structure
        else:
            print(f"❌ Impossible d'obtenir la structure de {table_name}")
            return None
    
    def get_table_count(self, table_name):
        """Compter les enregistrements d'une table"""
        result = self.make_request('count', table=table_name)
        if result and result.get('success'):
            return result['count']
        return 0
    
    def get_table_sample(self, table_name, limit=5):
        """Obtenir un échantillon de données d'une table"""
        print(f"\n📄 ÉCHANTILLON DE DONNÉES: {table_name}")
        print("=" * 50)
        
        result = self.make_request('sample', table=table_name, limit=limit)
        if result and result.get('success'):
            data = result['data']
            print(f"📊 Table: {result['table']}")
            print(f"📊 Limite: {result['limit']} lignes")
            
            if data:
                # Afficher les colonnes
                columns = list(data[0].keys())
                print(f"\nColonnes: {', '.join(columns)}")
                
                # Afficher les données
                print("\nDonnées:")
                for i, row in enumerate(data, 1):
                    print(f"  Ligne {i}:")
                    for col, val in row.items():
                        print(f"    {col}: {val}")
            else:
                print("📄 Table vide")
            return data
        else:
            print(f"❌ Impossible d'obtenir l'échantillon de {table_name}")
            return None
    
    def get_fluxvision_stats(self):
        """Obtenir les statistiques FluxVision"""
        print("\n🎯 STATISTIQUES FLUXVISION")
        print("=" * 50)
        
        result = self.make_request('fluxvision_stats')
        if result and result.get('success'):
            stats = result['fluxvision_stats']
            
            # Tables de dimensions
            print("📊 TABLES DE DIMENSIONS:")
            for table, count in stats['dimensions'].items():
                if count == 'inexistante':
                    print(f"  ❌ {table}: Table inexistante")
                else:
                    print(f"  ✅ {table}: {count:,} enregistrements")
            
            # Tables de faits
            print("\n📈 TABLES DE FAITS:")
            for table, count in stats['facts'].items():
                if count == 'inexistante':
                    print(f"  ❌ {table}: Table inexistante")
                else:
                    print(f"  ✅ {table}: {count:,} enregistrements")
            
            print(f"\n📊 Timestamp: {result['timestamp']}")
            return stats
        else:
            print("❌ Impossible d'obtenir les statistiques FluxVision")
            return None
    
    def interactive_explorer(self):
        """Mode interactif pour explorer les tables"""
        print("\n🔍 MODE INTERACTIF")
        print("=" * 50)
        print("Commandes disponibles:")
        print("  info     - Informations de la base")
        print("  tables   - Lister toutes les tables")
        print("  stats    - Statistiques FluxVision")
        print("  struct <table> - Structure d'une table")
        print("  count <table>  - Compter les enregistrements")
        print("  sample <table> [limit] - Échantillon de données")
        print("  quit     - Quitter")
        
        while True:
            try:
                command = input("\nCommande: ").strip().lower()
                
                if command == 'quit':
                    break
                elif command == 'info':
                    self.get_database_info()
                elif command == 'tables':
                    self.list_tables()
                elif command == 'stats':
                    self.get_fluxvision_stats()
                elif command.startswith('struct '):
                    table = command[7:].strip()
                    if table:
                        self.get_table_structure(table)
                    else:
                        print("❌ Nom de table requis")
                elif command.startswith('count '):
                    table = command[6:].strip()
                    if table:
                        count = self.get_table_count(table)
                        print(f"📊 {table}: {count:,} enregistrements")
                    else:
                        print("❌ Nom de table requis")
                elif command.startswith('sample '):
                    parts = command[7:].strip().split()
                    if parts:
                        table = parts[0]
                        limit = int(parts[1]) if len(parts) > 1 else 5
                        self.get_table_sample(table, limit)
                    else:
                        print("❌ Nom de table requis")
                else:
                    print("❌ Commande non reconnue")
                    
            except KeyboardInterrupt:
                break
            except Exception as e:
                print(f"❌ Erreur: {e}")

def main():
    print("🔍 EXPLORATEUR DE BASE DE DONNÉES DISTANTE FLUXVISION")
    print("=" * 60)
    
    # Configuration
    BASE_URL = "https://srv.cantal-destination.com"
    API_KEY = "fluxvision_2024_secure_key"
    
    explorer = RemoteDatabaseExplorer(BASE_URL, API_KEY)
    
    # Test de connexion
    print("📡 Test de connexion...")
    if not explorer.get_database_info():
        print("❌ Impossible de se connecter à l'API")
        sys.exit(1)
    
    # Mode interactif
    explorer.interactive_explorer()
    
    print("\n✅ Exploration terminée")

if __name__ == "__main__":
    main()
