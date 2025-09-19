<?php
// Récupérer la configuration d'environnement depuis PHP
require_once dirname(__DIR__) . '/database.php';

$environment = DatabaseConfig::isProduction() ? 'production' : 'local';
$basePath = DatabaseConfig::isProduction() ? '' : '/fluxvision_fin';
?>

<script>
// Configuration d'environnement injectée par PHP (plus fiable)
window.CANTALDESTINATION_ENV = {
    environment: '<?= $environment ?>',
    basePath: '<?= $basePath ?>',
    isProduction: <?= DatabaseConfig::isProduction() ? 'true' : 'false' ?>
};

// Override de la fonction isProduction si nécessaire
if (typeof window.CANTALDESTINATION_ENV !== 'undefined') {
    // Redéfinir la configuration avec les valeurs PHP
    Object.assign(CantalDestinationConfig, {
        environment: window.CANTALDESTINATION_ENV.environment,
        basePath: window.CANTALDESTINATION_ENV.basePath
    });
    
}
</script> 