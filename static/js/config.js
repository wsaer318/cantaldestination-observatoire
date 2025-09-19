/**
 * Configuration FluxVision - Gestion des URLs selon l'environnement
 */

// Détection de l'environnement
function isProduction() {
    const host = window.location.hostname;
    const href = window.location.href;
    
    // Détection plus précise
    // Si on est en localhost ou 127.0.0.1, c'est forcément local
    if (host === 'localhost' || host === '127.0.0.1' || host.startsWith('192.168.')) {
        return false;
    }
    
    // Si l'URL contient fluxvision_fin, c'est probablement local
    if (href.includes('/fluxvision_fin/')) {
        return false;
    }
    
    // Sinon, vérifier le domaine
    return host.includes('cantal-destination.com') || 
           host.includes('observatoire.cantal-destination.com');
}

// Configuration des chemins selon l'environnement
const CantalDestinationConfig = {
    // Environnement actuel
    environment: isProduction() ? 'production' : 'local',
    
    // Base path selon l'environnement
    basePath: isProduction() ? '' : '/fluxvision_fin',
    
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
};

// Configuration des périodes - Système Hybride
const PeriodConfig = {
    // Les 4 saisons utilisateur (interface simple)
    seasons: {
        'printemps': {
            name: 'Printemps',
            months: [3, 4, 5],
            icon: '🌸',
            color: '#4ade80'
        },
        'ete': {
            name: 'Été', 
            months: [6, 7, 8],
            icon: '☀️',
            color: '#f59e0b'
        },
        'automne': {
            name: 'Automne',
            months: [9, 10, 11], 
            icon: '🍂',
            color: '#ea580c'
        },
        'hiver': {
            name: 'Hiver',
            months: [12, 1, 2],
            icon: '❄️', 
            color: '#3b82f6'
        }
    },
    
    // Contextes d'utilisation
    contexts: {
        USER: 'user',         // Interface simple (4 saisons)
        BUSINESS: 'business', // Périodes métier précises
        HYBRID: 'hybrid',     // Les deux (menus experts)
        AUTO: 'auto'          // Détection automatique
    },
    
    // Détection intelligente de la saison actuelle
    getCurrentSeason: function() {
        const month = new Date().getMonth() + 1;
        
        for (const [key, season] of Object.entries(this.seasons)) {
            if (season.months.includes(month)) {
                return key;
            }
        }
        return 'hiver'; // Fallback
    },
    
    // Formatage d'affichage des périodes
    formatPeriodDisplay: function(periode, context = 'user') {
        if (context === 'user' && this.seasons[periode]) {
            return `${this.seasons[periode].icon} ${this.seasons[periode].name}`;
        }
        
        // Pour les périodes métier, capitaliser simplement
        return periode.charAt(0).toUpperCase() + periode.slice(1).replace(/_/g, ' ');
    },
    
    // Validation des paramètres de période
    isValidPeriod: function(periode, context = 'auto') {
        if (context === 'user' || context === 'auto') {
            return Object.keys(this.seasons).includes(periode);
        }
        
        // Pour le contexte business, on fait confiance au backend
        return periode && periode.length > 0;
    }
};

// API Helper pour les périodes
const PeriodAPI = {
    // Appel API intelligent avec mapping automatique
    callWithPeriod: function(endpoint, periode, annee, zone, context = 'auto', additionalParams = {}) {
        const params = new URLSearchParams({
            periode: periode,
            annee: annee,
            zone: zone,
            context: context,
            ...additionalParams
        });
        
        return fetch(`${CantalDestinationConfig.apiUrl(endpoint)}?${params}`);
    },
    
    // Récupération des options de périodes disponibles
    getAvailableOptions: async function(annee, context = 'user') {
        try {
            const response = await fetch(`${CantalDestinationConfig.apiUrl('period_options.php')}?annee=${annee}&context=${context}`);
            return await response.json();
        } catch (error) {
            console.error('Erreur chargement options périodes:', error);
            
            // Fallback : retourner les 4 saisons
            const seasons = {};
            Object.entries(PeriodConfig.seasons).forEach(([key, season]) => {
                seasons[key] = { name: season.name, type: 'season' };
            });
            
            return { seasons };
        }
    },
    
    // Informations sur la période actuelle (pour dashboard)
    getCurrentPeriodInfo: async function() {
        try {
            const response = await fetch(`${CantalDestinationConfig.apiUrl('current_period_info.php')}`);
            return await response.json();
        } catch (error) {
            console.error('Erreur informations période actuelle:', error);
            
            // Fallback local
            const currentSeason = PeriodConfig.getCurrentSeason();
            const currentYear = new Date().getFullYear();
            
            return {
                current_season: currentSeason,
                current_year: currentYear,
                display_name: `${PeriodConfig.seasons[currentSeason].name} ${currentYear}`,
                fallback: true
            };
        }
    }
};

// Fonctions globales pour la compatibilité
window.getApiUrl = function(endpoint) {
    return CantalDestinationConfig.apiUrl(endpoint);
};

// Nouvelles fonctions globales pour les périodes
window.PeriodConfig = PeriodConfig;
window.PeriodAPI = PeriodAPI;
