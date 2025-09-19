<?php $isEmbed = isset($_GET['embed']); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEmbed ? 'Aperçu Infographie' : 'Infographie - Cantal Destination' ?></title>
    <link rel="icon" type="image/x-icon" href="data:image/x-icon;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==">
    <!-- Polices -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- CSS spécifique à la navbar -->
    <link rel="stylesheet" href="<?= asset('/static/css/navbar.css') ?>">
    <!-- CSS spécifique à la page infographie -->
    <link rel="stylesheet" href="<?= asset('/static/css/infographie_excursionnistes.css') ?>">
    <link rel="stylesheet" href="<?= asset('/static/css/infographie.css') ?>">
    <link rel="stylesheet" href="<?= asset('/static/css/period-picker-dark.css') ?>">
    <link rel="stylesheet" href="<?= asset('/static/css/header-tourisme.css') ?>">
</head>
<body class="<?= $isEmbed ? 'embedded' : '' ?>">
    <?php if (!$isEmbed) include '_navbar.php'; ?>

    <!-- Indicateur de chargement pour l'infographie -->
    <div id="infographie-loading" class="infographie-loading">
        <div class="infographie-loading-spinner"></div>
        <div class="infographie-loading-text">Génération de l'infographie...</div>
        <div class="infographie-loading-subtext">Chargement des données et création des graphiques</div>
        <div class="infographie-loading-progress">
            <div class="infographie-loading-progress-bar" id="infographie-loading-progress-bar"></div>
        </div>
    </div>

    <div class="page-wrapper">
        <header class="dataviz-header">
            <div class="container header-content">
                <h1>INFOGRAPHIE TOURISTIQUE</h1>
                <p class="subtitle">Synthèse Visuelle Complète // <span id="header-period">Période</span></p>
                <p class="period-info">
                    <i class="fa-regular fa-calendar-check"></i> Période : <span id="exc-start-date">--/--/----</span> au <span id="exc-end-date">--/--/----</span>
                </p>
            </div>
            <div class="header-decoration"></div>
        </header>

        <main class="main-content">
            <div class="container">
                
                <!-- Filtres pour l'infographie (même IDs que le tableau de bord) -->
                <section class="filters-section">
                    <div class="filters-container">
                        <div class="filter-group">
                            <label for="exc-year-select">Année :</label>
                            <select id="exc-year-select" class="filter-select">
                                <option value="">Chargement...</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="exc-period-select">Période :</label>
                            <select id="exc-period-select" class="filter-select">
                                <option value="">Chargement...</option>
                            </select>
                            <div class="period-picker-wrap" id="dashboardPeriodPicker">
                                <button type="button" class="period-picker-input" id="pp-toggle" aria-expanded="false" aria-haspopup="dialog" aria-controls="pp-panel" title="Sélecteur de période (avancé)">
                                    <span aria-hidden="true">📅</span>
                                    <span id="pp-display" class="pp-display">Sélecteur avancé…</span>
                                </button>
                                <div class="pp-panel floating" id="pp-panel" role="dialog" aria-modal="true" aria-label="Sélecteur de période" aria-hidden="true" inert>
                                    <button class="pp-close" id="pp-close" aria-label="Fermer">×</button>
                                    <aside class="pp-left">
                                        <div class="pp-title">Périodes</div>
                                        <div class="pp-list" id="pp-list"></div>
                                    </aside>
                                    <section class="pp-right">
                                        <div class="pp-title">Périodes calendaires</div>
                                        <div class="pp-controls">
                                            <label class="pp-muted" for="pp-month">Mois</label>
                                            <select id="pp-month"></select>
                                            <label class="pp-muted" for="pp-year-select">Année</label>
                                            <select id="pp-year-select" class="pp-year-select"></select>
                                            <div class="pp-nav">
                                                <button class="pp-btn" id="pp-prev-year" title="Année précédente">«</button>
                                                <button class="pp-btn" id="pp-prev-month" title="Mois précédent">‹</button>
                                                <button class="pp-btn" id="pp-next-month" title="Mois suivant">›</button>
                                                <button class="pp-btn" id="pp-next-year" title="Année suivante">»</button>
                                            </div>
                                        </div>
                                        <table class="pp-cal">
                                            <thead><tr><th>Lu</th><th>Ma</th><th>Me</th><th>Je</th><th>Ve</th><th>Sa</th><th>Di</th></tr></thead>
                                            <tbody id="pp-grid"></tbody>
                                        </table>
                                        <div class="pp-footer">
                                            <span class="pp-muted" id="pp-hint">Choisir une période prédéfinie (recommandé)</span>
                                            <button class="pp-btn" id="pp-today">Aujourd'hui</button>
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </div>
                        <div class="filter-group">
                            <label for="exc-zone-select">Zone d'observation :</label>
                            <select id="exc-zone-select" class="filter-select">
                                <option value="">Chargement...</option>
                            </select>
                        </div>
                    </div>
                </section>

                <!-- Bouton de téléchargement -->
                <section class="download-action-section">
                    <div class="download-action-container">
                        <a href="<?= url('/tdb_comparaison') ?>" class="btn btn--secondary" title="Retour au tableau de bord">
                            <i class="fa-solid fa-arrow-left"></i> Tableau de bord
                        </a>
                        <button id="btn-telecharger-infographie" class="btn-infographie" title="Télécharger l'infographie" disabled>
                            <div class="btn-icon">
                                <i class="fa-solid fa-download"></i>
                            </div>
                            <div class="btn-content">
                                <span class="btn-title">Télécharger</span>
                                <span class="btn-subtitle">Image HD</span>
                            </div>
                        </button>
                        <?php if (Auth::isAdmin()): ?>
                        <button id="btn-partager-infographie" class="btn-infographie" title="Partager l'infographie" disabled>
                            <div class="btn-icon">
                                <i class="fa-solid fa-share"></i>
                            </div>
                            <div class="btn-content">
                                <span class="btn-title">Partager</span>
                                <span class="btn-subtitle">Vers un espace</span>
                            </div>
                        </button>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Zone d'infographie -->
                <section class="infographie-section">
                    <div id="infographie-container" class="infographie-container">
                        <div class="infographie-placeholder">
                            <div class="placeholder-content">
                                <i class="fa-solid fa-chart-simple"></i>
                                <h3>Infographie Touristique</h3>
                                <p>Sélectionnez vos paramètres et cliquez sur "Générer l'infographie" pour créer une synthèse visuelle complète de vos données touristiques.</p>
                                <div class="placeholder-features">
                                    <div class="feature-item">
                                        <i class="fa-solid fa-chart-pie"></i>
                                        <span>Indicateurs clés</span>
                                    </div>
                                    <div class="feature-item">
                                        <i class="fa-solid fa-map-location-dot"></i>
                                        <span>Origines</span>
                                    </div>
                                    <div class="feature-item">
                                        <i class="fa-solid fa-trend-up"></i>
                                        <span>Évolutions temporelles</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Templates HTML pour l'infographie -->
                <template id="infographie-main-template">
                    <div class="infographie-content">
                        <div class="header-tourisme">
                            <div class="ht-left-top">
                                <img src="<?= asset('/static/images/logo/CANTAL_Logo_Rouge_RVB_150dpi.png') ?>" alt="Cantal Auvergne" />
                            </div>
                            <div class="ht-center">
                                <p class="ht-title-line line1">FRÉQUENTATION TOURISME</p>
                                <p class="ht-title-line line2">ÉTÉ 2025</p>
                                <p class="ht-title-line line3">ZONE CANTAL</p>
                            </div>
                            <div class="ht-right-top">
                                <img src="<?= asset('/static/images/logo/cantal_destination_RVB.jpg') ?>" alt="Cantal Destination" />
                            </div>
                            <div class="ht-left-bottom"></div>
                            <div class="ht-right-bottom">
                                <img class="ht-eu-logo" src="<?= asset('/static/images/logo/Logo Europe.jpg') ?>" alt="Union Européenne" />
                            </div>
                        </div>
                        
                        <div class="infographie-layout">
                            <!-- Section Touristes complète -->
                            <div class="tourist-section">
                                <div class="section-header">
                                    <h3><i class="fa-solid fa-bed"></i> Touristes (Nuitées)</h3>
                                </div>
                                
                                <!-- Indicateurs touristes -->
                                <div class="indicators-subsection">
                                    <div class="indicators-header">
                                        <h4><i class="fa-solid fa-chart-line"></i> Indicateurs Clés</h4>
                                    </div>
                                    <div class="indicators-row" data-indicators-nuitees></div>
                                </div>
                                
                                <!-- Origines touristes -->
                                <div class="origins-subsection">
                                    <div class="origins-header">
                                        <h4><i class="fa-solid fa-map-location-dot"></i> Origines Géographiques</h4>
                                    </div>
                                    <div class="origins-grid">
                                        <div class="chart-container chart-departements" tabindex="0">
                                            <div class="chart-header">
                                                <h4>Top 15 Départements émetteurs</h4>
                                                <div class="chart-subtitle">Tri sur la période courante</div>
                                            </div>
                                            <div class="chart-wrapper">
                                                <canvas id="nuitees-departements-chart"></canvas>
                                            </div>
                                        </div>
                                        
                                        <div class="chart-container chart-regions" tabindex="0">
                                            <div class="chart-header">
                                                <h4>Top 5 Régions émetteuses</h4>
                                                <div class="chart-subtitle">Tri sur la période courante</div>
                                            </div>
                                            <div class="chart-wrapper">
                                                <canvas id="nuitees-regions-chart"></canvas>
                                            </div>
                                        </div>
                                        
                                        <div class="chart-container chart-pays" tabindex="0">
                                            <div class="chart-header">
                                                <h4>Top 5 Pays émetteurs</h4>
                                                <div class="chart-subtitle">Tri sur la période courante</div>
                                            </div>
                                            <div class="chart-wrapper">
                                                <canvas id="nuitees-pays-chart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Durée de séjour (nouvelle section, pleine largeur) -->
                                <div class="stay-subsection">
                                    <div class="origins-header stay-header">
                                        <h4><i class="fa-solid fa-stopwatch"></i> Durée de séjour – Comportements</h4>
                                    </div>
                                    <div class="origins-grid">
                                        <div class="chart-container chart-stay-distribution full-width" tabindex="0">
                                            <div class="chart-header">
                                                <h4>Durée de séjour – Français vs International</h4>
                                            </div>
                                            <div class="chart-wrapper">
                                                <canvas id="infographie-stay-distribution"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mobilité Interne - Nouveau bloc -->
                                <div class="mobility-subsection">
                                    <div class="origins-header mobility-header">
                                        <h4><i class="fa-solid fa-route"></i> Mobilité Interne</h4>
                                    </div>
                                    <div class="origins-grid">
                                        <div class="chart-container chart-mobility full-width" tabindex="0">
                                            <div class="chart-header">
                                                <h4>Top 10 Destinations Touristiques</h4>
                                                <div class="chart-subtitle">Communes les plus attractives</div>
                                            </div>
                                            <div class="chart-wrapper">
                                                <canvas id="infographie-mobility-destinations"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section Excursionnistes complète -->
                            <div class="excursionist-section">
                                <div class="section-header">
                                    <h3><i class="fa-solid fa-person-hiking"></i> Excursionnistes (Présences)</h3>
                                </div>
                                
                                <!-- Indicateurs excursionnistes -->
                                <div class="indicators-subsection">
                                    <div class="indicators-header">
                                        <h4><i class="fa-solid fa-chart-line"></i> Indicateurs Clés</h4>
                                    </div>
                                    <div class="indicators-row" data-indicators-excursionnistes></div>
                                </div>
                                
                                <!-- Origines excursionnistes -->
                                <div class="origins-subsection">
                                    <div class="origins-header">
                                        <h4><i class="fa-solid fa-map-location-dot"></i> Origines Géographiques</h4>
                                    </div>
                                    <div class="origins-grid">
                                        <div class="chart-container chart-departements" tabindex="0">
                                            <div class="chart-header">
                                                <h4>Top 15 Départements émetteurs</h4>
                                                <div class="chart-subtitle">Tri sur la période courante</div>
                                            </div>
                                            <div class="chart-wrapper">
                                                <canvas id="excursionnistes-departements-chart"></canvas>
                                            </div>
                                        </div>
                                        
                                        <div class="chart-container chart-regions" tabindex="0">
                                            <div class="chart-header">
                                                <h4>Top 5 Régions émetteuses</h4>
                                                <div class="chart-subtitle">Tri sur la période courante</div>
                                            </div>
                                            <div class="chart-wrapper">
                                                <canvas id="excursionnistes-regions-chart"></canvas>
                                            </div>
                                        </div>
                                        
                                        <div class="chart-container chart-pays" tabindex="0">
                                            <div class="chart-header">
                                                <h4>Top 5 Pays émetteurs</h4>
                                                <div class="chart-subtitle">Tri sur la période courante</div>
                                            </div>
                                            <div class="chart-wrapper">
                                                <canvas id="excursionnistes-pays-chart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section Définitions compacte -->
                            <div class="definitions-compact">
                                <div class="definitions-compact-header">
                                    <h4><i class="fa-solid fa-info-circle"></i> Définitions FluxVision</h4>
                                </div>
                                <div class="definitions-compact-content">
                                    <div class="definition-compact-item">
                                        <span class="definition-label"><i class="fa-solid fa-bed"></i> Touriste :</span>
                                        <span class="definition-text">Visiteur non résident, non habituel, dormant au moins une nuit sur place, présence peu fréquente.</span>
                                    </div>
                                    <div class="definition-compact-item">
                                        <span class="definition-label"><i class="fa-solid fa-person-hiking"></i> Excursionniste :</span>
                                        <span class="definition-text">Visiteur non résidant, qui reste seulement la journée (plus de 2h), sans nuitée, avec une fréquence de visite occasionnelle (moins de 5 fois sur 15 jours).</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="infographie-footer">
                            <div class="partners-strip">
                                <div class="partners-row">
                                    <img src="<?= asset('/static/images/logo/2 Logo Destination_Haut_Cantal.png') ?>" alt="Destination Haut Cantal" />
                                    <img src="<?= asset('/static/images/logo/5 logo hautes-terres-tourisme.png') ?>" alt="Hautes Terres Tourisme" />
                                    <img src="<?= asset('/static/images/logo/Le-Lioran-logo-couleur.png') ?>" alt="Le Lioran" />
                                    <img src="<?= asset('/static/images/logo/9 logo OT Chataigneraie cantalienne.png') ?>" alt="Châtaigneraie Cantalienne Destination" />
                                    <img src="<?= asset('/static/images/logo/Carladès Tourisme MC carré bleu.jpg') ?>" alt="Carladès Tourisme — Massif Cantalien" />
                                    <img src="<?= asset('/static/images/logo/7 logo Pays d_Aurillac.png') ?>" alt="Pays d'Aurillac Tourisme" />
                                    <img src="<?= asset('/static/images/logo/4 logo Pays de saint flour oti.jpg') ?>" alt="Les Pays de Saint-Flour" />
                                    <img src="<?= asset('/static/images/logo/3 logo pays de salers.png') ?>" alt="Pays de Salers — Office de Tourisme" />
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <template id="key-indicator-template">
                    <div class="indicator-card-compact">
                        <div class="indicator-icon" data-icon></div>
                        <div class="indicator-content">
                            <div class="indicator-title" data-title></div>
                            <div class="indicator-unit" data-unit></div>
                            <div class="indicator-history" data-history></div>
                        </div>
                    </div>
                </template>



            </div>
        </main>

        <footer class="dataviz-footer">
            <div class="container">
                <p>Source : FluxVision</p>
            </div>
        </footer>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <!-- html2canvas pour capturer l'infographie -->
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <!-- Scripts regroupés -->
    <script src="<?= asset('/static/dist/infographie.bundle.js?v=' . APP_VERSION) ?>" defer></script>
</body>
</html> 
