<?php
/**
 * Administration Unifiée des Caches FluxVision
 * Gestion des caches d'infographie et du tableau de bord
 */

require_once dirname(__DIR__) . '/infographie/CacheManager.php';

// Actions possibles
$action = $_GET['action'] ?? 'stats';
$category = $_GET['category'] ?? null;

$cacheManager = new CantalDestinationCacheManager();

function formatSize($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    
    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }
    
    return round($size, 2) . ' ' . $units[$unitIndex];
}

function formatDuration($seconds) {
    if ($seconds < 60) return $seconds . 's';
    if ($seconds < 3600) return round($seconds / 60) . 'min';
    return round($seconds / 3600, 1) . 'h';
}

// Interface Web simple
if (php_sapi_name() !== 'cli') {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Administration Cache FluxVision</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; }
            .card { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-align: center; margin: -20px -20px 20px -20px; padding: 30px; border-radius: 8px 8px 0 0; }
            .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
            .stat-card { background: #f8f9fa; padding: 15px; border-radius: 6px; text-align: center; border-left: 4px solid #007bff; }
            .stat-value { font-size: 24px; font-weight: bold; color: #007bff; }
            .category-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            .category-table th, .category-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
            .category-table th { background: #f8f9fa; font-weight: bold; }
            .category-table tr:hover { background: #f5f5f5; }
            .btn { padding: 8px 16px; margin: 2px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 14px; }
            .btn-primary { background: #007bff; color: white; }
            .btn-danger { background: #dc3545; color: white; }
            .btn-warning { background: #ffc107; color: #212529; }
            .btn-success { background: #28a745; color: white; }
            .btn:hover { opacity: 0.8; }
            .actions { text-align: center; margin: 20px 0; }
            .alert { padding: 15px; margin: 10px 0; border-radius: 4px; }
            .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
            .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
            .category-type { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
            .type-infographie { background: #e3f2fd; color: #1976d2; }
            .type-tableau-bord { background: #f3e5f5; color: #7b1fa2; }
            .expired { color: #dc3545; font-weight: bold; }
            .fresh { color: #28a745; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="header">
                    <h1>🗄️ Administration Cache FluxVision</h1>
                    <p>Gestion unifiée des caches d'infographie et du tableau de bord</p>
                </div>

                <div class="actions">
                    <a href="?action=stats" class="btn btn-primary">📊 Statistiques</a>
                    <a href="?action=cleanup" class="btn btn-warning">🧹 Nettoyer Expirés</a>
                    <a href="?action=clear_all" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer TOUS les caches ?')">🗑️ Vider Tout</a>
                    <a href="cleanup_old_tdb_cache.php" class="btn btn-warning">🔄 Nettoyer Anciens Caches</a>
                </div>

    <?php
}

// Traitement des actions
switch ($action) {
    case 'cleanup':
        $cleaned = $cacheManager->cleanup();
        if (php_sapi_name() !== 'cli') {
            echo "<div class='alert alert-success'>✅ $cleaned fichiers expirés supprimés</div>";
        } else {
            echo "✅ $cleaned fichiers expirés supprimés\n";
        }
        break;
        
    case 'clear_all':
        $deleted = $cacheManager->invalidate();
        if (php_sapi_name() !== 'cli') {
            echo "<div class='alert alert-warning'>⚠️ $deleted fichiers supprimés - Tous les caches ont été vidés</div>";
        } else {
            echo "⚠️ $deleted fichiers supprimés - Tous les caches ont été vidés\n";
        }
        break;
        
    case 'clear_category':
        if ($category) {
            $deleted = $cacheManager->invalidate($category);
            if (php_sapi_name() !== 'cli') {
                echo "<div class='alert alert-info'>ℹ️ $deleted fichiers supprimés pour la catégorie '$category'</div>";
            } else {
                echo "ℹ️ $deleted fichiers supprimés pour la catégorie '$category'\n";
            }
        }
        break;
}

// Afficher les statistiques
$stats = $cacheManager->getStats();

if (php_sapi_name() !== 'cli') {
    ?>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $stats['total_files'] ?></div>
            <div>Fichiers de cache</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= formatSize($stats['total_size']) ?></div>
            <div>Taille totale</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count($stats['categories']) ?></div>
            <div>Catégories actives</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= array_sum(array_column($stats['categories'], 'expired')) ?></div>
            <div>Fichiers expirés</div>
        </div>
    </div>

    <h2>📋 Détails par Catégorie</h2>
    <table class="category-table">
        <thead>
            <tr>
                <th>Catégorie</th>
                <th>Type</th>
                <th>Fichiers</th>
                <th>Taille</th>
                <th>Expirés</th>
                <th>TTL</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stats['categories'] as $categoryName => $categoryStats): ?>
                <?php if ($categoryStats['files'] > 0): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($categoryName) ?></strong></td>
                    <td>
                        <?php if (strpos($categoryName, 'infographie') !== false): ?>
                            <span class="category-type type-infographie">Infographie</span>
                        <?php else: ?>
                            <span class="category-type type-tableau-bord">Tableau de Bord</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $categoryStats['files'] ?></td>
                    <td><?= formatSize($categoryStats['size']) ?></td>
                    <td class="<?= $categoryStats['expired'] > 0 ? 'expired' : 'fresh' ?>">
                        <?= $categoryStats['expired'] ?>
                    </td>
                    <td><?= formatDuration($categoryStats['ttl']) ?></td>
                    <td>
                        <a href="?action=clear_category&category=<?= urlencode($categoryName) ?>" 
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Vider le cache de <?= htmlspecialchars($categoryName) ?> ?')">
                           🗑️ Vider
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (array_sum(array_column($stats['categories'], 'expired')) > 0): ?>
    <div class="alert alert-warning">
        ⚠️ Des fichiers de cache sont expirés. Utilisez le bouton "Nettoyer Expirés" pour les supprimer.
    </div>
    <?php endif; ?>

            </div>
        </div>
    </body>
    </html>
    <?php
} else {
    // Mode CLI
    echo "\n🗄️ STATISTIQUES DU CACHE FLUXVISION\n";
    echo str_repeat("=", 50) . "\n";
    echo "📊 Total fichiers: {$stats['total_files']}\n";
    echo "💾 Taille totale: " . formatSize($stats['total_size']) . "\n";
    echo "📂 Catégories actives: " . count($stats['categories']) . "\n";
    echo "⏰ Fichiers expirés: " . array_sum(array_column($stats['categories'], 'expired')) . "\n\n";
    
    echo "DÉTAILS PAR CATÉGORIE:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-30s %8s %10s %8s %6s\n", "CATÉGORIE", "FICHIERS", "TAILLE", "EXPIRÉS", "TTL");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($stats['categories'] as $categoryName => $categoryStats) {
        if ($categoryStats['files'] > 0) {
            printf("%-30s %8d %10s %8d %6s\n",
                substr($categoryName, 0, 30),
                $categoryStats['files'],
                formatSize($categoryStats['size']),
                $categoryStats['expired'],
                formatDuration($categoryStats['ttl'])
            );
        }
    }
    echo "\n";
}
?> 
