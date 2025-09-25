/**
 * SmartCalendar - Calendrier de Restitution
 * Génère le calendrier dynamiquement depuis la base de données
 */
class SmartCalendar {
    constructor() {
        this.calendarData = null;
        this.currentTooltip = null;
        
        // Construire l'URL de base plus robuste
        const pathParts = window.location.pathname.split('/').filter(part => part);
        const projectName = pathParts[0] || 'fluxvision_fin';
        this.baseUrl = window.location.origin + '/' + projectName;
        
        window.fvLog('🔧 SmartCalendar baseUrl:', this.baseUrl);
    }
    
    /**
     * Initialise le calendrier
     */
    async init() {
        try {
            await this.loadCalendarData();
            this.renderCalendar();
            this.showCurrentPeriod();
            this.addInteractivity();
        } catch (error) {
            console.error('Erreur initialisation SmartCalendar:', error);
            this.fallbackToStaticCalendar();
        }
    }
    
    /**
     * Charge les données du calendrier (injectées côté serveur ou via API)
     */
    async loadCalendarData() {
        // Priorité 1: Utiliser les données injectées côté serveur (évite les problèmes CSP)
        if (window.CALENDAR_DATA) {
            window.fvLog('📅 Utilisation des données injectées côté serveur');
            
            if (window.CALENDAR_DATA.status === 'success') {
                this.calendarData = window.CALENDAR_DATA;
                window.fvLog('✅ Calendrier intelligent chargé depuis les données serveur');
                return;
            } else {
                console.warn('⚠️ Erreur dans les données serveur:', window.CALENDAR_DATA.message);
            }
        }
        
        // Priorité 2: Fallback vers l'API si les données serveur ne sont pas disponibles
        window.fvLog('🔄 Fallback vers l\'API...');
        const urls = [
            `${this.baseUrl}/api/filters/calendar_periods.php`,
            './api/filters/calendar_periods.php',
            'api/filters/calendar_periods.php'
        ];
        
        let lastError = null;
        
        for (const url of urls) {
            try {
                window.fvLog('🔗 Tentative fetch:', url);
                const response = await fetch(url);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (data.status !== 'success') {
                    throw new Error(data.message || 'Erreur chargement calendrier');
                }
                
                this.calendarData = data;
                window.fvLog('📅 Calendrier intelligent chargé depuis API:', url);
                return; // Succès
                
            } catch (error) {
                console.warn(`⚠️ Échec avec ${url}:`, error.message);
                lastError = error;
                continue; // Essayer l'URL suivante
            }
        }
        
        // Si toutes les tentatives ont échoué
        throw new Error(`Impossible de charger le calendrier. Dernière erreur: ${lastError?.message}`);
    }
    
    /**
     * Génère le HTML du calendrier dynamiquement
     */
    renderCalendar() {
        const container = document.getElementById('interactive-calendar');
        if (!container) return;
        
        const seasons = ['printemps', 'ete', 'automne', 'hiver', 'annee'];
        const seasonIcons = {
            'printemps': 'fas fa-seedling',
            'ete': 'fas fa-sun', 
            'automne': 'fas fa-leaf',
            'hiver': 'fas fa-snowflake',
            'annee': 'fas fa-calendar-alt'
        };
        
        let html = '';
        
        seasons.forEach(season => {
            const period = this.calendarData.calendar[season];
            if (!period) return;
            
            const isCurrentClass = period.is_current ? ' current-period' : '';
            const periodIcon = period.icon || seasonIcons[season];
            
            html += `
                <div class="calendar-period ${season}-period${isCurrentClass}" 
                     data-season="${season}" 
                     data-period-code="${period.code_periode}"
                     data-has-alternatives="${this.calendarData.alternatives[season]?.length > 1}">
                    
                    <div class="period-icon">
                        <i class="${periodIcon}"></i>
                        ${period.is_current ? '<span class="current-badge"><i class="fas fa-star"></i></span>' : ''}
                    </div>
                    
                    <h3>${period.season_display}</h3>
                    
                    <div class="period-info">
                        <p class="period-name">${period.nom_periode}</p>
                        <p class="period-dates">${period.date_debut_fr} - ${period.date_fin_fr}</p>
                        <p class="period-duration">${period.duree_jours} jours</p>
                    </div>
                    
                    <div class="period-actions">
                        <a href="${this.baseUrl}/tdb_comparaison?periode=${period.code_periode}&annee=${this.calendarData.current_year}"
                           class="btn btn--period">
                            <i class="fas fa-chart-bar"></i> Analyser
                        </a>
                        
                        ${this.calendarData.alternatives[season]?.length > 1 ? `
                            <button class="btn btn--secondary btn--small show-alternatives" 
                                    data-season="${season}">
                                <i class="fas fa-list"></i> Autres périodes
                            </button>
                        ` : ''}
                    </div>
                    
                    ${period.is_current ? `
                        <div class="current-period-indicator">
                            <i class="fas fa-clock"></i> Période actuelle
                        </div>
                    ` : ''}
                </div>
            `;
        });
        
        container.innerHTML = html;
    }
    
    /**
     * Affiche les informations sur la période actuelle
     */
    showCurrentPeriod() {
        const currentPeriodInfo = document.getElementById('current-period-info');
        const currentPeriod = this.calendarData.current_period;
        
        if (currentPeriod && currentPeriodInfo) {
            document.getElementById('current-period-name').textContent = currentPeriod.nom_periode;
            document.getElementById('current-period-description').textContent = 
                `Du ${new Date(currentPeriod.date_debut).toLocaleDateString('fr-FR')} au ${new Date(currentPeriod.date_fin).toLocaleDateString('fr-FR')}`;
            
            const link = document.getElementById('current-period-link');
            link.href = `${this.baseUrl}/tdb_comparaison?periode=${currentPeriod.code_periode}&annee=${this.calendarData.current_year}`;
            
            currentPeriodInfo.style.display = 'block';
        }
    }
    
    /**
     * Ajoute l'interactivité (alternatives, tooltips, etc.)
     */
    addInteractivity() {
        // Gestion des boutons "Autres périodes"
        document.querySelectorAll('.show-alternatives').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const season = btn.dataset.season;
                this.showAlternatives(season);
            });
        });
        
        // Tooltips désactivés
        // document.querySelectorAll('.calendar-period').forEach(period => {
        //     period.addEventListener('mouseenter', () => {
        //         this.showTooltip(period);
        //     });
        //     
        //     period.addEventListener('mouseleave', () => {
        //         this.hideTooltip();
        //     });
        // });
    }
    
    /**
     * Affiche les périodes alternatives pour une saison
     */
    showAlternatives(season) {
        const alternatives = this.calendarData.alternatives[season];
        if (!alternatives || alternatives.length <= 1) return;
        
        const modal = this.createAlternativesModal(season, alternatives);
        document.body.appendChild(modal);
        modal.style.display = 'flex';
        
        // Fermeture du modal
        modal.addEventListener('click', (e) => {
            if (e.target === modal || e.target.classList.contains('modal-close')) {
                modal.remove();
            }
        });
    }
    
    /**
     * Crée le modal des périodes alternatives
     */
    createAlternativesModal(season, alternatives) {
        const seasonNames = {
            'printemps': 'Printemps',
            'ete': 'Été',
            'automne': 'Automne', 
            'hiver': 'Hiver'
        };
        
        const modal = document.createElement('div');
        modal.className = 'alternatives-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-list"></i> Périodes ${seasonNames[season]}</h3>
                    <button class="modal-close"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <p>Choisissez la période spécifique à analyser :</p>
                    <div class="alternatives-list">
                        ${alternatives.map(period => {
                            const startDate = new Date(period.date_debut).toLocaleDateString('fr-FR');
                            const endDate = new Date(period.date_fin).toLocaleDateString('fr-FR');
                            
                            return `
                                <div class="alternative-item">
                                    <div class="alternative-info">
                                        <h4>${period.nom_periode}</h4>
                                        <p>${startDate} - ${endDate}</p>
                                    </div>
                                     <a href="${this.baseUrl}/tdb_comparaison?periode=${period.code_periode}&annee=${this.calendarData.current_year}" 
                                       class="btn btn--small">
                                        <i class="fas fa-chart-bar"></i> Analyser
                                    </a>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            </div>
        `;
        
        return modal;
    }
    
    /**
     * Affiche un tooltip pour une période
     */
    showTooltip(periodElement) {
        try {
            const periodCode = periodElement.dataset.periodCode;
            const season = periodElement.dataset.season;
            
            if (!this.calendarData || !this.calendarData.calendar[season]) {
                return;
            }
            
            const periodData = this.calendarData.calendar[season];
            
            // Créer le tooltip
            const tooltip = document.createElement('div');
            tooltip.className = 'calendar-tooltip';
            tooltip.innerHTML = `
                <strong>${periodData.nom_periode}</strong><br>
                ${periodData.date_debut_fr} - ${periodData.date_fin_fr}<br>
                <small>${periodData.duree_jours} jours</small>
            `;
            
            // Positionner le tooltip
            const rect = periodElement.getBoundingClientRect();
            tooltip.style.position = 'fixed';
            tooltip.style.left = (rect.left + rect.width / 2) + 'px';
            tooltip.style.top = (rect.top - 60) + 'px';
            // Les styles transform et z-index sont déjà dans le CSS
            
            // Ajouter à la page
            document.body.appendChild(tooltip);
            this.currentTooltip = tooltip;
            
        } catch (error) {
            console.warn('Erreur showTooltip:', error);
        }
    }
    
    /**
     * Cache le tooltip actuel
     */
    hideTooltip() {
        if (this.currentTooltip) {
            this.currentTooltip.remove();
            this.currentTooltip = null;
        }
    }
    
    /**
     * Fallback vers le calendrier statique en cas d'erreur
     */
    fallbackToStaticCalendar() {
        console.warn('⚠️ Fallback vers calendrier statique');
        
        // Afficher un message d'information à l'utilisateur
        const container = document.getElementById('interactive-calendar');
        if (container) {
            const fallbackMessage = document.createElement('div');
            fallbackMessage.className = 'calendar-fallback-message';
            fallbackMessage.innerHTML = `
                <div style="background: rgba(255, 193, 7, 0.1); border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin-bottom: 20px; text-align: center;">
                    <i class="fas fa-exclamation-triangle" style="color: #ffc107; margin-right: 8px;"></i>
                    <strong>Mode de compatibilité :</strong> Calendrier statique activé.
                    <br><small>Le calendrier intelligent sera disponible lors du prochain déploiement.</small>
                </div>
            `;
            container.insertBefore(fallbackMessage, container.firstChild);
        }
        
        // Le calendrier statique reste dans le HTML de base (noscript)
        window.fvLog('📋 Calendrier statique maintenu pour compatibilité');
    }
}

// Auto-initialisation si on est sur la page d'accueil
document.addEventListener('DOMContentLoaded', () => {
    const calendarSection = document.getElementById('calendar-section');
    if (calendarSection) {
        const smartCalendar = new SmartCalendar();
        smartCalendar.init();
    }
});