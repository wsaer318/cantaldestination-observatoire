<?php
/**
 * Script d'optimisation de la base de données FluxVision
 * Création d'index pour améliorer les performances des API
 */

try {
    // Utiliser notre système de configuration de base de données
    require_once dirname(__DIR__) . '/database.php';
    $db = getCantalDestinationDatabase();
    $pdo = $db->getConnection();
    
    echo "<h1>🚀 Optimisation de la base de données FluxVision</h1>";
    echo "<p>Création d'index pour améliorer les performances...</p>";
    
    // Index pour la table fact_nuitees (la plus importante)
    $indexes_fact_nuitees = [
        // Index composite pour les requêtes principales
        "CREATE INDEX idx_fact_nuitees_date_zone_prov_cat ON fact_nuitees (date, id_zone, id_provenance, id_categorie)",
        
        // Index sur la date (très utilisé dans WHERE)
        "CREATE INDEX idx_fact_nuitees_date ON fact_nuitees (date)",
        
        // Index sur la zone (très utilisé dans JOIN)
        "CREATE INDEX idx_fact_nuitees_zone ON fact_nuitees (id_zone)",
        
        // Index sur provenance + catégorie (filtres fréquents)
        "CREATE INDEX idx_fact_nuitees_prov_cat ON fact_nuitees (id_provenance, id_categorie)",
        
        // Index composite optimisé pour les requêtes touristes
        "CREATE INDEX idx_fact_nuitees_touriste ON fact_nuitees (id_categorie, id_provenance, date, id_zone)"
    ];
    
    // Index pour la table fact_nuitees_departements
    $indexes_fact_dept = [
        "CREATE INDEX idx_fact_dept_date_zone_prov_cat ON fact_nuitees_departements (date, id_zone, id_provenance, id_categorie)",
        "CREATE INDEX idx_fact_dept_dept_date ON fact_nuitees_departements (id_departement, date)",
        "CREATE INDEX idx_fact_dept_composite ON fact_nuitees_departements (id_categorie, id_provenance, date, id_zone, id_departement)"
    ];
    
    // Index pour la table fact_nuitees_pays
    $indexes_fact_pays = [
        "CREATE INDEX idx_fact_pays_date_zone_prov_cat ON fact_nuitees_pays (date, id_zone, id_provenance, id_categorie)",
        "CREATE INDEX idx_fact_pays_pays_date ON fact_nuitees_pays (id_pays, date)"
    ];
    
    // Index pour la table fact_nuitees_age
    $indexes_fact_age = [
        "CREATE INDEX idx_fact_age_date_zone_prov_cat ON fact_nuitees_age (date, id_zone, id_provenance, id_categorie)",
        "CREATE INDEX idx_fact_age_age_date ON fact_nuitees_age (id_age, date)"
    ];
    
    // Index pour la table fact_nuitees_geolife
    $indexes_fact_geolife = [
        "CREATE INDEX idx_fact_geolife_date_zone_prov_cat ON fact_nuitees_geolife (date, id_zone, id_provenance, id_categorie)",
        "CREATE INDEX idx_fact_geolife_geo_date ON fact_nuitees_geolife (id_geolife, date)"
    ];
    
    // Index pour la table fact_diurnes (excursionnistes)
    $indexes_fact_diurnes = [
        "CREATE INDEX idx_fact_diurnes_date_zone_prov_cat ON fact_diurnes (date, id_zone, id_provenance, id_categorie)",
        "CREATE INDEX idx_fact_diurnes_date ON fact_diurnes (date)",
        "CREATE INDEX idx_fact_diurnes_excursionniste ON fact_diurnes (id_categorie, id_provenance, date, id_zone)"
    ];
    
    // Index pour les tables de dimensions (pour optimiser les jointures)
    $indexes_dimensions = [
        "CREATE INDEX idx_dim_zones_nom ON dim_zones_observation (nom_zone)",
        "CREATE INDEX idx_dim_prov_nom ON dim_provenances (nom_provenance)",
        "CREATE INDEX idx_dim_cat_nom ON dim_categories_visiteur (nom_categorie)",
        "CREATE INDEX idx_dim_dept_nom ON dim_departements (nom_departement)",
        "CREATE INDEX idx_dim_pays_nom ON dim_pays (nom_pays)",
        "CREATE INDEX idx_dim_age_nom ON dim_tranches_age (tranche_age)",
        "CREATE INDEX idx_dim_geolife_nom ON dim_segments_geolife (nom_segment)"
    ];
    
    // Fonction pour créer les index avec gestion d'erreurs
    function createIndexes($pdo, $indexes, $tableName) {
        echo "<h3>📊 Index pour $tableName</h3>";
        $success = 0;
        $errors = 0;
        
        foreach ($indexes as $index) {
            try {
                $pdo->exec($index);
                $indexName = preg_match('/CREATE INDEX (\w+)/', $index, $matches) ? $matches[1] : 'index';
                echo "<p style='color:green'>✅ $indexName créé</p>";
                $success++;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                    echo "<p style='color:orange'>⚠️ Index déjà existant</p>";
                } else {
                    echo "<p style='color:red'>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
                    $errors++;
                }
            }
        }
        
        echo "<p><strong>Résumé:</strong> $success créés, $errors erreurs</p>";
        return $success;
    }
    
    // Vérifier les tables existantes
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $totalIndexes = 0;
    
    // Créer les index selon les tables disponibles
    if (in_array('fact_nuitees', $tables)) {
        $totalIndexes += createIndexes($pdo, $indexes_fact_nuitees, 'fact_nuitees');
    }
    
    if (in_array('fact_nuitees_departements', $tables)) {
        $totalIndexes += createIndexes($pdo, $indexes_fact_dept, 'fact_nuitees_departements');
    }
    
    if (in_array('fact_nuitees_pays', $tables)) {
        $totalIndexes += createIndexes($pdo, $indexes_fact_pays, 'fact_nuitees_pays');
    }
    
    if (in_array('fact_nuitees_age', $tables)) {
        $totalIndexes += createIndexes($pdo, $indexes_fact_age, 'fact_nuitees_age');
    }
    
    if (in_array('fact_nuitees_geolife', $tables)) {
        $totalIndexes += createIndexes($pdo, $indexes_fact_geolife, 'fact_nuitees_geolife');
    }
    
    if (in_array('fact_diurnes', $tables)) {
        $totalIndexes += createIndexes($pdo, $indexes_fact_diurnes, 'fact_diurnes');
    }
    
    // Index sur les dimensions
    $totalIndexes += createIndexes($pdo, $indexes_dimensions, 'dimensions');
    
    echo "<h2>🎉 Optimisation terminée !</h2>";
    echo "<p><strong>Total des index créés:</strong> $totalIndexes</p>";
    
    // Test de performance
    echo "<h3>⚡ Test de performance</h3>";
    
    $start = microtime(true);
    $result = $pdo->query("
        SELECT COUNT(*) as count
        FROM fact_nuitees
        INNER JOIN dim_zones_observation ON fact_nuitees.id_zone = dim_zones_observation.id_zone
        INNER JOIN dim_categories_visiteur ON fact_nuitees.id_categorie = dim_categories_visiteur.id_categorie
        WHERE fact_nuitees.date BETWEEN '2024-02-10' AND '2024-03-10'
        AND dim_zones_observation.nom_zone = 'CANTAL'
        AND dim_categories_visiteur.nom_categorie = 'TOURISTE'
    ")->fetch();
    $end = microtime(true);
    
    $duration = round(($end - $start) * 1000, 2);
    echo "<p>Requête test: {$result['count']} enregistrements en <strong>{$duration}ms</strong></p>";
    
    if ($duration < 100) {
        echo "<p style='color:green'>🚀 Performance excellente !</p>";
    } elseif ($duration < 500) {
        echo "<p style='color:orange'>⚡ Performance correcte</p>";
    } else {
        echo "<p style='color:red'>🐌 Performance à améliorer</p>";
    }
    
    echo "<h3>📋 Prochaines étapes</h3>";
    echo "<ul>";
    echo "<li>Testez maintenant l'API: <a href='bloc_a_fixed.php?annee=2024&periode=hiver&zone=CANTAL'>bloc_a_fixed.php</a></li>";
    echo "<li>Vérifiez l'amélioration des performances</li>";
    echo "<li>Lancez ANALYZE TABLE si nécessaire</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
} 