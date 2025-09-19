/**
 * Script d'intégration API Bloc A dans le tableau de bord comparaison
 */

// Namespace isolé pour éviter les conflits
window.TDBComparaisonAPI_Namespace = window.TDBComparaisonAPI_Namespace || {};

class TDBComparaisonAPI {
    constructor() {
        this.apiBaseUrl = window.getApiUrl('bloc_a.php');
        this.currentData = null;
        this.init();
    }

    init() {
        
        // Vérifier si le DOM est déjà chargé
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.delayedInit();
            });
        } else {
            this.delayedInit();
        }
    }

    delayedInit() {
        // Attendre que les filtres soient chargés
        setTimeout(() => {
            this.bindFilterEvents();
            this.loadInitialData();
        }, 500);
    }

    bindFilterEvents() {
        
        const yearSelect = document.getElementById('exc-year-select');
        const periodSelect = document.getElementById('exc-period-select');
        const zoneSelect = document.getElementById('exc-zone-select');

        // Retirer tous les anciens événements pour éviter les doublons
        [yearSelect, periodSelect, zoneSelect].forEach(select => {
            if (select) {
                // Cloner l'élément pour supprimer tous les event listeners
                const newSelect = select.cloneNode(true);
                select.parentNode.replaceChild(newSelect, select);
            }
        });

        // Re-récupérer les éléments après clonage
        const newYearSelect = document.getElementById('exc-year-select');
        const newPeriodSelect = document.getElementById('exc-period-select');
        const newZoneSelect = document.getElementById('exc-zone-select');

        // Ajouter nos nouveaux événements
        [newYearSelect, newPeriodSelect, newZoneSelect].forEach(select => {
            if (select) {
                select.addEventListener('change', (event) => {
                    
                    
                    this.loadData();
                });
            }
        });
        
    }

    async loadInitialData() {
        // Charger les données par défaut (2024, hiver, CANTAL)
        await this.loadData('2024', 'hiver', 'CANTAL');
    }

    async loadData(annee = null, periode = null, zone = null) {
        try {
            // Récupérer les valeurs des filtres si non spécifiées
            if (!annee) annee = document.getElementById('exc-year-select')?.value || '2024';
            if (!periode) periode = document.getElementById('exc-period-select')?.value || 'hiver';
            if (!zone) zone = document.getElementById('exc-zone-select')?.value || 'CANTAL';

            // Construire l'URL de l'API
            const url = `${this.apiBaseUrl}?annee=${annee}&periode=${periode}&zone=${zone}`;
            
            this.showLoading();
            
            const response = await fetch(url);
            const data = await response.json();


            if (data.error) {
                throw new Error(data.message);
            }

            this.currentData = data;
            this.updateUI(data);
            this.updateHeader(data);

        } catch (error) {
            console.error('Erreur lors du chargement des données:', error);
            this.showError(error.message);
        }
    }

    updateHeader(data) {
        // Mettre à jour les informations de période dans le header
        const startDate = this.formatDate(data.debut);
        const endDate = this.formatDate(data.fin);
        
        document.getElementById('header-period').textContent = `${data.periode} ${data.annee}`;
        document.getElementById('exc-start-date').textContent = startDate;
        document.getElementById('exc-end-date').textContent = endDate;
        document.getElementById('exc-footer-start-date').textContent = startDate;
        document.getElementById('exc-footer-end-date').textContent = endDate;
        document.getElementById('exc-footer-year').textContent = data.annee;
    }

    updateUI(data) {
        this.updateTouristsTab(data);
        // Réactiver la carte de comparaison pour les "Totaux Actuels"
        this.updateComparisonCard(data);
        // this.updateExcursionistsTab(data); // Garde désactivé pour l'instant
    }

    updateTouristsTab(data) {
        const indicators = data.bloc_a;
        
        // Trouver les indicateurs touristiques
        const totalNuitees = this.findIndicator(indicators, 1);
        const nuiteesF2 = this.findIndicator(indicators, 2);
        const nuiteesIntl = this.findIndicator(indicators, 3);
        const top15Depts = this.findIndicator(indicators, 4);
        const top5Pays = this.findIndicator(indicators, 5);



        // Mettre à jour les KPIs touristes
        const kpiGrid = document.getElementById('key-figures-grid');
        if (kpiGrid) {
            kpiGrid.innerHTML = `
                <div class="key-figure-card">
                    <div class="key-figure-icon"><i class="fa-solid fa-bed"></i></div>
                    <div class="key-figure-content">
                        <div class="key-figure-value">TEST_VALEUR_BRUTE:${totalNuitees?.N || 0}_FIN_TEST</div>
                        <div class="key-figure-label">Nuitées Totales (TEST DÉBOGAGE)</div>
                        <div class="key-figure-subtitle">Valeur brute sans formatage</div>
                    </div>
                </div>
                <!-- AUTRES INDICATEURS DÉSACTIVÉS TEMPORAIREMENT POUR LE DÉBOGAGE -->
                <!-- 
                <div class="key-figure-card">DÉSACTIVÉ</div>
                <div class="key-figure-card">DÉSACTIVÉ</div>
                <div class="key-figure-card">DÉSACTIVÉ</div>
                <div class="key-figure-card">DÉSACTIVÉ</div>
                -->
            `;
        }
    }

    updateExcursionistsTab(data) {
        const indicators = data.bloc_a;
        
        // Trouver les indicateurs excursionnistes
        const picExc = this.findIndicator(indicators, 10);
        const totalExc = this.findIndicator(indicators, 16);

        // Mettre à jour les KPIs excursionnistes
        const excKpiGrid = document.getElementById('exc-key-figures-grid');
        if (excKpiGrid) {
            excKpiGrid.innerHTML = `
                <div class="key-figure-card">
                    <div class="key-figure-icon"><i class="fa-solid fa-person-hiking"></i></div>
                    <div class="key-figure-content">
                        <div class="key-figure-value">${this.formatNumber(totalExc?.N || 0)}</div>
                        <div class="key-figure-label">Total Excursionnistes</div>
                        <div class="key-figure-subtitle">${totalExc?.remarque || ''}</div>
                    </div>
                </div>
                <div class="key-figure-card">
                    <div class="key-figure-icon"><i class="fa-solid fa-chart-line"></i></div>
                    <div class="key-figure-content">
                        <div class="key-figure-value">${this.formatNumber(picExc?.N || 0)}</div>
                        <div class="key-figure-label">Pic Journalier</div>
                        <div class="key-figure-subtitle">${picExc?.date ? this.formatDate(picExc.date) : 'Non disponible'}</div>
                    </div>
                </div>
                <div class="key-figure-card">
                    <div class="key-figure-icon"><i class="fa-solid fa-calendar-day"></i></div>
                    <div class="key-figure-content">
                        <div class="key-figure-value">${this.calculateDailyAverage(totalExc?.N, data.debut, data.fin)}</div>
                        <div class="key-figure-label">Moyenne Journalière</div>
                        <div class="key-figure-subtitle">Présences/jour</div>
                    </div>
                </div>
            `;
        }
    }

    updateComparisonCard(data) {
        // Mettre à jour la carte de comparaison succincte
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

    // Utilitaires
    findIndicator(indicators, numero) {
        return indicators.find(ind => ind.numero === numero);
    }

    formatNumber(num) {
        if (!num || num === 0) return '0';
        
        // Vérifier que c'est bien un nombre
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

    calculatePercentage(value, total) {
        if (!value || !total) return '0';
        return Math.round((value / total) * 100);
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

// Initialiser l'API quand la page est chargée - avec protection contre les conflits
if (!window.TDBComparaisonAPI_Namespace.initialized) {
    window.TDBComparaisonAPI_Namespace.tdbAPI = new TDBComparaisonAPI();
    window.TDBComparaisonAPI_Namespace.initialized = true;
    
    // IMPORTANT: Appeler manuellement init() puisque le DOM est déjà chargé
    window.TDBComparaisonAPI_Namespace.tdbAPI.init();
} 