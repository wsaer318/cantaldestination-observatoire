<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cantal Destination - Observatoire Touristique - Accès sécurisé</title>
    <!-- CSS avec chemins de secours -->
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('/static/css/responsive.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- CSS inline de secours si les fichiers ne se chargent pas -->
    <style>
        .container { max-width: 400px; margin: 50px auto; padding: 20px; }
        .card { background: white; border: 1px solid #ddd; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; }
        .btn:hover { background: #0056b3; }

        .text-center { text-align: center; }
        .back-link { display: inline-block; margin-top: 20px; color: #007bff; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
                            <h1>Cantal Destination<br><span style="font-size: 0.7em; font-weight: normal;">Observatoire Touristique</span></h1>
            <p>Accès sécurisé au tableau de bord</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= url('/login') ?>" method="POST">
            <!-- Token CSRF -->
            <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
            
            <!-- URL de redirection après connexion -->
            <?php if (isset($_GET['redirect'])): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect']) ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" placeholder="Saisissez votre nom d'utilisateur" required autocomplete="username">
                <i class="fas fa-user"></i>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" placeholder="Saisissez votre mot de passe" required autocomplete="current-password">
                <i class="fas fa-lock"></i>
            </div>

            <button type="submit" class="btn btn--primary login-submit">
                <i class="fas fa-sign-in-alt"></i>
                Se connecter
            </button>
        </form>



        <div class="back-link">
            <a href="/fluxvision_fin/">
                <i class="fas fa-arrow-left"></i>
                Retour à l'accueil
            </a>
        </div>
    </div>
</body>
</html> 
