<?php
$current_path = $_SERVER['REQUEST_URI'] ?? '';
$path_without_query = parse_url($current_path, PHP_URL_PATH) ?? '';

function isActive($route) {
    global $path_without_query;
    return $path_without_query === $route ? 'active' : '';
}

function isActiveStartsWith($route) {
    global $path_without_query;
    return ($path_without_query && strpos($path_without_query, $route) === 0) ? 'active' : '';
}
?>

<nav class="main-navbar">
    <div class="navbar-brand">
        <a href="<?= url('/') ?>" class="brand-link">
            <div class="brand-logo">
                <img src="<?= asset('/static/images/logo/cantal_destination_ blanc_transparent.png') ?>" alt="Cantal Destination" style="height:28px; display:block;">
            </div>
        </a>
    </div>
    
    <div class="navbar-menu">
        <a href="<?= url('/') ?>" class="nav-item <?= isActive('/') ?>">
            <i class="fas fa-home"></i>
            <span>Accueil</span>
        </a>
        
        <div class="nav-item dropdown <?= isActiveStartsWith('/tdb_comparaison') || isActiveStartsWith('/tables') ?>">
            <span class="nav-trigger">
                <i class="fas fa-analytics"></i>
                <span>Analyses</span>
                <i class="fas fa-chevron-down"></i>
            </span>
            <div class="dropdown-menu">

                <a href="<?= url('/tdb_comparaison') ?>" class="dropdown-item">
                    <i class="fas fa-chart-bar"></i>
                    <div>
                        <span class="item-title">Comparaison Avancée</span>
                        <span class="item-desc">Analyse comparative par période et zone</span>
                    </div>
                </a>
                <a href="<?= url('/tables') ?>" class="dropdown-item">
                    <i class="fas fa-table"></i>
                    <div>
                        <span class="item-title">Tables d'Indicateurs</span>
                        <span class="item-desc">Données détaillées</span>
                    </div>
                </a>
                <a href="<?= url('/infographie') ?>" class="dropdown-item">
                    <i class="fas fa-chart-simple"></i>
                    <div>
                        <span class="item-title">Infographie</span>
                        <span class="item-desc">Synthèse visuelle</span>
                    </div>
                </a>
            </div>
        </div>
        
        <div class="nav-item dropdown <?= isActiveStartsWith('/fiches') || isActiveStartsWith('/methodologie') ?>">
            <span class="nav-trigger">
                <i class="fas fa-book"></i>
                <span>Documentation</span>
                <i class="fas fa-chevron-down"></i>
            </span>
            <div class="dropdown-menu">
                <a href="<?= url('/methodologie') ?>" class="dropdown-item">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <span class="item-title">Méthodologie</span>
                        <span class="item-desc">Méthodologie technique FluxVision</span>
                    </div>
                </a>
                <a href="<?= url('/fiches') ?>" class="dropdown-item">
                    <i class="fas fa-file-alt"></i>
                    <div>
                        <span class="item-title">Fiches Techniques</span>
                        <span class="item-desc">Guides d'utilisation</span>
                    </div>
                </a>
            </div>
        </div>
        
        <a href="<?= url('/help') ?>" class="nav-item <?= isActive('/help') ?>">
            <i class="fas fa-life-ring"></i>
            <span>Support</span>
        </a>
        
        <?php if (Auth::isAuthenticated()): ?>
        <?php if (Auth::isAdmin()): ?>
        <a href="<?= url('/shared-spaces') ?>" class="nav-item <?= isActive('/shared-spaces') ?>">
            <i class="fas fa-users"></i>
            <span>Espaces Partagés</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php if (Auth::isAuthenticated() && Auth::getUser()['role'] === 'admin'): ?>
        <a href="<?= url('/admin') ?>" class="nav-item <?= isActive('/admin') ?>">
            <i class="fas fa-users-cog"></i>
            <span>Administration</span>
        </a>
        <?php endif; ?>
    </div>
    
    <div class="navbar-actions">
        <?php if (Auth::isAuthenticated()): ?>
            <?php $user = Auth::getUser(); ?>
            <div class="nav-item dropdown user-menu">
                <span class="nav-trigger user-trigger">
                    <div class="user-avatar">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                    <i class="fas fa-chevron-down"></i>
                </span>
                <div class="dropdown-menu user-dropdown">
                    <div class="user-info">
                        <div class="user-avatar-large">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="user-name-large"><?= htmlspecialchars($user['name']) ?></div>
                            <div class="user-role"><?= ucfirst(htmlspecialchars($user['role'])) ?></div>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <?php if ($user['role'] === 'admin'): ?>
                    <a href="<?= url('/admin') ?>" class="dropdown-item">
                        <i class="fas fa-users-cog"></i>
                        <span>Administration</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <?php endif; ?>
                    <a href="<?= url('/logout') ?>" class="dropdown-item logout-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <a href="<?= url('/login') ?>" class="nav-item login-button">
                <i class="fas fa-sign-in-alt"></i>
                <span>Connexion</span>
            </a>
        <?php endif; ?>
    </div>
</nav> 