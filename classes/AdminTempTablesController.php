<?php
/**
 * Contrôleur pour la gestion des Tables Temporaires
 * Cantal Destination - Interface d'automatisation
 */

class AdminTempTablesController {
    
    private $message = null;
    private $messageType = null;
    private $status = null;
    private $migrationMessage = null;
    private $migrationMessageType = null;
    private $transferMessage = null;
    private $transferMessageType = null;
    private $logs = [];
    private $log_info = null;
    private $migration_logs = [];
    private $migration_log_info = null;
    private $stats = [];
    private $db_diag = null;
    
    public function __construct() {
        // Inclusion de la configuration de base de données
        require_once __DIR__ . '/../config/app.php';
        
        // Vérification de l'authentification admin
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            Security::logSecurityEvent('UNAUTHORIZED_ACCESS_ATTEMPT', [
                'page' => 'admin_temp_tables',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ], 'HIGH');
            header('Location: ' . url('/login'));
            exit;
        }

        // Vérification des permissions spécifiques
        if (!Security::checkPermission('admin')) {
            Security::logSecurityEvent('INSUFFICIENT_PERMISSIONS', [
                'user_id' => $_SESSION['user']['id'] ?? null,
                'required_role' => 'admin',
                'current_role' => $_SESSION['user']['role'] ?? 'unknown'
            ], 'HIGH');
            http_response_code(403);
            exit('Accès refusé');
        }
    }
    
    /**
     * Point d'entrée principal
     */
    public function index() {
        // Initialisation automatique du schéma (tables _temp et colonne is_provisional)
        $this->ensureTempAndProvisionalSchema();
        // Traitement des actions POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action'])) {
                $this->handleAction();
            } elseif (isset($_POST['file_action'])) {
                $this->handleFileAction();
            }
        }
        
        // Calcul des statistiques
        $this->calculateStats();
        
        // Chargement des logs
        $this->loadLogs();
        
        // Chargement des tables provisoires
        $provisoireTables = $this->getProvisoireTables();
        
        // Réponse AJAX (JSON) si demandé
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === '1') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => $this->message,
                'messageType' => $this->messageType,
                'migrationMessage' => $this->migrationMessage,
                'migrationMessageType' => $this->migrationMessageType,
                'transferMessage' => $this->transferMessage,
                'transferMessageType' => $this->transferMessageType,
                'logs' => $this->logs,
                'log_info' => $this->log_info,
                'migration_logs' => $this->migration_logs,
                'migration_log_info' => $this->migration_log_info,
                'stats' => $this->stats,
                'provisoireTables' => $provisoireTables,
                'db_diag' => $this->db_diag
            ]);
            exit;
        }

        // Retourner les données pour le template
        return [
            'message' => $this->message,
            'messageType' => $this->messageType,
            'status' => $this->status,
            'migrationMessage' => $this->migrationMessage,
            'migrationMessageType' => $this->migrationMessageType,
            'transferMessage' => $this->transferMessage,
            'transferMessageType' => $this->transferMessageType,
            'logs' => $this->logs,
            'log_info' => $this->log_info,
            'migration_logs' => $this->migration_logs,
            'migration_log_info' => $this->migration_log_info,
            'stats' => $this->stats,
            'provisoireTables' => $provisoireTables,
            'db_diag' => $this->db_diag
        ];
    }

    /**
     * Initialise le schéma requis pour la page :
     * - crée les tables *_temp manquantes à partir des tables principales
     * - ajoute la colonne is_provisional sur les tables de faits si absente
     *
     * Sécurisé (liste blanche) et silencieux (journalise mais ne bloque pas l'affichage)
     */
    private function ensureTempAndProvisionalSchema() {
        try {
            $fluxDb = CantalDestinationDatabase::getInstance();
            $db = $fluxDb->getConnection(); // PDO
        } catch (Exception $e) {
            if (class_exists('Security')) {
                Security::logSecurityEvent('DB_CONNECTION_ERROR_ON_INIT', [
                    'error' => $e->getMessage()
                ], 'HIGH');
            }
            return;
        }

        $mappings = [
            'fact_nuitees_temp' => 'fact_nuitees',
            'fact_nuitees_departements_temp' => 'fact_nuitees_departements',
            'fact_nuitees_pays_temp' => 'fact_nuitees_pays',
            'fact_diurnes_temp' => 'fact_diurnes',
            'fact_diurnes_departements_temp' => 'fact_diurnes_departements',
            'fact_diurnes_pays_temp' => 'fact_diurnes_pays',
            'fact_diurnes_geolife_temp' => 'fact_diurnes_geolife'
        ];

        $tableExistsStmt = $db->prepare("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $columnExistsStmt = $db->prepare("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $indexOnColumnStmt = $db->prepare("SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $uniqueIndexesStmt = $db->prepare("SELECT DISTINCT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND non_unique = 0 AND index_name <> 'PRIMARY'");
        $hasPrimaryStmt = $db->prepare("SELECT COUNT(*) AS c FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = ? AND constraint_type = 'PRIMARY KEY'");

        foreach ($mappings as $tempTable => $mainTable) {
            // Existence tables
            $tableExistsStmt->execute([$mainTable]);
            $mainExists = ((int)$tableExistsStmt->fetchColumn()) > 0;
            $tableExistsStmt->closeCursor();

            $tableExistsStmt->execute([$tempTable]);
            $tempExists = ((int)$tableExistsStmt->fetchColumn()) > 0;
            $tableExistsStmt->closeCursor();

            // Ajouter is_provisional si besoin
            if ($mainExists) {
                $columnExistsStmt->execute([$mainTable, 'is_provisional']);
                $hasProvisional = ((int)$columnExistsStmt->fetchColumn()) > 0;
                $columnExistsStmt->closeCursor();
                if (!$hasProvisional) {
                    try {
                        $db->exec("ALTER TABLE `{$mainTable}` ADD COLUMN is_provisional TINYINT(1) NOT NULL DEFAULT 0");
                    } catch (Exception $e) {
                        if (class_exists('Security')) {
                            Security::logSecurityEvent('ADD_IS_PROVISIONAL_FAILED', [
                                'table' => $mainTable,
                                'error' => $e->getMessage()
                            ], 'MEDIUM');
                        }
                    }
                }
                // Index sur is_provisional si absent
                try {
                    $indexOnColumnStmt->execute([$mainTable, 'is_provisional']);
                    $hasIndexOnCol = ((int)$indexOnColumnStmt->fetchColumn()) > 0;
                    $indexOnColumnStmt->closeCursor();
                    if (!$hasIndexOnCol) {
                        $indexName = "idx_{$mainTable}_provisional";
                        $idxCheck = $db->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?");
                        $idxCheck->execute([$mainTable, $indexName]);
                        $existsIdx = ((int)$idxCheck->fetchColumn()) > 0;
                        $idxCheck->closeCursor();
                        if (!$existsIdx) {
                            $db->exec("ALTER TABLE `{$mainTable}` ADD INDEX `{$indexName}` (is_provisional)");
                        }
                    }
                } catch (Exception $e) {
                    if (class_exists('Security')) {
                        Security::logSecurityEvent('CREATE_INDEX_PROVISIONAL_FAILED', [
                            'table' => $mainTable,
                            'error' => $e->getMessage()
                        ], 'LOW');
                    }
                }
            }

            // Créer la table temp à partir de la principale si manquante
            if ($mainExists && !$tempExists) {
                try {
                    $db->exec("CREATE TABLE `{$tempTable}` LIKE `{$mainTable}`");
                } catch (Exception $e) {
                    if (class_exists('Security')) {
                        Security::logSecurityEvent('CREATE_TEMP_TABLE_FAILED', [
                            'temp' => $tempTable,
                            'main' => $mainTable,
                            'error' => $e->getMessage()
                        ], 'HIGH');
                    }
                    continue;
                }

                // Nettoyage de la structure temp: drop colonnes non nécessaires
                foreach (['id', 'created_at', 'updated_at', 'is_provisional'] as $col) {
                    try {
                        $columnExistsStmt->execute([$tempTable, $col]);
                        $exists = ((int)$columnExistsStmt->fetchColumn()) > 0;
                        $columnExistsStmt->closeCursor();
                        if ($exists) {
                            $db->exec("ALTER TABLE `{$tempTable}` DROP COLUMN `{$col}`");
                        }
                    } catch (Exception $e) {
                        // non bloquant
                    }
                }

                // Supprimer indexes uniques copiés
                try {
                    $uniqueIndexesStmt->execute([$tempTable]);
                    $indexes = $uniqueIndexesStmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
                    $uniqueIndexesStmt->closeCursor();
                    foreach ($indexes as $idxName) {
                        $db->exec("ALTER TABLE `{$tempTable}` DROP INDEX `{$idxName}`");
                    }
                } catch (Exception $e) {
                    // non bloquant
                }

                // Supprimer clé primaire sur temp si présente
                try {
                    $hasPrimaryStmt->execute([$tempTable]);
                    $hasPk = ((int)$hasPrimaryStmt->fetchColumn()) > 0;
                    $hasPrimaryStmt->closeCursor();
                    if ($hasPk) {
                        $db->exec("ALTER TABLE `{$tempTable}` DROP PRIMARY KEY");
                    }
                } catch (Exception $e) {
                    // non bloquant
                }
            }
        }
    }
    
    /**
     * Gestion des actions administratives
     */
    private function handleAction() {
        // Validation CSRF obligatoire
        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            Security::logSecurityEvent('CSRF_TOKEN_INVALID', [
                'action' => $_POST['action'] ?? 'unknown',
                'user_id' => $_SESSION['user']['id'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ], 'HIGH');
            $this->message = "Token de sécurité invalide";
            $this->messageType = "error";
            return;
        }

        // Validation et sanitisation de l'action
        $action = Security::sanitizeInput($_POST['action'] ?? '', 'username');
        $allowedActions = ['check', 'force', 'refresh', 'clear_logs', 'migrate_to_main', 'verify_migration', 'clear_temp_tables', 'clear_provisional_data', 'clear_cache', 'test_db_connection', 'transfer_provisoire_to_main', 'db_diag', 'diagnostic', 'direct_import'];
        
        if (!in_array($action, $allowedActions, true)) {
            Security::logSecurityEvent('INVALID_ACTION_ATTEMPT', [
                'action' => $action,
                'user_id' => $_SESSION['user']['id'] ?? null
            ], 'HIGH');
            $this->message = "Action non autorisée";
            $this->messageType = "error";
            return;
        }

        try {
            switch ($action) {
                case 'check':
                    $this->handleCheckAction();
                    break;
                case 'force':
                    $this->handleForceAction();
                    break;
                case 'refresh':
                    $this->handleRefreshAction();
                    break;
                case 'diagnostic':
                    $this->handleDiagnosticAction();
                    break;
                case 'direct_import':
                    $this->handleDirectImportAction();
                    break;
                case 'clear_logs':
                    $this->handleClearLogsAction();
                    break;
                case 'migrate_to_main':
                    $this->handleMigrateAction();
                    break;
                case 'verify_migration':
                    $this->handleVerifyMigrationAction();
                    break;
                case 'clear_temp_tables':
                    $this->handleClearTempTablesAction();
                    break;
                case 'clear_provisional_data':
                    $this->handleClearProvisionalDataAction();
                    break;
                case 'clear_cache':
                    $this->handleClearCacheAction();
                    break;
                case 'test_db_connection':
                    $this->handleTestDbConnectionAction();
                    break;
                case 'transfer_provisoire_to_main':
                    $this->handleTransferProvisoireAction();
                    break;
                case 'db_diag':
                    $this->handleDbDiagAction();
                    break;
            }
        } catch (Exception $e) {
            Security::logSecurityEvent('ADMIN_ACTION_ERROR', [
                'action' => $action,
                'user_id' => $_SESSION['user']['id'],
                'error' => $e->getMessage()
            ], 'HIGH');
            $this->message = "Erreur lors de l'exécution de l'action";
            $this->messageType = "error";
        }
    }
    
    /**
     * Action de vérification des changements
     */
    private function handleCheckAction() {
        $script_path = realpath(__DIR__ . '/../tools/import/update_temp_tables.php');
        if (!$script_path || !file_exists($script_path)) {
            $this->message = "Script tools/import/update_temp_tables.php non trouvé";
            $this->messageType = "error";
            return;
        }
        
        require_once $script_path;
        $manager = new TempTablesManager(true);
        $result = $manager->checkAndUpdate(false);
        
        $this->message = $result['changes_detected'] 
            ? "Mise à jour effectuée en {$result['duration']}s" 
            : "Aucune modification détectée ({$result['duration']}s)";
        $this->messageType = "success";
        $this->status = $manager->getStatus();
        $_SESSION['temp_tables_operation_details'] = $result;
    }
    
    /**
     * Action de mise à jour forcée
     */
    private function handleForceAction() {
        $script_path = realpath(__DIR__ . '/../tools/import/update_temp_tables.php');
        if (!$script_path || !file_exists($script_path)) {
            $this->message = "Script tools/import/update_temp_tables.php non trouvé";
            $this->messageType = "error";
            return;
        }
        
        require_once $script_path;
        $manager = new TempTablesManager(true);
        $result = $manager->checkAndUpdate(true);
        
        $this->message = "Mise à jour forcée terminée en {$result['duration']}s";
        $this->messageType = "success";
        $this->status = $manager->getStatus();
        $_SESSION['temp_tables_operation_details'] = $result;
    }
    
    /**
     * Action de rafraîchissement du statut
     */
    private function handleRefreshAction() {
        $script_path = realpath(__DIR__ . '/../tools/import/update_temp_tables.php');
        if (!$script_path || !file_exists($script_path)) {
            $this->message = "Script tools/import/update_temp_tables.php non trouvé";
            $this->messageType = "error";
            return;
        }

        require_once $script_path;
        $manager = new TempTablesManager();
        $this->status = $manager->getStatus();
        $this->message = "Statut actualisé";
        $this->messageType = "success";
    }

    /**
     * Action de diagnostic de la configuration CSV
     */
    private function handleDiagnosticAction() {
        try {
            $script_path = realpath(__DIR__ . '/../tools/import/update_temp_tables.php');
            if (!$script_path || !file_exists($script_path)) {
                $this->message = "Script tools/import/update_temp_tables.php non trouvé";
                $this->messageType = "error";
                return;
            }

            require_once $script_path;

            $manager = new TempTablesManager(false); // Mode verbose pour le diagnostic
            $diagnostic = $manager->runDiagnostics();

            // Stocker les résultats dans la session pour affichage
            $_SESSION['diagnostic_results'] = $diagnostic;

            $this->message = "Diagnostic terminé - voir les détails ci-dessous";
            $this->messageType = "success";

            // Préparer les informations pour l'affichage
            $this->diagnosticResults = $diagnostic;

        } catch (Exception $e) {
            Security::logSecurityEvent('DIAGNOSTIC_ERROR', [
                'user_id' => $_SESSION['user']['id'],
                'error' => $e->getMessage()
            ], 'MEDIUM');

            $this->message = "Erreur lors du diagnostic: " . $e->getMessage();
            $this->messageType = "error";
        }
    }

    /**
     * Action d'import direct - Solution de contournement pour les timeouts AJAX
     */
    private function handleDirectImportAction() {
        try {
            // Démarrer un processus en arrière-plan
            $script_path = realpath(__DIR__ . '/../tools/import/update_temp_tables.php');

            if (!$script_path || !file_exists($script_path)) {
                $this->message = "Script tools/import/update_temp_tables.php non trouvé";
                $this->messageType = "error";
                return;
            }

            // Créer un fichier de flag pour suivre la progression
            $progress_file = __DIR__ . '/../data/temp_import_progress.json';
            $progress_data = [
                'status' => 'running',
                'start_time' => time(),
                'action' => 'force',
                'user_id' => $_SESSION['user']['id'],
                'message' => 'Import en cours...'
            ];
            file_put_contents($progress_file, json_encode($progress_data));

            // Lancer l'import en arrière-plan (Windows)
            $command = "start /B C:\\xampp\\php\\php.exe \"$script_path\" action=force > nul 2>&1";

            // Alternative : utiliser popen pour exécuter en arrière-plan
            $handle = popen($command, 'r');
            if ($handle) {
                pclose($handle);

                $this->message = "Import lancé en arrière-plan - Actualisez la page pour voir la progression";
                $this->messageType = "success";

                // Stocker l'info de progression dans la session
                $_SESSION['import_in_progress'] = true;
                $_SESSION['import_start_time'] = time();
            } else {
                $this->message = "Impossible de lancer l'import en arrière-plan";
                $this->messageType = "error";
            }

        } catch (Exception $e) {
            Security::logSecurityEvent('DIRECT_IMPORT_ERROR', [
                'user_id' => $_SESSION['user']['id'],
                'error' => $e->getMessage()
            ], 'HIGH');

            $this->message = "Erreur lors du lancement de l'import: " . $e->getMessage();
            $this->messageType = "error";
        }
    }
    
    /**
     * Action de vidage des logs
     */
    private function handleClearLogsAction() {
        $log_file = realpath(__DIR__ . '/../data/logs/temp_tables_update.log');
        $allowed_dir = realpath(__DIR__ . '/../data/logs');
        
        if ($log_file && $allowed_dir && strpos($log_file, $allowed_dir) === 0) {
            if (file_exists($log_file)) {
                if (file_put_contents($log_file, '') !== false) {
                    $this->message = "Logs supprimés avec succès";
                    $this->messageType = "success";
                    $this->logs = [];
                    Security::logSecurityEvent('LOGS_CLEARED', [
                        'user_id' => $_SESSION['user']['id']
                    ], 'INFO');
                } else {
                    $this->message = "Erreur lors de la suppression des logs";
                    $this->messageType = "error";
                }
            } else {
                $this->message = "Aucun fichier de log à supprimer";
                $this->messageType = "success";
                $this->logs = [];
            }
        } else {
            Security::logSecurityEvent('PATH_TRAVERSAL_ATTEMPT', [
                'attempted_path' => $log_file,
                'user_id' => $_SESSION['user']['id']
            ], 'HIGH');
            $this->message = "Chemin de fichier non autorisé";
            $this->messageType = "error";
        }
    }
    
    /**
     * Action de migration vers tables principales
     */
    private function handleMigrateAction() {
        require_once __DIR__ . '/../tools/migration/migrate_temp_to_main.php';
        
        $migrator = new TempToMainMigrator(true);
        $migrationResult = $migrator->migrateAllSilent();
        $migrator->close();
        
        if ($migrationResult['success']) {
            $totalMigrated = (int)$migrationResult['total_migrated'];
            
            require_once __DIR__ . '/../tools/diagnostic/verify_migration.php';
            $verifier = new MigrationVerifier(true);
            $verificationResult = $verifier->verifyMigrationSilent();
            $verifier->close();
            
            $this->migrationMessage = "Migration terminée ! " . number_format($totalMigrated) . " enregistrements transférés avec succès";
            $this->migrationMessageType = "success";
            
            Security::logSecurityEvent('MIGRATION_SUCCESS', [
                'user_id' => $_SESSION['user']['id'],
                'records_migrated' => $totalMigrated
            ], 'INFO');
        } else {
            $this->migrationMessage = "Migration partiellement réussie. Vérifiez les logs pour plus de détails.";
            $this->migrationMessageType = "warning";
        }
    }
    
    /**
     * Action de vérification de migration
     */
    private function handleVerifyMigrationAction() {
        require_once __DIR__ . '/../tools/diagnostic/verify_migration.php';
        
        $verifier = new MigrationVerifier(true);
        $verificationResult = $verifier->verifyMigrationSilent();
        $verifier->close();
        
        if ($verificationResult['success']) {
            $totalProvisional = (int)$verificationResult['summary']['total_provisional'];
            $totalRecords = (int)$verificationResult['summary']['total_records'];
            $percentage = $totalRecords > 0 ? round(($totalProvisional / $totalRecords) * 100, 1) : 0;
            
            $this->migrationMessage = "Vérification terminée ! " . number_format($totalProvisional) . " enregistrements provisoires détectés ({$percentage}% du total)";
            $this->migrationMessageType = "success";
        } else {
            $this->migrationMessage = "Problèmes détectés lors de la vérification. Consultez les logs.";
            $this->migrationMessageType = "warning";
        }
    }
    
    /**
     * Action de vidage des tables temporaires
     */
    private function handleClearTempTablesAction() {
        try {
            $fluxDb = CantalDestinationDatabase::getInstance();
            $db = $fluxDb->getConnection();
        } catch (Exception $e) {
            $this->migrationMessage = "Erreur de connexion à la base de données: " . $e->getMessage();
            $this->migrationMessageType = "error";
            return;
        }
        
        // Liste blanche des tables autorisées
        $allowedTables = [
            'fact_nuitees_departements_temp',
            'fact_nuitees_pays_temp', 
            'fact_nuitees_temp',
            'fact_diurnes_departements_temp',
            'fact_diurnes_pays_temp',
            'fact_diurnes_temp',
            'fact_diurnes_geolife_temp'
        ];
        
        $totalCleared = 0;
        $clearedTables = [];
        
        foreach ($allowedTables as $table) {
            // Vérifier si la table existe avec requête préparée (PDO)
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$table]);
            $result = $stmt->fetch();
            $tableExists = $result['count'] > 0;
            
            if ($tableExists) {
                // Compter les enregistrements avant suppression
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM `$table`");
                $stmt->execute();
                $result = $stmt->fetch();
                $count = (int)$result['count'];
                
                if ($count > 0) {
                    // Vider la table (TRUNCATE est sûr car on contrôle le nom de table)
                    if ($db->exec("TRUNCATE TABLE `$table`")) {
                        $totalCleared += $count;
                        $clearedTables[] = "$table ($count enregistrements)";
                    }
                }
            }
        }
        
        if ($totalCleared > 0) {
            $this->migrationMessage = "Tables temporaires vidées avec succès ! " . number_format($totalCleared) . " enregistrements supprimés de " . count($clearedTables) . " tables";
            $this->migrationMessageType = "success";
            
            Security::logSecurityEvent('TEMP_TABLES_CLEARED', [
                'user_id' => $_SESSION['user']['id'],
                'records_cleared' => $totalCleared,
                'tables_cleared' => count($clearedTables)
            ], 'INFO');
        } else {
            $this->migrationMessage = "Aucun enregistrement à supprimer - Les tables temporaires sont déjà vides";
            $this->migrationMessageType = "success";
        }
    }
    
    /**
     * Action de suppression des données provisoires dans les tables principales
     */
    private function handleClearProvisionalDataAction() {
        try {
            $fluxDb = CantalDestinationDatabase::getInstance();
            $db = $fluxDb->getConnection();
        } catch (Exception $e) {
            $this->migrationMessage = "Erreur de connexion à la base de données: " . $e->getMessage();
            $this->migrationMessageType = "error";
            return;
        }
        
        // Liste des tables principales avec données provisoires
        $mainTables = [
            'fact_nuitees_departements',
            'fact_nuitees_pays', 
            'fact_nuitees',
            'fact_nuitees_age',
            'fact_nuitees_geolife',
            'fact_nuitees_regions',
            'fact_diurnes_departements',
            'fact_diurnes_pays',
            'fact_diurnes',
            'fact_diurnes_age',
            'fact_diurnes_geolife',
            'fact_diurnes_regions'
        ];
        
        $totalDeleted = 0;
        $deletedTables = [];
        $errors = [];

        foreach ($mainTables as $table) {
            // Vérifier si la table existe et a la colonne is_provisional (PDO)
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'is_provisional'");
            $stmt->execute([$table]);
            $result = $stmt->fetch();
            $hasProvisionalColumn = $result['count'] > 0;
            
            if ($hasProvisionalColumn) {
                // Compter les enregistrements provisoires avant suppression
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM `$table` WHERE is_provisional = 1");
                $stmt->execute();
                $result = $stmt->fetch();
                $count = (int)$result['count'];
                
                if ($count > 0) {
                    // Supprimer les données provisoires par lots pour éviter les timeouts
                    $batchSize = 1000;
                    $deletedForTable = 0;
                    $batchesProcessed = 0;
                    $maxBatches = 1000; // Sécurité pour éviter les boucles infinies

                    while ($count > 0 && $batchesProcessed < $maxBatches) {
                        try {
                            $stmt = $db->prepare("DELETE FROM `$table` WHERE is_provisional = 1 LIMIT ?");
                            $stmt->execute([min($batchSize, $count)]);
                            $batchDeleted = $stmt->rowCount();
                            $deletedForTable += $batchDeleted;
                            $count -= $batchDeleted;
                            $batchesProcessed++;

                            if ($batchDeleted === 0) break; // Plus rien à supprimer
                        } catch (Exception $e) {
                            $errors[] = "Erreur batch sur $table: " . $e->getMessage();
                            break; // Arrêter cette table en cas d'erreur
                        }
                    }

                    $totalDeleted += $deletedForTable;
                    $deletedTables[] = "$table ($deletedForTable enregistrements)";
                }
            }
        }
        
        // Préparer le message final
        if ($totalDeleted > 0) {
            $message = "Données provisoires supprimées avec succès ! " . number_format($totalDeleted) . " enregistrements supprimés de " . count($deletedTables) . " tables principales";

            if (!empty($errors)) {
                $message .= " (Quelques erreurs mineures détectées mais la suppression s'est poursuivie)";
            }

            $this->migrationMessage = $message;
            $this->migrationMessageType = "success";

            Security::logSecurityEvent('PROVISIONAL_DATA_CLEARED', [
                'user_id' => $_SESSION['user']['id'],
                'records_deleted' => $totalDeleted,
                'tables_affected' => count($deletedTables),
                'details' => $deletedTables,
                'errors' => $errors
            ], 'INFO');
        } else {
            $this->migrationMessage = "Aucune donnée provisoire à supprimer - Les tables principales ne contiennent pas de données avec is_provisional = 1";
            $this->migrationMessageType = "success";
        }

        // Log des erreurs si présentes
        if (!empty($errors)) {
            foreach ($errors as $error) {
                error_log("AdminTempTables ClearProvisional: $error");
            }
        }
    }
    
    /**
     * Action de vidage du cache API
     */
    private function handleClearCacheAction() {
        $cache_dir = realpath(__DIR__ . '/../api/cache');
        $allowed_dir = realpath(__DIR__ . '/../api');
        
        if (!$cache_dir || !$allowed_dir || strpos($cache_dir, $allowed_dir) !== 0) {
            Security::logSecurityEvent('INVALID_CACHE_DIRECTORY', [
                'attempted_dir' => __DIR__ . '/../api/cache',
                'user_id' => $_SESSION['user']['id']
            ], 'HIGH');
            $this->message = "Répertoire de cache non accessible";
            $this->messageType = "error";
            return;
        }
        
        $totalDeleted = 0;
        $totalSize = 0;
        $deletedFiles = [];
        
        try {
            // Fonction récursive pour supprimer les fichiers de cache
            $this->clearCacheDirectory($cache_dir, $totalDeleted, $totalSize, $deletedFiles);
            
            if ($totalDeleted > 0) {
                $this->message = "Cache vidé avec succès ! " . number_format($totalDeleted) . " fichiers supprimés (" . $this->formatBytes($totalSize) . " libérés)";
                $this->messageType = "success";
                
                Security::logSecurityEvent('CACHE_CLEARED', [
                    'user_id' => $_SESSION['user']['id'],
                    'files_deleted' => $totalDeleted,
                    'size_freed' => $totalSize,
                    'cache_dir' => $cache_dir
                ], 'INFO');
            } else {
                $this->message = "Aucun fichier de cache à supprimer - Le cache est déjà vide";
                $this->messageType = "success";
            }
        } catch (Exception $e) {
            Security::logSecurityEvent('CACHE_CLEAR_ERROR', [
                'user_id' => $_SESSION['user']['id'],
                'error' => $e->getMessage(),
                'cache_dir' => $cache_dir
            ], 'HIGH');
            $this->message = "Erreur lors du vidage du cache";
            $this->messageType = "error";
        }
    }
    
    /**
     * Fonction récursive pour vider un répertoire de cache
     */
    private function clearCacheDirectory($dir, &$totalDeleted, &$totalSize, &$deletedFiles) {
        if (!is_dir($dir)) {
            return;
        }
        
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $itemPath = $dir . DIRECTORY_SEPARATOR . $item;
            $realItemPath = realpath($itemPath);
            
            // Vérification de sécurité pour éviter les path traversal
            if (!$realItemPath || strpos($realItemPath, realpath(__DIR__ . '/../api/cache')) !== 0) {
                continue;
            }
            
            if (is_dir($realItemPath)) {
                // Récursion pour les sous-dossiers
                $this->clearCacheDirectory($realItemPath, $totalDeleted, $totalSize, $deletedFiles);
                
                // Supprimer le dossier s'il est vide
                if ($this->isDirEmpty($realItemPath)) {
                    rmdir($realItemPath);
                }
            } elseif (is_file($realItemPath)) {
                // Supprimer les fichiers de cache (JSON principalement)
                $fileSize = filesize($realItemPath);
                if (unlink($realItemPath)) {
                    $totalDeleted++;
                    $totalSize += $fileSize;
                    $deletedFiles[] = basename($realItemPath);
                }
            }
        }
    }
    
    /**
     * Vérifier si un répertoire est vide
     */
    private function isDirEmpty($dir) {
        $items = scandir($dir);
        return $items !== false && count($items) <= 2; // Seulement . et ..
    }
    
    /**
     * Formater la taille en octets en format lisible
     */
    private function formatBytes($size) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }
        
        return round($size, 2) . ' ' . $units[$unitIndex];
    }
    
    /**
     * Gestion des actions sur les fichiers
     */
    private function handleFileAction() {
        // Validation CSRF obligatoire
        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            Security::logSecurityEvent('CSRF_TOKEN_INVALID', [
                'action' => 'file_action',
                'user_id' => $_SESSION['user']['id'] ?? null
            ], 'HIGH');
            $this->message = "Token de sécurité invalide";
            $this->messageType = "error";
            return;
        }

        // Resolution centralisee du repertoire de donnees
        $data_dir = resolve_data_temp_dir(true);

        if (!$data_dir || !is_dir($data_dir)) {
            Security::logSecurityEvent('INVALID_DATA_DIRECTORY', [
                'attempted_dir' => $data_dir
            ], 'HIGH');
            $this->message = "Répertoire de données non accessible";
            $this->messageType = "error";
            return;
        }

        $file_action = Security::sanitizeInput($_POST['file_action'] ?? '', 'username');
        $allowedFileActions = ['upload', 'delete'];
        
        if (!in_array($file_action, $allowedFileActions, true)) {
            Security::logSecurityEvent('INVALID_FILE_ACTION', [
                'action' => $file_action,
                'user_id' => $_SESSION['user']['id']
            ], 'HIGH');
            $this->message = "Action de fichier non autorisée";
            $this->messageType = "error";
            return;
        }

        try {
            switch ($file_action) {
                case 'upload':
                    $this->handleFileUpload($data_dir);
                    break;
                case 'delete':
                    $this->handleFileDelete($data_dir);
                    break;
            }
        } catch (Exception $e) {
            Security::logSecurityEvent('FILE_ACTION_ERROR', [
                'action' => $file_action,
                'user_id' => $_SESSION['user']['id'],
                'error' => $e->getMessage()
            ], 'HIGH');
            $this->message = "Erreur lors de l'opération sur le fichier";
            $this->messageType = "error";
        }
    }
    
    /**
     * Gestion de l'upload de fichier
     */
    private function handleFileUpload($data_dir) {
        // Support multi-fichiers et dossiers (webkitdirectory)
        $max_size = 10 * 1024 * 1024; // 10MB par fichier
        $success_count = 0;
        $errors = [];

        $processOne = function($original_name, $tmp_name, $size, $type, $error) use ($data_dir, $max_size, &$success_count, &$errors) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'Fichier trop volumineux (limite serveur)',
                UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux (limite formulaire)',
                UPLOAD_ERR_PARTIAL => 'Upload partiel',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier sélectionné',
                UPLOAD_ERR_NO_TMP_DIR => 'Répertoire temporaire manquant',
                UPLOAD_ERR_CANT_WRITE => 'Erreur d\'écriture',
                UPLOAD_ERR_EXTENSION => 'Extension bloquée'
            ];
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = ($upload_errors[$error] ?? 'Erreur inconnue') . " : " . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8');
                return;
            }
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                $errors[] = "Extension non autorisée (seulement .csv) : " . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8');
                Security::logSecurityEvent('INVALID_FILE_EXTENSION', [
                    'filename' => $original_name,
                    'extension' => $ext,
                    'user_id' => $_SESSION['user']['id']
                ], 'MEDIUM');
                return;
            }
            if ($size > $max_size) {
                $errors[] = "Fichier trop volumineux (>10MB) : " . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8');
                return;
            }
            $normalizedType = strtolower((string)$type);
            $allowedTypes = [
                'text/csv', 'application/csv', 'text/plain',
                'application/vnd.ms-excel', 'text/x-csv', 'application/x-csv',
                'text/comma-separated-values', 'application/octet-stream'
            ];
            if ($normalizedType !== '' && !in_array($normalizedType, $allowedTypes, true)) {
                $errors[] = "MIME non autorisé : " . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8');
                Security::logSecurityEvent('INVALID_MIME_TYPE', [
                    'filename' => $original_name,
                    'mime_type' => $type,
                    'user_id' => $_SESSION['user']['id']
                ], 'MEDIUM');
                return;
            }
            if (!$this->isValidCSVContent($tmp_name)) {
                $errors[] = "Contenu CSV invalide : " . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8');
                return;
            }
            $safeRelPath = $this->buildSafeRelativePath($original_name);
            if ($safeRelPath === '') {
                $errors[] = "Nom de fichier invalide : " . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8');
                return;
            }
            $target_path = $data_dir . '/' . $safeRelPath;
            $target_dir = dirname($target_path);
            if (!is_dir($target_dir)) {
                if (!mkdir($target_dir, 0755, true)) {
                    $errors[] = "Impossible de créer le dossier cible : " . htmlspecialchars($target_dir, ENT_QUOTES, 'UTF-8');
                    return;
                }
            }
            $real_base = realpath($data_dir);
            $real_target_dir = realpath($target_dir);
            if (!$real_base || !$real_target_dir || strpos($real_target_dir, $real_base) !== 0) {
                $errors[] = "Chemin de destination non autorisé";
                return;
            }
            if (move_uploaded_file($tmp_name, $target_path)) {
                chmod($target_path, 0644);
                $success_count++;
                Security::logSecurityEvent('FILE_UPLOADED', [
                    'filename' => $safeRelPath,
                    'size' => $size,
                    'user_id' => $_SESSION['user']['id']
                ], 'INFO');
            } else {
                $errors[] = "Erreur lors de l'upload : " . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8');
            }
        };

        if (isset($_FILES['csv_files']) && is_array($_FILES['csv_files']['name'])) {
            $names = $_FILES['csv_files']['name'];
            $tmp_names = $_FILES['csv_files']['tmp_name'];
            $sizes = $_FILES['csv_files']['size'];
            $types = $_FILES['csv_files']['type'];
            $errorsArr = $_FILES['csv_files']['error'];
            $count = count($names);
            for ($i = 0; $i < $count; $i++) {
                if ($names[$i] === '' || $tmp_names[$i] === '') { continue; }
                $processOne($names[$i], $tmp_names[$i], $sizes[$i], $types[$i], $errorsArr[$i]);
            }
            if ($success_count > 0 && empty($errors)) {
                $this->message = $success_count . " fichier(s) CSV ajouté(s) avec succès";
                $this->messageType = "success";
            } elseif ($success_count > 0 && !empty($errors)) {
                $this->message = $success_count . " fichier(s) importé(s), " . count($errors) . " erreur(s).\n" . implode("\n", $errors);
                $this->messageType = "warning";
            } else {
                $this->message = "Aucun fichier importé.\n" . implode("\n", $errors);
                $this->messageType = "error";
            }
            return;
        }

        if (isset($_FILES['csv_file'])) {
            $processOne(
                $_FILES['csv_file']['name'] ?? '',
                $_FILES['csv_file']['tmp_name'] ?? '',
                $_FILES['csv_file']['size'] ?? 0,
                $_FILES['csv_file']['type'] ?? '',
                $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE
            );
            if ($success_count > 0) {
                $this->message = "Fichier importé avec succès";
                $this->messageType = "success";
            } else {
                $this->message = !empty($errors) ? implode("\n", $errors) : "Aucun fichier importé";
                $this->messageType = "error";
            }
            return;
        }

        $this->message = "Aucun fichier reçu";
        $this->messageType = "error";
    }

    /**
     * Construit un chemin relatif sûr (préserve l'arborescence fournie, filtre)
     */
    private function buildSafeRelativePath($original_name) {
        $path = str_replace('\\\\', '/', $original_name);
        $parts = array_filter(explode('/', $path), function($seg) {
            return $seg !== '' && $seg !== '.' && $seg !== '..';
        });
        $safeParts = [];
        foreach ($parts as $seg) {
            $clean = preg_replace('/[^a-zA-Z0-9._-]/', '', $seg);
            if ($clean === '') { continue; }
            $safeParts[] = $clean;
        }
        $safe = implode('/', $safeParts);
        if (strtolower(pathinfo($safe, PATHINFO_EXTENSION)) !== 'csv') {
            return '';
        }
        return $safe;
    }
    
    /**
     * Gestion de la suppression de fichier
     */
    private function handleFileDelete($data_dir) {
        $filename = Security::sanitizeInput($_POST['filename'] ?? '', 'username');
        
        if (empty($filename)) {
            $this->message = "Nom de fichier manquant";
            $this->messageType = "error";
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9._-]+\.csv$/', $filename)) {
            Security::logSecurityEvent('INVALID_FILENAME_PATTERN', [
                'filename' => $filename,
                'user_id' => $_SESSION['user']['id']
            ], 'HIGH');
            $this->message = "Nom de fichier invalide";
            $this->messageType = "error";
            return;
        }

        $file_path = $data_dir . '/' . $filename;
        $real_file_path = realpath($file_path);
        
        if ($real_file_path && strpos($real_file_path, $data_dir) === 0 && file_exists($real_file_path)) {
            if (unlink($real_file_path)) {
                $this->message = "Fichier '" . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . "' supprimé avec succès";
                $this->messageType = "success";
                
                Security::logSecurityEvent('FILE_DELETED', [
                    'filename' => $filename,
                    'user_id' => $_SESSION['user']['id']
                ], 'INFO');
            } else {
                $this->message = "Erreur lors de la suppression du fichier";
                $this->messageType = "error";
            }
        } else {
            Security::logSecurityEvent('PATH_TRAVERSAL_ATTEMPT', [
                'attempted_path' => $file_path,
                'real_path' => $real_file_path,
                'user_id' => $_SESSION['user']['id']
            ], 'HIGH');
            $this->message = "Fichier non trouvé ou accès refusé";
            $this->messageType = "error";
        }
    }
    
    /**
     * Calcul des statistiques
     */
    private function calculateStats() {
        // Resolution centralisee du repertoire de donnees
        $data_dir = resolve_data_temp_dir();
        $candidate_dirs = array_unique(array_filter([
            $data_dir,
            DATA_TEMP_PRIMARY_PATH,
            DATA_TEMP_LEGACY_PATH
        ]));

        $files_config = [
            'frequentation_nuitee_fr.csv' => 'fact_nuitees_departements_temp',
            'frequentation_nuitee_int.csv' => 'fact_nuitees_pays_temp',
            'frequentation_nuitee.csv' => 'fact_nuitees_temp',
            'frequentation_journee_fr.csv' => 'fact_diurnes_departements_temp',
            'frequentation_journee_int.csv' => 'fact_diurnes_pays_temp',
            'frequentation_journee.csv' => 'fact_diurnes_geolife_temp'
        ];

        $total_files = count($files_config);
        $existing_files = 0;
        $total_size = 0;

        foreach ($files_config as $filename => $table_name) {
            $filepath = null;
            foreach ($candidate_dirs as $dir) {
                $testPath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
                if (is_file($testPath)) {
                    $filepath = $testPath;
                    break;
                }
            }

            if ($filepath) {
                $existing_files++;
                $total_size += filesize($filepath);
            }
        }

        $this->stats = [
            'total_files' => $total_files,
            'existing_files' => $existing_files,
            'total_size' => $total_size,
            'files_config' => $files_config,
            'data_dir' => $data_dir
        ];

        // Diagnostic DB rapide (sans MCP): structures et volumes clés
        try {
            $fluxDb = CantalDestinationDatabase::getInstance();
            $db = $fluxDb->getConnection();
            $diag = [
                'tables_presentes' => [],
                'provisional_counts' => [],
                'temp_counts' => []
            ];
            $tables = [
                'fact_diurnes','fact_diurnes_departements','fact_diurnes_pays',
                'fact_nuitees','fact_nuitees_departements','fact_nuitees_pays',
                'fact_diurnes_temp','fact_diurnes_departements_temp','fact_diurnes_pays_temp',
                'fact_nuitees_temp','fact_nuitees_departements_temp','fact_nuitees_pays_temp'
            ];
            foreach ($tables as $t) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                $stmt->execute([$t]);
                $exists = (int)$stmt->fetchColumn() > 0;
                $diag['tables_presentes'][$t] = $exists;
            }
            // counts temp
            foreach (['fact_diurnes_temp','fact_diurnes_departements_temp','fact_diurnes_pays_temp','fact_nuitees_temp','fact_nuitees_departements_temp','fact_nuitees_pays_temp'] as $t) {
                if (!empty($diag['tables_presentes'][$t])) {
                    $stmt = $db->query("SELECT COUNT(*) FROM `$t`");
                    $diag['temp_counts'][$t] = (int)$stmt->fetchColumn();
                }
            }
            // counts provisional
            foreach (['fact_diurnes','fact_diurnes_departements','fact_diurnes_pays','fact_nuitees','fact_nuitees_departements','fact_nuitees_pays'] as $t) {
                if (!empty($diag['tables_presentes'][$t])) {
                    // vérifier colonne is_provisional
                    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'is_provisional'");
                    $stmt->execute([$t]);
                    $hasProv = (int)$stmt->fetchColumn() > 0;
                    if ($hasProv) {
                        $stmt2 = $db->query("SELECT COUNT(*) FROM `$t` WHERE is_provisional = 1");
                        $diag['provisional_counts'][$t] = (int)$stmt2->fetchColumn();
                    } else {
                        $diag['provisional_counts'][$t] = null;
                    }
                }
            }
            $this->db_diag = $diag;
        } catch (Exception $e) {
            $this->db_diag = ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Chargement des logs
     */
    private function loadLogs() {
        $log_dir = realpath(__DIR__ . '/../data/logs');

        // Logs update temp tables
        $update_log = realpath(__DIR__ . '/../data/logs/temp_tables_update.log');
        if ($update_log && $log_dir && strpos($update_log, $log_dir) === 0 && file_exists($update_log)) {
            $log_lines = file($update_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($log_lines !== false) {
                $this->logs = array_map(function($line) {
                    return htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
                }, array_slice(array_reverse($log_lines), 0, 50));
                $this->log_info = [
                    'size' => filesize($update_log),
                    'modified' => filemtime($update_log),
                    'total_lines' => count($log_lines)
                ];
            }
        }

        // Logs migration temp -> main
        $migration_log = realpath(__DIR__ . '/../data/logs/migration_temp_to_main.log');
        if ($migration_log && $log_dir && strpos($migration_log, $log_dir) === 0 && file_exists($migration_log)) {
            $log_lines = file($migration_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($log_lines !== false) {
                $this->migration_logs = array_map(function($line) {
                    return htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
                }, array_slice(array_reverse($log_lines), 0, 200));
                $this->migration_log_info = [
                    'size' => filesize($migration_log),
                    'modified' => filemtime($migration_log),
                    'total_lines' => count($log_lines)
                ];
            }
        }
    }
    
    /**
     * Validation du contenu CSV
     */
    private function isValidCSVContent($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        // Lire un petit chunk de début pour valider
        $content = @file_get_contents($file_path, false, null, 0, 8192);
        if ($content === false || $content === '') {
            return false;
        }
        // Tolérer ISO-8859-1 et convertir vers UTF-8 si nécessaire
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }
        // Première ligne non vide
        $lines = preg_split('/\r\n|\n|\r/', $content);
        $firstNonEmpty = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') { $firstNonEmpty = $line; break; }
        }
        if ($firstNonEmpty === '') {
            return false;
        }
        // Doit contenir un séparateur CSV courant
        if (strpos($firstNonEmpty, ';') === false && strpos($firstNonEmpty, ',') === false) {
            return false;
        }
        return true;
    }

    /**
     * Action de test de connexion à la base de données
     */
    private function handleTestDbConnectionAction() {
        try {
            $testResult = DatabaseConfig::testConnection();
            
            if ($testResult['success']) {
                $this->message = $testResult['message'] . " (Base: {$testResult['database']}, Serveur: {$testResult['server_info']})";
                $this->messageType = "success";
                
                Security::logSecurityEvent('DB_CONNECTION_TEST_SUCCESS', [
                    'user_id' => $_SESSION['user']['id'],
                    'environment' => $testResult['environment'],
                    'database' => $testResult['database']
                ], 'INFO');
            } else {
                $this->message = $testResult['message'] . " - " . $testResult['error'];
                $this->messageType = "error";
                
                Security::logSecurityEvent('DB_CONNECTION_TEST_FAILED', [
                    'user_id' => $_SESSION['user']['id'],
                    'environment' => $testResult['environment'],
                    'error' => $testResult['error']
                ], 'MEDIUM');
            }
        } catch (Exception $e) {
            $this->message = "Erreur lors du test de connexion: " . $e->getMessage();
            $this->messageType = "error";
            
            Security::logSecurityEvent('DB_CONNECTION_TEST_ERROR', [
                'user_id' => $_SESSION['user']['id'],
                'error' => $e->getMessage()
            ], 'HIGH');
        }
    }

    /**
     * Liste les tables *_provisoire existantes dans la base
     */
    public function getProvisoireTables() {
        try {
            $fluxDb = CantalDestinationDatabase::getInstance();
            $db = $fluxDb->getConnection();
            $stmt = $db->query("SHOW TABLES LIKE 'fact\\_%\\_provisoire'");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            return $tables;
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des tables provisoires: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Transfert les données d'une table *_provisoire vers la table principale
     */
    private function handleTransferProvisoireAction() {
        $table = $_POST['provisoire_table'] ?? '';
        if (!preg_match('/^fact_[a-z_]+_provisoire$/', $table)) {
            $this->transferMessage = "Table non valide.";
            $this->transferMessageType = "error";
            return;
        }
        $mainTable = preg_replace('/_provisoire$/', '', $table);
        try {
            $fluxDb = CantalDestinationDatabase::getInstance();
            $db = $fluxDb->getConnection();
            
            // Obtenir la structure de la table provisoire
            $cols = $db->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
            $proviColumns = array_column($cols, 'Field');
            
            // Obtenir la structure de la table principale (sans id, created_at)
            $cols = $db->query("DESCRIBE `$mainTable`")->fetchAll(PDO::FETCH_ASSOC);
            $mainColumns = array_column($cols, 'Field');
            $mainColumns = array_filter($mainColumns, fn($col) => !in_array($col, ['id', 'created_at']));
            
            // Colonnes communes entre les deux tables (y compris is_provisional si elle existe dans les deux)
            $commonColumns = array_intersect($proviColumns, $mainColumns);
            
            if (empty($commonColumns)) {
                $this->transferMessage = "Aucune colonne commune entre $table et $mainTable.";
                $this->transferMessageType = "error";
                return;
            }
            
            // Construire la requête d'insertion avec gestion des doublons
            $columnsList = implode(', ', array_map(fn($c) => "`$c`", $commonColumns));
            
            $sql = "INSERT IGNORE INTO `$mainTable` ($columnsList) 
                    SELECT $columnsList 
                    FROM `$table`";
            
            $count = $db->exec($sql);
            $this->transferMessage = "Transfert terminé : $count lignes transférées de $table vers $mainTable (doublons ignorés).";
            $this->transferMessageType = "success";
            
            // Log de sécurité
            Security::logSecurityEvent('PROVISOIRE_TRANSFER_SUCCESS', [
                'user_id' => $_SESSION['user']['id'],
                'source_table' => $table,
                'target_table' => $mainTable,
                'records_transferred' => $count,
                'columns_transferred' => $commonColumns
            ], 'INFO');
            
        } catch (Exception $e) {
            $this->transferMessage = "Erreur lors du transfert : " . $e->getMessage();
            $this->transferMessageType = "error";
            
            Security::logSecurityEvent('PROVISOIRE_TRANSFER_ERROR', [
                'user_id' => $_SESSION['user']['id'],
                'source_table' => $table,
                'target_table' => $mainTable,
                'error' => $e->getMessage()
            ], 'HIGH');
        }
    }
} 
