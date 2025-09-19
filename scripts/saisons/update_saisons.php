<?php
/**
 * Script de mise à jour automatique des saisons astronomiques
 * 
 * Ce script:
 * 1. Exécute le scraper JavaScript pour récupérer les dernières données
 * 2. Importe automatiquement les données en base
 * 3. Lie les périodes aux saisons
 * 
 * Usage: php update_saisons.php
 */

echo "🔄 MISE À JOUR AUTOMATIQUE DES SAISONS ASTRONOMIQUES\n";
echo "=" . str_repeat("=", 60) . "\n\n";

// 1. Exécuter le scraper JavaScript
echo "1️⃣ Récupération des données astronomiques...\n";
// Détecter l'année minimale présente dans dim_periodes (pour élargir la couverture)
$minYear = null;
try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../classes/Database.php';
    $database = Database::getInstance();
    $db = $database->getConnection();
    $row = $db->query("SELECT MIN(annee) AS min_annee FROM dim_periodes WHERE annee IS NOT NULL")->fetch();
    if (!empty($row['min_annee'])) {
        $minYear = (int)$row['min_annee'];
        if ($minYear > 1900) {
            $minYear = $minYear - 1; // couvrir l'hiver précédent
        }
    }
} catch (\Throwable $e) {
    // en cas d'erreur, on continue sans minYear
}
$output = [];
$returnVar = 0;

// Changer vers le répertoire des scripts
$oldDir = getcwd();
chdir(__DIR__);

// Construire la commande node avec option --min-year si disponible
$cmd = 'node scrap_date.js';
if ($minYear !== null) {
    $cmd .= ' --min-year=' . (int)$minYear;
}
$cmd .= ' 2>&1';

exec($cmd, $output, $returnVar);

if ($returnVar !== 0) {
    echo "❌ Erreur lors de l'exécution du scraper JavaScript\n";
    echo "Sortie:\n" . implode("\n", $output) . "\n";
    exit(1);
}

echo "✅ Données récupérées avec succès\n";
foreach ($output as $line) {
    if (strpos($line, 'donnees php generees') !== false || 
        strpos($line, 'fichier saisons_data.php') !== false ||
        strpos($line, 'annee') !== false && strpos($line, ':') !== false) {
        echo "   " . $line . "\n";
    }
}

// 2. Vérifier que le fichier de données a été créé
if (!file_exists('saisons_data.php')) {
    chdir($oldDir);
    echo "❌ Le fichier saisons_data.php n'a pas été créé\n";
    exit(1);
}

echo "\n2️⃣ Import en base de données...\n";

// 3. Exécuter l'import en base
$output = [];
$returnVar = 0;

exec('php import_saisons_simple.php 2>&1', $output, $returnVar);

if ($returnVar !== 0) {
    echo "❌ Erreur lors de l'import en base\n";
    echo "Sortie:\n" . implode("\n", $output) . "\n";
    exit(1);
}

// Afficher seulement les lignes importantes
foreach ($output as $line) {
    if (strpos($line, '✅') !== false || 
        strpos($line, 'Total saisons') !== false ||
        strpos($line, 'Périodes liées') !== false ||
        strpos($line, 'Import automatique terminé') !== false) {
        echo "   " . $line . "\n";
    }
}

// 4. Nettoyage
echo "\n3️⃣ Nettoyage...\n";
if (file_exists('saisons_data.php')) {
    unlink('saisons_data.php');
    echo "   ✓ Fichier temporaire saisons_data.php supprimé\n";
}

// Restaurer le répertoire original
chdir($oldDir);

echo "\n🎉 MISE À JOUR TERMINÉE AVEC SUCCÈS!\n";
echo "\nVotre base de données contient maintenant les dernières données\n";
echo "astronomiques officielles des saisons.\n";
echo "\nPour mettre à jour à nouveau, exécutez: php update_saisons.php\n";
?>