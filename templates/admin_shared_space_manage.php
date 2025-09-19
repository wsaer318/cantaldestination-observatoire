<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer l'Espace - <?= htmlspecialchars($space['name']) ?> - FluxVision</title>
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
            <div class="header-content">
                <h1><i class="fas fa-cog"></i> Gérer l'Espace</h1>
                <p><?= htmlspecialchars($space['name']) ?></p>
            </div>
            <div class="header-actions">
                <a href="<?= url('/admin/shared-spaces') ?>" class="btn btn--secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux espaces
                </a>
            </div>
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

        <!-- Informations de l'espace -->
        <div class="admin-section">
            <h2><i class="fas fa-info-circle"></i> Informations de l'espace</h2>
            <div class="space-info-grid">
                <div class="info-card">
                    <h3>Nom</h3>
                    <p><?= htmlspecialchars($space['name']) ?></p>
                </div>
                <div class="info-card">
                    <h3>Description</h3>
                    <p><?= htmlspecialchars($space['description'] ?: 'Aucune description') ?></p>
                </div>
                <div class="info-card">
                    <h3>Créé le</h3>
                    <p><?= date('d/m/Y à H:i', strtotime($space['created_at'])) ?></p>
                </div>
                <div class="info-card">
                    <h3>Dernière modification</h3>
                    <p><?= date('d/m/Y à H:i', strtotime($space['updated_at'])) ?></p>
                </div>
            </div>
        </div>

        <!-- Modification de l'espace -->
        <div class="admin-section">
            <h2><i class="fas fa-edit"></i> Modifier l'espace</h2>
            <form action="<?= url('/admin/shared-spaces/' . $space['id'] . '/manage') ?>" method="POST" class="space-form">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="space_name">Nom de l'espace *</label>
                        <input type="text" id="space_name" name="space_name" required 
                               value="<?= htmlspecialchars($space['name']) ?>"
                               placeholder="Ex: Équipe Marketing Cantal">
                    </div>
                    <div class="form-group">
                        <label for="space_description">Description</label>
                        <textarea id="space_description" name="space_description" rows="3"
                                  placeholder="Description de l'espace de travail..."><?= htmlspecialchars($space['description']) ?></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn btn--primary">
                    <i class="fas fa-save"></i>
                    Sauvegarder les modifications
                </button>
            </form>
        </div>

                 <!-- Gestion des membres -->
         <div class="admin-section">
             <div class="section-header">
                 <h2><i class="fas fa-users"></i> Gestion des membres</h2>
                 <div class="member-count">
                     <span class="badge"><?= count($members) ?> membre<?= count($members) > 1 ? 's' : '' ?></span>
                 </div>
             </div>

             <!-- Mode de gestion multiple -->
             <div class="management-mode-toggle">
                 <button type="button" class="btn btn--secondary" onclick="toggleManagementMode('single')" id="single-mode-btn">
                     <i class="fas fa-user"></i> Mode individuel
                 </button>
                 <button type="button" class="btn btn--primary" onclick="toggleManagementMode('multiple')" id="multiple-mode-btn">
                     <i class="fas fa-users"></i> Mode multiple
                 </button>
             </div>

             <!-- Mode individuel -->
             <div id="single-management" class="management-panel">
                 <?php if (empty($members)): ?>
                     <div class="empty-state">
                         <i class="fas fa-user-plus"></i>
                         <h3>Aucun membre</h3>
                         <p>Ajoutez des membres pour commencer la collaboration.</p>
                     </div>
                 <?php else: ?>
                     <div class="members-grid">
                         <?php foreach ($members as $member): ?>
                             <div class="member-card" data-member-id="<?= $member['user_id'] ?>">
                                 <div class="member-header">
                                     <div class="member-avatar">
                                         <i class="fas fa-user"></i>
                                     </div>
                                     <div class="member-info">
                                         <h4><?= htmlspecialchars($member['username']) ?></h4>
                                         <span class="role-badge <?= $member['role'] ?>">
                                             <?= ucfirst($member['role']) ?>
                                         </span>
                                     </div>
                                     <?php if ($member['user_id'] === $currentUser['id']): ?>
                                         <div class="current-user-indicator">
                                             <i class="fas fa-star"></i>
                                         </div>
                                     <?php endif; ?>
                                 </div>
                                 
                                 <div class="member-details">
                                     <div class="detail-item">
                                         <i class="fas fa-calendar-alt"></i>
                                         <span>Rejoint le <?= date('d/m/Y', strtotime($member['joined_at'])) ?></span>
                                     </div>
                                 </div>
                                 
                                 <?php if ($member['user_id'] !== $currentUser['id']): ?>
                                     <div class="member-actions">
                                         <div class="role-selector">
                                             <label>Rôle :</label>
                                             <select class="role-select" onchange="updateMemberRole(<?= $space['id'] ?>, <?= $member['user_id'] ?>, this.value)">
                                                 <option value="reader" <?= $member['role'] === 'reader' ? 'selected' : '' ?>>Lecteur</option>
                                                 <option value="editor" <?= $member['role'] === 'editor' ? 'selected' : '' ?>>Éditeur</option>
                                                 <option value="validator" <?= $member['role'] === 'validator' ? 'selected' : '' ?>>Validateur</option>
                                                 <option value="admin" <?= $member['role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                                             </select>
                                         </div>
                                         
                                         <button type="button" class="btn btn--small btn--danger remove-member-btn" 
                                                 onclick="removeMember(<?= $space['id'] ?>, <?= $member['user_id'] ?>)"
                                                 title="Retirer ce membre">
                                             <i class="fas fa-user-minus"></i>
                                             Retirer
                                         </button>
                                     </div>
                                 <?php else: ?>
                                     <div class="current-user-note">
                                         <i class="fas fa-info-circle"></i>
                                         <span>Vous êtes le propriétaire de cet espace</span>
                                     </div>
                                 <?php endif; ?>
                             </div>
                         <?php endforeach; ?>
                     </div>
                 <?php endif; ?>

                 <!-- Ajouter un nouveau membre -->
                 <div class="add-member-section">
                     <div class="section-header">
                         <h3><i class="fas fa-user-plus"></i> Ajouter un membre</h3>
                         <div class="available-users-count">
                             <span class="badge">
                                 <?php 
                                 $availableCount = 0;
                                 foreach ($availableUsers as $user) {
                                     $isAlreadyMember = false;
                                     foreach ($members as $member) {
                                         if ($member['user_id'] == $user['id']) {
                                             $isAlreadyMember = true;
                                             break;
                                         }
                                     }
                                     if (!$isAlreadyMember) $availableCount++;
                                 }
                                 ?>
                                 <?= $availableCount ?> utilisateur<?= $availableCount > 1 ? 's' : '' ?> disponible<?= $availableCount > 1 ? 's' : '' ?>
                             </span>
                         </div>
                     </div>
                     
                     <?php if ($availableCount > 0): ?>
                         <form action="<?= url('/admin/shared-spaces/' . $space['id'] . '/manage') ?>" method="POST" class="add-member-form">
                             <input type="hidden" name="action" value="add_member">
                             <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                             
                             <div class="form-row">
                                 <div class="form-group">
                                     <label for="new_member">Utilisateur</label>
                                     <select id="new_member" name="user_id" required>
                                         <option value="">Sélectionner un utilisateur...</option>
                                         <?php foreach ($availableUsers as $user): ?>
                                             <?php 
                                             $isAlreadyMember = false;
                                             foreach ($members as $member) {
                                                 if ($member['user_id'] == $user['id']) {
                                                     $isAlreadyMember = true;
                                                     break;
                                                 }
                                             }
                                             ?>
                                             <?php if (!$isAlreadyMember): ?>
                                                 <option value="<?= $user['id'] ?>">
                                                     <?= htmlspecialchars($user['username']) ?>
                                                 </option>
                                             <?php endif; ?>
                                         <?php endforeach; ?>
                                     </select>
                                 </div>
                                 <div class="form-group">
                                     <label for="new_role">Rôle</label>
                                     <select id="new_role" name="role" required>
                                         <option value="reader">Lecteur</option>
                                         <option value="editor">Éditeur</option>
                                         <option value="validator">Validateur</option>
                                         <option value="admin">Administrateur</option>
                                     </select>
                                 </div>
                                 <div class="form-group">
                                     <label>&nbsp;</label>
                                     <button type="submit" class="btn btn--primary">
                                         <i class="fas fa-plus"></i>
                                         Ajouter
                                     </button>
                                 </div>
                             </div>
                         </form>
                     <?php else: ?>
                         <div class="no-available-users">
                             <i class="fas fa-info-circle"></i>
                             <p>Tous les utilisateurs sont déjà membres de cet espace.</p>
                         </div>
                     <?php endif; ?>
                 </div>
             </div>

             <!-- Mode multiple -->
             <div id="multiple-management" class="management-panel" style="display: none;">
                 <div class="multiple-actions">
                     <div class="action-group">
                         <h3><i class="fas fa-user-plus"></i> Ajouter plusieurs membres</h3>
                         <form action="<?= url('/admin/shared-spaces/' . $space['id'] . '/manage') ?>" method="POST" class="multiple-add-form">
                             <input type="hidden" name="action" value="add_multiple_members">
                             <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                             
                             <div class="form-group">
                                 <label>Rôle par défaut pour les nouveaux membres :</label>
                                 <select name="default_role" required>
                                     <option value="reader">Lecteur</option>
                                     <option value="editor">Éditeur</option>
                                     <option value="validator">Validateur</option>
                                     <option value="admin">Administrateur</option>
                                 </select>
                             </div>
                             
                             <div class="users-selection">
                                 <label>Sélectionner les utilisateurs :</label>
                                 <div class="users-grid">
                                     <?php foreach ($availableUsers as $user): ?>
                                         <?php 
                                         $isAlreadyMember = false;
                                         foreach ($members as $member) {
                                             if ($member['user_id'] == $user['id']) {
                                                 $isAlreadyMember = true;
                                                 break;
                                             }
                                         }
                                         ?>
                                         <?php if (!$isAlreadyMember): ?>
                                             <div class="user-select-item">
                                                 <label class="checkbox-label">
                                                     <input type="checkbox" name="users[]" value="<?= $user['id'] ?>">
                                                     <span class="checkmark"></span>
                                                     <span class="username"><?= htmlspecialchars($user['username']) ?></span>
                                                 </label>
                                             </div>
                                         <?php endif; ?>
                                     <?php endforeach; ?>
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
                             
                             <button type="submit" class="btn btn--primary">
                                 <i class="fas fa-plus"></i>
                                 Ajouter les membres sélectionnés
                             </button>
                         </form>
                     </div>

                     <div class="action-group">
                         <h3><i class="fas fa-edit"></i> Modifier les rôles en masse</h3>
                         <form action="<?= url('/admin/shared-spaces/' . $space['id'] . '/manage') ?>" method="POST" class="bulk-role-form">
                             <input type="hidden" name="action" value="update_multiple_roles">
                             <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                             
                             <div class="form-row">
                                 <div class="form-group">
                                     <label>Nouveau rôle :</label>
                                     <select name="new_role" required>
                                         <option value="">Sélectionner un rôle...</option>
                                         <option value="reader">Lecteur</option>
                                         <option value="editor">Éditeur</option>
                                         <option value="validator">Validateur</option>
                                         <option value="admin">Administrateur</option>
                                     </select>
                                 </div>
                                 <div class="form-group">
                                     <label>Appliquer à :</label>
                                     <select name="role_filter" onchange="filterMembersByRole(this.value)">
                                         <option value="">Tous les membres</option>
                                         <option value="reader">Lecteurs uniquement</option>
                                         <option value="editor">Éditeurs uniquement</option>
                                         <option value="validator">Validateurs uniquement</option>
                                         <option value="admin">Administrateurs uniquement</option>
                                     </select>
                                 </div>
                             </div>
                             
                             <div class="members-selection">
                                 <label>Sélectionner les membres à modifier :</label>
                                 <div class="members-grid-compact">
                                     <?php foreach ($members as $member): ?>
                                         <?php if ($member['user_id'] !== $currentUser['id']): ?>
                                             <div class="member-select-item" data-role="<?= $member['role'] ?>">
                                                 <label class="checkbox-label">
                                                     <input type="checkbox" name="members_to_update[]" value="<?= $member['user_id'] ?>">
                                                     <span class="checkmark"></span>
                                                     <div class="member-info-compact">
                                                         <span class="username"><?= htmlspecialchars($member['username']) ?></span>
                                                         <span class="role-badge-small <?= $member['role'] ?>"><?= ucfirst($member['role']) ?></span>
                                                     </div>
                                                 </label>
                                             </div>
                                         <?php endif; ?>
                                     <?php endforeach; ?>
                                 </div>
                                 
                                 <div class="selection-controls">
                                     <button type="button" class="btn btn--small btn--secondary" onclick="selectAllMembers()">
                                         <i class="fas fa-check-double"></i> Tout sélectionner
                                     </button>
                                     <button type="button" class="btn btn--small btn--secondary" onclick="deselectAllMembers()">
                                         <i class="fas fa-times"></i> Tout désélectionner
                                     </button>
                                 </div>
                             </div>
                             
                             <button type="submit" class="btn btn--warning">
                                 <i class="fas fa-save"></i>
                                 Mettre à jour les rôles
                             </button>
                         </form>
                     </div>

                     <div class="action-group">
                         <h3><i class="fas fa-user-minus"></i> Retirer plusieurs membres</h3>
                         <form action="<?= url('/admin/shared-spaces/' . $space['id'] . '/manage') ?>" method="POST" class="bulk-remove-form">
                             <input type="hidden" name="action" value="remove_multiple_members">
                             <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                             
                             <div class="members-selection">
                                 <label>Sélectionner les membres à retirer :</label>
                                 <div class="members-grid-compact">
                                     <?php foreach ($members as $member): ?>
                                         <?php if ($member['user_id'] !== $currentUser['id']): ?>
                                             <div class="member-select-item" data-role="<?= $member['role'] ?>">
                                                 <label class="checkbox-label">
                                                     <input type="checkbox" name="members_to_remove[]" value="<?= $member['user_id'] ?>">
                                                     <span class="checkmark"></span>
                                                     <div class="member-info-compact">
                                                         <span class="username"><?= htmlspecialchars($member['username']) ?></span>
                                                         <span class="role-badge-small <?= $member['role'] ?>"><?= ucfirst($member['role']) ?></span>
                                                     </div>
                                                 </label>
                                             </div>
                                         <?php endif; ?>
                                     <?php endforeach; ?>
                                 </div>
                                 
                                 <div class="selection-controls">
                                     <button type="button" class="btn btn--small btn--secondary" onclick="selectAllMembersForRemoval()">
                                         <i class="fas fa-check-double"></i> Tout sélectionner
                                     </button>
                                     <button type="button" class="btn btn--small btn--secondary" onclick="deselectAllMembersForRemoval()">
                                         <i class="fas fa-times"></i> Tout désélectionner
                                     </button>
                                 </div>
                             </div>
                             
                             <button type="submit" class="btn btn--danger">
                                 <i class="fas fa-trash"></i>
                                 Retirer les membres sélectionnés
                             </button>
                         </form>
                     </div>
                 </div>
             </div>
         </div>

        <!-- Actions dangereuses -->
        <div class="admin-section danger-zone">
            <h2><i class="fas fa-exclamation-triangle"></i> Zone dangereuse</h2>
            <p>Ces actions sont irréversibles. Utilisez-les avec précaution.</p>
            
            <div class="danger-actions">
                <button type="button" class="btn btn--danger" onclick="confirmDeleteSpace(<?= $space['id'] ?>)">
                    <i class="fas fa-trash"></i>
                    Supprimer l'espace
                </button>
            </div>
        </div>
    </div>




</body>
</html>
