<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur 401 - Non autorisé</title>
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
            color: #e67e22;
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
        .btn--secondary {
            background: var(--secondary);
        }
        .btn--secondary:hover {
            background: #16a085;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-content">
            <div class="error-code">401</div>
            <h1 class="error-title">Accès non autorisé</h1>
            <p class="error-message">
                Vous devez vous connecter pour accéder à cette ressource.
                Veuillez vous authentifier avec des identifiants valides.
            </p>
            <a href="<?= url('/login') ?>" class="btn">
                Se connecter
            </a>
            <a href="<?= url('/') ?>" class="btn btn--secondary">
                Retour à l'accueil
            </a>
        </div>
    </div>
</body>
</html> 