<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centre d'aide - Cantal Destination Observatoire</title>
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '_navbar.php'; ?>

    <!-- Header du centre d'aide -->
    <section class="help-hero">
        <div class="help-hero-content">
            <div class="help-hero-icon">
                <i class="fas fa-life-ring"></i>
            </div>
            <h1>Centre d'aide - Observatoire Touristique</h1>
            <p>Trouvez des réponses, des guides et obtenez le support dont vous avez besoin</p>
        </div>
    </section>

    <!-- Navigation rapide -->
    <section class="help-nav">
        <div class="help-nav-container">
            <a href="#getting-started" class="help-nav-item">
                <i class="fas fa-rocket"></i>
                <span>Premiers pas</span>
            </a>
            <a href="#faq" class="help-nav-item">
                <i class="fas fa-question-circle"></i>
                <span>FAQ</span>
            </a>
            <a href="#guides" class="help-nav-item">
                <i class="fas fa-book-open"></i>
                <span>Guides</span>
            </a>
            <a href="#api-docs" class="help-nav-item">
                <i class="fas fa-code"></i>
                <span>API</span>
            </a>
            <a href="#contact" class="help-nav-item">
                <i class="fas fa-envelope"></i>
                <span>Contact</span>
            </a>
        </div>
    </section>

    <div class="help-container">
        <!-- Section Premiers pas -->
        <section id="getting-started" class="help-section">
            <div class="help-section-header">
                <i class="fas fa-rocket"></i>
                <h2>Premiers pas avec l'observatoire</h2>
                <p>Découvrez comment utiliser efficacement notre plateforme d'analyse touristique</p>
            </div>

            <div class="help-cards">
                <div class="help-card">
                    <div class="help-card-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>Se connecter</h3>
                    <p>Apprenez à vous connecter et naviguer dans l'interface de l'observatoire.</p>
                    <a href="#login-guide" class="help-card-link">Voir le guide <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="help-card">
                    <div class="help-card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Dashboard</h3>
                    <p>Explorez les tableaux de bord et comprenez les indicateurs affichés.</p>
                    <a href="#dashboard-guide" class="help-card-link">Voir le guide <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="help-card">
                    <div class="help-card-icon">
                        <i class="fas fa-filter"></i>
                    </div>
                    <h3>Filtres & Analyses</h3>
                    <p>Utilisez les filtres pour personnaliser vos analyses de données.</p>
                    <a href="#filters-guide" class="help-card-link">Voir le guide <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="help-card">
                    <div class="help-card-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <h3>Export de données</h3>
                    <p>Exportez vos analyses et rapports dans différents formats.</p>
                    <a href="#export-guide" class="help-card-link">Voir le guide <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </section>

        <!-- Section FAQ -->
        <section id="faq" class="help-section">
            <div class="help-section-header">
                <i class="fas fa-question-circle"></i>
                <h2>Questions fréquemment posées</h2>
                <p>Trouvez rapidement des réponses aux questions les plus courantes</p>
            </div>

            <div class="faq-container">
                <div class="faq-category">
                    <h3 class="faq-category-title">
                        <i class="fas fa-sign-in-alt"></i>
                        Connexion & Compte
                    </h3>
                    <div class="faq-items">
                        <div class="faq-item">
                            <input type="checkbox" id="faq1" class="faq-toggle">
                            <label for="faq1" class="faq-question">
                                <i class="fas fa-chevron-right"></i>
                                Comment puis-je me connecter à FluxVision ?
                            </label>
                            <div class="faq-answer">
                                <p>Utilisez vos identifiants fournis par l'équipe Cantal Destination. Si vous n'avez pas d'accès, contactez l'administrateur système à <strong>admin@cantaldestination.fr</strong>.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <input type="checkbox" id="faq2" class="faq-toggle">
                            <label for="faq2" class="faq-question">
                                <i class="fas fa-chevron-right"></i>
                                J'ai oublié mon mot de passe, que faire ?
                            </label>
                            <div class="faq-answer">
                                <p>Contactez l'équipe support via le formulaire de contact ou à <strong>support@cantaldestination.fr</strong> avec votre nom d'utilisateur pour une réinitialisation.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="faq-category">
                    <h3 class="faq-category-title">
                        <i class="fas fa-chart-bar"></i>
                        Tableaux de bord & Données
                    </h3>
                    <div class="faq-items">
                        <div class="faq-item">
                            <input type="checkbox" id="faq3" class="faq-toggle">
                            <label for="faq3" class="faq-question">
                                <i class="fas fa-chevron-right"></i>
                                Quelle est la différence entre le dashboard général et excursionnistes ?
                            </label>
                            <div class="faq-answer">
                                <p>Le <strong>dashboard général</strong> affiche une vue d'ensemble de tous les visiteurs, tandis que le <strong>dashboard excursionnistes</strong> se concentre spécifiquement sur les visiteurs d'un jour sans nuitée.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <input type="checkbox" id="faq4" class="faq-toggle">
                            <label for="faq4" class="faq-question">
                                <i class="fas fa-chevron-right"></i>
                                À quelle fréquence les données sont-elles mises à jour ?
                            </label>
                            <div class="faq-answer">
                                <p>Les données FluxVision sont actualisées <strong>quotidiennement</strong>. Les analyses peuvent présenter un décalage de 24h à 48h selon les sources de données.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <input type="checkbox" id="faq5" class="faq-toggle">
                            <label for="faq5" class="faq-question">
                                <i class="fas fa-chevron-right"></i>
                                Comment interpréter les indicateurs de fréquentation ?
                            </label>
                            <div class="faq-answer">
                                <p>Consultez la section <a href="<?= url('/methodologie') ?>">Méthodologie</a> pour comprendre le calcul des indicateurs. Chaque métrique est également expliquée dans les <a href="<?= url('/fiches') ?>">fiches techniques</a>.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="faq-category">
                    <h3 class="faq-category-title">
                        <i class="fas fa-tools"></i>
                        Fonctionnalités & Utilisation
                    </h3>
                    <div class="faq-items">
                        <div class="faq-item">
                            <input type="checkbox" id="faq6" class="faq-toggle">
                            <label for="faq6" class="faq-question">
                                <i class="fas fa-chevron-right"></i>
                                Comment utiliser les filtres temporels ?
                            </label>
                            <div class="faq-answer">
                                <p>Utilisez le sélecteur de période en haut des dashboards pour filtrer par saisons, mois ou périodes personnalisées. Vous pouvez comparer différentes périodes en activant le mode comparaison.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <input type="checkbox" id="faq7" class="faq-toggle">
                            <label for="faq7" class="faq-question">
                                <i class="fas fa-chevron-right"></i>
                                Puis-je exporter les données et graphiques ?
                            </label>
                            <div class="faq-answer">
                                <p>Oui, utilisez les boutons d'export disponibles sur chaque graphique. Formats disponibles : PNG, SVG, PDF pour les graphiques et CSV, Excel pour les données.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="faq-category">
                    <h3 class="faq-category-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        Problèmes techniques
                    </h3>
                    <div class="faq-items">
                        <div class="faq-item">
                            <input type="checkbox" id="faq8" class="faq-toggle">
                            <label for="faq8" class="faq-question">
                                <i class="fas fa-chevron-right"></i>
                                Les graphiques ne s'affichent pas correctement
                            </label>
                            <div class="faq-answer">
                                <p>Vérifiez que JavaScript est activé dans votre navigateur. Actualisez la page (F5) ou videz le cache. Navigateurs recommandés : Chrome, Firefox, Safari, Edge (versions récentes).</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <input type="checkbox" id="faq9" class="faq-toggle">
                            <label for="faq9" class="faq-question">
                                <i class="fas fa-chevron-right"></i>
                                La plateforme est lente ou ne répond pas
                            </label>
                            <div class="faq-answer">
                                <p>Vérifiez votre connexion internet. Si le problème persiste, il peut s'agir d'une maintenance. Consultez nos <a href="#status">statuts système</a> ou contactez le support.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section Guides détaillés -->
        <section id="guides" class="help-section">
            <div class="help-section-header">
                <i class="fas fa-book-open"></i>
                <h2>Guides d'utilisation</h2>
                <p>Tutorials étape par étape pour maîtriser toutes les fonctionnalités</p>
            </div>

            <div class="guides-grid">
                <div class="guide-card">
                    <div class="guide-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <h3>Guide de démarrage rapide</h3>
                    <p>Découvrez FluxVision en 5 minutes : connexion, navigation et premières analyses.</p>
                    <div class="guide-meta">
                        <span class="guide-duration"><i class="fas fa-clock"></i> 5 min</span>
                        <span class="guide-level beginner">Débutant</span>
                    </div>
                    <a href="#quick-start" class="guide-link">Commencer</a>
                </div>

                <div class="guide-card">
                    <div class="guide-icon">
                        <i class="fas fa-chart-area"></i>
                    </div>
                    <h3>Maîtriser les dashboards</h3>
                    <p>Apprenez à lire, personnaliser et exploiter tous les tableaux de bord disponibles.</p>
                    <div class="guide-meta">
                        <span class="guide-duration"><i class="fas fa-clock"></i> 15 min</span>
                        <span class="guide-level intermediate">Intermédiaire</span>
                    </div>
                    <a href="#dashboard-mastery" class="guide-link">Commencer</a>
                </div>

                <div class="guide-card">
                    <div class="guide-icon">
                        <i class="fas fa-filter"></i>
                    </div>
                    <h3>Analyses avancées</h3>
                    <p>Filtres complexes, comparaisons temporelles et analyses de tendances approfondies.</p>
                    <div class="guide-meta">
                        <span class="guide-duration"><i class="fas fa-clock"></i> 25 min</span>
                        <span class="guide-level advanced">Avancé</span>
                    </div>
                    <a href="#advanced-analysis" class="guide-link">Commencer</a>
                </div>

                <div class="guide-card">
                    <div class="guide-icon">
                        <i class="fas fa-file-export"></i>
                    </div>
                    <h3>Export et reporting</h3>
                    <p>Créez des rapports professionnels et exportez vos données dans tous les formats.</p>
                    <div class="guide-meta">
                        <span class="guide-duration"><i class="fas fa-clock"></i> 10 min</span>
                        <span class="guide-level intermediate">Intermédiaire</span>
                    </div>
                    <a href="#reporting-guide" class="guide-link">Commencer</a>
                </div>
            </div>
        </section>

        <!-- Section Documentation API -->
        <section id="api-docs" class="help-section">
            <div class="help-section-header">
                <i class="fas fa-code"></i>
                <h2>Documentation API</h2>
                <p>Intégrez FluxVision dans vos applications avec notre API REST</p>
            </div>

            <div class="api-overview">
                <div class="api-card">
                    <h3><i class="fas fa-key"></i> Authentification</h3>
                    <p>Toutes les requêtes API nécessitent une authentification par token JWT.</p>
                    <code>Authorization: Bearer YOUR_JWT_TOKEN</code>
                </div>

                <div class="api-card">
                    <h3><i class="fas fa-link"></i> Endpoints principaux</h3>
                    <div class="api-endpoints">
                        <div class="api-endpoint">
                            <span class="api-method get">GET</span>
                            <span class="api-path">/api/filters</span>
                            <span class="api-desc">Filtres disponibles</span>
                        </div>
                        <div class="api-endpoint">
                            <span class="api-method get">GET</span>
                            <span class="api-path">/api/bloc_a</span>
                            <span class="api-desc">Données générales</span>
                        </div>
                        <div class="api-endpoint">
                            <span class="api-method get">GET</span>
                            <span class="api-path">/api/bloc_d1</span>
                            <span class="api-desc">Indicateurs détaillés</span>
                        </div>
                    </div>
                </div>

                <div class="api-card">
                    <h3><i class="fas fa-book"></i> Documentation complète</h3>
                    <p>Consultez notre documentation API complète avec exemples et schémas.</p>
                    <a href="#api-full-docs" class="btn btn--secondary">
                        <i class="fas fa-external-link-alt"></i>
                        Voir la documentation
                    </a>
                </div>
            </div>
        </section>

        <!-- Section Contact et Support -->
        <section id="contact" class="help-section">
            <div class="help-section-header">
                <i class="fas fa-envelope"></i>
                <h2>Contactez notre équipe</h2>
                <p>Notre équipe d'experts est là pour vous accompagner</p>
            </div>

            <div class="contact-grid">
                <div class="contact-option">
                    <div class="contact-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>Support technique</h3>
                    <p>Problèmes techniques, bugs, et assistance utilisateur</p>
                    <div class="contact-details">
                        <a href="mailto:support@cantaldestination.fr" class="contact-link">
                            <i class="fas fa-envelope"></i>
                            support@cantaldestination.fr
                        </a>
                        <span class="contact-response">Réponse sous 24h</span>
                    </div>
                </div>

                <div class="contact-option">
                    <div class="contact-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Accompagnement métier</h3>
                    <p>Questions sur l'interprétation des données et la méthodologie</p>
                    <div class="contact-details">
                        <a href="mailto:observatoire@cantaldestination.fr" class="contact-link">
                            <i class="fas fa-envelope"></i>
                            observatoire@cantaldestination.fr
                        </a>
                        <span class="contact-response">Réponse sous 48h</span>
                    </div>
                </div>

                <div class="contact-option">
                    <div class="contact-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Formation & Accompagnement</h3>
                    <p>Sessions de formation et accompagnement personnalisé</p>
                    <div class="contact-details">
                        <a href="mailto:formation@cantaldestination.fr" class="contact-link">
                            <i class="fas fa-envelope"></i>
                            formation@cantaldestination.fr
                        </a>
                        <span class="contact-response">Sur rendez-vous</span>
                    </div>
                </div>
            </div>

            <!-- Formulaire de contact -->
            <div class="contact-form-section">
                <h3>Formulaire de contact</h3>
                <form id="support-form" class="support-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Nom complet *</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="organization">Organisation</label>
                            <input type="text" id="organization" name="organization">
                        </div>
                        <div class="form-group">
                            <label for="category">Catégorie *</label>
                            <select id="category" name="category" required>
                                <option value="">Sélectionnez une catégorie</option>
                                <option value="technical">Support technique</option>
                                <option value="data">Question sur les données</option>
                                <option value="training">Demande de formation</option>
                                <option value="feature">Suggestion d'amélioration</option>
                                <option value="other">Autre</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Sujet *</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message détaillé *</label>
                        <textarea id="message" name="message" rows="6" required placeholder="Décrivez votre demande en détail..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority">Priorité</label>
                        <select id="priority" name="priority">
                            <option value="low">Basse</option>
                            <option value="medium" selected>Moyenne</option>
                            <option value="high">Haute</option>
                            <option value="urgent">Urgente</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn--primary">
                        <i class="fas fa-paper-plane"></i>
                        Envoyer la demande
                    </button>
                </form>
            </div>
        </section>

        <!-- Section Statut système -->
        <section id="status" class="help-section status-section">
            <div class="help-section-header">
                <i class="fas fa-heartbeat"></i>
                <h2>Statut du système</h2>
                <p>Vérifiez l'état de nos services en temps réel</p>
            </div>

            <div class="status-grid">
                <div class="status-item operational">
                    <div class="status-indicator"></div>
                    <div class="status-info">
                        <h4>Plateforme FluxVision</h4>
                        <span class="status-label">Opérationnel</span>
                    </div>
                </div>
                
                <div class="status-item operational">
                    <div class="status-indicator"></div>
                    <div class="status-info">
                        <h4>API & Services de données</h4>
                        <span class="status-label">Opérationnel</span>
                    </div>
                </div>
                
                <div class="status-item operational">
                    <div class="status-indicator"></div>
                    <div class="status-info">
                        <h4>Authentification</h4>
                        <span class="status-label">Opérationnel</span>
                    </div>
                </div>
            </div>
        </section>
    </div>
</body>
</html> 