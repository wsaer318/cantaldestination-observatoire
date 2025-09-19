// Dashboard D3.js - Filtres dynamiques et import automatique des données (multi-blocs)

document.addEventListener('DOMContentLoaded', function() {
    // Références DOM
    const yearSelect = document.getElementById('year-select');
    const periodSelect = document.getElementById('period-select');
    const zoneSelect = document.getElementById('zone-select');
    const applyBtn = document.getElementById('apply-filter');
    const resetBtn = document.getElementById('reset-filter');
    const dashboardDiv = document.getElementById('dashboard');

    // Stockage des données chargées
    let dashboardData = {
        bloc_a: null,
        bloc_a_n: null,
        bloc_d1: null,
        bloc_d2: null,
        bloc_d3: null,
        bloc_d5: null,
        bloc_d6: null
    };

    // Charger les options de filtres depuis l'API
            fetch(window.getApiUrl('filters'))
        .then(r => r.json())
        .then(data => {
            // Années
            yearSelect.innerHTML = '';
            data.annees.forEach(annee => {
                const opt = document.createElement('option');
                opt.value = annee;
                opt.textContent = annee;
                yearSelect.appendChild(opt);
            });
            // Périodes
            periodSelect.innerHTML = '';
            data.periodes.forEach(periode => {
                const opt = document.createElement('option');
                opt.value = periode;
                opt.textContent = periode;
                periodSelect.appendChild(opt);
            });
            // Zones
            zoneSelect.innerHTML = '';
            data.zones.forEach(zone => {
                const opt = document.createElement('option');
                opt.value = zone;
                opt.textContent = zone;
                zoneSelect.appendChild(opt);
            });
            // Charger le dashboard avec les valeurs par défaut
            updateDashboard();
        });

    // Fonction pour charger toutes les données nécessaires au dashboard
    function updateDashboard() {
        const annee = yearSelect.value;
        const annee_n = yearSelect.value - 1;
        const periode = periodSelect.value;
        const zone = zoneSelect.value;
        dashboardDiv.innerHTML = '';
        if (!annee || !periode || !zone) {
            dashboardDiv.textContent = 'Veuillez sélectionner tous les filtres.';
            return;
        }
        // Préparer les URLs
        const urls = {
            bloc_a: `/api/bloc_a?annee=${annee}&periode=${encodeURIComponent(periode)}&zone=${encodeURIComponent(zone)}`,
            bloc_a_n: `/api/bloc_a?annee=${annee_n}&periode=${encodeURIComponent(periode)}&zone=${encodeURIComponent(zone)}`,
            bloc_d1: `/api/bloc_d1?annee=${annee}&periode=${encodeURIComponent(periode)}&zone=${encodeURIComponent(zone)}`,
            bloc_d2: `/api/bloc_d2?annee=${annee}&periode=${encodeURIComponent(periode)}&zone=${encodeURIComponent(zone)}`,
            bloc_d3: `/api/bloc_d3?annee=${annee}&periode=${encodeURIComponent(periode)}&zone=${encodeURIComponent(zone)}`,
            bloc_d5: `/api/bloc_d5?annee=${annee}&periode=${encodeURIComponent(periode)}&zone=${encodeURIComponent(zone)}`,
            bloc_d6: `/api/bloc_d6?annee=${annee}&periode=${encodeURIComponent(periode)}&zone=${encodeURIComponent(zone)}`
        };
        // Charger toutes les données en parallèle
        Promise.all([
            fetch(urls.bloc_a).then(r => r.ok ? r.json() : null),
            fetch(urls.bloc_a_n).then(r => r.ok ? r.json() : null),
            fetch(urls.bloc_d1).then(r => r.ok ? r.json() : null),
            fetch(urls.bloc_d2).then(r => r.ok ? r.json() : null),
            fetch(urls.bloc_d3).then(r => r.ok ? r.json() : null),
            fetch(urls.bloc_d5).then(r => r.ok ? r.json() : null),
            fetch(urls.bloc_d6).then(r => r.ok ? r.json() : null)
        ]).then(([dataA, dataA_n, dataD1, dataD2, dataD3, dataD5, dataD6]) => {
            dashboardData.bloc_a = dataA && dataA.bloc_a ? dataA.bloc_a : null;
            dashboardData.bloc_a_n = dataA_n && dataA_n.bloc_a ? dataA_n.bloc_a : null;
            dashboardData.bloc_d1 = dataD1 && dataD1.bloc_d1 ? dataD1.bloc_d1 : null;
            dashboardData.bloc_d2 = dataD2 && dataD2.bloc_d2 ? dataD2.bloc_d2 : null;
            dashboardData.bloc_d3 = dataD3 && dataD3.bloc_d3 ? dataD3.bloc_d3 : null;
            dashboardData.bloc_d5 = dataD5 && dataD5.bloc_d5 ? dataD5.bloc_d5 : null;
            dashboardData.bloc_d6 = dataD6 && dataD6.bloc_d6 ? dataD6.bloc_d6 : null;

            // Conteneur pour les KPI principaux
            const kpiRow = document.createElement('div');
            kpiRow.className = 'kpi-row';
            // Affichage créatif du premier indicateur (KPI)
            if (dashboardData.bloc_a && dashboardData.bloc_a.length > 0) {
                const indic = dashboardData.bloc_a[0];
                let prevValue = null;
                let variation = null;
                if (dashboardData.bloc_a_n && dashboardData.bloc_a_n.length > 0) {
                    prevValue = dashboardData.bloc_a_n[0].N;
                    if (typeof prevValue === 'number' && typeof indic.N === 'number' && prevValue !== 0) {
                        variation = ((indic.N - prevValue) / prevValue * 100).toFixed(1);
                    }
                }
                let variationHtml = '';
                if (variation !== null) {
                    const isPositive = parseFloat(variation) >= 0;
                    variationHtml = `
                        <div class="kpi-variation ${isPositive ? 'positive' : 'negative'}">
                            <i class="fas fa-arrow-${isPositive ? 'up' : 'down'}"></i>
                            ${isPositive ? '+' : ''}${variation}%
                        </div>
                    `;
                }
                let prevHtml = '';
                if (prevValue !== null) {
                    prevHtml = `<div class="kpi-prev">${prevValue.toLocaleString('fr-FR')} <span class="kpi-prev-label">Année précédente</span></div>`;
                }
                const kpiCard = document.createElement('div');
                kpiCard.className = 'kpi-card kpi-main';
                kpiCard.innerHTML = `
                    <div class="kpi-icon"><i class="fas fa-bed"></i></div>
                    <div class="kpi-value">${indic.N.toLocaleString('fr-FR')}</div>
                    ${variationHtml}
                    ${prevHtml}
                    <div class="kpi-label">${indic.indicateur.replace(/^[0-9]+\.\s*/, '')}</div>
                    <div class="kpi-unit">${indic.unite}</div>
                `;
                kpiRow.appendChild(kpiCard);
            }
            // Affichage créatif du deuxième indicateur (KPI secondaire)
            if (dashboardData.bloc_a && dashboardData.bloc_a.length > 1) {
                const indic2 = dashboardData.bloc_a[1];
                let prevValue2 = null;
                let variation2 = null;
                if (dashboardData.bloc_a_n && dashboardData.bloc_a_n.length > 1) {
                    prevValue2 = dashboardData.bloc_a_n[1].N;
                    if (typeof prevValue2 === 'number' && typeof indic2.N === 'number' && prevValue2 !== 0) {
                        variation2 = ((indic2.N - prevValue2) / prevValue2 * 100).toFixed(1);
                    }
                }
                let variationHtml2 = '';
                if (variation2 !== null) {
                    const isPositive2 = parseFloat(variation2) >= 0;
                    variationHtml2 = `
                        <div class="kpi-variation ${isPositive2 ? 'positive' : 'negative'}">
                            <i class="fas fa-arrow-${isPositive2 ? 'up' : 'down'}"></i>
                            ${isPositive2 ? '+' : ''}${variation2}%
                        </div>
                    `;
                }
                let prevHtml2 = '';
                if (prevValue2 !== null) {
                    prevHtml2 = `<div class="kpi-prev">${prevValue2.toLocaleString('fr-FR')} <span class="kpi-prev-label">Année précédente</span></div>`;
                }
                const kpiCard2 = document.createElement('div');
                kpiCard2.className = 'kpi-card kpi-main';
                kpiCard2.innerHTML = `
                    <div class="kpi-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="kpi-value">${indic2.N.toLocaleString('fr-FR')}</div>
                    ${variationHtml2}
                    ${prevHtml2}
                    <div class="kpi-label">${indic2.indicateur.replace(/^[0-9]+\.\s*/, '')}</div>
                    <div class="kpi-unit">${indic2.unite}</div>
                `;
                kpiRow.appendChild(kpiCard2);
            }
            // Affichage créatif du troisième indicateur (KPI tertiaire)
            if (dashboardData.bloc_a && dashboardData.bloc_a.length > 2) {
                const indic3 = dashboardData.bloc_a[2];
                let prevValue3 = null;
                let variation3 = null;
                if (dashboardData.bloc_a_n && dashboardData.bloc_a_n.length > 2) {
                    prevValue3 = dashboardData.bloc_a_n[2].N;
                    if (typeof prevValue3 === 'number' && typeof indic3.N === 'number' && prevValue3 !== 0) {
                        variation3 = ((indic3.N - prevValue3) / prevValue3 * 100).toFixed(1);
                    }
                }
                let variationHtml3 = '';
                if (variation3 !== null) {
                    const isPositive3 = parseFloat(variation3) >= 0;
                    variationHtml3 = `
                        <div class="kpi-variation ${isPositive3 ? 'positive' : 'negative'}">
                            <i class="fas fa-arrow-${isPositive3 ? 'up' : 'down'}"></i>
                            ${isPositive3 ? '+' : ''}${variation3}%
                        </div>
                    `;
                }
                let prevHtml3 = '';
                if (prevValue3 !== null) {
                    prevHtml3 = `<div class="kpi-prev">${prevValue3.toLocaleString('fr-FR')} <span class="kpi-prev-label">Année précédente</span></div>`;
                }
                const kpiCard3 = document.createElement('div');
                kpiCard3.className = 'kpi-card kpi-main kpi-circle';
                kpiCard3.innerHTML = `
                    <div class="kpi-icon"><i class="fas fa-globe-europe"></i></div>
                    <div class="kpi-value">${indic3.N.toLocaleString('fr-FR')}</div>
                    ${variationHtml3}
                    ${prevHtml3}
                    <div class="kpi-label">${indic3.indicateur.replace(/^[0-9]+\.\s*/, '')}</div>
                    <div class="kpi-unit">${indic3.unite}</div>
                `;
                kpiRow.appendChild(kpiCard3);
            }
            dashboardDiv.appendChild(kpiRow);

            // Barplot D1 – Origine par départements (Top 5)
            if (dashboardData.bloc_d1 && Array.isArray(dashboardData.bloc_d1)) {
                // Prendre les 5 premiers (hors "Autre")
                const top5 = dashboardData.bloc_d1.filter(d => d.rang !== 'Autre').slice(0, 5);
                // Créer le conteneur
                const barplotContainer = document.createElement('div');
                barplotContainer.id = 'barplot-d1';
                barplotContainer.style.width = '100%';
                barplotContainer.style.maxWidth = '700px';
                barplotContainer.style.margin = '3rem auto 2rem auto';
                barplotContainer.style.background = 'rgba(255,255,255,0.85)';
                barplotContainer.style.borderRadius = '1.5rem';
                barplotContainer.style.boxShadow = '0 8px 32px 0 rgba(161,196,253,0.18), 0 0 0 6px #fcb69f22';
                barplotContainer.style.padding = '2.2rem 2.2rem 1.5rem 2.2rem';
                barplotContainer.style.position = 'relative';
                barplotContainer.innerHTML = `<h3 style="text-align:center;font-family:'Playfair Display',serif;font-size:1.5rem;color:#181818;margin-bottom:2rem;letter-spacing:0.01em;">D1 – Origine par départements (Top 5)</h3><svg width='100%' height='320'></svg>`;
                dashboardDiv.appendChild(barplotContainer);
                // D3.js barplot
                setTimeout(() => {
                    const svg = d3.select('#barplot-d1 svg');
                    svg.selectAll('*').remove();
                    const width = barplotContainer.offsetWidth;
                    const height = 260;
                    svg.attr('width', width).attr('height', height);
                    const margin = {top: 10, right: 20, bottom: 40, left: 200};
                    const plotW = width - margin.left - margin.right;
                    const plotH = height - margin.top - margin.bottom;
                    // Scales
                    const x = d3.scaleLinear()
                        .domain([0, d3.max(top5, d => d.n_nuitees)])
                        .range([0, plotW]);
                    const y = d3.scaleBand()
                        .domain(top5.map(d => d.departement))
                        .range([0, plotH])
                        .padding(0.22);
                    // Color scale
                    const color = d3.scaleLinear()
                        .domain([0, top5.length-1])
                        .range(['#36d1c4', '#ff6a88']);
                    // Group
                    const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);
                    // Bars
                    g.selectAll('rect')
                        .data(top5)
                        .enter()
                        .append('rect')
                        .attr('x', 0)
                        .attr('y', d => y(d.departement))
                        .attr('height', y.bandwidth())
                        .attr('width', 0)
                        .attr('rx', 12)
                        .attr('fill', (d,i) => color(i))
                        .style('filter', 'drop-shadow(0 2px 12px #a1c4fd44)')
                        .transition()
                        .duration(900)
                        .attr('width', d => x(d.n_nuitees));
                    // Dept labels
                    g.selectAll('text.label')
                        .data(top5)
                        .enter()
                        .append('text')
                        .attr('class', 'label')
                        .attr('x', -16)
                        .attr('y', d => y(d.departement) + y.bandwidth()/2 + 4)
                        .attr('text-anchor', 'end')
                        .attr('font-family', 'Raleway, sans-serif')
                        .attr('font-size', '1.1rem')
                        .attr('fill', '#181818')
                        .attr('font-weight', 700)
                        .text(d => d.departement);
                    // Value labels
                    g.selectAll('text.value')
                        .data(top5)
                        .enter()
                        .append('text')
                        .attr('class', 'value')
                        .attr('x', d => x(d.n_nuitees) + 12)
                        .attr('y', d => y(d.departement) + y.bandwidth()/2 + 4)
                        .attr('font-family', 'Raleway, sans-serif')
                        .attr('font-size', '1.05rem')
                        .attr('fill', '#36d1c4')
                        .attr('font-weight', 700)
                        .text(d => d.n_nuitees.toLocaleString('fr-FR'));
                    // X axis
                    g.append('g')
                        .attr('transform', `translate(0,${plotH})`)
                        .call(d3.axisBottom(x).ticks(5).tickFormat(d3.format(',')))
                        .selectAll('text')
                        .attr('font-size', '0.95rem')
                        .attr('fill', '#888');
                }, 0);
            }
        }).catch(err => {
            dashboardDiv.textContent = 'Erreur lors du chargement des données.';
            console.error(err);
        });
    }

    // Rafraîchir le dashboard lors du clic sur "Appliquer les filtres"
    applyBtn.addEventListener('click', updateDashboard);
    // Réinitialiser les filtres (remettre la première valeur de chaque select)
    resetBtn.addEventListener('click', function() {
        if (yearSelect.options.length) yearSelect.selectedIndex = 0;
        if (periodSelect.options.length) periodSelect.selectedIndex = 0;
        if (zoneSelect.options.length) zoneSelect.selectedIndex = 0;
        updateDashboard();
    });
});

// Style KPI créatif (à ajouter dans le CSS si besoin)
// .kpi-card.kpi-main { background: #fffbe6; border-radius: 18px; box-shadow: 0 4px 24px #f1c40f44; padding: 2rem 2.5rem; display: flex; flex-direction: column; align-items: center; margin: 2rem auto; max-width: 350px; }
// .kpi-card .kpi-icon { font-size: 3rem; color: #F1C40F; margin-bottom: 1rem; }
// .kpi-card .kpi-value { font-size: 2.8rem; font-weight: bold; color: #222; }
// .kpi-card .kpi-label { font-size: 1.1rem; color: #666; margin-top: 0.5rem; text-align: center; }
// .kpi-card .kpi-unit { font-size: 1rem; color: #aaa; margin-top: 0.2rem; } 