<?php
/**
 * Gestionnaire des Tables Temporaires
 * Observatoire du Tourisme - Cantal Destination
 */

class TempTablesManager {
    private $config;
    private $db;
    private $log_file;
    private $hash_file;
    
    public function __construct() {
        // Inclusion de la configuration de base de données
        require_once __DIR__ . '/../config/app.php';
        
        $this->config = [
            'data_dir' => resolve_data_temp_dir(true),
            'files' => [
                'nuitees_departements.csv' => 'nuitees_departements_temp',
                'nuitees_pays.csv' => 'nuitees_pays_temp',
                'nuitees_geolife.csv' => 'nuitees_geolife_temp',
                'diurnes_departements.csv' => 'diurnes_departements_temp',
                'diurnes_pays.csv' => 'diurnes_pays_temp',
                'diurnes_geolife.csv' => 'diurnes_geolife_temp'
            ]
        ];
        
        $this->log_file = __DIR__ . '/../logs/temp_tables_update.log';
        $this->hash_file = __DIR__ . '/../data/temp_tables_hashes.json';
        $this->ensureDirectories();
    }
    
    private function ensureDirectories() {
        $dirs = [dirname($this->log_file), dirname($this->hash_file)];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    private function connectDB() {
        if ($this->db) return $this->db;
        
        try {
            $this->db = DatabaseConfig::getConnection();
            return $this->db;
        } catch (Exception $e) {
            throw new Exception("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message\n";
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Log aussi dans le système de sécurité si disponible
        if (class_exists('SecurityManager')) {
            SecurityManager::logSecurityEvent('TEMP_TABLES_OPERATION', ['message' => $message], 'INFO');
        }
    }
    
    /**
     * Met à jour automatiquement dim_dates avec les nouvelles dates des fichiers CSV
     */
    private function updateDimDates() {
        $this->log("🔄 Vérification des nouvelles dates dans les fichiers CSV...");
        
        try {
            $db = $this->connectDB();
            
            // Collecter toutes les dates des fichiers CSV
            $csv_files = [
                'frequentation_nuitee_fr.csv',
                'frequentation_nuitee_int.csv', 
                'frequentation_nuitee.csv',
                'frequentation_journee_fr.csv',
                'frequentation_journee_int.csv',
                'frequentation_journee.csv'
            ];
            
            $all_dates = [];
            
            foreach ($csv_files as $csv_file) {
                $full_path = data_temp_file($csv_file);
                if (file_exists($full_path)) {
                    $dates = $this->extractDatesFromCSV($full_path);
                    $all_dates = array_merge($all_dates, $dates);
                    $this->log("Dates extraites de " . basename($csv_file) . ": " . count($dates));
                }
            }
            
            $unique_dates = array_unique($all_dates);
            $this->log("Total dates uniques trouvées: " . count($unique_dates));
            
            if (empty($unique_dates)) {
                $this->log("Aucune date trouvée dans les fichiers CSV");
                return;
            }
            
            // Vérifier quelles dates existent déjà
            $existing_dates = [];
            $result = $db->query("SELECT date FROM dim_dates");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $existing_dates[] = $row['date'];
                }
            }
            
            // Trouver les nouvelles dates à ajouter
            $new_dates = array_diff($unique_dates, $existing_dates);
            
            if (empty($new_dates)) {
                $this->log("✅ Toutes les dates existent déjà dans dim_dates");
                return;
            }
            
            $this->log("📅 Nouvelles dates à ajouter: " . count($new_dates));
            
            // Insérer les nouvelles dates
            $insert_stmt = $db->prepare("
                INSERT INTO dim_dates (date, annee, mois, jour, jour_semaine, numero_semaine, trimestre, semestre, est_weekend, est_ferie, nom_jour, nom_mois, nom_trimestre)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $inserted_count = 0;
            $jours_semaine = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
            $mois_noms = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
            
            foreach ($new_dates as $date_str) {
                $date_obj = DateTime::createFromFormat('Y-m-d', $date_str);
                if ($date_obj) {
                    $annee = (int)$date_obj->format('Y');
                    $mois = (int)$date_obj->format('n');
                    $jour = (int)$date_obj->format('j');
                    $jour_semaine = (int)$date_obj->format('w'); // 0=dimanche, 1=lundi, etc.
                    $numero_semaine = (int)$date_obj->format('W');
                    $trimestre = ceil($mois / 3);
                    $semestre = $mois <= 6 ? 1 : 2;
                    $est_weekend = ($jour_semaine == 0 || $jour_semaine == 6) ? 1 : 0;
                    $est_ferie = 0; // Par défaut pas férié
                    $nom_jour = $jours_semaine[$jour_semaine];
                    $nom_mois = $mois_noms[$mois];
                    $nom_trimestre = "Q" . $trimestre;
                    
                    $insert_stmt->bind_param('siiiiiiiisss', 
                        $date_str, $annee, $mois, $jour, $jour_semaine, $numero_semaine, 
                        $trimestre, $semestre, $est_weekend, $est_ferie, $nom_jour, $nom_mois, $nom_trimestre
                    );
                    
                    if ($insert_stmt->execute()) {
                        $inserted_count++;
                    } else {
                        $this->log("❌ Erreur insertion date $date_str: " . $insert_stmt->error);
                    }
                }
            }
            
            $this->log("✅ dim_dates mis à jour: $inserted_count nouvelles dates ajoutées");
            
        } catch (Exception $e) {
            $this->log("❌ Erreur mise à jour dim_dates: " . $e->getMessage());
        }
    }
    
    /**
     * Extrait les dates uniques d'un fichier CSV
     */
    private function extractDatesFromCSV($filepath) {
        $dates = [];
        
        if (!file_exists($filepath)) {
            return $dates;
        }
        
        try {
            $content = file_get_contents($filepath);
            
            // Conversion ISO-8859-1 vers UTF-8 si nécessaire
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
            }
            
            $lines = explode("\n", $content);
            $header = null;
            $date_column_index = null;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                $row = str_getcsv($line, ';');
                
                if ($header === null) {
                    $header = $row;
                    // Trouver l'index de la colonne Date
                    $date_column_index = array_search('Date', $header);
                    continue;
                }
                
                if ($date_column_index !== false && isset($row[$date_column_index])) {
                    $date_str = trim($row[$date_column_index]);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) {
                        $dates[] = $date_str;
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->log("❌ Erreur lecture fichier $filepath: " . $e->getMessage());
        }
        
        return array_unique($dates);
    }
    
    public function checkAndUpdate($force = false) {
        $start_time = microtime(true);
        $this->log("=== DÉBUT VÉRIFICATION TABLES TEMPORAIRES ===");
        
        // Mise à jour automatique des dates avant traitement des données
        $this->updateDimDates();
        
        $results = [];
        $changes_detected = false;
        
        foreach ($this->config['files'] as $filename => $table_name) {
            $filepath = $this->config['data_dir'] . '/' . $filename;
            
            if (!file_exists($filepath)) {
                $this->log("⚠️ Fichier manquant: $filename");
                continue;
            }
            
            if ($force || $this->hasFileChanged($filename, $filepath)) {
                $changes_detected = true;
                $this->log("🔄 Changement détecté: $filename");
                $results[] = $this->updateTable($filename, $table_name);
            } else {
                $this->log("✓ Aucun changement: $filename");
                $results[] = ['table' => $table_name, 'status' => 'unchanged'];
            }
        }
        
        $duration = round(microtime(true) - $start_time, 2);
        
        if ($changes_detected) {
            $this->log("🎯 Mise à jour terminée en {$duration}s");
        } else {
            $this->log("✅ Aucune mise à jour nécessaire ({$duration}s)");
        }
        
        $this->log("=== FIN VÉRIFICATION ===\n");
        
        return [
            'duration' => $duration,
            'changes_detected' => $changes_detected,
            'results' => $results,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    private function hasFileChanged($filename, $filepath) {
        $current_hash = md5_file($filepath);
        $stored_hashes = $this->getFileHashes();
        
        if (!isset($stored_hashes[$filename]) || $stored_hashes[$filename] !== $current_hash) {
            $stored_hashes[$filename] = $current_hash;
            $this->saveFileHashes($stored_hashes);
            return true;
        }
        return false;
    }
    
    private function getFileHashes() {
        if (file_exists($this->hash_file)) {
            return json_decode(file_get_contents($this->hash_file), true) ?: [];
        }
        return [];
    }
    
    private function saveFileHashes($hashes) {
        file_put_contents($this->hash_file, json_encode($hashes, JSON_PRETTY_PRINT));
    }
    
    private function updateTable($filename, $table_name) {
        $filepath = $this->config['data_dir'] . '/' . $filename;
        
        $this->log("Traitement: $filename -> $table_name");
        
        try {
            // Lecture et traitement des données
            $data = $this->readCSV($filepath);
            $mapped_data = $this->applyMappings($data, $table_name);
            
            // Mise à jour de la table
            $deleted = $this->clearTable($table_name);
            $inserted = $this->insertData($table_name, $mapped_data);
            
            $this->log("✅ $table_name mis à jour: $deleted supprimées, $inserted insérées");
            
            return [
                'table' => $table_name,
                'deleted' => $deleted,
                'inserted' => $inserted,
                'status' => 'success'
            ];
            
        } catch (Exception $e) {
            $this->log("❌ Erreur $table_name: " . $e->getMessage());
            return [
                'table' => $table_name,
                'error' => $e->getMessage(),
                'status' => 'error'
            ];
        }
    }
    
    private function readCSV($filepath) {
        $data = [];
        
        if (!file_exists($filepath)) {
            throw new Exception("Fichier non trouvé: $filepath");
        }
        
        // Lecture avec détection automatique de l'encodage
        $content = file_get_contents($filepath);
        
        // Conversion ISO-8859-1 vers UTF-8 si nécessaire
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }
        
        $lines = explode("\n", $content);
        $header = null;
        
        foreach ($lines as $line_num => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Séparateur point-virgule
            $row = str_getcsv($line, ';');
            
            if ($header === null) {
                $header = $row;
                continue;
            }
            
            if (count($row) === count($header)) {
                $data[] = array_combine($header, $row);
            }
        }
        
        return $data;
    }
    
    private function applyMappings($data, $table_name) {
        $mapped_data = [];
        
        foreach ($data as $row) {
            $mapped_row = [];
            
            // Mappings communs
            $common_mappings = [
                'Année' => 'annee',
                'Mois' => 'mois', 
                'Nombre' => 'nombre',
                'Évolution' => 'evolution'
            ];
            
            // Mappings spécifiques par type de table
            if (strpos($table_name, 'departements') !== false) {
                $specific_mappings = [
                    'Département' => 'departement',
                    'Code département' => 'code_departement'
                ];
            } elseif (strpos($table_name, 'pays') !== false) {
                $specific_mappings = [
                    'Pays' => 'pays',
                    'Code pays' => 'code_pays'
                ];
            } elseif (strpos($table_name, 'geolife') !== false) {
                $specific_mappings = [
                    'Zone géographique' => 'zone_geographique',
                    'Code zone' => 'code_zone'
                ];
            } else {
                $specific_mappings = [];
            }
            
            $all_mappings = array_merge($common_mappings, $specific_mappings);
            
            foreach ($row as $original_key => $value) {
                $mapped_key = $all_mappings[$original_key] ?? strtolower(str_replace(' ', '_', $original_key));
                $mapped_row[$mapped_key] = $value;
            }
            
            $mapped_data[] = $mapped_row;
        }
        
        return $mapped_data;
    }
    
    private function clearTable($table_name) {
        $db = $this->connectDB();
        
        $stmt = $db->prepare("DELETE FROM `$table_name`");
        if (!$stmt->execute()) {
            throw new Exception("Erreur lors du vidage de $table_name: " . $db->error);
        }
        
        $deleted_count = $db->affected_rows;
        $this->log("Table $table_name vidée: $deleted_count lignes supprimées");
        
        return $deleted_count;
    }
    
    private function insertData($table_name, $data) {
        if (empty($data)) {
            $this->log("Aucune donnée à insérer dans $table_name");
            return 0;
        }
        
        $db = $this->connectDB();
        
        // Récupérer les colonnes de la première ligne
        $columns = array_keys($data[0]);
        $columns_str = '`' . implode('`, `', $columns) . '`';
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
        
        $sql = "INSERT INTO `$table_name` ($columns_str) VALUES ($placeholders)";
        $stmt = $db->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Erreur préparation requête $table_name: " . $db->error);
        }
        
        $inserted_count = 0;
        $types = str_repeat('s', count($columns));
        
        // Insertion par batch pour optimiser les performances
        foreach ($data as $row) {
            $values = array_values($row);
            $stmt->bind_param($types, ...$values);
            if ($stmt->execute()) {
                $inserted_count++;
            } else {
                $this->log("Erreur insertion: " . $stmt->error);
            }
        }
        
        $stmt->close();
        $this->log("Table $table_name: $inserted_count lignes insérées");
        
        return $inserted_count;
    }
    
    public function getStatus() {
        $status = [];
        
        foreach ($this->config['files'] as $filename => $table_name) {
            $filepath = $this->config['data_dir'] . '/' . $filename;
            
            $file_info = [
                'filename' => $filename,
                'table' => $table_name,
                'exists' => file_exists($filepath),
                'size' => file_exists($filepath) ? filesize($filepath) : 0,
                'modified' => file_exists($filepath) ? filemtime($filepath) : 0
            ];
            
            // Compter les enregistrements dans la table
            try {
                $db = $this->connectDB();
                $result = $db->query("SELECT COUNT(*) as count FROM `$table_name`");
                $file_info['records'] = $result ? $result->fetch_assoc()['count'] : 0;
            } catch (Exception $e) {
                $file_info['records'] = 'Erreur';
            }
            
            $status[] = $file_info;
        }
        
        return $status;
    }
    
    public function getRecentLogs($lines = 50) {
        if (!file_exists($this->log_file)) {
            return '';
        }
        
        $file = file($this->log_file);
        return implode('', array_slice($file, -$lines));
    }
    
    public function getSystemInfo() {
        return [
            'php_version' => phpversion(),
            'mysql_available' => extension_loaded('mysqli'),
            'data_dir_exists' => is_dir($this->config['data_dir']),
            'data_dir_writable' => is_writable(dirname($this->hash_file)),
            'logs_writable' => is_writable(dirname($this->log_file)),
            'total_files' => count($this->config['files']),
            'last_check' => file_exists($this->hash_file) ? filemtime($this->hash_file) : null
        ];
    }
}
?> 
