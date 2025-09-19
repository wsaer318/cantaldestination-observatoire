<?php
/**
 * Script de raccourci pour exécuter les outils FluxVision
 * Usage: php tools.php [catégorie] [script] [arguments...]
 * Exemple: php tools.php import update_temp_tables
 */

if ($argc < 3) {
    echo "🛠️  OUTILS FLUXVISION\n";
    echo "===================\n\n";
    echo "Usage: php tools.php [catégorie] [script] [arguments...]\n\n";
    echo "📂 Catégories disponibles :\n";
    echo "  • import      - Import de données\n";
    echo "  • migration   - Migration de données\n";
    echo "  • diagnostic  - Diagnostic et vérification\n";
    echo "  • maintenance - Maintenance et nettoyage\n";
    echo "  • saisons     - Gestion des saisons\n";
    echo "  • etl         - Scripts ETL Python\n";
    echo "  • dev         - Développement et tests\n\n";
    echo "📋 Exemples :\n";
    echo "  php tools.php import update_temp_tables\n";
    echo "  php tools.php diagnostic check_zones\n";
    echo "  php tools.php migration migrate_temp_to_main\n";
    echo "  php tools.php maintenance cleanup_temp_tables\n\n";
    echo "📖 Documentation complète : tools/README.md\n";
    exit(1);
}

$category = $argv[1];
$script = $argv[2];
$args = array_slice($argv, 3);

// Validation de la catégorie
$valid_categories = ['import', 'migration', 'diagnostic', 'maintenance', 'saisons', 'etl', 'dev'];
if (!in_array($category, $valid_categories)) {
    echo "❌ Catégorie '$category' invalide.\n";
    echo "📂 Catégories valides : " . implode(', ', $valid_categories) . "\n";
    exit(1);
}

// Construction du chemin
$script_path = __DIR__ . "/tools/$category/$script.php";

// Vérification de l'existence du script
if (!file_exists($script_path)) {
    echo "❌ Script '$script.php' introuvable dans la catégorie '$category'.\n";
    echo "📁 Chemin recherché : tools/$category/$script.php\n";
    
    // Lister les scripts disponibles dans cette catégorie
    $available_scripts = glob(__DIR__ . "/tools/$category/*.php");
    if (!empty($available_scripts)) {
        echo "\n📋 Scripts disponibles dans '$category' :\n";
        foreach ($available_scripts as $available_script) {
            $name = basename($available_script, '.php');
            echo "  • $name\n";
        }
    }
    exit(1);
}

// Exécution du script
echo "🚀 Exécution : tools/$category/$script.php\n";
echo str_repeat("=", 50) . "\n\n";

// Préparer la commande (utiliser le même PHP que le script actuel)
$php_path = PHP_BINARY;
$cmd = escapeshellarg($php_path) . " " . escapeshellarg($script_path);
foreach ($args as $arg) {
    $cmd .= " " . escapeshellarg($arg);
}

// Exécuter la commande
passthru($cmd, $return_code);

echo "\n" . str_repeat("=", 50) . "\n";
echo $return_code === 0 ? "✅ Terminé avec succès\n" : "❌ Terminé avec erreur (code: $return_code)\n";

exit($return_code);
?>
