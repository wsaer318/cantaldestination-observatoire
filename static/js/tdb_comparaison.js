/**
 * Script JavaScript intégré pour la page de comparaison TDB
 * Combine : filters_loader.js + tdb_comparaison_api.js + logique de chargement + comparaison avancée
 */

// =============================================================================
// CONFIGURATION DYNAMIQUE DEPUIS LA BASE DE DONNÉES
// =============================================================================

const CHART_COLORS = [
    'rgba(0, 242, 234, 0.8)',  'rgba(248, 0, 255, 0.8)',  'rgba(163, 95, 255, 0.8)',
    'rgba(126, 255, 139, 0.8)', 'rgba(255, 150, 79, 0.8)',  'rgba(0, 174, 255, 0.8)',
    'rgba(255, 0, 168, 0.8)',  'rgba(255, 234, 0, 0.8)',   'rgba(0, 255, 56, 0.8)',
    'rgba(0, 255, 212, 0.8)','rgba(191, 255, 0, 0.8)',   'rgba(255, 0, 115, 0.8)',
    'rgba(119, 0, 255, 0.8)','rgba(0, 255, 149, 0.8)',  'rgba(0, 140, 255, 0.81)',
    'rgba(255, 62, 0, 0.8)',  'rgba(0, 255, 124, 0.8)',  'rgba(168, 255, 0, 0.8)',
    'rgba(0, 184, 255, 0.8)','rgba(255, 0, 219, 0.8)',  'rgba(117, 255, 0, 0.8)'
];

class CantalDestinationDynamicConfig {
    constructor() {
        this.data = null;
        this.isLoaded = false;
        
        // Configuration statique (limites, etc.)
        this.chartLimits = {
            departements: 15,
            regions: 5,
            pays: 5,
            age: 3,
            csp: 10
        };
    }
    
    async loadFromDatabase() {
        if (this.isLoaded) return this.data;
        
        try {
            const response = await fetch(window.getApiUrl('filters/filters_mysql.php'));
            this.data = await response.json();
            this.isLoaded = true;
            return this.data;
        } catch (error) {
            console.error('Erreur lors du chargement de la configuration:', error);
            // Fallback avec valeurs minimales
            this.data = {
                annees: [new Date().getFullYear(), new Date().getFullYear() - 1],
                zones: ['CANTAL'],
                periodes: [
                    { value: 'annee', label: 'Année' },
                    { value: 'hiver', label: 'Vacances d\'hiver' }
                ]
            };
            this.isLoaded = true;
            return this.data;
        }
    }
    
    // Getters dynamiques basés sur les données de la DB
    get defaultYear() {
        return this.data?.annees?.[0] || new Date().getFullYear();
    }
    
    get previousYear() {
        return this.data?.annees?.[1] || (new Date().getFullYear() - 1);
    }
    
    get availableYears() {
        return this.data?.annees || [];
    }
    
    get defaultZone() {
        // Prioriser CANTAL si disponible, sinon prendre la première zone
        const zones = this.data?.zones || [];
        return zones.find(zone => zone.toUpperCase() === 'CANTAL') || zones[0] || 'CANTAL';
    }
    
    get availableZones() {
        return this.data?.zones || [];
    }
    
    get defaultPeriod() {
        // Prioriser 'hiver' de l'année courante si disponible, sinon la première période disponible
        const periodes = this.data?.periodes || [];
        const currentYear = this.defaultYear;
        
        // Chercher 'hiver' de l'année courante en priorité
        const hiverCurrent = periodes.find(p => p.value === 'hiver' && p.annee === currentYear);
        if (hiverCurrent) return hiverCurrent.value;
        
        // Sinon, prendre la première période de l'année courante
        const currentYearPeriods = periodes.filter(p => p.annee === currentYear);
        if (currentYearPeriods.length > 0) return currentYearPeriods[0].value;
        
        // En dernier recours, prendre la première période disponible
        return periodes[0]?.value || 'hiver';
    }
    
    get availablePeriods() {
        // Retourner les périodes de l'année par défaut, ou toutes si pas de filtre par année
        const periodes = this.data?.periodes || [];
        const currentYear = this.defaultYear;
        
        // Filtrer par année courante et supprimer les doublons par code_periode
        const currentYearPeriods = periodes
            .filter(p => p.annee === currentYear)
            .reduce((unique, period) => {
                if (!unique.find(p => p.value === period.value)) {
                    unique.push(period);
                }
                return unique;
            }, []);
            
        return currentYearPeriods.length > 0 ? currentYearPeriods : periodes;
    }
    
    // Méthode pour obtenir une période par son code
    getPeriodByCode(code) {
        return this.data?.periodes?.find(p => p.value === code);
    }
    
    // Méthode pour mapper les noms de périodes depuis la base de données
    mapPeriodName(periodeName) {
        // D'abord, chercher dans les données de la DB si une période correspond exactement
        if (this.data?.periodes) {
            const exactMatch = this.data.periodes.find(p => 
                p.label.toLowerCase() === periodeName.toLowerCase()
            );
            if (exactMatch) return exactMatch.value;
        }
        
        // Retourner directement le nom de la période sans mapping
        return periodeName;
    }
}

// Instance globale
const fluxVisionDynamicConfig = new CantalDestinationDynamicConfig();

// =============================================================================
// SECTION 1: CHARGEUR DE FILTRES DEPUIS L'API MYSQL
// =============================================================================

// Fonction utilitaire pour mapper les noms de périodes
function mapPeriodName(periodeName) {
    return fluxVisionDynamicConfig.mapPeriodName(periodeName);
}

// =============================================================================

// Met à jour le select des périodes pour une année donnée à partir des données DB
async function updatePeriodSelectForYear(targetYear, desiredCode = null) {
    const periodSelect = document.getElementById('exc-period-select');
    if (!periodSelect) return;

    try {
        // Corriger la valeur vide de targetYear
        const correctedTargetYear = (targetYear && targetYear !== '') ? targetYear : fluxVisionDynamicConfig.defaultYear.toString();
        window.fvLog('🔎 updatePeriodSelectForYear:start', { targetYear, correctedTargetYear, desiredCode, previous: periodSelect.value });
        await fluxVisionDynamicConfig.loadFromDatabase();
        const all = fluxVisionDynamicConfig.data?.periodes || [];
        const year = Number(correctedTargetYear);

        // Filtrer par année et dédupliquer par value
        let periods = all
            .filter(p => Number(p.annee) === year)
            .filter((p, idx, arr) => arr.findIndex(x => x.value === p.value) === idx)
            .map(p => ({ value: p.value, label: p.label }));

        // Fallback: si aucune période pour cette année, utiliser toutes
        if (periods.length === 0) {
            periods = all
                .filter((p, idx, arr) => arr.findIndex(x => x.value === p.value) === idx)
                .map(p => ({ value: p.value, label: p.label }));
        }

        const previousValue = periodSelect.value;
        const wasOrWantsAnneeComplete = (desiredCode === 'annee_complete') || (previousValue === 'annee_complete');
        const wasOrWantsCustom = (desiredCode === 'custom') || (previousValue === 'custom');
        let selectedValue = null;
        if (desiredCode) {
            // Gestion spéciale pour "année complète" qui n'existe pas en base
            if (desiredCode === 'annee_complete') {
                // Chercher une période "année" ou similaire dans la base
                const normalizedDesired = 'annee';
                const candidates = [normalizedDesired, 'annee_complete', `annee_${year}`, `annee_complete_${year}`];
                selectedValue = candidates.find(v => v && periods.some(p => p.value === v)) || null;
                
                // Si toujours pas trouvé, tenter par label contenant "année"
                if (!selectedValue) {
                    const byLabel = periods.find(p => (p.label || '').toLowerCase().includes('année'));
                    if (byLabel) selectedValue = byLabel.value;
                }
                
                // Si toujours rien, garder la valeur précédente si elle existe
                if (!selectedValue && previousValue && periods.some(p => p.value === previousValue)) {
                    selectedValue = previousValue;
                }
                // Si toujours rien, on restera sur annee_complete mais en l'injectant dans la liste plus bas
            } else if (desiredCode === 'custom') {
                // Gestion du mode personnalisé: on injectera l'option plus bas
                selectedValue = 'custom';
            } else {
                const normalizedDesired = desiredCode;
                const candidates = [normalizedDesired, desiredCode];
                selectedValue = candidates.find(v => v && periods.some(p => p.value === v)) || null;
            }
        }
        if (!selectedValue) {
            if (periods.some(p => p.value === previousValue)) selectedValue = previousValue;
            else if (periods.some(p => p.value === 'annee')) selectedValue = 'annee';
            else if (periods.some(p => p.value === 'annee_complete')) selectedValue = 'annee_complete';
            else selectedValue = periods[0]?.value || '';
        }

        // Préserver "Année complète" si c'était la sélection précédente ou le souhait explicite
        const hasAnneeComplete = periods.some(p => p.value === 'annee_complete');
        if (wasOrWantsAnneeComplete && !hasAnneeComplete) {
            periods = [{ value: 'annee_complete', label: 'Année complète' }, ...periods];
            selectedValue = 'annee_complete';
        }
        // Préserver/insérer l'option "custom" pour les intervalles personnalisés
        const hasCustom = periods.some(p => p.value === 'custom');
        if (wasOrWantsCustom && !hasCustom) {
            periods = [{ value: 'custom', label: 'Intervalle personnalisé' }, ...periods];
            selectedValue = 'custom';
        }

        periodSelect.innerHTML = periods
            .map(p => `<option value="${p.value}" ${p.value === selectedValue ? 'selected' : ''}>${p.label}</option>`)
            .join('');

        window.fvLog('🔎 updatePeriodSelectForYear:end', { appliedSelected: selectedValue, options: periods.map(p=>p.value) });

        // Synchroniser l'entête après mise à jour
        try { if (typeof window.tdbComparaison !== 'undefined') window.tdbComparaison.syncHeaderWithFilters(); } catch(_){}
    } catch (e) {
        console.error('updatePeriodSelectForYear failed:', e);
    }
}

// =============================================================================

class FiltersLoader {
    constructor() {
    }

    async init() {
        window.fvLog('🚀 FiltersLoader.init: Starting initialization...');
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.startInitialization();
            });
        } else {
            this.startInitialization();
        }
    }

    async startInitialization() {
        window.fvLog('🔧 FiltersLoader.startInitialization: Starting sequence...');

        // Attendre un peu pour laisser le temps au DOM de se stabiliser
        setTimeout(async () => {
            window.fvLog('📥 FiltersLoader.startInitialization: Loading filters...');
            await this.loadFilters();
            window.fvLog('✅ FiltersLoader.startInitialization: Filters loaded successfully');
        }, 200);
    }

    async loadFilters() {
        window.fvLog('🔍 loadFilters: Starting to load filters from API...');

        try {
            // Charger la configuration depuis la base de données
            window.fvLog('📡 loadFilters: Calling fluxVisionDynamicConfig.loadFromDatabase()...');
            const filtersData = await fluxVisionDynamicConfig.loadFromDatabase();
            window.fvLog('✅ loadFilters: Filters data received:', filtersData);

            window.fvLog('🔍 [DIAGNOSTIC] loadFilters - filtersData received:', filtersData);
            window.fvLog('🔍 [DIAGNOSTIC] Zones from API:', filtersData?.zones);
            window.fvLog('🔍 [DIAGNOSTIC] Unique zones count:', new Set(filtersData?.zones || []).size);
            window.fvLog('🔍 [DIAGNOSTIC] Duplicates check:', filtersData?.zones?.length !== new Set(filtersData?.zones || []).size ? 'POSSIBLE DUPLICATES' : 'NO DUPLICATES');

            // Utiliser les données de l'API pour tous les filtres
            this.loadYearsFromAPI(filtersData.annees);
            this.loadPeriodsFromAPI(filtersData.periodes);
            this.loadZonesFromAPI(filtersData.zones);
            this.loadComparisonFilters(filtersData);

            // Replacer le sélecteur avancé sous la grille pour un centrage global
            try { this.relocatePeriodPicker(); } catch(_){}

    } catch (error) {
            console.error('Erreur lors du chargement des filtres depuis l\'API:', error);
            // Fallback vers les valeurs par défaut
            this.loadYears();
            this.loadPeriods();
            this.loadZones();
            this.loadComparisonFiltersDefault();
        }

    }

    // Déplace le bouton du sélecteur avancé au bas du conteneur des filtres
    relocatePeriodPicker() {
        const picker = document.getElementById('dashboardPeriodPicker');
        const container = document.querySelector('#normal-filters .filters-container');
        if (picker && container && picker.parentElement !== container) {
            container.appendChild(picker);
        }
    }

    loadComparisonFilters(filtersData) {

        window.fvLog('🔍 [DIAGNOSTIC] loadComparisonFilters called with zones:', filtersData.zones);

        this.loadSelectOptions('comp-period-a', fluxVisionDynamicConfig.availablePeriods, fluxVisionDynamicConfig.defaultPeriod);

        window.fvLog('🔍 [DIAGNOSTIC] Filling comp-zone-a with zones:', filtersData.zones);
        this.loadSelectOptions('comp-zone-a', filtersData.zones.map(zone => ({
            value: zone, label: zone
        })), fluxVisionDynamicConfig.defaultZone);
        
        this.loadSelectOptions('comp-year-a', filtersData.annees.map(year => ({
            value: year, label: year
        })), fluxVisionDynamicConfig.defaultYear);
        
        this.loadSelectOptions('comp-period-b', fluxVisionDynamicConfig.availablePeriods, fluxVisionDynamicConfig.defaultPeriod);
        
        window.fvLog('🔍 [DIAGNOSTIC] Filling comp-zone-b with zones:', filtersData.zones);
        this.loadSelectOptions('comp-zone-b', filtersData.zones.map(zone => ({
            value: zone, label: zone
        })), fluxVisionDynamicConfig.defaultZone);
        
        this.loadSelectOptions('comp-year-b', filtersData.annees.map(year => ({
            value: year, label: year
        })), fluxVisionDynamicConfig.previousYear);
        
    }

    async loadComparisonFiltersDefault() {
        
        try {
            // Essayer de charger depuis la configuration dynamique même en fallback
            await fluxVisionDynamicConfig.loadFromDatabase();
            
            const periods = fluxVisionDynamicConfig.availablePeriods;
            const zones = fluxVisionDynamicConfig.availableZones.map(zone => ({ value: zone, label: zone }));
            const years = fluxVisionDynamicConfig.availableYears.map(year => ({ value: year, label: year }));
            
            this.loadSelectOptions('comp-period-a', periods, fluxVisionDynamicConfig.defaultPeriod);
            this.loadSelectOptions('comp-zone-a', zones, fluxVisionDynamicConfig.defaultZone);
            this.loadSelectOptions('comp-year-a', years, fluxVisionDynamicConfig.defaultYear);
            
            this.loadSelectOptions('comp-period-b', periods, fluxVisionDynamicConfig.defaultPeriod);
            this.loadSelectOptions('comp-zone-b', zones, fluxVisionDynamicConfig.defaultZone);
            this.loadSelectOptions('comp-year-b', years, fluxVisionDynamicConfig.previousYear);
            
        } catch (error) {
            console.error('Impossible de charger même les données de fallback, utilisation valeurs minimales:', error);
            
            // Vraiment en dernier recours - valeurs absolument minimales
            const currentYear = new Date().getFullYear();
            const minimalPeriods = [{ value: 'annee', label: 'Année complète' }];
            const minimalZones = [{ value: 'CANTAL', label: 'CANTAL' }];
            const minimalYears = [
                { value: currentYear, label: currentYear },
                { value: currentYear - 1, label: currentYear - 1 }
            ];
            
            this.loadSelectOptions('comp-period-a', minimalPeriods, 'annee');
            this.loadSelectOptions('comp-zone-a', minimalZones, 'CANTAL');
            this.loadSelectOptions('comp-year-a', minimalYears, currentYear);
            
            this.loadSelectOptions('comp-period-b', minimalPeriods, 'annee');
            this.loadSelectOptions('comp-zone-b', minimalZones, 'CANTAL');
            this.loadSelectOptions('comp-year-b', minimalYears, currentYear - 1);
        }
    }

    loadSelectOptions(selectId, options, defaultValue) {
        const select = document.getElementById(selectId);
        if (select && options && options.length > 0) {
            select.innerHTML = options
                .map(option => `<option value="${option.value}" ${option.value == defaultValue ? 'selected' : ''}>${option.label}</option>`)
                .join('');
        } else {
            console.error(`Select element ${selectId} not found or no options data!`);
        }
    }

    async loadYearsFromAPI(apiYears) {
        window.fvLog('🔍 [DIAGNOSTIC] loadYearsFromAPI called with:', apiYears);
        
        const yearSelect = document.getElementById('exc-year-select');
        const compareYearSelect = document.getElementById('exc-compare-year-select');
        
        window.fvLog('🔍 [DIAGNOSTIC] yearSelect element found:', !!yearSelect);
        window.fvLog('🔍 [DIAGNOSTIC] compareYearSelect element found:', !!compareYearSelect);
        window.fvLog('🔍 [DIAGNOSTIC] apiYears valid:', apiYears && apiYears.length > 0);
        
        if (yearSelect && apiYears && apiYears.length > 0) {
            let defaultYear = new Date().getFullYear();
            window.fvLog('🔍 [DIAGNOSTIC] Initial defaultYear:', defaultYear);
            
            try {
                // Essayer d'utiliser le système hybride si disponible
                if (typeof PeriodAPI !== 'undefined') {
                    const currentPeriodInfo = await PeriodAPI.getCurrentPeriodInfo();
                    if (currentPeriodInfo && currentPeriodInfo.current_year) {
                        defaultYear = currentPeriodInfo.current_year;
                        window.fvLog('🔍 [DIAGNOSTIC] defaultYear from PeriodAPI:', defaultYear);
                    }
                }
                
                // Vérifier les paramètres URL
                const urlParams = new URLSearchParams(window.location.search);
                const yearFromUrl = urlParams.get('annee');
                if (yearFromUrl && apiYears.includes(parseInt(yearFromUrl))) {
                    defaultYear = parseInt(yearFromUrl);
                    window.fvLog('🔍 [DIAGNOSTIC] defaultYear from URL:', defaultYear);
                }
                
            } catch (error) {
                window.fvLog('🔍 [DIAGNOSTIC] Error in year selection logic:', error);
            }
            
            window.fvLog('🔍 [DIAGNOSTIC] Final defaultYear:', defaultYear);
            window.fvLog('🔍 [DIAGNOSTIC] Available years:', apiYears);
            
            const yearOptions = apiYears
                .map(year => {
                    const isSelected = parseInt(year) === parseInt(defaultYear);
                    window.fvLog('🔍 [DIAGNOSTIC] Year comparison:', { year, defaultYear, isSelected });
                    return `<option value="${year}" ${isSelected ? 'selected' : ''}>${year}</option>`;
                })
                .join('');
            
            window.fvLog('🔍 [DIAGNOSTIC] Generated year options:', yearOptions);
            
            yearSelect.innerHTML = yearOptions;
            
            window.fvLog('🔍 [DIAGNOSTIC] yearSelect.value after setting innerHTML:', yearSelect.value);
            window.fvLog('🔍 [DIAGNOSTIC] yearSelect.selectedIndex after setting innerHTML:', yearSelect.selectedIndex);
            
            // Si la valeur n'est toujours pas définie, la forcer manuellement
            if (!yearSelect.value && apiYears.includes(defaultYear)) {
                window.fvLog('🔧 [DIAGNOSTIC] Forcing year selection manually:', defaultYear);
                yearSelect.value = defaultYear;
                window.fvLog('🔍 [DIAGNOSTIC] yearSelect.value after manual set:', yearSelect.value);
            }
            
            // Remplir l'année de comparaison par défaut: N-1
            if (compareYearSelect) {
                const defaultCompareYear = (parseInt(defaultYear, 10) - 1);
                window.fvLog('🔍 [DIAGNOSTIC] defaultCompareYear:', defaultCompareYear);
                
                const compareYearOptions = apiYears
                    .map(year => {
                        const isSelected = parseInt(year) === parseInt(defaultCompareYear);
                        window.fvLog('🔍 [DIAGNOSTIC] Compare year comparison:', { year, defaultCompareYear, isSelected });
                        return `<option value="${year}" ${isSelected ? 'selected' : ''}>${year}</option>`;
                    })
                    .join('');
                
                compareYearSelect.innerHTML = compareYearOptions;
                window.fvLog('🔍 [DIAGNOSTIC] compareYearSelect.value after setting innerHTML:', compareYearSelect.value);
                
                // Si la valeur n'est toujours pas définie, la forcer manuellement
                if (!compareYearSelect.value && apiYears.includes(defaultCompareYear)) {
                    window.fvLog('🔧 [DIAGNOSTIC] Forcing compare year selection manually:', defaultCompareYear);
                    compareYearSelect.value = defaultCompareYear;
                    window.fvLog('🔍 [DIAGNOSTIC] compareYearSelect.value after manual set:', compareYearSelect.value);
                }
            }
                
        } else {
            console.error('🔧 loadYearsFromAPI - Year select element not found or no years data!');
            window.fvLog('🔍 [DIAGNOSTIC] yearSelect:', yearSelect);
            window.fvLog('🔍 [DIAGNOSTIC] apiYears:', apiYears);
        }
    }

    async loadYears() {
        const yearSelect = document.getElementById('exc-year-select');
        
        if (yearSelect) {
            try {
                await fluxVisionDynamicConfig.loadFromDatabase();
                const years = fluxVisionDynamicConfig.availableYears;
                const defaultYear = fluxVisionDynamicConfig.defaultYear;

            yearSelect.innerHTML = years
                    .map(year => `<option value="${year}" ${year === defaultYear ? 'selected' : ''}>${year}</option>`)
                .join('');
                
            } catch (error) {
                console.error('Erreur lors du chargement des années, fallback minimal:', error);
                const currentYear = new Date().getFullYear();
                const minimalYears = [currentYear, currentYear - 1, currentYear - 2];

                yearSelect.innerHTML = minimalYears
                    .map(year => `<option value="${year}" ${year === currentYear ? 'selected' : ''}>${year}</option>`)
                    .join('');
            }
        } else {
            console.error('Year select element not found!');
        }
    }

    // Méthode pour synchroniser le header quand les filtres changent
    syncHeaderWithFilters() {
        const selectedPeriode = document.getElementById('exc-period-select')?.value;
        const selectedAnnee = document.getElementById('exc-year-select')?.value;
        const headerPeriod = document.getElementById('header-period');
        
        if (headerPeriod && selectedPeriode && selectedAnnee) {
            if (selectedPeriode === 'custom' && this.customDateRange?.start && this.customDateRange?.end) {
                const s = this.formatDate(this.customDateRange.start);
                const e = this.formatDate(this.customDateRange.end);
                headerPeriod.textContent = `Personnalisé ${selectedAnnee} (${s} → ${e})`;
            } else {
                headerPeriod.textContent = `${selectedPeriode} ${selectedAnnee}`;
            }
        }
    }

    // Nouvelle méthode pour définir intelligemment la période par défaut
    async setDefaultPeriodSmart(periods, periodSelect) {
        // Utiliser la première période disponible comme fallback
        let defaultPeriod = periods.length > 0 ? periods[0].value : null;
        
        try {
            // Vérifier les paramètres URL (priorité la plus haute)
            const urlParams = new URLSearchParams(window.location.search);
            const periodFromUrl = urlParams.get('periode');
            if (periodFromUrl) {
                // ✅ RECHERCHE DIRECTE - PAS DE MAPPING EN DUR !
                // Le PeriodeManagerDB gère la résolution intelligente côté serveur
                
                // Recherche flexible dans les périodes disponibles
                let foundPeriod = null;
                
                // 1. Recherche exacte d'abord
                foundPeriod = periods.find(p => p.value.toLowerCase() === periodFromUrl.toLowerCase());
                
                // 2. Recherche par pattern si pas trouvé (protégée contre les faux positifs comme 'ete' dans 'annee_complete')
                if (!foundPeriod) {
                    const term = periodFromUrl.toLowerCase();
                    const isSeason = ['printemps','ete','été','automne','hiver'].includes(term);

                    // Préférence: suffixe explicite _<saison> (ex: vacances_ete)
                    if (isSeason) {
                        const season = term === 'été' ? 'ete' : term;
                        foundPeriod = periods.find(p => p.value.toLowerCase().endsWith('_' + season));

                        // Sinon, contient avec délimiteur underscore avant/après (évite 'completE')
                        if (!foundPeriod) {
                            const re = new RegExp(`(^|_)${season}($|_)`);
                            foundPeriod = periods.find(p => re.test(p.value.toLowerCase()));
                        }
                    }

                    // Fallback générique: contient, mais on exclut explicitement annee_complete sauf si demandé
                    if (!foundPeriod) {
                        foundPeriod = periods.find(p => {
                            const val = p.value.toLowerCase();
                            const lab = (p.label || '').toLowerCase();
                            if (val === 'annee_complete' && term !== 'annee' && term !== 'année') return false;
                            return val.includes(term) || lab.includes(term);
                        });
                    }
                }
                
                if (foundPeriod) {
                    defaultPeriod = foundPeriod.value;
                } else {
                    // Laisser le PeriodeManagerDB gérer la résolution intelligente
                    defaultPeriod = periodFromUrl;
                }
            }
            // Essayer d'utiliser le système hybride si disponible
            else if (typeof PeriodAPI !== 'undefined') {
                const currentPeriodInfo = await PeriodAPI.getCurrentPeriodInfo();
                if (currentPeriodInfo && currentPeriodInfo.resolved_period && currentPeriodInfo.resolved_period.code_periode) {
                    // Utiliser directement le code_periode du système hybride
                    const hybridPeriod = currentPeriodInfo.resolved_period.code_periode;
                    if (periods.some(p => p.value === hybridPeriod)) {
                        defaultPeriod = hybridPeriod;
                    }
                }
            }
            
        } catch (error) {
        }
                
        // Générer le HTML avec la période par défaut déterminée
        periodSelect.innerHTML = periods
            .map(period => `<option value="${period.value}" ${period.value === defaultPeriod ? 'selected' : ''}>${period.label}</option>`)
            .join('');
    }

    loadPeriodsFromAPI(apiPeriods) {
        const periodSelect = document.getElementById('exc-period-select');

        if (periodSelect) {
            fetch('api/filters/filters_mysql.php')
                .then(response => response.json())
                .then(data => {
                    if (data && data.periodes) {
                        // Les périodes sont déjà au bon format depuis filters/filters_mysql.php
                        // Supprimer les doublons basés sur la valeur
                        const periods = data.periodes.filter((period, index, arr) => 
                    arr.findIndex(p => p.value === period.value) === index
                );

                // Utiliser le système hybride pour déterminer la période par défaut
                this.setDefaultPeriodSmart(periods, periodSelect);
                        
                this.protectPeriodValues(periodSelect);

                // Synchroniser le header après avoir défini la période
                setTimeout(() => this.syncHeaderWithFilters(), 100);
                    }
                })
                .catch(error => {
                    console.error('Erreur chargement API périodes, utilisation fallback dynamique:', error);
                    
                    // Essayer de charger depuis la configuration dynamique
                    fluxVisionDynamicConfig.loadFromDatabase()
                        .then(() => {
                            const periods = fluxVisionDynamicConfig.availablePeriods;
                            // Utiliser le système hybride pour la période par défaut
                            this.setDefaultPeriodSmart(periods, periodSelect);
                        })
                        .catch(() => {
                            // Vraiment en dernier recours
                            const periods = [{ value: 'annee', label: 'Année complète' }];
                            this.setDefaultPeriodSmart(periods, periodSelect);
                        });
                    
                    this.protectPeriodValues(periodSelect);
                });
        } else {
            console.error('Period select element not found!');
        }
    }

    loadPeriods() {
        const periodSelect = document.getElementById('exc-period-select');
        
        if (periodSelect) {
            fetch('api/filters/filters_mysql.php')
                .then(response => response.json())
                .then(data => {
                    if (data && data.periodes) {
                        // Les périodes sont déjà au bon format depuis filters/filters_mysql.php
                        // Supprimer les doublons basés sur la valeur
                        const periods = data.periodes.filter((period, index, arr) => 
                            arr.findIndex(p => p.value === period.value) === index
                        );
                        
                        // Utiliser le système hybride pour déterminer la période par défaut
                        this.setDefaultPeriodSmart(periods, periodSelect);
                        
                        this.protectPeriodValues(periodSelect);
                        
                        // Synchroniser le header après avoir défini la période
                        setTimeout(() => this.syncHeaderWithFilters(), 100);
                    }
                })
                .catch(error => {
                    console.error('Erreur chargement API périodes, utilisation valeurs dynamiques:', error);
                    
                    // Essayer de charger depuis la configuration dynamique
                    fluxVisionDynamicConfig.loadFromDatabase()
                        .then(() => {
                            const periods = fluxVisionDynamicConfig.availablePeriods;
                            // Utiliser le système hybride pour la période par défaut
                            this.setDefaultPeriodSmart(periods, periodSelect);
                        })
                        .catch(() => {
                            // Vraiment en dernier recours
                            const periods = [{ value: 'annee', label: 'Année complète' }];
                            this.setDefaultPeriodSmart(periods, periodSelect);
                        });
                    
                    this.protectPeriodValues(periodSelect);
                });
        } else {
            console.error('Period select element not found!');
        }
    }

    protectPeriodValues(periodSelect) {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList' || mutation.type === 'attributes') {
                    const options = Array.from(periodSelect.options);
                    
                    // Vérifier s'il y a des options valides (au moins une option avec une valeur)
                    const hasValidOptions = options.some(opt => opt.value && opt.value.trim() !== '');
                    
                    if (!hasValidOptions && options.length === 0) {
                        this.loadPeriods();
                    }
                }
            });
        });
        
        observer.observe(periodSelect, { 
            childList: true, 
            attributes: true, 
            subtree: true 
        });
    }

    loadZonesFromAPI(apiZones) {
        window.fvLog('🔍 [DIAGNOSTIC] loadZonesFromAPI called with:', apiZones);
        window.fvLog('🔍 [DIAGNOSTIC] apiZones length:', apiZones ? apiZones.length : 'null');

        const zoneSelect = document.getElementById('exc-zone-select');

        if (zoneSelect && apiZones && apiZones.length > 0) {
            const zones = apiZones.map(zoneName => ({
                value: zoneName,
                label: zoneName
            }));

            // Vérifier s'il y a un paramètre zone dans l'URL
            let defaultZone = zones.find(zone => zone.value === 'CANTAL') ? 'CANTAL' : zones[0].value;
            
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const zoneFromUrl = urlParams.get('zone');
                if (zoneFromUrl) {
                    // Recherche exacte d'abord
                    const foundZone = zones.find(zone => zone.value === zoneFromUrl);
                    if (foundZone) {
                        defaultZone = foundZone.value;
                    }
                }
            } catch (error) {
            }

            zoneSelect.innerHTML = zones
                .map(zone => `<option value="${zone.value}" ${zone.value === defaultZone ? 'selected' : ''}>${zone.label}</option>`)
                .join('');

            window.fvLog('🔍 [DIAGNOSTIC] exc-zone-select filled with zones:', zones.map(z => z.value));

            // Si une zone a été sélectionnée depuis l'URL, déclencher le rechargement des données
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('zone') && defaultZone !== (zones.find(zone => zone.value === 'CANTAL') ? 'CANTAL' : zones[0].value)) {
                // Déclencher le rechargement avec un léger délai pour s'assurer que tous les filtres sont initialisés
                setTimeout(() => {
                    if (typeof this.loadData === 'function') {
                        this.loadData();
                    } else if (window.tdbComparaisonAPI && typeof window.tdbComparaisonAPI.loadData === 'function') {
                        window.tdbComparaisonAPI.loadData();
                    }
                }, 500);
            }
                
        } else {
            console.error('Zone select element not found or no zones data!');
        }
    }

    async loadZones() {
        const zoneSelect = document.getElementById('exc-zone-select');
        
        if (zoneSelect) {
            try {
                await fluxVisionDynamicConfig.loadFromDatabase();
                const zones = fluxVisionDynamicConfig.availableZones.map(zone => ({
                    value: zone,
                    label: zone
                }));
                let defaultZone = fluxVisionDynamicConfig.defaultZone;

                // Vérifier s'il y a un paramètre zone dans l'URL
                try {
                    const urlParams = new URLSearchParams(window.location.search);
                    const zoneFromUrl = urlParams.get('zone');
                    if (zoneFromUrl) {
                        // Recherche exacte d'abord
                        const foundZone = zones.find(zone => zone.value === zoneFromUrl);
                        if (foundZone) {
                            defaultZone = foundZone.value;
                        }
                    }
                } catch (urlError) {
                }

                zoneSelect.innerHTML = zones
                    .map(zone => `<option value="${zone.value}" ${zone.value === defaultZone ? 'selected' : ''}>${zone.label}</option>`)
                    .join('');
                
                // Si une zone a été sélectionnée depuis l'URL, déclencher le rechargement des données
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('zone') && defaultZone !== fluxVisionDynamicConfig.defaultZone) {
                    // Déclencher le rechargement avec un léger délai pour s'assurer que tous les filtres sont initialisés
                    setTimeout(() => {
                        if (typeof this.loadData === 'function') {
                            this.loadData();
                        } else if (window.tdbComparaisonAPI && typeof window.tdbComparaisonAPI.loadData === 'function') {
                            window.tdbComparaisonAPI.loadData();
                        }
                    }, 500);
                }
                
            } catch (error) {
                console.error('Erreur lors du chargement des zones, fallback minimal:', error);
                const zones = [{ value: 'CANTAL', label: 'CANTAL' }];
                zoneSelect.innerHTML = zones
                    .map(zone => `<option value="${zone.value}" selected>${zone.label}</option>`)
                    .join('');
            }
        } else {
            console.error('Zone select element not found!');
        }
    }
}

// =============================================================================
// SECTION 2: API BLOC A - GESTION DES DONNÉES
// =============================================================================

window.TDBComparaisonAPI_Namespace = window.TDBComparaisonAPI_Namespace || {};

class TDBComparaisonAPI {
    constructor() {
        this.apiBaseUrl = window.getApiUrl('legacy/blocks/bloc_a.php');
        this.currentData = null;
        // Instance du graphique Durée de séjour (combiné) pour pouvoir le détruire lors des mises à jour
        this.stayDistributionChart = null;
        // Instance du graphique Mobilité Interne (destinations) pour pouvoir le détruire lors des mises à jour
        this.mobilityDestinationsChart = null;
        // Intervalle personnalisé côté client (si choisi dans le sélecteur)
        this.customDateRange = null; // { start: 'YYYY-MM-DD', end: 'YYYY-MM-DD' }
        // Flag pour bloquer les appels automatiques pendant le traitement de "Année complète"
        this.isProcessingAnneeComplete = false;
        
        this.keyIndicatorsConfig = [
            {
                numero: 1,
                title: "Nuitées totales (FR + INTL)",
                icon: "fa-solid fa-bed",
                unit: "Nuitées",
                comparison: "-24,4%",
                defaultRemark: "Touristes NonLocaux + Etrangers"
            },
            {
                numero: 2,
                title: "Nuitées françaises",
                icon: "fa-solid fa-flag",
                unit: "Nuitées",
                comparison: "-24,3%",
                defaultRemark: "Touristes NonLocaux"
            },
            {
                numero: 3,
                title: "Nuitées internationales",
                icon: "fa-solid fa-globe",
                unit: "Nuitées",
                comparison: "-25,9%",
                defaultRemark: "Touristes Etrangers"
            },
            // 21-23: Durées moyennes (total, FR, INTL)
            {
                numero: 21,
                title: "Durée moyenne totale",
                icon: "fa-solid fa-stopwatch",
                unit: "Nuit(s)",
                defaultRemark: "Tous ≠ Local (FR + INTL)"
            },
            {
                numero: 22,
                title: "Durée moyenne Français",
                icon: "fa-solid fa-user",
                unit: "Nuit(s)",
                defaultRemark: "NONLOCAL"
            },
            {
                numero: 23,
                title: "Durée moyenne International",
                icon: "fa-solid fa-earth-europe",
                unit: "Nuit(s)",
                defaultRemark: "ETRANGER"
            }
        ];
    }

    init() {
        window.fvLog('🚀 TDBComparaisonAPI.init: Starting TDB API initialization...');

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.startTDBInitialization();
            });
        } else {
            this.startTDBInitialization();
        }
    }

    startTDBInitialization() {
        window.fvLog('🚀 startTDBInitialization: Starting TDB API delayed initialization...');
        setTimeout(async () => {
            window.fvLog('🔧 startTDBInitialization: Timeout reached, starting TDB sequence...');

            // D'abord attacher les event listeners
            window.fvLog('🎯 startTDBInitialization: Binding filter events...');
            this.bindFilterEvents();

            // Ensuite charger les données initiales (les filtres sont déjà chargés par FiltersLoader)
            window.fvLog('📊 startTDBInitialization: Loading initial data...');
            await this.loadInitialData();

            window.fvLog('✅ startTDBInitialization: TDB API initialization sequence completed');
        }, 500);
    }

    bindFilterEvents() {
        const yearSelect = document.getElementById('exc-year-select');
        const periodSelect = document.getElementById('exc-period-select');
        const zoneSelect = document.getElementById('exc-zone-select');

        [yearSelect, periodSelect, zoneSelect].forEach(select => {
            if (select) {
                const newSelect = select.cloneNode(true);
                select.parentNode.replaceChild(newSelect, select);
            }
        });

        const newYearSelect = document.getElementById('exc-year-select');
        const newCompareYearSelect = document.getElementById('exc-compare-year-select');
        const newPeriodSelect = document.getElementById('exc-period-select');
        const newZoneSelect = document.getElementById('exc-zone-select');

        if (newYearSelect) {
            newYearSelect.addEventListener('change', async (event) => {
                const rawNewYear = event.target.value;
                // Appliquer la même logique de correction que dans loadData()
                const newYear = (rawNewYear && rawNewYear !== '') ? rawNewYear : fluxVisionDynamicConfig.defaultYear.toString();
                const currentPeriod = document.getElementById('exc-period-select')?.value || null;
                window.fvLog('🔎 Year change detected', { rawNewYear, newYear, currentPeriod, customDateRange: this.customDateRange });

                // Ne pas modifier automatiquement l'année de comparaison, sauf si invalide (compare >= année)
                if (newCompareYearSelect) {
                    const compareVal = parseInt(newCompareYearSelect.value, 10);
                    const yInt = parseInt(newYear, 10);
                    if (!Number.isNaN(compareVal) && !Number.isNaN(yInt) && compareVal >= yInt) {
                        const fallback = yInt - 1;
                        if (!Number.isNaN(fallback)) {
                            newCompareYearSelect.value = String(fallback);
                            window.fvLog('🔎 Compare year auto-corrected because compare >= year', { year: yInt, compare_before: compareVal, compare_after: fallback });
                        }
                    }
                }

                await updatePeriodSelectForYear(newYear, currentPeriod);
                // Si on est en mode personnalisé, conserver le même jour/mois sur la nouvelle année
                if (currentPeriod === 'custom' && this.customDateRange?.start && this.customDateRange?.end) {
                    const toYear = (dateStr, y) => {
                        try {
                            const [yy, mm, dd] = String(dateStr).split('-').map(x => parseInt(x, 10));
                            const month = Math.max(1, Math.min(12, mm || 1));
                            const dayInMonth = Math.max(1, Math.min(new Date(y, month, 0).getDate(), dd || 1));
                            return `${y}-${String(month).padStart(2,'0')}-${String(dayInMonth).padStart(2,'0')}`;
                        } catch (_) { return null; }
                    };
                    const start = toYear(this.customDateRange.start, parseInt(newYear, 10));
                    const end = toYear(this.customDateRange.end, parseInt(newYear, 10));
                    window.fvLog('🔎 Recomputed custom range for new year', { start, end });
                    if (start && end) {
                        this.customDateRange = { start, end };
                        window.fvLog('🔎 Loading data with custom after year change');
                        await this.loadData(newYear, 'custom', null, start, end);
                        this.updateInfographieButton();
                        return;
                    }
                }
                window.fvLog('🔎 Loading data default path after year change');
                // Récupérer les valeurs actuelles des filtres après mise à jour
                this.loadData(
                    document.getElementById('exc-year-select')?.value,
                    document.getElementById('exc-period-select')?.value,
                    document.getElementById('exc-zone-select')?.value
                );
                this.updateInfographieButton();
            });
        }
        if (newCompareYearSelect) {
            newCompareYearSelect.addEventListener('change', () => {
                this.loadData();
                this.updateInfographieButton();
            });
        }
        if (newPeriodSelect) {
            newPeriodSelect.addEventListener('change', (event) => {
                const rawPeriod = event.target.value;
                const correctedPeriod = (rawPeriod && rawPeriod !== '') ? rawPeriod : fluxVisionDynamicConfig.defaultPeriod;
                window.fvLog('🔎 Period change detected', { rawPeriod, correctedPeriod });
                this.loadData();
                this.updateInfographieButton();
            });
        }
        if (newZoneSelect) {
            newZoneSelect.addEventListener('change', (event) => {
                const rawZone = event.target.value;
                const correctedZone = (rawZone && rawZone !== '') ? rawZone : fluxVisionDynamicConfig.defaultZone;
                window.fvLog('🔎 Zone change detected', { rawZone, correctedZone });
                this.loadData();
                this.updateInfographieButton();
            });
        }

        // Mettre à jour le bouton infographie lors du chargement initial
        this.updateInfographieButton();
    }

    updateInfographieButton() {
        // Mettre à jour le lien du bouton infographie avec les filtres actuels
        const infographieBtn = document.getElementById('btn-infographie');
        if (infographieBtn) {
            const yearSelect = document.getElementById('exc-year-select');
            const periodSelect = document.getElementById('exc-period-select');
            const zoneSelect = document.getElementById('exc-zone-select');

            if (yearSelect?.value && periodSelect?.value && zoneSelect?.value) {
                const params = new URLSearchParams({
                    annee: yearSelect.value,
                    periode: periodSelect.value,
                    zone: zoneSelect.value
                });
                // Ajouter l'intervalle personnalisé si actif
                if (this.customDateRange?.start && this.customDateRange?.end) {
                    params.set('debut', this.customDateRange.start);
                    params.set('fin', this.customDateRange.end);
                }
                
                const baseUrl = infographieBtn.href.split('?')[0]; // Garder seulement la partie avant les paramètres
                infographieBtn.href = `${baseUrl}?${params.toString()}`;
                
            }
        }
    }

    async loadInitialData() {
        window.fvLog('📊 loadInitialData: Starting to load initial data...');

        // S'assurer que la configuration est chargée
        window.fvLog('⚙️ loadInitialData: Ensuring config is loaded...');
        await fluxVisionDynamicConfig.loadFromDatabase();
        
        // Attendre que les filtres soient chargés et lisibles
        window.fvLog('⏳ loadInitialData: Waiting for filters to be ready (1000ms)...');
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        // Vérifier que les sélecteurs sont bien remplis
        const yearSelect = document.getElementById('exc-year-select');
        const periodSelect = document.getElementById('exc-period-select');
        const zoneSelect = document.getElementById('exc-zone-select');
        
        window.fvLog('🔍 [DIAGNOSTIC] loadInitialData - Selectors state:', {
            yearSelectExists: !!yearSelect,
            yearSelectHasOptions: yearSelect?.children.length || 0,
            yearSelectValue: yearSelect?.value || 'EMPTY',
            periodSelectExists: !!periodSelect,
            periodSelectHasOptions: periodSelect?.children.length || 0,
            periodSelectValue: periodSelect?.value || 'EMPTY',
            zoneSelectExists: !!zoneSelect,
            zoneSelectHasOptions: zoneSelect?.children.length || 0,
            zoneSelectValue: zoneSelect?.value || 'EMPTY'
        });
        
        // Si le sélecteur d'année n'a pas d'options ou a une valeur vide, forcer le rechargement
        if (!yearSelect || yearSelect.children.length === 0 || !yearSelect.value) {
            window.fvLog('🔧 loadInitialData: Year selector not properly loaded, forcing reload...');
            // Attendre un peu plus et réessayer
            await new Promise(resolve => setTimeout(resolve, 500));
            
            // Si toujours pas chargé, forcer le rechargement des années
            if (!yearSelect?.value && fluxVisionDynamicConfig.data?.annees) {
                window.fvLog('🔧 loadInitialData: Manually reloading years...');
                await filtersLoader.loadYearsFromAPI(fluxVisionDynamicConfig.data.annees);
            }
        }
        
        // Lire les valeurs actuelles des filtres (après le chargement)
        const currentYear = yearSelect?.value;
        const currentPeriod = periodSelect?.value;
        const currentZone = zoneSelect?.value;

        window.fvLog('📖 loadInitialData: Values read from selects:', {
            currentYear,
            currentPeriod,
            currentZone,
            yearElement: !!document.getElementById('exc-year-select'),
            periodElement: !!document.getElementById('exc-period-select'),
            zoneElement: !!document.getElementById('exc-zone-select')
        });
                
        // Utiliser les valeurs des filtres ou fallback
        const finalYear = currentYear || fluxVisionDynamicConfig.defaultYear.toString();
        const finalPeriod = currentPeriod || fluxVisionDynamicConfig.defaultPeriod;
        const finalZone = currentZone || fluxVisionDynamicConfig.defaultZone;

        window.fvLog('🎯 loadInitialData: Final values for loadData:', {
            finalYear,
            finalPeriod,
            finalZone
        });

        await this.loadData(
            finalYear,
            finalPeriod,
            finalZone,
            this.customDateRange?.start || null,
            this.customDateRange?.end || null
        );
    }

    async loadData(annee = null, periode = null, zone = null, debut = null, fin = null) {
        window.fvLog('🔎 loadData:input', { annee, periode, zone, debut, fin });
        // Bloquer les appels automatiques pendant le traitement de "Année complète"
        if (this.isProcessingAnneeComplete && !annee && !periode && !debut && !fin) {
            return;
        }
        
        try {
            // Récupérer les valeurs des filtres standards
            const standardYear = document.getElementById('exc-year-select')?.value;
            const standardPeriod = document.getElementById('exc-period-select')?.value;
            const standardZone = document.getElementById('exc-zone-select')?.value;
            
            // Corriger : vérifier si les valeurs sont vides ou nulles, pas juste falsy
            if (!annee || annee === '') {
                annee = (standardYear && standardYear !== '') ? standardYear : fluxVisionDynamicConfig.defaultYear.toString();
            }
            if (!periode || periode === '') {
                periode = (standardPeriod && standardPeriod !== '') ? standardPeriod : fluxVisionDynamicConfig.defaultPeriod;
            }
            if (!zone || zone === '') {
                zone = (standardZone && standardZone !== '') ? standardZone : fluxVisionDynamicConfig.defaultZone;
            }

            // Debug log pour vérifier les valeurs finales
            window.fvLog('🔧 loadData: valeurs finales après correction', {
                annee: annee,
                periode: periode,
                zone: zone,
                standardYear: standardYear,
                standardPeriod: standardPeriod,
                standardZone: standardZone
            });

            // Si la période est annee_complete et qu'aucune date n'est fournie, injecter l'intervalle de l'année
            if (periode === 'annee_complete' && !debut && !fin && annee) {
                const y = parseInt(annee, 10);
                if (!Number.isNaN(y)) {
                    debut = `${y}-01-01`;
                    fin = `${y}-12-31`;
                }
            }

            // Si la période est custom et qu'aucune borne n'est fournie, reprendre l'intervalle mémorisé
            if (periode === 'custom' && !debut && !fin && this.customDateRange?.start && this.customDateRange?.end) {
                debut = this.customDateRange.start;
                fin = this.customDateRange.end;
                window.fvLog('🔎 loadData: using stored customDateRange', { debut, fin });
            }
            
            const params = new URLSearchParams({ annee, periode, zone });
            // Ajouter l'année de comparaison si présente
            const compareYearSelect = document.getElementById('exc-compare-year-select');
            if (compareYearSelect?.value) {
                params.set('compare_annee', compareYearSelect.value);
            }
            if (debut) params.set('debut', debut);
            if (fin) params.set('fin', fin);
            const url = `${this.apiBaseUrl}?${params.toString()}`;
            window.fvLog('🔎 loadData:url', url);
            this.showLoading();
            
            const response = await fetch(url);
            window.fvLog('🔎 loadData:response', response.status, response.statusText);
            
            const data = await response.json();
            window.fvLog('🔎 loadData:data', { periode: data?.periode, debut: data?.debut, fin: data?.fin });

            if (data.error) {
                throw new Error(data.message);
            }

            this.currentData = data;
            this.updateUI(data);
            this.updateHeader(data);
            window.fvLog('🔎 loadData:done');
        
            } catch (error) {
            console.error('🔧 loadData - Erreur lors du chargement des données:', error);
            this.showError(error.message);
        }
    }

    updateHeader(data) {
        const startDate = this.formatDate(data.debut);
        const endDate = this.formatDate(data.fin);
        
        const headerPeriod = document.getElementById('header-period');
        const excStartDate = document.getElementById('exc-start-date');
        const excEndDate = document.getElementById('exc-end-date');
        const excFooterStartDate = document.getElementById('exc-footer-start-date');
        const excFooterEndDate = document.getElementById('exc-footer-end-date');
        const excFooterYear = document.getElementById('exc-footer-year');
        const compareYearSelect = document.getElementById('exc-compare-year-select');

        // Utiliser la période sélectionnée dans les filtres au lieu de data.periode
        const selectedPeriode = document.getElementById('exc-period-select')?.value || data.periode;
        const selectedAnnee = document.getElementById('exc-year-select')?.value || data.annee;
        
        if (headerPeriod) {
            const compareSuffix = compareYearSelect?.value ? `  (vs ${compareYearSelect.value})` : '';
            headerPeriod.textContent = `${selectedPeriode} ${selectedAnnee}${compareSuffix}`;
        }
        if (excStartDate) excStartDate.textContent = startDate;
        if (excEndDate) excEndDate.textContent = endDate;
        if (excFooterStartDate) excFooterStartDate.textContent = startDate;
        if (excFooterEndDate) excFooterEndDate.textContent = endDate;
        if (excFooterYear) excFooterYear.textContent = selectedAnnee;
    }

    updateUI(data) {
        this.updateTouristsTab(data);
        this.updateComparisonCard(data);
        this.updateExcursionistsTab(data);
        // Graphique combiné FR vs International (diagramme 100% empilé)
        this.loadStayDistributionCombined(data);
        this.initInlinePeriodPicker();
    }

    updateTouristsTab(data) {
        const indicators = data.bloc_a;
        
        const kpiGrid = document.getElementById('key-figures-grid');
        if (kpiGrid) {
            const yearSelectValue = document.getElementById('exc-year-select')?.value;
            const currentYear = (yearSelectValue && yearSelectValue !== '') ? yearSelectValue : fluxVisionDynamicConfig.defaultYear;
            const compareYearEl = document.getElementById('exc-compare-year-select');
            const prevYear = compareYearEl?.value ? parseInt(compareYearEl.value, 10) : (parseInt(currentYear, 10) - 1);
            
            const cardsHtml = this.keyIndicatorsConfig.map(config => {
                const indicator = this.findIndicator(indicators, config.numero);
                
                let currentValue;
                if (config.numero === 21) currentValue = data.avg_stay_all ?? null;
                else if (config.numero === 22) currentValue = data.avg_stay_nonlocal ?? null;
                else if (config.numero === 23) currentValue = data.avg_stay_etranger ?? null;
                else currentValue = indicator?.N || 0;
                const previousValue = indicator?.N_1 || 0;
                const evolutionPct = indicator?.evolution_pct;
                
                let comparisonText = "Non disponible";
                let comparisonClass = "comparison";
                
                if (evolutionPct !== null && evolutionPct !== undefined) {
                    const sign = evolutionPct >= 0 ? "+" : "";
                    comparisonText = `${sign}${evolutionPct}%`;
                    comparisonClass = evolutionPct >= 0 ? "comparison positive" : "comparison negative";
                }
                
                return `
                    <div class="key-figure-card">
                        <div class="card-header">
                            <i class="key-figure-icon ${config.icon}"></i>
                            <span class="indicator-title">${config.title}</span>
                        </div>
                        <div class="key-figure-content">
                            <div class="key-figure-value animate-value" data-value="${currentValue ?? 0}">${(config.numero>=21&&config.numero<=23 && currentValue!=null) ? (Number(currentValue).toFixed(2).replace('.', ',')) : this.formatNumber(currentValue || 0)}</div>
                            <div class="value-prev-year">${prevYear}: ${this.formatNumber(previousValue)}</div>
                            <div class="unit">${config.unit}</div>
                            ${(() => {
                                // Construire l'affichage "+-X% Significatif/Stable"
                                const usedEvolutionPct = (evolutionPct !== null && evolutionPct !== undefined)
                                    ? Number(evolutionPct)
                                    : (previousValue > 0 && currentValue != null)
                                        ? Number((((currentValue - previousValue) / previousValue) * 100).toFixed(1))
                                        : null;
                                if (usedEvolutionPct === null) return '';
                                const sign = usedEvolutionPct >= 0 ? '+' : '';
                                const stable = Math.abs(usedEvolutionPct) < 1; // seuil de stabilité 1%
                                const sig = this.classifyPoissonSignificance(currentValue ?? 0, previousValue ?? 0);
                                const isSignificant = !!(sig && sig.isSignificant);
                                let label = 'Stable';
                                if (!stable) {
                                    label = isSignificant ? 'Significatif' : 'Stable';
                                }
                                const isNeutral = stable || !isSignificant;
                                const cls = isNeutral ? 'comparison neutral' : (usedEvolutionPct > 0 ? 'comparison positive' : 'comparison negative');
                                return `<div class="${cls}">${sign}${usedEvolutionPct}% ${label}</div>`;
                            })()}
                            <div class="remark">${indicator?.remarque || config.defaultRemark}</div>
                        </div>
                    </div>
                `;
            }).join('');
            
            kpiGrid.innerHTML = cardsHtml;
        }

        this.loadDepartementsChart();
        this.loadRegionsChart();
        this.loadPaysChart();
        this.loadMobilityDestinationsChart();
        
        setTimeout(() => {
            this.triggerCounterAnimations();
        }, 100);
    }

    // Graphique 100% empilé avec 2 barres (Français, International)
    loadStayDistributionCombined(data) {
        try {
            const fr = Array.isArray(data.stay_distribution_fr) ? data.stay_distribution_fr : [];
            const intl = Array.isArray(data.stay_distribution_intl) ? data.stay_distribution_intl : [];
            const canvas = document.getElementById('chart-stay-distribution-combined');
            if (!canvas) return;

            if (!fr.length && !intl.length) {
                this.showInfoMessage('chart-stay-distribution-combined', 'Données indisponibles');
                return;
            }

            // Construire la liste des durées avec score (FR+INTL) pour classer
            const dureesSet = new Set();
            fr.forEach(d => dureesSet.add(d.duree));
            intl.forEach(d => dureesSet.add(d.duree));
            const allDurees = Array.from(dureesSet);
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

            // Helper pour agréger les éléments "Autres" par provenance
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
            // Ajouter les top 5
            topDurees.forEach((duree, i) => {
                const frItem = fr.find(x => x.duree === duree);
                const intlItem = intl.find(x => x.duree === duree);
                datasets.push({
                    label: duree,
                    data: [
                        frItem && frItem.part_pct != null ? Number(frItem.part_pct) : 0,
                        intlItem && intlItem.part_pct != null ? Number(intlItem.part_pct) : 0
                    ],
                    backgroundColor: CHART_COLORS[i % CHART_COLORS.length],
                    borderColor: CHART_COLORS[i % CHART_COLORS.length].replace('0.8','1'),
                    borderWidth: 1,
                    rawItems: [frItem || null, intlItem || null]
                });
            });

            // Ajouter la catégorie "Autres" si nécessaire
            if (otherDurees.length > 0) {
                const frAgg = aggregateItems(fr, otherDurees);
                const intlAgg = aggregateItems(intl, otherDurees);
                const frDetails = otherDurees.map(d => fr.find(x => x.duree === d)).filter(Boolean);
                const intlDetails = otherDurees.map(d => intl.find(x => x.duree === d)).filter(Boolean);
                const idx = datasets.length;
                datasets.push({
                    label: 'Autres',
                    data: [Number(frAgg.part_pct || 0), Number(intlAgg.part_pct || 0)],
                    backgroundColor: CHART_COLORS[idx % CHART_COLORS.length],
                    borderColor: CHART_COLORS[idx % CHART_COLORS.length].replace('0.8','1'),
                    borderWidth: 1,
                    rawItems: [frAgg, intlAgg],
                    otherLabels: otherDurees,
                    otherDetails: { fr: frDetails, intl: intlDetails }
                });
            }

            // Détruire l'instance précédente pour éviter l'erreur "Canvas is already in use"
            if (this.stayDistributionChart) {
                try { this.stayDistributionChart.destroy(); } catch (_) { /* noop */ }
            }

            this.stayDistributionChart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Français', 'International'],
                    datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: true, labels: { color: '#f0f5ff' } },
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
                                title: (items) => items?.[0]?.label ?? '',
                                label: (ctx) => {
                                    const ds = ctx.dataset;
                                    const value = ctx.parsed.x ?? ctx.parsed.y ?? ctx.parsed;
                                    const raw = Array.isArray(ds.rawItems) ? ds.rawItems[ctx.dataIndex] : null;
                                    const lines = [
                                        `${ds.label}`,
                                        `Part: ${this.formatPercentage(Number(value))}`
                                    ];
                                    if (raw) {
                                        if (raw.volume !== undefined && raw.volume !== null) {
                                            lines.push(`Nuitées: ${this.formatNumber(Number(raw.volume))}`);
                                        }
                                        if (raw.delta_pct !== undefined && raw.delta_pct !== null) {
                                            const sym = Number(raw.delta_pct) >= 0 ? '+' : '';
                                            lines.push(`Évolution: ${sym}${this.formatPercentage(Number(raw.delta_pct))}`);
                                        }
                                        if (raw.volume_n1 !== undefined && raw.volume_n1 !== null) {
                                            lines.push(`N-1: ${this.formatNumber(Number(raw.volume_n1))}`);
                                        }
                                    }
                                    // Ajouter la composition des "Autres"
                                    if (ds.label === 'Autres' && Array.isArray(ds.otherLabels) && ds.otherLabels.length) {
                                        lines.push(`Inclut: ${ds.otherLabels.join(', ')}`);
                                        // Détail supplémentaire: part/volume/N-1/évolution par durée
                                        const group = ctx.dataIndex === 0 ? (ds.otherDetails?.fr || []) : (ds.otherDetails?.intl || []);
                                        if (group.length) {
                                            lines.push('— Détails —');
                                            group.slice(0, 8).forEach(item => {
                                                const name = item?.duree ?? '';
                                                const part = item?.part_pct != null ? this.formatPercentage(Number(item.part_pct)) : 'n/a';
                                                const vol = item?.volume != null ? this.formatNumber(Number(item.volume)) : '0';
                                                const n1 = item?.volume_n1 != null ? this.formatNumber(Number(item.volume_n1)) : '0';
                                                const delta = item?.delta_pct != null ? `${Number(item.delta_pct) >= 0 ? '+' : ''}${this.formatPercentage(Number(item.delta_pct))}` : 'n/a';
                                                lines.push(`${name}: ${part} | ${vol} (N) | ${n1} (N-1) | ${delta}`);
                                            });
                                            if (group.length > 8) {
                                                lines.push(`(+${group.length - 8} autres…)`);
                                            }
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
                            ticks: { color: '#a0a8b8', callback: v => `${Number(v).toLocaleString('fr-FR')}%` },
                            grid: { color: 'rgba(160, 168, 184, 0.1)' }
                        },
                        y: {
                            stacked: true,
                            grid: { display: false },
                            ticks: { color: '#f0f5ff' }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('Erreur rendu graphique combiné durée de séjour:', e);
        }
    }

    async loadDepartementsChart() {
        try {
            // Utiliser les données actuelles de l'API principale si disponibles
            let annee, periode, zone, debut, fin;
            
            if (this.currentData) {
                // Utiliser les données de la dernière requête API principale
                annee = this.currentData.annee;
                periode = this.currentData.periode;
                zone = this.currentData.zone_observation;
                debut = this.currentData.debut;
                fin = this.currentData.fin;
            } else {
                // Fallback vers les filtres standards - gestion améliorée des valeurs vides
                const yearSelectValue = document.getElementById('year-select')?.value || document.getElementById('exc-year-select')?.value;
                const periodSelectValue = document.getElementById('period-select')?.value || document.getElementById('exc-period-select')?.value;
                const zoneSelectValue = document.getElementById('zone-select')?.value || document.getElementById('exc-zone-select')?.value;

                annee = (yearSelectValue && yearSelectValue !== '') ? yearSelectValue : fluxVisionDynamicConfig.defaultYear;
                periode = (periodSelectValue && periodSelectValue !== '') ? periodSelectValue : fluxVisionDynamicConfig.defaultPeriod;
                zone = (zoneSelectValue && zoneSelectValue !== '') ? zoneSelectValue : fluxVisionDynamicConfig.defaultZone;
            }

            const baseUrl = CantalDestinationConfig.basePath + '/';
            const params = new URLSearchParams({ annee, periode, zone, limit: '15' });
            const compareYearSelectD = document.getElementById('exc-compare-year-select');
            if (compareYearSelectD?.value) params.set('compare_annee', compareYearSelectD.value);
            if (debut) params.set('debut', debut);
            if (fin) params.set('fin', fin);
            const url = `${baseUrl}api/legacy/blocks/bloc_d1_cached.php?${params.toString()}`;
            
            const response = await fetch(url);
            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            this.renderDepartementsChart(data);

        } catch (error) {
            console.error('Erreur lors du chargement du graphique départements:', error);
        }
    }

    renderDepartementsChart(departementsData) {
        const canvasId = 'chart-departements';
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error('Canvas chart-departements non trouvé');
            return;
        }

        if (this.departementsChart) {
            this.departementsChart.destroy();
        }

        const chartData = this.buildChartData(departementsData, 'nom_departement', 'n_nuitees', 'Nuitées', fluxVisionDynamicConfig.chartLimits.departements);
        
        if (!chartData) {
            this.showInfoMessage(canvasId, "Données (Départements) indisponibles");
            return;
        }

        const options = {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(15, 18, 26, 0.9)',
                    titleColor: '#a35fff',
                    titleFont: { weight: 'bold', size: 14 },
                    bodyColor: '#f0f5ff',
                    borderColor: 'rgba(0, 242, 234, 0.5)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        title: (ctx) => ctx[0]?.label ?? '',
                        label: (ctx) => {
                            const val = ctx.parsed.x ?? ctx.parsed.y ?? ctx.parsed;
                            const rawItem = ctx.dataset.rawData?.[ctx.dataIndex];
                            if (!rawItem) return `Nuitées: ${this.formatNumber(val)}`;

                            const lines = [`Nuitées: ${this.formatNumber(rawItem.n_nuitees ?? val)}`];
                            if (rawItem.part_pct !== undefined && rawItem.part_pct !== null) {
                                lines.push(`Part: ${this.formatPercentage(rawItem.part_pct)}`);
                            }
                            if (rawItem.delta_pct !== undefined && rawItem.delta_pct !== null) {
                                const symbol = rawItem.delta_pct >= 0 ? '+' : '';
                                lines.push(`Évolution: ${symbol}${this.formatPercentage(rawItem.delta_pct)}`);
                            }
                            if (rawItem.n_nuitees_n1 !== undefined && rawItem.n_nuitees_n1 !== null) {
                                lines.push(`N-1: ${this.formatNumber(rawItem.n_nuitees_n1)}`);
                            }
                            return lines;
                        }
                    }
                }
            },
            animation: {
                duration: 1200,
                easing: 'easeOutQuart',
                delay: (context) => context.dataIndex * 100
            },
            scales: {
                x: { 
                    beginAtZero: true, 
                    grid: { color: 'rgba(160, 168, 184, 0.1)' }, 
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
                        maxTicksLimit: 15,
                        color: '#f0f5ff',
                        font: { size: 11 }
                    }
                }
            }
        };

        this.departementsChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: chartData,
            options: options
        });

    }

    async loadMobilityDestinationsChart() {
        try {
            // Utiliser les données actuelles de l'API principale si disponibles
            let annee, periode, zone, debut, fin;
            
            if (this.currentData) {
                // Utiliser les données de la dernière requête API principale
                annee = this.currentData.annee;
                periode = this.currentData.periode;
                zone = this.currentData.zone_observation;
                debut = this.currentData.debut;
                fin = this.currentData.fin;
            } else {
                // Fallback vers les filtres standards - gestion améliorée des valeurs vides
                const yearSelectValue = document.getElementById('year-select')?.value || document.getElementById('exc-year-select')?.value;
                const periodSelectValue = document.getElementById('period-select')?.value || document.getElementById('exc-period-select')?.value;
                const zoneSelectValue = document.getElementById('zone-select')?.value || document.getElementById('exc-zone-select')?.value;

                annee = (yearSelectValue && yearSelectValue !== '') ? yearSelectValue : fluxVisionDynamicConfig.defaultYear;
                periode = (periodSelectValue && periodSelectValue !== '') ? periodSelectValue : fluxVisionDynamicConfig.defaultPeriod;
                zone = (zoneSelectValue && zoneSelectValue !== '') ? zoneSelectValue : fluxVisionDynamicConfig.defaultZone;
            }

            const baseUrl = CantalDestinationConfig.basePath + '/';
            const params = new URLSearchParams({ annee, periode, zone });
            const compareYearSelect = document.getElementById('exc-compare-year-select');
            if (compareYearSelect?.value) params.set('compare_annee', compareYearSelect.value);
            if (debut) params.set('debut', debut);
            if (fin) params.set('fin', fin);
            const url = `${baseUrl}api/analytics/communes_excursion.php?${params.toString()}`;
            
            const response = await fetch(url);
            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            this.renderMobilityDestinationsChart(data);

        } catch (error) {
            console.error('Erreur lors du chargement du graphique mobilité destinations:', error);
        }
    }

    renderMobilityDestinationsChart(destinationsData) {
        const canvasId = 'chart-mobility-destinations';
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error('Canvas chart-mobility-destinations non trouvé');
            return;
        }

        if (this.mobilityDestinationsChart) {
            this.mobilityDestinationsChart.destroy();
        }

        // Vérifier que nous avons des données
        if (!destinationsData.destinations || destinationsData.destinations.length === 0) {
            this.showInfoMessage(canvasId, "Données (Destinations) indisponibles");
            return;
        }

        const chartData = this.buildChartData(
            destinationsData.destinations,
            'nom_commune',
            'total_visiteurs',
            'Visiteurs',
            10  // Top 10 destinations
        );
        
        if (!chartData) {
            this.showInfoMessage(canvasId, "Données (Destinations) indisponibles");
            return;
        }

        const options = {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(15, 18, 26, 0.9)',
                    titleColor: '#a35fff',
                    titleFont: { weight: 'bold', size: 14 },
                    bodyColor: '#f0f5ff',
                    borderColor: 'rgba(0, 242, 234, 0.5)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        title: (ctx) => ctx[0]?.label ?? '',
                        label: (ctx) => {
                            const val = ctx.parsed.x ?? ctx.parsed.y ?? ctx.parsed;
                            const rawItem = ctx.dataset.rawData?.[ctx.dataIndex];
                            if (!rawItem) return `Visiteurs: ${this.formatNumber(val)}`;

                            const lines = [`Visiteurs: ${this.formatNumber(rawItem.total_visiteurs)}`];
                            if (rawItem.total_visiteurs_n1 > 0) {
                                lines.push(`N-1: ${this.formatNumber(rawItem.total_visiteurs_n1)}`);
                                if (rawItem.evolution_pct !== null) {
                                    const sign = rawItem.evolution_pct > 0 ? '+' : '';
                                    lines.push(`Évolution: ${sign}${rawItem.evolution_pct}%`);
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
        };

        this.mobilityDestinationsChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: chartData,
            options: options
        });

    }

    async loadRegionsChart() {
        try {
            // Utiliser les données actuelles de l'API principale si disponibles
            let annee, periode, zone, debut, fin;
            
            if (this.currentData) {
                // Utiliser les données de la dernière requête API principale
                annee = this.currentData.annee;
                periode = this.currentData.periode;
                zone = this.currentData.zone_observation;
                debut = this.currentData.debut;
                fin = this.currentData.fin;
            } else {
                // Fallback vers les filtres standards - gestion améliorée des valeurs vides
                const yearSelectValue = document.getElementById('year-select')?.value || document.getElementById('exc-year-select')?.value;
                const periodSelectValue = document.getElementById('period-select')?.value || document.getElementById('exc-period-select')?.value;
                const zoneSelectValue = document.getElementById('zone-select')?.value || document.getElementById('exc-zone-select')?.value;

                annee = (yearSelectValue && yearSelectValue !== '') ? yearSelectValue : fluxVisionDynamicConfig.defaultYear;
                periode = (periodSelectValue && periodSelectValue !== '') ? periodSelectValue : fluxVisionDynamicConfig.defaultPeriod;
                zone = (zoneSelectValue && zoneSelectValue !== '') ? zoneSelectValue : fluxVisionDynamicConfig.defaultZone;
            }

            const baseUrl = CantalDestinationConfig.basePath + '/';
            const params = new URLSearchParams({ annee, periode, zone, limit: '5' });
            const compareYearSelectR = document.getElementById('exc-compare-year-select');
            if (compareYearSelectR?.value) params.set('compare_annee', compareYearSelectR.value);
            if (debut) params.set('debut', debut);
            if (fin) params.set('fin', fin);
            const url = `${baseUrl}api/legacy/blocks/bloc_d2_simple.php?${params.toString()}`;
            
            const response = await fetch(url);
            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            this.renderRegionsChart(data);

        } catch (error) {
            console.error('Erreur lors du chargement du graphique régions:', error);
            this.showInfoMessage('chart-regions', 'Données (Régions) indisponibles');
        }
    }

    renderRegionsChart(regionsData) {
        const canvasId = 'chart-regions';
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error('Canvas chart-regions non trouvé');
            return;
        }

        if (this.regionsChart) {
            this.regionsChart.destroy();
        }

        const chartData = this.buildChartData(regionsData, 'nom_region', 'n_nuitees', 'Nuitées', 5);

        if (!chartData) {
            this.showInfoMessage(canvasId, "Données (Régions) indisponibles");
            return;
        }

        const options = {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(15, 18, 26, 0.9)',
                    titleColor: '#a35fff',
                    titleFont: { weight: 'bold', size: 14 },
                    bodyColor: '#f0f5ff',
                    borderColor: 'rgba(0, 242, 234, 0.5)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        title: (ctx) => ctx[0]?.label ?? '',
                        label: (ctx) => {
                            const val = ctx.parsed.x ?? ctx.parsed.y ?? ctx.parsed;
                            const rawItem = ctx.dataset.rawData?.[ctx.dataIndex];
                            if (!rawItem) return `Nuitées: ${this.formatNumber(val)}`;

                            const lines = [`Nuitées: ${this.formatNumber(rawItem.n_nuitees ?? val)}`];
                            if (rawItem.part_pct !== undefined && rawItem.part_pct !== null) {
                                lines.push(`Part: ${this.formatPercentage(rawItem.part_pct)}`);
                            }
                            if (rawItem.delta_pct !== undefined && rawItem.delta_pct !== null) {
                                const symbol = rawItem.delta_pct >= 0 ? '+' : '';
                                lines.push(`Évolution: ${symbol}${this.formatPercentage(rawItem.delta_pct)}`);
                            }
                            if (rawItem.n_nuitees_n1 !== undefined && rawItem.n_nuitees_n1 !== null) {
                                lines.push(`N-1: ${this.formatNumber(rawItem.n_nuitees_n1)}`);
                            }
                            return lines;
                        }
                    }
                }
            },
            animation: {
                duration: 1200,
                easing: 'easeOutQuart',
                delay: (context) => context.dataIndex * 150
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: 'rgba(160, 168, 184, 0.1)' }, 
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
                        maxTicksLimit: 5,
                        color: '#f0f5ff',
                        font: { size: 11 }
                    }
                }
            }
        };

        // Créer le graphique avec le gestionnaire de graphiques
        this.regionsChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: chartData,
            options: options
        });
    }

    async loadPaysChart() {
        try {
            // Utiliser les données actuelles de l'API principale si disponibles
            let annee, periode, zone, debut, fin;
            
            if (this.currentData) {
                // Utiliser les données de la dernière requête API principale
                annee = this.currentData.annee;
                periode = this.currentData.periode;
                zone = this.currentData.zone_observation;
                debut = this.currentData.debut;
                fin = this.currentData.fin;
            } else {
                // Fallback vers les filtres standards - gestion améliorée des valeurs vides
                const yearSelectValue = document.getElementById('year-select')?.value || document.getElementById('exc-year-select')?.value;
                const periodSelectValue = document.getElementById('period-select')?.value || document.getElementById('exc-period-select')?.value;
                const zoneSelectValue = document.getElementById('zone-select')?.value || document.getElementById('exc-zone-select')?.value;

                annee = (yearSelectValue && yearSelectValue !== '') ? yearSelectValue : fluxVisionDynamicConfig.defaultYear;
                periode = (periodSelectValue && periodSelectValue !== '') ? periodSelectValue : fluxVisionDynamicConfig.defaultPeriod;
                zone = (zoneSelectValue && zoneSelectValue !== '') ? zoneSelectValue : fluxVisionDynamicConfig.defaultZone;
            }

            const baseUrl = CantalDestinationConfig.basePath + '/';
            const params = new URLSearchParams({ annee, periode, zone, limit: '5' });
            const compareYearSelectP = document.getElementById('exc-compare-year-select');
            if (compareYearSelectP?.value) params.set('compare_annee', compareYearSelectP.value);
            if (debut) params.set('debut', debut);
            if (fin) params.set('fin', fin);
            const url = `${baseUrl}api/legacy/blocks/bloc_d3_simple.php?${params.toString()}`;
            
            const response = await fetch(url);
            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            this.renderPaysChart(data);

        } catch (error) {
            console.error('Erreur lors du chargement du graphique pays:', error);
            this.showInfoMessage('chart-pays', 'Données (Pays) indisponibles');
        }
    }

    renderPaysChart(paysData) {
        const canvasId = 'chart-pays';
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error('Canvas chart-pays non trouvé');
            return;
        }

        if (this.paysChart) {
            this.paysChart.destroy();
        }

        const chartData = this.buildChartData(paysData, 'nom_pays', 'n_nuitees', 'Nuitées', 5);
        
        if (!chartData) {
            this.showInfoMessage(canvasId, "Données (Pays) indisponibles");
            return;
        }

        const options = {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'x',
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(15, 18, 26, 0.9)',
                    titleColor: '#a35fff',
                    titleFont: { weight: 'bold', size: 14 },
                    bodyColor: '#f0f5ff',
                    borderColor: 'rgba(0, 242, 234, 0.5)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        title: (ctx) => ctx[0]?.label ?? '',
                        label: (ctx) => {
                            const val = ctx.parsed.x ?? ctx.parsed.y ?? ctx.parsed;
                            const rawItem = ctx.dataset.rawData?.[ctx.dataIndex];
                            
                            if (!rawItem) return `Nuitées: ${this.formatNumber(val)}`;

                            const nNuitees = parseInt(rawItem.n_nuitees) || val;
                            const lines = [`Nuitées: ${this.formatNumber(nNuitees)}`];
                            
                            if (rawItem.part_pct !== undefined && rawItem.part_pct !== null && !isNaN(rawItem.part_pct)) {
                                const partPct = parseFloat(rawItem.part_pct);
                                lines.push(`Part: ${this.formatPercentage(partPct)}`);
                            }
                            
                            if (rawItem.delta_pct !== undefined && rawItem.delta_pct !== null && !isNaN(rawItem.delta_pct)) {
                                const deltaPct = parseFloat(rawItem.delta_pct);
                                const symbol = deltaPct >= 0 ? '+' : '';
                                lines.push(`Évolution: ${symbol}${this.formatPercentage(deltaPct)}`);
                            }
                            
                            // Afficher la valeur N-1
                            if (rawItem.n_nuitees_n1 !== undefined && rawItem.n_nuitees_n1 !== null && !isNaN(rawItem.n_nuitees_n1)) {
                                const nNuiteesN1 = parseInt(rawItem.n_nuitees_n1);
                                lines.push(`N-1: ${this.formatNumber(nNuiteesN1)}`);
                            }
                            
                            return lines;
                        }
                    }
                }
            },
            animation: {
                duration: 1200,
                easing: 'easeOutQuart',
                delay: (context) => context.dataIndex * 150
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        autoSkip: false,
                        maxTicksLimit: 5,
                        color: '#f0f5ff',
                        font: { size: 11 },
                        maxRotation: 45,
                        minRotation: 0
                    }
                },
                y: { 
                    beginAtZero: true, 
                    grid: { color: 'rgba(160, 168, 184, 0.1)' }, 
                    ticks: { 
                        color: '#a0a8b8', 
                        font: { size: 10 },
                        callback: function(value) {
                            return value.toLocaleString('fr-FR');
                        }
                    } 
                }
            }
        };

        // Créer le graphique avec le gestionnaire de graphiques
        this.paysChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: chartData,
            options: options
        });

    }

    buildChartData(items, labelKey, valueKey, datasetLabel = 'Valeurs', limit = 5) {
        if (!Array.isArray(items) || items.length === 0) {
    return null;
}

        const CHART_BORDER_COLORS = CHART_COLORS.map(c => c.replace('0.8', '1'));
        
        const filteredData = items
            .filter(item => {
                return item && typeof item === 'object' && 
                       item[valueKey] != null && !isNaN(Number(item[valueKey]));
            })
            .sort((a, b) => (Number(b[valueKey]) || 0) - (Number(a[valueKey]) || 0))
            .slice(0, limit);
            
        if (filteredData.length === 0) {
            return null;
        }
        
        return {
            labels: filteredData.map(i => i[labelKey] || 'Inconnu'),
            datasets: [{
                label: datasetLabel,
                data: filteredData.map(i => Number(i[valueKey])),
                backgroundColor: CHART_COLORS.slice(0, filteredData.length),
                borderColor: CHART_BORDER_COLORS.slice(0, filteredData.length),
                borderWidth: 1, 
                borderRadius: 3, 
                barThickness: 'flex', 
                maxBarThickness: 25,
                rawData: filteredData,
                primaryValueKey: valueKey
            }]
        };
    }

    // Test statistique (Poisson) pour volumes N vs N-1
    // Retourne { z, threshold, isSignificant, direction }
    classifyPoissonSignificance(current, previous, alpha = 0.05) {
        const N = Number(current);
        const N1 = Number(previous);
        if (!isFinite(N) || !isFinite(N1) || N < 0 || N1 < 0) {
            return { z: null, threshold: null, isSignificant: false, direction: 'stable' };
        }
        const denom = Math.sqrt(Math.max(N + N1, 1));
        const z = denom > 0 ? (N - N1) / denom : 0;
        const threshold = alpha === 0.01 ? 2.575 : 1.96;
        const isSignificant = Math.abs(z) >= threshold;
        let direction = 'stable';
        if (isSignificant) direction = z > 0 ? 'increase' : 'decrease';
        return { z, threshold, isSignificant, direction };
    }

    // --- Inline Period Picker (inspired by prototype) ---
    initInlinePeriodPicker() {
        
        const wrap = document.getElementById('dashboardPeriodPicker');
        
        // Empêche les doubles initialisations
        if (wrap.getAttribute('data-pp-init') === '1') {
            return;
        }
        wrap.setAttribute('data-pp-init', '1');
        

        const toggle = document.getElementById('pp-toggle');
        const panel = document.getElementById('pp-panel');
        const close = document.getElementById('pp-close');
        const display = document.getElementById('pp-display');
        const list = document.getElementById('pp-list');
        const monthSel = document.getElementById('pp-month');
        // Sélecteur d'année du panneau avancé (distinct du filtre standard)
        const yearSel = document.getElementById('pp-year-select');
        const grid = document.getElementById('pp-grid');
        const hint = document.getElementById('pp-hint');
        const prevY = document.getElementById('pp-prev-year');
        const nextY = document.getElementById('pp-next-year');
        const prevM = document.getElementById('pp-prev-month');
        const nextM = document.getElementById('pp-next-month');
        const todayBtn = document.getElementById('pp-today');

        const monthsFR = ["janvier","février","mars","avril","mai","juin","juillet","août","septembre","octobre","novembre","décembre"];        
        const atMidnight = (d)=> new Date(d.getFullYear(), d.getMonth(), d.getDate());
        const fmt = (d)=> `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}/${d.getFullYear()}`;
        const isSame = (a,b)=> a && b && a.getFullYear()===b.getFullYear() && a.getMonth()===b.getMonth() && a.getDate()===b.getDate();
        const inRange = (x,a,b)=> a && b && x>=atMidnight(a) && x<=atMidnight(b);

        // Préréglages dynamiques issus de la base de données
        let dbPresets = [];
        const loadDbPresets = async (year) => {
            try {
                // Endpoint qui renvoie { code: { nom, debut, fin } }
                const apiUrl = window.getApiUrl('filters/get_periodes.php') + `?action=year&annee=${year}`;
                const res = await fetch(apiUrl, { credentials: 'same-origin' });
                const json = await res.json();
                
                const items = Object.entries(json || {}).map(([code, info]) => {
                    const start = new Date(info.debut?.replace(' ', 'T') || `${year}-01-01T00:00:00`);
                    const end = new Date(info.fin?.replace(' ', 'T') || `${year}-12-31T23:59:59`);
                    return { code, label: info.nom || code, start, end };
                });
                
                // Ajouter "Année complète" en premier
                const fullStart = new Date(year, 0, 1);
                const fullEnd = new Date(year, 11, 31);
                dbPresets = [{ code: 'annee_complete', label: `Année complète ${year}`, start: fullStart, end: fullEnd }, ...items];
                
            } catch (e) {
                console.error('🔧 loadDbPresets - Chargement préréglages périodes (DB) échoué:', e);
                dbPresets = [{ code: 'annee_complete', label: `Année complète ${year}`, start: new Date(year,0,1), end: new Date(year,11,31) }];
            }
        };

        const state = { view:new Date(), start:null, end:null };

        async function buildPresetList(year){
            await loadDbPresets(year);
            // Sécurisé: construire la liste sans innerHTML
            while (list.firstChild) list.removeChild(list.firstChild);
            dbPresets.forEach((preset, index) => {
                const item = document.createElement('div');
                item.className = 'item';
                item.dataset.i = String(index);
                if (preset.code != null) item.dataset.code = String(preset.code);
                const label = document.createElement('span');
                label.textContent = String(preset.label ?? preset.code ?? '');
                item.appendChild(label);
                list.appendChild(item);
            });
        }

        function populateSelectors(){
            monthSel.innerHTML = monthsFR
                .map((m,i)=>`<option value="${i}">${m.charAt(0).toUpperCase()+m.slice(1)}</option>`)
                .join('');
            
            // Copier les années depuis le sélecteur standard vers le sélecteur avancé
            try {
                const std = document.getElementById('exc-year-select');
                if (yearSel && std && std.options && std.options.length > 0) {
                    yearSel.innerHTML = Array.from(std.options)
                        .map(opt => `<option value="${String(opt.value)}">${String(opt.textContent || opt.value)}</option>`)
                        .join('');
                }
            } catch(_) {}

            // On synchronise ensuite avec la valeur actuelle
            syncSelectors();
        }
        function syncSelectors(){             
            // Mettre à jour le sélecteur de mois
            if (monthSel) {
                monthSel.value = String(state.view.getMonth()); 
            }
            
            // Mettre à jour le sélecteur d'année
            const yStr = String(state.view.getFullYear());
            if (yearSel) {
                // Si l'option n'existe pas encore (cas où le panneau ouvre très tôt), on la crée
                const exists = Array.from(yearSel.options).some(o => String(o.value) === yStr);
                if (!exists) {
                    const opt = document.createElement('option');
                    opt.value = yStr;
                    opt.textContent = yStr;
                    yearSel.appendChild(opt);
                }
                yearSel.value = yStr;
            }
            
            // Synchroniser avec le filtre standard d'année si nécessaire
            const stdYearSelect = document.getElementById('exc-year-select');
            if (stdYearSelect && stdYearSelect.value !== yStr) {
                stdYearSelect.value = yStr;
                // Déclencher l'événement change pour mettre à jour les périodes
                stdYearSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
            
            buildPresetList(state.view.getFullYear()); 
        }

        function render(){
            const y=state.view.getFullYear(), m=state.view.getMonth();
            grid.innerHTML='';
            const first=new Date(y,m,1); let startCol=first.getDay()-1; if(startCol<0) startCol=6; const days=new Date(y,m+1,0).getDate();
            let row=document.createElement('tr'); for(let i=0;i<startCol;i++){ const td=document.createElement('td'); td.className='empty'; row.appendChild(td); }
            for(let d=1; d<=days; d++){
                if(row.children.length===7){ grid.appendChild(row); row=document.createElement('tr'); }
                const td=document.createElement('td'); td.textContent=d; const cur=new Date(y,m,d); const today=atMidnight(new Date());
                if(isSame(cur,today)) td.classList.add('today');
                if(state.start && !state.end && isSame(cur,state.start)) td.classList.add('pp-start');
                if(state.start && state.end){ if(isSame(cur,state.start)) td.classList.add('pp-start'); else if(isSame(cur,state.end)) td.classList.add('pp-end'); else if(inRange(atMidnight(cur), state.start, state.end)) td.classList.add('pp-inrange'); }
                td.addEventListener('click', ()=> onDayClick(cur)); row.appendChild(td);
            }
            while(row.children.length<7){ const td=document.createElement('td'); td.className='empty'; row.appendChild(td);} grid.appendChild(row);
            if(!state.start) hint.textContent='Sélectionne la date de début…'; else if(!state.end) hint.textContent=`Début : ${fmt(state.start)} — sélectionne la date de fin…`; else hint.textContent=`Sélection : ${fmt(state.start)} → ${fmt(state.end)}`;
        }
        async function onDayClick(date){
            if(!state.start || (state.start && state.end)){
                state.start=atMidnight(date);
                state.end=null;
                updateDisplay();
                render();
                return;
            }
            state.end=atMidnight(date);
            if(state.end<state.start){ const t=state.start; state.start=state.end; state.end=t; }
            updateDisplay();
            render();
            // Une fois l'intervalle choisi, enregistrer l'intervalle personnalisé et basculer en mode custom
            try {
                // ✅ NE PAS changer automatiquement l'année - laisser l'utilisateur la contrôler
                // const year = state.start.getFullYear();
                // const yearSelectEl = document.getElementById('exc-year-select');
                // if (yearSelectEl) yearSelectEl.value = String(year);
                
                // Utiliser l'année actuellement sélectionnée, pas celle de la date
                const currentYear = document.getElementById('exc-year-select')?.value || new Date().getFullYear();
                
                // Stocker l'intervalle en ISO pour l'API
                const fmtISO = (d)=> `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
                if (window.tdbComparaisonAPI) {
                    window.tdbComparaisonAPI.customDateRange = { start: fmtISO(state.start), end: fmtISO(state.end) };
                }
                
                // Bascule explicite en mode "custom" avec l'année actuelle
                const desiredCode = 'custom';
                await updatePeriodSelectForYear(currentYear, desiredCode);
                
                // Ne pas recharger automatiquement si "Année complète" est sélectionné
                // car cela écrase les données correctes
                const periodSelectEl = document.getElementById('exc-period-select');
                const isAnneeComplete = periodSelectEl?.value === 'annee_complete';
                                
                if (!isAnneeComplete) {
                    if (typeof window.tdbComparaisonAPI !== 'undefined' && typeof window.tdbComparaisonAPI.loadData === 'function') {
                        const r = window.tdbComparaisonAPI.customDateRange || null;
                        window.tdbComparaisonAPI.loadData(null, null, null, r?.start || null, r?.end || null);
                    } else if (typeof this.loadData === 'function') {
                        const r = this.customDateRange || null;
                        this.loadData(null, null, null, r?.start || null, r?.end || null);
                    }
                } else {
                }
            } catch(_) { /* noop */ }
        }
        function updateDisplay(){
            if(!display) return;
            
            if (state.start && state.end) {
                if (isSame(state.start, state.end)) {
                    // Même jour sélectionné (cas du bouton "Aujourd'hui")
                    display.textContent = `Aujourd'hui (${fmt(state.start)})`;
                } else {
                    // Intervalle de plusieurs jours
                    display.textContent = `Intervalle personnalisé (${fmt(state.start)} → ${fmt(state.end)})`;
                }
            } else {
                display.textContent = 'Sélecteur avancé…';
            }
            
            // S'assurer que le bouton n'est jamais disabled
            try { toggle.removeAttribute('disabled'); toggle.classList.remove('is-disabled'); } catch(_){ }
        }

        // wiring
        // Détacher le panneau dans un portail dédié (#portal-root)
        let portalRoot = document.getElementById('portal-root');
        if (!portalRoot) {
            portalRoot = document.createElement('div');
            portalRoot.id = 'portal-root';
            document.body.appendChild(portalRoot);
        }
        try {
            if (panel && panel.parentElement !== portalRoot) {
                portalRoot.appendChild(panel);
                panel.classList.add('floating');
            }
        } catch(_){}

        // Overlay (backdrop) - réutiliser s'il existe déjà
        let overlay = portalRoot.querySelector('.pp-backdrop');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'pp-backdrop';
            overlay.setAttribute('hidden', '');
            portalRoot.appendChild(overlay);
        }

        // Focus management
        let lastFocusedElement = null;
        function getFocusableElements(container) {
            return Array.from(container.querySelectorAll('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'))
                .filter(el => el instanceof HTMLElement && !el.hasAttribute('disabled'));
        }
        function trapFocus(e) {
            if (e.key !== 'Tab') return;
            const focusable = getFocusableElements(panel);
            if (focusable.length === 0) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey) {
                if (document.activeElement === first) { e.preventDefault(); last.focus(); }
            } else {
                if (document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        }

        function openPanel() {
            if (!panel.classList.contains('open')) {
                lastFocusedElement = document.activeElement;
                panel.classList.add('open');
                overlay.removeAttribute('hidden');
                toggle?.setAttribute('aria-expanded', 'true');
                panel.removeAttribute('aria-hidden');
                panel.removeAttribute('inert');
                
                // Positionner le panneau avec un délai pour permettre le rendu
                setTimeout(() => {
                    positionPanel();
                }, 10);
                
                document.addEventListener('keydown', trapFocus);
                const focusable = getFocusableElements(panel);
                (focusable[0] || close || panel).focus();
                document.body.style.overflow = 'hidden';
            }
        }
        function closePanel() {
            if (panel.classList.contains('open')) {
                // D'abord, déplacer le focus hors du panel pour éviter le warning aria-hidden
                try {
                    if (lastFocusedElement && lastFocusedElement instanceof HTMLElement) {
                        lastFocusedElement.focus();
                    } else if (toggle && toggle instanceof HTMLElement) {
                        toggle.focus();
                    } else {
                        document.body.focus({ preventScroll: true });
                    }
                } catch(_){}
                panel.classList.remove('open');
                overlay.setAttribute('hidden', '');
                toggle?.setAttribute('aria-expanded', 'false');
                // Reporter l'application d'aria-hidden/inert au prochain tick après le déplacement du focus
                requestAnimationFrame(()=>{
                    panel.setAttribute('aria-hidden', 'true');
                    panel.setAttribute('inert', '');
                });
                document.removeEventListener('keydown', trapFocus);
                document.body.style.overflow = '';
            }
        }

        function positionPanel() {
            if (!toggle || !panel) return;
            
            const r = toggle.getBoundingClientRect();
            const panelRect = panel.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            
            // Position initiale (en bas à droite du bouton)
            let left = Math.round(r.left);
            let top = Math.round(r.bottom + 6);
            
            // Vérifier si le panneau dépasse à droite
            if (left + panelRect.width > viewportWidth) {
                left = Math.max(10, viewportWidth - panelRect.width - 10);
            }
            
            // Vérifier si le panneau dépasse à gauche
            if (left < 10) {
                left = 10;
            }
            
            // Vérifier si le panneau dépasse en bas
            if (top + panelRect.height > viewportHeight) {
                // Placer le panneau au-dessus du bouton
                top = Math.max(10, r.top - panelRect.height - 6);
            }
            
            // Vérifier si le panneau dépasse en haut
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
            panel.style.minWidth = panel.style.minWidth || '680px';
            
            window.fvLog('📍 [PeriodPicker] Positionnement panneau:', {
                left: left,
                top: top,
                viewportWidth: viewportWidth,
                viewportHeight: viewportHeight,
                panelWidth: panelRect.width,
                panelHeight: panelRect.height
            });
        }
        
        // Fonction utilitaire pour vérifier si le panneau est visible
        function isPanelVisible() {
            if (!panel) return false;
            
            const rect = panel.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            
            return (
                rect.left >= 0 &&
                rect.top >= 0 &&
                rect.right <= viewportWidth &&
                rect.bottom <= viewportHeight
            );
        }

        const onToggle = ()=>{
            if (panel.classList.contains('open')) {
                closePanel();
            } else {
                openPanel();
            }
        };
        toggle?.addEventListener('click', onToggle);
        // Délégation pour survivre à d'éventuels remplacements DOM
        document.addEventListener('click', (ev)=>{
            const btn = ev.target.closest && ev.target.closest('#pp-toggle');
            if(btn && btn !== toggle){ onToggle(); }
        });
        // Gestion robuste du bouton de fermeture
        if (close) {
            window.fvLog('✅ [PeriodPicker] Bouton de fermeture trouvé:', close);
            
            close.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                window.fvLog('🔴 [PeriodPicker] Fermeture par croix cliquée');
                closePanel();
            });
            
            // Ajouter un gestionnaire de clic avec délégation d'événements
            document.addEventListener('click', (e) => {
                if (e.target && e.target.id === 'pp-close') {
                    e.preventDefault();
                    e.stopPropagation();
                    window.fvLog('🔴 [PeriodPicker] Fermeture par croix (délégation)');
                    closePanel();
                }
            });
        } else {
            console.warn('⚠️ [PeriodPicker] Bouton de fermeture non trouvé');
        }
        overlay.addEventListener('click', closePanel);
        document.addEventListener('mousedown', (ev)=>{ if(panel.classList.contains('open')){ if(!panel.contains(ev.target) && !wrap.contains(ev.target)) closePanel(); } });
        window.addEventListener('scroll', ()=>{ 
            if(panel.classList.contains('open')) {
                // Utiliser requestAnimationFrame pour optimiser les performances
                requestAnimationFrame(() => positionPanel());
            }
        }, { passive:true });
        
        window.addEventListener('resize', ()=>{ 
            if(panel.classList.contains('open')) {
                // Délai pour éviter les calculs multiples pendant le redimensionnement
                clearTimeout(window.resizeTimeout);
                window.resizeTimeout = setTimeout(() => {
                    positionPanel();
                }, 100);
            }
        });
        document.addEventListener('keydown', (ev)=>{ if(ev.key==='Escape') closePanel(); });
        prevY.onclick=()=>{ state.view.setFullYear(state.view.getFullYear()-1); syncSelectors(); render(); };
        nextY.onclick=()=>{ state.view.setFullYear(state.view.getFullYear()+1); syncSelectors(); render(); };
        prevM.onclick=()=>{ state.view.setMonth(state.view.getMonth()-1); syncSelectors(); render(); };
        nextM.onclick=()=>{ state.view.setMonth(state.view.getMonth()+1); syncSelectors(); render(); };
        todayBtn.onclick=()=>{ 
            const today = new Date();
            state.view = today;
            
            // Définir la date actuelle comme sélection (début et fin)
            state.start = atMidnight(today);
            state.end = atMidnight(today);
            
            window.fvLog('📅 [PeriodPicker] Bouton "Aujourd\'hui" cliqué:', {
                year: today.getFullYear(),
                month: today.getMonth(),
                date: today.toDateString(),
                start: state.start,
                end: state.end
            });
            
            // Mettre à jour l'année dans le sélecteur d'année
            if (yearSel) {
                yearSel.value = String(today.getFullYear());
            }
            
            // Mettre à jour le mois dans le sélecteur de mois
            if (monthSel) {
                monthSel.value = String(today.getMonth());
            }
            
            // Synchroniser avec le filtre standard d'année
            const stdYearSelect = document.getElementById('exc-year-select');
            if (stdYearSelect) {
                stdYearSelect.value = String(today.getFullYear());
                // Déclencher l'événement change pour mettre à jour les périodes
                stdYearSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
            
            syncSelectors(); 
            updateDisplay(); // Mettre à jour l'affichage du bouton
            render(); 
        };
        monthSel.addEventListener('change', ()=>{ state.view.setMonth(parseInt(monthSel.value,10)); render(); });
        yearSel.addEventListener('change', ()=>{
            const y = parseInt(yearSel.value, 10);
            if (!Number.isNaN(y)) {
                state.view.setFullYear(y);
                // Synchroniser aussi le filtre standard d'année
                const std = document.getElementById('exc-year-select');
                if (std) std.value = String(y);
                syncSelectors();
                render();
            }
        });
                list.addEventListener('click', async (e)=>{
            
            const item = e.target.closest('.item');
            if(!item) {
                return;
            }
            
            const i = +item.dataset.i;
            const preset = dbPresets[i];
            
            if(!preset) {
                return;
            }
            
            // Mettre à jour l'affichage du sélecteur avancé
            state.start = atMidnight(preset.start);
            state.end = atMidnight(preset.end);
            state.view = new Date(state.start);
            
            updateDisplay();
            syncSelectors();
            render();
            
            // Utiliser directement les sélecteurs standards
            try {
                const yearSelectEl = document.getElementById('exc-year-select');
                const periodSelectEl = document.getElementById('exc-period-select');
                const desiredCode = item.dataset.code;

                if (yearSelectEl) {
                    yearSelectEl.value = String(state.view.getFullYear());
                }
                
                // Pour "année complète", utiliser des dates explicites
                if (desiredCode === 'annee_complete') {
                    const api = window.tdbComparaisonAPI || this;
                    
                    if (api) {
                        api.customDateRange = null;
                        const year = state.view.getFullYear();
                        const startDate = `${year}-01-01`;
                        const endDate = `${year}-12-31`;
                        if (typeof api.loadData === 'function') {
                            // Pour l'année complète, utiliser la période 'annee_complete' avec les dates explicites
                            await api.loadData(year, 'annee_complete', null, startDate, endDate);
                                                
                            // Mettre à jour le sélecteur de période standard pour afficher "Année complète"
                            if (periodSelectEl) {
                                // Vérifier si "Année complète" existe déjà
                                let anneeCompleteOption = periodSelectEl.querySelector('option[value="annee_complete"]');
                                
                                if (!anneeCompleteOption) {
                                    // Créer l'option "Année complète" si elle n'existe pas
                                    anneeCompleteOption = document.createElement('option');
                                    anneeCompleteOption.value = 'annee_complete';
                                    anneeCompleteOption.textContent = 'Année complète';
                                    
                                    // Ajouter au début du sélecteur
                                    periodSelectEl.insertBefore(anneeCompleteOption, periodSelectEl.firstChild);
                                }
                                
                                // Sélectionner "Année complète"
                                anneeCompleteOption.selected = true;
                                
                                // Mettre à jour le bouton infographie avec les nouvelles valeurs
                                if (api && typeof api.updateInfographieButton === 'function') {
                                    api.updateInfographieButton();
                                }
                            }
                        } else {
                        }
                    } else {
                    }
                } else {
                    // Pour les autres périodes, utiliser le code de la période
                    const api = window.tdbComparaisonAPI || this;
                    if (api && typeof api.loadData === 'function') {
                        api.loadData(state.view.getFullYear(), desiredCode, null, null, null);
                        await updatePeriodSelectForYear(state.view.getFullYear(), desiredCode);
                        // Mettre à jour le bouton infographie avec les nouvelles valeurs
                        if (api && typeof api.updateInfographieButton === 'function') {
                            api.updateInfographieButton();
                        }
                    }
                }
                
                // Marquer que nous sommes en train de traiter "Année complète"
                const api = window.tdbComparaisonAPI || this;
                if (api) {
                    api.isProcessingAnneeComplete = true;
                    
                    // Réactiver après un délai
                    setTimeout(() => {
                        api.isProcessingAnneeComplete = false;
                    }, 1000);
                }
                
            } catch(error) {
                console.error('Erreur:', error);
            }
            // Ne pas fermer automatiquement: l'utilisateur décide (clic extérieur ou croix)
        });

        // init
        populateSelectors(); 
        buildPresetList(state.view.getFullYear()); 
        render();
    }

    formatNumber(num) {
        return (typeof num === 'number' && !isNaN(num)) ? num.toLocaleString('fr-FR') : '-';
    }

    formatPercentage(num, decimals = 1) {
        return (typeof num === 'number' && !isNaN(num))
            ? num.toFixed(decimals).replace('.', ',') + '%'
            : '-';
    }

    showInfoMessage(canvasId, message) {
        const canvas = document.getElementById(canvasId);
        if (canvas) {
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#a0a8b8';
            ctx.font = '14px Roboto';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(message, canvas.width / 2, canvas.height / 2);
        }
    }

    triggerCounterAnimations() {
        const elementsToAnimate = document.querySelectorAll('.animate-value');
        elementsToAnimate.forEach(element => {
            const value = parseFloat(element.dataset.value);
            if (!isNaN(value)) {
                this.animateCounter(element, value);
            }
        });
    }

    animateCounter(element, targetValue, duration = 1200) {
        if (!element || typeof targetValue !== 'number' || isNaN(targetValue)) {
            return;
        }

        const startTime = performance.now();
        const startValue = 0;

        function updateCounter(currentTime) {
            const elapsedTime = currentTime - startTime;
            const progress = Math.min(elapsedTime / duration, 1);
            const easedProgress = 1 - Math.pow(1 - progress, 3); // ease-out-cubic

            const currentValue = startValue + (targetValue * easedProgress);
            element.textContent = Math.round(currentValue).toLocaleString('fr-FR');

            if (progress < 1) {
                requestAnimationFrame(updateCounter);
            } else {
                element.textContent = targetValue.toLocaleString('fr-FR');
            }
        }

        requestAnimationFrame(updateCounter);
    }

    updateExcursionistsTab(data) {
        const indicators = data.bloc_a;
        
        const excIndicatorsConfig = [
            {
                numero: 16,
                title: "Excursionnistes totaux",
                icon: "fa-solid fa-person-hiking",
                unit: "Présences",
                defaultRemark: "Total excursionnistes non-locaux sur la période"
            },
            {
                numero: 10,
                title: "Périodes clés mi-saison",
                icon: "fa-solid fa-calendar-days",
                unit: "Présences",
                defaultRemark: "Jour de plus forte affluence"
            },
            {
                numero: 17,
                title: "Présences 2e samedi",
                icon: "fa-solid fa-calendar-week",
                unit: "Présences",
                defaultRemark: "Excursionnistes non-locaux - 2e samedi"
            },
            {
                numero: 18,
                title: "Présences 3e samedi",
                icon: "fa-solid fa-calendar-check",
                unit: "Présences",
                defaultRemark: "Excursionnistes non-locaux - 3e samedi"
            }
        ];

        const excKpiGrid = document.getElementById('exc-key-figures-grid');
        if (excKpiGrid) {
            const yearSelectValue = document.getElementById('exc-year-select')?.value;
            const currentYear = (yearSelectValue && yearSelectValue !== '') ? yearSelectValue : fluxVisionDynamicConfig.defaultYear;
            const compareYearEl = document.getElementById('exc-compare-year-select');
            const prevYear = compareYearEl?.value ? parseInt(compareYearEl.value, 10) : (parseInt(currentYear, 10) - 1);
            
            const cardsHtml = excIndicatorsConfig.map(config => {
                const indicator = this.findIndicator(indicators, config.numero);
                
                let subtitle = '';
                if (config.numero === 10 && indicator?.date) {
                    subtitle = this.formatDate(indicator.date);
                } else {
                    subtitle = indicator?.remarque || config.defaultRemark;
                }
                
                const currentValue = indicator?.N || 0;
                const previousValue = indicator?.N_1 || 0;
                const evolutionPct = indicator?.evolution_pct;
                
                let comparisonText = "Non disponible";
                let comparisonClass = "comparison";
                
                if (evolutionPct !== null && evolutionPct !== undefined) {
                    const sign = evolutionPct >= 0 ? "+" : "";
                    comparisonText = `${sign}${evolutionPct}%`;
                    comparisonClass = evolutionPct >= 0 ? "comparison positive" : "comparison negative";
                }
                
                return `
                    <div class="key-figure-card">
                        <div class="card-header">
                            <i class="key-figure-icon ${config.icon}"></i>
                            <span class="indicator-title">${config.title}</span>
                        </div>
                        <div class="key-figure-content">
                            <div class="key-figure-value animate-value" data-value="${currentValue}">${this.formatNumber(currentValue)}</div>
                            <div class="value-prev-year">${prevYear}: ${this.formatNumber(previousValue)}</div>
                            <div class="unit">${config.unit}</div>
                            ${(() => {
                                // Harmoniser l'affichage avec les touristes: +X% Significatif/Stable
                                const usedEvolutionPct = (evolutionPct !== null && evolutionPct !== undefined)
                                    ? Number(evolutionPct)
                                    : (previousValue > 0 && currentValue != null)
                                        ? Number((((currentValue - previousValue) / previousValue) * 100).toFixed(1))
                                        : null;
                                if (usedEvolutionPct === null) return '';
                                const sign = usedEvolutionPct >= 0 ? '+' : '';
                                const stable = Math.abs(usedEvolutionPct) < 1;
                                let label = 'Stable';
                                if (!stable) {
                                    const sig = this.classifyPoissonSignificance(currentValue ?? 0, previousValue ?? 0);
                                    label = (sig && sig.isSignificant) ? 'Significatif' : 'Stable';
                                }
                                const cls = stable ? 'comparison neutral' : (usedEvolutionPct > 0 ? 'comparison positive' : 'comparison negative');
                                return `<div class="${cls}">${sign}${usedEvolutionPct}% ${label}</div>`;
                            })()}
                            <div class="remark">${subtitle}</div>
                        </div>
                    </div>
                `;
            }).join('');
            
            excKpiGrid.innerHTML = cardsHtml;
        }

        this.loadExcursionistsCharts();
        
        setTimeout(() => {
            this.triggerCounterAnimations();
        }, 100);
    }

    loadExcursionistsCharts() {
        this.loadExcDepartementsChart();
        this.loadExcRegionsChart();
        this.loadExcPaysChart();
    }

    async loadExcDepartementsChart() {
        try {
            // Utiliser les données actuelles de l'API principale si disponibles
            let annee, periode, zone, debut, fin;
            
            if (this.currentData) {
                // Utiliser les données de la dernière requête API principale
                annee = this.currentData.annee;
                periode = this.currentData.periode;
                zone = this.currentData.zone_observation;
                debut = this.currentData.debut;
                fin = this.currentData.fin;
            } else {
                // Fallback vers les filtres standards
                const yearSelectValue = document.getElementById('exc-year-select')?.value;
                annee = (yearSelectValue && yearSelectValue !== '') ? yearSelectValue : fluxVisionDynamicConfig.defaultYear;
                periode = document.getElementById('exc-period-select')?.value || fluxVisionDynamicConfig.defaultPeriod;
                zone = document.getElementById('exc-zone-select')?.value || fluxVisionDynamicConfig.defaultZone;
            }

            const baseUrl = CantalDestinationConfig.basePath + '/';
            const params = new URLSearchParams({ annee, periode, zone, limit: '15' });
            if (debut) params.set('debut', debut);
            if (fin) params.set('fin', fin);
            const response = await fetch(`${baseUrl}api/legacy/blocks/bloc_d1_exc_cached.php?${params.toString()}`);
            const data = await response.json();

            if (data.error) {
                throw new Error(data.message || 'Erreur API excursionnistes départements');
            }

            this.renderExcDepartementsChart(data);

        } catch (error) {
            console.error('Erreur lors du chargement du graphique départements excursionnistes:', error);
            this.showInfoMessage('exc-chart-departements', 'Données non disponibles');
        }
    }

    async loadExcRegionsChart() {
        try {
            // Utiliser les données actuelles de l'API principale si disponibles
            let annee, periode, zone, debut, fin;
            
            if (this.currentData) {
                // Utiliser les données de la dernière requête API principale
                annee = this.currentData.annee;
                periode = this.currentData.periode;
                zone = this.currentData.zone_observation;
                debut = this.currentData.debut;
                fin = this.currentData.fin;
            } else {
                // Fallback vers les filtres standards
                const yearSelectValue = document.getElementById('exc-year-select')?.value;
                annee = (yearSelectValue && yearSelectValue !== '') ? yearSelectValue : fluxVisionDynamicConfig.defaultYear;
                periode = document.getElementById('exc-period-select')?.value || fluxVisionDynamicConfig.defaultPeriod;
                zone = document.getElementById('exc-zone-select')?.value || fluxVisionDynamicConfig.defaultZone;
            }

            const baseUrl = CantalDestinationConfig.basePath + '/';
            const params = new URLSearchParams({ annee, periode, zone, limit: '5' });
            if (debut) params.set('debut', debut);
            if (fin) params.set('fin', fin);
            const response = await fetch(`${baseUrl}api/legacy/blocks/bloc_d2_exc_cached.php?${params.toString()}`);
            const data = await response.json();

            if (data.error) {
                throw new Error(data.message || 'Erreur API excursionnistes régions');
            }

            this.renderExcRegionsChart(data);

        } catch (error) {
            console.error('Erreur lors du chargement du graphique régions excursionnistes:', error);
            this.showInfoMessage('exc-chart-regions', 'Données non disponibles');
        }
    }

    async loadExcPaysChart() {
        try {
            // Utiliser les données actuelles de l'API principale si disponibles
            let annee, periode, zone, debut, fin;
            
            if (this.currentData) {
                // Utiliser les données de la dernière requête API principale
                annee = this.currentData.annee;
                periode = this.currentData.periode;
                zone = this.currentData.zone_observation;
                debut = this.currentData.debut;
                fin = this.currentData.fin;
            } else {
                // Fallback vers les filtres standards
                const yearSelectValue = document.getElementById('exc-year-select')?.value;
                annee = (yearSelectValue && yearSelectValue !== '') ? yearSelectValue : fluxVisionDynamicConfig.defaultYear;
                periode = document.getElementById('exc-period-select')?.value || fluxVisionDynamicConfig.defaultPeriod;
                zone = document.getElementById('exc-zone-select')?.value || fluxVisionDynamicConfig.defaultZone;
            }

            const baseUrl = CantalDestinationConfig.basePath + '/';
            const params = new URLSearchParams({ annee, periode, zone, limit: '5' });
            if (debut) params.set('debut', debut);
            if (fin) params.set('fin', fin);
            const response = await fetch(`${baseUrl}api/legacy/blocks/bloc_d3_exc_cached.php?${params.toString()}`);
            const data = await response.json();

            if (data.error) {
                throw new Error(data.message || 'Erreur API excursionnistes pays');
            }

            this.renderExcPaysChart(data);

        } catch (error) {
            console.error('Erreur lors du chargement du graphique pays excursionnistes:', error);
            this.showInfoMessage('exc-chart-pays', 'Données non disponibles');
        }
    }

    // =============================================================================
    // MÉTHODES DE RENDU POUR LES GRAPHIQUES EXCURSIONNISTES
    // =============================================================================

    renderExcDepartementsChart(departementsData) {
        const canvasId = 'exc-chart-departements';
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error('Canvas exc-chart-departements non trouvé');
            return;
        }

        // Détruire le graphique existant s'il existe
        if (this.excDepartementsChart) {
            this.excDepartementsChart.destroy();
        }

        // Utiliser la même logique que les graphiques touristes
        const chartData = this.buildChartData(departementsData, 'nom_departement', 'n_presences', 'Présences', 15);
        
        if (!chartData) {
            this.showInfoMessage(canvasId, "Données (Départements) indisponibles");
            return;
        }

        // Utiliser les mêmes options que les graphiques touristes (barres horizontales)
        const options = {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y', // Barres horizontales comme les touristes
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(15, 18, 26, 0.9)',
                    titleColor: '#a35fff',
                    titleFont: { weight: 'bold', size: 14 },
                    bodyColor: '#f0f5ff',
                    borderColor: 'rgba(0, 242, 234, 0.5)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        title: (ctx) => ctx[0]?.label ?? '',
                        label: (ctx) => {
                            const val = ctx.parsed.x ?? ctx.parsed.y ?? ctx.parsed;
                            const rawItem = ctx.dataset.rawData?.[ctx.dataIndex];
                            if (!rawItem) return `Présences: ${this.formatNumber(val)}`;

                            const lines = [`Présences: ${this.formatNumber(rawItem.n_presences ?? val)}`];
                            if (rawItem.part_pct !== undefined && rawItem.part_pct !== null) {
                                lines.push(`Part: ${this.formatPercentage(rawItem.part_pct)}`);
                            }
                            if (rawItem.delta_pct !== undefined && rawItem.delta_pct !== null) {
                                const symbol = rawItem.delta_pct >= 0 ? '+' : '';
                                lines.push(`Évolution: ${symbol}${this.formatPercentage(rawItem.delta_pct)}`);
                            }
                            if (rawItem.n_presences_n1 !== undefined && rawItem.n_presences_n1 !== null) {
                                lines.push(`N-1: ${this.formatNumber(rawItem.n_presences_n1)}`);
                            }
                            return lines;
                        }
                    }
                }
            },
            animation: {
                duration: 1200,
                easing: 'easeOutQuart',
                delay: (context) => context.dataIndex * 100
            },
            scales: {
                x: { 
                    beginAtZero: true, 
                    grid: { color: 'rgba(160, 168, 184, 0.1)' }, 
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
                        maxTicksLimit: 15,
                        color: '#f0f5ff',
                        font: { size: 11 }
                    }
                }
            }
        };

        // Créer le graphique
        this.excDepartementsChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: chartData,
            options: options
        });
    }

    renderExcRegionsChart(regionsData) {
        const canvasId = 'exc-chart-regions';
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error('Canvas exc-chart-regions non trouvé');
            return;
        }

        // Détruire le graphique existant s'il existe
        if (this.excRegionsChart) {
            this.excRegionsChart.destroy();
        }

        // Utiliser la même logique que les graphiques touristes
        const chartData = this.buildChartData(regionsData, 'nom_region', 'n_presences', 'Présences', 5);
        
        if (!chartData) {
            this.showInfoMessage(canvasId, "Données (Régions) indisponibles");
            return;
        }

        // Utiliser les mêmes options que les graphiques touristes (barres horizontales)
        const options = {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(15, 18, 26, 0.9)',
                    titleColor: '#a35fff',
                    titleFont: { weight: 'bold', size: 14 },
                    bodyColor: '#f0f5ff',
                    borderColor: 'rgba(0, 242, 234, 0.5)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        title: (ctx) => ctx[0]?.label ?? '',
                        label: (ctx) => {
                            const val = ctx.parsed.x ?? ctx.parsed.y ?? ctx.parsed;
                            const rawItem = ctx.dataset.rawData?.[ctx.dataIndex];
                            if (!rawItem) return `Présences: ${this.formatNumber(val)}`;

                            const lines = [`Présences: ${this.formatNumber(rawItem.n_presences ?? val)}`];
                            if (rawItem.part_pct !== undefined && rawItem.part_pct !== null) {
                                lines.push(`Part: ${this.formatPercentage(rawItem.part_pct)}`);
                            }
                            if (rawItem.delta_pct !== undefined && rawItem.delta_pct !== null) {
                                const symbol = rawItem.delta_pct >= 0 ? '+' : '';
                                lines.push(`Évolution: ${symbol}${this.formatPercentage(rawItem.delta_pct)}`);
                            }
                            if (rawItem.n_presences_n1 !== undefined && rawItem.n_presences_n1 !== null) {
                                lines.push(`N-1: ${this.formatNumber(rawItem.n_presences_n1)}`);
                            }
                            return lines;
                        }
                    }
                }
            },
            animation: {
                duration: 1200,
                easing: 'easeOutQuart',
                delay: (context) => context.dataIndex * 150
            },
            scales: {
                x: { 
                    beginAtZero: true, 
                    grid: { color: 'rgba(160, 168, 184, 0.1)' }, 
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
                        maxTicksLimit: 5,
                        color: '#f0f5ff',
                        font: { size: 11 }
                    }
                }
            }
        };

        // Créer le graphique
        this.excRegionsChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: chartData,
            options: options
        });
    }

    renderExcPaysChart(paysData) {
        const canvasId = 'exc-chart-pays';
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error('Canvas exc-chart-pays non trouvé');
            return;
        }

        // Détruire le graphique existant s'il existe
        if (this.excPaysChart) {
            this.excPaysChart.destroy();
        }

        // Utiliser la même logique que les graphiques touristes
        const chartData = this.buildChartData(paysData, 'nom_pays', 'n_presences', 'Présences', 5);
        
        if (!chartData) {
            this.showInfoMessage(canvasId, "Données (Pays) indisponibles");
            return;
        }

        // Pour les pays, utiliser des barres verticales (comme dans l'original touristes)
        const options = {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'x', // Barres verticales pour les pays
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(15, 18, 26, 0.9)',
                    titleColor: '#a35fff',
                    titleFont: { weight: 'bold', size: 14 },
                    bodyColor: '#f0f5ff',
                    borderColor: 'rgba(0, 242, 234, 0.5)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        title: (ctx) => ctx[0]?.label ?? '',
                        label: (ctx) => {
                            const val = ctx.parsed.x ?? ctx.parsed.y ?? ctx.parsed;
                            const rawItem = ctx.dataset.rawData?.[ctx.dataIndex];
                            if (!rawItem) return `Présences: ${this.formatNumber(val)}`;

                            const lines = [`Présences: ${this.formatNumber(rawItem.n_presences ?? val)}`];
                            if (rawItem.part_pct !== undefined && rawItem.part_pct !== null) {
                                lines.push(`Part: ${this.formatPercentage(rawItem.part_pct)}`);
                            }
                            if (rawItem.delta_pct !== undefined && rawItem.delta_pct !== null) {
                                const symbol = rawItem.delta_pct >= 0 ? '+' : '';
                                lines.push(`Évolution: ${symbol}${this.formatPercentage(rawItem.delta_pct)}`);
                            }
                            if (rawItem.n_presences_n1 !== undefined && rawItem.n_presences_n1 !== null) {
                                lines.push(`N-1: ${this.formatNumber(rawItem.n_presences_n1)}`);
                            }
                            return lines;
                        }
                    }
                }
            },
            animation: {
                duration: 1200,
                easing: 'easeOutQuart',
                delay: (context) => context.dataIndex * 150
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        autoSkip: false,
                        maxTicksLimit: 5,
                        color: '#f0f5ff',
                        font: { size: 11 },
                        maxRotation: 45,
                        minRotation: 0
                    }
                },
                y: { 
                    beginAtZero: true, 
                    grid: { color: 'rgba(160, 168, 184, 0.1)' }, 
                    ticks: { 
                        color: '#a0a8b8', 
                        font: { size: 10 },
                        callback: function(value) {
                            return value.toLocaleString('fr-FR');
                        }
                    } 
                }
            }
        };

        // Créer le graphique
        this.excPaysChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: chartData,
            options: options
        });
    }

    updateComparisonCard(data) {
        const totalNuitees = this.findIndicator(data.bloc_a, 1);
        const totalExc = this.findIndicator(data.bloc_a, 16);

        const touristsTotal = document.getElementById('tourists-total');
        const excursionistsTotal = document.getElementById('excursionists-total');

        if (touristsTotal) {
            const formattedTourists = this.formatNumber(totalNuitees?.N || 0);
            touristsTotal.textContent = formattedTourists;
        }
        
        if (excursionistsTotal) {
            const formattedExc = this.formatNumber(totalExc?.N || 0);
            excursionistsTotal.textContent = formattedExc;
        }
    }

    findIndicator(indicators, numero) {
        return indicators.find(ind => ind.numero === numero);
    }

    formatNumber(num) {
        if (!num || num === 0) return '0';
        
        const numericValue = Number(num);
        if (isNaN(numericValue)) {
            console.error('formatNumber: Invalid number:', num);
            return '0';
        }
        
        const formatted = new Intl.NumberFormat('fr-FR').format(numericValue);
        return formatted;
    }

    formatDate(dateStr) {
        if (!dateStr) return '--/--/----';
        const date = new Date(dateStr);
        return date.toLocaleDateString('fr-FR');
    }

    calculateDailyAverage(total, startDate, endDate) {
        if (!total || !startDate || !endDate) return '0';
        const start = new Date(startDate);
        const end = new Date(endDate);
        const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
        return this.formatNumber(Math.round(total / days));
    }

    showLoading() {
        const kpiGrid = document.getElementById('key-figures-grid');
        const excKpiGrid = document.getElementById('exc-key-figures-grid');
        
        [kpiGrid, excKpiGrid].forEach(grid => {
            if (grid) {
                grid.innerHTML = '<div class="loading">Chargement des données...</div>';
            }
        });
    }

    showError(message) {
        const kpiGrid = document.getElementById('key-figures-grid');
        const excKpiGrid = document.getElementById('exc-key-figures-grid');
        
        [kpiGrid, excKpiGrid].forEach(grid => {
            if (grid) {
                grid.innerHTML = `<div class="error">Erreur: ${message}</div>`;
            }
        });
    }
}

// =============================================================================
// SECTION 3: GESTION DE LA COMPARAISON AVANCÉE
// =============================================================================

class AdvancedComparison {
    constructor() {
        this.apiBaseUrl = window.getApiUrl('analytics/comparison.php');
        this.currentComparisonData = null;
        this.isComparing = false;
        
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.bindComparisonEvents();
            });
        } else {
            this.bindComparisonEvents();
        }
    }

    bindComparisonEvents() {
        
        const compareBtn = document.getElementById('comp-compare-btn');
        const resetBtn = document.getElementById('comp-reset-btn');
        
        if (compareBtn) {
            compareBtn.addEventListener('click', () => {
                this.performComparison();
            });
        }
        
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                this.resetComparison();
            });
        }
    }

    async performDefaultComparison() {
        
        // Ne pas déclencher si une comparaison est déjà en cours
        if (this.isComparing) {
            return;
        }
        
        // Ne pas déclencher si des résultats sont déjà affichés
        const resultsDiv = document.getElementById('comparison-results');
        if (resultsDiv && this.currentComparisonData) {
            return;
        }
        
        // Vérifier que les filtres existent et sont chargés
        const yearA = document.getElementById('comp-year-a');
        const yearB = document.getElementById('comp-year-b');
        const periodA = document.getElementById('comp-period-a');
        const periodB = document.getElementById('comp-period-b');
        const zoneA = document.getElementById('comp-zone-a');
        const zoneB = document.getElementById('comp-zone-b');
        
        // Si les filtres ne sont pas encore chargés, réessayer plus tard
        if (!yearA || !yearB || !periodA || !periodB || !zoneA || !zoneB) {
            setTimeout(() => this.performDefaultComparison(), 500);
            return;
        }
        
        // Définir les valeurs par défaut si les selects sont vides
        if (!yearA.value) yearA.value = fluxVisionDynamicConfig.defaultYear.toString();
        if (!yearB.value) yearB.value = fluxVisionDynamicConfig.previousYear.toString();
        if (!periodA.value) periodA.value = fluxVisionDynamicConfig.defaultPeriod;
        if (!periodB.value) periodB.value = fluxVisionDynamicConfig.defaultPeriod;
        if (!zoneA.value) zoneA.value = fluxVisionDynamicConfig.defaultZone;
        if (!zoneB.value) zoneB.value = fluxVisionDynamicConfig.defaultZone;
        
        // Déclencher la comparaison normale
        this.performComparison();
    }

    async performComparison() {
        if (this.isComparing) {
            return;
        }

        
        try {
            this.isComparing = true;
            this.showComparisonLoading();
            
            // Récupérer les valeurs des filtres A et B
            const periodA = {
                annee: document.getElementById('comp-year-a')?.value || fluxVisionDynamicConfig.defaultYear.toString(),
                periode: document.getElementById('comp-period-a')?.value || fluxVisionDynamicConfig.defaultPeriod,
                zone: document.getElementById('comp-zone-a')?.value || fluxVisionDynamicConfig.defaultZone
            };
            
            const periodB = {
                annee: document.getElementById('comp-year-b')?.value || fluxVisionDynamicConfig.previousYear.toString(),
                periode: document.getElementById('comp-period-b')?.value || fluxVisionDynamicConfig.defaultPeriod,
                zone: document.getElementById('comp-zone-b')?.value || fluxVisionDynamicConfig.defaultZone
            };
            
            // Appel API pour la comparaison détaillée
            const url = `api/comparison_detailed.php?` + 
                `annee_a=${periodA.annee}&periode_a=${periodA.periode}&zone_a=${periodA.zone}&` +
                `annee_b=${periodB.annee}&periode_b=${periodB.periode}&zone_b=${periodB.zone}`;
                
            
            const response = await fetch(url);
            
            // Vérifier si la réponse est OK
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status} ${response.statusText}`);
            }
            
            // Lire le texte d'abord pour déboguer
            const responseText = await response.text();
            
            // Essayer de parser en JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (jsonError) {
                console.error('Erreur parsing JSON:', jsonError);
                console.error('Response content:', responseText);
                throw new Error('La réponse de l\'API n\'est pas du JSON valide');
            }
            
            
            if (data.error) {
                throw new Error(data.message || 'Erreur lors de la comparaison');
            }
            
            this.currentComparisonData = data;
            this.displayComparisonResults(data);
            
        } catch (error) {
            console.error('Erreur lors de la comparaison:', error);
            this.showComparisonError(error.message);
            

        } finally {
            this.isComparing = false;
        }
    }

    displayComparisonResults(data) {
        
        const resultsDiv = document.getElementById('comparison-results');
        if (!resultsDiv) {
            console.error('comparison-results div not found');
            return;
        }
        
        // Construire le HTML des résultats
        const resultsHTML = this.buildComparisonResultsHTML(data);
        resultsDiv.innerHTML = resultsHTML;
        resultsDiv.style.display = 'block';
        
        // Animer l'apparition
        resultsDiv.classList.add('fade-in-up');
        
        // Charger les départements d'origine
        this.loadDepartementsComparison(data.periode_a, data.periode_b);
    }

    buildComparisonResultsHTML(data) {
        const { periode_a, periode_b, comparison_summary } = data;
        
        // Créer le header de comparaison dans un panneau
        let html = `
            <div class="panel fade-in-up">
                <h2 class="panel-title">
                    <i class="fa-solid fa-chart-column"></i> Comparaison de Périodes
                </h2>
                <div class="comparison-header">
                    <div class="comparison-period">
                        <div class="period-badge period-a">A</div>
                        <h3>${periode_a.periode} ${periode_a.annee}</h3>
                        <p><i class="fa-solid fa-map-marker-alt"></i> ${periode_a.zone}</p>
                        <p><i class="fa-solid fa-calendar"></i> ${this.formatDate(periode_a.debut)} - ${this.formatDate(periode_a.fin)}</p>
                    </div>
                    <div class="comparison-period">
                        <div class="period-badge period-b">B</div>
                        <h3>${periode_b.periode} ${periode_b.annee}</h3>
                        <p><i class="fa-solid fa-map-marker-alt"></i> ${periode_b.zone}</p>
                        <p><i class="fa-solid fa-calendar"></i> ${this.formatDate(periode_b.debut)} - ${this.formatDate(periode_b.fin)}</p>
                    </div>
                </div>
            </div>
        `;
        
        // Ajouter les KPIs comparatifs dans un panneau
        html += `
            <div class="panel fade-in-up">
                <h2 class="panel-title">
                    <i class="fa-solid fa-chart-column"></i> Indicateurs Comparatifs
                </h2>
                <div class="comparison-kpis">
        `;
        
        if (comparison_summary.nuitees && comparison_summary.nuitees.totales) {
            html += this.buildComparisonKPI('Nuitées Totales', comparison_summary.nuitees.totales, 'nuitées', 'fa-moon');
        }
        
        if (comparison_summary.nuitees && comparison_summary.nuitees.francaises) {
            html += this.buildComparisonKPI('Nuitées Françaises', comparison_summary.nuitees.francaises, 'nuitées', 'fa-flag');
        }
        
        if (comparison_summary.nuitees && comparison_summary.nuitees.internationales) {
            html += this.buildComparisonKPI('Nuitées Internationales', comparison_summary.nuitees.internationales, 'nuitées', 'fa-globe');
        }
        
        if (comparison_summary.presences && comparison_summary.presences.totales) {
            html += this.buildComparisonKPI('Excursionnistes', comparison_summary.presences.totales, 'nuitées', 'fa-person-hiking');
        }
        
        html += `
                </div>
            </div>
        `;
        
        // Section départements d'origine
        html += `
            <div class="panel fade-in-up">
                <h2 class="panel-title">
                    <i class="fa-solid fa-map-location-dot"></i> Départements d'Origine
                </h2>
                <div id="comparison-departements-section" class="departments-comparison-container">
                    <div class="departments-loading">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        <span>Chargement des départements d'origine...</span>
                    </div>
                </div>
            </div>
        `;
        
        return html;
    }

    buildComparisonKPI(title, data, unit, icon) {
        const evolution = data.evolution;
        const evolutionClass = evolution >= 0 ? 'positive' : 'negative';
        const evolutionIcon = evolution >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
        const evolutionSign = evolution >= 0 ? '+' : '';
        
        // Calculer l'écart absolu
        const difference = Math.abs(data.periode_a - data.periode_b);
        const formattedDifference = this.formatNumber(difference);
        
        // Qualifier l'ampleur de l'écart basé sur le pourcentage
        let amplitudeText = '';
        const absEvolution = Math.abs(evolution);
        if (absEvolution < 5) {
            amplitudeText = 'Écart faible';
        } else if (absEvolution < 20) {
            amplitudeText = 'Écart modéré';
        } else if (absEvolution < 50) {
            amplitudeText = 'Écart important';
        } else {
            amplitudeText = 'Écart majeur';
        }
        
        return `
            <div class="comparison-kpi-card">
                <div class="kpi-header">
                    <div class="kpi-title">
                        <h4>${title}</h4>
                    </div>
                    <div class="kpi-icon">
                        <i class="fa-solid ${icon}"></i>
                    </div>
                </div>
                
                <div class="kpi-main-value">
                    <div class="kpi-value-display">${this.formatNumber(data.periode_a)}</div>
                    <div class="kpi-unit-label">${unit}</div>
                </div>
                
                <div class="kpi-comparison-section">
                    <div class="comparison-periods">
                        <div class="period-info">
                            <div class="period-label">
                                Période A
                            </div>
                            <div class="period-value">${this.formatNumber(data.periode_a)}</div>
                        </div>
                        <div class="period-info">
                            <div class="period-label">
                                Période B
                            </div>
                            <div class="period-value">${this.formatNumber(data.periode_b)}</div>
                        </div>
                    </div>
                    
                    <div class="evolution-display">
                        <div class="evolution-badge ${evolutionClass}">
                            <i class="fa-solid ${evolutionIcon}"></i>
                            <span>${evolutionSign}${Math.abs(evolution).toFixed(1)}%</span>
                        </div>
                        <span class="evolution-text">${amplitudeText} : ${formattedDifference} ${unit}</span>
                    </div>
                </div>
            </div>
        `;
    }



    resetComparison() {
        
        // Remettre les valeurs par défaut
        const yearA = document.getElementById('comp-year-a');
        const yearB = document.getElementById('comp-year-b');
        const periodA = document.getElementById('comp-period-a');
        const periodB = document.getElementById('comp-period-b');
        const zoneA = document.getElementById('comp-zone-a');
        const zoneB = document.getElementById('comp-zone-b');
        
        if (yearA) yearA.value = fluxVisionDynamicConfig.defaultYear.toString();
        if (yearB) yearB.value = fluxVisionDynamicConfig.previousYear.toString();
        if (periodA) periodA.value = fluxVisionDynamicConfig.defaultPeriod;
        if (periodB) periodB.value = fluxVisionDynamicConfig.defaultPeriod;
        if (zoneA) zoneA.value = fluxVisionDynamicConfig.defaultZone;
        if (zoneB) zoneB.value = fluxVisionDynamicConfig.defaultZone;
        
        // Cacher les résultats
        const resultsDiv = document.getElementById('comparison-results');
        if (resultsDiv) {
            resultsDiv.innerHTML = `
                <div class="loading">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    Préparation de la comparaison...
                </div>
            `;
        }
        

        
        this.currentComparisonData = null;
        
    }

    showComparisonLoading() {
        const resultsDiv = document.getElementById('comparison-results');
        if (resultsDiv) {
            resultsDiv.innerHTML = `
                <div class="loading">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <p class="loading-text">Comparaison en cours</p>
                    <p class="loading-subtitle">Analyse des données en temps réel</p>
                </div>
            `;
            resultsDiv.style.display = 'block';
        }
    }

    showComparisonError(message) {
        const resultsDiv = document.getElementById('comparison-results');
        if (resultsDiv) {
            resultsDiv.innerHTML = `
                <div class="error">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <p class="loading-text">Erreur de comparaison</p>
                    <p class="loading-subtitle">${message}</p>
                </div>
            `;
            resultsDiv.style.display = 'block';
        }
    }

    // Utilitaires
    formatNumber(num) {
        return (typeof num === 'number' && !isNaN(num)) ? num.toLocaleString('fr-FR') : '-';
    }

    formatDate(dateStr) {
        if (!dateStr) return '--/--/----';
        const date = new Date(dateStr);
        return date.toLocaleDateString('fr-FR');
    }

    async loadDepartementsComparison(periode_a, periode_b) {
        try {
            
            const container = document.getElementById('comparison-departements-section');
            if (!container) {
                console.error('Container comparison-departements-section not found!');
                return;
            }

            // Vérifier si au moins une période est "année"
            const isAnnualPeriod = periode_a.periode === 'annee' || periode_b.periode === 'annee';
            
            // Message de chargement adapté
            const loadingMessage = isAnnualPeriod 
                ? 'Chargement des départements d\'origine...<br><small class="text-muted">Les données annuelles prennent plus de temps à calculer</small>'
                : 'Chargement des départements d\'origine...';
            
            container.innerHTML = `
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Chargement...</span>
                    </div>
                    <p class="mt-3 mb-0">${loadingMessage}</p>
                </div>
            `;

            const params = new URLSearchParams({
                annee_a: periode_a.annee,
                periode_a: periode_a.periode,
                zone_a: periode_a.zone,
                annee_b: periode_b.annee,
                periode_b: periode_b.periode,
                zone_b: periode_b.zone,
                limit: 15
            });



            const response = await fetch(`api/comparison_departements.php?${params}`);
            
            
            if (!response.ok) {
                // Lire le contenu de l'erreur pour débugger
                const errorText = await response.text();
                console.error('Error response body:', errorText);
                throw new Error(`HTTP error! status: ${response.status}\nReponse: ${errorText.substring(0, 500)}`);
            }

            const data = await response.json();
            
            
            if (data.status !== 'success') {
                throw new Error(data.message || 'Erreur lors du chargement des données');
            }

            await this.renderDepartementsComparison(data.data);
            
        } catch (error) {
            console.error('Erreur lors du chargement des départements:', error);
            const container = document.getElementById('comparison-departements-section');
            if (container) {
                container.innerHTML = `
                    <div class="alert alert-danger">
                        <h6>Erreur lors du chargement des départements d'origine</h6>
                        <p class="mb-0">${error.message}</p>
                    </div>
                `;
            }
        }
    }

    renderDepartementsComparison(data) {
        const container = document.getElementById('comparison-departements-section');
        if (!container) return;
        
        const { periode_a, periode_b, departements } = data;
        
        // Séparer les départements par période et ne garder que ceux qui ont des données
        const departementsA = departements
            .filter(dept => dept.periode_a > 0)
            .sort((a, b) => b.periode_a - a.periode_a)
            .slice(0, 15);
            
        const departementsB = departements
            .filter(dept => dept.periode_b > 0)
            .sort((a, b) => b.periode_b - a.periode_b)
            .slice(0, 15);
        
        const html = `
            <div class="departments-face-to-face">
                <div class="chart-header">
                    <div class="period-label period-a-label">
                        <span class="period-title">${periode_a.periode} ${periode_a.annee}</span>
                        <span class="period-subtitle">${periode_a.zone}</span>
                    </div>
                    <div class="departments-title">
                        <h3>Départements d'Origine</h3>
                        <p>Top 15 par zone/période</p>
                    </div>
                    <div class="period-label period-b-label">
                        <span class="period-title">${periode_b.periode} ${periode_b.annee}</span>
                        <span class="period-subtitle">${periode_b.zone}</span>
                    </div>
                </div>
                
                <div class="charts-container">
                    <div class="chart-left">
                        <canvas id="departments-chart-a"></canvas>
                    </div>
                    <div class="chart-right">
                        <canvas id="departments-chart-b"></canvas>
                    </div>
                </div>
            </div>
        `;
        
        container.innerHTML = html;
        
        // Créer les graphiques
        this.createFaceToFaceCharts(departementsA, departementsB, periode_a, periode_b);
    }

    createFaceToFaceCharts(departementsA, departementsB, periode_a, periode_b) {
        const ctxA = document.getElementById('departments-chart-a').getContext('2d');
        const ctxB = document.getElementById('departments-chart-b').getContext('2d');
        
        // Préparer les données pour les départements A en format compatible avec buildChartData
        const dataForChartA = departementsA.map(dept => ({
            nom_departement: dept.nom_departement,
            n_nuitees: dept.periode_a,
            part_pct: dept.part_a,
            nom_region: dept.nom_region,
            nom_nouvelle_region: dept.nom_nouvelle_region
        }));
        
        // Préparer les données pour les départements B en format compatible avec buildChartData
        const dataForChartB = departementsB.map(dept => ({
            nom_departement: dept.nom_departement,
            n_nuitees: dept.periode_b,
            part_pct: dept.part_b,
            nom_region: dept.nom_region,
            nom_nouvelle_region: dept.nom_nouvelle_region
        }));
        
        // Utiliser buildChartData pour construire les données comme dans l'analyse standard
        const chartDataA = this.buildChartData(dataForChartA, 'nom_departement', 'n_nuitees', 'Nuitées', 15);
        const chartDataB = this.buildChartData(dataForChartB, 'nom_departement', 'n_nuitees', 'Nuitées', 15);
        
        if (!chartDataA || !chartDataB) {
            console.error('Impossible de construire les données des graphiques départements');
            return;
        }
        
        // Configuration commune (exactement comme dans renderDepartementsChart)
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y', // Barres horizontales comme dans l'original
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(15, 18, 26, 0.9)',
                    titleColor: '#a35fff',
                    titleFont: { weight: 'bold', size: 14 },
                    bodyColor: '#f0f5ff',
                    borderColor: 'rgba(0, 242, 234, 0.5)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        title: (ctx) => ctx[0]?.label ?? '',
                        label: (ctx) => {
                            const val = ctx.parsed.x ?? ctx.parsed.y ?? ctx.parsed;
                            const rawItem = ctx.dataset.rawData?.[ctx.dataIndex];
                            if (!rawItem) return `Nuitées: ${this.formatNumber(val)}`;

                            const lines = [`Nuitées: ${this.formatNumber(rawItem.n_nuitees ?? val)}`];
                            if (rawItem.part_pct !== undefined && rawItem.part_pct !== null) {
                                lines.push(`Part: ${this.formatPercentage(rawItem.part_pct)}`);
                            }
                            return lines;
                        }
                    }
                }
            },
            animation: {
                duration: 1200,
                easing: 'easeOutQuart',
                delay: (context) => context.dataIndex * 100
            },
            scales: {
                x: { 
                    beginAtZero: true, 
                    grid: { color: 'rgba(160, 168, 184, 0.1)' }, 
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
                        maxTicksLimit: 15,
                        color: '#f0f5ff',
                        font: { size: 11 }
                    }
                }
            }
        };
        
        // Créer le graphique A (gauche) avec les couleurs du thème standard
        new Chart(ctxA, {
            type: 'bar',
            data: chartDataA,
            options: commonOptions
        });
        
        // Créer le graphique B (droite) avec les couleurs du thème standard + axes inversés
        new Chart(ctxB, {
            type: 'bar',
            data: chartDataB,
            options: {
                ...commonOptions,
                scales: {
                    ...commonOptions.scales,
                    x: {
                        ...commonOptions.scales.x,
                        position: 'top',
                        reverse: true
                    },
                    y: {
                        ...commonOptions.scales.y,
                        position: 'right'
                    }
                }
            }
        });
        
    }

    // Copie de buildChartData de TDBComparaisonAPI pour la comparaison avancée
    buildChartData(items, labelKey, valueKey, datasetLabel = 'Valeurs', limit = 5) {
        if (!Array.isArray(items) || items.length === 0) {
            return null;
        }
        const CHART_BORDER_COLORS = CHART_COLORS.map(c => c.replace('0.8', '1'));
        
        // Filtrer, trier et limiter les données
        const filteredData = items
            .filter(item => {
                return item && typeof item === 'object' && 
                       item[valueKey] != null && !isNaN(Number(item[valueKey]));
            })
            .sort((a, b) => (Number(b[valueKey]) || 0) - (Number(a[valueKey]) || 0))
            .slice(0, limit);
            
        if (filteredData.length === 0) {
            return null;
        }
        
        return {
            labels: filteredData.map(i => i[labelKey] || 'Inconnu'),
            datasets: [{
                label: datasetLabel,
                data: filteredData.map(i => Number(i[valueKey])),
                backgroundColor: CHART_COLORS.slice(0, filteredData.length),
                borderColor: CHART_BORDER_COLORS.slice(0, filteredData.length),
                borderWidth: 1, 
                borderRadius: 3, 
                barThickness: 'flex', 
                maxBarThickness: 25,
                rawData: filteredData,
                primaryValueKey: valueKey
            }]
        };
    }

    // Copie de formatPercentage de TDBComparaisonAPI pour la comparaison avancée
    formatPercentage(num, decimals = 1) {
        return (typeof num === 'number' && !isNaN(num))
            ? num.toFixed(decimals).replace('.', ',') + '%'
            : '-';
    }
}

// =============================================================================
// SECTION 4: GESTION DES ONGLETS ET MODES
// =============================================================================

function setupModeToggle() {
    
    const modeRadios = document.querySelectorAll('input[name="view-mode"]');
    const normalFilters = document.getElementById('normal-filters');
    const comparisonFilters = document.getElementById('comparison-filters');
    const comparisonResultsSection = document.getElementById('comparison-results-section');
    const tabsContainer = document.querySelector('.tabs-container');
    const tabContent = document.querySelector('.tab-content');
    
    modeRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            const selectedMode = radio.value;
            
            if (selectedMode === 'normal') {
                // Mode Analyse Standard
                if (normalFilters) normalFilters.style.display = 'block';
                if (comparisonFilters) comparisonFilters.style.display = 'none';
                if (comparisonResultsSection) comparisonResultsSection.style.display = 'none';
                if (tabsContainer) tabsContainer.style.display = 'block';
                if (tabContent) tabContent.style.display = 'block';
                
            } else if (selectedMode === 'comparison') {
                // Mode Comparaison Avancée
                if (normalFilters) normalFilters.style.display = 'none';
                if (comparisonFilters) comparisonFilters.style.display = 'block';
                if (comparisonResultsSection) comparisonResultsSection.style.display = 'block';
                if (tabsContainer) tabsContainer.style.display = 'none';
                if (tabContent) tabContent.style.display = 'none';
                
                
                // Déclencher automatiquement une comparaison avec les valeurs par défaut
                setTimeout(() => {
                    if (window.advancedComparison) {
                        window.advancedComparison.performDefaultComparison();
                    }
                }, 1000); // Augmenté à 1000ms pour laisser le temps aux filtres de se charger
            }
        });
    });
}

function setupTabs() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabPanes = document.querySelectorAll('.tab-pane');

    if (tabButtons.length === 0 || tabPanes.length === 0) {
        return;
    }

    // Fonction pour changer d'onglet
    function switchTabs(activeButton, activePane, targetButton, targetPane) {
        // Désactiver l'ancien
        if (activeButton) activeButton.classList.remove('active');
        if (activePane) {
            activePane.classList.remove('active');
            activePane.style.display = 'none';
        }

        // Activer le nouveau
        targetButton.classList.add('active');
        targetPane.classList.add('active');
        targetPane.style.display = 'block';

    }
    
    tabButtons.forEach(button => {
        button.addEventListener('click', (event) => {
            const targetButton = event.currentTarget;
            const currentActiveButton = document.querySelector('.tab-button.active');

            if (targetButton === currentActiveButton) return; // Déjà actif

            const tabId = targetButton.getAttribute('data-tab');
            const targetPane = document.getElementById(`${tabId}-tab`);
            const currentActivePane = document.querySelector('.tab-pane.active');

            if (targetPane) {
                switchTabs(currentActiveButton, currentActivePane, targetButton, targetPane);
            } else {
                console.warn(`Tab pane with ID '${tabId}-tab' not found.`);
            }
        });
    });

    // Activer le premier onglet par défaut si aucun n'est actif
    if (!document.querySelector('.tab-button.active') && tabButtons.length > 0) {
        const firstButton = tabButtons[0];
        const firstTabId = firstButton.getAttribute('data-tab');
        const firstPane = document.getElementById(`${firstTabId}-tab`);
        if (firstPane) {
            switchTabs(null, null, firstButton, firstPane);
        }
    }
    
}

// =============================================================================
// CLASSE POUR DÉTECTER ET LISTER LES GRAPHIQUES DYNAMIQUEMENT
// =============================================================================

class ChartsDetector {
    constructor() {
        this.chartsList = [];
        this.addedButtons = new Set(); // Pour éviter de dupliquer les boutons
    }

    init() {
        // Détecter les graphiques après un délai pour laisser le temps aux éléments de se charger
        setTimeout(() => {
            this.detectChartsAndAddButtons();
        }, 2000);

        // Re-détecter périodiquement pour capturer les graphiques chargés dynamiquement
        setInterval(() => {
            this.detectChartsAndAddButtons();
        }, 5000);
    }

    detectChartsAndAddButtons() {
        const hasChanges = this.detectCharts();
        if (hasChanges) {
            this.addDownloadButtons();
        }
    }

    addDownloadButtons() {
        this.chartsList.forEach(chart => {
            const element = document.querySelector(chart.selector);
            if (!element) return;

            // Éviter de dupliquer les boutons
            if (this.addedButtons.has(chart.selector)) return;

            // Trouver le conteneur parent approprié pour ajouter le bouton
            let container = null;
            
            if (chart.type === 'canvas') {
                // Pour les graphiques canvas, chercher le conteneur chart-card
                container = element.closest('.chart-card');
            } else if (chart.type === 'indicators') {
                // Pour les indicateurs, chercher le panel parent
                container = element.closest('.panel');
            }

            if (!container) return;

            // Créer le bouton de téléchargement
            const downloadBtn = this.createDownloadButton(chart);
            
            // Ajouter le bouton dans le header du conteneur
            const header = container.querySelector('h2, h3');
            if (header) {
                // Créer un wrapper pour le titre et le bouton
                if (!header.querySelector('.chart-header-actions')) {
                    const actionsWrapper = document.createElement('div');
                    actionsWrapper.className = 'chart-header-actions';
                    actionsWrapper.style.cssText = `
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        width: 100%;
                    `;
                    
                    // Déplacer le contenu du header dans le wrapper
                    const titleContent = header.innerHTML;
                    header.innerHTML = '';
                    
                    const titleDiv = document.createElement('div');
                    titleDiv.innerHTML = titleContent;
                    
                    actionsWrapper.appendChild(titleDiv);
                    actionsWrapper.appendChild(downloadBtn);
                    header.appendChild(actionsWrapper);
                }
            }

            this.addedButtons.add(chart.selector);
        });
    }

    createDownloadButton(chart) {
        const button = document.createElement('button');
        button.className = 'chart-download-btn';
        button.title = `Télécharger: ${chart.name}`;
        button.innerHTML = '<i class="fa-solid fa-download"></i>';
        
        // Ajouter les styles inline pour que le bouton soit visible immédiatement
        button.style.cssText = `
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 6px;
            color: white;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-left: 8px;
        `;

        // Effet hover
        button.addEventListener('mouseenter', () => {
            button.style.transform = 'scale(1.1)';
            button.style.boxShadow = '0 4px 8px rgba(0,0,0,0.2)';
        });

        button.addEventListener('mouseleave', () => {
            button.style.transform = 'scale(1)';
            button.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
        });

        // Événement de téléchargement
        button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.downloadChart(chart);
        });

        return button;
    }

    detectCharts() {
        const newChartsList = [];

        // Définir les sélecteurs et noms des graphiques à détecter
        const chartSelectors = [
            // Indicateurs clés
            { selector: '#key-figures-grid', name: '📈 Indicateurs Clés Touristes', category: 'Touristes', type: 'indicators' },
            { selector: '#exc-key-figures-grid', name: '📈 Indicateurs Clés Excursionnistes', category: 'Excursionnistes', type: 'indicators' },
            
            // Graphiques Touristes
            { selector: '#chart-departements', name: '🗺️ Top 15 Départements (Nuitées)', category: 'Touristes', type: 'canvas' },
            { selector: '#chart-regions', name: '🌍 Top 5 Régions (Nuitées)', category: 'Touristes', type: 'canvas' },
            { selector: '#chart-pays', name: '🏳️ Top 5 Pays (Nuitées)', category: 'Touristes', type: 'canvas' },
            { selector: '#chart-mobility-destinations', name: '🚗 Top 10 Destinations (Visiteurs)', category: 'Touristes', type: 'canvas' },
            { selector: '#chart-age', name: '👥 Répartition par Âge (Touristes)', category: 'Touristes', type: 'canvas' },
            { selector: '#chart-csp', name: '💼 Répartition par CSP (Touristes)', category: 'Touristes', type: 'canvas' },
            
            // Graphiques Excursionnistes
            { selector: '#exc-chart-departements', name: '🗺️ Top 15 Départements (Présences)', category: 'Excursionnistes', type: 'canvas' },
            { selector: '#exc-chart-regions', name: '🌍 Top 5 Régions (Présences)', category: 'Excursionnistes', type: 'canvas' },
            { selector: '#exc-chart-pays', name: '🏳️ Top 5 Pays (Présences)', category: 'Excursionnistes', type: 'canvas' },
            { selector: '#exc-chart-age', name: '👥 Répartition par Âge (Excursionnistes)', category: 'Excursionnistes', type: 'canvas' },
            { selector: '#exc-chart-csp', name: '💼 Répartition par CSP (Excursionnistes)', category: 'Excursionnistes', type: 'canvas' }
        ];

        chartSelectors.forEach(chart => {
            const element = document.querySelector(chart.selector);
            if (element) {
                // Vérifier si le graphique est réellement chargé
                const isLoaded = this.isChartLoaded(element, chart.selector, chart.type);
                if (isLoaded) {
                    newChartsList.push({
                        name: chart.name,
                        category: chart.category,
                        selector: chart.selector,
                        type: chart.type,
                        status: 'Chargé'
                    });
                }
            }
        });

        // Mettre à jour la liste seulement si elle a changé
        if (JSON.stringify(newChartsList) !== JSON.stringify(this.chartsList)) {
            this.chartsList = newChartsList;
            return true; // Indique qu'il y a eu des changements
        }
        return false;
    }

    isChartLoaded(element, selector, type) {
        if (!element) return false;
        
        // Pour les canvas (graphiques Chart.js)
        if (type === 'canvas' && element.tagName === 'CANVAS') {
            // Vérifier s'il y a une instance Chart.js attachée
            const chartInstance = Chart.getChart(element);
            return chartInstance !== undefined && chartInstance.data && chartInstance.data.datasets && chartInstance.data.datasets.length > 0;
        }
        
        // Pour les grilles d'indicateurs
        if (type === 'indicators') {
            // Vérifier que la grille ne contient pas seulement "Chargement..."
            const loadingDiv = element.querySelector('.loading');
            if (loadingDiv) return false;
            
            // Vérifier qu'il y a des cartes d'indicateurs
            const cards = element.querySelectorAll('.key-figure-card');
            return cards.length > 0;
        }
        
        // Par défaut, considérer comme chargé si l'élément existe et n'est pas vide
        return element && element.children.length > 0 && !element.querySelector('.loading');
    }



    getChartsCount() {
        return this.chartsList.length;
    }

    getChartsByCategory() {
        const categories = {};
        this.chartsList.forEach(chart => {
            if (!categories[chart.category]) {
                categories[chart.category] = [];
            }
            categories[chart.category].push(chart);
        });
        return categories;
    }

    downloadChart(chart) {
        if (!chart) {
            console.warn('Graphique non défini');
            return;
        }

        try {
            // Pour les graphiques Canvas (Chart.js)
            if (chart.type === 'canvas') {
                this.downloadCanvasChart(chart.selector, chart.name);
            } 
            // Pour les grilles d'indicateurs
            else if (chart.type === 'indicators') {
                this.downloadIndicatorsGrid(chart.selector, chart.name);
            }
            
            // Animation de succès sur le bouton spécifique
            this.showDownloadSuccess(chart.selector);
            
        } catch (error) {
            console.error('Erreur lors du téléchargement:', error);
            this.showDownloadError(chart.selector);
        }
    }

    downloadCanvasChart(selector, chartName) {
        const canvas = document.querySelector(selector);
        if (!canvas || canvas.tagName !== 'CANVAS') {
            throw new Error('Canvas non trouvé');
        }

        // Créer un lien de téléchargement
        const link = document.createElement('a');
        link.download = `${this.sanitizeFilename(chartName)}_${new Date().toISOString().split('T')[0]}.png`;
        link.href = canvas.toDataURL('image/png', 1.0);
        
        // Déclencher le téléchargement
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    downloadIndicatorsGrid(selector, chartName) {
        const gridElement = document.querySelector(selector);
        if (!gridElement) {
            throw new Error('Grille d\'indicateurs non trouvée');
        }
        // Utiliser directement le fallback Canvas 2D pour une meilleure qualité
        this.createIndicatorsImage(gridElement, chartName);
    }

    createIndicatorsImage(gridElement, chartName) {        
        // Créer un canvas manuellement avec les données des indicateurs
        const cards = gridElement.querySelectorAll('.key-figure-card');
        if (cards.length === 0) {
            console.warn('⚠️ Aucune carte d\'indicateur trouvée');
            this.copyIndicatorsToClipboard(gridElement);
            return;
        }

        // Créer un canvas pour dessiner les indicateurs
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        // Dimensions du canvas (plus grandes pour une meilleure qualité)
        const cardWidth = 400;
        const cardHeight = 160;
        const padding = 30;
        const cols = Math.min(2, cards.length); // Maximum 2 colonnes pour plus de lisibilité
        const rows = Math.ceil(cards.length / cols);
        
        canvas.width = (cardWidth + padding) * cols + padding;
        canvas.height = (cardHeight + padding) * rows + padding + 80; // +80 pour le titre
        
        // Background avec gradient
        const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
        gradient.addColorStop(0, '#1a1f2c');
        gradient.addColorStop(1, '#0f121a');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        // Titre avec style amélioré
        ctx.fillStyle = '#f0f5ff';
        ctx.font = 'bold 32px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('Indicateurs Clés', canvas.width / 2, 50);
        
        // Sous-titre
        ctx.fillStyle = '#a0a8b8';
        ctx.font = '16px Arial';
        ctx.fillText('Tableau de Bord FluxVision', canvas.width / 2, 75);
        
        // Dessiner chaque carte
        cards.forEach((card, index) => {
            const col = index % cols;
            const row = Math.floor(index / cols);
            const x = padding + col * (cardWidth + padding);
            const y = 100 + padding + row * (cardHeight + padding);
            
            // Background de la carte avec gradient
            const cardGradient = ctx.createLinearGradient(x, y, x, y + cardHeight);
            cardGradient.addColorStop(0, '#2a2f3f');
            cardGradient.addColorStop(1, '#1e2332');
            ctx.fillStyle = cardGradient;
            ctx.fillRect(x, y, cardWidth, cardHeight);
            
            // Border avec effet glow
            ctx.strokeStyle = '#00f2ea';
            ctx.lineWidth = 2;
            ctx.shadowColor = '#00f2ea';
            ctx.shadowBlur = 5;
            ctx.strokeRect(x, y, cardWidth, cardHeight);
            ctx.shadowBlur = 0; // Reset shadow
            
            // Extraire les données de la carte
            const title = card.querySelector('.indicator-title')?.textContent?.trim() || 'Indicateur';
            const value = card.querySelector('.key-figure-value')?.textContent?.trim() || 'N/A';
            const unit = card.querySelector('.unit')?.textContent?.trim() || '';
            const prevYear = card.querySelector('.value-prev-year')?.textContent?.trim() || '';
            const comparison = card.querySelector('.comparison')?.textContent?.trim() || '';
            
            
            
            // Icône (simulée avec un cercle coloré)
            ctx.fillStyle = '#00f2ea';
            ctx.beginPath();
            ctx.arc(x + 25, y + 25, 8, 0, 2 * Math.PI);
            ctx.fill();
            
            // Titre de l'indicateur
            ctx.fillStyle = '#f0f5ff';
            ctx.font = 'bold 16px Arial';
            ctx.textAlign = 'left';
            ctx.fillText(title, x + 45, y + 30);
            
            // Valeur principale (plus grande et centrée)
            ctx.fillStyle = '#00f2ea';
            ctx.font = 'bold 36px Arial';
            ctx.textAlign = 'center';
            ctx.fillText(value, x + cardWidth / 2, y + 80);
            
            // Unité
            if (unit) {
                ctx.fillStyle = '#a0a8b8';
                ctx.font = 'bold 14px Arial';
                ctx.textAlign = 'center';
                ctx.fillText(unit, x + cardWidth / 2, y + 105);
            }
            
            // Année précédente
            if (prevYear) {
                ctx.fillStyle = '#a0a8b8';
                ctx.font = '12px Arial';
                ctx.textAlign = 'left';
                ctx.fillText(prevYear, x + 15, y + 130);
            }
            
            // Comparaison/évolution
            if (comparison) {
                ctx.fillStyle = comparison.includes('+') ? '#4ade80' : comparison.includes('-') ? '#f87171' : '#a0a8b8';
                ctx.font = 'bold 12px Arial';
                ctx.textAlign = 'right';
                ctx.fillText(comparison, x + cardWidth - 15, y + 130);
            }
        });
        
        // Télécharger l'image
        const link = document.createElement('a');
        link.download = `${this.sanitizeFilename(chartName)}_${new Date().toISOString().split('T')[0]}.png`;
        link.href = canvas.toDataURL('image/png', 1.0);
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        
    }

    copyIndicatorsToClipboard(gridElement) {
        const cards = gridElement.querySelectorAll('.key-figure-card');
        let text = 'Indicateurs Clés:\n\n';
        
        cards.forEach(card => {
            const title = card.querySelector('.indicator-title')?.textContent?.trim() || 'Indicateur';
            const value = card.querySelector('.key-figure-value')?.textContent?.trim() || 'N/A';
            const unit = card.querySelector('.unit')?.textContent?.trim() || '';
            text += `${title}: ${value} ${unit}\n`;
        });

        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                
            });
        }
    }

    navigateToSelectedChart() {
        const selectedSelector = this.chartsListElement?.value;
        if (!selectedSelector) {
            console.warn('Aucun graphique sélectionné');
            return;
        }

        const element = document.querySelector(selectedSelector);
        if (!element) {
            console.warn('Élément non trouvé pour la navigation');
            return;
        }

        // Faire défiler vers l'élément avec une animation fluide
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
            inline: 'nearest'
        });

        // Ajouter un effet de surbrillance temporaire
        this.highlightElement(element);
    }

    highlightElement(element) {
        // Créer un overlay de surbrillance
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 242, 234, 0.2);
            border: 2px solid rgba(0, 242, 234, 0.8);
            border-radius: 12px;
            pointer-events: none;
            z-index: 1000;
            animation: highlight-pulse 2s ease-in-out;
        `;

        // Ajouter les keyframes CSS si elles n'existent pas
        if (!document.querySelector('#highlight-keyframes')) {
            const style = document.createElement('style');
            style.id = 'highlight-keyframes';
            style.textContent = `
                @keyframes highlight-pulse {
                    0% { opacity: 0; transform: scale(1.05); }
                    50% { opacity: 1; transform: scale(1); }
                    100% { opacity: 0; transform: scale(1); }
                }
            `;
            document.head.appendChild(style);
        }

        // Positionner l'overlay
        const rect = element.getBoundingClientRect();
        const parent = element.offsetParent || document.body;
        const parentRect = parent.getBoundingClientRect();
        
        overlay.style.position = 'absolute';
        overlay.style.top = (rect.top - parentRect.top + parent.scrollTop) + 'px';
        overlay.style.left = (rect.left - parentRect.left + parent.scrollLeft) + 'px';
        overlay.style.width = rect.width + 'px';
        overlay.style.height = rect.height + 'px';

        parent.appendChild(overlay);

        // Supprimer l'overlay après l'animation
        setTimeout(() => {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        }, 2000);
    }

    showDownloadSuccess(selector) {
        const container = document.querySelector(selector)?.closest('.chart-card, .panel');
        if (container) {
            const downloadBtn = container.querySelector('.chart-download-btn');
            if (downloadBtn) {
                downloadBtn.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
                downloadBtn.innerHTML = '<i class="fa-solid fa-check"></i>';
                setTimeout(() => {
                    downloadBtn.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                    downloadBtn.innerHTML = '<i class="fa-solid fa-download"></i>';
                }, 1500);
            }
        }
    }

    showDownloadError(selector) {
        const container = document.querySelector(selector)?.closest('.chart-card, .panel');
        if (container) {
            const downloadBtn = container.querySelector('.chart-download-btn');
            if (downloadBtn) {
                downloadBtn.style.background = 'linear-gradient(135deg, #ff4444, #cc0000)';
                downloadBtn.innerHTML = '<i class="fa-solid fa-times"></i>';
                setTimeout(() => {
                    downloadBtn.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                    downloadBtn.innerHTML = '<i class="fa-solid fa-download"></i>';
                }, 1500);
            }
        }
    }

    sanitizeFilename(filename) {
        return filename
            .replace(/[^\w\s-]/g, '') // Supprimer les caractères spéciaux
            .replace(/\s+/g, '_')     // Remplacer les espaces par des underscores
            .toLowerCase();
    }
}

// =============================================================================
// SECTION 5: INITIALISATION PRINCIPALE
// =============================================================================


// Créer les instances globales
let filtersLoader;
let tdbAPI;
let advancedComparison;
let chartsDetector;

// Initialisation quand le DOM est prêt
function initializeSystem() {
    
    // Configurer le toggle de mode en premier
    setupModeToggle();
    
    // Configurer les onglets
    setupTabs();
    
    // Sauvegarder la fonction formatNumber globale si elle existe
    if (typeof window.formatNumber === 'function') {
        window.originalFormatNumber = window.formatNumber;
    }
    
    // Initialiser le chargeur de filtres
    filtersLoader = new FiltersLoader();
    filtersLoader.init();
    
    // Initialiser la comparaison avancée
    advancedComparison = new AdvancedComparison();
    advancedComparison.init();
    
    // Initialiser le détecteur de graphiques
    chartsDetector = new ChartsDetector();
    chartsDetector.init();
    
    // Rendre accessible globalement pour le mode toggle
    window.advancedComparison = advancedComparison;
    
    // Attendre un peu puis initialiser l'API
    setTimeout(() => {
        // Initialiser l'API avec protection contre les conflits
        if (!window.TDBComparaisonAPI_Namespace.initialized) {
            window.TDBComparaisonAPI_Namespace.tdbAPI = new TDBComparaisonAPI();
            window.TDBComparaisonAPI_Namespace.initialized = true;
            // Exposer un alias global rétro-compatible
            window.tdbComparaisonAPI = window.TDBComparaisonAPI_Namespace.tdbAPI;
            
            // IMPORTANT: Appeler manuellement init() puisque le DOM est déjà chargé
            window.TDBComparaisonAPI_Namespace.tdbAPI.init();
        } else {
            // Toujours rafraîchir l'alias même si déjà initialisé
            window.tdbComparaisonAPI = window.TDBComparaisonAPI_Namespace.tdbAPI;
        }
    }, 100);
    
}

// Lancer l'initialisation
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeSystem);
} else {
    initializeSystem();
}
