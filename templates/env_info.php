<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informations Environnement - Cantal Destination</title>
    <link rel="stylesheet" href="<?= asset('/static/css/app.css') ?>">
    <style>
        .env-info { max-width: 800px; margin: 20px auto; padding: 20px; }
        .env-card { background: white; border-radius: 8px; padding: 20px; margin: 10px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .env-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-weight: bold; }
        .env-local { background: #e3f2fd; color: #1976d2; }
        .env-production { background: #fff3e0; color: #f57c00; }
        .env-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .env-table th, .env-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; }
        .env-table th { background: #f5f5f5; font-weight: bold; }
        .status-ok { color: #4caf50; }
        .status-error { color: #f44336; }
        .status-warning { color: #ff9800; }
    </style>
</head>
<body>
    <div class="env-info">
        <h1>🔧 Informations Environnement - Observatoire</h1>
        
        <?php
        require_once '../config/app.php';
        
        $environment = DatabaseConfig::getCurrentEnvironment();
        $config = DatabaseConfig::getConfig();
        ?>
        
        <div class="env-card">
            <h2>Environnement Actuel</h2>
            <p>
                Status: 
                <span class="env-status <?= $environment === 'production' ? 'env-production' : 'env-local' ?>">
                    <?= $environment === 'production' ? '🌐 PRODUCTION' : '🏠 LOCAL' ?>
                </span>
            </p>
            
            <table class="env-table">
                <tr>
                    <th>Paramètre</th>
                    <th>Valeur</th>
                </tr>
                <tr>
                    <td>HTTP Host</td>
                    <td><?= $_SERVER['HTTP_HOST'] ?? 'N/A' ?></td>
                </tr>
                <tr>
                    <td>Server Name</td>
                    <td><?= $_SERVER['SERVER_NAME'] ?? 'N/A' ?></td>
                </tr>
                <tr>
                    <td>Server IP</td>
                    <td><?= $_SERVER['SERVER_ADDR'] ?? 'N/A' ?></td>
                </tr>
                <tr>
                    <td>Document Root</td>
                    <td><?= $_SERVER['DOCUMENT_ROOT'] ?? 'N/A' ?></td>
                </tr>
                <tr>
                    <td>Debug Mode</td>
                    <td><?= DEBUG ? 'Activé' : 'Désactivé' ?></td>
                </tr>
            </table>
        </div>
        
        <div class="env-card">
            <h2>Configuration Base de Données</h2>
            
            <table class="env-table">
                <tr>
                    <th>Paramètre</th>
                    <th>Valeur</th>
                </tr>
                <tr>
                    <td>Host</td>
                    <td><?= $config['host'] ?></td>
                </tr>
                <tr>
                    <td>Port</td>
                    <td><?= $config['port'] ?></td>
                </tr>
                <tr>
                    <td>Base de données</td>
                    <td><?= $config['database'] ?></td>
                </tr>
                <tr>
                    <td>Utilisateur</td>
                    <td><?= $config['username'] ?></td>
                </tr>
                <tr>
                    <td>Charset</td>
                    <td><?= $config['charset'] ?></td>
                </tr>
            </table>
        </div>
        
        <div class="env-card">
            <h2>Test de Connexion</h2>
            
            <?php
            try {
                $db = getCantalDestinationDatabase();
                echo '<p class="status-ok">✅ Connexion à la base de données réussie</p>';
                
                // Test d'une requête simple
                $result = $db->query("SELECT 1 as test");
                if ($result) {
                    echo '<p class="status-ok">✅ Requête de test réussie</p>';
                }
                
            } catch (Exception $e) {
                echo '<p class="status-error">❌ Erreur de connexion: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
        </div>
        
        <div class="env-card">
            <h2>Vérifications Système</h2>
            
            <table class="env-table">
                <tr>
                    <th>Vérification</th>
                    <th>Status</th>
                </tr>
                <tr>
                    <td>PHP Version</td>
                    <td><?= PHP_VERSION ?></td>
                </tr>
                <tr>
                    <td>Extension PDO</td>
                    <td class="<?= extension_loaded('pdo') ? 'status-ok' : 'status-error' ?>">
                        <?= extension_loaded('pdo') ? '✅ Activée' : '❌ Manquante' ?>
                    </td>
                </tr>
                <tr>
                    <td>Extension PDO MySQL</td>
                    <td class="<?= extension_loaded('pdo_mysql') ? 'status-ok' : 'status-error' ?>">
                        <?= extension_loaded('pdo_mysql') ? '✅ Activée' : '❌ Manquante' ?>
                    </td>
                </tr>
                <tr>
                    <td>Fichier .htaccess</td>
                    <td class="<?= file_exists('../.htaccess') ? 'status-ok' : 'status-warning' ?>">
                        <?= file_exists('../.htaccess') ? '✅ Présent' : '⚠️ Manquant' ?>
                    </td>
                </tr>
                <tr>
                    <td>Dossier logs writable</td>
                    <td class="<?= is_writable('../logs') ? 'status-ok' : 'status-warning' ?>">
                        <?= is_writable('../logs') ? '✅ Accessible' : '⚠️ Non accessible' ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="env-card">
            <h2>Actions Rapides</h2>
            
            <p><a href="<?= url('/') ?>" class="btn">🏠 Retour à l'accueil</a></p>
            
            <?php if ($environment === 'local'): ?>
                <p>
                    <strong>Vous êtes en local :</strong><br>
                    - Vous pouvez modifier et tester le code<br>
                    - Les erreurs sont affichées<br>
                    - Base de données : fluxvision (XAMPP)
                </p>
            <?php else: ?>
                <p>
                    <strong>Vous êtes en production :</strong><br>
                    - Mode debug désactivé<br>
                    - Erreurs masquées<br>
                    - Base de données : observatoire (hébergeur)
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 