// Auto-generated on 2025-09-21T08:32:17+00:00 -- Do not edit manually
// ---- begin static/js/utils.js ----

// Fonctions utilitaires partagées entre les fichiers JavaScript

// Centralised debug logging utility (disabled by default)
if (typeof window !== 'undefined') {
  window.FV_DEBUG_LOGS = Boolean(window.FV_DEBUG_LOGS);
  const fvConsole = window.console || {};
  window.fvLog = function(...args) {
    if (window.FV_DEBUG_LOGS && typeof fvConsole.log === 'function') {
      fvConsole.log(...args);
    }
  };
  window.fvInfo = function(...args) {
    if (window.FV_DEBUG_LOGS && typeof fvConsole.info === 'function') {
      fvConsole.info(...args);
    }
  };
  window.fvWarn = function(...args) {
    if (window.FV_DEBUG_LOGS && typeof fvConsole.warn === 'function') {
      fvConsole.warn(...args);
    }
  };
}

// Configuration par défaut des particules
const particlesConfig = {
  fullScreen: { enable: true, zIndex: 2 },
  background: { color: { value: "transparent" } },
  fpsLimit: 60,
  particles: {
    number: { value: 15, density: { enable: true, area: 400 } },
    color: { 
      value: ["#F1C40F", "#1ABC9C", "#9B59B6", "#3498DB", "#E74C3C", "#F9E79F", "#58D68D", "#BB8FCE"] 
    },
    shape: { type: "circle" },
    opacity: {
      value: { min: 0, max: 0.6 },
      random: { enable: true, minimumValue: 0 },
      animation: { enable: true, startValue: "min", speed: 1, sync: false }
    },
    size: { value: { min: 0.5, max: 3 }, random: true },
    move: { 
      enable: true, 
      speed: { min: 0.25, max: 1 }, 
      direction: "none", 
      random: true, 
      straight: false, 
      outModes: { default: "out" } 
    },
    position: { x: { min: 0, max: 100 }, y: { min: 0, max: 100 } },
    links: {
      enable: true,
      distance: 120,
      color: "random",
      opacity: 0.3,
      width: 1
    },
    twinkle: {
      particles: {
        enable: true,
        frequency: 0.05,
        opacity: 1
      }
    }
  },
  detectRetina: true,
  interactivity: {
    events: {
      onHover: {
        enable: true,
        mode: "grab"
      },
      onClick: {
        enable: false,
        mode: "push"
      }
    },
    modes: {
      grab: {
        distance: 140,
        links: {
          opacity: 0.5,
          color: "#ffffff"
        }
      },
      push: {
        quantity: 3
      }
    }
  }
};

// Fonction pour initialiser les particules
function initParticles(elementId = "tsparticles", config = {}) {
  // Fusionner la configuration par défaut avec les options personnalisées
  const mergedConfig = { ...particlesConfig, ...config };
  return tsParticles.load(elementId, mergedConfig);
}

// Fonction pour animer l'entrée des éléments
function animateEntrance() {
  const sidebar = document.querySelector('.sidebar');
  const content = document.querySelector('.content');
  const header = document.querySelector('header');
  const title = document.querySelector('.header-title');
  const subtitle = document.querySelector('.header-subtitle');
  const badges = document.querySelectorAll('.destination-badge');
  
  if (sidebar) {
    sidebar.style.opacity = '0';
    sidebar.style.transform = 'translateX(-40px)';
  }
  
  if (content) {
    content.style.opacity = '0';
    content.style.transform = 'translateX(40px)';
  }
  
  if (header) {
    header.style.opacity = '0';
    header.style.transform = 'translateY(-20px)';
  }
  
  if (title) title.style.opacity = '0';
  if (subtitle) subtitle.style.opacity = '0';
  
  if (badges) {
    badges.forEach(badge => {
      badge.style.opacity = '0';
      badge.style.transform = 'translateY(20px)';
    });
  }
  
  // Animation séquentielle avec des délais différents
  setTimeout(() => {
    if (sidebar) {
      sidebar.style.transition = 'all 0.8s cubic-bezier(0.19, 1, 0.22, 1)';
      sidebar.style.opacity = '1';
      sidebar.style.transform = 'translateX(0)';
    }
    
    if (header) {
      setTimeout(() => {
        header.style.transition = 'all 0.7s cubic-bezier(0.19, 1, 0.22, 1)';
        header.style.opacity = '1';
        header.style.transform = 'translateY(0)';
        
        setTimeout(() => {
          if (title) {
            title.style.transition = 'all 0.6s cubic-bezier(0.19, 1, 0.22, 1)';
            title.style.opacity = '1';
          }
          
          setTimeout(() => {
            if (subtitle) {
              subtitle.style.transition = 'all 0.6s cubic-bezier(0.19, 1, 0.22, 1)';
              subtitle.style.opacity = '1';
            }
            
            // Animer les badges un par un
            if (badges) {
              badges.forEach((badge, index) => {
                setTimeout(() => {
                  badge.style.transition = 'all 0.5s cubic-bezier(0.19, 1, 0.22, 1)';
                  badge.style.opacity = '1';
                  badge.style.transform = 'translateY(0)';
                }, index * 150);
              });
            }
          }, 200);
        }, 200);
      }, 200);
    }
    
    if (content) {
      setTimeout(() => {
        content.style.transition = 'all 0.7s cubic-bezier(0.19, 1, 0.22, 1)';
        content.style.opacity = '1';
        content.style.transform = 'translateX(0)';
      }, 300);
    }
    
  }, 100);
  
  // Animer les formes géométriques
  const shapes = document.querySelectorAll('.geometric-shape, .accent-shape');
  if (shapes) {
    shapes.forEach((shape, index) => {
      shape.style.opacity = '0';
      shape.style.transform = 'scale(0.5)';
      
      setTimeout(() => {
        shape.style.transition = 'all 0.8s cubic-bezier(0.34, 1.56, 0.64, 1)';
        shape.style.opacity = '0.1';
        shape.style.transform = 'scale(1)';
      }, 800 + index * 100);
    });
  }
}

// Fonction pour initialiser les animations basées sur le défilement
function initScrollAnimations() {
  // Créer un observateur d'intersection pour détecter quand les éléments sont visibles
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        // Appliquer une classe d'animation aléatoire
        const animations = [
          'animate-float-in',
          'animate-scale-in',
          'animate-slide-in-right',
          'animate-slide-in-left',
          'animate-rotate-in',
          'animate-bounce-in'
        ];
        const randomAnimation = animations[Math.floor(Math.random() * animations.length)];
        entry.target.classList.add(randomAnimation);
        // Rétablir opacité et transform pour que l'animation soit visible
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'none';
        // Arrêter d'observer l'élément
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });
  
  // Observer les éléments à animer
  const elementsToObserve = document.querySelectorAll('.section-content, .paragraphe, table, .remarque, ul.nested-list li, ol > li, .home-section, .feature-card, .quick-link-card, .stats-card');
  elementsToObserve.forEach(element => {
    element.style.opacity = '0';
    observer.observe(element);
  });
}

// Gestionnaire d'animations au survol
function handleHoverAnimations(e) {
  const target = e.target;
  
  // Effet de vague pour les éléments cliquables au survol
  if (target.matches('.btn, #fiches-list li, .section-title, .nav-link')) {
    const ripple = document.createElement('span');
    ripple.classList.add('hover-ripple');
    
    // Positionner la vague là où se trouve le pointeur
    const rect = target.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    ripple.style.left = `${x}px`;
    ripple.style.top = `${y}px`;
    
    target.appendChild(ripple);
    
    // Supprimer l'élément de vague après l'animation
    setTimeout(() => {
      ripple.remove();
    }, 1000);
  }
}

// Fonction pour formatter les nombres avec séparateur de milliers
function formatNumber(number) {
  if (number === undefined || number === null) return '';
  return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
}

// Exporter les fonctions pour les rendre accessibles
window.utils = {
  initParticles,
  animateEntrance,
  initScrollAnimations,
  handleHoverAnimations,
  formatNumber
};

// ---- end static/js/utils.js ----

// ---- begin static/js/config.js ----

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

// ---- end static/js/config.js ----

// ---- begin static/js/flux-vision-config.js ----

/**
 * Configuration dynamique FluxVision
 * Classe réutilisable pour charger la configuration depuis la base de données
 */

// Éviter la redéclaration si la classe existe déjà
if (typeof CantalDestinationDynamicConfig === 'undefined') {
    window.CantalDestinationDynamicConfig = class CantalDestinationDynamicConfig {
    constructor() {
        this.data = null;
        this.isLoaded = false;
        this.cache = null;
        this.cacheExpiry = 5 * 60 * 1000; // 5 minutes
    }

    async loadFromDatabase() {
        // Vérifier le cache
        if (this.cache && (Date.now() - this.cache.timestamp < this.cacheExpiry)) {
            this.data = this.cache.data;
            this.isLoaded = true;
            return;
        }

        try {
            const response = await fetch('api/filters_mysql.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            // L'API filters_mysql.php retourne directement les données
            if (result && result.annees && result.periodes && result.zones) {
                // Adapter la structure des données
                this.data = {
                    years: result.annees.map(String), // Convertir en strings
                    zones: result.zones,
                    periods: result.periodes.map(p => ({
                        code: p.value,
                        label: p.label
                    })),
                    metadata: {
                        date_ranges: {},
                        totals: {},
                        ...result.metadata
                    }
                };
                
                this.isLoaded = true;
                
                // Mettre en cache
                this.cache = {
                    data: this.data,
                    timestamp: Date.now()
                };
                
            } else {
                throw new Error('Structure de données invalide');
            }
        } catch (error) {
            console.error('❌ Erreur lors du chargement de la configuration:', error);
            this.loadFallbackConfig();
        }
    }

    loadFallbackConfig() {
        console.warn('⚠️ Utilisation de la configuration par défaut');
        this.data = {
            years: ['2024', '2023', '2022'],
            zones: ['CANTAL'],
            periods: [
                { code: 'hiver', label: 'Hiver' },
                { code: 'ete', label: 'Été' },
                { code: 'paques', label: 'Pâques' },
                { code: 'mai', label: 'Mai' },
                { code: 'printemps', label: 'Printemps' },
                { code: 'toussaint', label: 'Toussaint' },
                { code: 'noel', label: 'Noël' }
            ],
            metadata: {
                date_ranges: {},
                totals: {}
            }
        };
        this.isLoaded = true;
    }

    // Getters intelligents
    get defaultYear() {
        if (!this.isLoaded) return '2024';
        return this.data.years?.[0] || '2024';
    }

    get previousYear() {
        if (!this.isLoaded) return '2023';
        const years = this.data.years || ['2024', '2023'];
        return years[1] || String(parseInt(this.defaultYear) - 1);
    }

    get defaultZone() {
        if (!this.isLoaded) return 'CANTAL';
        return this.data.zones?.[0] || 'CANTAL';
    }

    get defaultPeriod() {
        if (!this.isLoaded) return 'hiver';
        return this.data.periods?.[0]?.code || 'hiver';
    }

    get availableYears() {
        if (!this.isLoaded) return ['2024', '2023'];
        return this.data.years || ['2024', '2023'];
    }

    get availableZones() {
        if (!this.isLoaded) return ['CANTAL'];
        return this.data.zones || ['CANTAL'];
    }

    get availablePeriods() {
        if (!this.isLoaded) return [
            { code: 'hiver', label: 'Hiver' },
            { code: 'ete', label: 'Été' }
        ];
        return this.data.periods || [
            { code: 'hiver', label: 'Hiver' },
            { code: 'ete', label: 'Été' }
        ];
    }

    // Méthodes utilitaires
    getPeriodLabel(code) {
        const period = this.availablePeriods.find(p => p.code === code);
        return period ? period.label : code;
    }

    getPeriodCode(label) {
        const period = this.availablePeriods.find(p => p.label === label);
        return period ? period.code : label;
    }

    getDateRange(year, period) {
        if (!this.isLoaded) return null;
        return this.data.metadata?.date_ranges?.[year]?.[period] || null;
    }

    getTotals(year, period, zone) {
        if (!this.isLoaded) return null;
        return this.data.metadata?.totals?.[year]?.[period]?.[zone] || null;
    }

    // Méthode pour forcer le rechargement
    async reload() {
        this.cache = null;
        this.isLoaded = false;
        await this.loadFromDatabase();
    }
    } // Fin de la classe CantalDestinationDynamicConfig
} // Fin de la condition if

// ---- end static/js/flux-vision-config.js ----

// ---- begin static/js/filters_loader.js ----

/**
 * Chargeur automatique des filtres pour le tableau de bord
 */

class FiltersLoader {
    constructor() {
        this.init();
    }

    init() {
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.loadFilters();
            });
        } else {
            this.loadFilters();
        }
    }

    async loadFilters() {
        window.fvLog('[FiltersLoader] loadFilters:start');
        try {
            // Charger les filtres depuis l'API MySQL
            const response = await fetch(window.getApiUrl('filters_mysql.php'));
            const filtersData = await response.json();
            window.fvLog('[FiltersLoader] loadFilters:ok', {
                annees: (filtersData?.annees || []).length,
                periodes: (filtersData?.periodes || []).length,
                zones: (filtersData?.zones || []).length
            });
                        
            // Utiliser les données de l'API
            this.loadYearsFromAPI(filtersData.annees);
            this.loadPeriodsFromAPI(filtersData.periodes);
            this.loadZonesFromAPI(filtersData.zones);
            
        } catch (error) {
            console.error('Erreur lors du chargement des filtres depuis l\'API:', error);
            // Fallback vers les valeurs par défaut
            this.loadYears();
            this.loadPeriods();
            this.loadZones();
        }
    }

    loadYearsFromAPI(apiYears) {
        const yearSelect = document.getElementById('exc-year-select');
        
        if (yearSelect && apiYears && apiYears.length > 0) {
            // Les années viennent déjà triées par ordre décroissant de l'API
            yearSelect.innerHTML = apiYears
                .map(year => `<option value="${year}" ${year == 2024 ? 'selected' : ''}>${year}</option>`)
                .join('');
                
        } else {
            console.error('Year select element not found or no years data!');
        }
    }

    loadYears() {
        const yearSelect = document.getElementById('exc-year-select');
        
        if (yearSelect) {
            // Générer les années de 2019 à 2025, puis trier par ordre décroissant
            const currentYear = new Date().getFullYear();
            const years = [];
            for (let year = 2019; year <= 2025; year++) {
                years.push(year);
            }
            
            // Trier par ordre décroissant (plus récent en premier)
            years.sort((a, b) => b - a);

            yearSelect.innerHTML = years
                .map(year => `<option value="${year}" ${year === 2024 ? 'selected' : ''}>${year}</option>`)
                .join('');
                
        } else {
            console.error('Year select element not found!');
        }
    }

    loadPeriodsFromAPI(apiPeriods) {
        const periodSelect = document.getElementById('exc-period-select');
        if (!periodSelect) {
            console.error('Period select element not found!');
            return;
        }
        if (!Array.isArray(apiPeriods) || apiPeriods.length === 0) {
            console.warn('[FiltersLoader] loadPeriodsFromAPI: no periods from API, using fallback');
            this.loadPeriods();
            return;
        }
        try {
            window.fvLog('[FiltersLoader] loadPeriodsFromAPI:using DB periods', apiPeriods.length);
            // Dédupliquer par code (value)
            const seen = new Set();
            const unique = [];
            for (const p of apiPeriods) {
                const code = String(p.value);
                if (!seen.has(code)) {
                    seen.add(code);
                    unique.push({ value: code, label: String(p.label || code) });
                }
            }
            // Mettre "Année complète" en premier si présent, puis le reste par ordre alpha
            const hasYear = unique.find(u => u.value === 'annee_complete');
            let ordered = unique.filter(u => u.value !== 'annee_complete').sort((a,b)=>a.label.localeCompare(b.label,'fr'));
            if (hasYear) ordered = [hasYear, ...ordered];
            const defaultValue = hasYear ? 'annee_complete' : ordered[0].value;
            periodSelect.innerHTML = ordered
                .map(period => `<option value="${period.value}" ${period.value === defaultValue ? 'selected' : ''}>${period.label}</option>`)
                .join('');
            window.fvLog('[FiltersLoader] loadPeriodsFromAPI:rendered unique', ordered.length);
        } catch (e) {
            console.error('[FiltersLoader] loadPeriodsFromAPI:error', e);
            this.loadPeriods();
        }
    }

    loadPeriods() {
        const periodSelect = document.getElementById('exc-period-select');
        if (!periodSelect) {
            console.error('Period select element not found!');
            return;
        }
        console.warn('[FiltersLoader] loadPeriods:fallback static set');
        const periods = [
            { value: 'annee_complete', label: 'Année complète' },
            { value: 'hiver', label: 'Vacances d\'hiver' },
            { value: 'paques', label: 'Week-end de Pâques' }
        ];
        const defaultValue = 'annee_complete';
        periodSelect.innerHTML = periods
            .map(period => `<option value="${period.value}" ${period.value === defaultValue ? 'selected' : ''}>${period.label}</option>`)
            .join('');
    }

    protectPeriodValues(periodSelect) {
		window.fvLog('[FiltersLoader] protectPeriodValues:disabled');
		return;
	}

    loadZonesFromAPI(apiZones) {
        const zoneSelect = document.getElementById('exc-zone-select');
        
        if (zoneSelect && apiZones && apiZones.length > 0) {
            // Créer les options depuis les données de l'API
            const zones = apiZones.map(zoneName => ({
                value: zoneName,
                label: zoneName
            }));

            // Sélectionner "CANTAL" par défaut s'il existe, sinon la première zone
            const defaultZone = zones.find(zone => zone.value === 'CANTAL') ? 'CANTAL' : zones[0].value;

            zoneSelect.innerHTML = zones
                .map(zone => `<option value="${zone.value}" ${zone.value === defaultZone ? 'selected' : ''}>${zone.label}</option>`)
                .join('');

        } else {
            console.error('Zone select element not found or no zones data!');
        }
    }

    loadZones() {
        const zoneSelect = document.getElementById('exc-zone-select');
        
        if (zoneSelect) {
            const zones = [
                { value: 'CANTAL', label: 'Cantal' }
                // Ajouter d'autres zones si disponibles
            ];

            zoneSelect.innerHTML = zones
                .map(zone => `<option value="${zone.value}" ${zone.value === 'CANTAL' ? 'selected' : ''}>${zone.label}</option>`)
                .join('');
        } else {
            console.error('Zone select element not found!');
        }
    }
}

// Initialiser le chargeur de filtres

const filtersLoader = new FiltersLoader();

// ---- end static/js/filters_loader.js ----

// ---- begin static/js/infographie.js ----

﻿/**
 * Script JavaScript pour la page d'infographie
 * Gère la génération et le téléchargement d'infographies touristiques
 */

class InfographieManager {
    constructor(config = {}) {

        // Configuration par défaut
        this.config = {
            defaultYear: new Date().getFullYear(),
            defaultPeriod: 'annee_complete',
            defaultZone: 'CANTAL',
            ...config
        };

        // État de l'application
        this.currentData = null;
        this.chartsGenerated = false;
        this.fetchCtl = null;
        this.chartInstances = {};

        // Debug discret
        const DEBUG = false;
        this.log = (...a) => { 
            if (DEBUG) try { 
                window.fvLog('[Infographie]', ...a);
            } catch {} 
        };

        this.init();
    }

    // Fonction utilitaire pour parser les dates ISO sans dÃ©calage de timezone
    parseISODate(s) {
        if (!s) return null;
        
        // Extraire la partie date (YYYY-MM-DD) même si elle est suivie d'une heure
        const dateMatch = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!dateMatch) {
            return null;
        }
        
        const [, Y, M, D] = dateMatch.map(Number);
        
        // Vérifier que les valeurs sont valides
        if (isNaN(Y) || isNaN(M) || isNaN(D) || M < 1 || M > 12 || D < 1 || D > 31) {
            return null;
        }
        
        return new Date(Y, M-1, D);
    }

    // Fonction utilitaire pour gÃ©rer les erreurs d'abandon
    handleFetchError(error) {
        // Ignorer les erreurs d'abandon
        if (error.name === 'AbortError') {
            return null;
        }
        // Erreur silencieuse pour les autres cas
        return null;
    }

    // Fonction utilitaire pour valider les dates
    isValidDate(d) {
        return d instanceof Date && !Number.isNaN(d.getTime());
    }

    // Méthode centralisée pour synchroniser les plages custom
    syncCustomRange(options = {}) {
        const { commitToUrl = false, start, end } = options;
        
        try {
            // Lire les dates depuis l'URL si pas fournies
            let customStart = start;
            let customEnd = end;
            
            if (!customStart || !customEnd) {
                const urlParams = new URLSearchParams(window.location.search);
                customStart = urlParams.get('debut');
                customEnd = urlParams.get('fin');
            }
            
            // Mettre à jour window.infographieCustomDateRange
            if (customStart && customEnd) {
                window.infographieCustomDateRange = {
                    start: customStart,
                    end: customEnd
                };
            } else {
                window.infographieCustomDateRange = null;
            }
            
            // Écrire dans l'URL si demandé
            if (commitToUrl && customStart && customEnd) {
                this.markInternalURLChange();
                const url = new URL(window.location.href);
                url.searchParams.set('debut', customStart);
                url.searchParams.set('fin', customEnd);
                window.history.replaceState(null, '', url.toString());
            }
            
            return { start: customStart, end: customEnd };
        } catch (error) {
            this.log('syncCustomRange error:', error);
            return { start: null, end: null };
        }
    }

    async init() {
        try {
            // Charger la configuration dynamique
            await this.loadConfig();
            
            // Les filtres sont maintenant gÃ©rÃ©s par filters_loader.js
            // Attendre que les filtres soient chargÃ©s
            await this.waitForFiltersLoaded();
            
            // Appliquer les paramÃ¨tres URL aux filtres
            this.applyURLParametersToFilters();

            // Initialiser le sélecteur avancé (préréglages DB + UI)
            try {
                this.initAdvancedPeriodPicker();
            } catch (error) {
                // Erreur silencieuse
            }
            
            // ✅ Ajouter un observateur pour tracer tous les changements d'URL
            this.observeURLChanges();
            
            // Initialiser les événements
            this.initializeEvents();
            
        } catch (error) {
            this.showError('Erreur lors du chargement de la page');
        }
    }

    // ✅ Nouvelle mÃ©thode pour observer les changements d'URL
    observeURLChanges() {
        let isInternalChange = false;
        
        const emit = () => {
            if (!isInternalChange) {
                window.dispatchEvent(new Event('urlchange'));
            }
        };
        
        const wrap = (k) => {
            const orig = history[k];
            history[k] = function(...args) {
                const ret = orig.apply(this, args);
                emit();
                return ret;
            };
        };
        
        wrap('pushState');
        wrap('replaceState');
        window.addEventListener('popstate', emit);
        
        // Écouter les changements d'URL
        window.addEventListener('urlchange', () => {
            if (!isInternalChange) {
                isInternalChange = true;
                try {
                    this.applyURLParametersToFilters();
                    this.generateInfographie();
                } finally {
                    isInternalChange = false;
                }
            }
        });
        
        // MÃ©thode pour marquer les changements internes
        this.markInternalURLChange = () => {
            isInternalChange = true;
            setTimeout(() => { isInternalChange = false; }, 100);
        };
    }

    // Sélecteur avancé minimal (préréglages DB + "année complète").
    // Aucun changement d'API, synchronise uniquement les selects existants.
    async updatePeriodSelectForYear(year, desiredCode = null) {
        try {
            const periodSelect = document.getElementById('exc-period-select');
            if (!periodSelect) return;
            // Récupérer les périodes depuis la configuration dynamique déjà chargée
            const cfg = this.config || (typeof CantalDestinationDynamicConfig !== 'undefined' ? new CantalDestinationDynamicConfig() : null);
            if (!cfg || !cfg.data) return;
            const all = cfg.data?.periodes || [];
            const y = Number(year);
            // Si la config n'a pas de périodes (encore), ne pas écraser les options existantes
            if (!Array.isArray(all) || all.length === 0) {
                // Assurer uniquement l'injection de l'option 'custom' si demandÃ©e
                if (desiredCode === 'custom' && !Array.from(periodSelect.options).some(o => o.value === 'custom')) {
                    const opt = document.createElement('option');
                    opt.value = 'custom';
                    opt.textContent = 'Intervalle personnalisé';
                    periodSelect.insertBefore(opt, periodSelect.firstChild);
                    periodSelect.value = 'custom';
                }
                return;
            }
            // IMPORTANT: ne pas reconstruire la liste, conserver les options crÃ©Ã©es par filters_loader.js
            const previousValue = periodSelect.value;
            const existing = Array.from(periodSelect.options).map(o => ({ value: o.value, label: o.textContent || '' }));
            const has = (v) => existing.some(o => o.value === v);
            const findByLabel = (substr) => existing.find(o => (o.label || '').toLowerCase().includes(substr));
            let selectedValue = null;
            if (desiredCode === 'custom') {
                if (!has('custom')) {
                    const opt = document.createElement('option');
                    opt.value = 'custom';
                    opt.textContent = 'Intervalle personnalisé';
                    periodSelect.insertBefore(opt, periodSelect.firstChild);
                }
                selectedValue = 'custom';
            } else if (desiredCode === 'annee_complete') {
                let target = 'annee_complete';
                if (!has(target) && has('annee')) target = 'annee';
                if (!has(target)) {
                    const byLbl = findByLabel('année');
                    if (byLbl) target = byLbl.value;
                }
                if (!has(target)) {
                    const opt = document.createElement('option');
                    opt.value = 'annee_complete';
                    opt.textContent = 'AnnÃ©e complète';
                    periodSelect.insertBefore(opt, periodSelect.firstChild);
                    target = 'annee_complete';
                }
                selectedValue = target;
            } else if (typeof desiredCode === 'string' && desiredCode) {
                if (!has(desiredCode)) {
                    const fromCfg = (all || []).find(p => p.value === desiredCode);
                    const opt = document.createElement('option');
                    opt.value = desiredCode;
                    opt.textContent = fromCfg?.label || desiredCode;
                    periodSelect.appendChild(opt);
                }
                selectedValue = desiredCode;
            }
            if (!selectedValue) selectedValue = previousValue || periodSelect.value;
            periodSelect.value = selectedValue;
            periodSelect.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (_) { /* noop */ }
    }

    async initAdvancedPeriodPicker() {

        try {
            const wrap = document.getElementById('dashboardPeriodPicker');
            if (!wrap) {
                return;
            }

            const toggle = document.getElementById('pp-toggle');
            const panel = document.getElementById('pp-panel');
            const closeBtn = document.getElementById('pp-close');
            const list = document.getElementById('pp-list');
            const yearSel = document.getElementById('pp-year-select');
            const monthSel = document.getElementById('pp-month');
            const todayBtn = document.getElementById('pp-today');
            const grid = document.getElementById('pp-grid');
            const hint = document.getElementById('pp-hint');
            const prevY = document.getElementById('pp-prev-year');
            const nextY = document.getElementById('pp-next-year');
            const prevM = document.getElementById('pp-prev-month');
            const nextM = document.getElementById('pp-next-month');
            const self = this;



            // Portail et overlay
            let portalRoot = document.getElementById('portal-root');
            if (!portalRoot) {
                portalRoot = document.createElement('div');
                portalRoot.id = 'portal-root';
                document.body.appendChild(portalRoot);
            }
            if (panel && panel.parentElement !== portalRoot) {
                portalRoot.appendChild(panel);
            }
            const overlay = (() => {
                let ov = portalRoot.querySelector('.pp-backdrop');
                if (!ov) {
                    ov = document.createElement('div');
                    ov.className = 'pp-backdrop';
                    ov.setAttribute('hidden', '');
                    portalRoot.appendChild(ov);
                }
                return ov;
            })();

            const monthsFR = [
                'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'
            ];
            const monthsShortFR = ['janv.','fÃ©vr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','dÃ©c.'];
            const state = { view: new Date(), start: null, end: null };

            const positionPanel = () => {
                if (!toggle || !panel) return;
                
                // Guard pour s'assurer que le panel est visible pour les mesures
                const wasHidden = panel.style.visibility === 'hidden' || panel.style.display === 'none';
                if (wasHidden) {
                    panel.style.visibility = 'hidden';
                    panel.style.display = 'block';
                }
                
                const r = toggle.getBoundingClientRect();
                const panelRect = panel.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;
                
                // Position initiale (en bas Ã  droite du bouton)
                let left = Math.round(r.left);
                let top = Math.round(r.bottom + 6);
                
                // VÃ©rifier si le panneau dÃ©passe Ã  droite
                if (left + panelRect.width > viewportWidth) {
                    left = Math.max(10, viewportWidth - panelRect.width - 10);
                }
                
                // VÃ©rifier si le panneau dÃ©passe Ã  gauche
                if (left < 10) {
                    left = 10;
                }
                
                // VÃ©rifier si le panneau dÃ©passe en bas
                if (top + panelRect.height > viewportHeight) {
                    // Placer le panneau au-dessus du bouton
                    top = Math.max(10, r.top - panelRect.height - 6);
                }
                
                // VÃ©rifier si le panneau dÃ©passe en haut
                if (top < 10) {
                    top = 10;
                }
                
                // Si le panneau est trop large pour l'écran, ajuster sa largeur
                if (panelRect.width > viewportWidth - 20) {
                    panel.style.maxWidth = (viewportWidth - 20) + 'px';
                    panel.style.width = 'auto';
                }
                
                panel.style.left = left + 'px';
                panel.style.top = top + 'px';
                
                // Restaurer l'état original si nÃ©cessaire
                if (wasHidden) {
                    panel.style.visibility = '';
                    panel.style.display = '';
                }
                

            };
            const openPanel = () => {
                panel.classList.add('open');
                panel.removeAttribute('aria-hidden');
                panel.removeAttribute('inert');
                overlay.removeAttribute('hidden');
                try { toggle?.setAttribute('aria-expanded','true'); } catch(_){ }
                positionPanel();
                // Synchroniser l'année du panneau avec le sÃ©lecteur standard et recharger la liste
                try {
                    const stdYear = document.getElementById('exc-year-select')?.value;
                    if (yearSel && stdYear) {
                        yearSel.value = String(stdYear);
                        loadDbPresets(String(stdYear));
                    }
                } catch(_){ }
                // Focus trap setup
                setTimeout(() => {
                    try {
                        const focusables = panel.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                        const first = focusables[0];
                        const last = focusables[focusables.length - 1];
                        const trap = function(e){
                            if (e.key === 'Tab') {
                                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last?.focus(); }
                                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first?.focus(); }
                            }
                        };
                        if (panel._ppTrap) panel.removeEventListener('keydown', panel._ppTrap);
                        panel._ppTrap = trap;
                        panel.addEventListener('keydown', trap);
                        if (first) first.focus();
                    } catch(_){}
                }, 0);
            };
            const closePanel = () => {
                panel.classList.remove('open');
                panel.setAttribute('aria-hidden', 'true');
                panel.setAttribute('inert', '');
                overlay.setAttribute('hidden', '');
                try { toggle?.setAttribute('aria-expanded','false'); } catch(_){ }
            };
            // Fermer via Escape
            document.addEventListener('keydown', (ev) => {
                if (ev.key === 'Escape' && panel.classList.contains('open')) {
                    closePanel();
                }
            });

            const buildYearOptions = () => {
                const std = document.getElementById('exc-year-select');
                if (!std) return;
                yearSel.innerHTML = Array.from(std.options)
                    .map(o => `<option value="${String(o.value)}">${String(o.textContent || o.value)}</option>`)
                    .join('');
                yearSel.value = std.value || std.options[0]?.value || String(new Date().getFullYear());
            };
            const buildMonthOptions = () => {
                monthSel.innerHTML = monthsFR
                    .map((m,i)=>`<option value="${i}">${m.charAt(0).toUpperCase()+m.slice(1)}</option>`)
                    .join('');
                monthSel.value = String(state.view.getMonth());
            };

            // Cache pour Ã©viter les appels multiples Ã  loadDbPresets
            const presetsCache = new Map();
            
            const loadDbPresets = async (year) => {
                try {
                    // VÃ©rifier le cache
                    if (presetsCache.has(year)) {
                        const cachedData = presetsCache.get(year);
                        this.renderPresetsList(cachedData.presets, cachedData.year);
                        return;
                    }
                    
                    const base = (typeof window.getApiUrl === 'function')
                        ? window.getApiUrl('get_periodes.php')
                        : 'api/get_periodes.php';
                    const url = `${base}?action=year&annee=${encodeURIComponent(year)}`;
                    const res = await fetch(url, { credentials: 'same-origin' });
                    const json = await res.json();
                    const items = Object.entries(json || {}).map(([code, info]) => ({
                        code,
                        label: String(info?.nom || code)
                    }));
                    const presets = [{ code: 'annee_complete', label: `AnnÃ©e complète ${year}` }, ...items];
                    
                    // Mettre en cache
                    presetsCache.set(year, { presets, year });
                    
                    // Rendre la liste
                    this.renderPresetsList(presets, year);
                    
                } catch (e) {
                    this.log('loadDbPresets error:', e);
                }
            };
            
            // MÃ©thode pour rendre la liste des presets
            this.renderPresetsList = (presets, year) => {
                try {
                    while (list.firstChild) list.removeChild(list.firstChild);
                    presets.forEach(p => {
                        const div = document.createElement('div');
                        div.className = 'item';
                        div.dataset.code = String(p.code);
                        div.textContent = p.label;
                        div.addEventListener('click', () => {
                            try {
                                const ySel = document.getElementById('exc-year-select');
                                const pSel = document.getElementById('exc-period-select');
                                if (ySel) ySel.value = String(year);
                                if (pSel) {
                                    if (p.code === 'annee_complete') {
                                        let opt = pSel.querySelector('option[value="annee_complete"]');
                                        if (!opt) {
                                            opt = document.createElement('option');
                                            opt.value = 'annee_complete';
                                            opt.textContent = 'AnnÃ©e complète';
                                            pSel.insertBefore(opt, pSel.firstChild);
                                        }
                                        pSel.value = 'annee_complete';
                                    } else {
                                        let opt = Array.from(pSel.options).find(o => o.value === p.code);
                                        if (!opt) {
                                            opt = document.createElement('option');
                                            opt.value = p.code;
                                            opt.textContent = p.label;
                                            pSel.appendChild(opt);
                                        }
                                        pSel.value = p.code;
                                    }
                                    pSel.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                                // Clear custom range if any and URL params
                                try {
                                    this.syncCustomRange({ commitToUrl: true });
                                } catch(_){}
                                // Active state in the list
                                Array.from(list.children).forEach(el => el.classList.toggle('active', el === div));
                                // ✅ Ne pas fermer le panneau - laisser l'utilisateur voir la sÃ©lection
                                // closePanel();
                            } catch(_){ 
                                this.log('preset click error:', _);
                            }
                        });
                        list.appendChild(div);
                    });
                    
                    // ✅ Mettre en surbrillance la pÃ©riode actuellement sÃ©lectionnÃ©e
                    highlightCurrentPeriod();
                    
                } catch (error) {
                    this.log('renderPresetsList error:', error);
                }
            };
            
            // ✅ Fonction pour mettre en surbrillance la pÃ©riode actuelle
            const highlightCurrentPeriod = () => {
                try {
                    const periodSelect = document.getElementById('exc-period-select');
                    if (!periodSelect || !list) return;
                    
                    const currentPeriod = periodSelect.value;
                    // Retirer toutes les surbrillances
                    Array.from(list.children).forEach(el => el.classList.remove('active'));
                    
                    // Si la pÃ©riode n'est pas custom, mettre en surbrillance l'Ã©lÃ©ment correspondant
                    if (currentPeriod && currentPeriod !== 'custom') {
                        const targetElement = Array.from(list.children).find(el => 
                            el.dataset.code === currentPeriod
                        );
                        
                        if (targetElement) {
                            targetElement.classList.add('active');
                        }
                    }
                } catch (error) {
                    // Erreur silencieuse
                }
            };

            // Calendar helpers and rendering
            const atMidnight = (d)=> new Date(d.getFullYear(), d.getMonth(), d.getDate());
            const sameDay = (a,b)=> a && b && a.getFullYear()===b.getFullYear() && a.getMonth()===b.getMonth() && a.getDate()===b.getDate();
            const inRange = (x,a,b)=> a && b && x>=atMidnight(a) && x<=atMidnight(b);
            const fmt = (d)=> `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}/${d.getFullYear()}`;

            function formatCompactRange(start, end){
                if (!start || !end) return 'SÃ©lecteur avancÃ©â€¦';
                const sameMonth = start.getFullYear()===end.getFullYear() && start.getMonth()===end.getMonth();
                if (sameMonth) {
                    return `${String(start.getDate()).padStart(2,'0')}–${String(end.getDate()).padStart(2,'0')} ${monthsShortFR[start.getMonth()]} ${start.getFullYear()}`;
                }
                const left = `${String(start.getDate()).padStart(2,'0')} ${monthsShortFR[start.getMonth()]} ${start.getFullYear()}`;
                const right = `${String(end.getDate()).padStart(2,'0')} ${monthsShortFR[end.getMonth()]} ${end.getFullYear()}`;
                return `${left} – ${right}`;
            }
            // function formatLongRange(start, end){
            //     if (!start || !end) return '';
            //     const left = `${String(start.getDate()).padStart(2,'0')} ${monthsShortFR[start.getMonth()]} ${start.getFullYear()}`;
            //     const right = `${String(end.getDate()).padStart(2,'0')} ${monthsShortFR[end.getMonth()]} ${end.getFullYear()}`;
            //     return `Du ${left} au ${right}`;
            // }
            function updateDisplay(){
                const display = document.getElementById('pp-display');
                if (!display) return;
                if (state.start && state.end) display.textContent = formatCompactRange(state.start, state.end);
                else display.textContent = 'SÃ©lecteur avancÃ©â€¦';
            }

            function renderCalendar(){
                if (!grid) return;
                const y = state.view.getFullYear();
                const m = state.view.getMonth();
                

                
                grid.innerHTML = '';
                let first = new Date(y, m, 1);
                let startCol = first.getDay() - 1; if (startCol < 0) startCol = 6;
                const days = new Date(y, m+1, 0).getDate();
                let row = document.createElement('tr');
                for (let i=0;i<startCol;i++) { const td = document.createElement('td'); td.className='empty'; row.appendChild(td); }
                const today = atMidnight(new Date());
                const cells = [];
                for (let d=1; d<=days; d++){
                    if (row.children.length === 7) { grid.appendChild(row); row = document.createElement('tr'); }
                    const td = document.createElement('td');
                    td.textContent = String(d);
                    const cur = new Date(y, m, d);
                    if (sameDay(cur, today)) td.classList.add('today');
                    if (state.start && !state.end && sameDay(cur, state.start)) { td.classList.add('pp-start','selected-start'); }
                    if (state.start && state.end){
                        if (sameDay(cur, state.start)) td.classList.add('pp-start','selected-start');
                        else if (sameDay(cur, state.end)) td.classList.add('pp-end','selected-end');
                        else if (inRange(atMidnight(cur), state.start, state.end)) td.classList.add('pp-inrange','in-range');
                    }
                    td.tabIndex = 0;
                    td.addEventListener('click', ()=> onDayClick(cur));
                    row.appendChild(td);
                    cells.push(td);
                }
                while (row.children.length < 7) { const td = document.createElement('td'); td.className='empty'; row.appendChild(td); }
                grid.appendChild(row);
                // Hint
                if (hint) {
                    if (!state.start) hint.textContent = 'SÃ©lectionne la date de dÃ©butâ€¦';
                    else if (!state.end) hint.textContent = 'SÃ©lectionne la date de finâ€¦';
                    else hint.textContent = `SÃ©lection : ${fmt(state.start)} → ${fmt(state.end)}`;
                }
                // Keyboard navigation within days
                if (panel._ppKb) panel.removeEventListener('keydown', panel._ppKb);
                panel._ppKb = function onKey(e){
                    const focusableCells = cells.filter(c => !c.classList.contains('empty'));
                    const idx = focusableCells.indexOf(document.activeElement);
                    if (['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Enter'].includes(e.key)) e.preventDefault();
                    if (e.key === 'ArrowLeft' && idx > 0) focusableCells[idx-1].focus();
                    if (e.key === 'ArrowRight' && idx >= 0 && idx < focusableCells.length-1) focusableCells[idx+1].focus();
                    if (e.key === 'ArrowUp' && idx - 7 >= 0) focusableCells[idx-7].focus();
                    if (e.key === 'ArrowDown' && idx + 7 < focusableCells.length) focusableCells[idx+7].focus();
                    if (e.key === 'Enter' && document.activeElement && focusableCells.includes(document.activeElement)) {
                        const day = Number(document.activeElement.textContent || '0');
                        if (day > 0) onDayClick(new Date(y,m,day));
                    }
                };
                panel.addEventListener('keydown', panel._ppKb);
            }

            async function onDayClick(date){
                if (!state.start || (state.start && state.end)) {
                    state.start = atMidnight(date);
                    state.end = null;
                    updateDisplay();
                    renderCalendar();
                    return;
                }
                state.end = atMidnight(date);
                if (state.end < state.start) { const t = state.start; state.start = state.end; state.end = t; }
                
                // ✅ Synchroniser la vue du calendrier avec la date de fin (borne supÃ©rieure)
                state.view = new Date(state.end.getFullYear(), state.end.getMonth(), 1);
                if (yearSel) yearSel.value = String(state.view.getFullYear());
                if (monthSel) monthSel.value = String(state.view.getMonth());
                

                
                updateDisplay();
                renderCalendar();
                try {
                    // Exposer la plage personnalisée et mettre Ã  jour l'entÃªte et l'URL
                    const fmtISO = (d)=> `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
                    const custom = { start: fmtISO(state.start), end: fmtISO(state.end) };
                    
                    // Utiliser la mÃ©thode centralisÃ©e
                    self.syncCustomRange({ 
                        commitToUrl: true, 
                        start: custom.start, 
                        end: custom.end 
                    });
                    
                    // ✅ NE PAS changer automatiquement l'année - laisser l'utilisateur la contrÃ´ler
                    // const yearSelectEl = document.getElementById('exc-year-select');
                    // if (yearSelectEl) yearSelectEl.value = String(state.start.getFullYear());
                    
                    const periodSelectEl = document.getElementById('exc-period-select');
                    if (periodSelectEl) {
                        // Utiliser l'année actuellement sÃ©lectionnÃ©e, pas celle de la date
                        const currentYear = document.getElementById('exc-year-select')?.value || new Date().getFullYear();
                        await self.updatePeriodSelectForYear(currentYear, 'custom');
                    }
                    
                    // Mettre Ã  jour l'URL (annee, periode, zone) pour rester cohÃ©rent avec la source de vÃ©ritÃ©
                    try { 
                        self.markInternalURLChange();
                        self.updateURLWithFilters(); 
                    } catch(_){}
                    
                    const startSpan = document.getElementById('exc-start-date');
                    const endSpan = document.getElementById('exc-end-date');
                    if (startSpan) startSpan.textContent = fmt(state.start);
                    if (endSpan) endSpan.textContent = fmt(state.end);
                    
                    // RafraÃ®chir l'entÃªte et relancer la gÃ©nÃ©ration
                    try { self.updateHeaderFromAPI(); } catch(_){
                        self.log('updateHeaderFromAPI error:', _);
                    }
                    try { self.chartsGenerated = false; self.generateInfographie(); } catch(_){
                        self.log('generateInfographie error:', _);
                    }
                } catch(_) { 
                    self.log('onDayClick error:', _);
                }
            }

            // Wiring
            toggle?.addEventListener('click', () => {
                panel.classList.contains('open') ? closePanel() : openPanel();
            });
            closeBtn?.addEventListener('click', closePanel);
            overlay.addEventListener('click', closePanel);
            document.addEventListener('mousedown', (ev) => {
                if (!panel.classList.contains('open')) return;
                const clickedInsidePanel = panel.contains(ev.target);
                const clickedToggle = toggle && toggle.contains(ev.target);
                if (!clickedInsidePanel && !clickedToggle) {
                    closePanel();
                }
            });
            window.addEventListener('resize', () => { 
                if (panel.classList.contains('open')) {
                    positionPanel();
                }
            });

            // Build and load
            buildYearOptions();
            buildMonthOptions();
            loadDbPresets(Number(yearSel.value));

            todayBtn.addEventListener('click', () => { 
                const d = new Date(); 
                state.view = d; 
                monthSel.value = String(d.getMonth()); 
                yearSel.value = String(d.getFullYear());
                renderCalendar(); 
            });
            // Calendar navigation
            prevY?.addEventListener('click', () => { 
                state.view = new Date(state.view.getFullYear()-1, state.view.getMonth(), 1); 
                yearSel.value = String(state.view.getFullYear()); 
                renderCalendar(); 
            });
            nextY?.addEventListener('click', () => { 
                state.view = new Date(state.view.getFullYear()+1, state.view.getMonth(), 1); 
                yearSel.value = String(state.view.getFullYear()); 
                renderCalendar(); 
            });
            prevM?.addEventListener('click', () => { 
                state.view = new Date(state.view.getFullYear(), state.view.getMonth()-1, 1); 
                monthSel.value = String(state.view.getMonth()); 
                renderCalendar(); 
            });
            nextM?.addEventListener('click', () => { 
                state.view = new Date(state.view.getFullYear(), state.view.getMonth()+1, 1); 
                monthSel.value = String(state.view.getMonth()); 
                renderCalendar(); 
            });
            monthSel.addEventListener('change', () => { 
                state.view = new Date(state.view.getFullYear(), Number(monthSel.value), 1); 
                renderCalendar(); 
            });
            yearSel.addEventListener('change', () => { 
                const y = Number(yearSel.value||new Date().getFullYear()); 
                
                // 1. Mettre Ã  jour state.view
                state.view = new Date(y, state.view.getMonth(), 1); 
                
                // 2. Rendu du calendrier
                renderCalendar(); 
                
                // 3. Charger les presets de la base de donnÃ©es
                loadDbPresets(y); 
                
                // 4. Synchroniser exc-year-select avec pp-year-select
                const excYearSelect = document.getElementById('exc-year-select');
                if (excYearSelect && excYearSelect.value !== yearSel.value) {
                    excYearSelect.value = yearSel.value;
                    excYearSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            // Initial state from URL (custom range)
            try {
                const u = new URL(window.location.href);
                const debut = u.searchParams.get('debut');
                const fin = u.searchParams.get('fin');
                if (debut && fin) {
                    const sd = this.parseISODate(debut);
                    const ed = this.parseISODate(fin);
                    if (this.isValidDate(sd) && this.isValidDate(ed)) {
                        state.start = atMidnight(sd);
                        state.end = atMidnight(ed);
                        // ✅ Afficher le mois de la date de fin (borne supÃ©rieure) Ã  l'ouverture
                        state.view = new Date(state.end.getFullYear(), state.end.getMonth(), 1);
                        
                        
                        
                                        // ✅ Synchroniser les sÃ©lecteurs avec la vue du calendrier
                if (yearSel) {
                    yearSel.value = String(state.view.getFullYear());
                    yearSel.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (monthSel) {
                    monthSel.value = String(state.view.getMonth());
                    monthSel.dispatchEvent(new Event('change', { bubbles: true }));
                }
                        
                        
                        
                        // ✅ NE PAS changer automatiquement l'année - laisser l'utilisateur la contrÃ´ler
                        // const yearSelectEl = document.getElementById('exc-year-select');
                        // if (yearSelectEl) yearSelectEl.value = String(state.start.getFullYear());
                        
                        // Utiliser l'année actuellement sÃ©lectionnÃ©e, pas celle de la date
                        const currentYear = document.getElementById('exc-year-select')?.value || new Date().getFullYear();
                        await self.updatePeriodSelectForYear(currentYear, 'custom');
                        
                        // labels
                        updateDisplay();
                        const startSpan = document.getElementById('exc-start-date');
                        const endSpan = document.getElementById('exc-end-date');
                        if (startSpan) startSpan.textContent = fmt(state.start);
                        if (endSpan) endSpan.textContent = fmt(state.end);
                    }
                }
            } catch(_){}

            // ✅ Initialisation des dates et du mois affichÃ© selon la pÃ©riode sÃ©lectionnÃ©e
            try {
                const periodSelect = document.getElementById('exc-period-select');
                const yearSelect = document.getElementById('exc-year-select');
                
                if (periodSelect && yearSelect) {
                    const periode = periodSelect.value;
                    const annee = parseInt(yearSelect.value);
                    
                    
                    
                    // Si pas de plage custom dÃ©finie, initialiser selon la pÃ©riode
                    if (!state.start || !state.end) {
                        if (periode === 'annee_complete') {
                            // AnnÃ©e complète : 1er janvier au 31 décembre
                            state.start = new Date(annee, 0, 1); // 1er janvier
                            state.end = new Date(annee, 11, 31); // 31 décembre
                            // Afficher décembre (mois de la date de fin)
                            state.view = new Date(annee, 11, 1); // 1er décembre
                            
                            
                        } else if (periode && periode !== 'custom') {
                            // Pour les autres pÃ©riodes prÃ©dÃ©finies, rÃ©cupÃ©rer les dates depuis l'API
                            try {
                                const base = (typeof window.getApiUrl === 'function')
                                    ? window.getApiUrl('get_periodes.php')
                                    : 'api/get_periodes.php';
                                const url = `${base}?action=year&annee=${encodeURIComponent(annee)}`;
                                const res = await fetch(url, { credentials: 'same-origin' });
                                const json = await res.json();
                                
                                if (json && json[periode]) {
                                    const periodeInfo = json[periode];
                                                                    if (periodeInfo.debut && periodeInfo.fin) {
                                    const sd = this.parseISODate(periodeInfo.debut);
                                    const ed = this.parseISODate(periodeInfo.fin);
                                    
                                    if (this.isValidDate(sd) && this.isValidDate(ed)) {
                                        state.start = atMidnight(sd);
                                        state.end = atMidnight(ed);
                                        // Afficher le mois de la date de fin
                                        state.view = new Date(state.end.getFullYear(), state.end.getMonth(), 1);
                                    }
                                    }
                                }
                            } catch (error) {
                            }
                        }
                        
                        // Synchroniser les sÃ©lecteurs avec la vue du calendrier
                        if (yearSel) {
                            yearSel.value = String(state.view.getFullYear());
                            yearSel.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        if (monthSel) {
                            monthSel.value = String(state.view.getMonth());
                            monthSel.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        
                        
                    }
                }
            } catch (error) {
            }

            // ✅ Fonction pour rÃ©initialiser le sÃ©lecteur selon la pÃ©riode
            const resetPeriodPickerForPeriod = async () => {
                const periodSelect = document.getElementById('exc-period-select');
                const yearSelect = document.getElementById('exc-year-select');
                
                if (periodSelect && yearSelect) {
                    const periode = periodSelect.value;
                    const annee = parseInt(yearSelect.value);
                    
                    // RÃ©initialiser l'état
                    state.start = null;
                    state.end = null;
                    
                    if (periode === 'annee_complete') {
                        // AnnÃ©e complète : 1er janvier au 31 décembre
                        state.start = new Date(annee, 0, 1);
                        state.end = new Date(annee, 11, 31);
                        state.view = new Date(annee, 11, 1); // DÃ©cembre
                    } else if (periode && periode !== 'custom') {
                        // Pour les autres pÃ©riodes prÃ©dÃ©finies, rÃ©cupÃ©rer les dates depuis l'API
                        try {
                            const base = (typeof window.getApiUrl === 'function')
                                ? window.getApiUrl('get_periodes.php')
                                : 'api/get_periodes.php';
                            const url = `${base}?action=year&annee=${encodeURIComponent(annee)}`;
                            const res = await fetch(url, { credentials: 'same-origin' });
                            const json = await res.json();
                            
                            if (json && json[periode]) {
                                const periodeInfo = json[periode];
                                if (periodeInfo.debut && periodeInfo.fin) {
                                    const sd = this.parseISODate(periodeInfo.debut);
                                    const ed = this.parseISODate(periodeInfo.fin);
                                    
                                    if (this.isValidDate(sd) && this.isValidDate(ed)) {
                                        state.start = atMidnight(sd);
                                        state.end = atMidnight(ed);
                                        // Afficher le mois de la date de fin
                                        state.view = new Date(state.end.getFullYear(), state.end.getMonth(), 1);
                                    }
                                }
                            }
                        } catch (error) {
                        }
                    }
                    
                    // Synchroniser les sÃ©lecteurs
                    if (yearSel) {
                        yearSel.value = String(state.view.getFullYear());
                        yearSel.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    if (monthSel) {
                        monthSel.value = String(state.view.getMonth());
                        monthSel.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    
                    // Mettre Ã  jour l'affichage
                    updateDisplay();
                    renderCalendar();
                }
            };
            
            // ✅ Rendre la fonction accessible globalement
            window.resetPeriodPickerForPeriod = resetPeriodPickerForPeriod;

            // Initial render
            renderCalendar();
            
            // ✅ Ã‰couter les changements de pÃ©riode
            const observePeriodChanges = () => {
                const periodSelect = document.getElementById('exc-period-select');
                if (periodSelect) {
                    // Ã‰couter les Ã©vÃ©nements change
                    periodSelect.addEventListener('change', () => {
                        resetPeriodPickerForPeriod();
                    });
                }
            };
            
            // DÃ©marrer l'observation
            observePeriodChanges();
        } catch (e) {
            // Erreur silencieuse
        }
    }

    async loadConfig() {
        try {
            // Attendre un peu pour que les scripts se chargent
            await new Promise(resolve => setTimeout(resolve, 100));
            
            // Utiliser la mÃªme configuration que tdb_comparaison (obligatoire maintenant)
            if (typeof CantalDestinationDynamicConfig !== 'undefined') {
                this.config = new CantalDestinationDynamicConfig();
                await this.config.loadFromDatabase();
            } else {
                throw new Error('CantalDestinationDynamicConfig non disponible - configuration requise');
            }

            // Palette de couleurs commune au tableau de bord
            if (!window.CHART_COLORS) {
                window.CHART_COLORS = [
                    'rgba(0, 242, 234, 0.8)',  'rgba(248, 0, 255, 0.8)',  'rgba(163, 95, 255, 0.8)',
                    'rgba(126, 255, 139, 0.8)', 'rgba(255, 150, 79, 0.8)',  'rgba(0, 174, 255, 0.8)',
                    'rgba(255, 0, 168, 0.8)',  'rgba(255, 234, 0, 0.8)',   'rgba(0, 255, 56, 0.8)',
                    'rgba(0, 255, 212, 0.8)','rgba(191, 255, 0, 0.8)',   'rgba(255, 0, 115, 0.8)',
                    'rgba(119, 0, 255, 0.8)','rgba(0, 255, 149, 0.8)',  'rgba(0, 140, 255, 0.81)'
                ];
            }
        } catch (error) {
            // Erreur silencieuse
            // throw error;
        }
    }

    applyURLParametersToFilters() {
        // Appliquer les paramÃ¨tres URL aux filtres (mÃªme logique que tdb_comparaison)
        try {
            const urlParams = new URLSearchParams(window.location.search);
            
            // Appliquer l'année depuis l'URL
            const yearFromUrl = urlParams.get('annee');
            if (yearFromUrl) {
                const yearSelect = document.getElementById('exc-year-select');
                if (yearSelect) {
                    const option = Array.from(yearSelect.options).find(opt => opt.value === yearFromUrl);
                    if (option) {
                        yearSelect.value = yearFromUrl;
                        yearSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            }

            // Appliquer la pÃ©riode depuis l'URL
            const periodFromUrl = urlParams.get('periode');
            if (periodFromUrl) {
                const periodSelect = document.getElementById('exc-period-select');
                if (periodSelect) {
                    // Recherche exacte d'abord
                    let option = Array.from(periodSelect.options).find(opt => 
                        opt.value.toLowerCase() === periodFromUrl.toLowerCase()
                    );
                    
                    // Recherche flexible si pas trouvÃ©
                    if (!option) {
                        option = Array.from(periodSelect.options).find(opt => 
                            opt.value.toLowerCase().includes(periodFromUrl.toLowerCase()) ||
                            opt.textContent.toLowerCase().includes(periodFromUrl.toLowerCase())
                        );
                    }
                    
                    if (option) {
                        periodSelect.value = option.value;
                        periodSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            }

            // Appliquer la zone depuis l'URL
            const zoneFromUrl = urlParams.get('zone');
            if (zoneFromUrl) {
                const zoneSelect = document.getElementById('exc-zone-select');
                if (zoneSelect) {
                    const option = Array.from(zoneSelect.options).find(opt => opt.value === zoneFromUrl);
                    if (option) {
                        zoneSelect.value = zoneFromUrl;
                        zoneSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            }
        } catch (error) {
        }
    }

    updateURLWithFilters() {
        
        // Mettre Ã  jour l'URL avec les valeurs actuelles des filtres (sans rechargement)
        try {
            const yearSelect = document.getElementById('exc-year-select');
            const periodSelect = document.getElementById('exc-period-select');
            const zoneSelect = document.getElementById('exc-zone-select');



            if (yearSelect?.value && periodSelect?.value && zoneSelect?.value) {
                const urlParams = new URLSearchParams(window.location.search);
                
                // Mettre Ã  jour les paramÃ¨tres
                urlParams.set('annee', yearSelect.value);
                urlParams.set('periode', periodSelect.value);
                urlParams.set('zone', zoneSelect.value);

                // Construire la nouvelle URL
                const newUrl = `${window.location.pathname}?${urlParams.toString()}`;
                

                
                                        // Mettre Ã  jour l'URL sans rechargement
                        this.markInternalURLChange();
                        window.history.replaceState(null, '', newUrl);

                
            }
        } catch (error) {
            // Erreur silencieuse
        }
    }

    async waitForFiltersLoaded() {
        const deadline = Date.now() + 10000;
        
        while (Date.now() < deadline) {
            const yearSelect = document.getElementById('exc-year-select');
            const periodSelect = document.getElementById('exc-period-select');
            const zoneSelect = document.getElementById('exc-zone-select');
            
            if (yearSelect?.options.length > 1 && 
                periodSelect?.options.length > 1 && 
                zoneSelect?.options.length > 1) {
                return;
            }
            
            await new Promise(resolve => setTimeout(resolve, 100));
        }
        
        // Fallback: continuer avec ce qui existe
        const yearSelect = document.getElementById('exc-year-select');
        const periodSelect = document.getElementById('exc-period-select');
        const zoneSelect = document.getElementById('exc-zone-select');
        
        // Si au moins un filtre a des options, continuer
        if (yearSelect?.options.length || periodSelect?.options.length || zoneSelect?.options.length) {
            // Erreur silencieuse - continuer avec les filtres disponibles
            return;
        }
        
        // Si aucun filtre n'est disponible, lever une erreur contrÃ´lÃ©e
        throw new Error('Impossible de charger les filtres aprÃ¨s 10 secondes');
    }

    initializeEvents() {
        const self = this;
        
        // Bouton de tÃ©lÃ©chargement
        const downloadBtn = document.getElementById('btn-telecharger-infographie');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', () => { this.downloadInfographie(); });
        }

        // Ã‰couter les changements de filtres
        const yearSelect = document.getElementById('exc-year-select');
        const periodSelect = document.getElementById('exc-period-select');
        const zoneSelect = document.getElementById('exc-zone-select');

        // ✅ Fonction pour rÃ©initialiser le sÃ©lecteur avancÃ© selon la pÃ©riode
        const resetAdvancedPeriodPicker = async () => {
            try {
                const periode = periodSelect?.value;
                const annee = parseInt(yearSelect?.value);
                
                if (periode && annee) {

                    
                    // Appeler la fonction de rÃ©initialisation du sÃ©lecteur avancÃ©
                    if (window.resetPeriodPickerForPeriod) {
                        await window.resetPeriodPickerForPeriod();
                    }
                }
            } catch (error) {
            }
        };

        // Fonction pour gÃ©nÃ©rer l'infographie automatiquement
        const autoGenerateInfographie = async () => {

            
            // VÃ©rifier que tous les filtres sont chargÃ©s et ont une valeur
            if (yearSelect?.value && periodSelect?.value && zoneSelect?.value) {

                
                // Si la pÃ©riode n'est PAS custom, supprimer toute plage custom persistÃ©e (URL/mÃ©moire)
                try {
                    if (periodSelect.value !== 'custom') {
                        self.syncCustomRange({ commitToUrl: true });
                    } else {
                        // ✅ Mettre Ã  jour window.infographieCustomDateRange avec les dates de l'URL
                        self.syncCustomRange();
                    }
                } catch(_) {
                    self.log('autoGenerateInfographie syncCustomRange error:', _);
                }
                
                // ✅ Mettre Ã  jour l'URL AVANT updateHeaderFromAPI pour que les dates soient cohÃ©rentes
                self.markInternalURLChange();
                self.updateURLWithFilters();
                
                // ✅ RÃ©initialiser le sÃ©lecteur avancÃ© selon la nouvelle pÃ©riode
                await resetAdvancedPeriodPicker();
                
                // ✅ Forcer la mise Ã  jour des dates custom dans l'URL si nÃ©cessaire
                if (periodSelect.value === 'custom') {
                    const urlParams = new URLSearchParams(window.location.search);
                    const currentYear = yearSelect.value;
                    const debutFromUrl = urlParams.get('debut');
                    const finFromUrl = urlParams.get('fin');
                    
                                            if (debutFromUrl && finFromUrl) {
                            const startDate = this.parseISODate(debutFromUrl);
                            const endDate = this.parseISODate(finFromUrl);
                        

                        
                        // VÃ©rifier si les dates custom sont d'une année diffÃ©rente de l'année sÃ©lectionnÃ©e
                        if (startDate.getFullYear() !== parseInt(currentYear) || endDate.getFullYear() !== parseInt(currentYear)) {
                            
                            // ✅ Corriger la crÃ©ation des dates pour Ã©viter les problÃ¨mes de timezone
                            const formatDate = (year, month, day) => {
                                return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                            };
                            
                            const newCustomStart = formatDate(parseInt(currentYear), startDate.getMonth(), startDate.getDate());
                            const newCustomEnd = formatDate(parseInt(currentYear), endDate.getMonth(), endDate.getDate());
                            
                            // Utiliser la mÃ©thode centralisÃ©e
                            self.syncCustomRange({ 
                                commitToUrl: true, 
                                start: newCustomStart, 
                                end: newCustomEnd 
                            });
                            
                        }
                    }
                }
                
                // Mettre Ã  jour le header APRÃˆS la mise Ã  jour de l'URL
                this.updateHeaderFromAPI();
                
                // ✅ Toujours rÃ©gÃ©nÃ©rer l'infographie quand on change les filtres
                // RÃ©initialiser le flag pour permettre la rÃ©gÃ©nÃ©ration
                this.chartsGenerated = false;
                this.generateInfographie();
            } else {
                this.log('autoGenerateInfographie: filtres incomplets');
            }
        };

        // Ã‰couter les changements de filtres
        [yearSelect, periodSelect, zoneSelect].forEach(select => {
            if (select) {
                select.addEventListener('change', (event) => {
                    
                    // Stocker la valeur prÃ©cÃ©dente pour le prochain changement
                    event.target.dataset.previousValue = event.target.value;
                    
                    // Appeler la fonction de gÃ©nÃ©ration automatique
                    autoGenerateInfographie();
                });
            }
        });

        // GÃ©nÃ©rer l'infographie initiale une fois les filtres chargÃ©s
        this.generateInitialInfographie();
    }

    async generateInitialInfographie() {
        // Attendre que les filtres soient chargÃ©s
        await this.waitForFiltersLoaded();
        
        // VÃ©rifier que tous les filtres ont des valeurs par dÃ©faut
        const yearSelect = document.getElementById('exc-year-select');
        const periodSelect = document.getElementById('exc-period-select');
        const zoneSelect = document.getElementById('exc-zone-select');

        if (yearSelect?.value && periodSelect?.value && zoneSelect?.value) {
            this.updateHeaderFromAPI();
            // GÃ©nÃ©rer l'infographie initiale seulement une fois
            if (!this.chartsGenerated) {
                this.generateInfographie();
            }
        }
    }

    async updateHeaderFromAPI() {
        
        try {
            // RÃ©cupÃ©rer les valeurs des filtres
            const annee = document.getElementById('exc-year-select')?.value || this.config?.defaultYear;
            const periode = document.getElementById('exc-period-select')?.value || this.config?.defaultPeriod;
            const zone = document.getElementById('exc-zone-select')?.value || this.config?.defaultZone;


            if (!annee || !periode || !zone) {
                return;
            }

            // Appeler l'API bloc_a pour rÃ©cupÃ©rer les vraies dates (mÃªme logique que le tableau de bord)
            // Prendre en compte une Ã©ventuelle plage custom (URL ou mÃ©moire)
            const urlParams = new URLSearchParams(window.location.search);
            const debutFromUrl = urlParams.get('debut');
            const finFromUrl = urlParams.get('fin');
            const customRange = (window.infographieCustomDateRange && window.infographieCustomDateRange.start && window.infographieCustomDateRange.end)
                ? window.infographieCustomDateRange
                : (debutFromUrl && finFromUrl ? { start: debutFromUrl, end: finFromUrl } : null);
            
            
            const url = `api/infographie/infographie_indicateurs_cles.php?annee=${annee}&periode=${periode}&zone=${zone}${customRange ? `&debut=${encodeURIComponent(customRange.start)}&fin=${encodeURIComponent(customRange.end)}` : ''}`;
            
            const response = await fetch(url);
            const data = await response.json();


            if (data.error) {
                // Erreur silencieuse
                return;
            }

            // Mettre Ã  jour le header; si plage custom active, forcer le libellÃ© long
            if (customRange) {
                data.periode = 'custom';
                data.annee = String(this.parseISODate(customRange.start).getFullYear());
                data.debut = `${customRange.start} 00:00:00`;
                data.fin = `${customRange.end} 23:59:59`;
            }
            
            this.updateHeader(data);

        } catch (error) {
            // Erreur silencieuse
            // Fallback vers les dates calculÃ©es
            this.updateHeaderDates();
        }
    }

    updateHeader(data) {
        // MÃªme logique que dans tdb_comparaison.js
        const startDate = this.formatDate(data.debut);
        const endDate = this.formatDate(data.fin);
        
        const headerPeriod = document.getElementById('header-period');
        const excStartDate = document.getElementById('exc-start-date');
        const excEndDate = document.getElementById('exc-end-date');
        const ppDisplay = document.getElementById('pp-display');
        const periodSelectEl = document.getElementById('exc-period-select');
        const periodLabel = periodSelectEl?.options?.[periodSelectEl.selectedIndex]?.text || String(data.periode);

        if (headerPeriod) {
            const urlParams = new URLSearchParams(window.location.search);
            const debut = urlParams.get('debut');
            const fin = urlParams.get('fin');
                            if (data.periode === 'custom' || (debut && fin)) {
                    try {
                        const s = this.parseISODate(data.debut) || new Date(data.debut);
                        const f = this.parseISODate(data.fin) || new Date(data.fin);
                        if (this.isValidDate(s) && this.isValidDate(f)) {
                            const fmt = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
                            headerPeriod.textContent = `Du ${fmt.format(s)} au ${fmt.format(f)}`;
                        } else {
                            headerPeriod.textContent = `${periodLabel} ${data.annee}`;
                        }
                    } catch(_) {
                        this.log('updateHeader date parsing error:', _);
                        headerPeriod.textContent = `${periodLabel} ${data.annee}`;
                    }
                } else {
                    headerPeriod.textContent = `${periodLabel} ${data.annee}`;
                }
        }
        if (excStartDate) excStartDate.textContent = startDate;
        if (excEndDate) excEndDate.textContent = endDate;
        if (ppDisplay) ppDisplay.textContent = this.formatCompactRangeFromStrings(data.debut, data.fin);

        // Mettre Ã  jour aussi les ancrages de compatibilitÃ©
        const titleHook = document.querySelector('[data-title]');
        const subtitleHook = document.querySelector('[data-subtitle]');
        if (titleHook) titleHook.textContent = 'SynthÃ¨se Touristique';
        if (subtitleHook) subtitleHook.textContent = `${periodLabel} ${data.annee}`;

        // Synchroniser immÃ©diatement le header visuel (ht-title-line line2) si dÃ©jÃ  prÃ©sent
        try {
            const headerCenter = document.querySelector('.ht-center');
            const line2 = headerCenter ? headerCenter.querySelector('.ht-title-line.line2') : null;
            if (line2) {
                const urlParams = new URLSearchParams(window.location.search);
                const debut = urlParams.get('debut');
                const fin = urlParams.get('fin');
                if (data.periode === 'custom' || (debut && fin)) {
                    const s = this.parseISODate(data.debut) || new Date(data.debut);
                    const f = this.parseISODate(data.fin) || new Date(data.fin);
                    if (this.isValidDate(s) && this.isValidDate(f)) {
                        const fmt = new Intl.DateTimeFormat('fr-FR', { day:'2-digit', month:'short', year:'numeric' });
                        line2.textContent = `DU ${fmt.format(s).toUpperCase()} AU ${fmt.format(f).toUpperCase()}`;
                    } else {
                        line2.textContent = `${String(periodLabel)} ${String(data.annee)}`.toUpperCase();
                    }
                } else {
                    line2.textContent = `${String(periodLabel)} ${String(data.annee)}`.toUpperCase();
                }
            }
        } catch(_){ 
            this.log('updateHeader visual sync error:', _);
        }
    }

    updateHeaderDates() {
        // MÃ©thode de fallback avec dates calculÃ©es
        const startDateSpan = document.getElementById('exc-start-date');
        const endDateSpan = document.getElementById('exc-end-date');
        const headerPeriodSpan = document.getElementById('header-period');

        if (headerPeriodSpan) {
            const periodSelect = document.getElementById('exc-period-select');
            const selectedPeriod = periodSelect?.options[periodSelect.selectedIndex]?.text || 'PÃ©riode';
            headerPeriodSpan.textContent = selectedPeriod;
        }


        // En cas de fallback, afficher des dates gÃ©nÃ©riques
        if (startDateSpan) startDateSpan.textContent = '--/--/----';
        if (endDateSpan) endDateSpan.textContent = '--/--/----';
    }

    // MÃ©thode pour calculer les dates selon la pÃ©riode


    // MÃ©thode utilitaire pour formater les dates
    formatDate(s) {
        if (!s) return '--/--/----';
        const d = this.parseISODate(s) || new Date(s);
        return this.isValidDate(d) ? d.toLocaleDateString('fr-FR') : '--/--/----';
    }

    // Formate une plage compacte pour pp-display Ã  partir de chaÃ®nes date ISO/SQL
    formatCompactRangeFromStrings(startStr, endStr) {
        try {
            if (!startStr || !endStr) return 'SÃ©lecteur avancÃ©â€¦';
            const s = this.parseISODate(startStr) || new Date(startStr);
            const e = this.parseISODate(endStr) || new Date(endStr);
            
            if (!this.isValidDate(s) || !this.isValidDate(e)) return 'SÃ©lecteur avancÃ©â€¦';
            const sameMonth = s.getFullYear() === e.getFullYear() && s.getMonth() === e.getMonth();
            const fmtMonth = new Intl.DateTimeFormat('fr-FR', { month: 'short' });
            const dd = (d)=> String(d.getDate()).padStart(2,'0');
            if (sameMonth) {
                return `${dd(s)}–${dd(e)} ${fmtMonth.format(s)} ${s.getFullYear()}`;
            }
            return `${dd(s)} ${fmtMonth.format(s)} ${s.getFullYear()} – ${dd(e)} ${fmtMonth.format(e)} ${e.getFullYear()}`;
        } catch (_) {
            return 'SÃ©lecteur avancÃ©â€¦';
        }
    }

    async generateInfographie() {
        
        // Annuler les requÃªtes prÃ©cÃ©dentes
        this.fetchCtl?.abort();
        this.fetchCtl = new AbortController();
        const { signal } = this.fetchCtl;
        
        // Ã‰viter les gÃ©nÃ©rations multiples simultanÃ©es
        if (this.chartsGenerated) {
            return;
        }

        this.chartsGenerated = true;
        
        // Afficher l'indicateur de chargement
        this.showLoadingIndicator();
        
        try {
            // RÃ©cupÃ©rer les valeurs des filtres
            const filters = this.getFilterValues();
            
            // Si des bornes custom existent mais que l'utilisateur a explicitement choisi annee_complete,
            // on ne force pas period=custom (on laisse l'utilisateur sur annee_complete),
            // mais on supprime les bornes custom de l'URL pour Ã©viter la confusion
            if (filters.customStart && filters.customEnd) {
                const selectedPeriod = document.getElementById('exc-period-select')?.value;
                
                if (selectedPeriod === 'annee_complete') {
                    try {
                        this.markInternalURLChange();
                        const u = new URL(window.location.href);
                        u.searchParams.delete('debut');
                        u.searchParams.delete('fin');
                        window.history.replaceState(null, '', u.toString());
                        delete filters.customStart;
                        delete filters.customEnd;
                    } catch(_){ 
                        // Erreur silencieuse
                    }
                }
            }
            
            // Charger les donnÃ©es
            await this.loadInfographieData(filters, signal);
            
            // GÃ©nÃ©rer l'infographie
            this.renderInfographie();
            
            // Activer les boutons de tÃ©lÃ©chargement et partage
            const downloadBtn = document.getElementById('btn-telecharger-infographie');
            const shareBtn = document.getElementById('btn-partager-infographie');
            
            if (downloadBtn) {
                downloadBtn.disabled = false;
            }
            if (shareBtn) {
                shareBtn.disabled = false;
            }

        } catch (error) {
            // Erreur silencieuse
            this.showError('Erreur lors de la gÃ©nÃ©ration de l\'infographie');
        } finally {
            // Masquer l'indicateur de chargement
            this.hideLoadingIndicator();
            
            // Garder chartsGenerated Ã  true si la gÃ©nÃ©ration a rÃ©ussi
            // (les boutons restent activÃ©s, donc l'infographie est prÃªte)
            if (this.chartsGenerated) {
                // L'infographie a Ã©tÃ© gÃ©nÃ©rÃ©e avec succÃ¨s, on garde le flag Ã  true
                window.fvLog('Infographie gÃ©nÃ©rÃ©e avec succÃ¨s');
            }
        }
    }

    getFilterValues() {
        
        // Lire les paramÃ¨tres URL en prioritÃ© (mÃªme logique que tdb_comparaison)
        const urlParams = new URLSearchParams(window.location.search);
        const yearFromUrl = urlParams.get('annee');
        const periodFromUrl = urlParams.get('periode');
        const zoneFromUrl = urlParams.get('zone');
        const debutFromUrl = urlParams.get('debut');
        const finFromUrl = urlParams.get('fin');



        const yearSelect = document.getElementById('exc-year-select');
        const periodSelect = document.getElementById('exc-period-select');
        const zoneSelect = document.getElementById('exc-zone-select');



        const values = {
            year: yearFromUrl || yearSelect?.value || this.config.defaultYear,
            period: periodFromUrl || periodSelect?.value || this.config.defaultPeriod,
            zone: zoneFromUrl || zoneSelect?.value || this.config.defaultZone
        };
        
        // ✅ Utiliser directement les dates de l'URL (qui sont maintenant mises Ã  jour par autoGenerateInfographie)
        if (debutFromUrl && finFromUrl) {
            values.customStart = debutFromUrl;
            values.customEnd = finFromUrl;
        }

        return values;
    }

    async loadInfographieData(filters, signal) {
        
        const paramsRange = (filters.customStart && filters.customEnd)
            ? `&debut=${encodeURIComponent(filters.customStart)}&fin=${encodeURIComponent(filters.customEnd)}`
            : '';
        
        
        const results = await Promise.allSettled([
            this.loadKeyIndicators(filters, paramsRange, signal),
            this.loadNuiteesOrigins(filters, paramsRange, signal),
            this.loadExcursionnistesOrigins(filters, paramsRange, signal),
            this.loadNuiteesDepartements(filters, paramsRange, signal),
            this.loadExcursionnistesDepartements(filters, paramsRange, signal),
            this.loadStayDistribution(filters, paramsRange, signal),
            this.loadMobilityDestinations(filters, paramsRange, signal)
        ]);

        const [keyIndicatorsResult, nuiteesOriginsResult, excursionnistesOriginsResult, nuiteesDepartementsResult, excursionnistesDepartementsResult, stayDistributionResult, mobilityDestinationsResult] = results;


        window.fvLog('[Infographie] 📊 RÃ©sultats des chargements:');
        window.fvLog('[Infographie] 🔑 keyIndicatorsResult:', keyIndicatorsResult.status === 'fulfilled' ? '✅' : '❌', keyIndicatorsResult.status === 'fulfilled' ? keyIndicatorsResult.value : keyIndicatorsResult.reason);
        window.fvLog('[Infographie] 🏠 nuiteesOriginsResult:', nuiteesOriginsResult.status === 'fulfilled' ? '✅' : '❌');
        window.fvLog('[Infographie] 🚶 excursionnistesOriginsResult:', excursionnistesOriginsResult.status === 'fulfilled' ? '✅' : '❌');
        window.fvLog('[Infographie] 📊 nuiteesDepartementsResult:', nuiteesDepartementsResult.status === 'fulfilled' ? '✅' : '❌');
        window.fvLog('[Infographie] 🚶 excursionnistesDepartementsResult:', excursionnistesDepartementsResult.status === 'fulfilled' ? '✅' : '❌');
        window.fvLog('[Infographie] 🏨 stayDistributionResult:', stayDistributionResult.status === 'fulfilled' ? '✅' : '❌');
        window.fvLog('[Infographie] 🎯 mobilityDestinationsResult:', mobilityDestinationsResult.status === 'fulfilled' ? '✅' : '❌');

        if (mobilityDestinationsResult.status === 'fulfilled') {
            window.fvLog('[Infographie] 📦 DonnÃ©es mobilityDestinations:', mobilityDestinationsResult.value);
            window.fvLog('[Infographie] 📊 Nombre de destinations:', mobilityDestinationsResult.value?.length || 0);
        } else {
            console.error('[Infographie] ❌ Erreur mobilityDestinationsResult:', mobilityDestinationsResult.reason);
        }

        this.currentData = {
            filters: filters,
            keyIndicators: keyIndicatorsResult.status === 'fulfilled' ? keyIndicatorsResult.value : null,
            nuiteesOrigins: {
                ...(nuiteesOriginsResult.status === 'fulfilled' ? nuiteesOriginsResult.value : {}),
                departements: nuiteesDepartementsResult.status === 'fulfilled' ? nuiteesDepartementsResult.value : null
            },
            excursionnistesOrigins: {
                ...(excursionnistesOriginsResult.status === 'fulfilled' ? excursionnistesOriginsResult.value : {}),
                departements: excursionnistesDepartementsResult.status === 'fulfilled' ? excursionnistesDepartementsResult.value : null
            },
            stayDistribution: stayDistributionResult.status === 'fulfilled' ? stayDistributionResult.value : null,
            mobilityDestinations: mobilityDestinationsResult.status === 'fulfilled' ? mobilityDestinationsResult.value : null
        };

        window.fvLog('[Infographie] 💾 currentData crÃ©Ã© avec mobilityDestinations:', this.currentData.mobilityDestinations);
        window.fvLog('[Infographie] 📊 Taille mobilityDestinations:', this.currentData.mobilityDestinations?.length || 0);


        return this.currentData;
    }

    async loadKeyIndicators(filters, paramsRange = '', signal) {
        // Utiliser l'API existante pour les indicateurs clÃ©s (mÃªme que tdb_comparaison)
        try {
            const url = `api/infographie/infographie_indicateurs_cles.php?annee=${filters.year}&periode=${filters.period}&zone=${filters.zone}${paramsRange}`;
            const response = await fetch(url, { signal });
            const responseText = await response.text();
            
            // VÃ©rifier si la rÃ©ponse est du JSON valide
            // Gérer le BOM (Byte Order Mark) qui peut être présent au début
            const cleanResponseText = responseText.replace(/^\uFEFF/, '').trim();

            if (!cleanResponseText.startsWith('{') && !cleanResponseText.startsWith('[')) {
                // Erreur silencieuse
                return null;
            }
            
            const parsed = JSON.parse(cleanResponseText);
            const payload = Array.isArray(parsed?.data) ? parsed.data : parsed;
            return payload;
        } catch (error) {
            return this.handleFetchError(error);
        }
    }

    async loadNuiteesOrigins(filters, paramsRange = '', signal) {
        // Charger les donnÃ©es d'origines pour les nuitées (rÃ©gions + pays)
        try {
            // Charger les rÃ©gions et pays en parallÃ¨le pour les nuitées
            const [regionsData, paysData] = await Promise.all([
                this.loadNuiteesRegions(filters, paramsRange, signal),
                this.loadNuiteesPays(filters, paramsRange, signal)
            ]);

            
            return {
                regions: regionsData,
                pays: paysData
            };
            
        } catch (error) {
            // Erreur silencieuse
            return null;
        }
    }

    async loadNuiteesRegions(filters, paramsRange = '', signal) {
        try {
            const url = `api/v2/infographie/regions-touristes?annee=${filters.year}&periode=${filters.period}&zone=${filters.zone}&limit=10${paramsRange}`;
            const response = await fetch(url, { signal });
            const responseText = await response.text();
            
            // Gérer le BOM (Byte Order Mark) qui peut être présent au début
            const cleanResponseText = responseText.replace(/^\uFEFF/, '').trim();

            if (!cleanResponseText.startsWith('{') && !cleanResponseText.startsWith('[')) {
                // Erreur silencieuse
                return null;
            }
            
            const parsed = JSON.parse(cleanResponseText);
            const payload = Array.isArray(parsed?.data) ? parsed.data : parsed;
            return payload;
            
        } catch (error) {
            return this.handleFetchError(error);
        }
    }

    async loadNuiteesPays(filters, paramsRange = '', signal) {
        try {
            const url = `api/v2/infographie/pays-touristes?annee=${filters.year}&periode=${filters.period}&zone=${filters.zone}&limit=10${paramsRange}`;
            const response = await fetch(url, { signal });
            const responseText = await response.text();
            
            // Gérer le BOM (Byte Order Mark) qui peut être présent au début
            const cleanResponseText = responseText.replace(/^\uFEFF/, '').trim();

            if (!cleanResponseText.startsWith('{') && !cleanResponseText.startsWith('[')) {
                // Erreur silencieuse
                return null;
            }
            
            const parsed = JSON.parse(cleanResponseText);
            const payload = Array.isArray(parsed?.data) ? parsed.data : parsed;
            return payload;
            
        } catch (error) {
            return this.handleFetchError(error);
        }
    }

    async loadExcursionnistesOrigins(filters, paramsRange = '', signal) {
        // Charger les donnÃ©es d'origines pour les excursionnistes (rÃ©gions + pays)
        try {
            // Charger les rÃ©gions et pays en parallÃ¨le pour les excursionnistes
            const [regionsData, paysData] = await Promise.all([
                this.loadExcursionnistesRegions(filters, paramsRange, signal),
                this.loadExcursionnistesPays(filters, paramsRange, signal)
            ]);

            return {
                regions: regionsData,
                pays: paysData
            };

        } catch (error) {
            return this.handleFetchError(error);
        }
    }

    async loadExcursionnistesRegions(filters, paramsRange = '', signal) {
        try {
            const url = `api/v2/infographie/regions-excursionnistes?annee=${filters.year}&periode=${filters.period}&zone=${filters.zone}&limit=10${paramsRange}`;
            const response = await fetch(url, { signal });
            const responseText = await response.text();
            
            // Gérer le BOM (Byte Order Mark) qui peut être présent au début
            const cleanResponseText = responseText.replace(/^\uFEFF/, '').trim();

            if (!cleanResponseText.startsWith('{') && !cleanResponseText.startsWith('[')) {
                // Erreur silencieuse
                return null;
            }
            
            const parsed = JSON.parse(cleanResponseText);
            const payload = Array.isArray(parsed?.data) ? parsed.data : parsed;
            return payload;
            
        } catch (error) {
            return this.handleFetchError(error);
        }
    }

    async loadExcursionnistesPays(filters, paramsRange = '', signal) {
        try {
            const params = new URLSearchParams({
                annee: filters.year,
                periode: filters.period,
                zone: filters.zone,
                limit: 5
            });
            if (filters.customStart && filters.customEnd) { params.set('debut', filters.customStart); params.set('fin', filters.customEnd); }

            const url = `api/v2/infographie/pays-excursionnistes?${params}`;
            const response = await fetch(url, { signal });

            // Gérer le BOM (Byte Order Mark) qui peut être présent au début
            const responseText = await response.text();
            const cleanResponseText = responseText.replace(/^\uFEFF/, '').trim();

            if (!cleanResponseText.startsWith('{') && !cleanResponseText.startsWith('[')) {
                return null;
            }

            const data = JSON.parse(cleanResponseText);

            if (data.error) {
                throw new Error(data.error);
            }

            const payload = Array.isArray(data?.data) ? data.data : data;
            return payload;
        } catch (error) {
            return this.handleFetchError(error);
        }
    }

    async loadNuiteesDepartements(filters, paramsRange = '', signal) {
        try {
            const params = new URLSearchParams({
                annee: filters.year,
                periode: filters.period,
                zone: filters.zone,
                limit: 15
            });
            if (filters.customStart && filters.customEnd) { params.set('debut', filters.customStart); params.set('fin', filters.customEnd); }

            const url = `api/v2/infographie/departements-touristes?${params}`;
            const response = await fetch(url, { signal });
            const responseText = await response.text();

            // Gérer le BOM
            const cleanResponseText = responseText.replace(/^\uFEFF/, '').trim();

            const data = JSON.parse(cleanResponseText);

            if (data.error) {
                throw new Error(data.error);
            }

            const payload = Array.isArray(data?.data) ? data.data : data;
            return payload;
        } catch (error) {
            return this.handleFetchError(error);
        }
    }

    async loadStayDistribution(filters, paramsRange = '', signal) {
        try {
            const params = new URLSearchParams({
                annee: filters.year,
                periode: filters.period,
                zone: filters.zone
            });
            if (filters.customStart && filters.customEnd) { params.set('debut', filters.customStart); params.set('fin', filters.customEnd); }
            const url = `api/infographie/infographie_duree_sejour.php?${params}`;
            const response = await fetch(url, { signal });
            const data = await response.json();
            const payload = Array.isArray(data?.data) ? data.data : data;
            return payload;
        } catch (error) {
            return this.handleFetchError(error);
        }
    }

    async loadExcursionnistesDepartements(filters, paramsRange = '', signal) {
        try {
            const params = new URLSearchParams({
                annee: filters.year,
                periode: filters.period,
                zone: filters.zone,
                limit: 15
            });

            // Ajouter les dates custom si disponibles
            if (filters.customStart && filters.customEnd) {
                params.set('debut', filters.customStart);
                params.set('fin', filters.customEnd);
            }

            const url = `api/v2/infographie/departements-excursionnistes?${params}`;
            const response = await fetch(url, { signal });
            const responseText = await response.text();

            // Gérer le BOM
            const cleanResponseText = responseText.replace(/^\uFEFF/, '').trim();

            const data = JSON.parse(cleanResponseText);

            if (data.error) {
                throw new Error(data.error);
            }
            const payload = Array.isArray(data?.data) ? data.data : data;
            return payload;
        } catch (error) {
            return this.handleFetchError(error);
        }
    }

    async loadMobilityDestinations(filters, paramsRange = '', signal) {
        try {
            const params = new URLSearchParams({
                annee: filters.year,
                periode: filters.period,
                zone: filters.zone,
                limit: 10
            });

            // Ajouter les dates custom si disponibles
            if (filters.customStart && filters.customEnd) {
                params.set('debut', filters.customStart);
                params.set('fin', filters.customEnd);
            }

            const url = `api/infographie/infographie_communes_excursion.php?${params}`;
            window.fvLog('[Infographie] 📷 Appel API mobility destinations:', url);
            window.fvLog('[Infographie] 📊 ParamÃ¨tres:', Object.fromEntries(params));

            const response = await fetch(url, { signal });
            window.fvLog('[Infographie] 📡 Statut HTTP:', response.status, response.statusText);
            window.fvLog('[Infographie] 📋 Headers:', Object.fromEntries(response.headers.entries()));

            const data = await response.json();
            window.fvLog('[Infographie] 📦 DonnÃ©es brutes reÃ§ues:', data);
            window.fvLog('[Infographie] 🎯 Destinations trouvÃ©es:', data.destinations?.length || 0);
            window.fvLog('[Infographie] ðŸ“ˆ Total destinations dans la rÃ©ponse:', data.total_destinations || 'N/A');

            if (data.error) {
                throw new Error(data.error);
            }
            return data.destinations || [];
        } catch (error) {
            return this.handleFetchError(error);
        }
    }

    renderInfographie() {
        window.fvLog('[Infographie] 🎨 DÃ©but du rendu de l\'infographie');

        const container = document.getElementById('infographie-container');
        if (!container) {
            console.error('[Infographie] ❌ Container infographie-container non trouvÃ©');
            return;
        }

        window.fvLog('[Infographie] ✅ Container trouvÃ©, destruction des anciens graphiques');
        // DÃ©truire tous les graphiques existants avant de continuer
        this.destroyAllCharts();

        // Masquer le placeholder
        const placeholder = container.querySelector('.infographie-placeholder');
        if (placeholder) {
            placeholder.classList.add('is-hidden');
        }

        // Supprimer le contenu existant
        const existingContent = container.querySelector('.infographie-content');
        if (existingContent) {
            existingContent.remove();
        }

        // VÃ©rifier le template principal
        const template = document.getElementById('infographie-main-template');
        if (!template) {
            // Erreur silencieuse
            container.innerHTML = '<div class="error">Template principal non trouvÃ©</div>';
            return;
        }

        const clone = template.content.cloneNode(true);

        // Remplir les donnÃ©es du template
        try {
            this.populateMainTemplate(clone);
        } catch (error) {
            // Erreur silencieuse
            container.innerHTML = '<div class="error">Erreur lors du remplissage: ' + error.message + '</div>';
            return;
        }

        // Ajouter le contenu au container
        container.appendChild(clone);

        // VÃ©rifier que le contenu a bien Ã©tÃ© ajoutÃ© et l'activer
        const addedContent = container.querySelector('.infographie-content');
        if (addedContent) {
            addedContent.classList.add('active');
        } else {
            // Erreur silencieuse
        }

        // GÃ©nÃ©rer les graphiques immÃ©diatement aprÃ¨s avoir ajoutÃ© le DOM
        // Plus de timeout pour Ã©viter les conflits
        this.generateCharts();
    }

    populateMainTemplate(clone) {
        
        const filters = this.currentData.filters;
        const periodLabel = document.getElementById('exc-period-select')?.options[document.getElementById('exc-period-select').selectedIndex]?.text || filters.period;

        // Remplir le header (nouvelle maquette HeaderTourisme)
        // 1) Ancien systÃ¨me (si prÃ©sent)
        const titleElement = clone.querySelector('[data-title]');
        const subtitleElement = clone.querySelector('[data-subtitle]');
        if (titleElement) {
            titleElement.textContent = `INFOGRAPHIE TOURISTIQUE - ${filters.zone}`;
        }
        if (subtitleElement) {
            subtitleElement.textContent = `${periodLabel} ${filters.year}`;
        }

        // 2) Nouveau header: mettre Ã  jour les lignes centrales si disponibles
        const headerCenter = clone.querySelector('.ht-center');
        if (headerCenter) {
            const line2 = headerCenter.querySelector('.ht-title-line.line2');
            const line3 = headerCenter.querySelector('.ht-title-line.line3');
            if (line2) {
                const urlParams = new URLSearchParams(window.location.search);
                const debut = urlParams.get('debut');
                const fin = urlParams.get('fin');
                const formatFR = (d)=>{
                    try { 
                        const date = this.parseISODate(d) || new Date(d);
                        if (this.isValidDate(date)) {
                            return date.toLocaleDateString('fr-FR', { day:'2-digit', month:'short', year:'numeric' });
                        }
                        return null;
                    } catch(_) { 
                        return null; 
                    }
                };
                if (debut && fin) {
                    const left = formatFR(debut);
                    const right = formatFR(fin);
                    line2.textContent = left && right ? `DU ${left.toUpperCase()} AU ${right.toUpperCase()}` : `${periodLabel} ${filters.year}`.toUpperCase();
                } else {
                    line2.textContent = `${periodLabel} ${filters.year}`.toUpperCase();
                }
            }
            if (line3) line3.textContent = `${filters.zone}`.toUpperCase();
        }

        // Remplir les indicateurs nuitées
        const nuiteesContainer = clone.querySelector('[data-indicators-nuitees]');
        if (nuiteesContainer) {
            this.populateKeyIndicators(nuiteesContainer, 'nuitees');
        } else {
            this.log('populateMainTemplate: nuitees container not found');
        }

        // Remplir les indicateurs excursionnistes
        const excursionnistesContainer = clone.querySelector('[data-indicators-excursionnistes]');
        if (excursionnistesContainer) {
            this.populateKeyIndicators(excursionnistesContainer, 'excursionnistes');
        } else {
            this.log('populateMainTemplate: excursionnistes container not found');
        }

        // Footer : bandeau des partenaires (pas de texte source Ã  remplir)
        
        
    }

    populateKeyIndicators(container, category = null) {
        if (!this.currentData.keyIndicators) {
            // Masquer la section d'indicateurs si pas de donnÃ©es
            const indicatorsSubsection = container.closest('.indicators-subsection');
            if (indicatorsSubsection) {
                indicatorsSubsection.classList.add('is-hidden');
            }
            return;
        }

        // Configuration des indicateurs - Nuitées + Excursionnistes
        const keyIndicatorsConfig = [
            // Indicateurs Nuitées de base
            {
                numero: 1,
                title: "Nuitées totales (FranÃ§ais + International)",
                icon: "fa-solid fa-bed",
                unit: "Nuitées",
                defaultRemark: "Touristes NonLocaux + Etrangers",
                category: "nuitees"
            },
            {
                numero: 2,
                title: "Nuitées françaises",
                icon: "fa-solid fa-flag",
                unit: "Nuitées",
                defaultRemark: "Touristes NonLocaux",
                category: "nuitees"
            },
            {
                numero: 3,
                title: "Nuitées internationales",
                icon: "fa-solid fa-globe",
                unit: "Nuitées",
                defaultRemark: "Touristes Etrangers",
                category: "nuitees"
            },
            // Indicateurs Nuitées Printemps (6-9)
            {
                numero: 6,
                title: "Weekend de PÃ¢ques",
                icon: "fa-solid fa-rabbit",
                unit: "Nuitées",
                defaultRemark: "Nuitées weekend de PÃ¢ques",
                category: "nuitees"
            },
            {
                numero: 7,
                title: "1er mai",
                icon: "fa-solid fa-seedling",
                unit: "Nuitées",
                defaultRemark: "Nuitées 1er mai",
                category: "nuitees"
            },
            {
                numero: 8,
                title: "Weekend de l'Ascension",
                icon: "fa-solid fa-dove",
                unit: "Nuitées",
                defaultRemark: "Nuitées weekend de l'Ascension",
                category: "nuitees"
            },
            {
                numero: 9,
                title: "Weekend de la PentecÃ´te",
                icon: "fa-solid fa-fire",
                unit: "Nuitées",
                defaultRemark: "Nuitées weekend de la PentecÃ´te",
                category: "nuitees"
            },
            // Indicateurs Excursionnistes de base
            {
                numero: 15,
                title: "Excursionnistes franÃ§ais",
                icon: "fa-solid fa-person-hiking",
                unit: "PrÃ©sences",
                defaultRemark: "Excursionnistes NonLocaux (FranÃ§ais)",
                category: "excursionnistes"
            },
            {
                numero: 15.5,
                title: "Excursionnistes internationaux",
                icon: "fa-solid fa-globe",
                unit: "PrÃ©sences",
                defaultRemark: "Excursionnistes Etrangers",
                category: "excursionnistes"
            },
            {
                numero: 16,
                title: "Excursionnistes totaux (FranÃ§ais + International)",
                icon: "fa-solid fa-person-hiking",
                unit: "PrÃ©sences",
                defaultRemark: "Excursionnistes NonLocaux + Etrangers",
                category: "excursionnistes"
            },
            {
                numero: 17,
                title: "PrÃ©sences 2e samedi",
                icon: "fa-solid fa-calendar-week",
                unit: "PrÃ©sences",
                defaultRemark: "Excursionnistes 2e samedi du mois",
                category: "excursionnistes"
            },
            {
                numero: 18,
                title: "PrÃ©sences 3e samedi",
                icon: "fa-solid fa-calendar-week",
                unit: "PrÃ©sences",
                defaultRemark: "Excursionnistes 3e samedi du mois",
                category: "excursionnistes"
            },
            // Indicateurs Excursionnistes Printemps (19-22)
            {
                numero: 19,
                title: "Weekend de PÃ¢ques",
                icon: "fa-solid fa-rabbit",
                unit: "PrÃ©sences",
                defaultRemark: "Excursionnistes weekend de PÃ¢ques",
                category: "excursionnistes"
            },
            {
                numero: 20,
                title: "1er mai",
                icon: "fa-solid fa-seedling",
                unit: "PrÃ©sences",
                defaultRemark: "Excursionnistes 1er mai",
                category: "excursionnistes"
            },
            {
                numero: 21,
                title: "Weekend de l'Ascension",
                icon: "fa-solid fa-dove",
                unit: "PrÃ©sences",
                defaultRemark: "Excursionnistes weekend de l'Ascension",
                category: "excursionnistes"
            },
            {
                numero: 22,
                title: "Weekend de la PentecÃ´te",
                icon: "fa-solid fa-fire",
                unit: "PrÃ©sences",
                defaultRemark: "Excursionnistes weekend de la PentecÃ´te",
                category: "excursionnistes"
            },
            // Indicateurs Excursionnistes Ã‰tÃ© (23-24)
            {
                numero: 23,
                title: "14 juillet",
                icon: "fa-solid fa-firework",
                unit: "PrÃ©sences",
                defaultRemark: "Excursionnistes 14 juillet",
                category: "excursionnistes"
            },
            {
                numero: 24,
                title: "15 août",
                icon: "fa-solid fa-sun",
                unit: "PrÃ©sences",
                defaultRemark: "Excursionnistes 15 août",
                category: "excursionnistes"
            }
        ];

        const indicators = this.currentData.keyIndicators.bloc_a || [];
        const currentYear = parseInt(this.currentData.filters.year);

        // Filtrer les indicateurs par catÃ©gorie si spÃ©cifiÃ©e
        const filteredConfig = category ? keyIndicatorsConfig.filter(config => config.category === category) : keyIndicatorsConfig;

        // Vider le container
        container.innerHTML = '';

        let indicatorsAdded = 0;

        filteredConfig.forEach(config => {
            const indicator = this.findIndicator(indicators, config.numero);
            
            // Ne pas afficher les indicateurs conditionnels s'ils ne sont pas dans la rÃ©ponse de l'API
            // - Indicateurs 17, 18 : vacances d'hiver uniquement
            // - Indicateurs 6, 7, 8, 9, 19, 20, 21, 22 : printemps uniquement
            // - Indicateurs 23, 24 : Ã©tÃ© uniquement (14 juillet et 15 août)
            const indicateursConditionnels = [6, 7, 8, 9, 17, 18, 19, 20, 21, 22, 23, 24];
            if (indicateursConditionnels.includes(config.numero) && !indicator) {
                return; // Skip cet indicateur
            }
            
            // VÃ©rifier si la valeur de rÃ©fÃ©rence est nulle (0)
            // ⚠️ TEMPORAIRE: Afficher les indicateurs 2025 mÃªme sans donnÃ©es N-1 pour HAUTES TERRES
            const referenceValue = indicator?.N || 0;
            const isHautesTerres = this.currentFilters?.zone === 'HAUTES TERRES';
            if (referenceValue === 0 && !isHautesTerres) {
                return; // Skip cet indicateur sauf pour HAUTES TERRES
            }
            
            // Utiliser le template
            const template = document.getElementById('key-indicator-template');
            if (!template) {
                // Erreur silencieuse
                container.innerHTML += `<div class="error">Template manquant pour ${config.title}</div>`;
                return;
            }
            
            const clone = template.content.cloneNode(true);
            
            // Remplir les donnÃ©es avec le nouveau template
            const iconElement = clone.querySelector('[data-icon]');
            const titleElement = clone.querySelector('[data-title]');
            const unitElement = clone.querySelector('[data-unit]');
            const historyElement = clone.querySelector('[data-history]');
            
            if (iconElement) iconElement.className += ` ${config.icon}`;
            if (titleElement) titleElement.textContent = config.title;
            if (unitElement) unitElement.textContent = config.unit;
            
            // GÃ©nÃ©rer l'historique des 4 derniÃ¨res années
            if (historyElement) {
                this.generateIndicatorHistory(historyElement, indicator, currentYear);
            }
            
            container.appendChild(clone);
            indicatorsAdded++;
        });
        
        // Masquer la section d'indicateurs si aucun indicateur n'a Ã©tÃ© ajoutÃ©
        if (indicatorsAdded === 0) {
            const indicatorsSubsection = container.closest('.indicators-subsection');
            if (indicatorsSubsection) {
                indicatorsSubsection.classList.add('is-hidden');
            }
        } else {
        }
        
    }
    
    generateIndicatorHistory(container, indicator, currentYear) {
        const referenceYear = indicator?.annee_reference || currentYear;
        
        // Utiliser les nouvelles donnÃ©es de l'API avec les 4 années
        const years = [];
        
        // AnnÃ©e de rÃ©fÃ©rence (année sÃ©lectionnÃ©e)
        years.push({
            year: referenceYear,
            value: indicator?.N || 0,
            evolution: null, // Pas d'Ã©volution pour l'année de rÃ©fÃ©rence
            isReference: true,
            isCurrent: (referenceYear === currentYear)
        });
        
        // AnnÃ©e N-1
        years.push({
            year: referenceYear - 1,
            value: indicator?.N_1 || 0,
            evolution: indicator?.evolution_pct,
            isReference: false,
            isCurrent: false
        });
        
        // AnnÃ©e N-2
        years.push({
            year: referenceYear - 2,
            value: indicator?.N_2 || 0,
            evolution: indicator?.evolution_pct_N1,
            isReference: false,
            isCurrent: false
        });
        
        // AnnÃ©e N-3
        years.push({
            year: referenceYear - 3,
            value: indicator?.N_3 || 0,
            evolution: indicator?.evolution_pct_N2,
            isReference: false,
            isCurrent: false
        });
        
        // GÃ©nÃ©rer le HTML pour chaque année (exclure les années avec valeur 0)
        container.innerHTML = '';
        years.forEach(yearData => {
            // Ne pas afficher les années avec une valeur de 0 (sauf l'année de rÃ©fÃ©rence)
            if (yearData.value === 0 && !yearData.isReference) {
                return;
            }
            
            const yearRow = document.createElement('div');
            yearRow.className = `indicator-year-row ${yearData.isCurrent ? 'current-year' : ''} ${yearData.isReference ? 'reference-year' : ''}`;
            
            // AnnÃ©e (seulement pour les années prÃ©cÃ©dentes)
            if (!yearData.isReference) {
                const yearSpan = document.createElement('span');
                yearSpan.className = 'indicator-year';
                yearSpan.textContent = yearData.year;
                yearRow.appendChild(yearSpan);
            }
            
            // Valeur
            const valueSpan = document.createElement('span');
            valueSpan.className = 'indicator-value';
                            valueSpan.textContent = this.formatNumber(yearData.value);
            
            // Ã‰volution
            const evolutionSpan = document.createElement('span');
            evolutionSpan.className = 'indicator-evolution';
            
            if (yearData.isReference) {
                evolutionSpan.textContent = 'Référence';
                evolutionSpan.classList.add('reference');
            } else if (yearData.evolution !== null && yearData.evolution !== undefined) {
                    const symbol = this.getVariationSymbol(yearData.evolution);
                    const formattedEvolution = this.formatPercentage(Math.abs(yearData.evolution));
                    evolutionSpan.textContent = `${symbol} ${formattedEvolution}`;
                evolutionSpan.classList.add(yearData.evolution >= 0 ? 'positive' : 'negative');
            } else {
                // Pas de donnÃ©es
                evolutionSpan.textContent = yearData.value > 0 ? '--' : '--';
                evolutionSpan.classList.add('neutral');
            }
            
            yearRow.appendChild(valueSpan);
            yearRow.appendChild(evolutionSpan);
            
            container.appendChild(yearRow);
        });
    }

    // MÃ©thode utilitaire pour trouver un indicateur par numÃ©ro
    findIndicator(indicators, numero) {
        return indicators.find(ind => ind.numero === numero);
    }

    // MÃ©thode utilitaire pour formater les nombres au format franÃ§ais
    formatNumber(num) {
        if (num === null || num === undefined || isNaN(num)) return '0';
        
        const value = parseInt(num);
        
        if (value >= 1000000) {
            const millions = (value / 1000000).toFixed(1);
            return millions.replace('.', ',') + ' M';
        } else if (value >= 1000) {
            const milliers = (value / 1000).toFixed(1);
            return milliers.replace('.', ',') + ' k';
        } else {
            return value.toString();
        }
    }

    // MÃ©thode pour formater les nombres avec sÃ©parateurs de milliers (format franÃ§ais)
    formatNumberWithSeparators(num) {
        if (num === null || num === undefined || isNaN(num)) return '0';
        
        const value = parseInt(num);
        return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00A0');
    }

    // MÃ©thode pour afficher un message d'erreur sur un canvas de graphique
    showChartError(canvas, message) {
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        // Effacer le canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Configuration du texte d'erreur
        ctx.fillStyle = '#a0a8b8';
        ctx.font = '14px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        // Centrer le message
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;

        // Afficher le message sur plusieurs lignes si nÃ©cessaire
        const maxWidth = canvas.width * 0.8;
        const words = message.split(' ');
        let line = '';
        let lines = [];
        let y = centerY - ((words.length / 2) * 20);

        for (let i = 0; i < words.length; i++) {
            const testLine = line + words[i] + ' ';
            const metrics = ctx.measureText(testLine);
            if (metrics.width > maxWidth && i > 0) {
                lines.push(line);
                line = words[i] + ' ';
            } else {
                line = testLine;
            }
        }
        lines.push(line);

        // Afficher chaque ligne
        lines.forEach((line, index) => {
            ctx.fillText(line.trim(), centerX, y + (index * 20));
        });
    }

    // MÃ©thode pour formater les pourcentages au format franÃ§ais
    formatPercentage(num) {
        if (num === null || num === undefined || isNaN(num)) return '0%';
        
        const value = parseFloat(num);
        return value.toFixed(1).replace('.', ',') + '%';
    }

    // MÃ©thode pour obtenir le symbole de variation (flÃ¨che)
    getVariationSymbol(evolution) {
        if (evolution === null || evolution === undefined || isNaN(evolution)) return '';
        
        const value = parseFloat(evolution);
        if (value > 0) return '↑';
        if (value < 0) return '↓';
        return '→';
    }



    generateCharts() {
        
        // RÃ©initialiser l'affichage de tous les conteneurs de graphiques
        this.resetChartContainers();
        
        // GÃ©nÃ©rer les graphiques d'origines
        this.updateLoadingText('GÃ©nÃ©ration des graphiques d\'origines...');
        this.generateNuiteesOriginCharts();
        
        this.generateExcursionnistesOriginCharts();

        // GÃ©nÃ©rer le graphique de durÃ©e de sÃ©jour combinÃ© (FranÃ§ais vs International)
        this.updateLoadingText('GÃ©nÃ©ration du graphique de durÃ©e de sÃ©jour...');
        this.generateStayDistributionCombined();

        // GÃ©nÃ©rer le graphique de mobilitÃ© interne (destinations touristiques)
        window.fvLog('[Infographie] 🚀 Appel de generateMobilityDestinationsChart');
        this.updateLoadingText('GÃ©nÃ©ration du graphique de mobilitÃ© interne...');
        this.generateMobilityDestinationsChart();
        
        // RÃ©organiser les grilles de durÃ©e de sÃ©jour si nÃ©cessaire
        this.updateLoadingText('Finalisation de l\'infographie...');
        this.reorganizeStayGrid();
        
        // VÃ©rification finale de l'état des Ã©lÃ©ments
        this.logFinalState();
        
        // Forcer le masquage des titres si nÃ©cessaire
        this.forceHideEmptyTitles();
    }



    // Nouveau: graphique 100% empilÃ© avec 2 barres (FranÃ§ais, International) pour l'infographie
    generateStayDistributionCombined() {
        try {
            const canvas = document.getElementById('infographie-stay-distribution');
            if (!canvas) return;

            // RÃ©cupÃ©rer les couleurs du thÃ¨me
            const colors = this.getThemeColors();

            // Ajouter l'indicateur d'unitÃ© pour le graphique de durÃ©e de sÃ©jour
            const chartContainer = canvas.closest('.chart-container');
            if (chartContainer) {
                const chartHeader = chartContainer.querySelector('.chart-header');
                if (chartHeader) {
                    // Supprimer l'ancien indicateur d'unitÃ© s'il existe
                    const existingUnitIndicator = chartContainer.querySelector('.chart-unit-indicator');
                    if (existingUnitIndicator) {
                        existingUnitIndicator.remove();
                    }

                    // CrÃ©er le nouvel indicateur d'unitÃ©
                    const unitIndicator = document.createElement('div');
                    unitIndicator.className = 'chart-unit-indicator';
                    unitIndicator.textContent = '%';
                    chartHeader.appendChild(unitIndicator);
                }
            }

            // Utiliser les donnÃ©es dÃ©diÃ©es de l'infographie
            const fr = Array.isArray(this.currentData?.stayDistribution?.stay_distribution_fr) ? this.currentData.stayDistribution.stay_distribution_fr : [];
            const intl = Array.isArray(this.currentData?.stayDistribution?.stay_distribution_intl) ? this.currentData.stayDistribution.stay_distribution_intl : [];

            if (!fr.length && !intl.length) {
                // Masquer complètement le conteneur du graphique
                const chartContainer = canvas.closest('.chart-container');
                if (chartContainer) {
                    chartContainer.classList.add('is-hidden');
                }
                return;
            }

            // Construire la liste des durÃ©es
            const dureesSet = new Set();
            fr.forEach(d => dureesSet.add(d.duree));
            intl.forEach(d => dureesSet.add(d.duree));
            const allDurees = Array.from(dureesSet);

            // Classer par importance globale (FranÃ§ais + International)
            const scoreByDuree = {};
            allDurees.forEach(duree => {
                const frPart = Number(fr.find(x => x.duree === duree)?.part_pct ?? 0);
                const intlPart = Number(intl.find(x => x.duree === duree)?.part_pct ?? 0);
                scoreByDuree[duree] = frPart + intlPart;
            });
            const sorted = allDurees.sort((a,b) => (scoreByDuree[b] - scoreByDuree[a]));
            const topLimit = 5;
            const topDurees = sorted.slice(0, topLimit);
            const otherDurees = sorted.slice(topLimit);

            const aggregateItems = (arr, durees) => {
                let vol = 0, volN1 = 0, part = 0;
                durees.forEach(d => {
                    const it = arr.find(x => x.duree === d);
                    if (!it) return;
                    vol += Number(it.volume ?? 0);
                    volN1 += Number(it.volume_n1 ?? 0);
                    part += Number(it.part_pct ?? 0);
                });
                const delta = volN1 > 0 ? ((vol - volN1) / volN1) * 100 : null;
                return { duree: 'Autres', volume: vol, volume_n1: volN1, part_pct: part, delta_pct: (delta!=null? Number(delta.toFixed(1)) : null) };
            };

            const datasets = [];
            topDurees.forEach((duree, i) => {
                const frItem = fr.find(x => x.duree === duree);
                const intlItem = intl.find(x => x.duree === duree);
                const baseColor = CHART_COLORS[i % CHART_COLORS.length];
                datasets.push({
                    label: duree,
                    data: [
                        frItem && frItem.part_pct != null ? Number(frItem.part_pct) : 0,
                        intlItem && intlItem.part_pct != null ? Number(intlItem.part_pct) : 0
                    ],
                    backgroundColor: baseColor,
                    borderColor: this.hexToRgba(baseColor, 1),
                    borderWidth: 1,
                    rawItems: [frItem || null, intlItem || null]
                });
            });

            if (otherDurees.length > 0) {
                const frAgg = aggregateItems(fr, otherDurees);
                const intlAgg = aggregateItems(intl, otherDurees);
                const frDetails = otherDurees.map(d => fr.find(x => x.duree === d)).filter(Boolean);
                const intlDetails = otherDurees.map(d => intl.find(x => x.duree === d)).filter(Boolean);
                const idx = datasets.length;
                const baseColor = CHART_COLORS[idx % CHART_COLORS.length];
                datasets.push({
                    label: 'Autres',
                    data: [Number(frAgg.part_pct || 0), Number(intlAgg.part_pct || 0)],
                    backgroundColor: baseColor,
                    borderColor: this.hexToRgba(baseColor, 1),
                    borderWidth: 1,
                    rawItems: [frAgg, intlAgg],
                    otherLabels: otherDurees,
                    otherDetails: { fr: frDetails, intl: intlDetails }
                });
            }

            const chartKey = 'infographieStayDistributionChart';
            if (this.chartInstances[chartKey]) {
                this.chartInstances[chartKey].destroy();
            }

            this.chartInstances[chartKey] = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: { labels: ['FranÃ§ais', 'International'], datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    layout: {
                        padding: {
                            top: 20,
                            bottom: 20,
                            left: 20,
                            right: 20
                        }
                    },
                    plugins: {
                        legend: { 
                            display: true, 
                            position: 'top', 
                            labels: { 
                                color: colors.textPrimary, // Utiliser la variable CSS
                                font: { size: 11, weight: '600' }, // Police plus grosse et plus grasse
                                usePointStyle: true, 
                                boxWidth: 12,
                                padding: 12
                            } 
                        },
                        tooltip: {
                            enabled: true,
                            backgroundColor: this.hexToRgba(colors.cardBg, 0.95),
                            titleColor: colors.primary,
                            titleFont: { weight: 'bold', size: 12 },
                            bodyColor: colors.textPrimary,
                            borderColor: this.hexToRgba(colors.primary, 0.5),
                            borderWidth: 1,
                            padding: 10,
                            displayColors: true,
                            callbacks: {
                                title: (items) => items?.[0]?.label ?? '',
                                label: (ctx) => {
                                    const ds = ctx.dataset;
                                    const value = ctx.parsed.x ?? ctx.parsed.y ?? ctx.parsed;
                                    const raw = Array.isArray(ds.rawItems) ? ds.rawItems[ctx.dataIndex] : null;
                                    const lines = [
                                        `${ds.label}`,
                                        `Part: ${Number(value).toFixed(1)}%`
                                    ];
                                    if (raw) {
                                        if (raw.volume !== undefined && raw.volume !== null) lines.push(`Nuitées: ${this.formatNumber(Number(raw.volume))}`);
                                        if (raw.delta_pct !== undefined && raw.delta_pct !== null) {
                                        const evolution = Number(raw.delta_pct);
                                        const symbol = this.getVariationSymbol(evolution);
                                        const formattedEvolution = this.formatPercentage(Math.abs(evolution));
                                        lines.push(`${symbol} ${formattedEvolution} vs rÃ©fÃ©rence`);
                                    }
                                    if (raw.volume_n1 !== undefined && raw.volume_n1 !== null) lines.push(`N-1: ${this.formatNumber(Number(raw.volume_n1))}`);
                                    }
                                    if (ds.label === 'Autres' && Array.isArray(ds.otherLabels) && ds.otherLabels.length) {
                                        lines.push(`Inclut: ${ds.otherLabels.join(', ')}`);
                                        const group = ctx.dataIndex === 0 ? (ds.otherDetails?.fr || []) : (ds.otherDetails?.intl || []);
                                        if (group.length) {
                                            lines.push('– DÃ©tails –');
                                            group.slice(0, 8).forEach(item => {
                                                const name = item?.duree ?? '';
                                                const part = item?.part_pct != null ? this.formatPercentage(Number(item.part_pct)) : 'n/a';
                                                const vol = item?.volume != null ? this.formatNumber(Number(item.volume)) : '0';
                                                const n1 = item?.volume_n1 != null ? this.formatNumber(Number(item.volume_n1)) : '0';
                                                const delta = item?.delta_pct != null ? (() => {
                                                    const evolution = Number(item.delta_pct);
                                                    const symbol = this.getVariationSymbol(evolution);
                                                    const formattedEvolution = this.formatPercentage(Math.abs(evolution));
                                                    return `${symbol} ${formattedEvolution}`;
                                                })() : 'n/a';
                                                lines.push(`${name}: ${part} | ${vol} (N) | ${n1} (N-1) | ${delta}`);
                                            });
                                            if (group.length > 8) lines.push(`(+${group.length - 8} autresâ€¦)`);
                                        }
                                    }
                                    return lines;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { 
                            beginAtZero: true, 
                            min: 0, 
                            max: 100, 
                            stacked: true, 
                            ticks: { 
                                color: colors.textSecondary, // Utiliser la variable CSS
                                font: { size: 10, weight: '500' },
                                callback: v => `${Number(v)}%` 
                            } 
                        },
                        y: { 
                            stacked: true, 
                            grid: { display: false }, 
                            ticks: { 
                                color: colors.textPrimary, // Utiliser la variable CSS
                                font: { size: 11, weight: '500' }
                            } 
                        }
                    }
                }
            });
        } catch (e) {
            // Erreur silencieuse
        }
    }

    generateNuiteesOriginCharts() {
        // RÃ©cupÃ©rer les couleurs du thÃ¨me
        const colors = this.getThemeColors();
        
        // GÃ©nÃ©rer les 3 graphiques sÃ©parÃ©ment
        this.generateOriginChart('nuitees-departements-chart', 'nuiteesOrigins', 'departements', colors.primary, 15);
        this.generateOriginChart('nuitees-regions-chart', 'nuiteesOrigins', 'regions', colors.primary, 5);
        this.generateOriginChart('nuitees-pays-chart', 'nuiteesOrigins', 'pays', colors.primary, 5);
        
        // RÃ©organiser la grille aprÃ¨s masquage des graphiques vides
        this.reorganizeOriginGrid('nuitees');
    }

    generateExcursionnistesOriginCharts() {
        // Récupérer les couleurs du thème
        const colors = this.getThemeColors();

        // Générer les 3 graphiques séparément avec la couleur d'origine #667eea (bleu)
        this.generateOriginChart('excursionnistes-departements-chart', 'excursionnistesOrigins', 'departements', '#667eea', 15);
        this.generateOriginChart('excursionnistes-regions-chart', 'excursionnistesOrigins', 'regions', '#667eea', 5);
        this.generateOriginChart('excursionnistes-pays-chart', 'excursionnistesOrigins', 'pays', '#667eea', 5);

        // Réorganiser la grille après masquage des graphiques vides
        this.reorganizeOriginGrid('excursionnistes');
    }

    generateOriginChart(canvasId, dataCategory, dataType, color, limit) {
        // Récupérer les couleurs du thème
        const colors = this.getThemeColors();
        
        // GÃ©nÃ©rer automatiquement le label basÃ© sur le type
        const typeLabels = {
            'departements': 'DÃ©partements',
            'regions': 'RÃ©gions', 
            'pays': 'Pays'
        };
        const label = typeLabels[dataType] || 'DonnÃ©es';
        
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            // Erreur silencieuse
            return;
        }

        // Ajouter l'indicateur d'unitÃ© dans l'en-tÃªte du graphique
        const chartContainer = canvas.closest('.chart-container');
        if (chartContainer) {
            const chartHeader = chartContainer.querySelector('.chart-header');
            if (chartHeader) {
                // Supprimer l'ancien indicateur d'unitÃ© s'il existe
                const existingUnitIndicator = chartContainer.querySelector('.chart-unit-indicator');
                if (existingUnitIndicator) {
                    existingUnitIndicator.remove();
                }

                // CrÃ©er le nouvel indicateur d'unitÃ©
                const unitIndicator = document.createElement('div');
                unitIndicator.className = 'chart-unit-indicator';
                const unit = dataCategory.includes('nuitees') ? 'nuitées' : 'prÃ©sences';
                unitIndicator.textContent = unit;
                chartHeader.appendChild(unitIndicator);
            }
        }

        // Récupérer les données selon le type et la catégorie
        let data;
        if (dataCategory === 'nuiteesOrigins') {
            if (dataType === 'departements') {
                data = this.currentData.nuiteesOrigins?.departements;
            } else if (dataType === 'regions') {
                data = this.currentData.nuiteesOrigins?.regions;
            } else if (dataType === 'pays') {
                data = this.currentData.nuiteesOrigins?.pays;
            }
        } else if (dataCategory === 'excursionnistesOrigins') {
            if (dataType === 'departements') {
                data = this.currentData.excursionnistesOrigins?.departements;
            } else if (dataType === 'regions') {
                data = this.currentData.excursionnistesOrigins?.regions;
            } else if (dataType === 'pays') {
                data = this.currentData.excursionnistesOrigins?.pays;
            }
        }

        if (!data || !Array.isArray(data) || data.length === 0) {
            // Masquer complètement le conteneur du graphique
            const chartContainer = canvas.closest('.chart-container');
            if (chartContainer) {
                chartContainer.classList.add('is-hidden');
            }
            return;
        }

        // Traitement des donnÃ©es pour Chart.js avec comparaison N vs N-1
        const chartData = this.processOriginDataComparison(data, dataType, limit);
        
        // VÃ©rifier si on a des donnÃ©es valides aprÃ¨s traitement
        if (!chartData.labels || chartData.labels.length === 0 || chartData.currentValues.every(v => v === 0)) {

            // Masquer complètement le conteneur du graphique
            const chartContainer = canvas.closest('.chart-container');
            if (chartContainer) {
                chartContainer.classList.add('is-hidden');
            }
            return;
        }
        
        // DÃ©truire le graphique existant s'il existe
        const chartKey = `${canvasId}Chart`;
        if (this.chartInstances[chartKey]) {
            this.chartInstances[chartKey].destroy();
        }

        // CrÃ©er le graphique en barres horizontales avec comparaison
        this.chartInstances[chartKey] = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: (() => {
                    const datasets = [{
                        label: `${chartData.currentYear}`,
                        data: chartData.currentValues,
                        backgroundColor: color,
                        borderColor: color,
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false
                    }];

                    // N'ajouter le dataset N-1 que s'il y a au moins une valeur non nulle
                    if (!chartData.previousValues.every(v => v === 0)) {
                        datasets.push({
                            label: `${chartData.previousYear}`,
                        data: chartData.previousValues,
                        backgroundColor: this.hexToRgba(color, 0.6), // Version transparente
                        borderColor: this.hexToRgba(color, 0.8),
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false
                        });
                    }

                    return datasets;
                })()
            },
            plugins: [
                {
                    id: 'multilineLabels',
                    afterDraw: (chart) => {
                        const ctx = chart.ctx;
                        const yScale = chart.scales.y;
                        const isRegionsChart = chart.canvas.id.includes('regions');
                        
                        if (!isRegionsChart) return;
                        
                        chart.data.labels.forEach((label, index) => {
                            if (Array.isArray(label) && label.length > 1) {
                                const y = yScale.getPixelForTick(index);
                                const x = yScale.left - 8; // Position Ã  gauche de l'axe avec marge
                                
                                ctx.save();
                                ctx.fillStyle = colors.textPrimary; // Utiliser la variable CSS
                                ctx.font = '11px -apple-system, BlinkMacSystemFont, sans-serif';
                                ctx.textAlign = 'right';
                                ctx.textBaseline = 'middle';
                                
                                // Afficher la deuxiÃ¨me ligne de l'Ã©tiquette
                                ctx.fillText(label[1], x, y + 12);
                                ctx.restore();
                            }
                        });
                    }
                },
                {
                    id: 'barEndValues',
                    afterDatasetsDraw: (chart) => {
                        const {ctx, chartArea, scales: {x, y}} = chart;
                        const ds = chart.data.datasets[0];
                        if (!ds) return;

                        // Capture une rÃ©fÃ©rence sÃ»re Ã  la fonction de formatage
                        const fmt = this.formatNumber.bind(this);

                        ctx.save();
                        ctx.fillStyle = colors.textPrimary; // Utiliser la variable CSS
                        ctx.font = 'bold 10px -apple-system, BlinkMacSystemFont, sans-serif';
                        ctx.textAlign = 'left';
                        ctx.textBaseline = 'middle';
                        ctx.shadowColor = 'rgba(0, 0, 0, 0.7)';
                        ctx.shadowBlur = 2;
                        ctx.shadowOffsetX = 1;
                        ctx.shadowOffsetY = 1;

                        ds.data.forEach((v, i) => {
                            if (!v) return;
                            const yPix = y.getPixelForValue(i);
                            const xPix = x.getPixelForValue(v) + 4; // RÃ©duit de 8px Ã  4px
                            // Utiliser l'espace de padding dÃ©fini dans layout.padding.right
                            const maxX = chartArea.right + 20; // RÃ©duit de 40px Ã  20px
                            const xClamped = Math.min(xPix, maxX);
                            ctx.fillText(fmt(v), xClamped, yPix);
                        });
                        ctx.restore();
                    }
                }
            ],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y', // Barres horizontales
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10,
                        left: 'auto',
                        right: 6 // RÃ©duit de 10px Ã  6px pour rapprocher les chiffres
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            color: colors.textPrimary, // Utiliser la variable CSS
                            font: { size: 11, weight: '600' }, // Police plus grosse et plus grasse
                            padding: 8,
                            usePointStyle: false,
                            boxWidth: 6,
                            boxHeight: 6,
                            generateLabels: function(chart) {
                                const datasets = chart.data.datasets;
                                if (datasets.length >= 2) {
                                    return [
                                        {
                                            text: `${chartData.currentYear}`,
                                            fillStyle: datasets[0].backgroundColor,
                                            strokeStyle: datasets[0].borderColor,
                                            lineWidth: 1,
                                            datasetIndex: 0
                                        },
                                        {
                                            text: `${chartData.previousYear}`,
                                            fillStyle: datasets[1].backgroundColor,
                                            strokeStyle: datasets[1].borderColor,
                                            lineWidth: 1,
                                            datasetIndex: 1
                                        }
                                    ];
                                }
                                return Chart.defaults.plugins.legend.labels.generateLabels(chart);
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: this.hexToRgba(colors.cardBg, 0.95),
                        titleColor: color,
                        titleFont: { weight: 'bold', size: 12 },
                        bodyColor: colors.textPrimary,
                        borderColor: this.hexToRgba(color, 0.8),
                        borderWidth: 1,
                        padding: 10,
                        displayColors: true,
                        callbacks: {
                            title: (ctx) => ctx[0]?.label || '',
                            label: (context) => {
                                const value = context.parsed.x || 0;
                                const unit = dataCategory.includes('nuitees') ? 'nuitées' : 'prÃ©sences';
                                const year = context.dataset.label || chartData.currentYear;
                                return `${year}: ${this.formatNumber(value)} ${unit}`;
                            },
                            afterLabel: (context) => {
                                // Calculer l'Ã©volution si on a les deux valeurs
                                const currentIndex = context.datasetIndex === 0 ? 0 : 1;
                                const otherIndex = currentIndex === 0 ? 1 : 0;
                                const currentValue = context.parsed.x || 0;
                                const otherValue = context.chart.data.datasets[otherIndex]?.data[context.dataIndex] || 0;
                                
                                if (currentValue > 0 && otherValue > 0 && currentIndex === 0) {
                                    const evolution = ((currentValue - otherValue) / otherValue * 100);
                                    const symbol = this.getVariationSymbol(evolution);
                                    const formattedEvolution = this.formatPercentage(Math.abs(evolution));
                                    return `${symbol} ${formattedEvolution} vs rÃ©fÃ©rence`;
                                }
                                return '';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        suggestedMax: (() => {
                            // Calculer le maximum entre toutes les valeurs N et N-1
                            const maxCurr = Math.max(...chartData.currentValues, 0);
                            const maxPrev = Math.max(...chartData.previousValues, 0);
                            const max = Math.max(maxCurr, maxPrev);
                            
                            // Fonction pour calculer un "nice max"
                            const niceMax = (max) => {
                                const p = Math.pow(10, Math.floor(Math.log10(max || 1)));
                                return Math.ceil(max / p) * p;
                            };
                            
                            return niceMax(max);
                        })(),
                        grid: {
                            color: 'rgba(160, 168, 184, 0.1)'
                        },
                        ticks: {
                            color: colors.textSecondary, // Utiliser la variable CSS
                            font: { size: 10, weight: '500' }, // Police plus grosse et plus grasse
                            maxTicksLimit: 6,
                            callback: (value) => this.formatNumber(value)
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        },
                        afterFit: function(scale) {
                            const isRegionsChart = scale.chart.canvas.id.includes('regions');
                            const minWidth = isRegionsChart ? 80 : 60; // RÃ©duit de 50% pour rapprocher les Ã©tiquettes
                            scale.width = Math.max(scale.width, minWidth);
                        },
                        ticks: {
                            color: colors.textPrimary, // Utiliser la variable CSS
                            font: { size: limit > 10 ? 10 : 12, weight: '500' }, // Police plus grosse et plus grasse
                            maxTicksLimit: limit > 10 ? 15 : 8,
                            padding: 6, // RÃ©duit de 12px Ã  6px (50% de rÃ©duction)
                            autoSkip: false, // DÃ©sactive l'auto-skip des catÃ©gories
                            callback: function(value, index, values) {
                                const label = this.getLabelForValue(value);
                                
                                if (Array.isArray(label)) {
                                    return label[0];
                                }
                                
                                return label;
                            }
                        }
                    }
                },
                animation: {
                    duration: 800,
                    easing: 'easeOutQuart',
                    delay: (context) => context.dataIndex * 30
                }
            }
        });
    }

    /**
     * Raccourcit intelligemment les noms de rÃ©gions pour l'affichage
     */
    shortenRegionName(regionName) {
        if (!regionName || regionName.length <= 12) return regionName;
        
        const abbreviations = {
            'AUVERGNE-RHÃ”NE-ALPES': 'AUVERGNE-RHÃ”NE-ALPES', // Garder tel quel mais on va ajuster l'affichage
            'BOURGOGNE-FRANCHE-COMTÃ‰': 'BOURGOGNE-F.COMTÃ‰',
            'CENTRE-VAL DE LOIRE': 'CENTRE-VAL LOIRE',
            'GRAND EST': 'GRAND EST',
            'HAUTS-DE-FRANCE': 'HAUTS-DE-FRANCE',
            'ILE-DE-FRANCE': 'ILE-DE-FRANCE',
            'NORMANDIE': 'NORMANDIE',
            'NOUVELLE AQUITAINE': 'NOUVELLE AQUITAINE',
            'OCCITANIE': 'OCCITANIE',
            'PAYS DE LA LOIRE': 'PAYS DE LA LOIRE',
            'PROVENCE-ALPES-CÃ”TE D\'AZUR': 'PACA',
            'BRETAGNE': 'BRETAGNE',
            'CORSE': 'CORSE'
        };
        
        return abbreviations[regionName] || regionName;
    }

    /**
     * Divise un nom de rÃ©gion en plusieurs lignes si nÃ©cessaire
     */
    wrapRegionName(regionName) {
        if (!regionName || regionName.length <= 16) return [regionName];
        
        const specialCases = {
            'AUVERGNE-RHÃ”NE-ALPES': ['AUVERGNE', 'RHÃ”NE-ALPES'],
            'BOURGOGNE-FRANCHE-COMTÃ‰': ['BOURGOGNE', 'FRANCHE-COMTÃ‰'],
            'CENTRE-VAL DE LOIRE': ['CENTRE', 'VAL DE LOIRE'],
            'NOUVELLE AQUITAINE': ['NOUVELLE', 'AQUITAINE'],
            'PROVENCE-ALPES-CÃ”TE D\'AZUR': ['PROVENCE-ALPES', 'CÃ”TE D\'AZUR'],
            'PAYS DE LA LOIRE': ['PAYS DE', 'LA LOIRE']
        };
        
        if (specialCases[regionName]) {
            return specialCases[regionName];
        }
        
        if (regionName.includes('-')) {
            const parts = regionName.split('-');
            if (parts.length === 2) return parts;
        }
        
        if (regionName.includes(' ')) {
            const words = regionName.split(' ');
            if (words.length === 2) return words;
            if (words.length > 2) {
                const mid = Math.ceil(words.length / 2);
                return [
                    words.slice(0, mid).join(' '),
                    words.slice(mid).join(' ')
                ];
            }
        }
        
        return [regionName];
    }

    processOriginData(data, dataType, limit) {
        if (!data || !Array.isArray(data)) {
            return { labels: [], values: [] };
        }

        const sortedData = data
            .filter(item => item && (item.n_nuitees > 0 || item.volume > 0 || item.n_presences > 0 || item.total_presences > 0))
            .sort((a, b) => (b.n_nuitees || b.volume || b.n_presences || b.total_presences || 0) - (a.n_nuitees || a.volume || a.n_presences || a.total_presences || 0))
            .slice(0, limit);

        const labels = [];
        const values = [];

        sortedData.forEach(item => {
            let label = '';
            let value = 0;

            if (dataType === 'departements') {
                label = item.departement_origine || item.nom_departement || item.departement || 'Inconnu';
                value = item.n_nuitees || item.volume || item.n_presences || item.total_presences || 0;
            } else if (dataType === 'regions') {
                const rawLabel = item.region_origine || item.nom_region || item.nom_nouvelle_region || 'Inconnu';
                const wrappedLabel = this.wrapRegionName(rawLabel);
                label = wrappedLabel.length > 1 ? wrappedLabel : rawLabel;
                value = item.n_nuitees || item.volume || item.n_presences || item.total_presences || 0;
            } else if (dataType === 'pays') {
                label = item.pays_origine || item.nom_pays || 'Inconnu';
                value = item.n_nuitees || item.volume || item.n_presences || item.total_presences || 0;
            }

            if (label && value > 0) {
                labels.push(label);
                values.push(value);
            }
        });

        return { labels, values };
    }

    processOriginDataComparison(data, dataType, limit) {
        if (!data || !Array.isArray(data)) {
            return { 
                labels: [], 
                currentValues: [], 
                previousValues: [],
                currentYear: new Date().getFullYear(),
                previousYear: new Date().getFullYear() - 1
            };
        }

        // Trier les donnÃ©es par valeur dÃ©croissante de l'année courante et limiter
        const sortedData = data
            .filter(item => item && (item.n_nuitees > 0 || item.volume > 0 || item.n_presences > 0 || item.total_presences > 0))
            .sort((a, b) => (b.n_nuitees || b.volume || b.n_presences || b.total_presences || 0) - (a.n_nuitees || a.volume || a.n_presences || a.total_presences || 0))
            .slice(0, limit);

        const labels = [];
        const currentValues = [];
        const previousValues = [];

        // Obtenir les années depuis les filtres actuels
        const currentYear = this.currentData?.filters?.year || new Date().getFullYear();
        const previousYear = currentYear - 1;

        sortedData.forEach(item => {
            let label = '';
            let currentValue = 0;
            let previousValue = 0;

            if (dataType === 'departements') {
                label = item.departement_origine || item.nom_departement || item.departement || 'Inconnu';
                currentValue = item.n_nuitees || item.volume || item.n_presences || item.total_presences || 0;
                previousValue = item.n_nuitees_n1 || item.volume_n1 || item.n_presences_n1 || item.total_presences_n1 || 0;
            } else if (dataType === 'regions') {
                const rawLabel = item.region_origine || item.nom_region || item.nom_nouvelle_region || 'Inconnu';
                const wrappedLabel = this.wrapRegionName(rawLabel);
                label = wrappedLabel.length > 1 ? wrappedLabel : rawLabel;
                currentValue = item.n_nuitees || item.volume || item.n_presences || item.total_presences || 0;
                previousValue = item.n_nuitees_n1 || item.volume_n1 || item.n_presences_n1 || item.total_presences_n1 || 0;
            } else if (dataType === 'pays') {
                label = item.pays_origine || item.nom_pays || 'Inconnu';
                currentValue = item.n_nuitees || item.volume || item.n_presences || item.total_presences || 0;
                previousValue = item.n_nuitees_n1 || item.volume_n1 || item.n_presences_n1 || item.total_presences_n1 || 0;
            }

            if (label && currentValue > 0) {
                labels.push(label);
                currentValues.push(currentValue);
                previousValues.push(previousValue);
            }
        });

        return { 
            labels, 
            currentValues, 
            previousValues,
            currentYear,
            previousYear
        };
    }

    combineOriginsData(regionsData, paysData, type) {
        // Combiner les donnÃ©es des rÃ©gions et pays en un seul tableau
        const combined = [];
        
        // Ajouter les rÃ©gions
        if (regionsData && Array.isArray(regionsData)) {
            regionsData.forEach(item => {
                if (type === 'nuitees') {
                    // Pour les nuitées : nom_region et n_nuitees
                    if (item.nom_region && item.n_nuitees !== undefined) {
                        const wrappedName = this.wrapRegionName(item.nom_region);
                        const displayName = wrappedName.length > 1 ? wrappedName : item.nom_region;
                        combined.push({
                            origine: displayName,
                            valeur: parseInt(item.n_nuitees) || 0
                        });
                    }
                } else {
                    // Pour les excursionnistes : nom_nouvelle_region et n_presences
                    if (item.nom_nouvelle_region && item.n_presences !== undefined) {
                        const wrappedName = this.wrapRegionName(item.nom_nouvelle_region);
                        const displayName = wrappedName.length > 1 ? wrappedName : item.nom_nouvelle_region;
                        combined.push({
                            origine: displayName,
                            valeur: parseInt(item.n_presences) || 0
                        });
                    }
                }
            });
        }
        
        // Ajouter les pays
        if (paysData && Array.isArray(paysData)) {
            paysData.forEach(item => {
                if (type === 'nuitees') {
                    // Pour les nuitées : nom_pays et n_nuitees
                    if (item.nom_pays && item.n_nuitees !== undefined) {
                        combined.push({
                            origine: item.nom_pays,
                            valeur: parseInt(item.n_nuitees) || 0
                        });
                    }
                } else {
                    // Pour les excursionnistes : pays_origine et n_presences
                    if (item.pays_origine && item.n_presences !== undefined) {
                        combined.push({
                            origine: item.pays_origine,
                            valeur: parseInt(item.n_presences) || 0
                        });
                    }
                }
            });
        }
        
        // Trier par valeur dÃ©croissante
        combined.sort((a, b) => b.valeur - a.valeur);
        
        return combined;
    }

    processOriginsData(data, type) {
        // Traiter les donnÃ©es des APIs pour les adapter aux graphiques
        
        // VÃ©rifier la structure des donnÃ©es
        if (!data || !Array.isArray(data)) {
            // Erreur silencieuse
            return { labels: ['Aucune donnÃ©e'], values: [0] };
        }

        // Extraire les labels et valeurs (donnÃ©es dÃ©jÃ  formatÃ©es par combineOriginsData)
        const labels = [];
        const values = [];

        // Limiter aux 8 premiÃ¨res origines pour la lisibilitÃ©
        const limitedData = data.slice(0, 8);
        
        limitedData.forEach(item => {
            if (item.origine && item.valeur !== undefined) {
                labels.push(item.origine);
                values.push(item.valeur);
            }
        });

        return { labels, values };
    }

    // RÃ©initialiser l'affichage de tous les conteneurs de graphiques
    resetChartContainers() {
        
        const allChartContainers = document.querySelectorAll('.chart-container');
        const allOriginsSubsections = document.querySelectorAll('.origins-subsection');
        const allIndicatorsSubsections = document.querySelectorAll('.indicators-subsection');
        const allStaySubsections = document.querySelectorAll('.stay-subsection');
        const allOriginsHeaders = document.querySelectorAll('.origins-header');
        const allStayHeaders = document.querySelectorAll('.stay-header');
        
        // RÃ©afficher tous les conteneurs de graphiques
        allChartContainers.forEach(container => {
            container.classList.remove('is-hidden');
        });
        
        // RÃ©afficher toutes les sections d'origines
        allOriginsSubsections.forEach(section => {
            section.classList.remove('is-hidden');
        });
        
        // RÃ©afficher toutes les sections d'indicateurs
        allIndicatorsSubsections.forEach(section => {
            section.classList.remove('is-hidden');
        });
        
        // RÃ©afficher toutes les sections de durÃ©e de sÃ©jour
        allStaySubsections.forEach(section => {
            section.classList.remove('is-hidden');
        });
        
        // RÃ©afficher tous les titres des sections d'origines
        allOriginsHeaders.forEach(header => {
            header.classList.remove('is-hidden');
        });
        
        // RÃ©afficher tous les titres des sections de durÃ©e de sÃ©jour
        allStayHeaders.forEach(header => {
            header.classList.remove('is-hidden');
        });
    }

    // RÃ©organiser la grille des origines quand certains graphiques sont masquÃ©s
    reorganizeOriginGrid(category) {
        
        // Mapper les catÃ©gories aux vraies classes CSS
        const sectionClass = category === 'nuitees' ? 'tourist-section' : 'excursionist-section';
        const originsGrid = document.querySelector(`.${sectionClass} .origins-grid`);
        if (!originsGrid) {
            return;
        }

        const hiddenCharts = originsGrid.querySelectorAll('.chart-container.is-hidden');
        const totalCharts = originsGrid.querySelectorAll('.chart-container').length;
        const hiddenCount = hiddenCharts.length;

        // Si tous les graphiques sont masquÃ©s, masquer toute la section
        if (hiddenCount === totalCharts) {
            const section = originsGrid.closest('.origins-subsection');
            if (section) {
                section.classList.add('is-hidden');
            }
        } else {
            // Si certains graphiques sont visibles, masquer seulement le titre si tous les graphiques sont masquÃ©s
            const visibleCharts = originsGrid.querySelectorAll('.chart-container:not(.is-hidden)');
            if (visibleCharts.length === 0) {
                const originsHeader = originsGrid.closest('.origins-subsection')?.querySelector('.origins-header');
                if (originsHeader) {
                    originsHeader.classList.add('is-hidden');
                }
            }
        }
        
        // Optimisation : remplacer les sÃ©lecteurs :has() en cascade par des toggles de classe
        this.optimizeGridLayout(originsGrid);
    }

    // MÃ©thode optimisÃ©e pour remplacer les sÃ©lecteurs :has() en cascade
    optimizeGridLayout(grid) {
        const hiddenCharts = grid.querySelectorAll('.chart-container.is-hidden');
        const totalCharts = grid.querySelectorAll('.chart-container').length;
        const hiddenCount = hiddenCharts.length;

        // Supprimer toutes les classes de layout prÃ©cÃ©dentes
        grid.classList.remove('layout-single', 'layout-double', 'layout-full');

        // Appliquer le layout appropriÃ©
        if (hiddenCount === totalCharts) {
            grid.classList.add('layout-empty');
        } else if (hiddenCount === totalCharts - 1) {
            grid.classList.add('layout-single');
        } else if (hiddenCount === totalCharts - 2) {
            grid.classList.add('layout-double');
        } else {
            grid.classList.add('layout-full');
        }
    }

    // RÃ©organiser la grille de durÃ©e de sÃ©jour
    reorganizeStayGrid() {
        
        const stayGrid = document.querySelector('.stay-subsection .origins-grid');
        if (!stayGrid) {
            return;
        }

        const hiddenCharts = stayGrid.querySelectorAll('.chart-container.is-hidden');
        const totalCharts = stayGrid.querySelectorAll('.chart-container').length;
        const hiddenCount = hiddenCharts.length;


        // Si tous les graphiques sont masquÃ©s, masquer toute la section
        if (hiddenCount === totalCharts) {
            const section = stayGrid.closest('.stay-subsection');
            if (section) {
                section.classList.add('is-hidden');
            }
        }
        
        // VÃ©rification supplÃ©mentaire : masquer aussi le titre si aucun graphique n'est visible
        if (hiddenCount === totalCharts) {
            const stayHeader = stayGrid.closest('.stay-subsection')?.querySelector('.stay-header');
            if (stayHeader) {
                stayHeader.classList.add('is-hidden');
            }
        }
        
    }

    // Forcer le masquage des titres vides
    forceHideEmptyTitles() {
        
        // VÃ©rifier toutes les sections d'origines
        const originsSubsections = document.querySelectorAll('.origins-subsection');
        originsSubsections.forEach((section, index) => {
            const visibleCharts = section.querySelectorAll('.chart-container:not(.is-hidden)');
            const originsHeader = section.querySelector('.origins-header');
            
            if (visibleCharts.length === 0 && originsHeader) {
                originsHeader.classList.add('is-hidden');
            }
        });
        
        // VÃ©rifier toutes les sections de durÃ©e de sÃ©jour
        const staySubsections = document.querySelectorAll('.stay-subsection');
        staySubsections.forEach((section, index) => {
            const visibleCharts = section.querySelectorAll('.chart-container:not(.is-hidden)');
            const stayHeader = section.querySelector('.stay-header');
            
            if (visibleCharts.length === 0 && stayHeader) {
                stayHeader.classList.add('is-hidden');
            }
        });
        
        // VÃ©rifier toutes les sections d'indicateurs
        const indicatorsSubsections = document.querySelectorAll('.indicators-subsection');
        indicatorsSubsections.forEach((section, index) => {
            const visibleIndicators = section.querySelectorAll('.indicator-card-compact');
            const indicatorsHeader = section.querySelector('.indicators-header');
            
            if (visibleIndicators.length === 0 && indicatorsHeader) {
                section.classList.add('is-hidden');
            }
        });
        
    }

    // VÃ©rification finale de l'état des Ã©lÃ©ments
    logFinalState() {
        
        // VÃ©rifier les sections d'origines
        const originsSubsections = document.querySelectorAll('.origins-subsection');
        originsSubsections.forEach((section, index) => {            
        });
        
        // VÃ©rifier les sections de durÃ©e de sÃ©jour
        const staySubsections = document.querySelectorAll('.stay-subsection');
        staySubsections.forEach((section, index) => {
            
        });
    }

    showChartPlaceholder(canvas, message) {
        
        // Masquer complètement le conteneur du graphique au lieu d'afficher un message
        const chartContainer = canvas.closest('.chart-container');
        if (chartContainer) {
            chartContainer.classList.add('is-hidden');
        }
    }

    async downloadInfographie() {
        const downloadBtn = document.getElementById('btn-telecharger-infographie');
        if (!downloadBtn || downloadBtn.disabled) return;

        try {
            downloadBtn.disabled = true;
            downloadBtn.innerHTML = '<div class="loading-spinner"></div> <span class="btn-title">TÃ©lÃ©chargement...</span>';
            
            // Afficher l'indicateur de chargement pour le tÃ©lÃ©chargement
            this.showDownloadLoadingIndicator();

            const container = document.querySelector('.infographie-content');
            if (!container) {
                throw new Error('Aucune infographie Ã  tÃ©lÃ©charger');
            }

            // Ã‰tape 1: Convertir tous les graphiques Chart.js en images
            await this.convertChartsToImages();

            // Ã‰tape 2: Attendre que toutes les images soient chargÃ©es
            await new Promise(resolve => setTimeout(resolve, 1000));

            // Ã‰tape 3: Activer le mode export avec les styles CSS identiques Ã  la page web
            container.classList.add('export-mode');

            // Ã‰tape 4: RÃ©cupÃ©rer les couleurs CSS exactes de la page web
            const computedStyle = getComputedStyle(document.documentElement);
            const cardBg = computedStyle.getPropertyValue('--card-bg').trim() || this.getCSSVariable('--card-bg', '#1a1f2c');
            
            // Ã‰tape 5: Forcer l'affichage de tous les Ã©lÃ©ments avec les styles de la page web
            const allElements = container.querySelectorAll('*');
            const originalStyles = new Map();
            
            allElements.forEach((el) => {
                originalStyles.set(el, {
                    display: el.style.display,
                    visibility: el.style.visibility,
                    opacity: el.style.opacity,
                    transform: el.style.transform,
                    transition: el.style.transition,
                    animation: el.style.animation,
                    background: el.style.background,
                    backgroundColor: el.style.backgroundColor,
                    border: el.style.border,
                    boxShadow: el.style.boxShadow
                });
                
                // Appliquer les styles de la page web et Ã©liminer les Ã©lÃ©ments blancs
                if (el.style.display === 'none') el.style.display = 'block';
                el.style.visibility = 'visible';
                el.style.opacity = '1';
                el.style.transform = 'none';
                el.style.transition = 'none';
                el.style.animation = 'none';
                
                // Ã‰liminer tous les backgrounds blancs et transparents gÃªnants
                const lightBg = this.getCSSVariable('--light-bg', '#ffffff');
                if (el.style.backgroundColor === 'white' || 
                    el.style.backgroundColor === '#ffffff' || 
                    el.style.backgroundColor === 'rgba(255,255,255,1)' ||
                    el.style.background === 'white' ||
                    el.style.background === '#ffffff') {
                    el.style.backgroundColor = 'transparent';
                    el.style.background = 'transparent';
                }
                
                // Ã‰liminer les bordures et ombres
                el.style.border = 'none';
                el.style.boxShadow = 'none';
                el.style.outline = 'none';
            });

            // Ã‰tape 6: Attendre la stabilisation
            await new Promise(resolve => setTimeout(resolve, 500));

            // Ã‰tape 7: Capturer avec html2canvas - Configuration optimisÃ©e et stable
            const canvas = await html2canvas(container, {
                backgroundColor: cardBg,
                scale: 2, // ✨ RÃ©solution double - Bon compromis entre qualitÃ© et stabilitÃ©
                logging: false,
                useCORS: true,
                allowTaint: true,
                foreignObjectRendering: false, // ✨ Ã‰vite les erreurs de clonage
                removeContainer: false, // ✨ Ne pas supprimer le container original
                imageTimeout: 10000, // ✨ Timeout plus long pour les images
                width: 800,
                height: container.scrollHeight,
                ignoreElements: function(element) {
                    // ✨ Ignorer les Ã©lÃ©ments problÃ©matiques
                    return element.tagName === 'IFRAME' || 
                           element.classList.contains('loading-spinner') ||
                           element.style.display === 'none';
                },
                onclone: function(clonedDoc) {
                    try {
                        // Approche plus simple et plus robuste
                        const clonedContainer = clonedDoc.querySelector('.infographie-content');
                        if (clonedContainer) {
                            clonedContainer.style.background = cardBg;
                            clonedContainer.style.width = '800px';
                            clonedContainer.style.padding = '2rem';
                            clonedContainer.style.overflow = 'visible';
                        }
                        
                        // Nettoyer seulement les Ã©lÃ©ments essentiels
                        const problematicElements = clonedDoc.querySelectorAll('iframe, .loading-spinner, .is-hidden');
                        problematicElements.forEach(el => {
                            if (el.parentNode) {
                                el.parentNode.removeChild(el);
                            }
                        });
                        
                    } catch (cloneError) {
                        // Erreur silencieuse
                        // Ne pas faire Ã©chouer la capture pour des erreurs de clonage mineures
                    }
                }
            });

            // Ã‰tape 8: Restaurer les styles originaux
            allElements.forEach((el) => {
                const original = originalStyles.get(el);
                if (original) {
                    el.style.display = original.display;
                    el.style.visibility = original.visibility;
                    el.style.opacity = original.opacity;
                    el.style.transform = original.transform;
                    el.style.transition = original.transition;
                    el.style.animation = original.animation;
                    el.style.background = original.background;
                    el.style.backgroundColor = original.backgroundColor;
                    el.style.border = original.border;
                    el.style.boxShadow = original.boxShadow;
                }
            });

            // Ã‰tape 9: DÃ©sactiver le mode export
            container.classList.remove('export-mode');

            // Ã‰tape 10: Restaurer les graphiques Chart.js
            await this.restoreChartsFromImages();

            // TÃ©lÃ©charger l'image avec les dimensions correctes
            const link = document.createElement('a');
            link.download = `infographie_${this.currentData.filters.zone}_${this.currentData.filters.period}_${this.currentData.filters.year}.png`;
            link.href = canvas.toDataURL('image/png', 1.0);
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

        } catch (error) {
            // Erreur silencieuse
            this.showError('Erreur lors du tÃ©lÃ©chargement de l\'infographie');
        } finally {
            // Masquer l'indicateur de chargement pour le tÃ©lÃ©chargement
            this.hideDownloadLoadingIndicator();
            
            downloadBtn.disabled = false;
            downloadBtn.innerHTML = `
                <div class="btn-icon">
                    <i class="fa-solid fa-download"></i>
                </div>
                <div class="btn-content">
                    <span class="btn-title">TÃ©lÃ©charger</span>
                    <span class="btn-subtitle">Image HD</span>
                </div>
            `;
        }
    }

    async convertChartsToImages() {
        // RÃ©cupÃ©rer tous les canvas Chart.js
        const canvases = document.querySelectorAll('.infographie-content canvas');
        this.chartImages = new Map();

        for (const canvas of canvases) {
            try {
                // Convertir le canvas en image HAUTE RÃ‰SOLUTION
                const imageDataUrl = canvas.toDataURL('image/png', 1.0);
                
                // CrÃ©er un Ã©lÃ©ment img avec les mÃªmes dimensions
                const img = document.createElement('img');
                img.src = imageDataUrl;
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.maxWidth = '100%';
                img.style.maxHeight = '100%';
                img.style.objectFit = 'contain';
                img.style.display = 'block';
                img.style.background = 'transparent';
                
                // Stocker les informations pour la restauration
                this.chartImages.set(canvas, {
                    originalCanvas: canvas,
                    imageElement: img,
                    parentElement: canvas.parentElement,
                    nextSibling: canvas.nextSibling
                });
                
                // REMPLACER le canvas par l'image (pas de superposition)
                canvas.parentElement.replaceChild(img, canvas);
                
            } catch (error) {
                // Erreur silencieuse
            }
        }
    }

    async restoreChartsFromImages() {
        if (this.chartImages) {
            this.chartImages.forEach((data, canvas) => {
                try {
                    // Remettre le canvas Ã  sa place originale
                    if (data.imageElement && data.imageElement.parentElement) {
                        if (data.nextSibling) {
                            data.imageElement.parentElement.insertBefore(canvas, data.nextSibling);
                        } else {
                            data.imageElement.parentElement.appendChild(canvas);
                        }
                        
                        // Supprimer l'image temporaire
                        data.imageElement.parentElement.removeChild(data.imageElement);
                    }
                    
                    // Restaurer l'affichage du canvas
                    canvas.style.display = '';
                    
                } catch (error) {
                    // Erreur silencieuse
                }
            });
            
            this.chartImages.clear();
        }
    }

    // Afficher l'indicateur de chargement
    showLoadingIndicator() {
        const loadingElement = document.getElementById('infographie-loading');
        const progressBar = document.getElementById('infographie-loading-progress-bar');
        
        if (loadingElement) {
            loadingElement.classList.add('active');
            
            // Animation de la barre de progression
            if (progressBar) {
                let progress = 0;
                const interval = setInterval(() => {
                    progress += Math.random() * 15;
                    if (progress > 90) progress = 90; // Ne pas aller Ã  100% avant la fin
                    progressBar.style.width = progress + '%';
                }, 200);
                
                // Stocker l'interval pour le nettoyer plus tard
                this.loadingProgressInterval = interval;
            }
        }
    }

    // Mettre Ã  jour le texte de l'indicateur de chargement
    updateLoadingText(text) {
        const loadingText = document.querySelector('.infographie-loading-text');
        if (loadingText) {
            loadingText.textContent = text;
        }
    }

    // Masquer l'indicateur de chargement
    hideLoadingIndicator() {
        const loadingElement = document.getElementById('infographie-loading');
        const progressBar = document.getElementById('infographie-loading-progress-bar');
        
        // Terminer la barre de progression
        if (progressBar) {
            progressBar.style.width = '100%';
        }
        
        // Nettoyer l'interval de progression
        if (this.loadingProgressInterval) {
            clearInterval(this.loadingProgressInterval);
            this.loadingProgressInterval = null;
        }
        
        // Masquer aprÃ¨s un court dÃ©lai pour montrer la progression complète
        setTimeout(() => {
            if (loadingElement) {
                loadingElement.classList.remove('active');
            }
            
            // RÃ©initialiser la barre de progression
            if (progressBar) {
                progressBar.style.width = '0%';
            }
        }, 300);
    }

    // Afficher l'indicateur de chargement pour le tÃ©lÃ©chargement
    showDownloadLoadingIndicator() {
        const loadingElement = document.getElementById('infographie-loading');
        const loadingText = loadingElement?.querySelector('.infographie-loading-text');
        const loadingSubtext = loadingElement?.querySelector('.infographie-loading-subtext');
        
        if (loadingElement) {
            // Changer les textes pour le tÃ©lÃ©chargement
            if (loadingText) loadingText.textContent = 'PrÃ©paration du tÃ©lÃ©chargement...';
            if (loadingSubtext) loadingSubtext.textContent = 'Conversion des graphiques et gÃ©nÃ©ration de l\'image';
            
            loadingElement.classList.add('active');
        }
    }

    // Masquer l'indicateur de chargement pour le tÃ©lÃ©chargement
    hideDownloadLoadingIndicator() {
        const loadingElement = document.getElementById('infographie-loading');
        const loadingText = loadingElement?.querySelector('.infographie-loading-text');
        const loadingSubtext = loadingElement?.querySelector('.infographie-loading-subtext');
        
        // Restaurer les textes par dÃ©faut
        if (loadingText) loadingText.textContent = 'GÃ©nÃ©ration de l\'infographie...';
        if (loadingSubtext) loadingSubtext.textContent = 'Chargement des donnÃ©es et crÃ©ation des graphiques';
        
        // Masquer l'indicateur
        if (loadingElement) {
            loadingElement.classList.remove('active');
        }
    }

    showError(message) {
        // Afficher un message d'erreur (Ã  adapter selon votre systÃ¨me de notifications)
        // Erreur silencieuse
        // alert(message);
    }

    // Nouvelle mÃ©thode pour dÃ©truire tous les graphiques
    destroyAllCharts() {
        Object.keys(this.chartInstances).forEach(chartKey => {
            if (this.chartInstances[chartKey]) {
                try {
                    this.chartInstances[chartKey].destroy();
                } catch (error) {
                    // Erreur silencieuse
                }
                delete this.chartInstances[chartKey];
            }
        });
        this.chartInstances = {};
    }

    // MÃ©thode pour lire les couleurs depuis les variables CSS
    getCSSVariable(variableName, fallback = '#ffffff') {
        try {
            const value = getComputedStyle(document.documentElement).getPropertyValue(variableName).trim();
            return value || fallback;
        } catch (error) {
            return fallback;
        }
    }

    // MÃ©thode pour obtenir les couleurs du thÃ¨me
    getThemeColors() {
        return {
            primary: this.getCSSVariable('--primary-color', '#00f2ea'),
            secondary: this.getCSSVariable('--secondary-color', '#a35fff'),
            textPrimary: this.getCSSVariable('--text-primary', '#ffffff'),
            textSecondary: this.getCSSVariable('--text-secondary', '#b8c2d0'),
            success: this.getCSSVariable('--success-color', '#00d4aa'),
            error: this.getCSSVariable('--error-color', '#ff6b6b'),
            info: this.getCSSVariable('--info-color', '#3b82f6'),
            cardBg: this.getCSSVariable('--card-bg', '#1a1f2c'),
            borderColor: this.getCSSVariable('--border-color', 'rgba(184, 194, 208, 0.25)')
        };
    }

    // Utilitaire pour crÃ©er des couleurs RGBA avec alpha
    rgba(r, g, b, a) {
        return `rgba(${r}, ${g}, ${b}, ${a})`;
    }

    // MÃ©thode pour extraire les composants RGB d'une couleur hex
    hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    // MÃ©thode pour crÃ©er une couleur avec alpha Ã  partir d'une couleur hex
    hexToRgba(hex, alpha) {
        const rgb = this.hexToRgb(hex);
        if (!rgb) return hex;
        return this.rgba(rgb.r, rgb.g, rgb.b, alpha);
    }
    
    // MÃ©thode pour vÃ©rifier si l'infographie est prÃªte Ã  Ãªtre partagÃ©e
    isInfographicReady() {
        const shareBtn = document.getElementById('btn-partager-infographie');
        const infographicContainer = document.querySelector('.infographie-container');
        
        return shareBtn && !shareBtn.disabled && 
               infographicContainer && infographicContainer.children.length > 0;
    }

    // Nouveau: graphique de mobilitÃ© interne pour l'infographie
    generateMobilityDestinationsChart() {
        window.fvLog('[Infographie] GÃ©nÃ©ration du graphique mobility destinations');
        window.fvLog('[Infographie] currentData:', this.currentData);

        try {
            const canvas = document.getElementById('infographie-mobility-destinations');
            if (!canvas) {
                console.warn('Canvas infographie-mobility-destinations non trouvÃ©');
                return;
            }

            // RÃ©cupÃ©rer les donnÃ©es des destinations depuis les donnÃ©es dÃ©jÃ  chargÃ©es
            const destinationsData = this.currentData?.mobilityDestinations;
            window.fvLog('[Infographie] DonnÃ©es mobilityDestinations:', destinationsData);

            if (!destinationsData || destinationsData.length === 0) {
                // Afficher un message d'erreur dans le canvas
                console.warn('[Infographie] Aucune donnÃ©e de mobilitÃ© interne disponible');
                this.showChartError(canvas, 'Aucune donnÃ©e de mobilitÃ© interne disponible');
                return;
            }

            window.fvLog('[Infographie] GÃ©nÃ©ration du graphique avec', destinationsData.length, 'destinations');
            this.renderMobilityDestinationsChart(canvas, destinationsData);

        } catch (error) {
            console.error('Erreur dans generateMobilityDestinationsChart:', error);
            // Afficher un message d'erreur dans le canvas
            const canvas = document.getElementById('infographie-mobility-destinations');
            if (canvas) {
                this.showChartError(canvas, 'Erreur lors du chargement des donnÃ©es de mobilitÃ© interne');
            }
        }
    }

    renderMobilityDestinationsChart(canvas, destinationsData) {
        if (!destinationsData || destinationsData.length === 0) {
            this.showChartError(canvas, 'Aucune donnÃ©e de mobilitÃ© interne disponible');
            return;
        }

        // RÃ©cupÃ©rer les couleurs du thÃ¨me
        const colors = this.getThemeColors();

        // Utiliser la mÃªme couleur que les excursionnistes
        const baseColor = '#667eea'; // Couleur des excursionnistes

        // DÃ©truire le graphique existant s'il existe
        const chartKey = 'mobilityDestinationsChart';
        if (this.chartInstances[chartKey]) {
            this.chartInstances[chartKey].destroy();
        }

        // Traiter les donnÃ©es comme les autres graphiques d'origines
        const currentYear = this.currentData?.filters?.year || new Date().getFullYear();
        const previousYear = currentYear - 1;

        // PrÃ©parer les donnÃ©es avec comparaison N vs N-1
        const labels = destinationsData.map(item => item.nom_commune);
        const currentValues = destinationsData.map(item => item.total_visiteurs || 0);
        const previousValues = destinationsData.map(item => item.total_visiteurs_n1 || 0);

        // CrÃ©er le graphique en barres horizontales avec comparaison
        this.chartInstances[chartKey] = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: (() => {
                    const datasets = [{
                        label: `${currentYear}`,
                        data: currentValues,
                        backgroundColor: baseColor,
                        borderColor: baseColor,
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false
                    }];

                    // N'ajouter le dataset N-1 que s'il y a au moins une valeur non nulle
                    if (!previousValues.every(v => v === 0)) {
                        datasets.push({
                            label: `${previousYear}`,
                            data: previousValues,
                            backgroundColor: this.hexToRgba(baseColor, 0.6), // Version transparente
                            borderColor: this.hexToRgba(baseColor, 0.8),
                            borderWidth: 1,
                            borderRadius: 4,
                            borderSkipped: false
                        });
                    }

                    return datasets;
                })()
            },
            plugins: [
                {
                    id: 'barEndValues',
                    afterDatasetsDraw: (chart) => {
                        const {ctx, chartArea, scales: {x, y}} = chart;
                        const ds = chart.data.datasets[0];
                        if (!ds) return;

                        // Capture une rÃ©fÃ©rence sÃ»re Ã  la fonction de formatage
                        const fmt = this.formatNumber.bind(this);

                        ctx.save();
                        ctx.fillStyle = colors.textPrimary; // Utiliser la variable CSS
                        ctx.font = 'bold 10px -apple-system, BlinkMacSystemFont, sans-serif';
                        ctx.textAlign = 'left';
                        ctx.textBaseline = 'middle';
                        ctx.shadowColor = 'rgba(0, 0, 0, 0.7)';
                        ctx.shadowBlur = 2;
                        ctx.shadowOffsetX = 1;
                        ctx.shadowOffsetY = 1;

                        ds.data.forEach((v, i) => {
                            if (!v) return;
                            const yPix = y.getPixelForValue(i);
                            const xPix = x.getPixelForValue(v) + 4; // RÃ©duit de 8px Ã  4px
                            // Utiliser l'espace de padding dÃ©fini dans layout.padding.right
                            const maxX = chartArea.right + 20; // RÃ©duit de 40px Ã  20px
                            const xClamped = Math.min(xPix, maxX);
                            ctx.fillText(fmt(v), xClamped, yPix);
                        });
                        ctx.restore();
                    }
                }
            ],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y', // Barres horizontales
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10,
                        left: 'auto',
                        right: 6 // RÃ©duit de 10px Ã  6px pour rapprocher les chiffres
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            color: colors.textPrimary, // Utiliser la variable CSS
                            font: { size: 11, weight: '600' }, // Police plus grosse et plus grasse
                            padding: 8,
                            usePointStyle: false,
                            boxWidth: 6,
                            boxHeight: 6,
                            generateLabels: function(chart) {
                                const datasets = chart.data.datasets;
                                if (datasets.length >= 2) {
                                    return [
                                        {
                                            text: `${currentYear}`,
                                            fillStyle: datasets[0].backgroundColor,
                                            strokeStyle: datasets[0].borderColor,
                                            lineWidth: 1,
                                            datasetIndex: 0
                                        },
                                        {
                                            text: `${previousYear}`,
                                            fillStyle: datasets[1].backgroundColor,
                                            strokeStyle: datasets[1].borderColor,
                                            lineWidth: 1,
                                            datasetIndex: 1
                                        }
                                    ];
                                } else {
                                    return [
                                        {
                                            text: `${currentYear}`,
                                            fillStyle: datasets[0].backgroundColor,
                                            strokeStyle: datasets[0].borderColor,
                                            lineWidth: 1,
                                            datasetIndex: 0
                                        }
                                    ];
                                }
                            }
                        }
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(15, 18, 26, 0.9)',
                        titleColor: '#a35fff',
                        titleFont: { weight: 'bold', size: 14 },
                        bodyColor: '#f0f5ff',
                        borderColor: 'rgba(0, 242, 234, 0.5)',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: true,
                        callbacks: {
                            title: (ctx) => ctx[0]?.label || '',
                            label: (ctx) => {
                                const rawItem = destinationsData[ctx.dataIndex];
                                if (!rawItem) return `Visiteurs: ${ctx.parsed.x}`;

                                const lines = [`${ctx.dataset.label}: ${this.formatNumber(ctx.parsed.x)}`];

                                if (rawItem.total_visiteurs_n1 > 0 && ctx.datasetIndex === 0) {
                                    if (rawItem.evolution_pct !== null) {
                                        const sign = rawItem.evolution_pct > 0 ? '+' : '';
                                        lines.push(`Ã‰volution: ${sign}${rawItem.evolution_pct}%`);
                                    }
                                }
                                return lines;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: {
                            color: '#a0a8b8',
                            font: { size: 10 },
                            callback: function(value) {
                                return value.toLocaleString('fr-FR');
                            }
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            autoSkip: false,
                            maxTicksLimit: 10,
                            color: '#f0f5ff',
                            font: { size: 11 }
                        }
                    }
                }
            }
        });

        // Ajouter l'indicateur d'unitÃ©
        const chartContainer = canvas.closest('.chart-container');
        if (chartContainer) {
            const chartHeader = chartContainer.querySelector('.chart-header');
            if (chartHeader) {
                // Supprimer l'ancien indicateur d'unitÃ© s'il existe
                const existingUnitIndicator = chartContainer.querySelector('.chart-unit-indicator');
                if (existingUnitIndicator) {
                    existingUnitIndicator.remove();
                }

                // CrÃ©er le nouvel indicateur d'unitÃ©
                const unitIndicator = document.createElement('div');
                unitIndicator.className = 'chart-unit-indicator';
                unitIndicator.textContent = 'visiteurs';
                unitIndicator.style.cssText = `
                    position: absolute;
                    top: 10px;
                    right: 15px;
                    background: ${baseColor};
                    color: white;
                    padding: 2px 8px;
                    border-radius: 3px;
                    font-size: 10px;
                    font-weight: bold;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    z-index: 10;
                `;
                chartHeader.appendChild(unitIndicator);
            }
        }
    }
}

// Initialiser l'infographie manager au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    const infographieManager = new InfographieManager();
    
    // Ajouter la fonctionnalitÃ© de partage
    setupShareInfographic(infographieManager);
});

// Fonction pour configurer le partage d'infographie
function setupShareInfographic(infographieManager) {
    const shareButton = document.getElementById('btn-partager-infographie');
    
    if (shareButton) {
        shareButton.addEventListener('click', () => {
            shareInfographic(infographieManager);
        });
    }
}

// Fonction pour partager une infographie
function shareInfographic(infographieManager) {
    // VÃ©rifier que l'infographie est prÃªte Ã  Ãªtre partagÃ©e
    if (!infographieManager.isInfographicReady()) {
        alert('Veuillez d\'abord gÃ©nÃ©rer une infographie avant de la partager.');
        return;
    }
    
    // RÃ©cupÃ©rer les paramÃ¨tres actuels
    const currentParams = {
        year: document.getElementById('exc-year-select')?.value,
        period: document.getElementById('exc-period-select')?.value,
        zone: infographieManager.config.defaultZone,
        customRange: window.infographieCustomDateRange
    };
    
    // GÃ©nÃ©rer un ID unique pour cette infographie
    const uniqueId = generateUniqueId(currentParams);
    
    // Construire l'URL avec les paramÃ¨tres
    const params = new URLSearchParams({
        action: 'share',
        year: currentParams.year || '',
        period: currentParams.period || '',
        zone: currentParams.zone,
        unique_id: uniqueId
    });
    
    // Ajouter les dates personnalisées si elles existent
    if (currentParams.customRange && currentParams.customRange.start && currentParams.customRange.end) {
        params.set('debut', currentParams.customRange.start);
        params.set('fin', currentParams.customRange.end);
    }
    
    // Capturer et sauvegarder une prÃ©visualisation de l'infographie
    captureAndSavePreview(uniqueId).then(previewId => {
        if (previewId) {
            params.append('preview_id', previewId);
        }
        
        // Rediriger vers la page de sÃ©lection d'espace
        window.location.href = `/fluxvision_fin/shared-spaces/select?${params.toString()}`;
    }).catch(error => {
        console.error('Erreur lors de la capture de prÃ©visualisation:', error);
        // Rediriger sans prÃ©visualisation en cas d'erreur
        window.location.href = `/fluxvision_fin/shared-spaces/select?${params.toString()}`;
    });
}

/**
 * Capturer et sauvegarder une prÃ©visualisation de l'infographie
 */
async function captureAndSavePreview(uniqueId) {
    try {
        // Charger html2canvas si pas dÃ©jÃ  fait
        if (typeof html2canvas === 'undefined') {
            await loadHtml2Canvas();
        }
        
        const infographicContainer = document.querySelector('.infographie-container');
        if (!infographicContainer) {
            throw new Error('Container infographie non trouvÃ©');
        }
        
        // Capturer l'infographie
        const canvas = await html2canvas(infographicContainer, {
            scale: 0.5, // RÃ©duire la qualitÃ© pour la prÃ©visualisation
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#ffffff'
        });
        
        const previewDataUrl = canvas.toDataURL('image/png', 0.8);
        
        // Sauvegarder via l'API
        const response = await fetch('/fluxvision_fin/api/infographie/preview.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                preview_data: previewDataUrl,
                unique_id: uniqueId,
                csrf_token: getCSRFToken()
            })
        });
        
        if (!response.ok) {
            throw new Error(`Erreur API: ${response.status}`);
        }
        
        const result = await response.json();
        if (result.success) {
            return result.preview_id;
        } else {
            throw new Error(result.error || 'Erreur lors de la sauvegarde');
        }
    } catch (error) {
        console.error('Erreur capture et sauvegarde prÃ©visualisation:', error);
        return null;
    }
}

/**
 * Capturer une prÃ©visualisation de l'infographie (ancienne version)
 */
async function captureInfographicPreview() {
    try {
        // Charger html2canvas si pas dÃ©jÃ  fait
        if (typeof html2canvas === 'undefined') {
            await loadHtml2Canvas();
        }
        
        const infographicContainer = document.querySelector('.infographie-container');
        if (!infographicContainer) {
            throw new Error('Container infographie non trouvÃ©');
        }
        
        // Capturer l'infographie
        const canvas = await html2canvas(infographicContainer, {
            scale: 0.5, // RÃ©duire la qualitÃ© pour la prÃ©visualisation
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#ffffff'
        });
        
        return canvas.toDataURL('image/png', 0.8);
    } catch (error) {
        console.error('Erreur capture prÃ©visualisation:', error);
        return null;
    }
}

/**
 * Charger html2canvas dynamiquement
 */

function loadHtml2Canvas() {
    return new Promise((resolve, reject) => {
        if (typeof html2canvas !== 'undefined') {
            resolve();
            return;
        }
        
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

/**
 * GÃ©nÃ©rer un ID unique pour l'infographie
 */
function generateUniqueId(params) {
    const date = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    const zone = params.zone || 'CANTAL';
    const year = params.year || '2024';
    const period = (params.period || 'ANNEE').substring(0, 4).toUpperCase();
    const customSuffix = (params.customRange && params.customRange.start && params.customRange.end) ? '_CUSTOM' : '';
    const random = Math.random().toString(36).substring(2, 8).toUpperCase();
    
    return `INF_${date}_${zone}_${year}_${period}${customSuffix}_${random}`;
}

/**
 * RÃ©cupÃ©rer le token CSRF depuis la page
 */
function getCSRFToken() {
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    return csrfInput ? csrfInput.value : '';
}

// ---- end static/js/infographie.js ----

