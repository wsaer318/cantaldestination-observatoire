/**
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

    // Fonction utilitaire pour parser les dates ISO sans décalage de timezone
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

    // Fonction utilitaire pour gérer les erreurs d'abandon
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
            
            // Les filtres sont maintenant gérés par filters_loader.js
            // Attendre que les filtres soient chargés
            await this.waitForFiltersLoaded();
            
            // Appliquer les paramètres URL aux filtres
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

    // ✅ Nouvelle méthode pour observer les changements d'URL
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
        
        // Méthode pour marquer les changements internes
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
                // Assurer uniquement l'injection de l'option 'custom' si demandée
                if (desiredCode === 'custom' && !Array.from(periodSelect.options).some(o => o.value === 'custom')) {
                    const opt = document.createElement('option');
                    opt.value = 'custom';
                    opt.textContent = 'Intervalle personnalisé';
                    periodSelect.insertBefore(opt, periodSelect.firstChild);
                    periodSelect.value = 'custom';
                }
                return;
            }
            // IMPORTANT: ne pas reconstruire la liste, conserver les options créées par filters_loader.js
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
                    opt.textContent = 'Année complète';
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
            const monthsShortFR = ['janv.','févr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
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
                
                // Restaurer l'état original si nécessaire
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
                // Synchroniser l'année du panneau avec le sélecteur standard et recharger la liste
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

            // Cache pour éviter les appels multiples à loadDbPresets
            const presetsCache = new Map();
            
            const loadDbPresets = async (year) => {
                try {
                    // Vérifier le cache
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
                    const presets = [{ code: 'annee_complete', label: `Année complète ${year}` }, ...items];
                    
                    // Mettre en cache
                    presetsCache.set(year, { presets, year });
                    
                    // Rendre la liste
                    this.renderPresetsList(presets, year);
                    
                } catch (e) {
                    this.log('loadDbPresets error:', e);
                }
            };
            
            // Méthode pour rendre la liste des presets
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
                                            opt.textContent = 'Année complète';
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
                                // ✅ Ne pas fermer le panneau - laisser l'utilisateur voir la sélection
                                // closePanel();
                            } catch(_){ 
                                this.log('preset click error:', _);
                            }
                        });
                        list.appendChild(div);
                    });
                    
                    // ✅ Mettre en surbrillance la période actuellement sélectionnée
                    highlightCurrentPeriod();
                    
                } catch (error) {
                    this.log('renderPresetsList error:', error);
                }
            };
            
            // ✅ Fonction pour mettre en surbrillance la période actuelle
            const highlightCurrentPeriod = () => {
                try {
                    const periodSelect = document.getElementById('exc-period-select');
                    if (!periodSelect || !list) return;
                    
                    const currentPeriod = periodSelect.value;
                    // Retirer toutes les surbrillances
                    Array.from(list.children).forEach(el => el.classList.remove('active'));
                    
                    // Si la période n'est pas custom, mettre en surbrillance l'élément correspondant
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
                if (!start || !end) return 'Sélecteur avancé…';
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
                else display.textContent = 'Sélecteur avancé…';
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
                    if (!state.start) hint.textContent = 'Sélectionne la date de début…';
                    else if (!state.end) hint.textContent = 'Sélectionne la date de fin…';
                    else hint.textContent = `Sélection : ${fmt(state.start)} → ${fmt(state.end)}`;
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
                
                // ✅ Synchroniser la vue du calendrier avec la date de fin (borne supérieure)
                state.view = new Date(state.end.getFullYear(), state.end.getMonth(), 1);
                if (yearSel) yearSel.value = String(state.view.getFullYear());
                if (monthSel) monthSel.value = String(state.view.getMonth());
                

                
                updateDisplay();
                renderCalendar();
                try {
                    // Exposer la plage personnalisée et mettre à jour l'entête et l'URL
                    const fmtISO = (d)=> `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
                    const custom = { start: fmtISO(state.start), end: fmtISO(state.end) };
                    
                    // Utiliser la méthode centralisée
                    self.syncCustomRange({ 
                        commitToUrl: true, 
                        start: custom.start, 
                        end: custom.end 
                    });
                    
                    // ✅ NE PAS changer automatiquement l'année - laisser l'utilisateur la contrôler
                    // const yearSelectEl = document.getElementById('exc-year-select');
                    // if (yearSelectEl) yearSelectEl.value = String(state.start.getFullYear());
                    
                    const periodSelectEl = document.getElementById('exc-period-select');
                    if (periodSelectEl) {
                        // Utiliser l'année actuellement sélectionnée, pas celle de la date
                        const currentYear = document.getElementById('exc-year-select')?.value || new Date().getFullYear();
                        await self.updatePeriodSelectForYear(currentYear, 'custom');
                    }
                    
                    // Mettre à jour l'URL (annee, periode, zone) pour rester cohérent avec la source de vérité
                    try { 
                        self.markInternalURLChange();
                        self.updateURLWithFilters(); 
                    } catch(_){}
                    
                    const startSpan = document.getElementById('exc-start-date');
                    const endSpan = document.getElementById('exc-end-date');
                    if (startSpan) startSpan.textContent = fmt(state.start);
                    if (endSpan) endSpan.textContent = fmt(state.end);
                    
                    // Rafraîchir l'entête et relancer la génération
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
                
                // 1. Mettre à jour state.view
                state.view = new Date(y, state.view.getMonth(), 1); 
                
                // 2. Rendu du calendrier
                renderCalendar(); 
                
                // 3. Charger les presets de la base de données
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
                        // ✅ Afficher le mois de la date de fin (borne supérieure) à l'ouverture
                        state.view = new Date(state.end.getFullYear(), state.end.getMonth(), 1);
                        
                        
                        
                                        // ✅ Synchroniser les sélecteurs avec la vue du calendrier
                if (yearSel) {
                    yearSel.value = String(state.view.getFullYear());
                    yearSel.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (monthSel) {
                    monthSel.value = String(state.view.getMonth());
                    monthSel.dispatchEvent(new Event('change', { bubbles: true }));
                }
                        
                        
                        
                        // ✅ NE PAS changer automatiquement l'année - laisser l'utilisateur la contrôler
                        // const yearSelectEl = document.getElementById('exc-year-select');
                        // if (yearSelectEl) yearSelectEl.value = String(state.start.getFullYear());
                        
                        // Utiliser l'année actuellement sélectionnée, pas celle de la date
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

            // ✅ Initialisation des dates et du mois affiché selon la période sélectionnée
            try {
                const periodSelect = document.getElementById('exc-period-select');
                const yearSelect = document.getElementById('exc-year-select');
                
                if (periodSelect && yearSelect) {
                    const periode = periodSelect.value;
                    const annee = parseInt(yearSelect.value);
                    
                    
                    
                    // Si pas de plage custom définie, initialiser selon la période
                    if (!state.start || !state.end) {
                        if (periode === 'annee_complete') {
                            // Année complète : 1er janvier au 31 décembre
                            state.start = new Date(annee, 0, 1); // 1er janvier
                            state.end = new Date(annee, 11, 31); // 31 décembre
                            // Afficher décembre (mois de la date de fin)
                            state.view = new Date(annee, 11, 1); // 1er décembre
                            
                            
                        } else if (periode && periode !== 'custom') {
                            // Pour les autres périodes prédéfinies, récupérer les dates depuis l'API
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
                        
                        // Synchroniser les sélecteurs avec la vue du calendrier
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

            // ✅ Fonction pour réinitialiser le sélecteur selon la période
            const resetPeriodPickerForPeriod = async () => {
                const periodSelect = document.getElementById('exc-period-select');
                const yearSelect = document.getElementById('exc-year-select');
                
                if (periodSelect && yearSelect) {
                    const periode = periodSelect.value;
                    const annee = parseInt(yearSelect.value);
                    
                    // Réinitialiser l'état
                    state.start = null;
                    state.end = null;
                    
                    if (periode === 'annee_complete') {
                        // Année complète : 1er janvier au 31 décembre
                        state.start = new Date(annee, 0, 1);
                        state.end = new Date(annee, 11, 31);
                        state.view = new Date(annee, 11, 1); // Décembre
                    } else if (periode && periode !== 'custom') {
                        // Pour les autres périodes prédéfinies, récupérer les dates depuis l'API
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
                    
                    // Synchroniser les sélecteurs
                    if (yearSel) {
                        yearSel.value = String(state.view.getFullYear());
                        yearSel.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    if (monthSel) {
                        monthSel.value = String(state.view.getMonth());
                        monthSel.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    
                    // Mettre à jour l'affichage
                    updateDisplay();
                    renderCalendar();
                }
            };
            
            // ✅ Rendre la fonction accessible globalement
            window.resetPeriodPickerForPeriod = resetPeriodPickerForPeriod;

            // Initial render
            renderCalendar();
            
            // ✅ Écouter les changements de période
            const observePeriodChanges = () => {
                const periodSelect = document.getElementById('exc-period-select');
                if (periodSelect) {
                    // Écouter les événements change
                    periodSelect.addEventListener('change', () => {
                        resetPeriodPickerForPeriod();
                    });
                }
            };
            
            // Démarrer l'observation
            observePeriodChanges();
        } catch (e) {
            // Erreur silencieuse
        }
    }

    async loadConfig() {
        try {
            // Attendre un peu pour que les scripts se chargent
            await new Promise(resolve => setTimeout(resolve, 100));
            
            // Utiliser la même configuration que tdb_comparaison (obligatoire maintenant)
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
        // Appliquer les paramètres URL aux filtres (même logique que tdb_comparaison)
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

            // Appliquer la période depuis l'URL
            const periodFromUrl = urlParams.get('periode');
            if (periodFromUrl) {
                const periodSelect = document.getElementById('exc-period-select');
                if (periodSelect) {
                    // Recherche exacte d'abord
                    let option = Array.from(periodSelect.options).find(opt => 
                        opt.value.toLowerCase() === periodFromUrl.toLowerCase()
                    );
                    
                    // Recherche flexible si pas trouvé
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
        
        // Mettre à jour l'URL avec les valeurs actuelles des filtres (sans rechargement)
        try {
            const yearSelect = document.getElementById('exc-year-select');
            const periodSelect = document.getElementById('exc-period-select');
            const zoneSelect = document.getElementById('exc-zone-select');



            if (yearSelect?.value && periodSelect?.value && zoneSelect?.value) {
                const urlParams = new URLSearchParams(window.location.search);
                
                // Mettre à jour les paramètres
                urlParams.set('annee', yearSelect.value);
                urlParams.set('periode', periodSelect.value);
                urlParams.set('zone', zoneSelect.value);

                // Construire la nouvelle URL
                const newUrl = `${window.location.pathname}?${urlParams.toString()}`;
                

                
                                        // Mettre à jour l'URL sans rechargement
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
        
        // Si aucun filtre n'est disponible, lever une erreur contrôlée
        throw new Error('Impossible de charger les filtres après 10 secondes');
    }

    initializeEvents() {
        const self = this;
        
        // Bouton de téléchargement
        const downloadBtn = document.getElementById('btn-telecharger-infographie');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', () => { this.downloadInfographie(); });
        }

        // Écouter les changements de filtres
        const yearSelect = document.getElementById('exc-year-select');
        const periodSelect = document.getElementById('exc-period-select');
        const zoneSelect = document.getElementById('exc-zone-select');

        // ✅ Fonction pour réinitialiser le sélecteur avancé selon la période
        const resetAdvancedPeriodPicker = async () => {
            try {
                const periode = periodSelect?.value;
                const annee = parseInt(yearSelect?.value);
                
                if (periode && annee) {

                    
                    // Appeler la fonction de réinitialisation du sélecteur avancé
                    if (window.resetPeriodPickerForPeriod) {
                        await window.resetPeriodPickerForPeriod();
                    }
                }
            } catch (error) {
            }
        };

        // Fonction pour générer l'infographie automatiquement
        const autoGenerateInfographie = async () => {

            
            // Vérifier que tous les filtres sont chargés et ont une valeur
            if (yearSelect?.value && periodSelect?.value && zoneSelect?.value) {

                
                // Si la période n'est PAS custom, supprimer toute plage custom persistée (URL/mémoire)
                try {
                    if (periodSelect.value !== 'custom') {
                        self.syncCustomRange({ commitToUrl: true });
                    } else {
                        // ✅ Mettre à jour window.infographieCustomDateRange avec les dates de l'URL
                        self.syncCustomRange();
                    }
                } catch(_) {
                    self.log('autoGenerateInfographie syncCustomRange error:', _);
                }
                
                // ✅ Mettre à jour l'URL AVANT updateHeaderFromAPI pour que les dates soient cohérentes
                self.markInternalURLChange();
                self.updateURLWithFilters();
                
                // ✅ Réinitialiser le sélecteur avancé selon la nouvelle période
                await resetAdvancedPeriodPicker();
                
                // ✅ Forcer la mise à jour des dates custom dans l'URL si nécessaire
                if (periodSelect.value === 'custom') {
                    const urlParams = new URLSearchParams(window.location.search);
                    const currentYear = yearSelect.value;
                    const debutFromUrl = urlParams.get('debut');
                    const finFromUrl = urlParams.get('fin');
                    
                                            if (debutFromUrl && finFromUrl) {
                            const startDate = this.parseISODate(debutFromUrl);
                            const endDate = this.parseISODate(finFromUrl);
                        

                        
                        // Vérifier si les dates custom sont d'une année différente de l'année sélectionnée
                        if (startDate.getFullYear() !== parseInt(currentYear) || endDate.getFullYear() !== parseInt(currentYear)) {
                            
                            // ✅ Corriger la création des dates pour éviter les problèmes de timezone
                            const formatDate = (year, month, day) => {
                                return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                            };
                            
                            const newCustomStart = formatDate(parseInt(currentYear), startDate.getMonth(), startDate.getDate());
                            const newCustomEnd = formatDate(parseInt(currentYear), endDate.getMonth(), endDate.getDate());
                            
                            // Utiliser la méthode centralisée
                            self.syncCustomRange({ 
                                commitToUrl: true, 
                                start: newCustomStart, 
                                end: newCustomEnd 
                            });
                            
                        }
                    }
                }
                
                // Mettre à jour le header APRÈS la mise à jour de l'URL
                this.updateHeaderFromAPI();
                
                // ✅ Toujours régénérer l'infographie quand on change les filtres
                // Réinitialiser le flag pour permettre la régénération
                this.chartsGenerated = false;
                this.generateInfographie();
            } else {
                this.log('autoGenerateInfographie: filtres incomplets');
            }
        };

        // Écouter les changements de filtres
        [yearSelect, periodSelect, zoneSelect].forEach(select => {
            if (select) {
                select.addEventListener('change', (event) => {
                    
                    // Stocker la valeur précédente pour le prochain changement
                    event.target.dataset.previousValue = event.target.value;
                    
                    // Appeler la fonction de génération automatique
                    autoGenerateInfographie();
                });
            }
        });

        // Générer l'infographie initiale une fois les filtres chargés
        this.generateInitialInfographie();
    }

    async generateInitialInfographie() {
        // Attendre que les filtres soient chargés
        await this.waitForFiltersLoaded();
        
        // Vérifier que tous les filtres ont des valeurs par défaut
        const yearSelect = document.getElementById('exc-year-select');
        const periodSelect = document.getElementById('exc-period-select');
        const zoneSelect = document.getElementById('exc-zone-select');

        if (yearSelect?.value && periodSelect?.value && zoneSelect?.value) {
            this.updateHeaderFromAPI();
            // Générer l'infographie initiale seulement une fois
            if (!this.chartsGenerated) {
                this.generateInfographie();
            }
        }
    }

    async updateHeaderFromAPI() {
        
        try {
            // Récupérer les valeurs des filtres
            const annee = document.getElementById('exc-year-select')?.value || this.config?.defaultYear;
            const periode = document.getElementById('exc-period-select')?.value || this.config?.defaultPeriod;
            const zone = document.getElementById('exc-zone-select')?.value || this.config?.defaultZone;


            if (!annee || !periode || !zone) {
                return;
            }

            // Appeler l'API bloc_a pour récupérer les vraies dates (même logique que le tableau de bord)
            // Prendre en compte une éventuelle plage custom (URL ou mémoire)
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

            // Mettre à jour le header; si plage custom active, forcer le libellé long
            if (customRange) {
                data.periode = 'custom';
                data.annee = String(this.parseISODate(customRange.start).getFullYear());
                data.debut = `${customRange.start} 00:00:00`;
                data.fin = `${customRange.end} 23:59:59`;
            }
            
            this.updateHeader(data);

        } catch (error) {
            // Erreur silencieuse
            // Fallback vers les dates calculées
            this.updateHeaderDates();
        }
    }

    updateHeader(data) {
        // Même logique que dans tdb_comparaison.js
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

        // Mettre à jour aussi les ancrages de compatibilité
        const titleHook = document.querySelector('[data-title]');
        const subtitleHook = document.querySelector('[data-subtitle]');
        if (titleHook) titleHook.textContent = 'Synthèse Touristique';
        if (subtitleHook) subtitleHook.textContent = `${periodLabel} ${data.annee}`;

        // Synchroniser immédiatement le header visuel (ht-title-line line2) si déjà présent
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
        // Méthode de fallback avec dates calculées
        const startDateSpan = document.getElementById('exc-start-date');
        const endDateSpan = document.getElementById('exc-end-date');
        const headerPeriodSpan = document.getElementById('header-period');

        if (headerPeriodSpan) {
            const periodSelect = document.getElementById('exc-period-select');
            const selectedPeriod = periodSelect?.options[periodSelect.selectedIndex]?.text || 'Période';
            headerPeriodSpan.textContent = selectedPeriod;
        }


        // En cas de fallback, afficher des dates génériques
        if (startDateSpan) startDateSpan.textContent = '--/--/----';
        if (endDateSpan) endDateSpan.textContent = '--/--/----';
    }

    // Méthode pour calculer les dates selon la période


    // Méthode utilitaire pour formater les dates
    formatDate(s) {
        if (!s) return '--/--/----';
        const d = this.parseISODate(s) || new Date(s);
        return this.isValidDate(d) ? d.toLocaleDateString('fr-FR') : '--/--/----';
    }

    // Formate une plage compacte pour pp-display à partir de chaînes date ISO/SQL
    formatCompactRangeFromStrings(startStr, endStr) {
        try {
            if (!startStr || !endStr) return 'Sélecteur avancé…';
            const s = this.parseISODate(startStr) || new Date(startStr);
            const e = this.parseISODate(endStr) || new Date(endStr);
            
            if (!this.isValidDate(s) || !this.isValidDate(e)) return 'Sélecteur avancé…';
            const sameMonth = s.getFullYear() === e.getFullYear() && s.getMonth() === e.getMonth();
            const fmtMonth = new Intl.DateTimeFormat('fr-FR', { month: 'short' });
            const dd = (d)=> String(d.getDate()).padStart(2,'0');
            if (sameMonth) {
                return `${dd(s)}–${dd(e)} ${fmtMonth.format(s)} ${s.getFullYear()}`;
            }
            return `${dd(s)} ${fmtMonth.format(s)} ${s.getFullYear()} – ${dd(e)} ${fmtMonth.format(e)} ${e.getFullYear()}`;
        } catch (_) {
            return 'Sélecteur avancé…';
        }
    }

    async generateInfographie() {
        
        // Annuler les requêtes précédentes
        this.fetchCtl?.abort();
        this.fetchCtl = new AbortController();
        const { signal } = this.fetchCtl;
        
        // Éviter les générations multiples simultanées
        if (this.chartsGenerated) {
            return;
        }

        this.chartsGenerated = true;
        
        // Afficher l'indicateur de chargement
        this.showLoadingIndicator();
        
        try {
            // Récupérer les valeurs des filtres
            const filters = this.getFilterValues();
            
            // Si des bornes custom existent mais que l'utilisateur a explicitement choisi annee_complete,
            // on ne force pas period=custom (on laisse l'utilisateur sur annee_complete),
            // mais on supprime les bornes custom de l'URL pour éviter la confusion
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
            
            // Charger les données
            await this.loadInfographieData(filters, signal);
            
            // Générer l'infographie
            this.renderInfographie();
            
            // Activer les boutons de téléchargement et partage
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
            this.showError('Erreur lors de la génération de l\'infographie');
        } finally {
            // Masquer l'indicateur de chargement
            this.hideLoadingIndicator();
            
            // Garder chartsGenerated à true si la génération a réussi
            // (les boutons restent activés, donc l'infographie est prête)
            if (this.chartsGenerated) {
                // L'infographie a été générée avec succès, on garde le flag à true
                window.fvLog('Infographie générée avec succès');
            }
        }
    }

    getFilterValues() {
        
        // Lire les paramètres URL en priorité (même logique que tdb_comparaison)
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
        
        // ✅ Utiliser directement les dates de l'URL (qui sont maintenant mises à jour par autoGenerateInfographie)
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
        ]);

        const [keyIndicatorsResult, nuiteesOriginsResult, excursionnistesOriginsResult, nuiteesDepartementsResult, excursionnistesDepartementsResult] = results;

        window.fvLog('[Infographie] 📊 Résultats des chargements:');
        window.fvLog('[Infographie] 🔑 keyIndicatorsResult:', keyIndicatorsResult.status === 'fulfilled' ? '✅' : '❌', keyIndicatorsResult.status === 'fulfilled' ? keyIndicatorsResult.value : keyIndicatorsResult.reason);
        window.fvLog('[Infographie] 🏠 nuiteesOriginsResult:', nuiteesOriginsResult.status === 'fulfilled' ? '✅' : '❌');
        window.fvLog('[Infographie] 🚶 excursionnistesOriginsResult:', excursionnistesOriginsResult.status === 'fulfilled' ? '✅' : '❌');
        window.fvLog('[Infographie] 📊 nuiteesDepartementsResult:', nuiteesDepartementsResult.status === 'fulfilled' ? '✅' : '❌');
        window.fvLog('[Infographie] 🚶 excursionnistesDepartementsResult:', excursionnistesDepartementsResult.status === 'fulfilled' ? '✅' : '❌');

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
        };




        return this.currentData;
    }

    async loadKeyIndicators(filters, paramsRange = '', signal) {
        // Utiliser l'API existante pour les indicateurs clés (même que tdb_comparaison)
        try {
            const url = `api/infographie/infographie_indicateurs_cles.php?annee=${filters.year}&periode=${filters.period}&zone=${filters.zone}${paramsRange}`;
            const response = await fetch(url, { signal });
            const responseText = await response.text();
            
            // Vérifier si la réponse est du JSON valide
            if (!responseText.trim().startsWith('{') && !responseText.trim().startsWith('[')) {
                // Erreur silencieuse
                return null;
            }
            
            const data = JSON.parse(responseText);
            return data;
        } catch (error) {
            return this.handleFetchError(error);
        }
    }

    async loadNuiteesOrigins(filters, paramsRange = '', signal) {
        // Charger les données d'origines pour les nuitées (régions + pays)
        try {
            // Charger les régions et pays en parallèle pour les nuitées
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
            const url = `api/infographie/infographie_regions_touristes.php?annee=${filters.year}&periode=${filters.period}&zone=${filters.zone}&limit=10${paramsRange}`;
            const response = await fetch(url, { signal });
            const responseText = await response.text();
            
            if (!responseText.trim().startsWith('{') && !responseText.trim().startsWith('[')) {
                // Erreur silencieuse
                return null;
            }
            
            const data = JSON.parse(responseText);
            return data;
            
        } catch (error) {
            return this.handleFetchError(error);
        }
    }

    async loadNuiteesPays(filters, paramsRange = '', signal) {
        try {
            const url = `api/infographie/infographie_pays_touristes.php?annee=${filters.year}&periode=${filters.period}&zone=${filters.zone}&limit=10${paramsRange}`;
            const response = await fetch(url, { signal });
            const responseText = await response.text();
            
            if (!responseText.trim().startsWith('{') && !responseText.trim().startsWith('[')) {
                // Erreur silencieuse
                return null;
            }
            
            const data = JSON.parse(responseText);
            return data;
            
        } catch (error) {
            return this.handleFetchError(error);
        }
    }

    async loadExcursionnistesOrigins(filters, paramsRange = '', signal) {
        // Charger les données d'origines pour les excursionnistes (régions + pays)
        try {
            // Charger les régions et pays en parallèle pour les excursionnistes
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
            const url = `api/infographie/infographie_regions_excursionnistes.php?annee=${filters.year}&periode=${filters.period}&zone=${filters.zone}&limit=10${paramsRange}`;
            const response = await fetch(url, { signal });
            const responseText = await response.text();
            
            if (!responseText.trim().startsWith('{') && !responseText.trim().startsWith('[')) {
                // Erreur silencieuse
                return null;
            }
            
            const data = JSON.parse(responseText);
            return data;
            
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

            const url = `api/infographie/infographie_pays_excursionnistes.php?${params}`;
            const response = await fetch(url, { signal });
            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            return data;
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

            const url = `api/infographie/infographie_departements_touristes.php?${params}`;
            const response = await fetch(url, { signal });
            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            return data;
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

            const url = `api/infographie/infographie_departements_excursionnistes.php?${params}`;
            const response = await fetch(url, { signal });
            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }
            return data;
        } catch (error) {
            return this.handleFetchError(error);
        }
    }


    renderInfographie() {
        window.fvLog('[Infographie] 🎨 Début du rendu de l\'infographie');

        const container = document.getElementById('infographie-container');
        if (!container) {
            console.error('[Infographie] ❌ Container infographie-container non trouvé');
            return;
        }

        window.fvLog('[Infographie] ✅ Container trouvé, destruction des anciens graphiques');
        // Détruire tous les graphiques existants avant de continuer
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

        // Vérifier le template principal
        const template = document.getElementById('infographie-main-template');
        if (!template) {
            // Erreur silencieuse
            container.innerHTML = '<div class="error">Template principal non trouvé</div>';
            return;
        }

        const clone = template.content.cloneNode(true);

        // Remplir les données du template
        try {
            this.populateMainTemplate(clone);
        } catch (error) {
            // Erreur silencieuse
            container.innerHTML = '<div class="error">Erreur lors du remplissage: ' + error.message + '</div>';
            return;
        }

        // Ajouter le contenu au container
        container.appendChild(clone);

        // Vérifier que le contenu a bien été ajouté et l'activer
        const addedContent = container.querySelector('.infographie-content');
        if (addedContent) {
            addedContent.classList.add('active');
        } else {
            // Erreur silencieuse
        }

        // Générer les graphiques immédiatement après avoir ajouté le DOM
        // Plus de timeout pour éviter les conflits
        this.generateCharts();
    }

    populateMainTemplate(clone) {
        
        const filters = this.currentData.filters;
        const periodLabel = document.getElementById('exc-period-select')?.options[document.getElementById('exc-period-select').selectedIndex]?.text || filters.period;

        // Remplir le header (nouvelle maquette HeaderTourisme)
        // 1) Ancien système (si présent)
        const titleElement = clone.querySelector('[data-title]');
        const subtitleElement = clone.querySelector('[data-subtitle]');
        if (titleElement) {
            titleElement.textContent = `INFOGRAPHIE TOURISTIQUE - ${filters.zone}`;
        }
        if (subtitleElement) {
            subtitleElement.textContent = `${periodLabel} ${filters.year}`;
        }

        // 2) Nouveau header: mettre à jour les lignes centrales si disponibles
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

        // Remplir les indicateurs durée de séjour
        const dureeSejourContainer = clone.querySelector('[data-indicators-duree-sejour]');
        if (dureeSejourContainer) {
            this.populateKeyIndicators(dureeSejourContainer, 'duree-sejour');
        } else {
            this.log('populateMainTemplate: duree-sejour container not found');
        }

        // Footer : bandeau des partenaires (pas de texte source à remplir)
        
        
    }

    populateKeyIndicators(container, category = null) {
        if (!this.currentData.keyIndicators) {
            // Masquer la section d'indicateurs si pas de données
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
                title: "Nuitées totales (Français + International)",
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
                title: "Weekend de Pâques",
                icon: "fa-solid fa-rabbit",
                unit: "Nuitées",
                defaultRemark: "Nuitées weekend de Pâques",
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
                title: "Weekend de la Pentecôte",
                icon: "fa-solid fa-fire",
                unit: "Nuitées",
                defaultRemark: "Nuitées weekend de la Pentecôte",
                category: "nuitees"
            },
            // Indicateurs Excursionnistes de base
            {
                numero: 15,
                title: "Excursionnistes français",
                icon: "fa-solid fa-person-hiking",
                unit: "Présences",
                defaultRemark: "Excursionnistes NonLocaux (Français)",
                category: "excursionnistes"
            },
            {
                numero: 15.5,
                title: "Excursionnistes internationaux",
                icon: "fa-solid fa-globe",
                unit: "Présences",
                defaultRemark: "Excursionnistes Etrangers",
                category: "excursionnistes"
            },
            {
                numero: 16,
                title: "Excursionnistes totaux (Français + International)",
                icon: "fa-solid fa-person-hiking",
                unit: "Présences",
                defaultRemark: "Excursionnistes NonLocaux + Etrangers",
                category: "excursionnistes"
            },
            {
                numero: 17,
                title: "Présences 2e samedi",
                icon: "fa-solid fa-calendar-week",
                unit: "Présences",
                defaultRemark: "Excursionnistes 2e samedi du mois",
                category: "excursionnistes"
            },
            {
                numero: 18,
                title: "Présences 3e samedi",
                icon: "fa-solid fa-calendar-week",
                unit: "Présences",
                defaultRemark: "Excursionnistes 3e samedi du mois",
                category: "excursionnistes"
            },
            // Indicateurs Excursionnistes Printemps (19-22)
            {
                numero: 19,
                title: "Weekend de Pâques",
                icon: "fa-solid fa-rabbit",
                unit: "Présences",
                defaultRemark: "Excursionnistes weekend de Pâques",
                category: "excursionnistes"
            },
            {
                numero: 20,
                title: "1er mai",
                icon: "fa-solid fa-seedling",
                unit: "Présences",
                defaultRemark: "Excursionnistes 1er mai",
                category: "excursionnistes"
            },
            {
                numero: 21,
                title: "Weekend de l'Ascension",
                icon: "fa-solid fa-dove",
                unit: "Présences",
                defaultRemark: "Excursionnistes weekend de l'Ascension",
                category: "excursionnistes"
            },
            {
                numero: 22,
                title: "Weekend de la Pentecôte",
                icon: "fa-solid fa-fire",
                unit: "Présences",
                defaultRemark: "Excursionnistes weekend de la Pentecôte",
                category: "excursionnistes"
            },
            // Indicateurs Excursionnistes Été (23-24)
            {
                numero: 23,
                title: "14 juillet",
                icon: "fa-solid fa-firework",
                unit: "Présences",
                defaultRemark: "Excursionnistes 14 juillet",
                category: "excursionnistes"
            },
            {
                numero: 24,
                title: "15 août",
                icon: "fa-solid fa-sun",
                unit: "Présences",
                defaultRemark: "Excursionnistes 15 août",
                category: "excursionnistes"
            },
            // Indicateurs Durée de séjour (25-27)
            {
                numero: 25,
                title: "Durée moyenne de séjour totale",
                icon: "fa-solid fa-stopwatch",
                unit: "Nuits",
                defaultRemark: "Durée moyenne pondérée (FR + INTL)",
                category: "duree-sejour"
            },
            {
                numero: 26,
                title: "Durée moyenne de séjour française",
                icon: "fa-solid fa-flag",
                unit: "Nuits",
                defaultRemark: "Durée moyenne pondérée française",
                category: "duree-sejour"
            },
            {
                numero: 27,
                title: "Durée moyenne de séjour internationale",
                icon: "fa-solid fa-globe",
                unit: "Nuits",
                defaultRemark: "Durée moyenne pondérée internationale",
                category: "duree-sejour"
            }
        ];

        const indicators = this.currentData.keyIndicators.bloc_a || [];
        const currentYear = parseInt(this.currentData.filters.year);

        // Filtrer les indicateurs par catégorie si spécifiée
        const filteredConfig = category ? keyIndicatorsConfig.filter(config => config.category === category) : keyIndicatorsConfig;

        // Vider le container
        container.innerHTML = '';

        let indicatorsAdded = 0;

        filteredConfig.forEach(config => {
            const indicator = this.findIndicator(indicators, config.numero);
            
            // Ne pas afficher les indicateurs conditionnels s'ils ne sont pas dans la réponse de l'API
            // - Indicateurs 17, 18 : vacances d'hiver uniquement
            // - Indicateurs 6, 7, 8, 9, 19, 20, 21, 22 : printemps uniquement
            // - Indicateurs 23, 24 : été uniquement (14 juillet et 15 août)
            const indicateursConditionnels = [6, 7, 8, 9, 17, 18, 19, 20, 21, 22, 23, 24];
            if (indicateursConditionnels.includes(config.numero) && !indicator) {
                return; // Skip cet indicateur
            }
            
            // Vérifier si la valeur de référence est nulle (0)
            // ⚠️ TEMPORAIRE: Afficher les indicateurs 2025 même sans données N-1 pour HAUTES TERRES
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
            
            // Remplir les données avec le nouveau template
            const iconElement = clone.querySelector('[data-icon]');
            const titleElement = clone.querySelector('[data-title]');
            const unitElement = clone.querySelector('[data-unit]');
            const historyElement = clone.querySelector('[data-history]');
            
            if (iconElement) iconElement.className += ` ${config.icon}`;
            if (titleElement) titleElement.textContent = config.title;
            if (unitElement) unitElement.textContent = config.unit;
            
            // Générer l'historique des 4 dernières années
            if (historyElement) {
                this.generateIndicatorHistory(historyElement, indicator, currentYear);
            }
            
            container.appendChild(clone);
            indicatorsAdded++;
        });
        
        // Masquer la section d'indicateurs si aucun indicateur n'a été ajouté
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
        
        // Utiliser les nouvelles données de l'API avec les 4 années
        const years = [];
        
        // Année de référence (année sélectionnée)
        years.push({
            year: referenceYear,
            value: indicator?.N || 0,
            evolution: null, // Pas d'évolution pour l'année de référence
            isReference: true,
            isCurrent: (referenceYear === currentYear)
        });
        
        // Année N-1
        years.push({
            year: referenceYear - 1,
            value: indicator?.N_1 || 0,
            evolution: indicator?.evolution_pct,
            isReference: false,
            isCurrent: false
        });
        
        // Année N-2
        years.push({
            year: referenceYear - 2,
            value: indicator?.N_2 || 0,
            evolution: indicator?.evolution_pct_N1,
            isReference: false,
            isCurrent: false
        });
        
        // Année N-3
        years.push({
            year: referenceYear - 3,
            value: indicator?.N_3 || 0,
            evolution: indicator?.evolution_pct_N2,
            isReference: false,
            isCurrent: false
        });
        
        // Générer le HTML pour chaque année (exclure les années avec valeur 0)
        container.innerHTML = '';
        years.forEach(yearData => {
            // Ne pas afficher les années avec une valeur de 0 (sauf l'année de référence)
            if (yearData.value === 0 && !yearData.isReference) {
                return;
            }
            
            const yearRow = document.createElement('div');
            yearRow.className = `indicator-year-row ${yearData.isCurrent ? 'current-year' : ''} ${yearData.isReference ? 'reference-year' : ''}`;
            
            // Année (seulement pour les années précédentes)
            if (!yearData.isReference) {
                const yearSpan = document.createElement('span');
                yearSpan.className = 'indicator-year';
                yearSpan.textContent = yearData.year;
                yearRow.appendChild(yearSpan);
            }
            
            // Valeur
            const valueSpan = document.createElement('span');
            valueSpan.className = 'indicator-value';
            
            // Utiliser formatDuration pour les indicateurs de durée de séjour (25, 26, 27)
            const isDurationIndicator = indicator?.numero >= 25 && indicator?.numero <= 27;
            valueSpan.textContent = isDurationIndicator ? this.formatDuration(yearData.value) : this.formatNumber(yearData.value);
            
            // Évolution
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
                // Pas de données
                evolutionSpan.textContent = yearData.value > 0 ? '--' : '--';
                evolutionSpan.classList.add('neutral');
            }
            
            yearRow.appendChild(valueSpan);
            yearRow.appendChild(evolutionSpan);
            
            container.appendChild(yearRow);
        });
    }

    // Méthode utilitaire pour trouver un indicateur par numéro
    findIndicator(indicators, numero) {
        return indicators.find(ind => ind.numero === numero);
    }

    // Méthode utilitaire pour formater les nombres au format français
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

    // Méthode pour formater les nombres avec séparateurs de milliers (format français)
    formatNumberWithSeparators(num) {
        if (num === null || num === undefined || isNaN(num)) return '0';
        
        const value = parseInt(num);
        return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00A0');
    }

    // Méthode pour afficher un message d'erreur sur un canvas de graphique
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

        // Afficher le message sur plusieurs lignes si nécessaire
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

    // Méthode pour formater les pourcentages au format français
    formatPercentage(num) {
        if (num === null || num === undefined || isNaN(num)) return '0%';
        
        const value = parseFloat(num);
        return value.toFixed(1).replace('.', ',') + '%';
    }

    // Méthode pour formater les durées de séjour (1 chiffre après la virgule)
    formatDuration(num) {
        if (num === null || num === undefined || isNaN(num)) return '0,0';
        
        const value = parseFloat(num);
        return value.toFixed(1).replace('.', ',');
    }

    // Méthode pour obtenir le symbole de variation (flèche)
    getVariationSymbol(evolution) {
        if (evolution === null || evolution === undefined || isNaN(evolution)) return '';
        
        const value = parseFloat(evolution);
        if (value > 0) return '↑';
        if (value < 0) return '↓';
        return '→';
    }



    generateCharts() {
        
        // Réinitialiser l'affichage de tous les conteneurs de graphiques
        this.resetChartContainers();
        
        // Générer les graphiques d'origines
        this.updateLoadingText('Génération des graphiques d\'origines...');
        this.generateNuiteesOriginCharts();
        
        this.generateExcursionnistesOriginCharts();


        
        this.updateLoadingText('Finalisation de l\'infographie...');
        
        // Vérification finale de l'état des éléments
        this.logFinalState();
        
        // Forcer le masquage des titres si nécessaire
        this.forceHideEmptyTitles();
    }



    generateNuiteesOriginCharts() {
        // Récupérer les couleurs du thème
        const colors = this.getThemeColors();
        
        // Générer les 3 graphiques séparément
        this.generateOriginChart('nuitees-departements-chart', 'nuiteesOrigins', 'departements', colors.primary, 15);
        this.generateOriginChart('nuitees-regions-chart', 'nuiteesOrigins', 'regions', colors.primary, 5);
        this.generateOriginChart('nuitees-pays-chart', 'nuiteesOrigins', 'pays', colors.primary, 5);
        
        // Réorganiser la grille après masquage des graphiques vides
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
        
        // Générer automatiquement le label basé sur le type
        const typeLabels = {
            'departements': 'Départements',
            'regions': 'Régions', 
            'pays': 'Pays'
        };
        const label = typeLabels[dataType] || 'Données';
        
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            // Erreur silencieuse
            return;
        }

        // Ajouter l'indicateur d'unité dans l'en-tête du graphique
        const chartContainer = canvas.closest('.chart-container');
        if (chartContainer) {
            const chartHeader = chartContainer.querySelector('.chart-header');
            if (chartHeader) {
                // Supprimer l'ancien indicateur d'unité s'il existe
                const existingUnitIndicator = chartContainer.querySelector('.chart-unit-indicator');
                if (existingUnitIndicator) {
                    existingUnitIndicator.remove();
                }

                // Créer le nouvel indicateur d'unité
                const unitIndicator = document.createElement('div');
                unitIndicator.className = 'chart-unit-indicator';
                const unit = dataCategory.includes('nuitees') ? 'nuitées' : 'présences';
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

        // Traitement des données pour Chart.js avec comparaison N vs N-1
        const chartData = this.processOriginDataComparison(data, dataType, limit);
        
        // Vérifier si on a des données valides après traitement
        if (!chartData.labels || chartData.labels.length === 0 || chartData.currentValues.every(v => v === 0)) {

            // Masquer complètement le conteneur du graphique
            const chartContainer = canvas.closest('.chart-container');
            if (chartContainer) {
                chartContainer.classList.add('is-hidden');
            }
            return;
        }
        
        // Détruire le graphique existant s'il existe
        const chartKey = `${canvasId}Chart`;
        if (this.chartInstances[chartKey]) {
            this.chartInstances[chartKey].destroy();
        }

        // Créer le graphique en barres horizontales avec comparaison
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
                                const x = yScale.left - 8; // Position à gauche de l'axe avec marge
                                
                                ctx.save();
                                ctx.fillStyle = colors.textPrimary; // Utiliser la variable CSS
                                ctx.font = '11px -apple-system, BlinkMacSystemFont, sans-serif';
                                ctx.textAlign = 'right';
                                ctx.textBaseline = 'middle';
                                
                                // Afficher la deuxième ligne de l'étiquette
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

                        // Capture une référence sûre à la fonction de formatage
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
                            const xPix = x.getPixelForValue(v) + 4; // Réduit de 8px à 4px
                            // Utiliser l'espace de padding défini dans layout.padding.right
                            const maxX = chartArea.right + 20; // Réduit de 40px à 20px
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
                        right: 6 // Réduit de 10px à 6px pour rapprocher les chiffres
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
                                const unit = dataCategory.includes('nuitees') ? 'nuitées' : 'présences';
                                const year = context.dataset.label || chartData.currentYear;
                                return `${year}: ${this.formatNumber(value)} ${unit}`;
                            },
                            afterLabel: (context) => {
                                // Calculer l'évolution si on a les deux valeurs
                                const currentIndex = context.datasetIndex === 0 ? 0 : 1;
                                const otherIndex = currentIndex === 0 ? 1 : 0;
                                const currentValue = context.parsed.x || 0;
                                const otherValue = context.chart.data.datasets[otherIndex]?.data[context.dataIndex] || 0;
                                
                                if (currentValue > 0 && otherValue > 0 && currentIndex === 0) {
                                    const evolution = ((currentValue - otherValue) / otherValue * 100);
                                    const symbol = this.getVariationSymbol(evolution);
                                    const formattedEvolution = this.formatPercentage(Math.abs(evolution));
                                    return `${symbol} ${formattedEvolution} vs référence`;
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
                            const minWidth = isRegionsChart ? 80 : 60; // Réduit de 50% pour rapprocher les étiquettes
                            scale.width = Math.max(scale.width, minWidth);
                        },
                        ticks: {
                            color: colors.textPrimary, // Utiliser la variable CSS
                            font: { size: limit > 10 ? 10 : 12, weight: '500' }, // Police plus grosse et plus grasse
                            maxTicksLimit: limit > 10 ? 15 : 8,
                            padding: 6, // Réduit de 12px à 6px (50% de réduction)
                            autoSkip: false, // Désactive l'auto-skip des catégories
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
     * Raccourcit intelligemment les noms de régions pour l'affichage
     */
    shortenRegionName(regionName) {
        if (!regionName || regionName.length <= 12) return regionName;
        
        const abbreviations = {
            'AUVERGNE-RHÔNE-ALPES': 'AUVERGNE-RHÔNE-ALPES', // Garder tel quel mais on va ajuster l'affichage
            'BOURGOGNE-FRANCHE-COMTÉ': 'BOURGOGNE-F.COMTÉ',
            'CENTRE-VAL DE LOIRE': 'CENTRE-VAL LOIRE',
            'GRAND EST': 'GRAND EST',
            'HAUTS-DE-FRANCE': 'HAUTS-DE-FRANCE',
            'ILE-DE-FRANCE': 'ILE-DE-FRANCE',
            'NORMANDIE': 'NORMANDIE',
            'NOUVELLE AQUITAINE': 'NOUVELLE AQUITAINE',
            'OCCITANIE': 'OCCITANIE',
            'PAYS DE LA LOIRE': 'PAYS DE LA LOIRE',
            'PROVENCE-ALPES-CÔTE D\'AZUR': 'PACA',
            'BRETAGNE': 'BRETAGNE',
            'CORSE': 'CORSE'
        };
        
        return abbreviations[regionName] || regionName;
    }

    /**
     * Divise un nom de région en plusieurs lignes si nécessaire
     */
    wrapRegionName(regionName) {
        if (!regionName || regionName.length <= 16) return [regionName];
        
        const specialCases = {
            'AUVERGNE-RHÔNE-ALPES': ['AUVERGNE', 'RHÔNE-ALPES'],
            'BOURGOGNE-FRANCHE-COMTÉ': ['BOURGOGNE', 'FRANCHE-COMTÉ'],
            'CENTRE-VAL DE LOIRE': ['CENTRE', 'VAL DE LOIRE'],
            'NOUVELLE AQUITAINE': ['NOUVELLE', 'AQUITAINE'],
            'PROVENCE-ALPES-CÔTE D\'AZUR': ['PROVENCE-ALPES', 'CÔTE D\'AZUR'],
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

        // Trier les données par valeur décroissante de l'année courante et limiter
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
        // Combiner les données des régions et pays en un seul tableau
        const combined = [];
        
        // Ajouter les régions
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
        
        // Trier par valeur décroissante
        combined.sort((a, b) => b.valeur - a.valeur);
        
        return combined;
    }

    processOriginsData(data, type) {
        // Traiter les données des APIs pour les adapter aux graphiques
        
        // Vérifier la structure des données
        if (!data || !Array.isArray(data)) {
            // Erreur silencieuse
            return { labels: ['Aucune donnée'], values: [0] };
        }

        // Extraire les labels et valeurs (données déjà formatées par combineOriginsData)
        const labels = [];
        const values = [];

        // Limiter aux 8 premières origines pour la lisibilité
        const limitedData = data.slice(0, 8);
        
        limitedData.forEach(item => {
            if (item.origine && item.valeur !== undefined) {
                labels.push(item.origine);
                values.push(item.valeur);
            }
        });

        return { labels, values };
    }

    // Réinitialiser l'affichage de tous les conteneurs de graphiques
    resetChartContainers() {
        
        const allChartContainers = document.querySelectorAll('.chart-container');
        const allOriginsSubsections = document.querySelectorAll('.origins-subsection');
        const allIndicatorsSubsections = document.querySelectorAll('.indicators-subsection');
        const allOriginsHeaders = document.querySelectorAll('.origins-header');
        
        // Réafficher tous les conteneurs de graphiques
        allChartContainers.forEach(container => {
            container.classList.remove('is-hidden');
        });
        
        // Réafficher toutes les sections d'origines
        allOriginsSubsections.forEach(section => {
            section.classList.remove('is-hidden');
        });
        
        // Réafficher toutes les sections d'indicateurs
        allIndicatorsSubsections.forEach(section => {
            section.classList.remove('is-hidden');
        });
        
        
        // Réafficher tous les titres des sections d'origines
        allOriginsHeaders.forEach(header => {
            header.classList.remove('is-hidden');
        });
        
    }

    // Réorganiser la grille des origines quand certains graphiques sont masqués
    reorganizeOriginGrid(category) {
        
        // Mapper les catégories aux vraies classes CSS
        const sectionClass = category === 'nuitees' ? 'tourist-section' : 'excursionist-section';
        const originsGrid = document.querySelector(`.${sectionClass} .origins-grid`);
        if (!originsGrid) {
            return;
        }

        const hiddenCharts = originsGrid.querySelectorAll('.chart-container.is-hidden');
        const totalCharts = originsGrid.querySelectorAll('.chart-container').length;
        const hiddenCount = hiddenCharts.length;

        // Si tous les graphiques sont masqués, masquer toute la section
        if (hiddenCount === totalCharts) {
            const section = originsGrid.closest('.origins-subsection');
            if (section) {
                section.classList.add('is-hidden');
            }
        } else {
            // Si certains graphiques sont visibles, masquer seulement le titre si tous les graphiques sont masqués
            const visibleCharts = originsGrid.querySelectorAll('.chart-container:not(.is-hidden)');
            if (visibleCharts.length === 0) {
                const originsHeader = originsGrid.closest('.origins-subsection')?.querySelector('.origins-header');
                if (originsHeader) {
                    originsHeader.classList.add('is-hidden');
                }
            }
        }
        
        // Optimisation : remplacer les sélecteurs :has() en cascade par des toggles de classe
        this.optimizeGridLayout(originsGrid);
    }

    // Méthode optimisée pour remplacer les sélecteurs :has() en cascade
    optimizeGridLayout(grid) {
        const hiddenCharts = grid.querySelectorAll('.chart-container.is-hidden');
        const totalCharts = grid.querySelectorAll('.chart-container').length;
        const hiddenCount = hiddenCharts.length;

        // Supprimer toutes les classes de layout précédentes
        grid.classList.remove('layout-single', 'layout-double', 'layout-full');

        // Appliquer le layout approprié
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


    // Forcer le masquage des titres vides
    forceHideEmptyTitles() {
        
        // Vérifier toutes les sections d'origines
        const originsSubsections = document.querySelectorAll('.origins-subsection');
        originsSubsections.forEach((section, index) => {
            const visibleCharts = section.querySelectorAll('.chart-container:not(.is-hidden)');
            const originsHeader = section.querySelector('.origins-header');
            
            if (visibleCharts.length === 0 && originsHeader) {
                originsHeader.classList.add('is-hidden');
            }
        });
        
        
        // Vérifier toutes les sections d'indicateurs
        const indicatorsSubsections = document.querySelectorAll('.indicators-subsection');
        indicatorsSubsections.forEach((section, index) => {
            const visibleIndicators = section.querySelectorAll('.indicator-card-compact');
            const indicatorsHeader = section.querySelector('.indicators-header');
            
            if (visibleIndicators.length === 0 && indicatorsHeader) {
                section.classList.add('is-hidden');
            }
        });
        
    }

    // Vérification finale de l'état des éléments
    logFinalState() {
        
        // Vérifier les sections d'origines
        const originsSubsections = document.querySelectorAll('.origins-subsection');
        originsSubsections.forEach((section, index) => {            
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
            downloadBtn.innerHTML = '<div class="loading-spinner"></div> <span class="btn-title">Téléchargement...</span>';
            
            // Afficher l'indicateur de chargement pour le téléchargement
            this.showDownloadLoadingIndicator();

            const container = document.querySelector('.infographie-content');
            if (!container) {
                throw new Error('Aucune infographie à télécharger');
            }

            // Étape 1: Convertir tous les graphiques Chart.js en images
            await this.convertChartsToImages();

            // Étape 2: Attendre que toutes les images soient chargées
            await new Promise(resolve => setTimeout(resolve, 1000));

            // Étape 3: Activer le mode export avec les styles CSS identiques à la page web
            container.classList.add('export-mode');

            // Étape 4: Récupérer les couleurs CSS exactes de la page web
            const computedStyle = getComputedStyle(document.documentElement);
            const cardBg = computedStyle.getPropertyValue('--card-bg').trim() || this.getCSSVariable('--card-bg', '#1a1f2c');
            
            // Étape 5: Forcer l'affichage de tous les éléments avec les styles de la page web
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
                
                // Appliquer les styles de la page web et éliminer les éléments blancs
                if (el.style.display === 'none') el.style.display = 'block';
                el.style.visibility = 'visible';
                el.style.opacity = '1';
                el.style.transform = 'none';
                el.style.transition = 'none';
                el.style.animation = 'none';
                
                // Éliminer tous les backgrounds blancs et transparents gênants
                const lightBg = this.getCSSVariable('--light-bg', '#ffffff');
                if (el.style.backgroundColor === 'white' || 
                    el.style.backgroundColor === '#ffffff' || 
                    el.style.backgroundColor === 'rgba(255,255,255,1)' ||
                    el.style.background === 'white' ||
                    el.style.background === '#ffffff') {
                    el.style.backgroundColor = 'transparent';
                    el.style.background = 'transparent';
                }
                
                // Éliminer les bordures et ombres
                el.style.border = 'none';
                el.style.boxShadow = 'none';
                el.style.outline = 'none';
            });

            // Étape 6: Attendre la stabilisation
            await new Promise(resolve => setTimeout(resolve, 500));

            // Étape 7: Capturer avec html2canvas - Configuration optimisée et stable
            const canvas = await html2canvas(container, {
                backgroundColor: cardBg,
                scale: 2, // ✨ Résolution double - Bon compromis entre qualité et stabilité
                logging: false,
                useCORS: true,
                allowTaint: true,
                foreignObjectRendering: false, // ✨ Évite les erreurs de clonage
                removeContainer: false, // ✨ Ne pas supprimer le container original
                imageTimeout: 10000, // ✨ Timeout plus long pour les images
                width: 800,
                height: container.scrollHeight,
                ignoreElements: function(element) {
                    // ✨ Ignorer les éléments problématiques
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
                        
                        // Nettoyer seulement les éléments essentiels
                        const problematicElements = clonedDoc.querySelectorAll('iframe, .loading-spinner, .is-hidden');
                        problematicElements.forEach(el => {
                            if (el.parentNode) {
                                el.parentNode.removeChild(el);
                            }
                        });
                        
                    } catch (cloneError) {
                        // Erreur silencieuse
                        // Ne pas faire échouer la capture pour des erreurs de clonage mineures
                    }
                }
            });

            // Étape 8: Restaurer les styles originaux
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

            // Étape 9: Désactiver le mode export
            container.classList.remove('export-mode');

            // Étape 10: Restaurer les graphiques Chart.js
            await this.restoreChartsFromImages();

            // Télécharger l'image avec les dimensions correctes
            const link = document.createElement('a');
            link.download = `infographie_${this.currentData.filters.zone}_${this.currentData.filters.period}_${this.currentData.filters.year}.png`;
            link.href = canvas.toDataURL('image/png', 1.0);
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

        } catch (error) {
            // Erreur silencieuse
            this.showError('Erreur lors du téléchargement de l\'infographie');
        } finally {
            // Masquer l'indicateur de chargement pour le téléchargement
            this.hideDownloadLoadingIndicator();
            
            downloadBtn.disabled = false;
            downloadBtn.innerHTML = `
                <div class="btn-icon">
                    <i class="fa-solid fa-download"></i>
                </div>
                <div class="btn-content">
                    <span class="btn-title">Télécharger</span>
                    <span class="btn-subtitle">Image HD</span>
                </div>
            `;
        }
    }

    async convertChartsToImages() {
        // Récupérer tous les canvas Chart.js
        const canvases = document.querySelectorAll('.infographie-content canvas');
        this.chartImages = new Map();

        for (const canvas of canvases) {
            try {
                // Convertir le canvas en image HAUTE RÉSOLUTION
                const imageDataUrl = canvas.toDataURL('image/png', 1.0);
                
                // Créer un élément img avec les mêmes dimensions
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
                    // Remettre le canvas à sa place originale
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
                    if (progress > 90) progress = 90; // Ne pas aller à 100% avant la fin
                    progressBar.style.width = progress + '%';
                }, 200);
                
                // Stocker l'interval pour le nettoyer plus tard
                this.loadingProgressInterval = interval;
            }
        }
    }

    // Mettre à jour le texte de l'indicateur de chargement
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
        
        // Masquer après un court délai pour montrer la progression complète
        setTimeout(() => {
            if (loadingElement) {
                loadingElement.classList.remove('active');
            }
            
            // Réinitialiser la barre de progression
            if (progressBar) {
                progressBar.style.width = '0%';
            }
        }, 300);
    }

    // Afficher l'indicateur de chargement pour le téléchargement
    showDownloadLoadingIndicator() {
        const loadingElement = document.getElementById('infographie-loading');
        const loadingText = loadingElement?.querySelector('.infographie-loading-text');
        const loadingSubtext = loadingElement?.querySelector('.infographie-loading-subtext');
        
        if (loadingElement) {
            // Changer les textes pour le téléchargement
            if (loadingText) loadingText.textContent = 'Préparation du téléchargement...';
            if (loadingSubtext) loadingSubtext.textContent = 'Conversion des graphiques et génération de l\'image';
            
            loadingElement.classList.add('active');
        }
    }

    // Masquer l'indicateur de chargement pour le téléchargement
    hideDownloadLoadingIndicator() {
        const loadingElement = document.getElementById('infographie-loading');
        const loadingText = loadingElement?.querySelector('.infographie-loading-text');
        const loadingSubtext = loadingElement?.querySelector('.infographie-loading-subtext');
        
        // Restaurer les textes par défaut
        if (loadingText) loadingText.textContent = 'Génération de l\'infographie...';
        if (loadingSubtext) loadingSubtext.textContent = 'Chargement des données et création des graphiques';
        
        // Masquer l'indicateur
        if (loadingElement) {
            loadingElement.classList.remove('active');
        }
    }

    showError(message) {
        // Afficher un message d'erreur (à adapter selon votre système de notifications)
        // Erreur silencieuse
        // alert(message);
    }

    // Nouvelle méthode pour détruire tous les graphiques
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

    // Méthode pour lire les couleurs depuis les variables CSS
    getCSSVariable(variableName, fallback = '#ffffff') {
        try {
            const value = getComputedStyle(document.documentElement).getPropertyValue(variableName).trim();
            return value || fallback;
        } catch (error) {
            return fallback;
        }
    }

    // Méthode pour obtenir les couleurs du thème
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

    // Utilitaire pour créer des couleurs RGBA avec alpha
    rgba(r, g, b, a) {
        return `rgba(${r}, ${g}, ${b}, ${a})`;
    }

    // Méthode pour extraire les composants RGB d'une couleur hex
    hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    // Méthode pour créer une couleur avec alpha à partir d'une couleur hex
    hexToRgba(hex, alpha) {
        const rgb = this.hexToRgb(hex);
        if (!rgb) return hex;
        return this.rgba(rgb.r, rgb.g, rgb.b, alpha);
    }
    
    // Méthode pour vérifier si l'infographie est prête à être partagée
    isInfographicReady() {
        const shareBtn = document.getElementById('btn-partager-infographie');
        const infographicContainer = document.querySelector('.infographie-container');
        
        return shareBtn && !shareBtn.disabled && 
               infographicContainer && infographicContainer.children.length > 0;
    }


}

// Initialiser l'infographie manager au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    const infographieManager = new InfographieManager();
    
    // Ajouter la fonctionnalité de partage
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
    // Vérifier que l'infographie est prête à être partagée
    if (!infographieManager.isInfographicReady()) {
        alert('Veuillez d\'abord générer une infographie avant de la partager.');
        return;
    }
    
    // Récupérer les paramètres actuels
    const currentParams = {
        year: document.getElementById('exc-year-select')?.value,
        period: document.getElementById('exc-period-select')?.value,
        zone: infographieManager.config.defaultZone,
        customRange: window.infographieCustomDateRange
    };
    
    // Générer un ID unique pour cette infographie
    const uniqueId = generateUniqueId(currentParams);
    
    // Construire l'URL avec les paramètres
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
    
    // Capturer et sauvegarder une prévisualisation de l'infographie
    captureAndSavePreview(uniqueId).then(previewId => {
        if (previewId) {
            params.append('preview_id', previewId);
        }
        
        // Rediriger vers la page de sélection d'espace
        window.location.href = (window.CantalDestinationConfig ? window.CantalDestinationConfig.url('/shared-spaces/select') : '/shared-spaces/select') + `?${params.toString()}`;
    }).catch(error => {
        console.error('Erreur lors de la capture de prévisualisation:', error);
        // Rediriger sans prévisualisation en cas d'erreur
        window.location.href = (window.CantalDestinationConfig ? window.CantalDestinationConfig.url('/shared-spaces/select') : '/shared-spaces/select') + `?${params.toString()}`;
    });
}

/**
 * Capturer et sauvegarder une prévisualisation de l'infographie
 */
async function captureAndSavePreview(uniqueId) {
    try {
        // Charger html2canvas si pas déjà fait
        if (typeof html2canvas === 'undefined') {
            await loadHtml2Canvas();
        }
        
        const infographicContainer = document.querySelector('.infographie-container');
        if (!infographicContainer) {
            throw new Error('Container infographie non trouvé');
        }
        
        // Capturer l'infographie
        const canvas = await html2canvas(infographicContainer, {
            scale: 0.5, // Réduire la qualité pour la prévisualisation
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#ffffff'
        });
        
        const previewDataUrl = canvas.toDataURL('image/png', 0.8);
        
        // Sauvegarder via l'API
        const response = await fetch((window.CantalDestinationConfig ? window.CantalDestinationConfig.url('/api/infographie/preview.php') : '/api/infographie/preview.php'), {
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
        console.error('Erreur capture et sauvegarde prévisualisation:', error);
        return null;
    }
}

/**
 * Capturer une prévisualisation de l'infographie (ancienne version)
 */
async function captureInfographicPreview() {
    try {
        // Charger html2canvas si pas déjà fait
        if (typeof html2canvas === 'undefined') {
            await loadHtml2Canvas();
        }
        
        const infographicContainer = document.querySelector('.infographie-container');
        if (!infographicContainer) {
            throw new Error('Container infographie non trouvé');
        }
        
        // Capturer l'infographie
        const canvas = await html2canvas(infographicContainer, {
            scale: 0.5, // Réduire la qualité pour la prévisualisation
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#ffffff'
        });
        
        return canvas.toDataURL('image/png', 0.8);
    } catch (error) {
        console.error('Erreur capture prévisualisation:', error);
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
 * Générer un ID unique pour l'infographie
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
 * Récupérer le token CSRF depuis la page
 */
function getCSRFToken() {
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    return csrfInput ? csrfInput.value : '';
}
