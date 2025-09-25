# Gestion des URLs - Système Centralisé

## Problème résolu

Ce système évite les URLs hardcodées dans le code et s'adapte automatiquement aux changements de répertoire de l'application.

## Architecture

### 1. Configuration PHP centralisée (`config/app.php`)

```php
// Détection automatique du chemin de base
function getBasePath() {
    // Détection automatique basée sur $_SERVER['SCRIPT_NAME']
    // S'adapte automatiquement aux changements de répertoire
}

// Fonctions utilitaires
function asset($path) {
    return getBasePath() . $path;
}

function url($path) {
    return asset($path);
}
```

### 2. Configuration JavaScript dynamique (`templates/config_js.php`)

```javascript
// Configuration injectée par PHP
window.CANTALDESTINATION_ENV = {
    environment: 'local|production',
    basePath: '/detected/path',
    isProduction: true|false
};

// Configuration centralisée
window.CantalDestinationConfig = {
    basePath: window.CANTALDESTINATION_ENV.basePath,
    url: function(path) { return this.basePath + path; }
};
```

### 3. Routeur adaptatif (`index.php`)

```php
// Détection automatique du préfixe
$basePath = getBasePath();
if (!empty($basePath) && strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}
```

## Utilisation

### En PHP
```php
// ✅ Bon - utilise les fonctions centralisées
echo asset('/static/css/style.css');
echo url('/login');

// ❌ Mauvais - URLs hardcodées
echo '/fluxvision_fin/static/css/style.css';
```

### En JavaScript
```javascript
// ✅ Bon - utilise la configuration centralisée
fetch(CantalDestinationConfig.url('/api/data'));
window.location.href = CantalDestinationConfig.url('/login');

// ❌ Mauvais - URLs hardcodées
fetch('/fluxvision_fin/api/data');
```

## Avantages

1. **Adaptabilité automatique** : S'adapte aux changements de répertoire sans modification du code
2. **Centralisation** : Une seule source de vérité pour la configuration des chemins
3. **Maintenabilité** : Plus besoin de chercher et remplacer les URLs dans tout le code
4. **Sécurité** : Évite les erreurs d'URLs qui pourraient exposer des chemins incorrects

## Migration

Pour migrer du système ancien vers le nouveau :

1. **Remplacer les URLs hardcodées** par les fonctions centralisées
2. **Utiliser les templates de base** (`_base.php`) pour l'inclusion automatique de la configuration
3. **Tester** que toutes les URLs fonctionnent correctement

## Exemple de migration

### Avant (problématique)
```php
// URLs hardcodées
echo '<a href="/fluxvision_fin/login">Login</a>';
echo '<link rel="stylesheet" href="/fluxvision_fin/static/css/style.css">';
```

### Après (solution)
```php
// URLs dynamiques
echo '<a href="' . url('/login') . '">Login</a>';
echo '<link rel="stylesheet" href="' . asset('/static/css/style.css') . '">';
```

## Tests

Pour vérifier que le système fonctionne :

1. **Changer le répertoire** de l'application
2. **Vérifier** que toutes les URLs se génèrent correctement
3. **Tester** la navigation et les appels API
4. **Vérifier** que les assets se chargent correctement

## Maintenance

- **Ajouter de nouvelles URLs** : Utiliser les fonctions `url()` et `asset()`
- **Modifier la configuration** : Éditer `config/app.php` et `templates/config_js.php`
- **Déboguer** : Vérifier les logs de la console JavaScript en mode local
