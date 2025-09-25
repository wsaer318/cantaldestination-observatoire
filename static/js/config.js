/**
 * Configuration FluxVision - Gestion des URLs selon l'environnement
 * Version améliorée avec détection automatique et configuration centralisée
 */

// Détection de l'environnement (fallback si la config PHP n'est pas disponible)
function isProduction() {
    // Si la configuration PHP est disponible, l'utiliser
    if (window.CANTALDESTINATION_ENV) {
        return window.CANTALDESTINATION_ENV.isProduction;
    }
    
    // Fallback: détection automatique
    const host = window.location.hostname;
    const href = window.location.href;
    
    // Si on est en localhost ou 127.0.0.1, c'est forcément local
    if (host === 'localhost' || host === '127.0.0.1' || host.startsWith('192.168.')) {
        return false;
    }
    
    // Sinon, vérifier le domaine
    return host.includes('cantal-destination.com') || 
           host.includes('observatoire.cantal-destination.com');
}

// Détection automatique du chemin de base
function getBasePath() {
    // Si la configuration PHP est disponible, l'utiliser
    if (window.CANTALDESTINATION_ENV && window.CANTALDESTINATION_ENV.basePath) {
        return window.CANTALDESTINATION_ENV.basePath;
    }
    
    // Fallback: détection automatique du chemin
    const pathname = window.location.pathname;
    const pathParts = pathname.split('/').filter(part => part);
    
    // Si on a des parties de chemin, reconstruire le chemin de base
    if (pathParts.length > 1) {
        // Prendre tout sauf le dernier élément (qui est souvent le fichier)
        return '/' + pathParts.slice(0, -1).join('/');
    }
    
    return isProduction() ? '' : '';
}

// Configuration des chemins selon l'environnement
const CantalDestinationConfig = {
    // Environnement actuel
    environment: isProduction() ? 'production' : 'local',
    
    // Base path selon l'environnement (détection automatique)
    basePath: getBasePath(),
    
    // Génération des URLs
    url: function(path) {
        return this.basePath + path;
    },
    
    // URL pour les API
    apiUrl: function(endpoint) {
        return this.url('/api/' + endpoint);
    },
    
    // URL pour les assets
    assetUrl: function(path) {
        return this.url('/static' + path);
    },
    
    // Vérification si on est en production
    isProduction: function() {
        return isProduction();
    },
    
    // Vérification si on est en local
    isLocal: function() {
        return !isProduction();
    }
};

// Fonctions utilitaires globales pour la compatibilité
window.asset = function(path) {
    return CantalDestinationConfig.assetUrl(path);
};

window.url = function(path) {
    return CantalDestinationConfig.url(path);
};

// Fonction globale getApiUrl pour tous les appels API
window.getApiUrl = function(endpoint) {
    return CantalDestinationConfig.apiUrl(endpoint);
};

// Configuration des périodes - Système Hybride
const PeriodConfig = {
    // Périodes disponibles
    periods: [
        { key: 'annee', label: 'Année', type: 'year' },
        { key: 'week-end_de_paques', label: 'Week-end de Pâques', type: 'holiday' },
        { key: 'vacances_d_hiver', label: 'Vacances d\'hiver', type: 'holiday' },
        { key: 'vacances_d_ete', label: 'Vacances d\'été', type: 'holiday' },
        { key: 'vacances_de_toussaint', label: 'Vacances de Toussaint', type: 'holiday' },
        { key: 'vacances_de_noel', label: 'Vacances de Noël', type: 'holiday' }
    ],
    
    // Années disponibles
    years: [2020, 2021, 2022, 2023, 2024, 2025],
    
    // Zones disponibles
    zones: [
        { key: 'CANTAL', label: 'Cantal', type: 'department' },
        { key: 'AURILLAC', label: 'Aurillac', type: 'city' },
        { key: 'MAURIAC', label: 'Mauriac', type: 'city' },
        { key: 'SAINT_FLOUR', label: 'Saint-Flour', type: 'city' }
    ],
    
    // Fonction pour obtenir la période par clé
    getPeriod: function(key) {
        return this.periods.find(p => p.key === key);
    },
    
    // Fonction pour obtenir la zone par clé
    getZone: function(key) {
        return this.zones.find(z => z.key === key);
    }
};

// Configuration des tableaux de bord
const DashboardConfig = {
    types: [
        { key: 'General', label: 'Général', icon: 'fas fa-chart-line' },
        { key: 'Excursionnistes', label: 'Excursionnistes', icon: 'fas fa-hiking' },
        { key: 'Comparaison', label: 'Comparaison', icon: 'fas fa-balance-scale' },
        { key: 'Infographie', label: 'Infographie', icon: 'fas fa-chart-pie' }
    ],
    
    getType: function(key) {
        return this.types.find(t => t.key === key);
    }
};

// Configuration des espaces partag�s
const GlobalEnv = window.CANTALDESTINATION_ENV || {};
const SharedSpacesConfig = {
    baseUrl: window.location.origin + (GlobalEnv.basePath || CantalDestinationConfig.basePath),
    csrfToken: GlobalEnv.csrfToken || null,

    // Initialisation
    init: function() {
        // Le token CSRF est déjà défini depuis la configuration globale
        // Log de configuration pour le debug
        if (CantalDestinationConfig.isLocal()) {
            console.log('🔧 SharedSpacesConfig initialisé:', {
                baseUrl: this.baseUrl,
                csrfToken: this.csrfToken ? 'Présent' : 'Manquant'
            });
        }
    }
};

// Initialisation automatique
document.addEventListener('DOMContentLoaded', function() {
    SharedSpacesConfig.init();
    

});

// Export pour les modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        CantalDestinationConfig,
        PeriodConfig,
        DashboardConfig,
        SharedSpacesConfig
    };
}
