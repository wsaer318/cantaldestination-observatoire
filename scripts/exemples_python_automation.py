#!/usr/bin/env python3
"""
Exemples d'automatisation avec le client Python pour la base de données distante
"""

from python_db_client import RemoteDatabaseClient
import json
import datetime

def main():
    # Initialisation du client
    client = RemoteDatabaseClient()
    
    print("=== EXEMPLES D'AUTOMATISATION ===\n")
    
    # 1. Exploration de la base de données
    print("1. EXPLORATION DE LA BASE")
    print("-" * 30)
    
    # Lister les tables
    result = client.get_tables()
    if result.get('success'):
        print(f"Tables trouvées: {len(result['tables'])}")
        for table in result['tables']:
            # Compter les lignes dans chaque table
            count_result = client.get_count(table)
            if count_result.get('success'):
                print(f"  - {table}: {count_result['count']} lignes")
    
    # 2. Import automatique de données
    print("\n2. IMPORT AUTOMATIQUE")
    print("-" * 30)
    
    # Exemple d'import de données utilisateurs
    users_data = [
        {
            'username': 'user1',
            'email': 'user1@example.com',
            'created_at': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'status': 'active'
        },
        {
            'username': 'user2',
            'email': 'user2@example.com',
            'created_at': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'status': 'active'
        }
    ]
    
    # Insérer les données (remplacez 'users' par le nom de votre table)
    # result = client.batch_insert('users', users_data)
    # if result.get('success'):
    #     print(f"Import terminé: {result['inserted']}/{result['total']} utilisateurs ajoutés")
    
    # 3. Mise à jour automatique
    print("\n3. MISE À JOUR AUTOMATIQUE")
    print("-" * 30)
    
    # Exemple: désactiver les utilisateurs inactifs depuis 30 jours
    # sql = """
    #     UPDATE users 
    #     SET status = 'inactive' 
    #     WHERE last_login < DATE_SUB(NOW(), INTERVAL 30 DAY)
    #     AND status = 'active'
    # """
    # result = client.execute_query(sql)
    # if result.get('success'):
    #     print(f"Utilisateurs désactivés: {result.get('affected_rows', 0)}")
    
    # 4. Nettoyage automatique
    print("\n4. NETTOYAGE AUTOMATIQUE")
    print("-" * 30)
    
    # Exemple: supprimer les logs anciens
    # result = client.cleanup_old_records('logs', 'created_at', 90)
    # if result.get('success'):
    #     print(f"Logs supprimés: {result.get('affected_rows', 0)}")
    
    # 5. Sauvegarde automatique
    print("\n5. SAUVEGARDE AUTOMATIQUE")
    print("-" * 30)
    
    # Exemple: sauvegarder une table importante
    # result = client.backup_table('users')
    # if result.get('success'):
    #     print("Sauvegarde de la table 'users' créée")
    
    # 6. Requêtes complexes
    print("\n6. REQUÊTES COMPLEXES")
    print("-" * 30)
    
    # Exemple: statistiques utilisateurs
    # sql = """
    #     SELECT 
    #         status,
    #         COUNT(*) as count,
    #         DATE(created_at) as date
    #     FROM users 
    #     GROUP BY status, DATE(created_at)
    #     ORDER BY date DESC
    # """
    # result = client.execute_query(sql)
    # if result.get('success'):
    #     print("Statistiques utilisateurs:")
    #     for row in result['data']:
    #         print(f"  {row['date']} - {row['status']}: {row['count']}")
    
    # 7. Traitement de fichiers CSV
    print("\n7. TRAITEMENT CSV")
    print("-" * 30)
    
    # Exemple: import depuis un fichier CSV
    # result = client.import_csv('products', 'products.csv')
    # if result.get('success'):
    #     print(f"Produits importés: {result['imported']}/{result['total']}")
    
    print("\n=== AUTOMATISATION TERMINÉE ===")

def exemple_traitement_donnees():
    """Exemple de traitement de données avec pandas"""
    try:
        import pandas as pd
        
        client = RemoteDatabaseClient()
        
        # Récupérer des données
        result = client.execute_query("SELECT * FROM users LIMIT 100")
        if result.get('success'):
            # Convertir en DataFrame pandas
            df = pd.DataFrame(result['data'])
            
            # Traitement des données
            df['age_group'] = pd.cut(df['age'], bins=[0, 25, 50, 100], labels=['Jeune', 'Adulte', 'Senior'])
            
            # Statistiques
            stats = df.groupby('age_group').agg({
                'id': 'count',
                'status': lambda x: (x == 'active').sum()
            }).rename(columns={'id': 'total', 'status': 'actifs'})
            
            print("Statistiques par groupe d'âge:")
            print(stats)
            
            # Mettre à jour la base avec les nouvelles données
            for index, row in df.iterrows():
                client.update_data('users', 
                                 {'age_group': row['age_group']}, 
                                 {'id': row['id']})
        
    except ImportError:
        print("Pandas non installé. Installez-le avec: pip install pandas")

def exemple_planification_taches():
    """Exemple de planification de tâches"""
    import schedule
    import time
    
    client = RemoteDatabaseClient()
    
    def nettoyage_quotidien():
        """Nettoyage quotidien des données temporaires"""
        print("Exécution du nettoyage quotidien...")
        client.cleanup_old_records('temp_data', 'created_at', 1)
    
    def sauvegarde_hebdomadaire():
        """Sauvegarde hebdomadaire des tables importantes"""
        print("Exécution de la sauvegarde hebdomadaire...")
        tables_importantes = ['users', 'orders', 'products']
        for table in tables_importantes:
            client.backup_table(table)
    
    def rapport_mensuel():
        """Génération du rapport mensuel"""
        print("Génération du rapport mensuel...")
        sql = """
            SELECT 
                COUNT(*) as total_users,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
                DATE_FORMAT(created_at, '%Y-%m') as month
            FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        """
        result = client.execute_query(sql)
        if result.get('success'):
            print("Rapport mensuel généré")
    
    # Planification des tâches
    schedule.every().day.at("02:00").do(nettoyage_quotidien)
    schedule.every().sunday.at("03:00").do(sauvegarde_hebdomadaire)
    schedule.every().month.at("01:00").do(rapport_mensuel)
    
    print("Planificateur de tâches démarré...")
    while True:
        schedule.run_pending()
        time.sleep(60)

if __name__ == "__main__":
    # Exécuter les exemples de base
    main()
    
    # Décommentez pour tester les exemples avancés
    # exemple_traitement_donnees()
    # exemple_planification_taches()
