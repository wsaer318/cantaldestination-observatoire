<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Méthodologie FluxVision - Cantal Destination</title>
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="methodology-page">
    <?php include '_navbar.php'; ?>
    
    <div class="methodology-content">
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-info-circle"></i> Méthodologie FluxVision</h1>
                <p class="page-subtitle">Comprendre la technologie FluxVision de collecte et d'analyse des données touristiques</p>
            </div>
        </div>
                <section class="methodology-section">
                    <h2><i class="fas fa-mobile-alt"></i> Principe de la Technologie</h2>
                    <div class="method-cards">
                        <div class="method-card">
                            <h3>Collecte des Signaux</h3>
                            <p>FluxVision collecte les signaux anonymisés des téléphones mobiles via le réseau Orange, sans accès aux données personnelles ou contenu des communications.</p>
                        </div>
                        <div class="method-card">
                            <h3>Big Data Touristique</h3>
                            <p>Traitement de millions de données quotidiennes pour identifier les flux touristiques et comportements de mobilité sur le territoire.</p>
                        </div>
                        <div class="method-card">
                            <h3>Anonymisation</h3>
                            <p>Toutes les données sont anonymisées et agrégées selon les standards RGPD, garantissant la protection de la vie privée.</p>
                        </div>
                    </div>
                </section>

                <section class="methodology-section">
                    <h2><i class="fas fa-users"></i> Définition des Profils</h2>
                    <div class="profiles-grid">
                        <div class="profile-card">
                            <h3>Résidents</h3>
                            <p>Personnes domiciliées sur le territoire, identifiées par la récurrence de leur présence nocturne sur une période de référence.</p>
                        </div>
                        <div class="profile-card">
                            <h3>Touristes</h3>
                            <p>Visiteurs non-résidents présents au moins une nuit sur le territoire, incluant hébergement marchand et non-marchand.</p>
                        </div>
                        <div class="profile-card">
                            <h3>Excursionnistes</h3>
                            <p>Visiteurs présents sur le territoire en journée uniquement, sans nuitée, identifiés par leurs déplacements diurnes.</p>
                        </div>
                    </div>
                </section>

                <section class="methodology-section">
                    <h2><i class="fas fa-chart-bar"></i> Indicateurs Calculés</h2>
                    <div class="indicators-list">
                        <div class="indicator-item">
                            <h4>Volume de Fréquentation</h4>
                            <p>Nombre de visiteurs uniques par période et zone géographique</p>
                        </div>
                        <div class="indicator-item">
                            <h4>Durée de Séjour</h4>
                            <p>Temps moyen de présence des touristes sur le territoire</p>
                        </div>
                        <div class="indicator-item">
                            <h4>Origine Géographique</h4>
                            <p>Provenance des visiteurs (département, région, pays de résidence)</p>
                        </div>
                        <div class="indicator-item">
                            <h4>Saisonnalité</h4>
                            <p>Répartition temporelle des flux touristiques</p>
                        </div>
                    </div>
                </section>

                <section class="methodology-section">
                    <h2><i class="fas fa-shield-alt"></i> Conformité et Éthique</h2>
                    <div class="compliance-info">
                        <div class="compliance-item">
                            <h4>RGPD Compliant</h4>
                            <p>Respect total du Règlement Général sur la Protection des Données</p>
                        </div>
                        <div class="compliance-item">
                            <h4>Agrégation Statistique</h4>
                            <p>Données présentées uniquement sous forme agrégée, seuils minimum appliqués</p>
                        </div>
                        <div class="compliance-item">
                            <h4>Partenariat Orange</h4>
                            <p>Collaboration officielle avec Orange Business Services pour l'accès sécurisé aux données</p>
                        </div>
                    </div>
                </section>
    </div>

    <script src="<?= asset('/static/js/config.js') ?>"></script>
</body>
</html> 