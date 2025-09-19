<?php
/**
 * Template d'administration - Gestion des Tables Temporaires
 * Cantal Destination - Interface d'automatisation
 */

// Utilisation du contrôleur pour la logique métier
require_once __DIR__ . '/../classes/AdminTempTablesController.php';
$controller = new AdminTempTablesController();
$data = $controller->index();

// Extraction des données pour le template
extract($data);
$stats = $data['stats'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Tables Temporaires | Cantal Destination</title>
    <link rel="stylesheet" href="<?= asset('/static/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('/static/css/admin-temp-tables.css') ?>">
</head>
<body>
    <?php include '_navbar.php'; ?>

    <div class="admin-container">
        <div class="admin-header">
            <h1><i class="fas fa-database"></i> Gestion des Tables Temporaires</h1>
            <p>Automatisation des données touristiques - Cantal Destination</p>
        </div>
        
        <?php if ($message): ?>
            <div class="<?= $messageType === 'error' ? 'error-message' : 'success-message' ?>">
                <i class="fas fa-<?= $messageType === 'error' ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Actions de gestion -->
        <div class="admin-section">
            <h2><i class="fas fa-cogs"></i> Actions</h2>
            <div class="form-row">
                <form method="POST" class="admin-action-form">
                    <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                    <input type="hidden" name="action" value="refresh">
                    <button type="submit" class="btn btn--secondary">
                        <i class="fas fa-sync"></i>
                        Actualiser le statut
                    </button>
                </form>
                
                <form method="POST" class="admin-action-form">
                    <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                    <input type="hidden" name="action" value="check">
                    <button type="submit" class="btn btn--primary">
                        <i class="fas fa-search"></i>
                        Vérifier les changements
                    </button>
                </form>
                
                <form method="POST" class="admin-action-form">
                    <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                    <input type="hidden" name="action" value="force">
                    <button type="submit" class="btn btn--warning">
                        <i class="fas fa-sync-alt"></i>
                        Forcer la mise à jour
                    </button>
                </form>

                <form method="POST" class="admin-action-form">
                    <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                    <input type="hidden" name="action" value="direct_import">
                    <button type="submit" class="btn btn--danger" onclick="return confirm('Cette action lance l\'import en arrière-plan. Cela peut prendre plusieurs minutes. Voulez-vous continuer ?');">
                        <i class="fas fa-rocket"></i>
                        Import Direct (Arrière-plan)
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Statistiques générales -->
        <div class="admin-section">
            <h2><i class="fas fa-chart-bar"></i> Vue d'ensemble</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-csv"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['existing_files'] ?>/<?= $stats['total_files'] ?></h3>
                        <p>Fichiers CSV</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-table"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= count($stats['files_config']) ?></h3>
                        <p>Tables temporaires</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-hdd"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($stats['total_size'] / 1024, 1) ?> KB</h3>
                        <p>Taille totale</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Migration vers tables principales -->
        <div class="admin-section">
            <h2><i class="fas fa-exchange-alt"></i> Migration vers Tables Principales</h2>
            <p class="section-description">
                <i class="fas fa-info-circle"></i>
                Transférez les données des tables temporaires vers les tables principales avec le marquage <code>is_provisional = 1</code>. 
                Cette opération évite automatiquement les doublons.
            </p>
            
            <?php if ($migrationMessage): ?>
                <div class="<?= $migrationMessageType === 'error' ? 'error-message' : ($migrationMessageType === 'warning' ? 'warning-message' : 'success-message') ?>">
                    <i class="fas fa-<?= $migrationMessageType === 'error' ? 'exclamation-triangle' : ($migrationMessageType === 'warning' ? 'exclamation-circle' : 'check-circle') ?>"></i>
                    <?= htmlspecialchars($migrationMessage) ?>
                </div>
            <?php endif; ?>
            
            <div class="form-row">
                <form method="POST" class="admin-action-form">
                    <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                    <input type="hidden" name="action" value="verify_migration">
                    <button type="submit" class="btn btn--secondary">
                        <i class="fas fa-search"></i>
                        Vérifier l'état de la migration
                    </button>
                </form>
                
                <form method="POST" class="admin-action-form">
                    <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                    <input type="hidden" name="action" value="migrate_to_main">
                    <button type="submit" class="btn btn--primary">
                        <i class="fas fa-database"></i>
                        Migrer vers les tables principales
                    </button>
                </form>
            </div>
            
            <!-- Section pour vider les tables temporaires -->
            <div class="card mt-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-trash-alt"></i> Gestion des Tables Temporaires
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        <strong>Attention :</strong> Cette action supprimera définitivement toutes les données des tables temporaires.
                    </p>
                    
                    <form method="post" style="display: inline-block;">
                        <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                        <input type="hidden" name="action" value="clear_temp_tables">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-trash-alt"></i> Vider les Tables Temporaires
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="migration-info">
                <h3><i class="fas fa-lightbulb"></i> Informations importantes</h3>
                <ul>
                    <li><strong>Sécurité :</strong> Les doublons sont automatiquement évités</li>
                    <li><strong>Marquage :</strong> Toutes les données migrées auront <code>is_provisional = 1</code></li>
                    <li><strong>Conservation :</strong> Les tables temporaires ne sont pas supprimées</li>
                    <li><strong>Réversibilité :</strong> Vous pouvez supprimer les données provisoires avec <code>DELETE FROM table WHERE is_provisional = 1</code></li>
                </ul>
            </div>
        </div>
        
        <!-- Transfert des tables PROVISOIRE vers principales -->
        <div class="admin-section">
            <h2><i class="fas fa-random"></i> Transfert des tables PROVISOIRE vers principales</h2>
            <p>Transférez les données de chaque table <code>_provisoire</code> vers la table principale correspondante (sans la colonne <code>id</code>).</p>
            <?php 
            if (!empty($provisoireTables)) : ?>
                <div class="users-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Table provisoire</th>
                                <th>Table principale</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($provisoireTables as $provTable): 
                            $mainTable = preg_replace('/_provisoire$/', '', $provTable); ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($provTable) ?></strong></td>
                                <td><?= htmlspecialchars($mainTable) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                                        <input type="hidden" name="action" value="transfer_provisoire_to_main">
                                        <input type="hidden" name="provisoire_table" value="<?= htmlspecialchars($provTable) ?>">
                                        <button type="submit" class="btn btn--primary btn--small" onclick="return confirm('Transférer toutes les données de <?= htmlspecialchars($provTable) ?> vers <?= htmlspecialchars($mainTable) ?> ?\nAucune suppression automatique de la table provisoire.');">
                                            <i class="fas fa-random"></i> Transférer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted"><i class="fas fa-info-circle"></i> Aucune table <code>_provisoire</code> trouvée dans la base.</p>
            <?php endif; ?>
            <?php if ($transferMessage): ?>
                <div class="<?= $transferMessageType === 'error' ? 'error-message' : 'success-message' ?>">
                    <i class="fas fa-<?= $transferMessageType === 'error' ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                    <?= htmlspecialchars($transferMessage) ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Suppression des données provisoires -->
        <div class="admin-section">
            <h2><i class="fas fa-eraser"></i> Suppression des Données Provisoires</h2>
            <p class="section-description">
                <i class="fas fa-info-circle"></i>
                Supprimez toutes les données provisoires (marquées <code>is_provisional = 1</code>) des tables principales. 
                Cette action permet de nettoyer les données migrées depuis les tables temporaires.
            </p>
            
            <div class="form-row">
                <form method="POST" class="admin-action-form">
                    <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                    <input type="hidden" name="action" value="clear_provisional_data">
                    <button type="submit" class="btn btn--danger">
                        <i class="fas fa-eraser"></i>
                        Supprimer les données provisoires
                    </button>
                </form>
            </div>
            
            <div class="provisional-info">
                <h3><i class="fas fa-exclamation-triangle"></i> Attention</h3>
                <ul>
                    <li><strong>Action irréversible :</strong> Les données supprimées ne pourront pas être récupérées</li>
                    <li><strong>Cible :</strong> Seules les données avec <code>is_provisional = 1</code> sont supprimées</li>
                    <li><strong>Tables concernées :</strong> fact_nuitees*, fact_diurnes* (tables principales uniquement)</li>
                    <li><strong>Sécurité :</strong> Les tables temporaires ne sont pas affectées</li>
                </ul>
            </div>
        </div>
        
        <!-- Gestion des fichiers CSV -->
        <div class="admin-section">
            <h2><i class="fas fa-file-csv"></i> Gestion des fichiers CSV</h2>
            
            <!-- Upload de fichier -->
            <div class="form-group">
                <h3><i class="fas fa-upload"></i> Ajouter un fichier CSV</h3>
                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                    <input type="hidden" name="file_action" value="upload">
                    
                    <div class="form-row">
                        <div class="file-input-wrapper">
                            <input type="file" name="csv_files[]" id="csv_files" accept=".csv" multiple webkitdirectory directory>
                            <label for="csv_files" class="file-input-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                Choisir des fichiers CSV ou un dossier
                            </label>
                        </div>
                        <button type="submit" class="btn btn--primary">
                            <i class="fas fa-plus"></i>
                            Ajouter
                        </button>
                    </div>
                </form>
                <p class="help-text">
                    <i class="fas fa-info-circle"></i>
                    Vous pouvez sélectionner plusieurs fichiers CSV ou un dossier (tous les CSV inclus seront importés). Les fichiers seront ajoutés dans /data/data_temp/ en préservant l'arborescence.
                </p>
            </div>

            <!-- État des fichiers CSV -->
            <?php if ($status !== null): ?>
            <div class="form-group">
                <h3><i class="fas fa-list"></i> État détaillé des fichiers CSV</h3>
                <div class="users-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Fichier CSV</th>
                                <th>Table temporaire</th>
                                <th>Enregistrements</th>
                                <th>Taille</th>
                                <th>Dernière modification</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($status as $file): ?>
                                <tr class="<?= $file['exists'] ? '' : 'inactive' ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($file['filename']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($file['table']) ?></td>
                                    <td><?= is_numeric($file['records']) ? number_format($file['records']) : $file['records'] ?></td>
                                    <td>
                                        <?= $file['exists'] ? number_format($file['size']) . ' octets' : '-' ?>
                                    </td>
                                    <td>
                                        <?= $file['exists'] ? date('d/m/Y H:i:s', $file['modified']) : '-' ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $file['exists'] ? 'active' : 'inactive' ?>">
                                            <?= $file['exists'] ? 'Disponible' : 'Manquant' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($file['exists']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                                            <input type="hidden" name="file_action" value="delete">
                                            <input type="hidden" name="filename" value="<?= htmlspecialchars($file['filename']) ?>">
                                            <button type="submit" class="btn btn--danger btn--small">
                                                <i class="fas fa-trash"></i>
                                                Supprimer
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="form-group">
                <h3><i class="fas fa-list"></i> Fichiers CSV disponibles</h3>
                <div class="files-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom du fichier</th>
                                <th>Taille</th>
                                <th>Dernière modification</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($stats['data_dir']) {
                                // Recherche récursive de tous les CSV sous data_dir
                                $csv_files = [];
                                try {
                                    $it = new RecursiveIteratorIterator(
                                        new RecursiveDirectoryIterator($stats['data_dir'], FilesystemIterator::SKIP_DOTS)
                                    );
                                    foreach ($it as $fi) {
                                        if ($fi->isFile() && strtolower($fi->getExtension()) === 'csv') {
                                            $csv_files[] = $fi->getPathname();
                                        }
                                    }
                                } catch (Exception $e) {
                                    $csv_files = glob($stats['data_dir'] . '/*.csv');
                                }
                                if (empty($csv_files)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">
                                            <i class="fas fa-folder-open"></i>
                                            Aucun fichier CSV trouvé
                                        </td>
                                    </tr>
                                <?php else:
                                    foreach ($csv_files as $file_path):
                                        $filename = basename($file_path);
                                        $file_size = @filesize($file_path);
                                        $file_modified = @filemtime($file_path);
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($filename) ?></strong>
                                            <?php if (isset($stats['files_config'][$filename])): ?>
                                                <br><small class="text-muted">→ <?= htmlspecialchars($stats['files_config'][$filename]) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $file_size ? number_format($file_size / 1024, 1) . ' KB' : '-' ?></td>
                                        <td><?= $file_modified ? date('d/m/Y H:i:s', $file_modified) : '-' ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                                                <input type="hidden" name="file_action" value="delete">
                                                <input type="hidden" name="filename" value="<?= htmlspecialchars($filename) ?>">
                                                <button type="submit" class="btn btn--danger btn--small">
                                                    <i class="fas fa-trash"></i>
                                                    Supprimer
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; 
                                endif;
                            } else { ?>
                                <tr>
                                    <td colspan="4" class="text-center">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Répertoire de données non accessible
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <p class="help-text">
                    <i class="fas fa-info-circle"></i>
                    Cliquez sur "Actualiser le statut" pour voir les détails complets avec les tables temporaires.
                </p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Logs d'activité -->
        <div class="admin-section">
            <h2><i class="fas fa-file-alt"></i> Logs d'activité</h2>
            
            <?php if (!empty($logs)): ?>
                <div class="form-row" style="margin-bottom: 15px;">
                    <a href="<?= url('/admin/temp-tables/download-logs') ?>" class="btn btn--secondary btn--small">
                        <i class="fas fa-download"></i>
                        Télécharger logs complets
                    </a>
                    
                    <form method="POST" class="admin-action-form" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= Security::getCSRFToken() ?>">
                        <input type="hidden" name="action" value="clear_logs">
                        <button type="submit" class="btn btn--danger btn--small">
                            <i class="fas fa-trash"></i>
                            Vider les logs
                        </button>
                    </form>
                    
                    <?php if ($log_info): ?>
                    <span class="help-text">
                        <i class="fas fa-info-circle"></i>
                        <?= count($logs) ?> entrées affichées sur <?= $log_info['total_lines'] ?> total
                        • Taille: <?= number_format($log_info['size'] / 1024, 1) ?> KB
                        • Modifié: <?= date('d/m/Y H:i', $log_info['modified']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <div class="logs-container">
                    <?php foreach ($logs as $log): ?>
                        <div class="log-entry">
                            <?= $log ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="help-text">
                    <i class="fas fa-info-circle"></i>
                    Aucun log disponible. Les logs apparaîtront ici après les opérations de mise à jour.
                </p>
            <?php endif; ?>

            <h3 style="margin-top:25px"><i class="fas fa-exchange-alt"></i> Logs Migration Temp → Main</h3>
            <?php if (!empty($migration_logs)): ?>
                <div class="form-row" style="margin-bottom: 15px;">
                    <a href="<?= url('/admin/temp-tables/download-logs?type=migration') ?>" class="btn btn--secondary btn--small">
                        <i class="fas fa-download"></i>
                        Télécharger logs migration
                    </a>
                    <?php if ($migration_log_info): ?>
                    <span class="help-text">
                        <i class="fas fa-info-circle"></i>
                        <?= count($migration_logs) ?> entrées affichées sur <?= $migration_log_info['total_lines'] ?> total
                        • Taille: <?= number_format($migration_log_info['size'] / 1024, 1) ?> KB
                        • Modifié: <?= date('d/m/Y H:i', $migration_log_info['modified']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="logs-container">
                    <?php foreach ($migration_logs as $log): ?>
                        <div class="log-entry"><?= $log ?></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="help-text">
                    <i class="fas fa-info-circle"></i>
                    Aucun log de migration trouvé pour l’instant.
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Gestion du Cache -->
        <div class="section">
            <h3>Gestion du Cache</h3>
            <p>Vider le cache système pour forcer le rechargement des données.</p>
            <form method="post" class="action-form">
                <input type="hidden" name="csrf_token" value="<?php echo Security::getCSRFToken(); ?>">
                <input type="hidden" name="action" value="clear_cache">
                <button type="submit" class="btn btn-warning">Vider le Cache</button>
            </form>
        </div>

        <!-- Configuration automatisation -->
        <div class="admin-section">
            <h2><i class="fas fa-robot"></i> Automatisation</h2>
            <p>Pour automatiser les mises à jour, configurez un cron job :</p>
            <div class="code-block">
                <code>*/15 * * * * curl -s "<?= url('/update_temp_tables.php?action=check') ?>"</code>
            </div>
            <p class="help-text">
                <i class="fas fa-info-circle"></i>
                Cette commande vérifie les changements toutes les 15 minutes et met à jour uniquement les tables modifiées.
            </p>
        </div>
        
        <!-- Informations système -->
        <div class="admin-section">
            <h2><i class="fas fa-server"></i> Informations système</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fab fa-php"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= phpversion() ?></h3>
                        <p>Version PHP</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= extension_loaded('mysqli') ? 'Actif' : 'Inactif' ?></h3>
                        <p>MySQLi</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-folder"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= is_writable(__DIR__ . '/../data/logs') ? 'OK' : 'Erreur' ?></h3>
                        <p>Permissions logs</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= asset('/static/js/utils.js') ?>"></script>
    <script src="<?= asset('/static/js/admin-temp-tables.js') ?>"></script>
    <script>
        // Maintenir la position de scroll après les actions POST
        document.addEventListener('DOMContentLoaded', function() {
            // Si il y a un message de transfert, scroller vers la section des tables provisoires
            <?php if ($transferMessage): ?>
            const transferSection = document.querySelector('h2').parentElement;
            const allH2 = document.querySelectorAll('h2');
            for (let h2 of allH2) {
                if (h2.textContent.includes('Transfert des tables PROVISOIRE')) {
                    h2.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    break;
                }
            }
            <?php endif; ?>
            
            // Stocker la position de scroll avant envoi de formulaire
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    sessionStorage.setItem('scrollPosition', window.scrollY);
                });
            });
            
            // Restaurer la position de scroll après rechargement
            const scrollPosition = sessionStorage.getItem('scrollPosition');
            if (scrollPosition && !<?= $transferMessage ? 'true' : 'false' ?>) {
                window.scrollTo(0, parseInt(scrollPosition));
                sessionStorage.removeItem('scrollPosition');
            }
        });
    </script>
</body>
</html> 