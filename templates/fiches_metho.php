<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cantal Destination - Fiches Méthodologiques</title>
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('/static/css/responsive.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,900;1,400&family=Raleway:wght@300;400;500;600;700;800&family=Source+Code+Pro:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tsparticles@2.11.1/tsparticles.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tsparticles-plugin-emitters@2.2.3/tsparticles.plugin.emitters.min.js"></script>
    <script src="<?= asset('/static/js/utils.js') ?>"></script>
    <script src="<?= asset('/static/js/config.js') ?>"></script>
</head>
<body>
    <?php include '_navbar.php'; ?>
    <div id="tsparticles"></div>
    <div class="container" style="position: relative;">
        <aside class="sidebar">
            <div class="geometric-shape shape-1"></div>
            <div class="geometric-shape shape-2"></div>
            <div class="sidebar-header">
                <div class="logo-container">
                    <img src="" class="logo">
                </div>
                <!-- Titre retiré -->
                <p class="tagline">Analyse touristique du Cantal</p>
            </div>
            <h3><i class="fas fa-chart-line"></i> Fiches d'indicateurs</h3>
            <ul id="fiches-list" class="nav-list">
                <!-- Liste des fiches insérée par JavaScript -->
            </ul>
        </aside>
        <main class="content">
            <header>
                <div class="header-left">
                    <h1 class="header-title">Fiches Méthodologiques</h1>
                    <p class="header-subtitle">Dispositif de restitution standardisé des données FluxVision</p>
                </div>
                <div class="header-right">
                    <div class="destination-categories">
                        <div class="badge badge--mountain">
                            <i class="fas fa-mountain destination-icon icon-mountain"></i>
                            Montagne
                        </div>
                        <div class="badge badge--nature">
                            <i class="fas fa-water destination-icon icon-beach"></i>
                            Nature
                        </div>
                    </div>
                </div>
            </header>
            
            <div class="decorative-element top-right"></div>
            <div class="decorative-element bottom-left"></div>
            
            <div class="accent-shape accent-triangle"></div>
            <div class="accent-shape accent-circle"></div>
            <div class="accent-shape accent-square"></div>
            <div id="fiche-details">
                <!-- Détail de la fiche sélectionnée -->
                <div class="placeholder">
                    <i class="fas fa-file-alt"></i>
                    <p>Sélectionnez une fiche pour afficher les détails</p>
                </div>
            </div>
        </main>
    </div>

    <script src="<?= asset('/static/js/fiches.js') ?>"></script>
    <script src="<?= asset('/static/js/init.js') ?>"></script>
</body>
</html> 