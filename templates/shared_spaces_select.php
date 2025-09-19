<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sélection d'espace - Partage d'infographie - FluxVision</title>
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('/static/css/infographie.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="<?= asset('/static/js/config.js') ?>"></script>
    <script src="<?= asset('/static/js/shared-spaces-select.js') ?>"></script>
</head>
<body>
    <?php include '_navbar.php'; ?>

    <div class="share-infographic-container">
        <!-- Colonne gauche : Sélection d'espace -->
        <div class="share-selection-panel">
            <div class="panel-header">
                <h1><i class="fas fa-share"></i> Partager l'infographie</h1>
                <p>Sélectionnez un espace pour partager votre infographie</p>
            </div>

            <!-- Informations de l'infographie -->
            <div class="infographic-info">
                <h3><i class="fas fa-info-circle"></i> Infographie à partager</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Zone :</span>
                        <span class="info-value" title="<?= htmlspecialchars($infographicParams['zone']) ?>"><?= htmlspecialchars($infographicParams['zone']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Année :</span>
                        <span class="info-value" title="<?= htmlspecialchars($infographicParams['year']) ?>"><?= htmlspecialchars($infographicParams['year']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Période :</span>
                        <span class="info-value" title="<?= htmlspecialchars($infographicParams['period']) ?>"><?= htmlspecialchars($infographicParams['period']) ?></span>
                    </div>
                    <?php if ($infographicParams['debut'] && $infographicParams['fin']): ?>
                    <div class="info-item">
                        <span class="info-label">Dates :</span>
                        <span class="info-value" title="<?= htmlspecialchars($infographicParams['debut']) ?> - <?= htmlspecialchars($infographicParams['fin']) ?>"><?= htmlspecialchars($infographicParams['debut']) ?> - <?= htmlspecialchars($infographicParams['fin']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($infographicParams['unique_id']): ?>
                    <div class="info-item">
                        <span class="info-label">ID :</span>
                        <span class="info-value code" title="<?= htmlspecialchars($infographicParams['unique_id']) ?>"><?= htmlspecialchars($infographicParams['unique_id']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sélection d'espace -->
            <div class="space-selection">
                <h3><i class="fas fa-users"></i> Choisir un espace</h3>
                
                <?php if (empty($userSpaces)): ?>
                    <div class="no-spaces">
                        <i class="fas fa-folder-open"></i>
                        <p>Vous n'avez pas encore d'espaces partagés.</p>
                        <a href="<?= url('/shared-spaces/create') ?>" class="btn btn--primary">
                            <i class="fas fa-plus"></i> Créer un espace
                        </a>
                    </div>
                <?php else: ?>
                    <div class="spaces-list">
                        <?php foreach ($userSpaces as $space): ?>
                            <?php 
                            // Vérifier si l'utilisateur peut partager dans cet espace
                            $canShare = in_array($space['user_role'], ['editor', 'admin']);
                            ?>
                            <div class="space-option <?= $canShare ? '' : 'space-disabled' ?>" data-space-id="<?= $space['id'] ?>">
                                <label class="space-radio <?= $canShare ? '' : 'disabled' ?>">
                                    <input type="radio" name="selected_space" value="<?= $space['id'] ?>" <?= $canShare ? '' : 'disabled' ?>>
                                    <span class="radio-custom"></span>
                                    <div class="space-info">
                                        <h4><?= htmlspecialchars($space['name']) ?></h4>
                                        <p><?= htmlspecialchars($space['description'] ?? '') ?></p>
                                        <div class="space-meta">
                                            <span class="role-badge role-<?= $space['user_role'] ?>">
                                                <?= ucfirst($space['user_role']) ?>
                                            </span>
                                            <?php if (!$canShare): ?>
                                                <span class="permission-warning">
                                                    <i class="fas fa-exclamation-triangle"></i> Lecture seule
                                                </span>
                                            <?php endif; ?>
                                            <span class="member-count">
                                                <i class="fas fa-users"></i> <?= $space['stats']['member_count'] ?? 0 ?> membres
                                            </span>
                                            <span class="infographic-count">
                                                <i class="fas fa-chart-bar"></i> <?= $space['stats']['infographic_count'] ?? 0 ?> infographies
                                            </span>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Informations complémentaires -->
            <div class="share-details">
                <h3><i class="fas fa-edit"></i> Informations complémentaires</h3>
                <form id="share-form" class="share-form">
                    <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                    <input type="hidden" name="infographic_params" value="<?= htmlspecialchars(json_encode($infographicParams)) ?>">
                    
                    <div class="form-group">
                        <label for="infographic-title">Titre de l'infographie *</label>
                        <input type="text" id="infographic-title" name="title" required 
                               placeholder="Ex: Infographie Cantal 2024"
                               value="<?= htmlspecialchars($infographicParams['zone'] . ' ' . $infographicParams['year']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="infographic-description">Description</label>
                        <textarea id="infographic-description" name="description" rows="3"
                                  placeholder="Description de l'infographie..."></textarea>
                    </div>

                    <!-- Actions -->
                    <div class="share-actions">
                        <a href="<?= url('/infographie') ?>" class="btn btn--secondary">
                            <i class="fas fa-arrow-left"></i> Retour à l'infographie
                        </a>
                        <button type="submit" class="btn btn--primary" id="share-button" disabled>
                            <i class="fas fa-share"></i> Partager l'infographie
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Colonne droite : Prévisualisation -->
        <div class="share-preview-panel">
            <div class="preview-header">
                <h2><i class="fas fa-eye"></i> Prévisualisation</h2>
                <p>Aperçu de l'infographie à partager</p>
            </div>
            
            <div class="preview-container">
                <?php if ($infographicParams['preview_id']): ?>
                    <!-- Prévisualisation capturée -->
                    <img src="<?= url('/api/infographie/preview.php?id=' . htmlspecialchars($infographicParams['preview_id'])) ?>" 
                         alt="Prévisualisation de l'infographie" 
                         class="infographic-preview-image">
                <?php else: ?>
                    <!-- Fallback : aperçu live -->
                    <div class="live-preview-wrapper">
                        <iframe class="infographic-live-preview"
                                src="<?= url('/infographie') ?>?annee=<?= urlencode($infographicParams['year'] ?? date('Y')) ?>&periode=<?= urlencode($infographicParams['period'] ?? 'annee_complete') ?>&zone=<?= urlencode($infographicParams['zone'] ?? 'CANTAL') ?><?php if (!empty($infographicParams['debut']) && !empty($infographicParams['fin'])): ?>&debut=<?= urlencode($infographicParams['debut']) ?>&fin=<?= urlencode($infographicParams['fin']) ?><?php endif; ?>&embed=1">
                        </iframe>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="preview-info">
                <div class="preview-meta">
                    <span class="preview-size">Taille : ~2.5 MB</span>
                    <span class="preview-format">Format : PNG haute qualité</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configuration globale
        const ShareConfig = {
            csrfToken: '<?= Security::getCSRFToken() ?>',
            baseUrl: '<?= url('/api/shared-spaces') ?>',
            infographicParams: <?= json_encode($infographicParams) ?>
        };
    </script>
</body>
</html>
