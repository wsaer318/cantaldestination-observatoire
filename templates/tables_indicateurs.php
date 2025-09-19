<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cantal Destination - Tables des Indicateurs</title>
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('/static/css/responsive.css') ?>"
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,900;1,400&family=Raleway:wght@300;400;500;600;700;800&family=Source+Code+Pro:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tsparticles@2.11.1/tsparticles.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tsparticles-plugin-emitters@2.2.3/tsparticles.plugin.emitters.min.js"></script>
    <script src="<?= asset('/static/js/utils.js') ?>"></script>    <script src="<?= asset('/static/js/config.js') ?>"></script>
</head>
<body>
    <?php include '_navbar.php'; ?>
    <div id="tsparticles"></div>
    <div class="container" style="position: relative;">
        <div class="decorative-element top-right"></div>
        <div class="decorative-element bottom-left"></div>
        <div class="accent-shape accent-triangle"></div>
        <div class="accent-shape accent-circle"></div>
        <div class="accent-shape accent-square"></div>
        <aside class="sidebar">
            <div class="geometric-shape shape-1"></div>
            <div class="geometric-shape shape-2"></div>
            <div class="sidebar-header">
                <div class="logo-container">
                    <img src="" class="logo">
                </div>
                <p class="tagline">Analyse touristique du Cantal</p>
            </div>
            <h3><i class="fas fa-table"></i> Tables d'indicateurs</h3>
            <ul class="nav-list">
                <li><a href="#bloc-a" class="active"><i class="fas fa-star"></i> Bloc A – Indicateurs principaux (Nuitées & Présences)</a></li>
                <li><a href="#bloc-b"><i class="fas fa-plus-circle"></i> Bloc B – Indicateurs complémentaires</a></li>
                <li><a href="#bloc-d1"><i class="fas fa-map-marker-alt"></i> D1 – Origine par départements (Top 15)</a></li>
                <li><a href="#bloc-d2"><i class="fas fa-map"></i> D2 – Origine par régions (Top 5)</a></li>
                <li><a href="#bloc-d3"><i class="fas fa-flag"></i> D3 – Origine par pays (Top 5)</a></li>
                <li><a href="#bloc-d4"><i class="fas fa-calendar-alt"></i> D4 – Périodes clés mi-saison</a></li>
                <li><a href="#bloc-d5"><i class="fas fa-briefcase"></i> D5 – CSP Top 3</a></li>
                <li><a href="#bloc-d6"><i class="fas fa-user-friends"></i> D6 – Tranches d'âge Top 3</a></li>
            </ul>
        </aside>
        <main class="content">
            <header>
                <div class="header-left">
                    <h1 class="header-title">Tables des Indicateurs</h1>
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
            
            <div id="tables-content">
                <section id="introduction">
                    <p class="lead-text">
                        Ce dispositif de restitution standardisé permet d'analyser la fréquentation touristique 
                        du département du Cantal et des territoires partenaires à travers des indicateurs clés 
                        issus de l'outil FluxVision.
                    </p>
                </section>

                <section id="filter-panel" class="indicator-section">
                    <h2>Filtrer les données</h2>
                    <div class="filter-container">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="year-select">Année de référence (N)</label>
                                <select id="year-select" class="filter-select">
                                    <!-- Options générées dynamiquement -->
                                </select>
                            </div>
                        </div>
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="period-select">Période</label>
                                <select id="period-select" class="filter-select">
                                    <!-- Options générées dynamiquement -->
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="territory-select">Zone d'observation</label>
                                <select id="territory-select" class="filter-select">
                                    <!-- Options générées dynamiquement -->
                                </select>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button id="apply-filter" class="btn btn--filter">
                                <i class="fas fa-filter"></i> Appliquer les filtres
                            </button>
                            <button id="reset-filter" class="btn btn--filter btn--filter-secondary">
                                <i class="fas fa-undo"></i> Réinitialiser
                            </button>
                            <button id="export-pdf" class="btn btn--filter btn--export">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </button>
                        </div>
                    </div>
                </section>

                <section id="bloc-a" class="indicator-section">
                    <h2>Bloc A – Indicateurs principaux (Nuitées & Présences)</h2>
                    <div class="table-responsive">
                        <table class="table table--indicators">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Indicateur</th>
                                    <th>N</th>
                                    <th>N‑1</th>
                                    <th>Δ %</th>
                                    <th>Unité</th>
                                    <th>Source</th>
                                    <th>Remarques</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td>Nuitées</td>
                                    <td>FluxVision</td>
                                    <td>Non-locaux</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td>Nuitées</td>
                                    <td>FluxVision</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td>Nuitées</td>
                                    <td>FluxVision</td>
                                    <td>Filtrer certaines nationalités si bruit</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td>Nuitées</td>
                                    <td>FluxVision</td>
                                    <td>↗ D1</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td>Nuitées</td>
                                    <td>FluxVision</td>
                                    <td>↗ D2</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td>Nuitées</td>
                                    <td>FluxVision</td>
                                    <td>↗ D3</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td>Présences</td>
                                    <td>FluxVision</td>
                                    <td>↗ D4</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td>%</td>
                                    <td>FluxVision</td>
                                    <td>% requis (complétude)</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td>%</td>
                                    <td>FluxVision</td>
                                    <td>% requis (complétude)</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td>FluxVision</td>
                                    <td>Origine DEPT & INTL</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td>Présences</td>
                                    <td>FluxVision</td>
                                    <td>Les 2 premiers samedis des vac hiver</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td>Présences</td>
                                    <td>FluxVision</td>
                                    <td>Trail, Festival…<br>3e samedi vac hiver</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="bloc-b" class="indicator-section">
                    <h2>Bloc B – Indicateurs complémentaires</h2>
                    <div class="table-responsive">
                        <table class="table table--indicators">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Indicateur</th>
                                    <th>Valeur</th>
                                    <th>Unité</th>
                                    <th>Source</th>
                                    <th>Rôle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>13</td>
                                    <td>Éco‑compteurs</td>
                                    <td></td>
                                    <td>Passages</td>
                                    <td>Éco‑compteurs</td>
                                    <td>Flux terrain</td>
                                </tr>
                                <tr>
                                    <td>14</td>
                                    <td>OpenSystem</td>
                                    <td></td>
                                    <td>Réservations</td>
                                    <td>OpenSystem</td>
                                    <td>Occupation hébergements</td>
                                </tr>
                                <tr>
                                    <td>15</td>
                                    <td>Statistiques web</td>
                                    <td></td>
                                    <td>Visites</td>
                                    <td>Analytics OT</td>
                                    <td>Intérêt en ligne</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="note"><i class="fas fa-info-circle"></i> Note : Le Bloc B contextualise le Bloc A ; il s'agit de données externes à FluxVision.</p>
                </section>

                <section id="bloc-d1" class="indicator-section">
                    <h2>D1 – Origine par départements (Top 15)</h2>
                    <div class="table-responsive">
                        <table class="table table--indicators">
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th>Département</th>
                                    <th>Nuitées N</th>
                                    <th>Nuitées N‑1</th>
                                    <th>Δ %</th>
                                    <th>Part % (N)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>4</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>5</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="bloc-d2" class="indicator-section">
                    <h2>D2 – Origine par régions (Top 5)</h2>
                    <div class="table-responsive">
                        <table class="table table--indicators">
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th>Région</th>
                                    <th>Nuitées N</th>
                                    <th>Nuitées N‑1</th>
                                    <th>Δ %</th>
                                    <th>Part % (N)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>4</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>5</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="bloc-d3" class="indicator-section">
                    <h2>D3 – Origine par pays (Top 5)</h2>
                    <div class="table-responsive">
                        <table class="table table--indicators">
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th>Pays</th>
                                    <th>Nuitées N</th>
                                    <th>Nuitées N‑1</th>
                                    <th>Δ %</th>
                                    <th>Part % (N)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>4</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>5</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="bloc-d4" class="indicator-section">
                    <h2>D4 – Fréquentation des périodes clés (mi‑saison)</h2>
                    <div class="table-responsive">
                        <table class="table table--indicators">
                            <thead>
                                <tr>
                                    <th>Période</th>
                                    <th>Nuitées N</th>
                                    <th>Nuitées N‑1</th>
                                    <th>Δ %</th>
                                    <th>Part %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Week‑end de Pâques</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Vacances de Pâques</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Week‑end de l'Ascension</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Week‑end de la Pentecôte</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="bloc-d5" class="indicator-section">
                    <h2>D5 – CSP Top 3</h2>
                    <div class="table-responsive">
                        <table class="table table--indicators">
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th>CSP</th>
                                    <th>Présences N</th>
                                    <th>Présences N‑1</th>
                                    <th>Δ %</th>
                                    <th>Part % N</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>CSP +</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td>CSP en croissance</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td>Populaire</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="bloc-d6" class="indicator-section">
                    <h2>D6 – Tranches d'âge Top 3</h2>
                    <div class="table-responsive">
                        <table class="table table--indicators">
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th>Tranche d'âge</th>
                                    <th>Présences N</th>
                                    <th>Présences N‑1</th>
                                    <th>Δ %</th>
                                    <th>Part % (N)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
          </div>
  
    <script src="<?= asset('/static/js/tables.js') ?>"></script>
    <script src="<?= asset('/static/js/init.js') ?>"></script>
</body>
</html> 