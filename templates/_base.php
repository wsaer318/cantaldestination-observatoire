<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Cantal Destination - Observatoire Touristique' ?></title>
    
    <!-- Configuration JavaScript dynamique (doit être chargée en premier) -->
    <?php include 'config_js.php'; ?>
    
    <!-- CSS avec chemins de secours -->
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('/static/css/responsive.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- CSS inline de secours si les fichiers ne se chargent pas -->
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .card { background: white; border: 1px solid #ddd; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .btn { background: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
        .text-center { text-align: center; }
        .error-message { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .success-message { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
    
    <?= $additionalHead ?? '' ?>
</head>
<body class="<?= $bodyClass ?? '' ?>">
    <?= $content ?? '' ?>
    
    <!-- Scripts JavaScript -->
    <script src="<?= asset('/static/js/config.js') ?>"></script>
    <?= $additionalScripts ?? '' ?>
</body>
</html>
