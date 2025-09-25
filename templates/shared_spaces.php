<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espaces Partagés - FluxVision</title>
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="<?= asset('/static/js/config.js') ?>"></script>
    <script src="<?= asset('/static/js/shared-spaces.js') ?>"></script>
</head>
<body>
    <?php include '_navbar.php'; ?>

    <div class="admin-container">
        <div class="admin-header">
            <div class="header-content">
                <h1><i class="fas fa-users"></i> Mes Espaces Partagés</h1>
                <p>Accédez à vos espaces de travail collaboratifs et aux infographies partagées</p>
            </div>
                    <div class="header-actions">
            <a href="<?= url('/shared-spaces/create') ?>" class="btn btn--primary">
                <i class="fas fa-plus"></i>
                Créer un espace
            </a>
        </div>
        </div>

        <!-- Messages de succès/erreur -->
        <div id="messages-container"></div>

        <!-- Filtres et recherche -->
        <div class="admin-section">
            <div class="filter-container">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="role-filter">Mon rôle :</label>
                        <select id="role-filter" class="filter-select" onchange="filterSpacesByRole()">
                            <option value="">Tous les rôles</option>
                            <option value="admin">Administrateur</option>
                            <option value="validator">Validateur</option>
                            <option value="editor">Éditeur</option>
                            <option value="reader">Lecteur</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="search-spaces">Rechercher :</label>
                        <input type="text" id="search-spaces" class="filter-select" 
                               placeholder="Nom de l'espace..." onkeyup="searchSpaces()">
                    </div>
                    <div class="filter-actions">
                        <button type="button" class="btn btn--secondary" onclick="refreshSpaces()">
                            <i class="fas fa-sync-alt"></i>
                            Actualiser
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des espaces -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-list"></i> Mes espaces</h2>
                <div class="spaces-count">
                    <span class="badge" id="spaces-count">0 espace</span>
                </div>
            </div>
            
            <div id="spaces-container">
                <div class="loading-indicator">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Chargement des espaces...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de création d'espace -->
    <div id="create-space-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Créer un nouvel espace</h3>
                <button type="button" class="modal-close" onclick="hideCreateSpaceModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="create-space-form" class="space-form">
                    <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                    
                    <div class="form-group">
                        <label for="space-name">Nom de l'espace *</label>
                        <input type="text" id="space-name" name="name" required 
                               placeholder="Ex: Équipe Marketing Cantal">
                    </div>
                    
                    <div class="form-group">
                        <label for="space-description">Description</label>
                        <textarea id="space-description" name="description" rows="3"
                                  placeholder="Description de l'espace de travail..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Ajouter des membres (optionnel) :</label>
                        <div class="members-selection">
                            <div id="available-users-grid" class="users-grid">
                                <!-- Les utilisateurs disponibles seront chargés ici -->
                            </div>
                            <div class="selection-controls">
                                <button type="button" class="btn btn--small btn--secondary" onclick="selectAllUsers()">
                                    <i class="fas fa-check-double"></i> Tout sélectionner
                                </button>
                                <button type="button" class="btn btn--small btn--secondary" onclick="deselectAllUsers()">
                                    <i class="fas fa-times"></i> Tout désélectionner
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn--secondary" onclick="hideCreateSpaceModal()">
                            Annuler
                        </button>
                        <button type="submit" class="btn btn--primary">
                            <i class="fas fa-plus"></i>
                            Créer l'espace
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de détails d'espace -->
    <div id="space-details-modal" class="modal" style="display: none;">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 id="space-details-title"><i class="fas fa-info-circle"></i> Détails de l'espace</h3>
                <button type="button" class="modal-close" onclick="hideSpaceDetailsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="space-details-content">
                    <!-- Le contenu sera chargé dynamiquement -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configuration globale
        const SharedSpacesConfig = {
            csrfToken: '<?= Security::getCSRFToken() ?>',
            baseUrl: '<?= url('/api/shared-spaces') ?>'
        };
    </script>
</body>
</html>
