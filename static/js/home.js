// Script pour la page d'accueil uniquement
document.addEventListener('DOMContentLoaded', () => {
    // Initialiser animations et effets en utilisant les fonctions de utils.js
    utils.animateEntrance();
    utils.initScrollAnimations();
    
    // Gestionnaire d'événements global pour les animations au survol
    document.body.addEventListener('mouseover', utils.handleHoverAnimations);
    

    
    // Initialiser le calendrier interactif
    initializeInteractiveCalendar();
});

/**
 * ✅ ALIGNEMENT AVEC TABLEAU DE BORD
 * Utilise le même système CantalDestinationDynamicConfig que tdb_comparaison
 */
let cantalDestinationConfig = null;

async function getCurrentPeriod() {
    // ✅ UTILISER LE SYSTÈME PERIOD MAPPER INTELLIGENT
    if (!cantalDestinationConfig) {
        cantalDestinationConfig = new CantalDestinationDynamicConfig();
        await cantalDestinationConfig.loadFromDatabase();
    }
    
    // ✅ PÉRIODE DYNAMIQUE via PeriodMapper
    const smartPeriod = await cantalDestinationConfig.getCurrentPeriodSmart();
    
    window.fvLog('🎯 Période détectée par PeriodMapper:', smartPeriod);
    
    return {
        periode: smartPeriod.periode,
        annee: smartPeriod.annee
    };
}

/**
 * Configuration dynamique depuis la base de données - IDENTIQUE au TDB
 */
class CantalDestinationDynamicConfig {
    constructor() {
        this.data = null;
        this.isLoaded = false;
    }
    
    async loadFromDatabase() {
        if (this.isLoaded) return this.data;
        
        try {
            const response = await fetch(window.getApiUrl('filters_mysql.php'));
            this.data = await response.json();
            this.isLoaded = true;
            return this.data;
        } catch (error) {
            console.error('Erreur lors du chargement de la configuration:', error);
            // ✅ FALLBACK SANS CODE EN DUR
            this.data = {
                annees: [new Date().getFullYear(), new Date().getFullYear() - 1],
                zones: ['CANTAL'],
                periodes: [
                    { value: 'annee_complete', label: 'Année complète' }
                    // Plus de périodes spécifiques en dur - on utilise uniquement annee_complete
                ]
            };
            this.isLoaded = true;
            return this.data;
        }
    }
    
    get defaultYear() {
        return this.data?.annees?.[0] || new Date().getFullYear();
    }
    
    get defaultZone() {
        const zones = this.data?.zones || [];
        return zones.find(zone => zone.toUpperCase() === 'CANTAL') || zones[0] || 'CANTAL';
    }
    
    async getCurrentPeriodSmart() {
        // ✅ UTILISER LE SYSTÈME EXISTANT : PeriodMapper via API
        try {
            const response = await fetch(`${CantalDestinationConfig.basePath}/api/current_period_info.php`);
            const data = await response.json();
            
            if (data.status === 'success' && data.resolved_period) {
                return {
                    periode: data.resolved_period.code_periode,
                    annee: data.resolved_period.annee || data.current_year,
                    source: 'PeriodMapper intelligent'
                };
            }
        } catch (error) {
            console.warn('Fallback: PeriodMapper non disponible', error);
        }
        
        // ✅ FALLBACK INTELLIGENT sans code en dur
        const currentYear = new Date().getFullYear();
        
        // Utiliser les données de la base pour fallback intelligent
        const periodes = this.data?.periodes || [];
        const currentYearPeriods = periodes.filter(p => p.annee === currentYear);
        
        if (currentYearPeriods.length > 0) {
            // Prendre la première période de l'année courante comme fallback
            return {
                periode: currentYearPeriods[0].value,
                annee: currentYear,
                source: 'Fallback base de données'
            };
        }
        
        // En cas d'échec total, période générique
        return {
            periode: 'annee_complete',
            annee: currentYear,
            source: 'Fallback générique'
        };
    }
    
    get defaultPeriod() {
        // ❌ OBSOLÈTE : utilisez getCurrentPeriodSmart() pour la période dynamique
        // Cette méthode garde la logique pour compatibilité descendante
        const periodes = this.data?.periodes || [];
        return periodes.length > 0 ? periodes[0].value : 'annee_complete'; // ✅ Plus de code en dur
    }
}



























/**
 * Formate un nombre avec séparateurs de milliers
 */
function formatNumber(num) {
    if (num === null || num === undefined) return 'N/A';
    return num.toLocaleString('fr-FR');
}

/**
 * Met en majuscule la première lettre
 */
function capitalizeFirst(str) {
    if (!str || typeof str !== 'string') {
        return str || ''; // Retourner chaîne vide si undefined/null
    }
    return str.charAt(0).toUpperCase() + str.slice(1);
}

/**
 * Initialise le calendrier interactif des restitutions - Version simplifiée
 */
function initializeInteractiveCalendar() {
    const currentMonth = new Date().getMonth() + 1; // getMonth() retourne 0-11
    const currentYear = new Date().getFullYear();
    
    // ✅ SIMPLIFICATION : ne plus détecter de période depuis JS
    // Laisser PeriodMapper s'occuper de ça via getCurrentPeriod()
    
    // Déterminer la période actuelle via API (pas de code en dur)
    let currentPeriod = null;
    
    // Pour le calendrier simplifié, on se contente de mettre toutes les cartes disponibles
    // sans détecter la période actuelle en JS (PeriodMapper le fait mieux)
    /*for (const [periodKey, period] of Object.entries(periodData)) {
        if (period.months.includes(currentMonth) && periodKey !== 'annee') {
            currentPeriod = periodKey;
            break;
        }
    }*/
    
    // ✅ CALENDRIER SIMPLIFIÉ : pas de détection JS
    // La période actuelle sera mise en évidence par getCurrentPeriod() et le CSS
    const periodCards = document.querySelectorAll('.calendar-period');
    // Plus besoin de détecter currentPeriod ici - c'est fait par PeriodMapper
    
    // Configurer les interactions simplifiées
    setupSimpleCalendarInteractions();
}

/**
 * Met à jour les statuts visuels des saisons
 */
function updateSeasonStatuses(seasonData, currentSeason, analysisSeasonKey) {
    for (const [seasonKey, season] of Object.entries(seasonData)) {
        const seasonCard = document.querySelector(`[data-season="${seasonKey}"]`);
        // ✅ MAPPING DYNAMIQUE - pas de code en dur  
        const seasonToEnglish = {
            'ete': 'summer',
            'hiver': 'winter', 
            'printemps': 'spring',
            'automne': 'autumn'
        };
        const statusIndicator = document.getElementById(`${seasonToEnglish[seasonKey] || 'season'}-status`);
        
        if (!seasonCard || !statusIndicator) continue;
        
        // Supprimer toutes les classes de statut existantes
        seasonCard.classList.remove('season-current', 'season-analysis', 'season-upcoming', 'season-past');
        statusIndicator.classList.remove('status-current', 'status-analysis', 'status-upcoming', 'status-past');
        
        // Ajouter la classe appropriée
        if (currentSeason && currentSeason.key === seasonKey) {
            seasonCard.classList.add('season-current');
            statusIndicator.classList.add('status-current');
        } else if (analysisSeasonKey === seasonKey) {
            seasonCard.classList.add('season-analysis');
            statusIndicator.classList.add('status-analysis');
        } else {
            seasonCard.classList.add('season-available');
            statusIndicator.classList.add('status-available');
        }
    }
}

/**
 * Affiche les informations de la période actuelle
 */
function showCurrentPeriodInfo(currentSeason, analysisSeasonKey, seasonData) {
    const currentPeriodInfo = document.getElementById('current-period-info');
    const periodName = document.getElementById('current-period-name');
    const periodDescription = document.getElementById('current-period-description');
    const periodLink = document.getElementById('current-period-link');
    
    if (!currentPeriodInfo) return;
    
    let displayInfo = null;
    
    if (analysisSeasonKey) {
        // Période d'analyse en cours
        displayInfo = {
            name: `Période d'analyse - ${seasonData[analysisSeasonKey].name}`,
            description: 'C\'est le moment idéal pour analyser les données de cette période !',
            link: `${CantalDestinationConfig.basePath}/tdb_comparaison?periode=${analysisSeasonKey}`,
            type: 'analysis'
        };
    } else if (currentSeason) {
        // Saison en cours
        displayInfo = {
            name: `En cours - ${currentSeason.name}`,
            description: currentSeason.description,
            link: `${CantalDestinationConfig.basePath}/tdb_comparaison?periode=${currentSeason.key}`,
            type: 'current'
        };
    }
    
    if (displayInfo) {
        periodName.textContent = displayInfo.name;
        periodDescription.textContent = displayInfo.description;
        periodLink.href = displayInfo.link;
        
        // Ajouter une classe pour le type de période
        currentPeriodInfo.className = `calendar-current-period period-${displayInfo.type}`;
        currentPeriodInfo.style.display = 'block';
    }
}

/**
 * Configure les interactions du calendrier simplifié
 */
function setupSimpleCalendarInteractions() {
    const periodCards = document.querySelectorAll('.calendar-period');
    
    periodCards.forEach(card => {
        // Click pour navigation rapide vers les analyses
        card.addEventListener('click', function(e) {
            // Éviter le double click si on clique sur le bouton
            if (e.target.closest('.btn')) return;
            
            const analyzeButton = this.querySelector('.btn--period');
            
            if (analyzeButton) {
                // Animation de feedback
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                    window.location.href = analyzeButton.href;
                }, 150);
            }
        });
    });
    
    // Animation d'entrée pour les cartes
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, index * 80); // Délai progressif
            }
        });
    }, { threshold: 0.1 });
    
    periodCards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.6s ease-out';
        observer.observe(card);
    });
} 