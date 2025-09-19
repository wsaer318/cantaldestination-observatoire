/**
 * Script JavaScript pour les tables d'indicateurs
 * Basé sur le fonctionnement de tdb_comparaison.js
 */


// État global
let periodesDates = null;
let isLoadingFilters = false;
let isLoadingData = false;
let ficheData = [];

document.addEventListener('DOMContentLoaded', function() {
    initializeIndicatorLabels();
    loadFilters();
    bindFilterEvents();
    initExportPDF();
});

// Chargement des filtres depuis l'API (même logique que tdb_comparaison)
async function loadFilters() {
    if (isLoadingFilters) return;
    isLoadingFilters = true;
    
    
    try {
        // Utiliser la même API que tdb_comparaison
        const response = await fetch(window.getApiUrl('filters_mysql.php'));
        const filtersData = await response.json();
        
        
        // Charger les filtres depuis l'API
        loadYearsFromAPI(filtersData.annees);
        loadPeriodsFromAPI(filtersData.periodes);
        loadZonesFromAPI(filtersData.zones);
        
        // Charger les données des périodes
        await loadPeriodesDates();
        
    } catch (error) {
        console.error('Erreur lors du chargement des filtres depuis l\'API:', error);
        // Fallback vers les valeurs par défaut
        loadDefaultFilters();
        await loadPeriodesDates();
    }
    
    isLoadingFilters = false;
}

function loadYearsFromAPI(apiYears) {
    const yearSelect = document.getElementById('year-select');
    
    if (yearSelect && apiYears && apiYears.length > 0) {
        // Les années viennent déjà triées par ordre décroissant de l'API
        yearSelect.innerHTML = apiYears
            .map(year => `<option value="${year}" ${year == 2024 ? 'selected' : ''}>${year}</option>`)
            .join('');
            
    }
}

function loadPeriodsFromAPI(apiPeriods) {
    const periodSelect = document.getElementById('period-select');
    
    if (periodSelect && apiPeriods && apiPeriods.length > 0) {
        // Utiliser directement les données de l'API filters_mysql.php
        // qui contient déjà les périodes avec leurs codes et labels corrects
        
        // Grouper par code_periode pour éviter les doublons
        const uniquePeriods = [];
        const seenCodes = new Set();
        
        for (const period of apiPeriods) {
            if (!seenCodes.has(period.value)) {
                uniquePeriods.push({
                    value: period.value,
                    label: period.label
                });
                seenCodes.add(period.value);
            }
        }
        
        // Trier par ordre alphabétique des labels pour un tri cohérent
        // L'API retourne déjà les périodes dans un ordre logique
        uniquePeriods.sort((a, b) => {
            // Garder 'annee' en premier si elle existe
            if (a.value === 'annee') return -1;
            if (b.value === 'annee') return 1;
            
            // Sinon, trier par label alphabétique
            return a.label.localeCompare(b.label);
        });
        
        // Sélectionner la première période par défaut ou 'hiver' si disponible
        const defaultPeriod = uniquePeriods.find(p => p.value === 'hiver') ? 'hiver' : uniquePeriods[0]?.value;
        
        periodSelect.innerHTML = uniquePeriods
            .map(period => `<option value="${period.value}" ${period.value === defaultPeriod ? 'selected' : ''}>${period.label}</option>`)
            .join('');
    } else {
        // Fallback minimal si l'API ne répond pas
        console.warn('Aucune période trouvée dans l\'API, utilisation du fallback minimal');
        periodSelect.innerHTML = '<option value="annee" selected>Année complète</option>';
    }
}

function loadZonesFromAPI(apiZones) {
    const zoneSelect = document.getElementById('territory-select');
    
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

    }
}

function loadDefaultFilters() {
    console.warn('Chargement des filtres par défaut - l\'API n\'est pas disponible');
    
    // Années - utiliser une plage raisonnable
    const yearSelect = document.getElementById('year-select');
    if (yearSelect) {
        const currentYear = new Date().getFullYear();
        const years = [];
        for (let year = currentYear - 5; year <= currentYear + 1; year++) {
            years.push(year);
        }
        years.sort((a, b) => b - a); // Plus récent en premier
        
        yearSelect.innerHTML = years
            .map(year => `<option value="${year}" ${year === currentYear ? 'selected' : ''}>${year}</option>`)
            .join('');
    }
    
    // Périodes - fallback minimal
    const periodSelect = document.getElementById('period-select');
    if (periodSelect) {
        periodSelect.innerHTML = '<option value="annee" selected>Année complète</option>';
    }
    
    // Zones - fallback minimal
    const zoneSelect = document.getElementById('territory-select');
    if (zoneSelect) {
        zoneSelect.innerHTML = '<option value="CANTAL" selected>CANTAL</option>';
    }
}

// Chargement des dates des périodes
async function loadPeriodesDates() {
    try {
        const response = await fetch(window.getApiUrl('periodes_dates'));
        const data = await response.json();
        periodesDates = data;
        
        // Attendre que les filtres soient complètement chargés puis appliquer
        setTimeout(() => {
            const waitForFilters = () => {
                const yearSelect = document.getElementById('year-select');
                const periodSelect = document.getElementById('period-select');
                const territorySelect = document.getElementById('territory-select');
                
                if (periodesDates && yearSelect && periodSelect && territorySelect && 
                    yearSelect.options.length > 0 && periodSelect.options.length > 0 && territorySelect.options.length > 0) {
                    applyFilters();
                } else {
                    setTimeout(waitForFilters, 100);
                }
            };
            waitForFilters();
        }, 500);
        
    } catch (error) {
        console.error('Erreur lors du chargement des périodes dates:', error);
        // Continuer quand même
        setTimeout(applyFilters, 1000);
    }
}

// Événements pour les filtres
function bindFilterEvents() {
    const yearSelect = document.getElementById('year-select');
    const periodSelect = document.getElementById('period-select');
    const zoneSelect = document.getElementById('territory-select');
    const applyFilterBtn = document.getElementById('apply-filter');
    const resetFilterBtn = document.getElementById('reset-filter');

    // Événements de changement
    if (yearSelect) yearSelect.addEventListener('change', applyFilters);
    if (periodSelect) periodSelect.addEventListener('change', applyFilters);
    if (zoneSelect) zoneSelect.addEventListener('change', applyFilters);
    if (applyFilterBtn) applyFilterBtn.addEventListener('click', applyFilters);
    if (resetFilterBtn) resetFilterBtn.addEventListener('click', resetFilters);
    
}

// Application des filtres avec les APIs fonctionnelles
function applyFilters() {
    if (isLoadingData) return;
    isLoadingData = true;
    
    
    const yearSelect = document.getElementById('year-select');
    const periodSelect = document.getElementById('period-select');
    const territorySelect = document.getElementById('territory-select');
    
    // Vérifier que tous les éléments existent
    if (!yearSelect || !periodSelect || !territorySelect) {
        setTimeout(applyFilters, 200);
        isLoadingData = false;
        return;
    }
    
    const yearN = yearSelect.value;
    const periodSlug = periodSelect.value;
    const territorySlug = territorySelect.value;
    
    
    // Masquer les messages précédents
    clearAllTables();
    
    // Charger bloc A (données principales) avec l'API fonctionnelle
    fetch(window.getApiUrl(`bloc_a.php?annee=${yearN}&periode=${encodeURIComponent(periodSlug)}&zone=${encodeURIComponent(territorySlug)}`))
        .then(response => response.ok ? response.json() : null)
        .then(dataN => {
            if (dataN && dataN.bloc_a) {
                // Charger N-1 pour les comparaisons
                const yearN1 = parseInt(yearN) - 1;
                return fetch(window.getApiUrl(`bloc_a.php?annee=${yearN1}&periode=${encodeURIComponent(periodSlug)}&zone=${encodeURIComponent(territorySlug)}`))
                    .then(response => response.ok ? response.json() : null)
                    .then(dataN1 => {
                        updateMainTable(dataN.bloc_a, dataN1 ? dataN1.bloc_a : null);
                        return dataN;
                    });
            }
            return dataN;
        })
        .then(dataN => {
            if (dataN && dataN.bloc_a) {
                // Charger les données détaillées avec les mêmes APIs que tdb_comparaison
                loadDetailedData(yearN, periodSlug, territorySlug);
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des données:', error);
            showNoDataMessage();
        })
        .finally(() => {
            isLoadingData = false;
        });
}

// Chargement des données détaillées avec les APIs fonctionnelles
function loadDetailedData(yearN, periodSlug, territorySlug) {
    
    // D1 - Départements (même API que tdb_comparaison)
    fetch(window.getApiUrl(`bloc_d1_cached.php?annee=${yearN}&periode=${encodeURIComponent(periodSlug)}&zone=${encodeURIComponent(territorySlug)}&limit=15`))
        .then(resp => resp.ok ? resp.json() : null)
        .then(dataD1 => {
            if (dataD1 && Array.isArray(dataD1)) {
                updateDetailTableD1(dataD1);
            }
        })
        .catch(error => console.error('Erreur D1:', error));
        
    // D2 - Régions (même API que tdb_comparaison)
    fetch(window.getApiUrl(`bloc_d2_simple.php?annee=${yearN}&periode=${encodeURIComponent(periodSlug)}&zone=${encodeURIComponent(territorySlug)}&limit=5`))
        .then(resp => resp.ok ? resp.json() : null)
        .then(dataD2 => {
            if (dataD2 && Array.isArray(dataD2)) {
                updateDetailTableD2(dataD2);
            }
        })
        .catch(error => console.error('Erreur D2:', error));
        
    // D3 - Pays
    fetch(window.getApiUrl(`bloc_d3_simple.php?annee=${yearN}&periode=${encodeURIComponent(periodSlug)}&zone=${encodeURIComponent(territorySlug)}&limit=5`))
        .then(resp => resp.ok ? resp.json() : null)
        .then(dataD3 => {
            if (dataD3 && Array.isArray(dataD3)) {
                updateDetailTableD3(dataD3);
            }
        })
        .catch(error => console.error('Erreur D3:', error));
        
    // D5 - CSP (utiliser l'API working qui fonctionne)
    fetch(window.getApiUrl(`bloc_a_working.php?annee=${yearN}&periode=${encodeURIComponent(periodSlug)}&zone=${encodeURIComponent(territorySlug)}`))
        .then(resp => resp.ok ? resp.json() : null)
        .then(data => {
            if (data && data.bloc_d5) {
                updateDetailTableD5(data.bloc_d5);
            }
        })
        .catch(error => console.error('Erreur D5:', error));
        
    // D6 - Âge (utiliser l'API working qui fonctionne)
    fetch(window.getApiUrl(`bloc_a_working.php?annee=${yearN}&periode=${encodeURIComponent(periodSlug)}&zone=${encodeURIComponent(territorySlug)}`))
        .then(resp => resp.ok ? resp.json() : null)
        .then(data => {
            if (data && data.bloc_d6) {
                updateDetailTableD6(data.bloc_d6);
            }
        })
        .catch(error => console.error('Erreur D6:', error));
}

// Fonction pour initialiser les libellés des indicateurs avec les titres des fiches
function initializeIndicatorLabels() {
    // Charger les données des fiches depuis l'API du serveur
    fetch(window.getApiUrl('fiches'))
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur lors du chargement des fiches');
            }
            return response.json();
        })
        .then(data => {
            ficheData = data;
            // Initialiser les libellés avec les titres des fiches
            updateIndicatorLabels(data);
        })
        .catch(error => {
            console.error('Erreur lors du chargement des fiches:', error);
        });
}

// Fonction pour mettre à jour les libellés des indicateurs
function updateIndicatorLabels(ficheData) {
        if (!ficheData || ficheData.length === 0) return;

        const mainTable = document.querySelector('#bloc-a .table--indicators tbody');
        if (!mainTable) return;

        const rows = mainTable.querySelectorAll('tr');
        
        rows.forEach((row, index) => {
            const cells = row.querySelectorAll('td');
            const rowNumber = index + 1;
            
            // Chercher la fiche correspondante par ID
            const fiche = ficheData.find(f => f.id === rowNumber.toString());
            
            if (fiche && cells.length > 1) {
                // Remplir la colonne Numéro (première colonne)
                cells[0].textContent = fiche.id;
                // Supprimer le numéro et le point au début du titre (ex: "1. Titre" -> "Titre")
                const titre = fiche.titre.replace(/^\d+\.\s*/, '');
                cells[1].textContent = titre;
            }
        });
    }

    // Gestion de l'export PDF
function initExportPDF() {
    const exportPdfBtn = document.getElementById('export-pdf');
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', function() {
            const element = document.getElementById('tables-content');
            const opt = {
                margin: [10, 10, 10, 10],
                filename: 'tables-indicateurs-fluxvision.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            
            html2pdf().set(opt).from(element).save();
        });
    }
    }
    
    // Mettre à jour le tableau principal Bloc A dynamiquement
    function updateMainTable(blocA, blocA_N1) {
        const mainTable = document.querySelector('#bloc-a .table--indicators tbody');
        if (!blocA || !Array.isArray(blocA)) {
            showNoDataMessage();
            return;
        }
    
        // Charger les fiches pour faire la correspondance titre <-> id
    fetch(window.getApiUrl('fiches'))
            .then(response => response.json())
            .then(fichesData => {
                mainTable.innerHTML = '';
                blocA.forEach((indicateur, i) => {
                    // Exclure l'indicateur numero 16 (Excursionnistes totaux)
                    if (indicateur.numero === 16) return;
                
                    const n1 = blocA_N1 && blocA_N1[i] ? blocA_N1[i].N : '';
                    let delta = '';
                    const noDelta = (
                        indicateur.indicateur && (
                            indicateur.indicateur.startsWith('4. Origine par départements') ||
                            indicateur.indicateur.startsWith('5. Origine par pays') ||
                            indicateur.indicateur.startsWith('6. Origine par régions') ||
                            indicateur.indicateur.startsWith("8. Nuitées par tranche d'âge (Top 3)")
                        )
                    );
                
                    if (!noDelta && blocA_N1 && blocA_N1[i] && typeof indicateur.N === 'number' && typeof blocA_N1[i].N === 'number' && blocA_N1[i].N !== 0) {
                        delta = ((indicateur.N - blocA_N1[i].N) / blocA_N1[i].N * 100).toFixed(1);
                    }
                
                    // Supprimer le numéro et le point au début de l'indicateur
                    const indicateurLabel = (indicateur.indicateur ?? '').replace(/^\d+\.\s*/, '');
                
                    // Trouver la fiche correspondante (après suppression du numéro)
                    const fiche = fichesData.find(f => (f.titre ?? '').replace(/^\d+\.\s*/, '') === indicateurLabel);
                    let indicateurCell = indicateurLabel;
                
                    if (fiche) {
                    indicateurCell = `<a href="${window.location.origin}${CantalDestinationConfig.url('/fiches')}?id=${fiche.id}" class="fiche-link">${indicateurLabel}</a>`;
                    }
                
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${indicateur.numero ?? ''}</td>
                        <td>${indicateurCell}</td>
                        <td>${indicateur.N ?? ''}</td>
                        <td>${n1}</td>
                        <td>${noDelta ? '' : delta}</td>
                        <td>${indicateur.unite ?? ''}</td>
                        <td>FluxVision</td>
                        <td>${indicateur.remarque ?? ''}</td>
                    `;
                    mainTable.appendChild(row);
                });
            });
    }
    
    // Mettre à jour le tableau D1 dynamiquement
    function updateDetailTableD1(data) {
        const table = document.querySelector('#bloc-d1 .table--indicators tbody');
    if (!table) return;
    
        table.innerHTML = '';
    data.forEach((item, index) => {
            if (item.rang === 'Autre') return; // Ignore la ligne Autre
        
        // Calculer le rang automatiquement (index + 1)
        const rang = index + 1;
        
            const row = document.createElement('tr');
            row.innerHTML = `
            <td>${rang}</td>
            <td>${item.nom_departement || item.departement}</td>
                <td>${item.n_nuitees}</td>
            <td>${item.n_nuitees_n1 || ''}</td>
                <td>${item.delta_pct !== null && item.delta_pct !== undefined ? item.delta_pct: ''}</td>
                <td>${item.part_pct !== null && item.part_pct !== undefined ? item.part_pct: ''}</td>
            `;
            table.appendChild(row);
        });
    }
    
    function updateDetailTableD2(data) {
        const table = document.querySelector('#bloc-d2 .table--indicators tbody');
    if (!table) return;
    
        table.innerHTML = '';
    data.forEach((item, index) => {
            if (item.rang === 'Autre') return;
        
        // Calculer le rang automatiquement (index + 1)
        const rang = index + 1;
        
            const row = document.createElement('tr');
            row.innerHTML = `
            <td>${rang}</td>
            <td>${item.nom_region || item.region}</td>
                <td>${item.n_nuitees}</td>
            <td>${item.n_nuitees_n1 || ''}</td>
                <td>${item.delta_pct !== null && item.delta_pct !== undefined ? item.delta_pct: ''}</td>
                <td>${item.part_pct !== null && item.part_pct !== undefined ? item.part_pct: ''}</td>
            `;
            table.appendChild(row);
        });
    }
    
    function updateDetailTableD3(data) {
        const table = document.querySelector('#bloc-d3 .table--indicators tbody');
    if (!table) return;
    
        table.innerHTML = '';
    data.forEach((item, index) => {
            if (item.rang === 'Autre') return;
        
        // Calculer le rang automatiquement (index + 1)
        const rang = index + 1;
        
            const row = document.createElement('tr');
            row.innerHTML = `
            <td>${rang}</td>
            <td>${item.nom_pays || item.pays}</td>
                <td>${item.n_nuitees}</td>
            <td>${item.n_nuitees_n1 || ''}</td>
                <td>${item.delta_pct !== null && item.delta_pct !== undefined ? item.delta_pct: ''}</td>
                <td>${item.part_pct !== null && item.part_pct !== undefined ? item.part_pct: ''}</td>
            `;
            table.appendChild(row);
        });
    }
    
    function updateDetailTableD5(data) {
        const table = document.querySelector('#bloc-d5 .table--indicators tbody');
    if (!table) return;
    
        table.innerHTML = '';
    data.forEach((item, index) => {
            if (item.rang === 'Autre') return;
        
        // Calculer le rang automatiquement (index + 1)
        const rang = index + 1;
        
            const row = document.createElement('tr');
            row.innerHTML = `
            <td>${rang}</td>
                <td>${item.csp}</td>
                <td>${item.n_nuitees}</td>
            <td>${item.n_nuitees_n1 || ''}</td>
                <td>${item.delta_pct !== null && item.delta_pct !== undefined ? item.delta_pct: ''}</td>
                <td>${item.part_pct !== null && item.part_pct !== undefined ? item.part_pct: ''}</td>
            `;
            table.appendChild(row);
        });
    }
    
    function updateDetailTableD6(data) {
        const table = document.querySelector('#bloc-d6 .table--indicators tbody');
    if (!table) return;
    
        table.innerHTML = '';
    data.forEach((item, index) => {
            if (item.rang === 'Autre') return;
        
        // Calculer le rang automatiquement (index + 1)
        const rang = index + 1;
        
            const row = document.createElement('tr');
            row.innerHTML = `
            <td>${rang}</td>
                <td>${item.age}</td>
                <td>${item.n_nuitees}</td>
            <td>${item.n_nuitees_n1 || ''}</td>
                <td>${item.delta_pct !== null && item.delta_pct !== undefined ? item.delta_pct: ''}</td>
                <td>${item.part_pct !== null && item.part_pct !== undefined ? item.part_pct: ''}</td>
            `;
            table.appendChild(row);
        });
    }
    
    function resetFilters() {
    // Réinitialiser les filtres aux valeurs par défaut
    const yearSelect = document.getElementById('year-select');
    const periodSelect = document.getElementById('period-select');
    const zoneSelect = document.getElementById('territory-select');
    
    if (yearSelect) yearSelect.selectedIndex = 0;
    if (periodSelect) periodSelect.selectedIndex = 0;
    if (zoneSelect) zoneSelect.selectedIndex = 0;
    
    // Réappliquer les filtres
    applyFilters();
}

    function clearAllTables() {
    // Effacer tous les tableaux
    const tables = document.querySelectorAll('.table--indicators tbody');
    tables.forEach(table => {
        table.innerHTML = '<tr><td colspan="8">Chargement...</td></tr>';
    });
}

    function showNoDataMessage() {
    const tables = document.querySelectorAll('.table--indicators tbody');
    tables.forEach(table => {
        table.innerHTML = '<tr><td colspan="8">Aucune donnée disponible pour cette sélection</td></tr>';
    });
} 