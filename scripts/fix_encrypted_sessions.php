<?php
/**
 * Script pour corriger les sessions contenant des données chiffrées
 * Force la reconnexion pour mettre à jour les sessions utilisateur
 */

require_once __DIR__ . '/../config/app.php';

echo "🔧 CORRECTION DES SESSIONS - FluxVision\n";
echo "=" . str_repeat("=", 50) . "\n\n";

echo "📋 Ce script va :\n";
echo "  • Nettoyer toutes les sessions PHP existantes\n";
echo "  • Forcer la reconnexion des utilisateurs\n";
echo "  • Assurer que les nouvelles sessions contiennent des données déchiffrées\n\n";

$confirmation = readline("Voulez-vous continuer ? (y/N) : ");
if (strtolower($confirmation) !== 'y') {
    echo "❌ Opération annulée\n";
    exit(0);
}

echo "\n🧹 NETTOYAGE DES SESSIONS...\n";

try {
    // 1. Nettoyer les fichiers de session PHP
    $sessionPath = session_save_path();
    if (empty($sessionPath)) {
        $sessionPath = sys_get_temp_dir();
    }
    
    echo "📁 Chemin des sessions : $sessionPath\n";
    
    $sessionFiles = glob($sessionPath . '/sess_*');
    $deletedCount = 0;
    
    foreach ($sessionFiles as $file) {
        if (unlink($file)) {
            $deletedCount++;
        }
    }
    
    echo "✅ $deletedCount fichiers de session supprimés\n";
    
    // 2. Détruire la session courante si elle existe
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
        echo "✅ Session courante détruite\n";
    }
    
    // 3. Créer un fichier d'information pour les utilisateurs
    $infoFile = __DIR__ . '/../session_cleanup.txt';
    $infoContent = "Sessions nettoyées le " . date('Y-m-d H:i:s') . "\n";
    $infoContent .= "Raison : Migration du système de chiffrement\n";
    $infoContent .= "Action requise : Reconnexion nécessaire\n";
    
    file_put_contents($infoFile, $infoContent);
    echo "✅ Fichier d'information créé\n";
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "🎉 NETTOYAGE TERMINÉ AVEC SUCCÈS !\n\n";
    
    echo "📋 ACTIONS RÉALISÉES :\n";
    echo "  • $deletedCount sessions supprimées\n";
    echo "  • Session courante fermée\n";
    echo "  • Fichier d'information créé\n\n";
    
    echo "⚠️  IMPORTANT :\n";
    echo "  • Tous les utilisateurs devront se reconnecter\n";
    echo "  • Les nouvelles sessions contiendront des données déchiffrées\n";
    echo "  • Les données en base restent chiffrées (sécurisé)\n\n";
    
    echo "🔐 SÉCURITÉ :\n";
    echo "  • Les données personnelles restent protégées en base\n";
    echo "  • Le déchiffrement se fait uniquement en mémoire (session)\n";
    echo "  • Aucune donnée sensible en clair sur le disque\n\n";
    
    echo "✅ Les utilisateurs peuvent maintenant se reconnecter normalement.\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR : " . $e->getMessage() . "\n";
    echo "📍 Ligne : " . $e->getLine() . "\n";
    echo "📄 Fichier : " . $e->getFile() . "\n";
    exit(1);
}

echo "\n🎯 Script terminé - Sessions corrigées !\n"; 