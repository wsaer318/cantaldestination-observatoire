<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cantal Destination - Tableau de bord Comparaison</title>
    <link rel="icon" type="image/x-icon" href="data:image/x-icon;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==">
    <!-- Polices -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- CSS spécifique à la navbar -->
    <link rel="stylesheet" href="<?= asset('/static/css/navbar.css') ?>">
    <!-- CSS spécifique à la page TDB excursionnistes -->
    <link rel="stylesheet" href="<?= asset('/static/css/tdb_excursionnistes.css') ?>">
    <!-- CSS spécifique au bloc de comparaison -->
    <link rel="stylesheet" href="<?= asset('/static/css/tdb_comparaison.css') ?>">
    <!-- CSS responsive -->
    <link rel="stylesheet" href="<?= asset('/static/css/responsive.css') ?>">
    <script src="<?= asset('/static/js/utils.js') ?>"></script>    <script src="<?= asset('/static/js/config.js') ?>"></script>
</head>
<body>
    <?php include '_navbar.php'; ?>

    <div class="page-wrapper">
        <header class="dataviz-header">
            <div class="container header-content">
                <h1>TABLEAU DE BORD COMPARAISON</h1>
                <p class="subtitle">Analyse des Données Touristiques // <span id="header-period">Période</span></p>
                <p class="period-info">
                    <i class="fa-regular fa-calendar-check"></i> Période : <span id="exc-start-date">--/--/----</span> au <span id="exc-end-date">--/--/----</span>
                </p>
            </div>
            <div class="header-decoration"></div>
        </header>

        <main class="main-content">
            <div class="container">

                <!-- Mode Selector -->
                <section class="mode-selector-section">
                    <div class="mode-toggle-container">
                        <div class="mode-toggle">
                            <input type="radio" id="mode-normal" name="view-mode" value="normal" checked>
                            <label for="mode-normal" class="mode-option">
                                <i class="fa-solid fa-chart-line"></i>
                                <span>Analyse Standard</span>
                            </label>
                            
                            <input type="radio" id="mode-comparison" name="view-mode" value="comparison">
                            <label for="mode-comparison" class="mode-option">
                                <i class="fa-solid fa-chart-bar"></i>
                                <span>Comparaison Avancée</span>
                            </label>
                        </div>
                    </div>
                </section>

                <!-- Filtres Standards (Mode Normal) -->
                <section class="filters-section" id="normal-filters">
                    <div class="filters-container">
                        <div class="filter-group">
                            <label for="exc-year-select">Année :</label>
                            <select id="exc-year-select" class="filter-select">
                                <option value="">Chargement...</option>
                            </select>
                        </div>
                             <div class="filter-group">
                                 <label for="exc-compare-year-select">Année de comparaison :</label>
                                 <select id="exc-compare-year-select" class="filter-select">
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
                                 <div class="pp-panel" id="pp-panel" role="dialog" aria-modal="true" aria-label="Sélecteur de période">
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
                                                 <button class="pp-btn" id="pp-prev-year">«</button>
                                                 <button class="pp-btn" id="pp-prev-month">‹</button>
                                                 <button class="pp-btn" id="pp-next-month">›</button>
                                                 <button class="pp-btn" id="pp-next-year">»</button>
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

                <!-- Bouton infographie -->
                <section class="infographie-action-section">
                    <div class="infographie-action-container">
                        <a href="<?= url('/infographie') ?>" id="btn-infographie" class="btn-infographie" title="Générer une infographie complète">
                            <div class="btn-icon">
                                <i class="fa-solid fa-chart-simple"></i>
                            </div>
                            <div class="btn-content">
                                <span class="btn-title">Infographie</span>
                                <span class="btn-subtitle">Synthèse visuelle complète</span>
                            </div>
                        </a>
                    </div>
                </section>

                <!-- Section Bloc de Comparaison (Mode Avancé - À venir) -->
                <section class="section comparison-section" id="comparison-filters" style="display: none;">
                    <div class="panel fade-in-up">
                        <h2 class="panel-title">
                            <i class="fa-solid fa-chart-bar"></i> Comparaison par Période et Zone d'Observation
                        </h2>
                        
                        <!-- Filtres de comparaison -->
                        <div class="comparison-filters">
                            <div class="comparison-filter-row">
                                <div class="comparison-filter-group">
                                    <h4><i class="fa-solid fa-circle" style="color: #007bff;"></i> Période A</h4>
                                    <div class="filter-subgroup">
                                        <label for="comp-period-a">Période :</label>
                                        <select id="comp-period-a" class="filter-select"></select>
                                    </div>
                                    <div class="filter-subgroup">
                                        <label for="comp-zone-a">Zone :</label>
                                        <select id="comp-zone-a" class="filter-select"></select>
                                    </div>
                                    <div class="filter-subgroup">
                                        <label for="comp-year-a">Année :</label>
                                        <select id="comp-year-a" class="filter-select"></select>
                                    </div>
                                </div>
                                
                                <div class="comparison-vs">
                                    <i class="fa-solid fa-arrows-left-right"></i>
                                    <span>VS</span>
                                </div>
                                
                                <div class="comparison-filter-group">
                                    <h4><i class="fa-solid fa-circle" style="color: #dc3545;"></i> Période B</h4>
                                    <div class="filter-subgroup">
                                        <label for="comp-period-b">Période :</label>
                                        <select id="comp-period-b" class="filter-select"></select>
                                    </div>
                                    <div class="filter-subgroup">
                                        <label for="comp-zone-b">Zone :</label>
                                        <select id="comp-zone-b" class="filter-select"></select>
                                    </div>
                                    <div class="filter-subgroup">
                                        <label for="comp-year-b">Année :</label>
                                        <select id="comp-year-b" class="filter-select"></select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="comparison-actions">
                                <button id="comp-compare-btn" class="btn btn-primary">
                                    <i class="fa-solid fa-chart-column"></i> Comparer
                                </button>
                                <button id="comp-reset-btn" class="btn btn-secondary">
                                    <i class="fa-solid fa-refresh"></i> Réinitialiser
                                </button>
                            </div>
                        </div>
                        
                    </div>
                </section>

                <!-- Résultats de comparaison (Mode Avancé) -->
                <section class="comparison-results-section" id="comparison-results-section" style="display: none;">
                    <div class="comparison-results fade-in-up" id="comparison-results">
                        <div class="loading">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                            Préparation de la comparaison...
                        </div>
                    </div>
                </section>

                <!-- Navigation par onglets -->
                <div class="tabs-container">
                    <div class="tabs-navigation">
                        <button class="tab-button active" data-tab="tourists">
                            <i class="fa-solid fa-moon"></i> Touristes
                        </button>
                        <button class="tab-button" data-tab="excursionists">
                            <i class="fa-solid fa-person-hiking"></i> Excursionnistes
                        </button>
                    </div>
                    
                    <!-- Comparaison succincte -->
                    <div class="comparison-card fade-in-up">
                        <div class="comparison-title"><i class="fa-solid fa-chart-column"></i> Totaux Actuels</div>
                        <div class="comparison-content">
                            <div class="comparison-item">
                                <div class="comparison-label">Touristes</div>
                                <div class="comparison-value" id="tourists-total">--</div>
                                <div class="comparison-unit">nuitées</div>
                            </div>
                            <div class="comparison-divider"></div>
                            <div class="comparison-item">
                                <div class="comparison-label">Excursionnistes</div>
                                <div class="comparison-value" id="excursionists-total">--</div>
                                <div class="comparison-unit">présences</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenu des onglets -->
                <div class="tab-content">
                    <!-- Onglet Touristes -->
                    <div class="tab-pane active fade-in-up" id="tourists-tab">
                        <div class="dashboard-grid fade-in-up">
                            <!-- Colonne gauche - KPIs -->
                            <div class="dashboard-column kpi-column">
                                <div class="panel fade-in-up">
                                    <h2 class="panel-title"><i class="fa-solid fa-chart-line"></i> Indicateurs Clés</h2>
                                    <div class="key-figures-grid" id="key-figures-grid">
                                        <div class="loading">Chargement...</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Colonne droite - Visualisations -->
                            <div class="dashboard-column viz-column">
                                <!-- Section Origines -->
                                <div class="panel fade-in-up">
                                    <h2 class="panel-title"><i class="fa-solid fa-globe"></i> Origines des Touristes</h2>
                                    <div class="charts-grid charts-grid-two">
                                        <div class="chart-card hover-scale fade-in-up">
                                            <h3><i class="fa-solid fa-map-location-dot"></i> Top 15 Départements (Nuitées)</h3>
                                            <div class="chart-container"><canvas id="chart-departements"></canvas></div>
                                            <p class="chart-summary">Focus sur les 15 principaux départements d'origine.</p>
                                        </div>
                                        <div class="chart-card hover-scale fade-in-up">
                                            <h3><i class="fa-solid fa-map"></i> Top 5 Régions (Nuitées)</h3>
                                            <div class="chart-container"><canvas id="chart-regions"></canvas></div>
                                            <p class="chart-summary">Focus sur les 5 principales régions d'origine françaises.</p>
                                        </div>
                                        <div class="chart-card hover-scale fade-in-up">
                                            <h3><i class="fa-solid fa-flag"></i> Top 5 Pays (Nuitées)</h3>
                                            <div class="chart-container"><canvas id="chart-pays"></canvas></div>
                                            <p class="chart-summary">Focus sur les 5 principaux pays d'origine internationaux.</p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Section Mobilité Interne -->
                                <div class="panel fade-in-up">
                                    <h2 class="panel-title"><i class="fa-solid fa-route"></i> Mobilité Interne</h2>
                                    <div class="charts-grid">
                                        <div class="chart-card hover-scale fade-in-up">
                                            <h3><i class="fa-solid fa-chart-bar"></i> Mobilité Interne - Bar Plot</h3>
                                            <div class="chart-container"><canvas id="chart-mobility-destinations"></canvas></div>
                                            <p class="chart-summary">Analyse de la mobilité interne des touristes dans la région.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section Comportements – Durée de séjour -->
                                <div class="panel fade-in-up">
                                    <h2 class="panel-title"><i class="fa-solid fa-stopwatch"></i> Comportements – Durée de séjour</h2>
                                    <div class="charts-grid stay-duration-grid">
                                        <div class="chart-card hover-scale fade-in-up">
                                            <h3><i class="fa-solid fa-clock"></i> Distribution des durées – Français vs International</h3>
                                            <div class="chart-container"><canvas id="chart-stay-distribution-combined"></canvas></div>
                                            <p class="chart-summary">Diagramme en bande 100% empilé par classe de durée; comparaison Français et International.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Onglet Excursionnistes -->
                    <div class="tab-pane fade-in-up" id="excursionists-tab">
                        <div class="dashboard-grid fade-in-up">
                            <!-- Colonne gauche - KPIs -->
                            <div class="dashboard-column kpi-column">
                                <div class="panel fade-in-up">
                                    <h2 class="panel-title"><i class="fa-solid fa-chart-line"></i> Indicateurs Clés </h2>
                                    <div class="key-figures-grid" id="exc-key-figures-grid">
                                        <div class="loading">Chargement...</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Colonne droite - Visualisations -->
                            <div class="dashboard-column viz-column">
                                <!-- Section Origines -->
                                <div class="panel fade-in-up">
                                    <h2 class="panel-title origin-title"><i class="fa-solid fa-globe"></i> Origines des Excursionnistes</h2>
                                    <div class="charts-grid">
                                        <div class="chart-card hover-scale fade-in-up">
                                            <h3><i class="fa-solid fa-map-location-dot"></i> Top 15 Départements (Présences)</h3>
                                            <div class="chart-container"><canvas id="exc-chart-departements"></canvas></div>
                                            <p class="chart-summary">Focus sur les 15 principaux départements d'origine.</p>
                                        </div>
                                        <div class="chart-card hover-scale fade-in-up">
                                            <h3><i class="fa-solid fa-map"></i> Top 5 Régions (Présences)</h3>
                                            <div class="chart-container"><canvas id="exc-chart-regions"></canvas></div>
                                            <p class="chart-summary">Focus sur les 5 principales régions d'origine françaises.</p>
                                        </div>
                                        <div class="chart-card hover-scale fade-in-up">
                                            <h3><i class="fa-solid fa-flag"></i> Top 5 Pays (Présences)</h3>
                                            <div class="chart-container"><canvas id="exc-chart-pays"></canvas></div>
                                            <p class="chart-summary">Focus sur les 5 principaux pays d'origine internationaux.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="dataviz-footer">
            <div class="container">
                <p>Source : FluxVision | <span id="exc-footer-year">2025</span></p>
            </div>
        </footer>
    </div><!-- Fin page-wrapper -->

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <!-- html2canvas pour capturer les grilles d'indicateurs -->
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <!-- Configuration dynamique FluxVision -->
    <script src="<?= asset('/static/js/flux-vision-config.js') ?>"></script>
    <!-- Scripts désactivés temporairement pour isoler le problème -->
    <!-- <script src="<?= asset('/static/js/tdb_init.js') ?>"></script> -->
    <script src="<?= asset('/static/js/mode_toggle.js') ?>"></script>
    
    <!-- Script JS intégré pour la comparaison - contient tout le code nécessaire -->
    <script src="<?= asset('/static/js/tdb_comparaison.js') ?>"></script>
    <!-- Racine de portail UI pour les popovers/dialogs -->
    <div id="portal-root"></div>
</body>
</html>