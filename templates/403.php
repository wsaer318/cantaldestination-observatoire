<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès interdit - Cantal Destination Observatoire</title>
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="error-page">
        <div class="error-content">
            <div class="error-icon">
                <i class="fas fa-ban"></i>
            </div>
            <h1>403</h1>
            <h2>Accès interdit</h2>
            <p>Vous n'avez pas les permissions nécessaires pour accéder à cette page.</p>
            <a href="<?= url('/') ?>" class="btn btn--primary">
                <i class="fas fa-home"></i>
                Retour à l'accueil
            </a>
        </div>
    </div>
</body>
</html> 