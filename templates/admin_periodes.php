<?php
/**
 * Template d'administration - Gestion des Périodes
 * Interface complète pour la gestion des périodes touristiques
 */

// Inclusion des dépendances
require_once __DIR__ . '/../classes/PeriodesController.php';
require_once __DIR__ . '/../classes/Security.php';

$controller = new PeriodesController();
$message = '';
$messageType = '';

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token CSRF invalide';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $result = $controller->createPeriode($_POST);
                $message = $result['success'] ? $result['message'] : $result['error'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
                
            case 'update':
                $result = $controller->updatePeriode($_POST['id_periode'], $_POST);
                $message = $result['success'] ? $result['message'] : $result['error'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
                
            case 'delete':
                $result = $controller->deletePeriode($_POST['id_periode']);
                $message = $result['success'] ? $result['message'] : $result['error'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
                
            case 'duplicate':
                $result = $controller->duplicatePeriode($_POST['id_periode'], $_POST['nouvelle_annee']);
                $message = $result['success'] ? $result['message'] : $result['error'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
                
            case 'delete_groupe':
                $result = $controller->deleteGroupe($_POST['code_periode']);
                $message = $result['success'] ? $result['message'] : $result['error'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
        }
    }
}

// Récupération des données
$periodesGroupees = $controller->getPeriodesGroupees();
$stats = $controller->getStats();

// Récupération d'une période pour édition
$periodeEdit = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $periodeEdit = $controller->getPeriodeById($_GET['edit']);
}

// Récupération des détails d'un groupe de périodes
$detailsGroupe = null;
$periodesDetail = [];
if (isset($_GET['details']) && !empty($_GET['details'])) {
    $codeGroupe = $_GET['details'];
    $periodesDetail = $controller->getPeriodesByCode($codeGroupe);
    if (!empty($periodesDetail)) {
        $detailsGroupe = $codeGroupe;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Gestion des Périodes | Cantal Destination</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23F1C40F' d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('/static/css/admin-periodes.css') ?>">
</head>
<body>
    <?php include '_navbar.php'; ?>

    <div class="periodes-container">
        <div class="periodes-header">
            <h1><i class="fas fa-calendar-alt"></i> Gestion des Périodes</h1>
            <p>Administration complète des périodes touristiques</p>
        </div>

        <?php if ($message): ?>
            <div class="<?= $messageType === 'error' ? 'error-message' : 'success-message' ?>">
                <i class="fas fa-<?= $messageType === 'error' ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label">Périodes totales</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?= count($stats['par_annee']) ?></div>
                <div class="stat-label">Années couvertes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-value"><?= count($stats['codes_populaires']) ?></div>
                <div class="stat-label">Types de périodes</div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="quick-actions">
            <button onclick="openModal('createModal')" class="btn btn--primary">
                <i class="fas fa-plus"></i>
                Nouvelle Période
            </button>
            <button onclick="location.reload()" class="btn btn--secondary">
                <i class="fas fa-sync"></i>
                Actualiser
            </button>
        </div>

        <!-- Formulaire de création/édition -->
        <div class="admin-section">
            <div class="section-header">
                <h2>
                    <i class="fas fa-<?= $periodeEdit ? 'edit' : 'plus' ?>"></i>
                    <?= $periodeEdit ? 'Modifier la Période' : 'Créer une Nouvelle Période' ?>
                </h2>
            </div>

            <form method="POST" id="periodeForm">
                <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                <input type="hidden" name="action" value="<?= $periodeEdit ? 'update' : 'create' ?>">
                <?php if ($periodeEdit): ?>
                    <input type="hidden" name="id_periode" value="<?= $periodeEdit['id_periode'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="code_periode">Code de la Période *</label>
                        <input type="text" 
                               id="code_periode" 
                               name="code_periode" 
                               value="<?= htmlspecialchars($periodeEdit['code_periode'] ?? '') ?>"
                               placeholder="ex: ete, hiver, paques..."
                               pattern="[a-z_]+"
                               title="Uniquement des lettres minuscules et des underscores"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="nom_periode">Nom de la Période *</label>
                        <input type="text" 
                               id="nom_periode" 
                               name="nom_periode" 
                               value="<?= htmlspecialchars($periodeEdit['nom_periode'] ?? '') ?>"
                               placeholder="ex: Vacances d'été"
                               required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="annee">Année *</label>
                        <select id="annee" name="annee" required>
                            <?php 
                            $currentYear = date('Y');
                            $selectedYear = $periodeEdit['annee'] ?? $currentYear;
                            for ($year = $currentYear - 2; $year <= $currentYear + 5; $year++): 
                            ?>
                                <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="date_debut">Date de Début *</label>
                        <input type="date" 
                               id="date_debut" 
                               name="date_debut" 
                               value="<?= $periodeEdit ? date('Y-m-d', strtotime($periodeEdit['date_debut'])) : '' ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_fin">Date de Fin *</label>
                        <input type="date" 
                               id="date_fin" 
                               name="date_fin" 
                               value="<?= $periodeEdit ? date('Y-m-d', strtotime($periodeEdit['date_fin'])) : '' ?>"
                               required>
                    </div>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 25px;">
                    <button type="submit" class="btn btn--primary">
                        <i class="fas fa-<?= $periodeEdit ? 'save' : 'plus' ?>"></i>
                        <?= $periodeEdit ? 'Mettre à Jour' : 'Créer la Période' ?>
                    </button>
                    
                    <?php if ($periodeEdit): ?>
                        <a href="<?= url('/admin/periodes') ?>" class="btn btn--secondary">
                            <i class="fas fa-times"></i>
                            Annuler
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Groupes de périodes -->
        <div class="admin-section">
            <div class="section-header">
                <h2>
                    <i class="fas fa-layer-group"></i>
                    Types de Périodes (<?= count($periodesGroupees) ?> groupes)
                </h2>
            </div>

            <div class="groupes-grid">
                <?php foreach ($periodesGroupees as $groupe): ?>
                    <div class="groupe-card">
                        <div class="groupe-header">
                            <div class="groupe-info">
                                <h3><?= htmlspecialchars($groupe['nom_periode']) ?></h3>
                                <span class="code-badge"><?= htmlspecialchars($groupe['code_periode']) ?></span>
                            </div>
                            <div class="groupe-stats">
                                <span class="stat-badge"><?= $groupe['nb_annees'] ?> année<?= $groupe['nb_annees'] > 1 ? 's' : '' ?></span>
                            </div>
                        </div>
                        
                        <div class="groupe-content">
                            <div class="periode-range">
                                <i class="fas fa-calendar-alt"></i>
                                <span><?= $groupe['premiere_annee'] ?> - <?= $groupe['derniere_annee'] ?></span>
                            </div>
                            
                            <div class="annees-disponibles">
                                <strong>Années disponibles :</strong>
                                <div class="annees-list">
                                    <?php foreach ($groupe['annees_disponibles'] as $annee): ?>
                                        <span class="year-tag"><?= $annee ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="groupe-actions">
                            <a href="?details=<?= urlencode($groupe['code_periode']) ?>#details-groupe" 
                               class="btn btn--small btn--secondary">
                                <i class="fas fa-eye"></i>
                                Voir détails
                            </a>
                            
                            <button onclick="ajouterAnnee('<?= htmlspecialchars($groupe['code_periode']) ?>', '<?= htmlspecialchars($groupe['nom_periode']) ?>')" 
                                    class="btn btn--small btn--success">
                                <i class="fas fa-plus"></i>
                                Ajouter année
                            </button>
                            
                            <button onclick="supprimerGroupe('<?= htmlspecialchars($groupe['code_periode']) ?>', '<?= htmlspecialchars($groupe['nom_periode']) ?>', <?= $groupe['nb_annees'] ?>)" 
                                    class="btn btn--small btn--danger">
                                <i class="fas fa-trash"></i>
                                Supprimer groupe
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($detailsGroupe): ?>
        <!-- Détails d'un groupe de périodes -->
        <div class="admin-section" id="details-groupe">
            <div class="section-header">
                <h2>
                    <i class="fas fa-search"></i>
                    Détails du groupe "<?= htmlspecialchars($detailsGroupe) ?>"
                </h2>
                <a href="<?= url('/admin/periodes') ?>" class="btn btn--small btn--secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux groupes
                </a>
            </div>

            <div class="periodes-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Année</th>
                            <th>Date de Début</th>
                            <th>Date de Fin</th>
                            <th>Durée</th>
                            <th>Créé le</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($periodesDetail as $periode): ?>
                            <?php
                            $dateDebut = new DateTime($periode['date_debut']);
                            $dateFin = new DateTime($periode['date_fin']);
                            $duree = $dateDebut->diff($dateFin)->days + 1;
                            $dateCreation = new DateTime($periode['created_at']);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($periode['id_periode']) ?></td>
                                <td>
                                    <span class="year-badge"><?= htmlspecialchars($periode['annee']) ?></span>
                                </td>
                                <td><?= $dateDebut->format('d/m/Y') ?></td>
                                <td><?= $dateFin->format('d/m/Y') ?></td>
                                <td><?= $duree ?> jour<?= $duree > 1 ? 's' : '' ?></td>
                                <td><?= $dateCreation->format('d/m/Y H:i') ?></td>
                                <td class="actions">
                                    <a href="?edit=<?= $periode['id_periode'] ?>" 
                                       class="btn btn--small btn--secondary" 
                                       title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <button onclick="openDuplicateModal(<?= $periode['id_periode'] ?>, '<?= htmlspecialchars($periode['nom_periode']) ?>')" 
                                            class="btn btn--small btn--success" 
                                            title="Dupliquer">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('⚠️ ATTENTION ⚠️\n\nÊtes-vous sûr de vouloir supprimer la période \'<?= htmlspecialchars($periode['nom_periode']) ?>\' (<?= $periode['annee'] ?>) ?\n\nCette action est irréversible !')">
                                        <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id_periode" value="<?= $periode['id_periode'] ?>">
                                        <button type="submit" class="btn btn--small btn--danger" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal de duplication -->
    <div id="duplicateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-copy"></i> Dupliquer la Période</h3>
                <span class="close" onclick="closeModal('duplicateModal')">&times;</span>
            </div>
            
            <p>Dupliquer la période "<span id="duplicatePeriodName"></span>" vers une nouvelle année :</p>
            
            <form method="POST" class="duplicate-form">
                <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                <input type="hidden" name="action" value="duplicate">
                <input type="hidden" name="id_periode" id="duplicatePeriodId">
                
                <div class="form-group">
                    <label for="nouvelle_annee">Nouvelle Année</label>
                    <select name="nouvelle_annee" id="nouvelle_annee" required>
                        <?php 
                        $currentYear = date('Y');
                        for ($year = $currentYear; $year <= $currentYear + 5; $year++): 
                        ?>
                            <option value="<?= $year ?>"><?= $year ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn--success">
                    <i class="fas fa-copy"></i>
                    Dupliquer
                </button>
            </form>
        </div>
    </div>

    <!-- Modal de suppression de groupe -->
    <div id="deleteGroupeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Supprimer le Groupe</h3>
                <span class="close" onclick="closeModal('deleteGroupeModal')">&times;</span>
            </div>
            
            <div style="padding: 25px;">
                <div class="warning-message" style="margin-bottom: 20px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>⚠️ ATTENTION - Action irréversible ⚠️</strong>
                </div>
                
                <p style="margin-bottom: 15px;">
                    Vous êtes sur le point de supprimer le groupe de périodes :
                </p>
                
                <div style="background: var(--surface); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <strong id="deleteGroupeName" style="color: var(--primary);"></strong><br>
                    <span style="opacity: 0.8;">Code : <code id="deleteGroupeCode"></code></span><br>
                    <span style="opacity: 0.8;" id="deleteGroupeCount"></span>
                </div>
                
                <p style="margin-bottom: 20px; color: var(--accent2);">
                    <strong>Cette action supprimera définitivement toutes les périodes de ce groupe !</strong>
                </p>
                
                <form method="POST" id="deleteGroupeForm">
                    <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                    <input type="hidden" name="action" value="delete_groupe">
                    <input type="hidden" name="code_periode" id="deleteGroupeCodeInput">
                    
                    <div style="display: flex; gap: 15px; justify-content: flex-end;">
                        <button type="button" onclick="closeModal('deleteGroupeModal')" class="btn btn--secondary">
                            <i class="fas fa-times"></i>
                            Annuler
                        </button>
                        
                        <button type="submit" class="btn btn--danger">
                            <i class="fas fa-trash"></i>
                            Supprimer définitivement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?= asset('/static/js/utils.js') ?>"></script>
    <script src="<?= asset('/static/js/admin-periodes.js') ?>"></script>
</body>
</html> 