<?php
/**
 * Interface d'administration des caches d'infographie
 * Permet de visualiser, nettoyer et gérer les caches organisés
 */

// Sécurité : vérifier l'accès admin (à adapter selon votre système d'auth)
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // Pour les tests, on peut commenter cette ligne
    // die('Accès non autorisé');
}

require_once __DIR__ . '/CacheManager.php';

$cacheManager = getInfographieCacheManager();
$action = $_GET['action'] ?? 'stats';
$message = '';

// Traitement des actions
switch ($action) {
    case 'cleanup':
        $type = $_GET['type'] ?? null;
        $cleaned = $cacheManager->cleanupExpiredCache($type);
        $message = "✅ $cleaned fichiers de cache expirés supprimés";
        break;
        
    case 'purge':
        $type = $_GET['type'] ?? null;
        $purged = $cacheManager->purgeCache($type);
        $message = "🗑️ $purged fichiers de cache supprimés";
        break;
        
    case 'stats':
    default:
        // Affichage des statistiques
        break;
}

$stats = $cacheManager->getCacheStats();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration des Caches - Infographie</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            padding: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        .folder-details {
            margin-top: 20px;
        }
        .folder-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        .folder-header {
            background: #e9ecef;
            padding: 15px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .folder-content {
            padding: 15px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }
        .actions {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9em;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .message {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .progress-bar {
            background: #e9ecef;
            border-radius: 4px;
            height: 8px;
            margin-top: 5px;
            overflow: hidden;
        }
        .progress-fill {
            background: #007bff;
            height: 100%;
            transition: width 0.3s ease;
        }
        .expired {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗂️ Administration des Caches d'Infographie</h1>
            <p>Gestion centralisée des caches organisés par catégorie</p>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <!-- Statistiques globales -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total des fichiers</h3>
                    <div class="stat-value"><?= $stats['total_files'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>Taille totale</h3>
                    <div class="stat-value"><?= $stats['total_size_mb'] ?> MB</div>
                </div>
                <div class="stat-card">
                    <h3>Fichiers expirés</h3>
                    <div class="stat-value expired"><?= $stats['expired_files'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>Catégories</h3>
                    <div class="stat-value"><?= count($stats['folders']) ?></div>
                </div>
            </div>
            
            <!-- Actions globales -->
            <div class="actions">
                <a href="?action=stats" class="btn btn-primary">🔄 Actualiser</a>
                <a href="?action=cleanup" class="btn btn-warning">🧹 Nettoyer les expirés</a>
                <a href="?action=purge" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer TOUS les caches ?')">🗑️ Purger tout</a>
            </div>
            
            <!-- Détails par dossier -->
            <div class="folder-details">
                <h2>Détails par catégorie</h2>
                
                <?php foreach ($stats['folders'] as $type => $folderStats): ?>
                    <div class="folder-card">
                        <div class="folder-header">
                            <span>📁 <?= ucfirst($type) ?></span>
                            <div>
                                <a href="?action=cleanup&type=<?= $type ?>" class="btn btn-warning">Nettoyer</a>
                                <a href="?action=purge&type=<?= $type ?>" class="btn btn-danger" onclick="return confirm('Supprimer tous les caches de <?= $type ?> ?')">Purger</a>
                            </div>
                        </div>
                        <div class="folder-content">
                            <div>
                                <strong>Fichiers:</strong><br>
                                <?= $folderStats['files'] ?>
                                <?php if ($folderStats['expired'] > 0): ?>
                                    <br><span class="expired">(<?= $folderStats['expired'] ?> expirés)</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <strong>Taille:</strong><br>
                                <?= $folderStats['size_mb'] ?> MB
                            </div>
                            <div>
                                <strong>Utilisation:</strong><br>
                                <?php 
                                $usage = $stats['total_size_mb'] > 0 ? ($folderStats['size_mb'] / $stats['total_size_mb']) * 100 : 0;
                                echo round($usage, 1) . '%';
                                ?>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= min($usage, 100) ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Informations techniques -->
            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                <h3>Structure des dossiers</h3>
                <p>Les caches sont maintenant organisés de la façon suivante :</p>
                <ul>
                    <li><code>cache/departements/</code> - Caches des données par départements</li>
                    <li><code>cache/regions/</code> - Caches des données par régions</li>
                    <li><code>cache/pays/</code> - Caches des données par pays</li>
                    <li><code>cache/indicateurs_cles/</code> - Caches des indicateurs clés</li>
                    <li><code>cache/periodes/</code> - Caches des périodes</li>
                </ul>
                
                <h4>Format des noms de fichiers :</h4>
                <p><code>[catégorie]_[zone]_[année]_[période]_[limit].json</code></p>
                <p>Exemple : <code>excursionnistes_cantal_2024_hiver_limit15.json</code></p>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh toutes les 30 secondes si on est sur la page stats
        <?php if ($action === 'stats'): ?>
        setTimeout(() => {
            window.location.reload();
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html> 