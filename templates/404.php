<?php
// Si l'utilisateur n'est pas connecté, le rediriger vers login
if (!Auth::isAuthenticated()) {
    header('Location: ' . url('/login'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page non trouvée - Cantal Destination Observatoire</title>
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '_navbar.php'; ?>
    
    <div class="container">
        <div class="content" style="text-align: center; padding: 60px 20px;">
            <div class="error-container">
                <div class="error-icon">
                    <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: var(--accent2); margin-bottom: 20px;"></i>
                </div>
                
                <h1 style="font-family: var(--title-font); font-size: 2.5rem; color: var(--primary); margin-bottom: 15px;">
                    Page non trouvée
                </h1>
                
                <p style="font-size: 1.2rem; color: var(--on-surface); margin-bottom: 30px; opacity: 0.8;">
                    La page que vous recherchez n'existe pas ou a été déplacée.
                </p>
                
                <div class="error-actions">
                    <a href="<?= url('/') ?>" class="btn btn--primary">
                        <i class="fas fa-home"></i>
                        Retour à l'accueil
                    </a>
                    
                    <a href="<?= url('/tables') ?>" class="btn btn--secondary" style="margin-left: 15px;">
                        <i class="fas fa-chart-line"></i>
                        Tableau de bord
                    </a>
                </div>
                
                <div style="margin-top: 40px; opacity: 0.6;">
                    <p style="font-size: 0.9rem;">
                        Si vous pensez qu'il s'agit d'une erreur, contactez l'équipe technique.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 