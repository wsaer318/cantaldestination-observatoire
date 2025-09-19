<?php
/**
 * Configuration email pour FluxVision
 * 
 * Copiez ce fichier et modifiez les paramètres selon votre serveur SMTP
 * Pour Gmail/Google Workspace, utilisez :
 * - smtp_host: smtp.gmail.com
 * - smtp_port: 587
 * - smtp_secure: tls
 * - smtp_auth: true
 * - smtp_username: votre.email@gmail.com
 * - smtp_password: mot_de_passe_app (voir Google App Passwords)
 */

return [
    // Activation de l'envoi d'emails
    'enabled' => true,
    
    // Configuration SMTP
    'smtp_host' => 'localhost',      // Serveur SMTP
    'smtp_port' => 587,              // Port SMTP (587 pour TLS, 465 pour SSL, 25 pour non-sécurisé)
    'smtp_secure' => 'tls',          // 'tls', 'ssl' ou false
    'smtp_auth' => false,            // Authentification SMTP requise ?
    'smtp_username' => '',           // Nom d'utilisateur SMTP
    'smtp_password' => '',           // Mot de passe SMTP
    
    // Expéditeur
    'from_email' => 'noreply@cantaldestination.fr',
    'from_name' => 'CantalDestination - Observatoire Touristique',
    
    // Email de l'administrateur (recevra les notifications)
    'admin_email' => 'admin@cantaldestination.fr',
]; 