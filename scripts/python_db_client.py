#!/usr/bin/env python3
"""
Client Python pour interagir avec la base de données distante via API PHP
Usage: python python_db_client.py [command] [options]
"""

import requests
import json
import sys
import datetime
from typing import Dict, List, Any, Optional

class RemoteDatabaseClient:
    def __init__(self, server_url: str = None):
        self.server_url = server_url or 'https://observatoire.cantal-destination.com/api/database_api.php'
        self.api_key = f'observatoire_python_2024_{datetime.date.today().strftime("%Y-%m-%d")}'
        
    def _make_request(self, action: str, data: Dict = None, method: str = 'POST') -> Dict:
        """Effectue une requête vers l'API distante"""
        url = f"{self.server_url}?key={self.api_key}&action={action}"
        
        headers = {
            'Content-Type': 'application/json',
            'Authorization': f'Bearer {self.api_key}'
        }
        
        try:
            if method == 'GET':
                response = requests.get(url, headers=headers, timeout=30)
            else:
                response = requests.post(url, json=data or {}, headers=headers, timeout=30)
            
            response.raise_for_status()
            return response.json()
            
        except requests.exceptions.RequestException as e:
            return {
                'success': False,
                'error': f'Erreur de connexion: {str(e)}'
            }
        except json.JSONDecodeError as e:
            return {
                'success': False,
                'error': f'Erreur de décodage JSON: {str(e)}'
            }
    
    def execute_query(self, sql: str, params: List = None) -> Dict:
        """Exécute une requête SQL personnalisée"""
        data = {
            'sql': sql,
            'params': params or []
        }
        return self._make_request('query', data)
    
    def insert_data(self, table: str, values: Dict) -> Dict:
        """Insère des données dans une table"""
        data = {
            'table': table,
            'values': values
        }
        return self._make_request('insert', data)
    
    def update_data(self, table: str, values: Dict, where: Dict) -> Dict:
        """Met à jour des données dans une table"""
        data = {
            'table': table,
            'values': values,
            'where': where
        }
        return self._make_request('update', data)
    
    def delete_data(self, table: str, where: Dict = None) -> Dict:
        """Supprime des données d'une table"""
        data = {
            'table': table,
            'where': where or {}
        }
        return self._make_request('delete', data)
    
    def get_tables(self) -> Dict:
        """Récupère la liste des tables"""
        return self._make_request('tables')
    
    def get_structure(self, table: str) -> Dict:
        """Récupère la structure d'une table"""
        data = {'table': table}
        return self._make_request('structure', data)
    
    def get_count(self, table: str) -> Dict:
        """Récupère le nombre de lignes dans une table"""
        data = {'table': table}
        return self._make_request('count', data)
    
    def import_csv(self, table: str, csv_file: str, delimiter: str = ',') -> Dict:
        """Importe des données depuis un fichier CSV"""
        try:
            import csv
            rows = []
            with open(csv_file, 'r', encoding='utf-8') as file:
                reader = csv.DictReader(file, delimiter=delimiter)
                for row in reader:
                    rows.append(row)
            
            if not rows:
                return {'success': False, 'error': 'Fichier CSV vide'}
            
            # Insérer toutes les lignes
            success_count = 0
            for row in rows:
                result = self.insert_data(table, row)
                if result.get('success'):
                    success_count += 1
            
            return {
                'success': True,
                'imported': success_count,
                'total': len(rows)
            }
            
        except FileNotFoundError:
            return {'success': False, 'error': f'Fichier {csv_file} non trouvé'}
        except Exception as e:
            return {'success': False, 'error': f'Erreur lors de l\'import: {str(e)}'}
    
    def batch_insert(self, table: str, data_list: List[Dict]) -> Dict:
        """Insère plusieurs lignes en une fois"""
        success_count = 0
        errors = []
        
        for i, data in enumerate(data_list):
            result = self.insert_data(table, data)
            if result.get('success'):
                success_count += 1
            else:
                errors.append(f"Ligne {i+1}: {result.get('error', 'Erreur inconnue')}")
        
        return {
            'success': True,
            'inserted': success_count,
            'total': len(data_list),
            'errors': errors
        }
    
    def cleanup_old_records(self, table: str, date_column: str, days: int) -> Dict:
        """Nettoie les anciens enregistrements"""
        sql = f"DELETE FROM {table} WHERE {date_column} < DATE_SUB(NOW(), INTERVAL {days} DAY)"
        return self.execute_query(sql)
    
    def backup_table(self, table: str, backup_suffix: str = None) -> Dict:
        """Crée une sauvegarde d'une table"""
        if not backup_suffix:
            backup_suffix = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
        
        backup_table = f"{table}_backup_{backup_suffix}"
        sql = f"CREATE TABLE {backup_table} AS SELECT * FROM {table}"
        return self.execute_query(sql)

def main():
    """Fonction principale pour l'interface en ligne de commande"""
    if len(sys.argv) < 2:
        print("Usage: python python_db_client.py [command] [options]")
        print("\nCommandes disponibles:")
        print("  tables                    - Liste toutes les tables")
        print("  structure <table>         - Structure d'une table")
        print("  count <table>             - Nombre de lignes dans une table")
        print("  query <sql>               - Exécute une requête SQL")
        print("  insert <table> <json>     - Insère des données")
        print("  update <table> <values> <where> - Met à jour des données")
        print("  delete <table> <where>    - Supprime des données")
        print("  import <table> <csv_file> - Importe un fichier CSV")
        print("  cleanup <table> <date_col> <days> - Nettoie les anciens enregistrements")
        print("  backup <table>            - Sauvegarde une table")
        return
    
    client = RemoteDatabaseClient()
    command = sys.argv[1]
    
    try:
        if command == 'tables':
            result = client.get_tables()
            if result.get('success'):
                print("Tables disponibles:")
                for table in result['tables']:
                    print(f"  - {table}")
            else:
                print(f"Erreur: {result.get('error')}")
        
        elif command == 'structure' and len(sys.argv) >= 3:
            table = sys.argv[2]
            result = client.get_structure(table)
            if result.get('success'):
                print(f"Structure de la table '{table}':")
                for col in result['structure']:
                    print(f"  {col['Field']} - {col['Type']} - {col['Null']} - {col['Key']}")
            else:
                print(f"Erreur: {result.get('error')}")
        
        elif command == 'count' and len(sys.argv) >= 3:
            table = sys.argv[2]
            result = client.get_count(table)
            if result.get('success'):
                print(f"Nombre de lignes dans '{table}': {result['count']}")
            else:
                print(f"Erreur: {result.get('error')}")
        
        elif command == 'query' and len(sys.argv) >= 3:
            sql = sys.argv[2]
            result = client.execute_query(sql)
            if result.get('success'):
                if 'data' in result:
                    print(f"Résultats ({result['count']} lignes):")
                    for row in result['data']:
                        print(f"  {row}")
                else:
                    print(f"Requête exécutée: {result.get('affected_rows', 0)} lignes affectées")
            else:
                print(f"Erreur: {result.get('error')}")
        
        elif command == 'insert' and len(sys.argv) >= 4:
            table = sys.argv[2]
            values = json.loads(sys.argv[3])
            result = client.insert_data(table, values)
            if result.get('success'):
                print(f"Données insérées. ID: {result.get('id')}")
            else:
                print(f"Erreur: {result.get('error')}")
        
        elif command == 'update' and len(sys.argv) >= 5:
            table = sys.argv[2]
            values = json.loads(sys.argv[3])
            where = json.loads(sys.argv[4])
            result = client.update_data(table, values, where)
            if result.get('success'):
                print(f"Données mises à jour. Lignes affectées: {result.get('affected_rows')}")
            else:
                print(f"Erreur: {result.get('error')}")
        
        elif command == 'delete' and len(sys.argv) >= 4:
            table = sys.argv[2]
            where = json.loads(sys.argv[3]) if len(sys.argv) > 3 else {}
            result = client.delete_data(table, where)
            if result.get('success'):
                print(f"Données supprimées. Lignes affectées: {result.get('affected_rows')}")
            else:
                print(f"Erreur: {result.get('error')}")
        
        elif command == 'import' and len(sys.argv) >= 4:
            table = sys.argv[2]
            csv_file = sys.argv[3]
            result = client.import_csv(table, csv_file)
            if result.get('success'):
                print(f"Import terminé: {result['imported']}/{result['total']} lignes importées")
            else:
                print(f"Erreur: {result.get('error')}")
        
        elif command == 'cleanup' and len(sys.argv) >= 5:
            table = sys.argv[2]
            date_column = sys.argv[3]
            days = int(sys.argv[4])
            result = client.cleanup_old_records(table, date_column, days)
            if result.get('success'):
                print(f"Nettoyage terminé. Lignes supprimées: {result.get('affected_rows')}")
            else:
                print(f"Erreur: {result.get('error')}")
        
        elif command == 'backup' and len(sys.argv) >= 3:
            table = sys.argv[2]
            result = client.backup_table(table)
            if result.get('success'):
                print(f"Sauvegarde de la table '{table}' créée avec succès")
            else:
                print(f"Erreur: {result.get('error')}")
        
        else:
            print("Commande non reconnue ou arguments manquants")
            print("Utilisez 'python python_db_client.py' pour voir l'aide")
    
    except json.JSONDecodeError:
        print("Erreur: Format JSON invalide")
    except Exception as e:
        print(f"Erreur inattendue: {str(e)}")

if __name__ == "__main__":
    main()
