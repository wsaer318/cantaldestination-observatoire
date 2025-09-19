<?php
/**
 * Script pour forcer une mise à jour des saisons (ignorer la vérification de date)
 * 
 * Usage: php force_update_saisons.php
 */

echo "🔄 FORÇAGE DE LA MISE À JOUR DES SAISONS\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Exécuter directement le script de mise à jour standard
include __DIR__ . '/update_saisons.php';

echo "\n💡 Cette mise à jour a été forcée manuellement.\n";
echo "Le CRON quotidien continuera normalement.\n";
?>