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