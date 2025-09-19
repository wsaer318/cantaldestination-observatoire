<?php
/**
 * Client d'exploration de base de données distant
 * À utiliser depuis votre ordinateur pour accéder à la base de données du serveur
 */

class RemoteDBClient {
    
    private $server_url;
    private $api_key;
    
    public function __construct($server_url = null) {
        // URL de votre serveur
        $this->server_url = $server_url ?? 'https://observatoire.cantal-destination.com/api/remote_db_explorer.php';
        
        // Clé d'authentification (change quotidiennement)
        $this->api_key = 'observatoire_remote_2024_' . date('Y-m-d');
    }
    
    /**
     * Faire une requête à l'explorateur distant
     */
    private function makeRequest($action, $params = []) {
        $params['key'] = $this->api_key;
        $params['action'] = $action;
        
        $url = $this->server_url . '?' . http_build_query($params);
        
        // Configuration de la requête
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'header' => [
                    'User-Agent: RemoteDBClient/1.0',
                    'Accept: application/json'
                ]
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            return [
                'success' => false,
                'error' => 'Erreur de connexion au serveur'
            ];
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Afficher les informations générales de la base
     */
    public function showInfo() {
        echo "=== INFORMATIONS GÉNÉRALES ===\n";
        
        $result = $this->makeRequest('info');
        
        if ($result['success']) {
            echo "✅ Base de données: " . $result['database'] . "\n";
            echo "📊 Version MySQL: " . $result['mysql_version'] . "\n";
            echo "🕐 Timestamp: " . $result['timestamp'] . "\n";
            echo "🌍 Timezone: " . $result['server_timezone'] . "\n";
        } else {
            echo "❌ Erreur: " . ($result['error'] ?? 'Erreur inconnue') . "\n";
        }
        echo "\n";
    }
    
    /**
     * Lister toutes les tables
     */
    public function listTables() {
        echo "=== LISTE DES TABLES ===\n";
        
        $result = $this->makeRequest('tables');
        
        if ($result['success']) {
            echo "📋 Nombre total de tables: " . $result['count'] . "\n\n";
            
            foreach ($result['tables'] as $table) {
                $size_mb = round($table['size'] / 1024 / 1024, 2);
                $index_mb = round($table['index_size'] / 1024 / 1024, 2);
                
                echo "📊 " . str_pad($table['name'], 30) . " | ";
                echo "Enregistrements: " . str_pad(number_format($table['records']), 10) . " | ";
                echo "Taille: " . str_pad($size_mb . " MB", 8) . " | ";
                echo "Index: " . str_pad($index_mb . " MB", 8) . " | ";
                echo "Engine: " . $table['engine'] . "\n";
            }
        } else {
            echo "❌ Erreur: " . ($result['error'] ?? 'Erreur inconnue') . "\n";
        }
        echo "\n";
    }
    
    /**
     * Afficher la structure d'une table
     */
    public function showStructure($table_name) {
        echo "=== STRUCTURE DE LA TABLE: $table_name ===\n";
        
        $result = $this->makeRequest('structure', ['table' => $table_name]);
        
        if ($result['success']) {
            $table_info = $result['table_info'];
            echo "📊 Informations de la table:\n";
            echo "   Engine: " . $table_info['engine'] . "\n";
            echo "   Collation: " . $table_info['collation'] . "\n";
            echo "   Enregistrements: " . number_format($table_info['rows']) . "\n";
            echo "   Taille moyenne par ligne: " . $table_info['avg_row_length'] . " bytes\n";
            echo "   Taille des données: " . round($table_info['data_length'] / 1024 / 1024, 2) . " MB\n";
            echo "   Taille des index: " . round($table_info['index_length'] / 1024 / 1024, 2) . " MB\n\n";
            
            echo "📋 Structure des colonnes:\n";
            echo str_pad("Colonne", 25) . " | " . str_pad("Type", 20) . " | " . str_pad("Null", 5) . " | " . str_pad("Clé", 5) . " | " . str_pad("Défaut", 10) . " | Extra\n";
            echo str_repeat("-", 80) . "\n";
            
            foreach ($result['structure'] as $column) {
                echo str_pad($column['Field'], 25) . " | ";
                echo str_pad($column['Type'], 20) . " | ";
                echo str_pad($column['Null'], 5) . " | ";
                echo str_pad($column['Key'], 5) . " | ";
                echo str_pad($column['Default'] ?? 'NULL', 10) . " | ";
                echo $column['Extra'] . "\n";
            }
        } else {
            echo "❌ Erreur: " . ($result['error'] ?? 'Erreur inconnue') . "\n";
        }
        echo "\n";
    }
    
    /**
     * Afficher un échantillon de données
     */
    public function showSample($table_name, $limit = 10, $offset = 0) {
        echo "=== ÉCHANTILLON DE DONNÉES: $table_name ===\n";
        
        $result = $this->makeRequest('sample', [
            'table' => $table_name,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
        if ($result['success']) {
            echo "📊 Affichage des lignes " . ($offset + 1) . " à " . ($offset + count($result['sample'])) . " sur " . number_format($result['total']) . " total\n\n";
            
            if (empty($result['sample'])) {
                echo "Aucune donnée trouvée.\n";
            } else {
                // Afficher les en-têtes
                $headers = array_keys($result['sample'][0]);
                foreach ($headers as $header) {
                    echo str_pad($header, 20) . " | ";
                }
                echo "\n" . str_repeat("-", count($headers) * 23) . "\n";
                
                // Afficher les données
                foreach ($result['sample'] as $row) {
                    foreach ($row as $value) {
                        $display_value = is_null($value) ? 'NULL' : (string)$value;
                        if (strlen($display_value) > 18) {
                            $display_value = substr($display_value, 0, 15) . '...';
                        }
                        echo str_pad($display_value, 20) . " | ";
                    }
                    echo "\n";
                }
            }
            
            if ($result['has_more']) {
                echo "\n💡 Utilisez --offset " . ($offset + $limit) . " pour voir plus de données\n";
            }
        } else {
            echo "❌ Erreur: " . ($result['error'] ?? 'Erreur inconnue') . "\n";
        }
        echo "\n";
    }
    
    /**
     * Rechercher dans une table
     */
    public function search($table_name, $column, $search_term, $limit = 50) {
        echo "=== RECHERCHE DANS $table_name ===\n";
        echo "🔍 Recherche '$search_term' dans la colonne '$column'\n\n";
        
        $result = $this->makeRequest('search', [
            'table' => $table_name,
            'column' => $column,
            'search' => $search_term,
            'limit' => $limit
        ]);
        
        if ($result['success']) {
            echo "📊 " . $result['count'] . " résultat(s) trouvé(s)\n\n";
            
            if (empty($result['results'])) {
                echo "Aucun résultat trouvé.\n";
            } else {
                // Afficher les en-têtes
                $headers = array_keys($result['results'][0]);
                foreach ($headers as $header) {
                    echo str_pad($header, 20) . " | ";
                }
                echo "\n" . str_repeat("-", count($headers) * 23) . "\n";
                
                // Afficher les résultats
                foreach ($result['results'] as $row) {
                    foreach ($row as $value) {
                        $display_value = is_null($value) ? 'NULL' : (string)$value;
                        if (strlen($display_value) > 18) {
                            $display_value = substr($display_value, 0, 15) . '...';
                        }
                        echo str_pad($display_value, 20) . " | ";
                    }
                    echo "\n";
                }
            }
        } else {
            echo "❌ Erreur: " . ($result['error'] ?? 'Erreur inconnue') . "\n";
        }
        echo "\n";
    }
    
    /**
     * Afficher les statistiques
     */
    public function showStats() {
        echo "=== STATISTIQUES DE LA BASE ===\n";
        
        $result = $this->makeRequest('stats');
        
        if ($result['success']) {
            $stats = $result['stats'];
            echo "📊 Nombre de tables: " . $stats['tables_count'] . "\n";
            echo "📈 Total d'enregistrements: " . number_format($stats['total_records']) . "\n";
            echo "💾 Taille totale: " . $stats['total_size_mb'] . " MB\n";
            echo "   - Données: " . $stats['data_size_mb'] . " MB\n";
            echo "   - Index: " . $stats['index_size_mb'] . " MB\n";
            echo "🕐 Timestamp: " . $result['timestamp'] . "\n";
        } else {
            echo "❌ Erreur: " . ($result['error'] ?? 'Erreur inconnue') . "\n";
        }
        echo "\n";
    }
    
    /**
     * Afficher l'aide
     */
    public function showHelp() {
        echo "=== AIDE - CLIENT D'EXPLORATION DE BASE DE DONNÉES ===\n\n";
        echo "Usage: php remote_db_client.php [COMMANDE] [PARAMÈTRES]\n\n";
        echo "Commandes disponibles:\n";
        echo "  info                    - Informations générales de la base\n";
        echo "  tables                  - Lister toutes les tables\n";
        echo "  structure TABLE         - Structure d'une table\n";
        echo "  sample TABLE [LIMIT] [OFFSET] - Échantillon de données\n";
        echo "  search TABLE COLUMN TERM [LIMIT] - Recherche dans une table\n";
        echo "  stats                   - Statistiques de la base\n";
        echo "  help                    - Afficher cette aide\n\n";
        echo "Exemples:\n";
        echo "  php remote_db_client.php info\n";
        echo "  php remote_db_client.php tables\n";
        echo "  php remote_db_client.php structure dim_dates\n";
        echo "  php remote_db_client.php sample dim_dates 5\n";
        echo "  php remote_db_client.php search dim_dates date_id 2024 10\n";
        echo "  php remote_db_client.php stats\n";
    }
}

// Exécution du script
if (php_sapi_name() === 'cli') {
    $client = new RemoteDBClient();
    
    $command = $argv[1] ?? 'help';
    
    switch ($command) {
        case 'info':
            $client->showInfo();
            break;
            
        case 'tables':
            $client->listTables();
            break;
            
        case 'structure':
            $table = $argv[2] ?? null;
            if ($table) {
                $client->showStructure($table);
            } else {
                echo "❌ Nom de table requis. Usage: php remote_db_client.php structure TABLE\n";
            }
            break;
            
        case 'sample':
            $table = $argv[2] ?? null;
            $limit = (int)($argv[3] ?? 10);
            $offset = (int)($argv[4] ?? 0);
            
            if ($table) {
                $client->showSample($table, $limit, $offset);
            } else {
                echo "❌ Nom de table requis. Usage: php remote_db_client.php sample TABLE [LIMIT] [OFFSET]\n";
            }
            break;
            
        case 'search':
            $table = $argv[2] ?? null;
            $column = $argv[3] ?? null;
            $term = $argv[4] ?? null;
            $limit = (int)($argv[5] ?? 50);
            
            if ($table && $column && $term) {
                $client->search($table, $column, $term, $limit);
            } else {
                echo "❌ Paramètres manquants. Usage: php remote_db_client.php search TABLE COLUMN TERM [LIMIT]\n";
            }
            break;
            
        case 'stats':
            $client->showStats();
            break;
            
        case 'help':
        default:
            $client->showHelp();
            break;
    }
} else {
    echo "Ce script doit être exécuté en ligne de commande.\n";
    echo "Usage: php remote_db_client.php [COMMANDE]\n";
}
?>
