<?php
/**
 * Script CRON pour mise à jour des saisons - Point d'entrée racine
 * 
 * Ce script est dans la racine pour faciliter la configuration CRON
 * mais redirige vers le script organisé dans scripts/saisons/
 */

// Inclure le vrai script CRON
include __DIR__ . '/scripts/saisons/cron_update_saisons.php';
?>