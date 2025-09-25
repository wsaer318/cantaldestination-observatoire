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
            const response = await fetch(window.getApiUrl('filters/filters_mysql.php'));
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
