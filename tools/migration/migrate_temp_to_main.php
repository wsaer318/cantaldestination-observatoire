<?php
/**
 * Migration des Tables Temporaires vers Tables Principales
 * Cantal Destination - Observatoire du Tourisme
 * 
 * Transfère les données des tables temporaires vers les tables principales
 * avec is_provisional = 1 et évite les doublons
 */

// Configuration pour éviter le timeout sur gros fichiers CSV
set_time_limit(1800); // 30 minutes
ini_set('memory_limit', '1G'); // Augmenter la mémoire
ini_set('mysql.connect_timeout', 300); // 5 minutes timeout de connexion MySQL

// Import de la configuration de base de données
require_once __DIR__ . '/../../config/database.php';

class TempToMainMigrator {
    private $db;
    private $log_file;
    private $silentMode = false;
    
    // Mapping des tables temporaires vers principales
    private $table_mappings = [
        'fact_nuitees_temp' => 'fact_nuitees',
        'fact_nuitees_departements_temp' => 'fact_nuitees_departements',
        'fact_nuitees_pays_temp' => 'fact_nuitees_pays',
        'fact_diurnes_temp' => 'fact_diurnes',
        'fact_diurnes_departements_temp' => 'fact_diurnes_departements',
        'fact_diurnes_pays_temp' => 'fact_diurnes_pays',
        'fact_lieu_activite_soir_temp' => 'fact_lieu_activite_soir',
        'fact_sejours_duree_temp' => 'fact_sejours_duree',
        'fact_sejours_duree_departements_temp' => 'fact_sejours_duree_departements',
        'fact_sejours_duree_pays_temp' => 'fact_sejours_duree_pays'
    ];
    
    public function __construct($silentMode = false) {
        $this->silentMode = $silentMode;
        $this->log_file = __DIR__ . '/../../logs/migration_temp_to_main.log';
        $this->ensureLogDirectory();
        $this->connectDB();
    }
    
    private function ensureLogDirectory() {
        $dir = dirname($this->log_file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    private function connectDB() {
        try {
            // Utiliser la configuration centralisée au lieu de paramètres hardcodés
            $dbConfig = DatabaseConfig::getConfig();
            
            $host = $dbConfig['host'] . ($dbConfig['port'] ? ':' . $dbConfig['port'] : '');
            $this->db = new mysqli($host, $dbConfig['username'], $dbConfig['password'], $dbConfig['database']);
            
            if ($this->db->connect_error) {
                throw new Exception("Connexion échouée: " . $this->db->connect_error);
            }
            
            $this->db->set_charset("utf8mb4");
            $this->log("✅ Connexion à la base de données réussie");
            $this->log("🔧 Configuration DB: $host/" . $dbConfig['database'] . " (user: " . $dbConfig['username'] . ")");
            
        } catch (Exception $e) {
            $this->log("❌ Erreur de connexion: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        
        // N'afficher que si pas en mode silencieux
        if (!$this->silentMode) {
            echo $logMessage;
        }
        
        file_put_contents($this->log_file, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    public function setSilentMode($silent = true) {
        $this->silentMode = $silent;
    }
    
    public function checkTablesExist() {
        $this->log("🔍 Vérification de l'existence des tables...");
        
        $existingTables = [];
        $result = $this->db->query("SHOW TABLES");
        while ($row = $result->fetch_array()) {
            $existingTables[] = $row[0];
        }
        
        $missingTables = [];
        foreach ($this->table_mappings as $tempTable => $mainTable) {
            if (!in_array($tempTable, $existingTables)) {
                $missingTables[] = $tempTable . " (temporaire)";
            }
            if (!in_array($mainTable, $existingTables)) {
                $missingTables[] = $mainTable . " (principale)";
            }
        }
        
        if (!empty($missingTables)) {
            $this->log("❌ Tables manquantes: " . implode(', ', $missingTables));
            return false;
        }
        
        $this->log("✅ Toutes les tables existent");
        return true;
    }
    
    public function addIsProvisionalColumn() {
        $this->log("🔧 Vérification/Ajout de la colonne is_provisional...");
        
        foreach ($this->table_mappings as $tempTable => $mainTable) {
            // Vérifier si la colonne existe déjà
            $result = $this->db->query("SHOW COLUMNS FROM `$mainTable` LIKE 'is_provisional'");
            
            if ($result->num_rows == 0) {
                $this->log("➕ Ajout de is_provisional à $mainTable");
                $sql = "ALTER TABLE `$mainTable` ADD COLUMN is_provisional BOOLEAN DEFAULT FALSE NOT NULL";
                
                if (!$this->db->query($sql)) {
                    $this->log("❌ Erreur ajout colonne $mainTable: " . $this->db->error);
                    return false;
                }
                
                // Créer un index sur is_provisional
                $indexSql = "CREATE INDEX IF NOT EXISTS idx_{$mainTable}_provisional ON `$mainTable`(is_provisional)";
                $this->db->query($indexSql);
                
            } else {
                $this->log("✅ Colonne is_provisional existe déjà dans $mainTable");
            }
        }
        
        return true;
    }
    
    public function getTableStats() {
        $this->log("📊 Statistiques des tables avant migration:");
        
        $stats = [];
        foreach ($this->table_mappings as $tempTable => $mainTable) {
            // Compter les enregistrements dans la table temporaire
            $result = $this->db->query("SELECT COUNT(*) as count FROM `$tempTable`");
            $tempCount = $result ? $result->fetch_assoc()['count'] : 0;
            
            // Compter les enregistrements dans la table principale
            $result = $this->db->query("SELECT COUNT(*) as count FROM `$mainTable`");
            $mainCount = $result ? $result->fetch_assoc()['count'] : 0;
            
            // Compter les enregistrements provisoires existants
            $result = $this->db->query("SELECT COUNT(*) as count FROM `$mainTable` WHERE is_provisional = 1");
            $provisionalCount = $result ? $result->fetch_assoc()['count'] : 0;
            
            $stats[$tempTable] = [
                'temp_count' => $tempCount,
                'main_count' => $mainCount,
                'provisional_count' => $provisionalCount
            ];
            
            $this->log("   $tempTable: $tempCount enregistrements");
            $this->log("   $mainTable: $mainCount total ($provisionalCount provisoires)");
        }
        
        return $stats;
    }
    
    public function migrateTable($tempTable, $mainTable) {
        $this->log("🔄 Migration $tempTable → $mainTable...");
        
        // Obtenir la structure de la table temporaire
        $tempColumns = [];
        $result = $this->db->query("DESCRIBE `$tempTable`");
        while ($row = $result->fetch_assoc()) {
            $tempColumns[] = $row['Field'];
        }
        
        // Obtenir la structure de la table principale (sans id, created_at, is_provisional)
        $mainColumns = [];
        $result = $this->db->query("DESCRIBE `$mainTable`");
        while ($row = $result->fetch_assoc()) {
            if (!in_array($row['Field'], ['id', 'created_at', 'is_provisional'])) {
                $mainColumns[] = $row['Field'];
            }
        }
        
        // Colonnes communes entre les deux tables
        $commonColumns = array_intersect($tempColumns, $mainColumns);
        
        if (empty($commonColumns)) {
            $this->log("❌ Aucune colonne commune entre $tempTable et $mainTable");
            return false;
        }
        
        $this->log("📋 Colonnes à migrer: " . implode(', ', $commonColumns));
        
        // Construire la requête d'insertion avec gestion des doublons
        $columnsList = implode(', ', array_map(function($col) { return "`$col`"; }, $commonColumns));
        
        // Utiliser ON DUPLICATE KEY UPDATE pour gérer les doublons et marquer comme provisoire
        $sql = "INSERT INTO `$mainTable` ($columnsList, is_provisional) 
                SELECT $columnsList, 1 as is_provisional 
                FROM `$tempTable` 
                ON DUPLICATE KEY UPDATE is_provisional = VALUES(is_provisional)";
        
        $this->log("🔍 Requête SQL: " . substr($sql, 0, 100) . "...");
        
        // Exécuter la migration
        if ($this->db->query($sql)) {
            $insertedRows = $this->db->affected_rows;
            $this->log("✅ $insertedRows enregistrements migrés vers $mainTable");
            return $insertedRows;
        } else {
            $this->log("❌ Erreur migration $tempTable: " . $this->db->error);
            return false;
        }
    }
    
    public function migrateAll() {
        $this->log("🚀 DÉBUT DE LA MIGRATION COMPLÈTE");
        $this->log("================================");
        
        // Vérifications préalables
        if (!$this->checkTablesExist()) {
            return false;
        }
        
        if (!$this->addIsProvisionalColumn()) {
            return false;
        }
        
        // Statistiques avant migration
        $statsBefore = $this->getTableStats();
        
        // Migration table par table
        $totalMigrated = 0;
        $successCount = 0;
        
        foreach ($this->table_mappings as $tempTable => $mainTable) {
            $result = $this->migrateTable($tempTable, $mainTable);
            
            if ($result !== false) {
                $totalMigrated += $result;
                $successCount++;
            }
        }
        
        // Statistiques après migration
        $this->log("\n📊 STATISTIQUES FINALES:");
        $this->log("========================");
        
        foreach ($this->table_mappings as $tempTable => $mainTable) {
            $result = $this->db->query("SELECT COUNT(*) as count FROM `$mainTable` WHERE is_provisional = 1");
            $provisionalCount = $result ? $result->fetch_assoc()['count'] : 0;
            
            $result = $this->db->query("SELECT COUNT(*) as count FROM `$mainTable`");
            $totalCount = $result ? $result->fetch_assoc()['count'] : 0;
            
            $this->log("✅ $mainTable: $totalCount total ($provisionalCount provisoires)");
        }
        
        $this->log("\n🎯 RÉSUMÉ:");
        $this->log("- Tables migrées avec succès: $successCount/" . count($this->table_mappings));
        $this->log("- Total d'enregistrements migrés: " . number_format($totalMigrated));
        
        if ($successCount == count($this->table_mappings)) {
            $this->log("✅ MIGRATION TERMINÉE AVEC SUCCÈS!");
            return true;
        } else {
            $this->log("⚠️ Migration partiellement réussie");
            return false;
        }
    }
    
    public function verifyMigration() {
        $this->log("\n🔍 VÉRIFICATION DE LA MIGRATION:");
        $this->log("===============================");
        
        foreach ($this->table_mappings as $tempTable => $mainTable) {
            // Compter les enregistrements dans chaque table
            $result = $this->db->query("SELECT COUNT(*) as count FROM `$tempTable`");
            $tempCount = $result ? $result->fetch_assoc()['count'] : 0;
            
            $result = $this->db->query("SELECT COUNT(*) as count FROM `$mainTable` WHERE is_provisional = 1");
            $provisionalCount = $result ? $result->fetch_assoc()['count'] : 0;
            
            if ($tempCount > 0) {
                $migrationRate = round(($provisionalCount / $tempCount) * 100, 1);
                $this->log("📋 $tempTable ($tempCount) → $mainTable ($provisionalCount provisoires) - $migrationRate%");
                
                if ($migrationRate < 100) {
                    $this->log("⚠️ Migration incomplète pour $mainTable");
                }
            } else {
                $this->log("ℹ️ $tempTable est vide");
            }
        }
    }
    
    public function migrateAllSilent() {
        try {
            // Vérifications préliminaires silencieuses (ne bloque pas la migration)
            $this->log("=== MIGRATION SILENCIEUSE: démarrage ===");
            $this->checkTablesExistSilent();
            
            if (!$this->addIsProvisionalColumnSilent()) {
                $this->log("❌ addIsProvisionalColumnSilent a échoué");
                return ['success' => false, 'error' => 'Erreur ajout colonne is_provisional'];
            }
            
            // Migration de chaque table
            $results = [];
            $totalMigrated = 0;
            
            foreach ($this->table_mappings as $tempTable => $mainTable) {
                // Skip proprement si table absente
                if (!$this->tableExists($mainTable)) {
                    $this->log("⏭️ Table principale manquante, on ignore: $mainTable");
                    $results[$tempTable] = 0;
                    continue;
                }
                if (!$this->tableExists($tempTable)) {
                    $this->log("ℹ️ Table temporaire absente (aucune donnée à migrer): $tempTable");
                    $results[$tempTable] = 0;
                    continue;
                }

                $migrated = $this->migrateTableSilent($tempTable, $mainTable);
                if ($migrated === false) {
                    // ne pas échouer globalement
                    $this->log("❌ Erreur pendant la migration $tempTable → $mainTable (aucune ligne insérée)");
                    $results[$tempTable] = 0;
                    continue;
                }

                $this->log("✅ $tempTable → $mainTable: $migrated lignes insérées (provisionnelles)");
                $results[$tempTable] = $migrated;
                $totalMigrated += $migrated;
            }
            
            $this->log("=== MIGRATION SILENCIEUSE: terminée. Total inséré: $totalMigrated ===");
            return [
                'success' => true,
                'total_migrated' => $totalMigrated,
                'details' => $results
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function checkTablesExistSilent() { return true; }

    private function tableExists($tableName) {
        $safe = $this->db->real_escape_string($tableName);
        $result = $this->db->query("SHOW TABLES LIKE '$safe'");
        return $result && $result->num_rows > 0;
    }
    
    private function addIsProvisionalColumnSilent() {
        foreach ($this->table_mappings as $tempTable => $mainTable) {
            $result = $this->db->query("SHOW COLUMNS FROM `$mainTable` LIKE 'is_provisional'");
            
            if ($result->num_rows == 0) {
                $sql = "ALTER TABLE `$mainTable` ADD COLUMN is_provisional BOOLEAN DEFAULT FALSE NOT NULL";
                
                if (!$this->db->query($sql)) {
                    $this->log("❌ Ajout de is_provisional échoué sur $mainTable: " . $this->db->error);
                    return false;
                }
                
                $indexName = "idx_{$mainTable}_provisional";
                // MySQL < 8 ne supporte pas IF NOT EXISTS sur CREATE INDEX
                $idxExists = $this->db->query("SHOW INDEX FROM `$mainTable` WHERE Key_name = '$indexName'");
                if ($idxExists && $idxExists->num_rows === 0) {
                    $this->db->query("CREATE INDEX `$indexName` ON `$mainTable`(is_provisional)");
                }
                $this->log("➕ Colonne is_provisional ajoutée (et index) sur $mainTable");
            } else {
                $this->log("✔️ is_provisional déjà présent sur $mainTable");
            }
        }
        
        return true;
    }
    
    private function migrateTableSilent($tempTable, $mainTable) {
        // Stat de la table source
        $cntRes = $this->db->query("SELECT COUNT(*) AS c FROM `$tempTable`");
        $tempCount = $cntRes ? (int)$cntRes->fetch_assoc()['c'] : 0;
        $this->log("➡️ Préparation $tempTable ($tempCount lignes) → $mainTable");

        // Obtenir la structure de la table temporaire
        $tempColumns = [];
        $result = $this->db->query("DESCRIBE `$tempTable`");
        while ($row = $result->fetch_assoc()) {
            $tempColumns[] = $row['Field'];
        }
        
        // Obtenir la structure de la table principale (sans id, created_at, updated_at, is_provisional)
        $mainColumns = [];
        $result = $this->db->query("DESCRIBE `$mainTable`");
        while ($row = $result->fetch_assoc()) {
            if (!in_array($row['Field'], ['id', 'created_at', 'updated_at', 'is_provisional'])) {
                $mainColumns[] = $row['Field'];
            }
        }
        // Logs de diagnostic colonnes
        $this->log("🧱 Colonnes principales $mainTable: " . implode(', ', $mainColumns));
        $this->log("🧪 Colonnes temporaires $tempTable: " . implode(', ', $tempColumns));
        
        // Colonnes communes entre les deux tables
        $commonColumns = array_intersect($tempColumns, $mainColumns);
        
        if (empty($commonColumns)) {
            $this->log("❌ Aucune colonne commune entre $tempTable et $mainTable. temp: [" . implode(',', $tempColumns) . "], main: [" . implode(',', $mainColumns) . "]");
            return false;
        }
        
        // Construire la requête d'insertion avec gestion des doublons
        $columnsList = implode(', ', array_map(function($col) { return "`$col`"; }, $commonColumns));
        $this->log("📋 Colonnes communes ($tempTable → $mainTable): " . implode(', ', $commonColumns));

        $sql = "INSERT INTO `$mainTable` ($columnsList, is_provisional) 
                SELECT $columnsList, 1 as is_provisional 
                FROM `$tempTable`
                ON DUPLICATE KEY UPDATE is_provisional = VALUES(is_provisional)";
        $this->log("SQL ($tempTable → $mainTable): " . substr($sql, 0, 160) . "...");

        // Exécuter la migration
        if ($this->db->query($sql)) {
            $affected = $this->db->affected_rows;
            $this->log("↳ Insérées: $affected lignes dans $mainTable");
            return $affected;
        } else {
            $this->log("❌ Erreur SQL $tempTable → $mainTable: " . $this->db->error);
            return false;
        }
    }
    
    public function close() {
        if ($this->db) {
            $this->db->close();
            $this->log("🔌 Connexion fermée");
        }
    }
}

// Exécution du script seulement si appelé directement (pas via include/require)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
        // Mode CLI
        echo "🚀 Migration Tables Temporaires → Tables Principales\n";
        echo "===================================================\n\n";
        
        try {
            $migrator = new TempToMainMigrator();
            
            // Utiliser la méthode silencieuse pour éviter les timeouts
            echo "🔄 Utilisation de la méthode de migration silencieuse...\n";
            $result = $migrator->migrateAllSilent();
            
            $migrator->close();
            
            if ($result['success']) {
                echo "\n✅ Migration terminée avec succès!\n";
                echo "📊 Total migré: " . number_format($result['total_migrated']) . " enregistrements\n";
                echo "📋 Détails par table:\n";
                foreach ($result['details'] as $table => $count) {
                    echo "  - $table: " . number_format($count) . " enregistrements\n";
                }
                exit(0);
            } else {
                echo "\n❌ Migration échouée: " . ($result['error'] ?? 'Erreur inconnue') . "\n";
                exit(1);
            }
            
        } catch (Exception $e) {
            echo "❌ Erreur fatale: " . $e->getMessage() . "\n";
            exit(1);
        }
    } else {
        // Interface web simple (seulement si appelé directement)
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Migration Tables Temporaires</title>
            <meta charset="utf-8">
            <style>
                body { font-family: monospace; margin: 20px; background: #f5f5f5; }
                .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
                .button:hover { background: #005a87; }
                .log { background: #f8f8f8; padding: 15px; border-radius: 4px; margin-top: 20px; white-space: pre-wrap; font-family: monospace; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>🚀 Migration Tables Temporaires → Tables Principales</h1>
                <p>Ce script transfère les données des tables temporaires vers les tables principales avec <code>is_provisional = 1</code>.</p>
                
                <?php if (isset($_POST['migrate'])): ?>
                    <div class="log">
                    <?php
                    ob_start();
                    try {
                        $migrator = new TempToMainMigrator();
                        $success = $migrator->migrateAll();
                        if ($success) {
                            $migrator->verifyMigration();
                        }
                        $migrator->close();
                    } catch (Exception $e) {
                        echo "❌ Erreur: " . $e->getMessage() . "\n";
                    }
                    echo ob_get_clean();
                    ?>
                    </div>
                <?php else: ?>
                    <form method="post">
                        <button type="submit" name="migrate" class="button">🔄 Lancer la Migration</button>
                    </form>
                    
                    <h3>⚠️ Avertissement</h3>
                    <ul>
                        <li>Cette opération ajoute les données des tables temporaires aux tables principales</li>
                        <li>Les doublons sont automatiquement évités</li>
                        <li>Toutes les données migrées auront <code>is_provisional = 1</code></li>
                        <li>Les tables temporaires ne sont pas supprimées</li>
                    </ul>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }
}
?> 