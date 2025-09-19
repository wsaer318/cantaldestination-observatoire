<?php
/**
 * Script de lancement pour la mise à jour des saisons
 * 
 * Ce script est dans la racine pour faciliter l'accès mais
 * les vrais scripts sont organisés dans scripts/saisons/
 */

echo "🔄 MISE À JOUR DES SAISONS ASTRONOMIQUES\n";
echo "Redirection vers scripts/saisons/update_saisons.php...\n\n";

// Inclure le vrai script
include __DIR__ . '/scripts/saisons/update_saisons.php';
?>