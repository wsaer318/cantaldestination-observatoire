<?php
/**
 * Cantal Destination - Automatisation Tables Temporaires
 * Mise à jour automatique des données touristiques
 * Nom du fichier : update_temp_tables.php
 */

// Configuration pour éviter le timeout sur gros fichiers CSV
set_time_limit(600); // 10 minutes
ini_set('memory_limit', '512M'); // Augmenter la mémoire si nécessaire

// Import de la configuration de base de données
require_once __DIR__ . '/../../config/app.php';

class TempTablesManager {
    private $config;
    private $db;
    private $log_file;
    private $hash_file;
    private $silentMode = false;
    private $table_logged = []; // Pour éviter les logs répétés
    private $debugMode = false; // Mode debug pour logs détaillés
    private $current_row_index = 0; // Index de la ligne actuelle pour les logs
    private $global_stats = [
        'diurnes_departements_processed' => 0,
        'diurnes_pays_processed' => 0,
        'diurnes_general_processed' => 0,
        'sejours_duree_departements_processed' => 0,
        'sejours_duree_pays_processed' => 0,
        'sejours_duree_general_processed' => 0,
        'nuitees_departements_processed' => 0,
        'nuitees_pays_processed' => 0,
        'nuitees_general_processed' => 0,
        'lieu_activite_processed' => 0,

        'diurnes_departements_mapped' => 0,
        'diurnes_pays_mapped' => 0,
        'diurnes_general_mapped' => 0,
        'sejours_duree_departements_mapped' => 0,
        'sejours_duree_pays_mapped' => 0,
        'sejours_duree_general_mapped' => 0,
        'nuitees_departements_mapped' => 0,
        'nuitees_pays_mapped' => 0,
        'nuitees_general_mapped' => 0,
        'lieu_activite_mapped' => 0,

        'diurnes_departements_errors' => 0,
        'diurnes_pays_errors' => 0,
        'diurnes_general_errors' => 0,
        'sejours_duree_departements_errors' => 0,
        'sejours_duree_pays_errors' => 0,
        'sejours_duree_general_errors' => 0,
        'nuitees_departements_errors' => 0,
        'nuitees_pays_errors' => 0,
        'nuitees_general_errors' => 0,
        'lieu_activite_errors' => 0
    ]; // Statistiques détaillées pour TOUS les types de fichiers
    
    // Cache pour éviter les requêtes répétitives
    private $zone_cache = [];
    private $provenance_cache = [];
    private $categorie_cache = [];
    private $departement_cache = [];
    private $pays_cache = [];
    private $commune_cache = [];
    private $epci_cache = [];
    private $duree_cache = [];
    private $international_pays_cache = [];
    
    public function __construct($silentMode = true) {
        // Mode silencieux par défaut pour optimiser les gros volumes
        $this->silentMode = $silentMode;

        // Utiliser la configuration centralisée au lieu de paramètres hardcodés
        $dbConfig = DatabaseConfig::getConfig();

        $this->config = [
            'db_host' => $dbConfig['host'] . ($dbConfig['port'] ? ':' . $dbConfig['port'] : ''),
            'db_name' => $dbConfig['database'],
            'db_user' => $dbConfig['username'],
            'db_pass' => $dbConfig['password'],
            'data_dir' => resolve_data_temp_dir(true),
            'files' => [
                'frequentation_nuitee_fr.csv' => 'fact_nuitees_departements_temp',
                'frequentation_nuitee_int.csv' => 'fact_nuitees_pays_temp',
                'frequentation_nuitee.csv' => 'fact_nuitees_temp',
                'frequentation_journee_fr.csv' => 'fact_diurnes_departements_temp',
                'frequentation_journee_int.csv' => 'fact_diurnes_pays_temp',
                'frequentation_journee.csv' => 'fact_diurnes_temp',
                'export_mobilite.csv' => 'fact_lieu_activite_soir_temp',
                'duree_sejour.csv' => 'fact_sejours_duree_temp',
                'duree_sejour_fr.csv' => 'fact_sejours_duree_departements_temp',
            'duree_sejour_int.csv' => 'fact_sejours_duree_pays_temp'
            ]
        ];

        $this->log_file = __DIR__ . '/../../logs/temp_tables_update.log';
        $this->hash_file = __DIR__ . '/../../data/file_hashes.json';

        // Vérification des chemins avant de continuer
        $this->validatePaths();

        $this->ensureDirectories();

        // Log de la configuration utilisée (sans mot de passe)
        $this->log("🔧 Configuration DB: " . $this->config['db_host'] . "/" . $this->config['db_name'] . " (user: " . $this->config['db_user'] . ")");
    }
    
    public function setSilentMode($silent) {
        $this->silentMode = $silent;
    }

    /**
     * Méthode de diagnostic pour tester la configuration
     */
    public function runDiagnostics() {
        $this->log("🔬 DIAGNOSTIC - Test de la configuration CSV Import");
        $this->log("==================================================");

        $results = [
            'environment' => DatabaseConfig::getCurrentEnvironment(),
            'paths' => [],
            'files' => [],
            'database' => [],
            'permissions' => []
        ];

        // Test des chemins
        $results['paths']['data_directory'] = [
            'configured' => $this->config['data_dir'],
            'exists' => is_dir($this->config['data_dir']),
            'readable' => is_readable($this->config['data_dir']),
            'writable' => is_writable($this->config['data_dir'])
        ];

        $results['paths']['log_file'] = [
            'configured' => $this->log_file,
            'directory_exists' => is_dir(dirname($this->log_file)),
            'directory_writable' => is_writable(dirname($this->log_file))
        ];

        // Test des fichiers
        foreach ($this->config['files'] as $filename => $table) {
            $filePath = $this->config['data_dir'] . '/' . $filename;
            $results['files'][$filename] = [
                'path' => $filePath,
                'exists' => file_exists($filePath),
                'readable' => file_exists($filePath) ? is_readable($filePath) : false,
                'size' => file_exists($filePath) ? filesize($filePath) : 0,
                'table' => $table
            ];
        }

        // Test de la base de données
        try {
            $db = $this->connectDB();
            $results['database']['connection'] = true;
            $results['database']['server_info'] = $db->getAttribute(PDO::ATTR_SERVER_VERSION);

            // Vérifier quelques tables clés
            $tables = ['fact_diurnes_departements_temp', 'dim_zones_observation', 'dim_provenances'];
            foreach ($tables as $table) {
                $stmt = $db->query("SHOW TABLES LIKE '$table'");
                $results['database']['tables'][$table] = ($stmt->rowCount() > 0);
            }

        } catch (Exception $e) {
            $results['database']['connection'] = false;
            $results['database']['error'] = $e->getMessage();
        }

        // Log des résultats
        $this->log("🔧 Environnement: " . $results['environment']);
        $this->log("📁 Répertoire données: " . ($results['paths']['data_directory']['exists'] ? '✅' : '❌'));
        $this->log("📝 Logs: " . ($results['paths']['log_file']['directory_writable'] ? '✅' : '❌'));
        $this->log("🗄️  Base de données: " . ($results['database']['connection'] ? '✅' : '❌'));

        $filesFound = 0;
        foreach ($results['files'] as $file => $info) {
            if ($info['exists']) {
                $filesFound++;
                $this->log("📄 $file: ✅ (" . number_format($info['size']) . " octets)");
            }
        }

        $this->log("📊 Fichiers trouvés: $filesFound/" . count($results['files']));

        return $results;
    }
    
    /**
     * Validation des chemins avant l'exécution
     */
    private function validatePaths() {
        $this->log("🔍 Validation des chemins...");

        // Vérifier le répertoire de données
        if (!is_dir($this->config['data_dir'])) {
            $this->log("❌ ERREUR: Répertoire de données introuvable: " . $this->config['data_dir']);
            $this->log("ℹ️  Chemin absolu: " . realpath($this->config['data_dir']));

            // Essayer de créer le répertoire
            if (!mkdir($this->config['data_dir'], 0755, true)) {
                throw new Exception("Impossible de créer le répertoire de données: " . $this->config['data_dir']);
            } else {
                $this->log("✅ Répertoire de données créé: " . $this->config['data_dir']);
            }
        } else {
            $this->log("✅ Répertoire de données accessible: " . $this->config['data_dir']);
        }

        // Vérifier quelques fichiers clés
        $keyFiles = ['frequentation_journee_fr.csv', 'frequentation_journee_int.csv'];
        foreach ($keyFiles as $file) {
            $filePath = $this->config['data_dir'] . '/' . $file;
            if (file_exists($filePath)) {
                $size = filesize($filePath);
                $this->log("✅ Fichier trouvé: $file (" . number_format($size) . " octets)");
            } else {
                $this->log("⚠️  Fichier manquant: $file (sera ignoré)");
            }
        }

        $this->log("🔍 Validation des chemins terminée");
    }

    private function ensureDirectories() {
        // Dossiers pour les fichiers de log et hash
        $dirs = [
            dirname($this->log_file),
            dirname($this->hash_file)
        ];

        // Ajouter le dossier pour le fichier de progression (utilisé par check_import_progress.php)
        $progress_file = __DIR__ . '/../../data/temp/temp_import_progress.json';
        $dirs[] = dirname($progress_file);

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    $this->log("❌ Impossible de créer le dossier : $dir");
                } else {
                    $this->log("📁 Dossier créé : $dir");
                }
            }
        }
    }

    /**
     * Recherche un fichier CSV par nom partout sous data_dir (sous-dossiers inclus)
     */
    private function findCsvPath($filename) {
        $root = rtrim($this->config['data_dir'], '/');
        $candidate = $root . '/' . $filename;
        if (file_exists($candidate)) {
            return $candidate;
        }
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile() && strcasecmp($fileInfo->getFilename(), $filename) === 0) {
                    return $fileInfo->getPathname();
                }
            }
        } catch (Exception $e) {
            // silencieux
        }
        return null;
    }
    
    private function connectDB() {
        if ($this->db) return $this->db;
        
        $this->db = new mysqli(
            $this->config['db_host'],
            $this->config['db_user'], 
            $this->config['db_pass'],
            $this->config['db_name']
        );
        
        if ($this->db->connect_error) {
            throw new Exception("Connexion échouée: " . $this->db->connect_error);
        }
        
        $this->db->set_charset("utf8");
        return $this->db;
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message\n";
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        if (!$this->silentMode) {
            echo $log_entry;
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
                $full_path = $this->findCsvPath($csv_file);
                if ($full_path && file_exists($full_path)) {
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
            
            // Vérifier quelles dates existent déjà (format YYYY-MM-DD)
            $existing_dates = [];
            $result = $db->query("SELECT DATE_FORMAT(date, '%Y-%m-%d') as date_formatted FROM dim_dates");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $existing_dates[] = $row['date_formatted'];
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
                INSERT INTO dim_dates (date, vacances_a, vacances_b, vacances_c, ferie, jour_semaine, mois, annee, trimestre, semaine)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $inserted_count = 0;
            $jours_semaine = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
            
            foreach ($new_dates as $date_str) {
                $date_obj = DateTime::createFromFormat('Y-m-d', $date_str);
                if ($date_obj) {
                    $annee = (int)$date_obj->format('Y');
                    $mois = (int)$date_obj->format('n');
                    $jour_semaine_num = (int)$date_obj->format('w'); // 0=dimanche, 1=lundi, etc.
                    $jour_semaine_nom = $jours_semaine[$jour_semaine_num];
                    $semaine = (int)$date_obj->format('W');
                    $trimestre = ceil($mois / 3);
                    
                    // Valeurs par défaut pour vacances et fériés
                    $vacances_a = 0;
                    $vacances_b = 0; 
                    $vacances_c = 0;
                    $ferie = 0;
                    
                    $insert_stmt->bind_param('siiiisiiii', 
                        $date_str, $vacances_a, $vacances_b, $vacances_c, $ferie, 
                        $jour_semaine_nom, $mois, $annee, $trimestre, $semaine
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
            
            // Détection automatique de l'encodage et conversion si nécessaire
            $detected_encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            
            if ($detected_encoding && $detected_encoding !== 'UTF-8') {
                $content = mb_convert_encoding($content, 'UTF-8', $detected_encoding);
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
        $this->log("=== DÉBUT MISE À JOUR DONNÉES TOURISTIQUES ===");
        
        // Mise à jour automatique des dates avant traitement des données
        $this->updateDimDates();
        
        $results = [];
        $changes_detected = false;

        // Créer toutes les tables temporaires nécessaires au préalable
        $this->log("🔧 Vérification/création de toutes les tables temporaires...");
        foreach ($this->config['files'] as $filename => $table_name) {
            try {
                $sample_data = $this->getSampleDataForTable($table_name);
                $this->ensureTableExists($table_name, $sample_data);
            } catch (Exception $e) {
                $this->log("⚠️ Impossible de créer la table $table_name: " . $e->getMessage());
            }
        }
        
        foreach ($this->config['files'] as $filename => $table_name) {
            $filepath = $this->findCsvPath($filename);
            
            if (!$filepath || !file_exists($filepath)) {
                $this->log("⚠️ Fichier manquant: $filename (table $table_name déjà créée)");
                $results[] = ['table' => $table_name, 'status' => 'no_file'];
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
        
        // Afficher le résumé détaillé des statistiques diurnes
        $this->logDiurnesSummary();
        
        $this->log("=== FIN MISE À JOUR ===\n");

        // Calculer les statistiques globales pour la réponse AJAX
        $global_stats = $this->calculateGlobalStats($results);
        $file_status = $this->getFileStatusSummary();
        $error_summary = $this->getErrorSummary($results);
        
        // Ajouter un diagnostic de déploiement si nécessaire
        $deployment_diagnostic = $this->generateDeploymentDiagnostic($global_stats, $file_status);

        // Récupérer un échantillon de la table fact_diurnes_departements_temp et l'ajouter aux logs
        $table_sample = $this->getTableSample('fact_diurnes_departements_temp');

        // Afficher l'échantillon dans les logs pour la console JavaScript
        if (isset($table_sample['sample_data']) && !empty($table_sample['sample_data'])) {
            $this->log("=== ÉCHANTILLON DE DONNÉES INSÉRÉES ===");
            $this->log("Table: " . $table_sample['table']);
            $this->log("Nombre de lignes d'échantillon: " . $table_sample['row_count']);
            $this->log("Colonnes: " . implode(', ', $table_sample['columns']));

            foreach ($table_sample['sample_data'] as $index => $row) {
                $this->log("Ligne " . ($index + 1) . ": " . json_encode($row, JSON_UNESCAPED_UNICODE));
            }
            $this->log("=== FIN ÉCHANTILLON ===");
        }

        return [
            'duration' => $duration,
            'changes_detected' => $changes_detected,
            'results' => $results,
            'global_stats' => $global_stats,
            'file_status' => $file_status,
            'error_summary' => $error_summary,
            'table_sample' => $table_sample,
            'performance' => [
                'avg_time_per_file' => count($results) > 0 ? round($duration / count($results), 2) : 0,
                'total_files_processed' => count($results),
                'total_inserted' => array_sum(array_column($results, 'inserted')),
                'total_deleted' => array_sum(array_column($results, 'deleted'))
            ],
            'deployment_diagnostic' => $deployment_diagnostic
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
        $filepath = $this->findCsvPath($filename);
        
        $this->log("Traitement: $filename -> $table_name");
        
        try {
            // CRÉER LA TABLE SI ELLE N'EXISTE PAS (toujours, même sans données)
            // Cette étape doit être faite AVANT de lire le CSV pour éviter les erreurs
            $sample_data = $this->getSampleDataForTable($table_name);
            $this->log("🔧 Vérification/création de la table $table_name...");
            $this->ensureTableExists($table_name, $sample_data);

            // Lecture et traitement des données
            $data = $this->readCSV($filepath);
            $mapped_data = $this->applyMappings($data, $table_name);
            
            // Mise à jour de la table (mode incrémental):
            // on supprime uniquement les dates présentes dans le CSV puis on insère
            $deleted = $this->deleteByDates($table_name, $mapped_data);
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
    
    /**
     * Supprime uniquement les lignes des dates présentes dans les données fournies
     * afin de permettre un import incrémental sans effacer l'historique complet.
     */
    private function deleteByDates($table_name, $data) {
        if (empty($data)) {
            return 0;
        }
        $db = $this->connectDB();
        $dates = [];
        foreach ($data as $row) {
            if (!empty($row['date'])) {
                $dates[] = $row['date'];
            }
        }
        $dates = array_values(array_unique($dates));
        if (empty($dates)) {
            return 0;
        }

        $totalDeleted = 0;
        $chunkSize = 500; // éviter des requêtes trop longues
        $chunks = array_chunk($dates, $chunkSize);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $db->prepare("DELETE FROM `$table_name` WHERE `date` IN ($placeholders)");
            if ($stmt === false) {
                $this->log("❌ Préparation DELETE échouée pour $table_name: " . $db->error);
                continue;
            }
            // lier dynamiquement les paramètres
            $types = str_repeat('s', count($chunk));
            $stmt->bind_param($types, ...$chunk);
            if ($stmt->execute()) {
                $totalDeleted += $stmt->affected_rows;
            } else {
                $this->log("❌ Exécution DELETE échouée pour $table_name: " . $stmt->error);
            }
            $stmt->close();
        }
        if ($totalDeleted > 0) {
            $this->log("$table_name: $totalDeleted lignes supprimées (dates présentes dans le CSV)");
        }
        return $totalDeleted;
    }

    private function readCSV($filepath) {
        $data = [];
        
        if (!file_exists($filepath)) {
            throw new Exception("Fichier non trouvé: $filepath");
        }
        
        // Lecture du fichier
        $content = file_get_contents($filepath);
        
        // Détection automatique de l'encodage et conversion si nécessaire
        $detected_encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        
        if ($detected_encoding && $detected_encoding !== 'UTF-8') {
            $this->log("Conversion encodage détecté: $detected_encoding -> UTF-8");
            $content = mb_convert_encoding($content, 'UTF-8', $detected_encoding);
        }
        
        $lines = explode("\n", $content);
        $header = null;
        $valid_rows = 0;
        $invalid_rows = 0;
        
        foreach ($lines as $line_num => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Séparateur point-virgule
            $row = str_getcsv($line, ';');
            
            if ($header === null) {
                $header = $row;
                $this->log("En-têtes détectés: " . implode(', ', $header));
                continue;
            }
            
            if (count($row) === count($header)) {
                $data[] = array_combine($header, $row);
                $valid_rows++;
            } else {
                $invalid_rows++;
                if ($invalid_rows <= 5) { // Log seulement les 5 premières erreurs
                    $this->log("⚠️ Ligne $line_num: " . count($row) . " colonnes au lieu de " . count($header));
                }
            }
        }
        
        $this->log("Lecture CSV terminée: $valid_rows lignes valides, $invalid_rows lignes invalides");
        return $data;
    }
    
    private function applyMappings($data, $table_name) {
        $this->log("🚀 START applyMappings for table: $table_name");
        $mapped_data = [];
        $db = $this->connectDB();
        $total_rows = count($data);
        $mapped_count = 0;
        $rejected_count = 0;
        $rejection_reasons = [];
        $this->current_row_index = 0;
        
        foreach ($data as $row_index => $row) {
            $this->current_row_index = $row_index + 1; // Index basé sur 1 pour l'utilisateur
            $mapped_row = [];
            $missing_fields = [];
            
            // Mapping de la date et calcul du jour de la semaine
            if (isset($row['Date'])) {
                $mapped_row['date'] = $row['Date'];

                // Calculer le jour de la semaine pour les tables lieu d'activité
                if (strpos($table_name, 'fact_lieu_activite_soir') !== false) {
                    $date_obj = DateTime::createFromFormat('Y-m-d', $row['Date']);
                    if ($date_obj) {
                        $jours_semaine = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                        $jour_semaine_num = (int)$date_obj->format('w');
                        $mapped_row['jour_semaine'] = $jours_semaine[$jour_semaine_num];
                    } else {
                        $missing_fields[] = 'Date (format invalide pour calcul jour_semaine)';
                    }
                }
            } else {
                $missing_fields[] = 'Date';
            }
            
            // Mapping de la zone d'observation
            if (isset($row['ZoneObservation'])) {
                $zone_id = $this->getZoneId($db, $row['ZoneObservation']);
                if ($zone_id) {
                    $mapped_row['id_zone'] = $zone_id;
                } else {
                    $missing_fields[] = 'ZoneObservation (ID non trouvé pour: ' . $row['ZoneObservation'] . ')';
                }
            } else {
                $missing_fields[] = 'ZoneObservation';
            }
            
            // Mapping de la provenance
            if (isset($row['Provenance'])) {
                $provenance_id = $this->getProvenanceId($db, $row['Provenance']);
                if ($provenance_id) {
                    $mapped_row['id_provenance'] = $provenance_id;
                } else {
                    $missing_fields[] = 'Provenance (ID non trouvé pour: ' . $row['Provenance'] . ')';
                }
            } else {
                $missing_fields[] = 'Provenance';
            }
            
            // Mapping de la catégorie visiteur
            if (isset($row['CategorieVisiteur'])) {
                $categorie_id = $this->getCategorieId($db, $row['CategorieVisiteur']);
                if ($categorie_id) {
                    $mapped_row['id_categorie'] = $categorie_id;
                } else {
                    $missing_fields[] = 'CategorieVisiteur (ID non trouvé pour: ' . $row['CategorieVisiteur'] . ')';
                }
            } else {
                $missing_fields[] = 'CategorieVisiteur';
            }
            
            // Mapping du volume
            if (isset($row['Volume'])) {
                $mapped_row['volume'] = (int)$row['Volume'];
            } else {
                $mapped_row['volume'] = 0; // Valeur par défaut
            }
            
            // Mappings spécifiques par type de table
            // Log seulement au début du traitement d'une table
            if (!isset($this->table_logged[$table_name])) {
                $this->log("🔀 Processing table: $table_name");
                $this->table_logged[$table_name] = true;
            }

            // CONDITIONS SPÉCIFIQUES EN PREMIER (plus précises)
            if (strpos($table_name, 'fact_diurnes_departements') !== false) {
                $this->global_stats['diurnes_departements_processed']++;

                // Log synthétique : seulement tous les 5000 enregistrements (pas de détails individuels)
                if ($this->global_stats['diurnes_departements_processed'] % 5000 === 1 && !$this->silentMode) {
                    $this->log("🏙️ [DIURNES DÉPARTEMENTS] {$this->global_stats['diurnes_departements_processed']} lignes traitées");
                }

                // Tables diurnes départements - mapping NomDepartement vers id_departement
                if (isset($row['NomDepartement'])) {
                    $dept_id = $this->getDepartementId($db, $row['NomDepartement']);
                    if ($dept_id) {
                        $mapped_row['id_departement'] = $dept_id;
                        $this->global_stats['diurnes_departements_mapped']++;
                        // Log de progression tous les 2000 mappings réussis
                        if (!$this->silentMode && $this->global_stats['diurnes_departements_mapped'] % 2000 === 0) {
                            $this->log("📈 [DIURNES DÉPARTEMENTS] {$this->global_stats['diurnes_departements_mapped']} mappings réussis");
                        }
                    } else {
                        $this->global_stats['diurnes_departements_errors']++;
                        // Log détaillé des erreurs de mapping département
                        $this->log("❌ [REJET DÉPARTEMENT] Ligne {$this->current_row_index} | Zone: " .
                                  ($row['ZoneObservation'] ?? 'N/A') . " | Département: {$row['NomDepartement']} | Introuvable en DB");
                        $missing_fields[] = 'NomDepartement (ID département non trouvé pour: ' . $row['NomDepartement'] . ')';
                    }
                } else {
                    $this->global_stats['diurnes_departements_errors']++;
                    $this->log("❌ [REJET COLONNE] Ligne {$this->current_row_index} | Zone: " .
                              ($row['ZoneObservation'] ?? 'N/A') . " | Colonne 'NomDepartement' manquante ou vide");
                    $missing_fields[] = 'NomDepartement';
                }
            } elseif (strpos($table_name, 'fact_diurnes_pays') !== false) {
                $this->global_stats['diurnes_pays_processed']++;

                // Log synthétique : seulement tous les 3000 enregistrements
                if ($this->global_stats['diurnes_pays_processed'] % 3000 === 1 && !$this->silentMode) {
                    $this->log("🌍 [DIURNES PAYS] {$this->global_stats['diurnes_pays_processed']} lignes traitées");
                }

                // Tables diurnes internationales - mapping Pays vers id_pays
                if (isset($row['Pays'])) {
                    $pays_id = $this->getPaysId($db, $row['Pays']);
                    if ($pays_id) {
                        $mapped_row['id_pays'] = $pays_id;
                        $this->global_stats['diurnes_pays_mapped']++;
                        // Log de progression tous les 1500 mappings réussis
                        if (!$this->silentMode && $this->global_stats['diurnes_pays_mapped'] % 1500 === 0) {
                            $this->log("📈 [DIURNES PAYS] {$this->global_stats['diurnes_pays_mapped']} mappings réussis");
                        }
                    } else {
                        $this->global_stats['diurnes_pays_errors']++;
                        // Log détaillé des erreurs de mapping pays
                        $this->log("❌ [REJET PAYS] Ligne {$this->current_row_index} | Zone: " .
                                  ($row['ZoneObservation'] ?? 'N/A') . " | Pays: {$row['Pays']} | Introuvable en DB");
                        $missing_fields[] = 'Pays (ID pays non trouvé pour: ' . $row['Pays'] . ')';
                    }
                } else {
                    $this->global_stats['diurnes_pays_errors']++;
                    $this->log("❌ [REJET COLONNE] Ligne {$this->current_row_index} | Zone: " .
                              ($row['ZoneObservation'] ?? 'N/A') . " | Colonne 'Pays' manquante ou vide");
                    $missing_fields[] = 'Pays';
                }
            } elseif (strpos($table_name, 'fact_sejours_duree_departements') !== false) {
                $this->global_stats['sejours_duree_departements_processed']++;
                // Log pour les séjours durée départements
                if ($this->global_stats['sejours_duree_departements_processed'] % 5000 === 1 && !$this->silentMode) {
                    $this->log("🏛️ [SÉJOURS DURÉE DÉPARTEMENTS] {$this->global_stats['sejours_duree_departements_processed']} lignes traitées");
                }
                // Tables départements françaises - mapping NomDepartement vers id_departement
                if (isset($row['NomDepartement'])) {
                    $dept_id = $this->getDepartementId($db, $row['NomDepartement']);
                    if ($dept_id) {
                        $mapped_row['id_departement'] = $dept_id;
                        $this->global_stats['sejours_duree_departements_mapped']++;
                    } else {
                        $this->global_stats['sejours_duree_departements_errors']++;
                        $missing_fields[] = 'NomDepartement (ID département non trouvé pour: ' . $row['NomDepartement'] . ')';
                    }
                } else {
                    $this->global_stats['sejours_duree_departements_errors']++;
                    $missing_fields[] = 'NomDepartement';
                }

                // Tables durée de séjour - mapping DureeSejour vers id_duree
                if (isset($row['DureeSejour'])) {
                    $duree_id = $this->getDureeId($db, $row['DureeSejour']);
                    if ($duree_id) {
                        $mapped_row['id_duree'] = $duree_id;
                    } else {
                        $missing_fields[] = 'DureeSejour (ID durée non trouvé pour: ' . $row['DureeSejour'] . ')';
                    }
                } else {
                    $missing_fields[] = 'DureeSejour';
                }
            } elseif (strpos($table_name, 'fact_sejours_duree_pays') !== false) {
                $this->global_stats['sejours_duree_pays_processed']++;
                // Log pour les séjours durée pays
                if ($this->global_stats['sejours_duree_pays_processed'] % 3000 === 1 && !$this->silentMode) {
                    $this->log("🌐 [SÉJOURS DURÉE PAYS] {$this->global_stats['sejours_duree_pays_processed']} lignes traitées");
                }
                // Tables internationales - mapping Pays vers id_pays
                if (isset($row['Pays'])) {
                    $pays_id = $this->getInternationalPaysId($db, $row['Pays']);
                    if ($pays_id) {
                        $mapped_row['id_pays'] = $pays_id;
                        $this->global_stats['sejours_duree_pays_mapped']++;
                    } else {
                        $this->global_stats['sejours_duree_pays_errors']++;
                        $missing_fields[] = 'Pays (ID pays non trouvé pour: ' . $row['Pays'] . ')';
                    }
                } else {
                    $this->global_stats['sejours_duree_pays_errors']++;
                    $missing_fields[] = 'Pays';
                }

                // Tables durée de séjour - mapping DureeSejour vers id_duree
                if (isset($row['DureeSejour'])) {
                    $duree_id = $this->getDureeId($db, $row['DureeSejour']);
                    if ($duree_id) {
                        $mapped_row['id_duree'] = $duree_id;
                    } else {
                        $missing_fields[] = 'DureeSejour (ID durée non trouvé pour: ' . $row['DureeSejour'] . ')';
                    }
                } else {
                    $missing_fields[] = 'DureeSejour';
                }

            // CONDITIONS GÉNÉRIQUES EN DERNIER (moins précises)
            } elseif (strpos($table_name, 'departements') !== false) {
                // $this->log("🎯 ENTERED departements condition (générique)");
                // Tables départements
                if (isset($row['NomDepartement'])) {
                    $dept_id = $this->getDepartementId($db, $row['NomDepartement']);
                    if ($dept_id) {
                        $mapped_row['id_departement'] = $dept_id;
                    } else {
                        $missing_fields[] = 'NomDepartement (ID non trouvé pour: ' . $row['NomDepartement'] . ')';
                    }
                } else {
                    $missing_fields[] = 'NomDepartement';
                }
            } elseif (strpos($table_name, 'pays') !== false) {
                // $this->log("🎯 ENTERED pays condition (générique)");
                // Tables pays
                if (isset($row['Pays'])) {
                    $pays_id = $this->getPaysId($db, $row['Pays']);
                    if ($pays_id) {
                        $mapped_row['id_pays'] = $pays_id;
                    } else {
                        $missing_fields[] = 'Pays (ID non trouvé pour: ' . $row['Pays'] . ')';
                    }
                } else {
                    $missing_fields[] = 'Pays';
                }
            } elseif (strpos($table_name, 'geolife') !== false) {
                // $this->log("🎯 ENTERED geolife condition");
                // Tables geolife - pour l'instant on skip car pas de données
                // TODO: Implémenter le mapping geolife si nécessaire
            } elseif (strpos($table_name, 'fact_lieu_activite_soir') !== false) {
                // $this->log("🎯 ENTERED fact_lieu_activite_soir condition");
                // Tables lieu d'activité soir - mapping ZoneMobilite vers id_commune
                if (isset($row['ZoneMobilite'])) {
                    $commune_id = $this->getCommuneId($db, $row['ZoneMobilite']);
                    if ($commune_id) {
                        $mapped_row['id_commune'] = $commune_id;
                        // Récupérer l'id_epci basé sur l'id_commune depuis les données existantes
                        $mapped_row['id_epci'] = $this->getEpciId($db, $commune_id);
                    } else {
                        $missing_fields[] = 'ZoneMobilite (ID commune non trouvé pour: ' . $row['ZoneMobilite'] . ')';
                    }
                } else {
                    $missing_fields[] = 'ZoneMobilite';
                }
            } elseif (strpos($table_name, 'fact_sejours_duree') !== false) {
                $this->log("🔍 Processing: $table_name");
                // Tables durée de séjour - mapping DureeSejour vers id_duree
                if (isset($row['DureeSejour'])) {
                    $duree_id = $this->getDureeId($db, $row['DureeSejour']);
                    if ($duree_id) {
                        $mapped_row['id_duree'] = $duree_id;
                    } else {
                        $missing_fields[] = 'DureeSejour (ID durée non trouvé pour: ' . $row['DureeSejour'] . ')';
                    }
                } else {
                    $missing_fields[] = 'DureeSejour';
                }
            }
            
            // Validation des champs obligatoires (logs synthétiques)
            if (isset($mapped_row['date']) && isset($mapped_row['id_zone']) && 
                isset($mapped_row['id_provenance']) && isset($mapped_row['id_categorie']) &&
                $mapped_row['date'] !== null && $mapped_row['id_zone'] !== null &&
                $mapped_row['id_provenance'] !== null && $mapped_row['id_categorie'] !== null) {

                // Conditions spécifiques selon le type de table
                $isValid = true;

                if (strpos($table_name, 'fact_sejours_duree_departements') !== false) {
                    // Pour les tables départements, id_duree et id_departement sont obligatoires
                    if (!isset($mapped_row['id_duree']) || $mapped_row['id_duree'] === null) {
                        $isValid = false;
                    }
                    if (!isset($mapped_row['id_departement']) || $mapped_row['id_departement'] === null) {
                        $isValid = false;
                    }
                } elseif (strpos($table_name, 'fact_sejours_duree_pays') !== false) {
                    // Pour les tables internationales, id_duree et id_pays sont obligatoires
                    if (!isset($mapped_row['id_duree']) || $mapped_row['id_duree'] === null) {
                        $isValid = false;
                    }
                    if (!isset($mapped_row['id_pays']) || $mapped_row['id_pays'] === null) {
                        $isValid = false;
                    }
                } else                if (strpos($table_name, 'fact_diurnes_departements') !== false) {
                    // Pour les tables diurnes départements, id_departement est obligatoire
                    if (!isset($mapped_row['id_departement']) || $mapped_row['id_departement'] === null) {
                        $isValid = false;
                        $this->global_stats['diurnes_departements_errors']++;
                        // Log seulement en mode debug et pour les premières erreurs
                        if ($this->debugMode && !$this->silentMode && $this->global_stats['diurnes_departements_errors'] <= 1) {
                            $this->log("❌ [VALIDATION] Ligne rejetée: département manquant");
                        }
                    }
                } elseif (strpos($table_name, 'fact_diurnes_pays') !== false) {
                    // Pour les tables diurnes internationales, id_pays est obligatoire
                    if (!isset($mapped_row['id_pays']) || $mapped_row['id_pays'] === null) {
                        $isValid = false;
                        $this->global_stats['diurnes_pays_errors']++;
                        // Log seulement en mode debug et pour les premières erreurs
                        if ($this->debugMode && !$this->silentMode && $this->global_stats['diurnes_pays_errors'] <= 1) {
                            $this->log("❌ [VALIDATION] Ligne rejetée: pays manquant");
                        }
                    }
                } elseif (strpos($table_name, 'fact_sejours_duree') !== false) {
                    // Pour les tables générales de durée de séjour, seulement id_duree est obligatoire
                    if (!isset($mapped_row['id_duree']) || $mapped_row['id_duree'] === null) {
                        $isValid = false;
                    }
                } elseif (strpos($table_name, 'fact_lieu_activite_soir') !== false) {
                    // Pour les tables lieu d'activité, id_commune et id_epci sont obligatoires
                    if (!isset($mapped_row['id_commune']) || $mapped_row['id_commune'] === null) {
                        $isValid = false;
                    }
                    if (isset($mapped_row['id_epci']) && $mapped_row['id_epci'] === null) {
                        $isValid = false;
                    }
                }

                if ($isValid) {
                $mapped_data[] = $mapped_row;
                $mapped_count++;
                } else {
                    $rejected_count++;

                    // Log détaillé de chaque rejet avec les raisons
                    $rejection_details = "❌ [REJET] $table_name - Ligne " . ($this->current_row_index ?? '?');
                    $rejection_details .= " | Champs manquants: " . implode(', ', $missing_fields);
                    $rejection_details .= " | Zone: " . ($row['ZoneObservation'] ?? 'N/A');
                    $rejection_details .= " | Volume: " . ($row['Volume'] ?? '0');

                    // Log toujours les rejets, mais avec un format compact pour éviter la surcharge
                    $this->log($rejection_details);
                }
            } else {
                $rejected_count++;
                $reason = implode(', ', $missing_fields);
                if (!isset($rejection_reasons[$reason])) {
                    $rejection_reasons[$reason] = 0;
                }
                $rejection_reasons[$reason]++;
                
                // Log les 5 premières rejets pour diagnostic
                if ($rejected_count <= 5) {
                    $this->log("❌ Ligne rejetée $table_name: " . $reason);
                }
            }
        }
        
        // Log statistiques détaillées avec résumé des rejets
        if (!$this->silentMode) {
            $success_rate = $total_rows > 0 ? round(($mapped_count / $total_rows) * 100, 1) : 0;
            $this->log("📊 [$table_name] $mapped_count/$total_rows mappées ($success_rate%)");

            // Toujours log les statistiques de rejet
        if ($rejected_count > 0) {
                $this->log("⚠️ [$table_name] $rejected_count lignes rejetées");

                // Log détaillé des raisons de rejet
                foreach ($rejection_reasons as $reason => $count) {
                    $percentage = round(($count / $rejected_count) * 100, 1);
                    $this->log("   └─ $reason: $count lignes ($percentage%)");
                }
            } else {
                $this->log("✅ [$table_name] Aucune ligne rejetée - traitement parfait!");
            }
        }
        
        return $mapped_data;
    }

    /**
     * Affiche un résumé détaillé des statistiques d'alimentation des tables diurnes
     */
    /**
     * Calcule les statistiques globales RÉELLES pour la réponse AJAX
     */
    private function calculateGlobalStats($results) {
        $stats = [
            'total_files_expected' => count($this->config['files']),
            'total_files_present' => 0,
            'total_files_processed' => 0,
            'total_processed' => 0,
            'total_mapped' => 0,
            'total_errors' => 0,
            'total_inserted' => 0,
            'total_deleted' => 0,
            'success_rate' => 0,
            'files_processed' => [],
            'files_missing' => []
        ];

        // Analyser les résultats réels
        foreach ($results as $result) {
            if (isset($result['inserted'])) $stats['total_inserted'] += $result['inserted'];
            if (isset($result['deleted'])) $stats['total_deleted'] += $result['deleted'];
            if ($result['status'] !== 'no_file') {
                $stats['total_files_processed']++;
                $stats['files_processed'][] = $result['table'];
            }
        }

        // Vérifier les fichiers présents vs attendus
        foreach ($this->config['files'] as $filename => $table_name) {
            $filepath = $this->findCsvPath($filename);
            if ($filepath && file_exists($filepath)) {
                $stats['total_files_present']++;
            } else {
                $stats['files_missing'][] = $filename;
            }
        }

        // Statistiques réelles du traitement (TOUS LES TYPES DE FICHIERS)
        $stats['total_processed'] = $this->global_stats['diurnes_departements_processed'] +
                                   $this->global_stats['diurnes_pays_processed'] +
                                   $this->global_stats['diurnes_general_processed'] +
                                   $this->global_stats['sejours_duree_departements_processed'] +
                                   $this->global_stats['sejours_duree_pays_processed'] +
                                   $this->global_stats['sejours_duree_general_processed'] +
                                   $this->global_stats['nuitees_departements_processed'] +
                                   $this->global_stats['nuitees_pays_processed'] +
                                   $this->global_stats['nuitees_general_processed'] +
                                   $this->global_stats['lieu_activite_processed'];

        $stats['total_mapped'] = $this->global_stats['diurnes_departements_mapped'] +
                                $this->global_stats['diurnes_pays_mapped'] +
                                $this->global_stats['diurnes_general_mapped'] +
                                $this->global_stats['sejours_duree_departements_mapped'] +
                                $this->global_stats['sejours_duree_pays_mapped'] +
                                $this->global_stats['sejours_duree_general_mapped'] +
                                $this->global_stats['nuitees_departements_mapped'] +
                                $this->global_stats['nuitees_pays_mapped'] +
                                $this->global_stats['nuitees_general_mapped'] +
                                $this->global_stats['lieu_activite_mapped'];

        $stats['total_errors'] = $this->global_stats['diurnes_departements_errors'] +
                                $this->global_stats['diurnes_pays_errors'] +
                                $this->global_stats['diurnes_general_errors'] +
                                $this->global_stats['sejours_duree_departements_errors'] +
                                $this->global_stats['sejours_duree_pays_errors'] +
                                $this->global_stats['sejours_duree_general_errors'] +
                                $this->global_stats['nuitees_departements_errors'] +
                                $this->global_stats['nuitees_pays_errors'] +
                                $this->global_stats['nuitees_general_errors'] +
                                $this->global_stats['lieu_activite_errors'];

        $stats['success_rate'] = $stats['total_processed'] > 0 ?
            round(($stats['total_mapped'] / $stats['total_processed']) * 100, 1) : 0;

        return $stats;
    }

    /**
     * Génère un résumé détaillé de l'état des fichiers
     */
    private function getFileStatusSummary() {
        $file_status = [];

        foreach ($this->config['files'] as $filename => $table_name) {
            $filepath = $this->findCsvPath($filename);

            if (!$filepath || !file_exists($filepath)) {
                $file_status[$filename] = [
                    'status' => 'missing',
                    'table' => $table_name,
                    'size' => 0,
                    'modified' => null,
                    'lines' => 0
                ];
            } else {
                $size = filesize($filepath);
                $modified = filemtime($filepath);
                $lines = count(file($filepath));

                $file_status[$filename] = [
                    'status' => 'present',
                    'table' => $table_name,
                    'size' => $size,
                    'modified' => date('Y-m-d H:i:s', $modified),
                    'lines' => $lines - 1 // -1 pour l'en-tête
                ];
            }
        }

        return $file_status;
    }

    /**
     * Génère un résumé RÉEL des erreurs par analyse des logs
     */
    private function getErrorSummary($results) {
        $error_summary = [
            'total_errors' => 0,
            'errors_by_type' => [],
            'errors_by_table' => [],
            'sample_errors' => [],
            'missing_files' => [],
            'processing_issues' => []
        ];

        // Vérifier d'abord les fichiers manquants (cause principale d'erreurs)
        foreach ($this->config['files'] as $filename => $table_name) {
            $filepath = $this->findCsvPath($filename);
            if (!$filepath || !file_exists($filepath)) {
                $error_summary['missing_files'][] = [
                    'file' => $filename,
                    'table' => $table_name
                ];
            }
        }

        // Analyser les logs pour les vraies erreurs de traitement
        if (file_exists($this->log_file)) {
            $log_content = file_get_contents($this->log_file);
            $lines = explode("\n", $log_content);

            $error_patterns = [
                'ZoneObservation.*non trouvé' => 'Zone inconnue',
                'Provenance.*non trouvé' => 'Provenance inconnue',
                'Pays.*non trouvé' => 'Pays inconnu',
                'NomDepartement.*non trouvé' => 'Département inconnu',
                'CategorieVisiteur.*non trouvé' => 'Catégorie inconnue',
                'DureeSejour.*non trouvé' => 'Durée inconnue',
                'Colonne.*manquante' => 'Colonne manquante',
                'fichier introuvable' => 'Fichier manquant'
            ];

            foreach ($lines as $line) {
                if (strpos($line, '❌') !== false || strpos($line, '⚠️') !== false) {
                    $error_summary['total_errors']++;

                    // Classifier l'erreur
                    foreach ($error_patterns as $pattern => $description) {
                        if (preg_match("/$pattern/i", $line)) {
                            if (!isset($error_summary['errors_by_type'][$description])) {
                                $error_summary['errors_by_type'][$description] = 0;
                            }
                            $error_summary['errors_by_type'][$description]++;
                            break;
                        }
                    }

                    // Garder quelques exemples d'erreurs
                    if (count($error_summary['sample_errors']) < 5) {
                        $error_summary['sample_errors'][] = trim($line);
                    }
                }
            }
        }

        // Ajouter des informations sur les problèmes de traitement
        if (count($error_summary['missing_files']) > 0) {
            $error_summary['processing_issues'][] = count($error_summary['missing_files']) . " fichier(s) CSV manquant(s)";
        }

        if ($error_summary['total_errors'] === 0 && count($error_summary['missing_files']) === 0) {
            $error_summary['processing_issues'][] = "Aucun problème détecté";
        }

        return $error_summary;
    }

    /**
     * Récupère les 5 premières lignes d'une table temporaire
     */
    private function getTableSample($table_name) {
        $db = $this->connectDB();

        try {
            $query = "SELECT * FROM `$table_name` LIMIT 5";
            $result = $db->query($query);

            if (!$result) {
                return ['error' => 'Erreur lors de la requête: ' . $db->error];
            }

            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }

            return [
                'table' => $table_name,
                'row_count' => count($rows),
                'columns' => !empty($rows) ? array_keys($rows[0]) : [],
                'sample_data' => $rows
            ];

        } catch (Exception $e) {
            return ['error' => 'Exception: ' . $e->getMessage()];
        }
    }

    /**
     * Génère un diagnostic de déploiement pour identifier les problèmes
     */
    private function generateDeploymentDiagnostic($global_stats, $file_status) {
        $diagnostic = [
            'status' => 'unknown',
            'severity' => 'low',
            'issues' => [],
            'recommendations' => [],
            'deployment_ready' => false
        ];

        $missing_files = 0;
        $total_expected = $global_stats['total_files_expected'];
        $total_present = $global_stats['total_files_present'];

        // Compter les fichiers manquants
        foreach ($file_status as $filename => $info) {
            if ($info['status'] === 'missing') {
                $missing_files++;
            }
        }

        // Analyser la situation
        if ($total_present === 0) {
            $diagnostic['status'] = 'critical';
            $diagnostic['severity'] = 'critical';
            $diagnostic['issues'][] = 'Aucun fichier CSV trouvé';
            $diagnostic['recommendations'][] = 'Vérifier le répertoire data_dir';
            $diagnostic['recommendations'][] = 'Uploader tous les fichiers CSV';
        } elseif ($missing_files > 0) {
            if ($missing_files > $total_expected / 2) {
                $diagnostic['status'] = 'warning';
                $diagnostic['severity'] = 'high';
                $diagnostic['issues'][] = "$missing_files fichiers manquants sur $total_expected";
                $diagnostic['recommendations'][] = 'Uploader les fichiers manquants';
            } else {
                $diagnostic['status'] = 'partial';
                $diagnostic['severity'] = 'medium';
                $diagnostic['issues'][] = "$missing_files fichiers manquants sur $total_expected";
                $diagnostic['recommendations'][] = 'Uploader les fichiers manquants pour traitement complet';
            }
        } elseif ($global_stats['success_rate'] < 50) {
            $diagnostic['status'] = 'processing_error';
            $diagnostic['severity'] = 'medium';
            $diagnostic['issues'][] = 'Taux de succès faible (' . $global_stats['success_rate'] . '%)';
            $diagnostic['recommendations'][] = 'Vérifier la qualité des données CSV';
            $diagnostic['recommendations'][] = 'Consulter les logs d\'erreurs détaillés';
        } elseif ($global_stats['success_rate'] >= 90) {
            $diagnostic['status'] = 'healthy';
            $diagnostic['severity'] = 'low';
            $diagnostic['deployment_ready'] = true;
        } else {
            $diagnostic['status'] = 'operational';
            $diagnostic['severity'] = 'low';
            $diagnostic['deployment_ready'] = true;
        }

        // Recommandations spécifiques selon les fichiers manquants
        if ($missing_files > 0) {
            $missing_list = [];
            foreach ($file_status as $filename => $info) {
                if ($info['status'] === 'missing') {
                    $missing_list[] = $filename;
                }
            }

            if (!empty($missing_list)) {
                $diagnostic['missing_files'] = $missing_list;
                $diagnostic['recommendations'][] = 'Fichiers à uploader: ' . implode(', ', $missing_list);
            }
        }

        // Vérification de la taille totale (doit être significative)
        $total_size_mb = 0;
        foreach ($file_status as $filename => $info) {
            if ($info['status'] === 'present') {
                $total_size_mb += $info['size'] / (1024 * 1024);
            }
        }

        if ($total_size_mb < 0.1 && $total_present > 0) {
            $diagnostic['issues'][] = 'Taille totale des fichiers anormalement petite';
            $diagnostic['recommendations'][] = 'Vérifier que les fichiers CSV contiennent des données';
        }

        return $diagnostic;
    }

    private function logDiurnesSummary() {
        // Calculer les totaux pour TOUS les types de fichiers
        $total_processed = $this->global_stats['diurnes_departements_processed'] +
                          $this->global_stats['diurnes_pays_processed'] +
                          $this->global_stats['diurnes_general_processed'] +
                          $this->global_stats['sejours_duree_departements_processed'] +
                          $this->global_stats['sejours_duree_pays_processed'] +
                          $this->global_stats['sejours_duree_general_processed'];

        $total_mapped = $this->global_stats['diurnes_departements_mapped'] +
                       $this->global_stats['diurnes_pays_mapped'] +
                       $this->global_stats['diurnes_general_mapped'] +
                       $this->global_stats['sejours_duree_departements_mapped'] +
                       $this->global_stats['sejours_duree_pays_mapped'] +
                       $this->global_stats['sejours_duree_general_mapped'];

        $total_errors = $this->global_stats['diurnes_departements_errors'] +
                       $this->global_stats['diurnes_pays_errors'] +
                       $this->global_stats['diurnes_general_errors'] +
                       $this->global_stats['sejours_duree_departements_errors'] +
                       $this->global_stats['sejours_duree_pays_errors'] +
                       $this->global_stats['sejours_duree_general_errors'];

        $this->log("📊 === RÉSUMÉ ALIMENTATION GLOBALE ===");

        // Statistiques par type de fichier
        $this->log("🏙️ DIURNES DÉPARTEMENTS FRANÇAIS:");
        $this->log("   📥 Traitées: " . number_format($this->global_stats['diurnes_departements_processed']));
        $this->log("   ✅ Mappées: " . number_format($this->global_stats['diurnes_departements_mapped']));
        $this->log("   ❌ Erreurs: " . number_format($this->global_stats['diurnes_departements_errors']));
        $success_rate_dept = $this->global_stats['diurnes_departements_processed'] > 0 ?
            ($this->global_stats['diurnes_departements_mapped'] / $this->global_stats['diurnes_departements_processed']) * 100 : 0;
        $this->log("   📈 Taux succès: " . round($success_rate_dept, 2) . "%");

        $this->log("🌍 DIURNES PAYS INTERNATIONAUX:");
        $this->log("   📥 Traitées: " . number_format($this->global_stats['diurnes_pays_processed']));
        $this->log("   ✅ Mappées: " . number_format($this->global_stats['diurnes_pays_mapped']));
        $this->log("   ❌ Erreurs: " . number_format($this->global_stats['diurnes_pays_errors']));
        $success_rate_pays = $this->global_stats['diurnes_pays_processed'] > 0 ?
            ($this->global_stats['diurnes_pays_mapped'] / $this->global_stats['diurnes_pays_processed']) * 100 : 0;
        $this->log("   📈 Taux succès: " . round($success_rate_pays, 2) . "%");

        $this->log("🏠 DIURNES GÉNÉRAUX:");
        $this->log("   📥 Traitées: " . number_format($this->global_stats['diurnes_general_processed']));
        $this->log("   ✅ Mappées: " . number_format($this->global_stats['diurnes_general_mapped']));
        $this->log("   ❌ Erreurs: " . number_format($this->global_stats['diurnes_general_errors']));
        $this->log("   📈 Taux succès: " . round(($this->global_stats['diurnes_general_processed'] > 0 ?
            ($this->global_stats['diurnes_general_mapped'] / $this->global_stats['diurnes_general_processed']) * 100 : 0), 2) . "%");

        $this->log("🏛️ SÉJOURS DURÉE DÉPARTEMENTS:");
        $this->log("   📥 Traitées: " . number_format($this->global_stats['sejours_duree_departements_processed']));
        $this->log("   ✅ Mappées: " . number_format($this->global_stats['sejours_duree_departements_mapped']));
        $this->log("   ❌ Erreurs: " . number_format($this->global_stats['sejours_duree_departements_errors']));
        $success_rate_sejours_dept = $this->global_stats['sejours_duree_departements_processed'] > 0 ?
            ($this->global_stats['sejours_duree_departements_mapped'] / $this->global_stats['sejours_duree_departements_processed']) * 100 : 0;
        $this->log("   📈 Taux succès: " . round($success_rate_sejours_dept, 2) . "%");

        $this->log("🌐 SÉJOURS DURÉE PAYS:");
        $this->log("   📥 Traitées: " . number_format($this->global_stats['sejours_duree_pays_processed']));
        $this->log("   ✅ Mappées: " . number_format($this->global_stats['sejours_duree_pays_mapped']));
        $this->log("   ❌ Erreurs: " . number_format($this->global_stats['sejours_duree_pays_errors']));
        $success_rate_sejours_pays = $this->global_stats['sejours_duree_pays_processed'] > 0 ?
            ($this->global_stats['sejours_duree_pays_mapped'] / $this->global_stats['sejours_duree_pays_processed']) * 100 : 0;
        $this->log("   📈 Taux succès: " . round($success_rate_sejours_pays, 2) . "%");

        $this->log("🎯 TOTAUX GÉNÉRAUX (TOUS TYPES):");
        $this->log("   📥 Total traitées: " . number_format($total_processed));
        $this->log("   ✅ Total mappées: " . number_format($total_mapped));
        $this->log("   ❌ Total erreurs: " . number_format($total_errors));
        $this->log("   📈 Taux succès global: " . round(($total_processed > 0 ?
            ($total_mapped / $total_processed) * 100 : 0), 2) . "%");
        $this->log("============================================");
    }
    
    private function getZoneId($db, $zone_name) {
        // Pour gros volumes : pas de logs détaillés
        $normalized_name = $this->normalizeZone($zone_name);

        // Cache optimisé
        if (isset($this->zone_cache[$normalized_name])) {
            return $this->zone_cache[$normalized_name];
        }

        // Requête DB optimisée
        $stmt = $db->prepare("SELECT id_zone FROM dim_zones_observation WHERE nom_zone = ?");
        $stmt->bind_param("s", $normalized_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $id = $row ? $row['id_zone'] : null;
        $this->zone_cache[$normalized_name] = $id;

        // Log seulement les erreurs critiques
        if (!$id && !$this->silentMode) {
            $this->log("❌ Zone non trouvée: '$zone_name'");
        }
        
        return $id;
    }

    private function normalizeZone($zone_name) {
        // Nettoyer et normaliser automatiquement
        $clean_name = trim($zone_name);

        // Nettoyer les apostrophes typographiques et caractères spéciaux
        $replacements = array(
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE',
            'Ç' => 'C',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ð' => 'D',
            'Ñ' => 'N',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ý' => 'Y',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
            'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ð' => 'd',
            'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            '’' => "'", '‘' => "'", '´' => "'", '`' => "'",
            '' => "'", '' => "'", '' => "'", '' => "'",
            '' => "OE", '' => "oe", '' => "S", '' => "s",
            '' => "S", '' => "s", '' => "S", '' => "s"
        );
        $clean_name = strtr($clean_name, $replacements);

        // Nettoyer les espaces multiples
        $clean_name = preg_replace('/\s+/', ' ', $clean_name);

        // Convertir en majuscules
        $normalized = strtoupper($clean_name);

        // ✅ Mappings adaptatifs selon l'environnement
        // Détection automatique : PRODUCTION vs DÉVELOPPEMENT
        $is_production = $this->isProductionEnvironment();
        
        if ($is_production) {
            // PRODUCTION : Utiliser les nouveaux noms directement
            $mappings = [
                'HAUT CANTAL' => 'HAUT CANTAL',               // Direct en production
                'HAUTES TERRES' => 'HAUTES TERRES',           // Direct en production
                'PAYS D\'AURILLAC' => 'PAYS D\'AURILLAC',     // Direct en production
                "PAYS D'AURILLAC" => 'PAYS D\'AURILLAC',      // Variante apostrophe
                'PAYS D AURILLAC' => 'PAYS D\'AURILLAC',      // Variante sans apostrophe
                'PAYS DAURILLAC' => 'PAYS D\'AURILLAC',       // Variante encodage
                'PAYS D�AURILLAC' => 'PAYS D\'AURILLAC',      // Variante caractère spécial CSV (�)
                'LIORAN' => 'LIORAN',                         // Direct en production
                'CH�TAIGNERAIE' => 'CHÂTAIGNERAIE',          // Correction encodage
                'CHATAIGNERAIE' => 'CHÂTAIGNERAIE',          // Correction encodage
                'VAL TRUY�RE' => 'VAL TRUYÈRE',               // Correction encodage
                'VAL TRUYERE' => 'VAL TRUYÈRE'                // Correction encodage
            ];
        } else {
            // DÉVELOPPEMENT : Utiliser les anciens mappings
            $mappings = [
                'HAUT CANTAL' => 'GENTIANE',                  // Mapping développement
                'HAUTES TERRES' => 'HTC',                     // Mapping développement
                'PAYS D\'AURILLAC' => 'CABA',                 // Mapping développement
                "PAYS D'AURILLAC" => 'CABA',                  // Variante apostrophe
                'PAYS D AURILLAC' => 'CABA',                  // Variante sans apostrophe
                'PAYS DAURILLAC' => 'CABA',                   // Variante encodage
                'PAYS D�AURILLAC' => 'CABA',                  // Variante caractère spécial CSV (�)
                'LIORAN' => 'STATION',                        // Mapping développement
                'CH�TAIGNERAIE' => 'CHÂTAIGNERAIE',          // Correction encodage
                'CHATAIGNERAIE' => 'CHÂTAIGNERAIE',          // Correction encodage
                'VAL TRUY�RE' => 'VAL TRUYÈRE',               // Correction encodage
                'VAL TRUYERE' => 'VAL TRUYÈRE'                // Correction encodage
            ];
        }

        // Appliquer le mapping si trouvé, sinon retourner la version normalisée
        return $mappings[$normalized] ?? $normalized;
    }

    /**
     * Détecte si on est en environnement de production
     */
    private function isProductionEnvironment() {
        // Méthodes de détection multiples pour plus de robustesse
        
        // 1. Vérifier le nom d'hôte
        $hostname = gethostname();
        if (strpos($hostname, 'srv.cantal') !== false || strpos($hostname, 'observatoire') !== false) {
            return true;
        }
        
        // 2. Vérifier les variables d'environnement
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
            if (strpos($host, 'srv.cantal-destination.com') !== false || 
                strpos($host, 'observatoire.cantal-destination.com') !== false) {
                return true;
            }
        }
        
        // 3. Vérifier le chemin du serveur
        if (isset($_SERVER['SERVER_NAME'])) {
            $server = $_SERVER['SERVER_NAME'];
            if (strpos($server, 'cantal-destination.com') !== false) {
                return true;
            }
        }
        
        // 4. Vérifier la présence de fichiers spécifiques à la production
        if (file_exists('/home/observatoire/public_html')) {
            return true;
        }
        
        // 5. Détection par base de données (vérifier si les nouvelles zones existent)
        try {
            if (!$this->db) {
                $this->connectDB();
            }
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM dim_zones_observation WHERE nom_zone IN ('HAUT CANTAL', 'HAUTES TERRES', 'PAYS D\\'AURILLAC', 'LIORAN')");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            // Si les 4 nouvelles zones existent, on est probablement en production
            if ($result['count'] >= 4) {
                return true;
            }
        } catch (Exception $e) {
            // En cas d'erreur, continuer avec les autres méthodes
        }
        
        // Par défaut, considérer comme développement
        return false;
    }
    
    private function getProvenanceId($db, $provenance_name) {
        // Normaliser le nom de provenance
        $normalized_name = $this->normalizeProvenance($provenance_name);
        
        // Utiliser le cache pour éviter les requêtes répétitives
        if (isset($this->provenance_cache[$normalized_name])) {
            return $this->provenance_cache[$normalized_name];
        }
        
        $stmt = $db->prepare("SELECT id_provenance FROM dim_provenances WHERE nom_provenance = ?");
        $stmt->bind_param("s", $normalized_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $id = $row ? $row['id_provenance'] : null;
        $this->provenance_cache[$normalized_name] = $id;
        
        return $id;
    }
    
    private function normalizeProvenance($provenance_name) {
        // Nettoyer et normaliser automatiquement
        $clean_name = trim($provenance_name);

        // Convertir en majuscules
        $normalized = strtoupper($clean_name);

        // Mappings spécifiques pour les cas particuliers
        $mappings = [
            'NON LOCAL' => 'NONLOCAL',
            'NONLOCAL' => 'NONLOCAL',
            'ETRANGER' => 'ETRANGER',
            'ÉTRANGER' => 'ETRANGER',
            'STRANGER' => 'ETRANGER',
            'INTERNATIONAL' => 'ETRANGER'
        ];

        return $mappings[$normalized] ?? $normalized;
    }
    
    private function getCategorieId($db, $categorie_name) {
        // Normaliser le nom de catégorie
        $normalized_name = $this->normalizeCategorie($categorie_name);
        
        // Utiliser le cache pour éviter les requêtes répétitives
        if (isset($this->categorie_cache[$normalized_name])) {
            return $this->categorie_cache[$normalized_name];
        }
        
        $stmt = $db->prepare("SELECT id_categorie FROM dim_categories_visiteur WHERE nom_categorie = ?");
        $stmt->bind_param("s", $normalized_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $id = $row ? $row['id_categorie'] : null;
        $this->categorie_cache[$normalized_name] = $id;
        
        return $id;
    }
    
    private function normalizeCategorie($categorie_name) {
        // Mapping des variations de noms de catégorie vers les noms standardisés
        $mappings = [
            'Touriste' => 'TOURISTE',
            'touriste' => 'TOURISTE',
            'TOURISTE' => 'TOURISTE',
            'Resident' => 'RESIDENT',
            'resident' => 'RESIDENT',
            'RESIDENT' => 'RESIDENT',
            'Résident' => 'RESIDENT',
            'résident' => 'RESIDENT',
            'Habituellement present' => 'HABITUELLEMENT PRESENT',
            'habituellement present' => 'HABITUELLEMENT PRESENT',
            'HABITUELLEMENT PRESENT' => 'HABITUELLEMENT PRESENT',
            'Excursionniste' => 'EXCURSIONNISTE',
            'excursionniste' => 'EXCURSIONNISTE',
            'EXCURSIONNISTE' => 'EXCURSIONNISTE',
            'Excursionniste recurrent' => 'EXCURSIONNISTE RECURRENT',
            'excursionniste recurrent' => 'EXCURSIONNISTE RECURRENT',
            'EXCURSIONNISTE RECURRENT' => 'EXCURSIONNISTE RECURRENT',
            'Transit' => 'TRANSIT',
            'transit' => 'TRANSIT',
            'TRANSIT' => 'TRANSIT',
            'Transit nocturne' => 'TRANSIT NOCTURNE',
            'transit nocturne' => 'TRANSIT NOCTURNE',
            'TRANSIT NOCTURNE' => 'TRANSIT NOCTURNE',
            'Habituellement present en transit' => 'HABITUELLEMENT PRESENT EN TRANSIT',
            'Resident en transit' => 'RESIDENT EN TRANSIT',
            'Touriste en transit' => 'TOURISTE EN TRANSIT'
        ];
        
        // Nettoyer et normaliser
        $clean_name = trim($categorie_name);
        return $mappings[$clean_name] ?? strtoupper($clean_name);
    }
    
    private function getDepartementId($db, $dept_name) {
        // Normaliser le nom du département
        $normalized_name = $this->normalizeDepartement($dept_name);
        
        // Utiliser le cache pour éviter les requêtes répétitives
        if (isset($this->departement_cache[$normalized_name])) {
            return $this->departement_cache[$normalized_name];
        }
        
        $stmt = $db->prepare("SELECT id_departement FROM dim_departements WHERE nom_departement = ?");
        $stmt->bind_param("s", $normalized_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $id = $row ? $row['id_departement'] : null;
        $this->departement_cache[$normalized_name] = $id;
        
        return $id;
    }
    
    private function normalizeDepartement($dept_name) {
        // Mapping des variations de noms de départements vers les noms standardisés
        $mappings = [
            'PUY-DE-DÔME' => 'PUY-DE-DÔME',
            'Puy-de-Dôme' => 'PUY-DE-DÔME',
            'puy-de-dôme' => 'PUY-DE-DÔME',
            'Z_Autre 97' => 'AUTRE 97',
            'Z_Autre 98' => 'AUTRE 98',
            'Autre 97' => 'AUTRE 97',
            'Autre 98' => 'AUTRE 98'
        ];
        
        // Nettoyer et normaliser
        $clean_name = trim($dept_name);
        
        // Vérifier d'abord dans le mapping
        if (isset($mappings[$clean_name])) {
            return $mappings[$clean_name];
        }
        
        // Sinon, convertir en majuscules
        return strtoupper($clean_name);
    }
    
    private function getPaysId($db, $pays_name) {
        // Normaliser le nom du pays
        $normalized_name = $this->normalizePays($pays_name);
        
        // Utiliser le cache pour éviter les requêtes répétitives
        if (isset($this->pays_cache[$normalized_name])) {
            return $this->pays_cache[$normalized_name];
        }
        
        $stmt = $db->prepare("SELECT id_pays FROM dim_pays WHERE nom_pays = ?");
        $stmt->bind_param("s", $normalized_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $id = $row ? $row['id_pays'] : null;
        $this->pays_cache[$normalized_name] = $id;
        
        // Debug: log si pas trouvé
        if (!$id) {
            $this->log("⚠️ Pays non trouvé: '$pays_name' -> normalisé: '$normalized_name'");
        }
        
        return $id;
    }
    

    private function getCommuneId($db, $commune_name) {
        // Normaliser le nom de la commune
        $normalized_name = $this->normalizeCommune($commune_name);

        // Utiliser le cache pour éviter les requêtes répétitives
        if (isset($this->commune_cache[$normalized_name])) {
            return $this->commune_cache[$normalized_name];
        }

        $stmt = $db->prepare("SELECT id_commune FROM dim_communes WHERE nom_commune = ?");
        $stmt->bind_param("s", $normalized_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $id = $row ? $row['id_commune'] : null;
        $this->commune_cache[$normalized_name] = $id;

        // Debug: log si pas trouvé (seulement en mode verbose)
        if (!$id && !$this->silentMode) {
            $this->log("⚠️ Commune non trouvée: '$commune_name' -> normalisé: '$normalized_name'");
        }

        return $id;
    }

    private function getEpciId($db, $commune_id) {
        // Utiliser le cache pour éviter les requêtes répétitives
        if (isset($this->epci_cache[$commune_id])) {
            return $this->epci_cache[$commune_id];
        }

        // Chercher l'id_epci basé sur l'id_commune dans les données existantes
        $stmt = $db->prepare("
            SELECT DISTINCT id_epci
            FROM fact_lieu_activite_soir
            WHERE id_commune = ? AND id_epci != 0
            LIMIT 1
        ");
        $stmt->bind_param("i", $commune_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $id_epci = $row ? $row['id_epci'] : 0; // Valeur par défaut 0 si pas trouvé
        $this->epci_cache[$commune_id] = $id_epci;

        return $id_epci;
    }

    private function getDureeId($db, $duree_sejour) {
        // Utiliser le cache pour éviter les requêtes répétitives
        if (isset($this->duree_cache[$duree_sejour])) {
            return $this->duree_cache[$duree_sejour];
        }

        // Convertir en majuscules et normaliser
        $normalized_duree = $this->normalizeDureeSejour($duree_sejour);

        $stmt = $db->prepare("SELECT id_duree FROM dim_durees_sejour WHERE libelle = ?");
        $stmt->bind_param("s", $normalized_duree);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $id_duree = $row ? $row['id_duree'] : null;
        $this->duree_cache[$duree_sejour] = $id_duree;

        // Debug: log si pas trouvé (seulement en mode verbose)
        if (!$id_duree && !$this->silentMode) {
            $this->log("⚠️ Durée de séjour non trouvée: '$duree_sejour' -> normalisé: '$normalized_duree'");
        }

        return $id_duree;
    }

    private function getInternationalPaysId($db, $pays_name) {
        if (isset($this->international_pays_cache[$pays_name])) {
            return $this->international_pays_cache[$pays_name];
        }

        // Nettoyer le nom du pays (supprimer guillemets, espaces, etc.)
        $normalized_pays = $this->normalizePays($pays_name);

        $stmt = $db->prepare("SELECT id_pays FROM dim_pays WHERE nom_pays = ?");
        $stmt->bind_param("s", $normalized_pays);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $id_pays = $row ? $row['id_pays'] : null;

        if (!$id_pays && !$this->silentMode) {
            $this->log("⚠️ Pays non trouvé: '$pays_name' -> normalisé: '$normalized_pays'");
        }

        $this->international_pays_cache[$pays_name] = $id_pays;
        return $id_pays;
    }
    
    private function normalizePays($pays_name) {
        // Nettoyer les caractères spéciaux et guillemets
        $normalized = trim($pays_name);
        $normalized = str_replace(['"', '"', '"', '"'], '', $normalized); // Supprimer tous types de guillemets
        $normalized = str_replace(';', ',', $normalized); // Remplacer ; par ,
        $normalized = str_replace(' ', ' ', $normalized); // Remplacer espaces insécables par espaces normaux
        $normalized = preg_replace('/\s+/', ' ', $normalized); // Normaliser les espaces multiples
        $normalized = trim($normalized);
        $normalized = strtoupper($normalized);

        // Corrections spécifiques pour les pays avec caractères spéciaux
        $corrections = [
            'CORÉE DU SUD' => 'CORÉE DU SUD',
            'ÉTATS-UNIS' => 'ÉTATS-UNIS',
            'DOMINICAINE (RÉPUBLIQUE)' => 'DOMINICAINE (RÉPUBLIQUE)',
            'OCÉAN INDIEN (LA RÉUNION, MAYOTTE, TAAF)' => 'OCÉAN INDIEN (LA RÉUNION, MAYOTTE, TAAF)',
            'GUADELOUPE, MARTINIQUE' => 'GUADELOUPE, MARTINIQUE',
            'CÔTE D\'IVOIRE' => 'CÔTE D\'IVOIRE',
            'ÉMIRATS ARABES UNIS' => 'ÉMIRATS ARABES UNIS',
            'RÉPUBLIQUE TCHÈQUE' => 'RÉPUBLIQUE TCHÈQUE',
            // Ajouter d'autres corrections si nécessaire
        ];

        return $corrections[$normalized] ?? $normalized;
    }

    private function normalizeDureeSejour($duree_sejour) {
        // Convertir en majuscules et normaliser
        $normalized = strtoupper(trim($duree_sejour));

        // Mappings spécifiques pour les durées de séjour
        $mappings = [
            '1 NUIT' => '1 NUIT',
            '1 NUITS' => '1 NUIT', // Correction pour les données internationales
            '2 NUITS' => '2 NUITS',
            '3 NUITS' => '3 NUITS',
            '4 NUITS' => '4 NUITS',
            '5 NUITS' => '5 NUITS',
            '6 NUITS' => '6 NUITS',
            '7 NUITS' => '7 NUITS',
            '8 NUITS' => '8 NUITS',
            '9 NUITS' => '9 NUITS',
            '10 NUITS' => '10 NUITS',
            '11 NUITS' => '11 NUITS',
            '12 NUITS' => '12 NUITS',
            '13 NUITS ET PLUS' => '13 NUITS ET PLUS',
            '13 NUITS ET PLUS' => '13 NUITS ET PLUS', // Correction casse
            // Cas particuliers
            '1N' => '1 NUIT',
            '2N' => '2 NUITS',
            '3N' => '3 NUITS',
            '4N' => '4 NUITS',
            '5N' => '5 NUITS',
            '6N' => '6 NUITS',
            '7N' => '7 NUITS',
            '8N' => '8 NUITS',
            '9N' => '9 NUITS',
            '10N' => '10 NUITS',
            '11N' => '11 NUITS',
            '12N' => '12 NUITS',
        ];

        return $mappings[$normalized] ?? $normalized;
    }

    private function normalizeCommune($commune_name) {
        // Mapping des variations de noms de communes vers les noms standardisés
        $mappings = [
            'ALBARET-STE-MARIE' => 'ALBARET-SAINTE-MARIE',
            'ALBARET STE MARIE' => 'ALBARET-SAINTE-MARIE',
            'ST-MARTIN-VALMEROUX' => 'SAINT-MARTIN-VALMEROUX',
            'ST MARTIN VALMEROUX' => 'SAINT-MARTIN-VALMEROUX',
            'AGEN-D\'AVEYRON' => 'AGEN-D\'AVEYRON',
            'AGEN D\'AVEYRON' => 'AGEN-D\'AVEYRON',
            // Ajouter d'autres mappings au besoin
        ];
        
        // Nettoyer et normaliser
        $clean_name = trim($commune_name);
        
        // Vérifier d'abord dans le mapping
        if (isset($mappings[$clean_name])) {
            return $mappings[$clean_name];
        }
        
        // Sinon, convertir en majuscules avec gestion des accents
        $normalized = strtoupper($clean_name);
        
        // Correction spécifique pour les accents mal convertis
        $normalized = str_replace('é', 'É', $normalized);
        $normalized = str_replace('è', 'È', $normalized);
        $normalized = str_replace('à', 'À', $normalized);
        $normalized = str_replace('ù', 'Ù', $normalized);
        $normalized = str_replace('ç', 'Ç', $normalized);
        $normalized = str_replace('-', '-', $normalized); // Préserver les tirets

        // Correction spécifique pour les abréviations
        $normalized = str_replace('STE', 'SAINTE', $normalized);
        $normalized = str_replace('ST ', 'SAINT ', $normalized);
        $normalized = str_replace('ST-', 'SAINT-', $normalized);
        
        return $normalized;
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

    /**
     * S'assure que la table temporaire existe, la crée si nécessaire
     */
    private function getSampleDataForTable($table_name) {
        // Données d'exemple par défaut selon le type de table
        if (strpos($table_name, 'fact_lieu_activite_soir') !== false) {
            return [[
                'date' => '2024-01-01',
                'jour_semaine' => 'Lundi',
                'id_zone' => 1,
                'id_provenance' => 1,
                'id_categorie' => 1,
                'id_commune' => 1,
                'id_epci' => 0,
                'volume' => 100
            ]];
        }

        if (strpos($table_name, 'fact_nuitees') !== false) {
            return [[
                'date' => '2024-01-01',
                'id_zone' => 1,
                'id_provenance' => 1,
                'id_categorie' => 1,
                'volume' => 100
            ]];
        }

        if (strpos($table_name, 'fact_diurnes') !== false) {
            $sample = [
                'date' => '2024-01-01',
                'id_zone' => 1,
                'id_provenance' => 1,
                'id_categorie' => 1,
                'volume' => 100
            ];

            // Pour les tables pays internationales, ajouter id_pays
            if (strpos($table_name, 'fact_diurnes_pays') !== false) {
                $sample['id_pays'] = 1;
            }

            // Pour les tables départements françaises, ajouter id_departement
            if (strpos($table_name, 'fact_diurnes_departements') !== false) {
                $sample['id_departement'] = 1;
            }

            return [$sample];
        }

        if (strpos($table_name, 'fact_sejours_duree') !== false) {
            $sample = [
                'date' => '2024-01-01',
                'id_zone' => 1,
                'id_provenance' => 1,
                'id_categorie' => 1,
                'id_duree' => 1,
                'volume' => 100
            ];

            // Pour les tables internationales, ajouter id_pays
            if (strpos($table_name, 'fact_sejours_duree_pays') !== false) {
                $sample['id_pays'] = 1;
            }

            // Pour les tables départements françaises, ajouter id_departement
            if (strpos($table_name, 'fact_sejours_duree_departements') !== false) {
                $sample['id_departement'] = 1;
            }

            return [$sample];
        }

        // Données par défaut génériques
        return [[
            'date' => '2024-01-01',
            'id_zone' => 1,
            'id_provenance' => 1,
            'id_categorie' => 1,
            'volume' => 100
        ]];
    }

    private function ensureTableExists($table_name, $sample_data) {
        $db = $this->connectDB();

        // Vérifier si la table existe
        $result = $db->query("SHOW TABLES LIKE '$table_name'");
        if ($result && $result->num_rows > 0) {
            $this->log("✓ Table $table_name existe déjà");
            return;
        }

        $this->log("📝 Création de la table $table_name...");

        // Créer la table basée sur les données
        $create_sql = $this->generateCreateTableSQL($table_name, $sample_data);

        if ($db->query($create_sql)) {
            $this->log("✅ Table $table_name créée avec succès");
        } else {
            throw new Exception("Erreur création table $table_name: " . $db->error);
        }
    }

    /**
     * Génère le SQL CREATE TABLE basé sur les données
     */
    private function generateCreateTableSQL($table_name, $sample_data) {
        if (empty($sample_data)) {
            throw new Exception("Impossible de créer la table $table_name : aucune donnée exemple");
        }

        $columns = [];
        $sample_row = $sample_data[0];

        // Définir les types de colonnes selon le nom de la table et les données
        foreach ($sample_row as $column => $value) {
            $column_def = $this->getColumnDefinition($table_name, $column, $value);
            if ($column_def) {
                $columns[] = "`$column` $column_def";
            }
        }

        // Ajouter la colonne is_provisional si elle n'existe pas déjà
        if (!isset($sample_row['is_provisional'])) {
            $columns[] = "`is_provisional` TINYINT(1) DEFAULT 1";
        }

        // Clés primaires selon le type de table
        $primary_keys = $this->getPrimaryKeys($table_name);

        $columns_sql = implode(",\n    ", $columns);

        if (!empty($primary_keys)) {
            $columns_sql .= ",\n    PRIMARY KEY (" . implode(", ", $primary_keys) . ")";
        }

        $sql = "CREATE TABLE `$table_name` (\n    $columns_sql\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return $sql;
    }

    /**
     * Définit le type de colonne selon le contexte
     */
    private function getColumnDefinition($table_name, $column, $sample_value) {
        // Colonnes communes
        if ($column === 'date') {
            return "DATE NOT NULL";
        }

        if ($column === 'volume') {
            return "INT(11) NOT NULL DEFAULT 0";
        }

        if ($column === 'is_provisional') {
            return "TINYINT(1) DEFAULT 1";
        }

        // Colonnes d'identifiant
        if (strpos($column, 'id_') === 0) {
            if ($column === 'id_epci') {
                // id_epci peut être 0 (valeur par défaut)
                return "INT(11) NOT NULL DEFAULT 0";
            }
            return "INT(11) NOT NULL";
        }

        // Colonnes spécifiques selon le type de table
        if (strpos($table_name, 'fact_lieu_activite_soir') !== false) {
            if ($column === 'jour_semaine') {
                return "VARCHAR(20) DEFAULT NULL";
            }
        }

        // Colonnes numériques
        if (is_numeric($sample_value)) {
            return "INT(11) DEFAULT 0";
        }

        // Colonnes textuelles
        return "VARCHAR(255) DEFAULT NULL";
    }

    /**
     * Définit les clés primaires selon le type de table
     */
    private function getPrimaryKeys($table_name) {
        if (strpos($table_name, 'fact_lieu_activite_soir') !== false) {
            return ['`date`', '`id_commune`', '`id_zone`', '`id_provenance`', '`id_categorie`'];
        }

        if (strpos($table_name, 'fact_nuitees') !== false) {
            return ['`date`', '`id_zone`', '`id_provenance`', '`id_categorie`'];
        }

        if (strpos($table_name, 'fact_diurnes') !== false) {
            $keys = ['`date`', '`id_zone`', '`id_provenance`', '`id_categorie`'];

            // Pour les tables pays internationales, ajouter id_pays à la clé primaire
            if (strpos($table_name, 'fact_diurnes_pays') !== false) {
                $keys[] = '`id_pays`';
            }

            // Pour les tables départements françaises, ajouter id_departement à la clé primaire
            if (strpos($table_name, 'fact_diurnes_departements') !== false) {
                $keys[] = '`id_departement`';
            }

            return $keys;
        }

        if (strpos($table_name, 'fact_sejours_duree') !== false) {
            $keys = ['`date`', '`id_zone`', '`id_provenance`', '`id_categorie`', '`id_duree`'];

            // Pour les tables internationales, ajouter id_pays à la clé primaire
            if (strpos($table_name, 'fact_sejours_duree_pays') !== false) {
                $keys[] = '`id_pays`';
            }

            // Pour les tables départements françaises, ajouter id_departement à la clé primaire
            if (strpos($table_name, 'fact_sejours_duree_departements') !== false) {
                $keys[] = '`id_departement`';
            }

            return $keys;
        }

        // Par défaut, pas de clé primaire (pour les anciennes données)
        return [];
    }
    
    private function insertData($table_name, $data) {
        if (empty($data)) {
            $this->log("Aucune donnée à insérer dans $table_name");
            return 0;
        }

        // Log seulement en mode non-silencieux pour éviter surcharge avec gros volumes
        if (!$this->silentMode) {
            $this->log("📊 Insertion $table_name: " . count($data) . " lignes à traiter");
        }
        $db = $this->connectDB();

        // Créer la table si elle n'existe pas
        $this->ensureTableExists($table_name, $data);

        // Déterminer l'ensemble des colonnes présentes sur tout le dataset pour éviter les décalages
        $allColumnsAssoc = [];
        foreach ($data as $row) {
            foreach (array_keys($row) as $col) { $allColumnsAssoc[$col] = true; }
        }
        $columns = array_values(array_keys($allColumnsAssoc));
        // Ordonner avec les champs clés prioritaires en tête
        $priority = ['date','id_zone','id_provenance','id_categorie','volume','id_departement','id_pays','id_geolife'];
        usort($columns, function($a,$b) use ($priority){
            $pa = array_search($a, $priority);
            $pb = array_search($b, $priority);
            $pa = $pa === false ? PHP_INT_MAX : $pa;
            $pb = $pb === false ? PHP_INT_MAX : $pb;
            if ($pa === $pb) return strcmp($a,$b);
            return $pa - $pb;
        });

        $columns_str = '`' . implode('`, `', $columns) . '`';
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';

        $sql = "INSERT INTO `$table_name` ($columns_str) VALUES ($placeholders)";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erreur préparation requête $table_name: " . $db->error);
        }

        // Types par colonne
        $types = '';
        foreach ($columns as $column) {
            if ($column === 'date') {
                $types .= 's';
            } elseif (in_array($column, ['id_zone','id_provenance','id_categorie','volume','id_departement','id_pays','id_geolife'])) {
                $types .= 'i';
            } else {
                $types .= 's';
            }
        }

        $inserted_count = 0;
        $skipped_mismatch = 0;

        // Utiliser INSERT IGNORE dès le départ pour éviter les conflits de clés
        $this->log("Utilisation de INSERT IGNORE pour éviter les conflits de clés primaires");
        $this->log("🔄 Début insertion par lots de " . count($data) . " lignes");

        // Créer la requête INSERT IGNORE
        $insert_sql = "INSERT IGNORE INTO `$table_name` (" . implode(', ', array_map(function($col) { return "`$col`"; }, $columns)) . ") VALUES (" . str_repeat('?, ', count($columns) - 1) . "?)";
        $this->log("📝 Requête SQL: $insert_sql");
        $stmt = $db->prepare($insert_sql);

        if (!$stmt) {
            throw new Exception("Erreur préparation INSERT IGNORE: " . $db->error);
        }

        // Insertion par lots
        $batch_size = 1000;
        $batches = array_chunk($data, $batch_size);
        foreach ($batches as $batch_num => $batch) {
            $batch_num_display = $batch_num + 1;
            // Log seulement tous les 10 lots ou en mode non-silencieux
            if (!$this->silentMode && ($batch_num % 10 === 0 || count($batches) < 10)) {
                $this->log("📦 Lot $batch_num_display/" . count($batches) . ": " . count($batch) . " lignes");
            }
            $batch_inserted = 0;

            foreach ($batch as $row_index => $row) {
                // Log détaillé seulement en mode debug
                if ($this->debugMode && $batch_num === 0 && $row_index < 3) {
                    $this->log("🔍 Ligne " . ($row_index + 1) . ": " . json_encode($row));
                }

                // Normaliser: remplir les colonnes manquantes avec null
                $values = [];
                foreach ($columns as $col) {
                    $values[] = array_key_exists($col, $row) ? $row[$col] : null;
                }

                if (count($values) !== count($columns)) {
                    $skipped_mismatch++;
                    // Log erreurs seulement si pas en mode silencieux
                    if (!$this->silentMode) {
                        $this->log("⚠️ Ligne ignorée: colonnes incorrectes");
                    }
                    continue;
                }

                $stmt->bind_param($types, ...$values);
                if ($stmt->execute()) {
                    $inserted_count++;
                    $batch_inserted++;
                } else {
                    // Log erreurs seulement si pas en mode silencieux
                    if (!$this->silentMode) {
                        $this->log("❌ Erreur INSERT ligne " . ($row_index + 1));
                    }
                }
            }

            // Log seulement tous les 10 lots ou en mode non-silencieux
            if (!$this->silentMode && ($batch_num % 10 === 0 || count($batches) < 10)) {
                $this->log("✅ Lot $batch_num_display terminé: $batch_inserted/" . count($batch) . " lignes");
            }
        }

        $stmt->close();
        if ($skipped_mismatch > 0) {
            $this->log("$table_name: $skipped_mismatch lignes normalisées (valeurs manquantes remplacées par NULL)");
        }
        $this->log("Table $table_name: $inserted_count lignes insérées");
        return $inserted_count;
    }
    
    public function getStatus() {
        $status = [];
        
        foreach ($this->config['files'] as $filename => $table_name) {
            $filepath = $this->findCsvPath($filename);
            
            $file_info = [
                'filename' => $filename,
                'table' => $table_name,
                'exists' => $filepath && file_exists($filepath),
                'size' => ($filepath && file_exists($filepath)) ? filesize($filepath) : 0,
                'modified' => ($filepath && file_exists($filepath)) ? filemtime($filepath) : 0
            ];
            
            // Compter les enregistrements dans la table
            try {
                $db = $this->connectDB();
                
                // Vérifier si la table existe, sinon la créer
                $table_check = $db->query("SHOW TABLES LIKE '$table_name'");
                if (!$table_check || $table_check->num_rows === 0) {
                    $this->log("🔧 Table $table_name n'existe pas, création automatique...");
                    $sample_data = $this->getSampleDataForTable($table_name);
                    $this->ensureTableExists($table_name, $sample_data);
                }

                // Maintenant compter les enregistrements
                $result = $db->query("SELECT COUNT(*) as count FROM `$table_name`");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $file_info['records'] = (int)$row['count'];
                } else {
                    $file_info['records'] = 0;
                }
            } catch (Exception $e) {
                // Log l'erreur pour diagnostic
                $this->log("Erreur getStatus pour $table_name: " . $e->getMessage());
                $file_info['records'] = 'Erreur: ' . $e->getMessage();
            }
            
            $status[] = $file_info;
        }
        
        return $status;
    }
}

// Interface supprimée - Logique PHP pure uniquement

// Actions API
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $manager = new TempTablesManager();
    
    switch ($_GET['action']) {
        case 'check':
            $result = $manager->checkAndUpdate(false);
            break;
            
        case 'force':
            $result = $manager->checkAndUpdate(true);
            break;
            
        case 'status':
            $result = ['status' => $manager->getStatus()];
            break;
            
        default:
            $result = ['error' => 'Action non reconnue'];
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}
?>
