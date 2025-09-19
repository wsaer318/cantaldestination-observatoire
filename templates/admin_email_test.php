<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Configuration Email - Cantal Destination</title>
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '_navbar.php'; ?>

    <div class="admin-container">
        <div class="admin-header">
            <h1><i class="fas fa-envelope"></i> Test Configuration Email</h1>
            <p>Testez et vérifiez la configuration email de l'observatoire</p>
        </div>

        <?php if (isset($message)): ?>
            <?php if ($message['type'] === 'success'): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($message['text']) ?>
                </div>
            <?php elseif ($message['type'] === 'config'): ?>
                <div class="admin-section">
                    <h2><i class="fas fa-cog"></i> Configuration Actuelle</h2>
                    <div class="config-grid">
                        <div class="config-item">
                            <label>Emails activés :</label>
                            <span class="status-badge <?= $message['config']['enabled'] ? 'active' : 'inactive' ?>">
                                <?= $message['config']['enabled'] ? 'Activé' : 'Désactivé' ?>
                            </span>
                        </div>
                        <div class="config-item">
                            <label>Email admin :</label>
                            <span><?= htmlspecialchars($message['config']['admin_email']) ?></span>
                        </div>
                        <div class="config-item">
                            <label>Email expéditeur :</label>
                            <span><?= htmlspecialchars($message['config']['from_email']) ?></span>
                        </div>
                        <div class="config-item">
                            <label>Serveur SMTP :</label>
                            <span><?= htmlspecialchars($message['config']['smtp_host']) ?>:<?= $message['config']['smtp_port'] ?></span>
                        </div>
                        <div class="config-item">
                            <label>Sécurité SMTP :</label>
                            <span><?= $message['config']['smtp_secure'] ?: 'Aucune' ?></span>
                        </div>
                        <div class="config-item">
                            <label>Authentification :</label>
                            <span class="status-badge <?= $message['config']['smtp_auth'] ? 'active' : 'inactive' ?>">
                                <?= $message['config']['smtp_auth'] ? 'Requise' : 'Non requise' ?>
                            </span>
                        </div>
                    </div>
                    
                    <h3><i class="fas fa-clipboard-check"></i> Résultats du Test</h3>
                    <div class="test-results">
                        <div class="test-item">
                            <span>SMTP configuré :</span>
                            <span class="status-badge <?= $message['results']['smtp_configured'] ? 'active' : 'inactive' ?>">
                                <?= $message['results']['smtp_configured'] ? 'Oui' : 'Non' ?>
                            </span>
                        </div>
                        <div class="test-item">
                            <span>Peut envoyer :</span>
                            <span class="status-badge <?= $message['results']['can_send'] ? 'active' : 'inactive' ?>">
                                <?= $message['results']['can_send'] ? 'Oui' : 'Non' ?>
                            </span>
                        </div>
                        <?php if (isset($message['results']['error'])): ?>
                            <div class="test-item error">
                                <span>Erreur :</span>
                                <span><?= htmlspecialchars($message['results']['error']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Test Configuration -->
        <div class="admin-section">
            <h2><i class="fas fa-tools"></i> Tester la Configuration</h2>
            <div class="test-actions">
                <form action="<?= url('/admin/email-test') ?>" method="POST" style="display: inline; margin-right: 15px;">
                    <input type="hidden" name="action" value="test_config">
                    <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                    <button type="submit" class="btn btn--secondary">
                        <i class="fas fa-info-circle"></i>
                        Afficher Configuration
                    </button>
                </form>
                
                <form action="<?= url('/admin/email-test') ?>" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="send_test">
                    <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                    <button type="submit" class="btn btn--primary">
                        <i class="fas fa-paper-plane"></i>
                        Envoyer Email de Test
                    </button>
                </form>
            </div>
        </div>

        <!-- Instructions Configuration -->
        <div class="admin-section">
            <h2><i class="fas fa-book"></i> Configuration Email</h2>
            <div class="config-help">
                <h3>📁 Fichier de Configuration</h3>
                <p>Pour configurer l'envoi d'emails, éditez le fichier : <code>config/email.php</code></p>
                
                <h3>📧 Exemple pour Gmail</h3>
                <div class="code-block">
                    <pre><code>return [
    'enabled' => true,
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_auth' => true,
    'smtp_username' => 'votre.email@gmail.com',
    'smtp_password' => 'mot_de_passe_app',
    'admin_email' => 'admin@cantaldestination.fr',
];</code></pre>
                </div>
                
                <h3>🔧 Autres serveurs SMTP populaires</h3>
                <div class="smtp-examples">
                    <div class="smtp-provider">
                        <strong>OVH :</strong>
                        <ul>
                            <li>Host : ssl0.ovh.net</li>
                            <li>Port : 587 (TLS) ou 465 (SSL)</li>
                        </ul>
                    </div>
                    <div class="smtp-provider">
                        <strong>Orange :</strong>
                        <ul>
                            <li>Host : smtp.orange.fr</li>
                            <li>Port : 587</li>
                        </ul>
                    </div>
                    <div class="smtp-provider">
                        <strong>Outlook :</strong>
                        <ul>
                            <li>Host : smtp-mail.outlook.com</li>
                            <li>Port : 587</li>
                        </ul>
                    </div>
                </div>
                
                <div class="warning-box">
                    <h4>⚠️ Important</h4>
                    <ul>
                        <li>Pour Gmail, utilisez un <strong>mot de passe d'application</strong> (pas votre mot de passe principal)</li>
                        <li>Vérifiez que votre serveur peut envoyer des emails sortants</li>
                        <li>En développement, les emails sont simulés dans les logs si SMTP n'est pas configuré</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Retour -->
        <div class="admin-section">
            <a href="<?= url('/admin') ?>" class="btn btn--secondary">
                <i class="fas fa-arrow-left"></i>
                Retour à l'Administration
            </a>
        </div>
    </div>

    <style>
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .config-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: var(--surface);
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        
        .config-item label {
            font-weight: 600;
            color: var(--primary);
        }
        
        .test-results {
            margin: 20px 0;
        }
        
        .test-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin: 8px 0;
            background: var(--surface);
            border-radius: 6px;
        }
        
        .test-item.error {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.3);
        }
        
        .test-actions {
            display: flex;
            gap: 15px;
            margin: 20px 0;
        }
        
        .code-block {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .code-block pre {
            margin: 0;
            padding: 20px;
            overflow-x: auto;
        }
        
        .code-block code {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            color: var(--on-surface);
        }
        
        .smtp-examples {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .smtp-provider {
            background: var(--surface);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        
        .smtp-provider strong {
            color: var(--primary);
            display: block;
            margin-bottom: 8px;
        }
        
        .smtp-provider ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .smtp-provider li {
            margin: 4px 0;
            font-size: 0.9em;
        }
        
        .warning-box {
            background: rgba(243, 156, 18, 0.1);
            border: 1px solid rgba(243, 156, 18, 0.3);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .warning-box h4 {
            color: #f39c12;
            margin: 0 0 10px 0;
        }
        
        .warning-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .warning-box li {
            margin: 5px 0;
        }
        
        .config-help h3 {
            color: var(--primary);
            margin: 25px 0 10px 0;
        }
        
        .config-help p {
            margin: 10px 0;
        }
        
        .config-help code {
            background: var(--surface);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
    </style>
</body>
</html> 