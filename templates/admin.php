<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Cantal Destination Observatoire</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23F1C40F' d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '_navbar.php'; ?>

    <div class="admin-container">
        <div class="admin-header">
            <h1><i class="fas fa-users-cog"></i> Administration des utilisateurs</h1>
            <p>Gérez les comptes utilisateurs de l'observatoire</p>
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

        <!-- Formulaire de création d'utilisateur -->
        <div class="admin-section">
            <h2><i class="fas fa-user-plus"></i> Créer un nouvel utilisateur</h2>
            <form action="<?= url('/admin') ?>" method="POST" class="user-form">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur *</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="name">Nom complet *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email">
                    </div>
                    <div class="form-group">
                        <label for="role">Rôle *</label>
                        <select id="role" name="role" required>
                            <option value="user">Utilisateur</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Mot de passe *</label>
                        <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le mot de passe *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
                    </div>
                </div>
                
                <button type="submit" class="btn btn--primary">
                    <i class="fas fa-user-plus"></i>
                    Créer l'utilisateur
                </button>
            </form>
        </div>

        <!-- Liste des utilisateurs -->
        <div class="admin-section">
            <h2><i class="fas fa-users"></i> Utilisateurs existants</h2>
            <div class="users-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom d'utilisateur</th>
                            <th>Nom complet</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Dernière connexion</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr class="<?= $user['active'] ? '' : 'inactive' ?>">
                                <td><?= htmlspecialchars($user['id']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                                <td>
                                    <span class="role-badge <?= $user['role'] ?>">
                                        <?= $user['role'] === 'admin' ? 'Administrateur' : 'Utilisateur' ?>
                                    </span>
                                </td>
                                <td>
                                    <?= isset($user['last_login']) && $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais' ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $user['active'] ? 'active' : 'inactive' ?>">
                                        <?= $user['active'] ? 'Actif' : 'Inactif' ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <?php if ($user['username'] !== 'admin'): ?>
                                        <?php if ($user['active']): ?>
                                            <form action="<?= url('/admin') ?>" method="POST" style="display: inline; margin-right: 5px;">
                                                <input type="hidden" name="action" value="deactivate">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                                                <button type="submit" class="btn btn--small btn--warning" 
                                                        onclick="return confirm('Êtes-vous sûr de vouloir désactiver cet utilisateur ?')"
                                                        title="Désactiver l'utilisateur">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form action="<?= url('/admin') ?>" method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                                            <button type="submit" class="btn btn--small btn--danger" 
                                                    onclick="return confirm('⚠️ ATTENTION ⚠️\n\nCette action est IRRÉVERSIBLE !\n\nÊtes-vous absolument sûr de vouloir SUPPRIMER DÉFINITIVEMENT cet utilisateur ?\n\nToutes ses données seront perdues.')"
                                                    title="Supprimer définitivement l'utilisateur">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Gestion des Périodes -->
        <div class="admin-section">
            <h2><i class="fas fa-calendar-alt"></i> Gestion des Périodes</h2>
            <p>Administration complète des périodes touristiques : création, modification, suppression et duplication.</p>
            <div style="margin-top: 15px;">
                <a href="<?= url('/admin/periodes') ?>" class="btn btn--primary">
                    <i class="fas fa-calendar-check"></i>
                    Gérer les Périodes
                </a>
            </div>
        </div>

        <!-- Gestion des Espaces Partagés -->
        <div class="admin-section">
            <h2><i class="fas fa-users"></i> Espaces Partagés</h2>
            <p>Gestion des espaces de travail collaboratifs pour les infographies.</p>
            <div style="margin-top: 15px;">
                <a href="<?= url('/admin/shared-spaces') ?>" class="btn btn--primary">
                    <i class="fas fa-users"></i>
                    Gérer les Espaces Partagés
                </a>
            </div>
        </div>

        <!-- Gestion des Tables Temporaires -->
        <div class="admin-section">
            <h2><i class="fas fa-database"></i> Tables Temporaires</h2>
            <p>Gestion et automatisation des tables temporaires de données touristiques.</p>
            <div style="margin-top: 15px;">
                <a href="<?= url('/admin/temp-tables') ?>" class="btn btn--primary">
                    <i class="fas fa-table"></i>
                    Gérer les Tables Temporaires
                </a>
            </div>
        </div>

        <!-- Configuration Email -->
        <div class="admin-section">
            <h2><i class="fas fa-envelope"></i> Configuration Email</h2>
            <p>Configurez et testez l'envoi d'emails de notification automatiques.</p>
            <div style="margin-top: 15px;">
                <a href="<?= url('/admin/email-test') ?>" class="btn btn--secondary">
                    <i class="fas fa-cog"></i>
                    Tester Configuration Email
                </a>
            </div>
        </div>

        <!-- Outils de Maintenance -->
        <div class="admin-section">
            <h2><i class="fas fa-tools"></i> Outils de Maintenance</h2>
            <p>Outils de diagnostic et réparation des données utilisateurs.</p>
            <div style="margin-top: 15px;">
                <a href="<?= url('/admin/repair-users') ?>" class="btn btn--warning">
                    <i class="fas fa-wrench"></i>
                    Réparer Données Utilisateurs
                </a>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="admin-section">
            <h2><i class="fas fa-chart-pie"></i> Statistiques</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= count(array_filter($users, fn($u) => $u['active'])) ?></h3>
                        <p>Utilisateurs actifs</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= count(array_filter($users, fn($u) => $u['role'] === 'admin' && $u['active'])) ?></h3>
                        <p>Administrateurs</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= count(array_filter($users, fn($u) => isset($u['last_login']) && $u['last_login'] !== null)) ?></h3>
                        <p>Connexions enregistrées</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= count(array_filter($users, fn($u) => !$u['active'])) ?></h3>
                        <p>Utilisateurs inactifs</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 