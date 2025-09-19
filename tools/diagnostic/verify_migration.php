<?php
/**
 * Vérification de la Migration des Tables Temporaires
 * Cantal Destination - Observatoire du Tourisme
 */

// Import de la configuration de base de données
require_once __DIR__ . '/../../config/database.php';

class MigrationVerifier {
    private $db;
    private $silentMode = false;
    
    private $table_mappings = [
        'fact_nuitees_temp' => 'fact_nuitees',
        'fact_nuitees_departements_temp' => 'fact_nuitees_departements',
        'fact_nuitees_pays_temp' => 'fact_nuitees_pays',
        'fact_diurnes_temp' => 'fact_diurnes',
        'fact_diurnes_departements_temp' => 'fact_diurnes_departements',
        'fact_diurnes_pays_temp' => 'fact_diurnes_pays',
        'fact_diurnes_geolife_temp' => 'fact_diurnes_geolife',
        'fact_lieu_activite_soir_temp' => 'fact_lieu_activite_soir',
        'fact_sejours_duree_temp' => 'fact_sejours_duree',
        'fact_sejours_duree_departements_temp' => 'fact_sejours_duree_departements',
        'fact_sejours_duree_pays_temp' => 'fact_sejours_duree_pays'
    ];
    
    public function __construct($silentMode = false) {
        $this->silentMode = $silentMode;
        $this->connectDB();
    }
    
    public function setSilentMode($silent = true) {
        $this->silentMode = $silent;
    }
    
    private function log($message) {
        if (!$this->silentMode) {
            echo $message;
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
            $this->log("✅ Connexion à la base de données réussie\n");
            
        } catch (Exception $e) {
            $this->log("❌ Erreur de connexion: " . $e->getMessage() . "\n");
            throw $e;
        }
    }
    
    public function verifyMigrationSilent() {
        try {
            $totalTemp = 0;
            $totalProvisional = 0;
            $totalRecords = 0;
            $allSuccess = true;
            $details = [];
            
            foreach ($this->table_mappings as $tempTable => $mainTable) {
                // Compter les enregistrements dans la table temporaire
                $result = $this->db->query("SELECT COUNT(*) as count FROM `$tempTable`");
                $tempCount = $result ? $result->fetch_assoc()['count'] : 0;
                
                // Compter les enregistrements provisoires dans la table principale
                $result = $this->db->query("SELECT COUNT(*) as count FROM `$mainTable` WHERE is_provisional = 1");
                $provisionalCount = $result ? $result->fetch_assoc()['count'] : 0;
                
                // Compter le total dans la table principale
                $result = $this->db->query("SELECT COUNT(*) as count FROM `$mainTable`");
                $mainTotalCount = $result ? $result->fetch_assoc()['count'] : 0;
                
                $totalTemp += $tempCount;
                $totalProvisional += $provisionalCount;
                $totalRecords += $mainTotalCount;
                
                $details[$tempTable] = [
                    'main_table' => $mainTable,
                    'temp_count' => $tempCount,
                    'provisional_count' => $provisionalCount,
                    'main_total_count' => $mainTotalCount,
                    'migration_rate' => $tempCount > 0 ? round(($provisionalCount / $tempCount) * 100, 1) : 100
                ];
                
                if ($tempCount > 0 && $provisionalCount != $tempCount) {
                    $allSuccess = false;
                }
            }
            
            return [
                'success' => $allSuccess,
                'summary' => [
                    'total_temp' => $totalTemp,
                    'total_provisional' => $totalProvisional,
                    'total_records' => $totalRecords
                ],
                'details' => $details
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function verifyMigration() {
        echo "🔍 VÉRIFICATION DE LA MIGRATION\n";
        echo "==============================\n\n";
        
        $totalTemp = 0;
        $totalProvisional = 0;
        $allSuccess = true;
        
        foreach ($this->table_mappings as $tempTable => $mainTable) {
            echo "📋 Vérification $tempTable → $mainTable\n";
            
            // Compter les enregistrements dans la table temporaire
            $result = $this->db->query("SELECT COUNT(*) as count FROM `$tempTable`");
            $tempCount = $result ? $result->fetch_assoc()['count'] : 0;
            
            // Compter les enregistrements provisoires dans la table principale
            $result = $this->db->query("SELECT COUNT(*) as count FROM `$mainTable` WHERE is_provisional = 1");
            $provisionalCount = $result ? $result->fetch_assoc()['count'] : 0;
            
            // Compter le total dans la table principale
            $result = $this->db->query("SELECT COUNT(*) as count FROM `$mainTable`");
            $mainTotalCount = $result ? $result->fetch_assoc()['count'] : 0;
            
            $totalTemp += $tempCount;
            $totalProvisional += $provisionalCount;
            
            if ($tempCount > 0) {
                $migrationRate = round(($provisionalCount / $tempCount) * 100, 1);
                
                if ($migrationRate == 100) {
                    echo "   ✅ $tempCount enregistrements → $provisionalCount provisoires ($migrationRate%)\n";
                } else {
                    echo "   ⚠️ $tempCount enregistrements → $provisionalCount provisoires ($migrationRate%)\n";
                    $allSuccess = false;
                }
            } else {
                echo "   ℹ️ Table temporaire vide\n";
            }
            
            echo "   📊 Total dans $mainTable: " . number_format($mainTotalCount) . " enregistrements\n";
            echo "\n";
        }
        
        echo "🎯 RÉSUMÉ GLOBAL:\n";
        echo "================\n";
        echo "📊 Total enregistrements temporaires: " . number_format($totalTemp) . "\n";
        echo "📊 Total enregistrements provisoires: " . number_format($totalProvisional) . "\n";
        
        if ($allSuccess && $totalTemp == $totalProvisional) {
            echo "✅ MIGRATION COMPLÈTE ET RÉUSSIE!\n";
        } else {
            echo "⚠️ Migration incomplète ou problème détecté\n";
        }
        
        return $allSuccess;
    }
    
    public function testProvisionalQueries() {
        echo "\n🧪 TEST DES REQUÊTES AVEC is_provisional\n";
        echo "=======================================\n\n";
        
        // Test 1: Données principales uniquement
        echo "🔍 Test 1: Données principales (is_provisional = 0)\n";
        $result = $this->db->query("SELECT COUNT(*) as count FROM fact_nuitees_departements WHERE is_provisional = 0");
        $mainCount = $result ? $result->fetch_assoc()['count'] : 0;
        echo "   📊 Enregistrements principaux: " . number_format($mainCount) . "\n";
        
        // Test 2: Données provisoires uniquement
        echo "🔍 Test 2: Données provisoires (is_provisional = 1)\n";
        $result = $this->db->query("SELECT COUNT(*) as count FROM fact_nuitees_departements WHERE is_provisional = 1");
        $provCount = $result ? $result->fetch_assoc()['count'] : 0;
        echo "   📊 Enregistrements provisoires: " . number_format($provCount) . "\n";
        
        // Test 3: Exemple de requête avec filtre
        echo "🔍 Test 3: Requête avec filtre sur les données provisoires\n";
        $sql = "SELECT d.nom_departement, SUM(f.volume) as total_volume 
                FROM fact_nuitees_departements f 
                JOIN dim_departements d ON f.id_departement = d.id_departement 
                WHERE f.is_provisional = 1 
                GROUP BY d.nom_departement 
                ORDER BY total_volume DESC 
                LIMIT 5";
        
        $result = $this->db->query($sql);
        if ($result && $result->num_rows > 0) {
            echo "   📋 Top 5 départements (données provisoires):\n";
            while ($row = $result->fetch_assoc()) {
                echo "      • " . $row['nom_departement'] . ": " . number_format($row['total_volume']) . " nuitées\n";
            }
        } else {
            echo "   ⚠️ Aucune donnée provisoire trouvée\n";
        }
        
        echo "\n";
    }
    
    public function showMigrationSummary() {
        echo "📈 RÉSUMÉ DÉTAILLÉ DE LA MIGRATION\n";
        echo "=================================\n\n";
        
        $grandTotal = 0;
        $grandProvisional = 0;
        
        foreach ($this->table_mappings as $tempTable => $mainTable) {
            // Stats de la table principale
            $result = $this->db->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_provisional = 1 THEN 1 ELSE 0 END) as provisoires,
                SUM(CASE WHEN is_provisional = 0 THEN 1 ELSE 0 END) as principales
                FROM `$mainTable`");
            
            if ($result) {
                $stats = $result->fetch_assoc();
                $total = $stats['total'];
                $provisoires = $stats['provisoires'];
                $principales = $stats['principales'];
                
                $grandTotal += $total;
                $grandProvisional += $provisoires;
                
                echo "🏷️ $mainTable:\n";
                echo "   📊 Total: " . number_format($total) . " enregistrements\n";
                echo "   🔵 Principales: " . number_format($principales) . " enregistrements\n";
                echo "   🟡 Provisoires: " . number_format($provisoires) . " enregistrements\n";
                
                if ($total > 0) {
                    $provPercent = round(($provisoires / $total) * 100, 1);
                    echo "   📈 Pourcentage provisoire: $provPercent%\n";
                }
                echo "\n";
            }
        }
        
        echo "🎯 TOTAUX GÉNÉRAUX:\n";
        echo "   📊 Total général: " . number_format($grandTotal) . " enregistrements\n";
        echo "   🟡 Total provisoires: " . number_format($grandProvisional) . " enregistrements\n";
        
        if ($grandTotal > 0) {
            $globalProvPercent = round(($grandProvisional / $grandTotal) * 100, 1);
            echo "   📈 Pourcentage global provisoire: $globalProvPercent%\n";
        }
    }
    
    public function close() {
        if ($this->db) {
            $this->db->close();
            if (!$this->silentMode) {
                echo "\n🔌 Connexion fermée\n";
            }
        }
    }
}

// Exécution du script seulement si appelé directement (pas via include/require)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "🔍 Vérification de la Migration - Tables Temporaires → Principales\n";
    echo "==================================================================\n\n";

    try {
        $verifier = new MigrationVerifier();
        
        // Vérification de la migration
        $success = $verifier->verifyMigration();
        
        // Tests des requêtes avec is_provisional
        $verifier->testProvisionalQueries();
        
        // Résumé détaillé
        $verifier->showMigrationSummary();
        
        $verifier->close();
        
        echo "\n" . ($success ? "✅ Vérification terminée avec succès!" : "⚠️ Problèmes détectés lors de la vérification") . "\n";
        
    } catch (Exception $e) {
        echo "❌ Erreur fatale: " . $e->getMessage() . "\n";
    }
}
?> 