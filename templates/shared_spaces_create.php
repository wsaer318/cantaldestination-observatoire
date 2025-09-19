<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Espace Partagé - FluxVision</title>
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '_navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div class="header-content">
                <h1><i class="fas fa-plus-circle"></i> Créer un Espace Partagé</h1>
                <p>Créez un nouvel espace de travail collaboratif pour partager des infographies</p>
            </div>
            <div class="header-actions">
                <a href="<?= url('/shared-spaces') ?>" class="btn btn--secondary">
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

        <div class="content-section">
            <form action="<?= url('/shared-spaces/create') ?>" method="POST" class="space-form">
                <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="space_name">Nom de l'espace *</label>
                        <input type="text" id="space_name" name="space_name" required 
                               placeholder="Ex: Équipe Marketing Cantal"
                               value="<?= htmlspecialchars($_POST['space_name'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="space_description">Description</label>
                    <textarea id="space_description" name="space_description" rows="3"
                              placeholder="Description de l'espace de travail..."
                              ><?= htmlspecialchars($_POST['space_description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Membres initiaux (optionnel)</label>
                    <p class="form-help">Sélectionnez les utilisateurs que vous souhaitez ajouter à cet espace</p>
                    
                    <?php if (empty($availableUsers)): ?>
                        <p class="text-muted">Aucun utilisateur disponible</p>
                    <?php else: ?>
                        <div class="members-selection">
                            <?php foreach ($availableUsers as $user): ?>
                                <div class="member-checkbox">
                                    <input type="checkbox" id="user_<?= $user['id'] ?>" 
                                           name="members[]" value="<?= $user['id'] ?>"
                                           <?= in_array($user['id'], $_POST['members'] ?? []) ? 'checked' : '' ?>>
                                    <label for="user_<?= $user['id'] ?>">
                                        <strong><?= htmlspecialchars($user['username']) ?></strong>
                                        <small class="user-role"><?= htmlspecialchars($user['role']) ?></small>
                                    </label>
                                    <select name="member_role_<?= $user['id'] ?>" class="role-select">
                                        <option value="reader" <?= ($_POST["member_role_{$user['id']}"] ?? 'reader') === 'reader' ? 'selected' : '' ?>>Lecteur</option>
                                        <option value="editor" <?= ($_POST["member_role_{$user['id']}"] ?? 'reader') === 'editor' ? 'selected' : '' ?>>Éditeur</option>
                                        <option value="validator" <?= ($_POST["member_role_{$user['id']}"] ?? 'reader') === 'validator' ? 'selected' : '' ?>>Validateur</option>
                                        <option value="admin" <?= ($_POST["member_role_{$user['id']}"] ?? 'reader') === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">
                        <i class="fas fa-plus"></i>
                        Créer l'espace
                    </button>
                    <a href="<?= url('/shared-spaces') ?>" class="btn btn--secondary">
                        Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Améliorer l'expérience utilisateur
        document.addEventListener('DOMContentLoaded', function() {
            const memberCheckboxes = document.querySelectorAll('input[name="members[]"]');
            const roleSelects = document.querySelectorAll('.role-select');
            
            // Activer/désactiver les sélecteurs de rôle selon la case cochée
            memberCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const userId = this.value;
                    const roleSelect = document.querySelector(`select[name="member_role_${userId}"]`);
                    
                    if (roleSelect) {
                        roleSelect.disabled = !this.checked;
                    }
                });
                
                // Initialiser l'état
                if (checkbox.checked) {
                    const userId = checkbox.value;
                    const roleSelect = document.querySelector(`select[name="member_role_${userId}"]`);
                    if (roleSelect) {
                        roleSelect.disabled = false;
                    }
                }
            });
        });
    </script>
</body>
</html>
