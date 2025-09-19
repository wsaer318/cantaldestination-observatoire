<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cantal Destination - Observatoire Touristique</title>
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('/static/css/responsive.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,900;1,400&family=Raleway:wght@300;400;500;600;700;800&family=Source+Code+Pro:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tsparticles@2.11.1/tsparticles.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tsparticles-plugin-emitters@2.2.3/tsparticles.plugin.emitters.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Ressources pour la carte interactive (Réservées aux Administrateurs) -->
    <?php if (Auth::isAuthenticated() && Auth::getUser()['role'] === 'admin'): ?>
    <link rel="stylesheet" href="<?= asset('/static/css/cantal_map.css') ?>">
    <!-- Leaflet pour la carte interactive via CDN autorisé -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <?php endif; ?>
    <script src="<?= asset('/static/js/utils.js') ?>"></script>    <script src="<?= asset('/static/js/config.js') ?>"></script>
</head>
<body>
    <?php include '_navbar.php'; ?>
    
    <div id="tsparticles"></div>
    
    <div class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title shine-effect">Cantal Destination</h1>
            <p class="hero-subtitle">Observatoire Touristique du Cantal</p>
            <p class="hero-description">Notre mission d'observation touristique pour mesurer, comprendre et valoriser la fréquentation du département. Nous utilisons la technologie FluxVision d'Orange pour produire des analyses big data et accompagner les décisions stratégiques territoriales.</p>
            <div class="hero-actions">
                <a href="<?= url('/tdb_comparaison') ?>" class="btn hero-btn"><i class="fas fa-chart-bar"></i> Analyses Touristiques</a>
                <a href="<?= url('/fiches') ?>" class="btn btn--secondary hero-btn"><i class="fas fa-file-text"></i> Méthodologie</a>
            </div>
        </div>
        <div class="hero-image">
            <img src="https://www.cantal.fr/wp-content/uploads/2019/03/1363792661_cantal-destination.jpg" alt="Paysage du Cantal - Observatoire Touristique">
        </div>
    </div>
    
    <div class="container home-container" style="position: relative;">
        <div class="decorative-element top-right"></div>
        <div class="decorative-element bottom-left"></div>
        <div class="accent-shape accent-triangle"></div>
        <div class="accent-shape accent-circle"></div>
        <div class="accent-shape accent-square"></div>
        


        <!-- Section Mission Observatoire -->
        <section class="home-section">
            <h2 class="section-title"><i class="fas fa-eye"></i> Mission Observatoire</h2>
            <div class="section-content">
                <div class="mission-intro">
                    <p class="section-description">Nous développons une mission d'observation touristique pour accompagner les offices de tourisme et acteurs locaux dans la compréhension des dynamiques territoriales.</p>
                </div>
                <div class="mission-objectives">
                    <div class="objective-card">
                        <i class="fas fa-users objective-icon"></i>
                        <h3>Qui vient ?</h3>
                        <p>Origine géographique des visiteurs, segmentation touristes/excursionnistes, profils socio-démographiques</p>
                    </div>
                    <div class="objective-card">
                        <i class="fas fa-calendar-alt objective-icon"></i>
                        <h3>Quand ?</h3>
                        <p>Saisonnalité, vacances scolaires, week-ends, événements spéciaux, pics d'activité</p>
                    </div>
                    <div class="objective-card">
                        <i class="fas fa-map-marked-alt objective-icon"></i>
                        <h3>Où ?</h3>
                        <p>Zones de fréquentation, Lioran, Aurillac, Saint-Flour, mouvements dans le département</p>
                    </div>
                    <div class="objective-card">
                        <i class="fas fa-chart-bar objective-icon"></i>
                        <h3>Comparaisons</h3>
                        <p>Évolutions inter-années, benchmark territorial, forces et faiblesses du Cantal</p>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Section Carte du Cantal (Réservée aux Administrateurs) -->
        <?php if (Auth::isAuthenticated() && Auth::getUser()['role'] === 'admin'): ?>
        <section class="home-section">
            <h2 class="section-title">
                <i class="fas fa-map-marked-alt"></i> Zones d'Observation Touristique
                <span class="admin-badge" style="
                    background: linear-gradient(135deg, #ff6b6b, #ee5a24);
                    color: white;
                    font-size: 11px;
                    padding: 2px 8px;
                    border-radius: 12px;
                    margin-left: 10px;
                    font-weight: 500;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                ">
                    <i class="fas fa-shield-alt" style="margin-right: 3px;"></i>Admin
                </span>
            </h2>
            <div class="section-content">
                <div class="map-intro">
                    <p class="section-description">
                        <i class="fas fa-info-circle" style="color: #3498db; margin-right: 5px;"></i>
                        Carte administrative réservée aux gestionnaires. Découvrez les territoires du Cantal analysés par notre observatoire avec les contours administratifs officiels.
                    </p>
                </div>
                <div class="map-container">
                    <div id="cantal-map" class="cantal-interactive-map"></div>
                    <div class="map-info">
                        <div class="map-stats">
                            <div class="stat-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span class="stat-number">11</span>
                                <span class="stat-label">Zones actives</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-chart-line"></i>
                                <span class="stat-number">365j</span>
                                <span class="stat-label">Suivi annuel</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-users"></i>
                                <span class="stat-number">2M+</span>
                                <span class="stat-label">Visiteurs analysés</span>
                            </div>
                        </div>
                        <div class="map-actions">
                            <a href="<?= url('/tdb_comparaison') ?>" class="btn btn--secondary">
                                <i class="fas fa-chart-bar"></i> Analyser les données
                            </a>
                            <a href="<?= url('/admin') ?>" class="btn btn--primary" style="margin-left: 10px;">
                                <i class="fas fa-cog"></i> Administration
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- Section FluxVision Technology -->
        <section class="home-section">
            <h2 class="section-title"><i class="fas fa-mobile-alt"></i> Notre Outil : FluxVision</h2>
            <div class="section-content">
                <div class="technology-showcase">
                    <div class="tech-description">
                        <div class="tech-header">
                            <img src="<?= asset('/static/images/Orange-S.A.-Logo.png') ?>" alt="Orange Logo" height="40" loading="lazy">
                            <h3>Notre Partenariat avec Orange</h3>
                        </div>
                        <p>Nous utilisons FluxVision, qui exploite le <strong>bornage des téléphones portables Orange</strong> pour analyser la fréquentation touristique de manière anonymisée, 365 jours par an sur tout le territoire cantalien.</p>
                        <div class="tech-features">
                            <div class="feature-item">
                                <i class="fas fa-shield-alt"></i>
                                <span>Données 100% anonymisées</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-clock"></i>
                                <span>Suivi en temps réel</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-map"></i>
                                <span>Couverture territoriale complète</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-database"></i>
                                <span>Big Data touristique</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section Outils d'Analyse -->
        <section id="tools" class="home-section">
            <h2 class="section-title"><i class="fas fa-tools"></i> Outils d'Analyse</h2>
            <div class="section-content">
                <div class="features-grid">
                    <?php if (Auth::isAuthenticated() && Auth::getUser()['role'] === 'admin'): ?>
                    <div class="card card--feature">
                        <i class="fas fa-chart-bar feature-icon"></i>
                        <h3>Comparaison Avancée</h3>
                        <p>Comparaison détaillée entre différentes périodes et zones d'observation</p>
                        <a href="<?= url('/tdb_comparaison') ?>" class="btn btn--small">Comparer</a>
                    </div>
                    <?php endif; ?>
                    <div class="card card--feature">
                        <i class="fas fa-table feature-icon"></i>
                        <h3>Tables d'Indicateurs</h3>
                        <p>Export PDF, données structurées par blocs thématiques, chiffres de référence</p>
                        <a href="<?= url('/tables') ?>" class="btn btn--small">Consulter</a>
                    </div>
                    <div class="card card--feature">
                        <i class="fas fa-book-open feature-icon"></i>
                        <h3>Fiches Méthodologiques</h3>
                        <p>Documentation détaillée des 16 indicateurs, définitions, sources, modes de calcul</p>
                        <a href="<?= url('/fiches') ?>" class="btn btn--small">Voir les fiches</a>
                    </div>

                </div>
            </div>
        </section>
        
        <!-- Section Partenaires & OT -->
        <section class="home-section">
            <h2 class="section-title"><i class="fas fa-handshake"></i> Réseau Partenarial</h2>
            <div class="section-content">
                <div class="partners-intro">
                    <p class="section-description">Notre mission Observatoire s'appuie sur un réseau de <strong>9 Offices de Tourisme</strong> pour valider les indicateurs, enrichir les analyses et diffuser les restitutions territoriales.</p>
                </div>
                <div class="partners-grid">
                    <div class="partner-card">
                        <i class="fas fa-building partner-icon"></i>
                        <h3>Offices de Tourisme</h3>
                        <p>Aurillac, Saint-Flour et 7 autres OT collaborent pour définir les chiffres clés prioritaires</p>
                    </div>
                    <div class="partner-card">
                        <i class="fas fa-bed partner-icon"></i>
                        <h3>Hébergeurs</h3>
                        <p>Hôtels, campings, gîtes contribuent aux données d'occupation et de capacité</p>
                    </div>
                    <div class="partner-card">
                        <i class="fas fa-hiking partner-icon"></i>
                        <h3>Prestataires d'Activités</h3>
                        <p>Tourisme industriel, accompagnateurs, sites de visite enrichissent l'offre touristique</p>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Section Calendrier Restitutions -->
        <section id="calendar-section" class="home-section">
            <h2 class="section-title"><i class="fas fa-calendar-day"></i> Calendrier de Restitution</h2>
            <div class="section-content calendar-content">
                <div class="calendar-intro">
                    <p class="section-description">
                        <i class="fas fa-magic"></i> Analyses des données touristiques <?= date('Y') ?> - 
                        Périodes optimisées automatiquement depuis la base de données
                    </p>
                </div>
                
                <!-- Calendrier dynamique (géré par JavaScript) -->
                <div class="calendar-grid-simple" id="interactive-calendar">
                    <!-- Le contenu sera généré dynamiquement par smart_calendar.js -->
                    
                    <!-- Fallback statique en cas d'erreur JS -->
                    <noscript>
                        <div class="calendar-period printemps-period" data-season="printemps">
                            <div class="period-icon"><i class="fas fa-seedling"></i></div>
                            <h3>Printemps</h3>
                            <p>Mars - Mai</p>
                            <a href="<?= url('/tdb_comparaison?periode=printemps') ?>" class="btn btn--period">
                                <i class="fas fa-chart-bar"></i> Analyser
                            </a>
                        </div>
                        
                        <div class="calendar-period ete-period" data-season="ete">
                            <div class="period-icon"><i class="fas fa-sun"></i></div>
                            <h3>Été</h3>
                            <p>Juin - Août</p>
                            <a href="<?= url('/tdb_comparaison?periode=vacances_ete') ?>" class="btn btn--period">
                                <i class="fas fa-chart-bar"></i> Analyser
                            </a>
                        </div>
                        
                        <div class="calendar-period hiver-period" data-season="hiver">
                            <div class="period-icon"><i class="fas fa-snowflake"></i></div>
                            <h3>Hiver</h3>
                            <p>Décembre - Février</p>
                            <a href="<?= url('/tdb_comparaison?periode=vacances_hiver') ?>" class="btn btn--period">
                                <i class="fas fa-chart-bar"></i> Analyser
                            </a>
                        </div>
                        
                        <div class="calendar-period automne-period" data-season="automne">
                            <div class="period-icon"><i class="fas fa-leaf"></i></div>
                            <h3>Automne</h3>
                            <p>Septembre - Novembre</p>
                            <a href="<?= url('/tdb_comparaison?periode=vacances_toussaint') ?>" class="btn btn--period">
                                <i class="fas fa-chart-bar"></i> Analyser
                            </a>
                        </div>
                        
                        <div class="calendar-period annee-period" data-season="annee">
                            <div class="period-icon"><i class="fas fa-calendar-alt"></i></div>
                            <h3>Année</h3>
                            <p>Janvier - Décembre</p>
                            <a href="<?= url('/tdb_comparaison?periode=annee_complete') ?>" class="btn btn--period">
                                <i class="fas fa-chart-bar"></i> Analyser
                            </a>
                        </div>
                    </noscript>
                </div>
                
                <div class="calendar-current-period" id="current-period-info" style="display: none;">
                    <div class="current-period-header">
                        <i class="fas fa-calendar-check"></i>
                        <span>Période actuelle</span>
                    </div>
                    <div class="current-period-content">
                        <h4 id="current-period-name">-</h4>
                        <p id="current-period-description">-</p>
                        <a href="#" id="current-period-link" class="btn btn--primary btn--small">
                            <i class="fas fa-eye"></i> Voir les données actuelles
                        </a>
                    </div>
                </div>
                
                <div class="calendar-legend">
                    <p><i class="fas fa-info-circle"></i> <strong>Format de restitution :</strong> Slides PowerPoint 15 pages, rapports Word 5 pages, infographies selon les besoins des OT</p>
                    <p><i class="fas fa-users"></i> <strong>Public cible :</strong> Offices de Tourisme, élus, professionnels du tourisme, structures d'hébergement</p>
                </div>
                
                <div class="cta-container">
                    <a href="<?= url('/tables') ?>" class="btn btn--calendar">
                        <i class="fas fa-table"></i> Consulter les données actuelles
                    </a>
                </div>
            </div>
        </section>

        <!-- Section Footer Info -->
        <section class="home-section">
            <h2 class="section-title"><i class="fas fa-info-circle"></i> À propos</h2>
            <div class="section-content">
                <div class="about-grid">
                                         <div class="about-card">
                        <h3><i class="fas fa-landmark"></i> Qui sommes-nous ?</h3>
                        <p>Association financée par le Conseil départemental du Cantal, nous sommes dédiés à la promotion et au développement touristique territorial.</p>
                    </div>
                    <div class="about-card">
                        <h3><i class="fas fa-eye"></i> Notre Mission Observatoire</h3>
                        <p>Nous collectons, analysons et restituons les données de fréquentation pour accompagner les décisions stratégiques locales.</p>
                    </div>
                    <div class="about-card">
                        <h3><i class="fas fa-phone"></i> Notre Équipe</h3>
                        <p>5 personnes dédiées disponibles pour formations, support technique et accompagnement méthodologique.</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Données du calendrier intelligent injectées côté serveur -->
    <script>
        <?php 
        require_once 'includes/calendar_data_provider.php';
        $calendarData = CalendarDataProvider::getCalendarDataAsJson();
        ?>
        // Données du calendrier disponibles globalement
        window.CALENDAR_DATA = <?= $calendarData ?>;
        if (window.fvLog) { window.fvLog('Donn�es calendrier inject�es c�t� serveur', window.CALENDAR_DATA); }
    </script>
    
    <!-- Script spécifique à la page d'accueil -->
    <script src="<?= asset('/static/js/home.js') ?>"></script>
    <script src="<?= asset('/static/js/smart_calendar.js') ?>"></script>
    <script src="<?= asset('/static/js/init.js') ?>"></script>
    
    <!-- Carte interactive du Cantal (Réservée aux Administrateurs) -->
    <?php if (Auth::isAuthenticated() && Auth::getUser()['role'] === 'admin'): ?>
    <script src="<?= asset('/static/js/cantal_map.js') ?>"></script>
    <?php endif; ?>
</body>
</html> 


