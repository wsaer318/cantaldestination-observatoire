<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Espaces Partagés - FluxVision</title>
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="<?= asset('/static/js/utils.js') ?>"></script>
    <script src="<?= asset('/static/js/config.js') ?>"></script>
    <script src="<?= asset('/static/js/admin-shared-spaces.js') ?>"></script>
</head>
<body>
    <?php include '_navbar.php'; ?>

    <div class="admin-container">
        <div class="admin-header">
            <h1><i class="fas fa-users"></i> Gestion des Espaces Partagés</h1>
            <p>Créez et gérez les espaces de travail collaboratifs pour les infographies</p>
        </div>

        <?php if (isset($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire de création d'espace -->
        <div class="admin-section">
            <h2><i class="fas fa-plus-circle"></i> Créer un nouvel espace</h2>
            <form action="<?= url('/admin/shared-spaces') ?>" method="POST" class="space-form">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="space_name">Nom de l'espace *</label>
                        <input type="text" id="space_name" name="space_name" required 
                               placeholder="Ex: Équipe Marketing Cantal">
                    </div>
                    <div class="form-group">
                        <label for="space_description">Description</label>
                        <textarea id="space_description" name="space_description" rows="3"
                                  placeholder="Description de l'espace de travail..."></textarea>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Membres initiaux</label>
                    <div class="members-selection">
                        <?php foreach ($availableUsers as $user): ?>
                            <div class="member-checkbox">
                                <input type="checkbox" id="user_<?= $user['id'] ?>" 
                                       name="members[]" value="<?= $user['id'] ?>">
                                <label for="user_<?= $user['id'] ?>">
                                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                                </label>
                                <select name="member_role_<?= $user['id'] ?>" class="role-select">
                                    <option value="reader">Lecteur</option>
                                    <option value="editor">Éditeur</option>
                                    <option value="validator">Validateur</option>
                                    <option value="admin">Administrateur</option>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <button type="submit" class="btn btn--primary">
                    <i class="fas fa-plus"></i>
                    Créer l'espace
                </button>
            </form>
        </div>

        <!-- Liste des espaces existants -->
        <div class="admin-section">
            <h2><i class="fas fa-list"></i> Espaces existants</h2>
            
            <?php if (empty($userSpaces)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>Aucun espace partagé</h3>
                    <p>Créez votre premier espace de travail collaboratif pour commencer.</p>
                </div>
            <?php else: ?>
                <div class="spaces-grid">
                    <?php foreach ($userSpaces as $space): ?>
                        <div class="space-card <?= $space['is_active'] ? '' : 'space-inactive' ?>">
                            <div class="space-header">
                                <h3><?= htmlspecialchars($space['name']) ?></h3>
                                <div class="space-status">
                                    <span class="role-badge <?= $space['user_role'] ?>">
                                        <?= ucfirst($space['user_role']) ?>
                                    </span>
                                    <?php if (!$space['is_active']): ?>
                                        <span class="status-badge inactive">
                                            <i class="fas fa-times-circle"></i> Désactivé
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="space-description">
                                <?= htmlspecialchars($space['description'] ?: 'Aucune description') ?>
                            </div>
                            
                                                         <div class="space-stats">
                                 <div class="stat">
                                     <i class="fas fa-users"></i>
                                     <span><?= $spaceStats[$space['id']]['member_count'] ?? 0 ?> membres</span>
                                 </div>
                                 <div class="stat">
                                     <i class="fas fa-chart-bar"></i>
                                     <span><?= $spaceStats[$space['id']]['infographic_count'] ?? 0 ?> infographies</span>
                                 </div>
                             </div>
                            
                            <div class="space-actions">
                                <?php if ($space['is_active']): ?>
                                    <a href="<?= url('/shared-spaces/' . $space['id']) ?>" 
                                       class="btn btn--small btn--primary">
                                        <i class="fas fa-eye"></i>
                                        Voir
                                    </a>
                                    
                                    <?php if ($space['user_role'] === 'admin'): ?>
                                        <a href="<?= url('/admin/shared-spaces/' . $space['id'] . '/manage') ?>" 
                                           class="btn btn--small btn--secondary">
                                            <i class="fas fa-cog"></i>
                                            Gérer
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- Actions pour les espaces désactivés -->
                                    <form method="POST" action="<?= url('/admin/shared-spaces/' . $space['id'] . '/manage') ?>" 
                                          style="display: inline;" onsubmit="return confirm('Restaurer cet espace ?')">
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                                        <button type="submit" class="btn btn--small btn--success">
                                            <i class="fas fa-undo"></i>
                                            Restaurer
                                        </button>
                                    </form>
                                    
                                    <form method="POST" action="<?= url('/admin/shared-spaces/' . $space['id'] . '/manage') ?>" 
                                          style="display: inline;" onsubmit="return confirm('Supprimer définitivement cet espace ? Cette action est irréversible.')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                                        <button type="submit" class="btn btn--small btn--danger">
                                            <i class="fas fa-trash"></i>
                                            Supprimer définitivement
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($space['is_active'] && $space['user_role'] === 'admin'): ?>
                                    <!-- Actions pour les espaces actifs (admin seulement) -->
                                    <form method="POST" action="<?= url('/admin/shared-spaces/' . $space['id'] . '/manage') ?>" 
                                          style="display: inline;" onsubmit="return confirm('Désactiver cet espace ?')">
                                        <input type="hidden" name="action" value="disable">
                                        <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                                        <button type="submit" class="btn btn--small btn--warning">
                                            <i class="fas fa-times"></i>
                                            Désactiver
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            
                            <div class="space-meta">
                                <small>
                                    <i class="fas fa-clock"></i>
                                    Créé le <?= date('d/m/Y', strtotime($space['created_at'])) ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Statistiques globales -->
        <div class="admin-section">
            <h2><i class="fas fa-chart-pie"></i> Statistiques</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['total_spaces'] ?? 0 ?></h3>
                        <p>Espaces créés</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['total_memberships'] ?? 0 ?></h3>
                        <p>Membres actifs</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['total_infographics'] ?? 0 ?></h3>
                        <p>Infographies partagées</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['total_comments'] ?? 0 ?></h3>
                        <p>Commentaires</p>
                    </div>
                </div>
            </div>
        </div>
    </div>


</body>
</html>
