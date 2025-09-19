<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur 500 - Erreur interne du serveur</title>
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg);
            color: var(--on-surface);
        }
        .error-content {
            text-align: center;
            padding: 2rem;
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            max-width: 500px;
        }
        .error-code {
            font-size: 4rem;
            font-weight: bold;
            color: #8e44ad;
            margin-bottom: 1rem;
        }
        .error-title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--on-surface);
        }
        .error-message {
            margin-bottom: 2rem;
            line-height: 1.6;
            color: rgba(240, 240, 240, 0.8);
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: var(--primary);
            color: var(--bg);
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: var(--transition);
            margin: 0 5px;
        }
        .btn:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
        }
        .error-id {
            font-family: monospace;
            font-size: 0.8rem;
            color: rgba(240, 240, 240, 0.5);
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-content">
            <div class="error-code">500</div>
            <h1 class="error-title">Erreur interne du serveur</h1>
            <p class="error-message">
                Une erreur inattendue s'est produite sur le serveur.
                Nos équipes techniques ont été automatiquement notifiées.
                Veuillez réessayer plus tard.
            </p>
            <a href="<?= url('/') ?>" class="btn">
                Retour à l'accueil
            </a>
            <div class="error-id">
                ID d'erreur: <?= substr(md5(time() . $_SERVER['REQUEST_URI']), 0, 8) ?>
            </div>
        </div>
    </div>
</body>
</html> 