/**
 * Fichier : fiches.js
 * Description : Gestion des fiches de données
 */


// Variables globales
let fichesData = [];
let currentFicheId = null;

// Helper pour convertir Δ% et Part% en HTML fractionnel avant la virgule
function wrapFormula(text) {
  // Regex générique : prefix = formula (numer/denom × mult), puis suffixe
  const regex = /^\s*(?<prefix>[^=]+)=\s*(?<formula>[^,]+)(?<suffix>,.*)?$/;
  const match = text.match(regex);
  if (match) {
    const { prefix, formula, suffix } = match.groups;
    // Extraire numérateur, dénominateur, multiplicateur
    const m = formula.match(/(.+?)\s*\/\s*(.+?)\s*×\s*(.+)/);
    if (m) {
      let numer = m[1].trim();
      let denom = m[2].trim();
      const mult = m[3].trim();
      // Retirer les parenthèses si présentes
      numer = numer.replace(/^\(+/, '').replace(/\)+$/, '');
      denom = denom.replace(/^\(+/, '').replace(/\)+$/, '');
      const fracHTML = `<span class="fraction new-fraction"><span class="num">${numer}</span><span class="den">${denom}</span></span> <span class="fraction-mult">×</span> ${mult}`;
      // Inclure le suffixe dans le conteneur formula mais avec une classe spécifique
      const suffixHTML = suffix ? `<span class="formula-suffix">${suffix}</span>` : '';
      return `<span class="formula shine-effect">${prefix.trim()} = ${fracHTML}${suffixHTML}</span>`;
    }
  }
  return text;
}

// Fonction de chargement des données au démarrage
document.addEventListener('DOMContentLoaded', () => {
    // Charger les données JSON
    fetchFichesData();
    
    // Utiliser les fonctions communes depuis utils.js
    utils.animateEntrance();
    utils.initScrollAnimations();
    
    // Ajouter des animations aléatoires aux éléments décoratifs
    animateDecorativeElements();
    
    // Gestionnaire d'événements global pour les animations au survol
    document.body.addEventListener('mouseover', utils.handleHoverAnimations);
    
    // Initialise tsParticles avec la fonction commune
    utils.initParticles();
});

// Fonction pour animer les éléments décoratifs
function animateDecorativeElements() {
    // Ajouter l'effet de brillance aux titres et boutons
    document.querySelectorAll('.fiche-title, .btn, .destination-badge').forEach(el => {
        el.classList.add('shine-effect');
    });
    
    // Animation subtile et continue des formes décoratives
    setInterval(() => {
        document.querySelectorAll('.decorative-element, .accent-shape').forEach(shape => {
            const randomX = Math.random() * 10 - 5;
            const randomY = Math.random() * 10 - 5;
            const randomRotate = Math.random() * 10 - 5;
            
            shape.style.transition = 'all 5s cubic-bezier(0.34, 1.56, 0.64, 1)';
            shape.style.transform = `translate(${randomX}px, ${randomY}px) rotate(${randomRotate}deg)`;
        });
    }, 5000);
}

// Fonction pour ajouter un effet de chargement lors des transitions entre fiches
function showLoadingEffect() {
    const ficheDetails = document.getElementById('fiche-details');
    ficheDetails.classList.add('content-loading');
    
    setTimeout(() => {
        ficheDetails.classList.remove('content-loading');
    }, 800);
}

// Fonction pour récupérer les données JSON
function fetchFichesData() {
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'loading-indicator';
    loadingIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chargement des fiches...';
    const fichesList = document.getElementById('fiches-list');
    if (fichesList) {
        fichesList.appendChild(loadingIndicator);
    }
    fetch(window.getApiUrl('fiches'))
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur lors du chargement des données');
            }
            return response.json();
        })
        .then(data => {
            fichesData = data;
            populateFichesList(data);
        })
        .catch(error => {
            console.error('Erreur:', error);
            if (fichesList) {
                fichesList.innerHTML = `
                    <li class="error"><i class="fas fa-exclamation-triangle"></i> Erreur de chargement des données</li>
                `;
            }
        });
}

// Fonction pour peupler la liste des fiches
function populateFichesList(fiches) {
    const listContainer = document.getElementById('fiches-list');
    listContainer.innerHTML = '';
    
    // Trier les fiches par ID numérique
    fiches.sort((a, b) => {
        const idA = parseInt(a.id);
        const idB = parseInt(b.id);
        return idA - idB;
    });
    
    fiches.forEach((fiche, index) => {
        const listItem = document.createElement('li');
        listItem.textContent = fiche.titre;
        listItem.dataset.id = fiche.id;
        
        // Ajouter un délai d'animation pour chaque élément
        listItem.style.opacity = '0';
        listItem.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            listItem.style.transition = 'all 0.5s cubic-bezier(0.19, 1, 0.22, 1)';
            listItem.style.opacity = '1';
            listItem.style.transform = 'translateY(0)';
        }, 50 * index);
        
        listItem.addEventListener('click', () => {
            showLoadingEffect();
            setTimeout(() => displayFicheDetails(fiche.id), 300);
        });
        listContainer.appendChild(listItem);
    });
    
    // Sélectionner la fiche correspondant à l'id de l'URL si présent
    const urlParams = new URLSearchParams(window.location.search);
    const ficheIdFromUrl = urlParams.get('id');
    if (ficheIdFromUrl && fiches.some(f => f.id === ficheIdFromUrl)) {
        displayFicheDetails(ficheIdFromUrl);
    } else if (fiches.length > 0) {
        displayFicheDetails(fiches[0].id);
    }
}

// Fonction pour afficher les détails d'une fiche
function displayFicheDetails(ficheId) {
    // Mise à jour de la fiche active
    currentFicheId = ficheId;
    
    // Mise à jour du style dans la liste
    const listItems = document.querySelectorAll('#fiches-list li');
    listItems.forEach(item => {
        if (item.dataset.id === ficheId) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
    
    // Récupérer les données de la fiche
    const fiche = fichesData.find(f => f.id === ficheId);
    if (!fiche) {
        console.error(`Fiche avec l'ID "${ficheId}" non trouvée`);
        return;
    }
    
    // Vérifier si la fiche a des sections
    if (!fiche.sections || !Array.isArray(fiche.sections)) {
        console.error(`La fiche "${ficheId}" n'a pas de sections valides:`, fiche);
        document.getElementById('fiche-details').innerHTML = '<div class="error">Cette fiche ne contient pas de sections valides.</div>';
        return;
    }
    
    // Masquer le contenu actuel avec une animation
    const ficheDetailsContainer = document.getElementById('fiche-details');
    ficheDetailsContainer.style.opacity = '0';
    ficheDetailsContainer.style.transform = 'translateY(10px)';
    
    setTimeout(() => {
        // Construire le HTML de la fiche
        let ficheHTML = `
            <h2 class="fiche-title">${fiche.titre}</h2>
        `;
        
        // Parcourir les sections
        fiche.sections.forEach(section => {
            ficheHTML += `
                <h3 class="section-title">${section.sous_titre}</h3>
                <div class="section-content">
                    ${renderSectionContent(section)}
                </div>
            `;
        });
        
        ficheDetailsContainer.innerHTML = ficheHTML;
        
        // Appliquer le rendu KaTeX aux formules LaTeX dynamiques
        if (window.renderMathInElement) {
          renderMathInElement(ficheDetailsContainer, {
            delimiters: [
              { left: '$', right: '$', display: false },
              { left: '\\(', right: '\\)', display: false }
            ]
          });
        }
        
        // Afficher le nouveau contenu avec animation
        ficheDetailsContainer.style.transition = 'all 0.4s ease-out';
        ficheDetailsContainer.style.opacity = '1';
        ficheDetailsContainer.style.transform = 'translateY(0)';
        
        // Ajouter des animations aux sections
        animateSections();
    }, 200);
}

// Fonction pour animer les sections lors du chargement
function animateSections() {
    const sections = document.querySelectorAll('.section-content');
    
    sections.forEach((section, index) => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            section.style.transition = 'all 0.4s ease-out';
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
        }, 100 + (index * 100));
    });
}

// Fonction pour générer le contenu HTML d'une section selon son type
function renderSectionContent(section) {
    switch (section.type) {
        case 'paragraphe':
            return `<div class="paragraphe">${wrapFormula(section.contenu)}</div>`;
            
        case 'tableau':
            return renderTableau(section);
            
        case 'liste_ordonnee':
            return renderListeOrdonnee(section.contenu);
            
        case 'liste_non_ordonnee':
            return renderListeNonOrdonnee(section.contenu);
            
        case 'remarque':
            return `<div class="remarque">${section.contenu}</div>`;
            
        case 'description_tableau':
            return renderDescriptionTableau(section);
            
        case 'bloc_tableau':
            return renderBlocTableau(section);
            
        default:
            return `<div>${JSON.stringify(section.contenu)}</div>`;
    }
}

// Fonction pour générer un tableau HTML
function renderTableau(section) {
    let html = '<table>';
    
    // En-têtes
    html += '<thead><tr>';
    section.headers.forEach(header => {
        html += `<th>${header}</th>`;
    });
    html += '</tr></thead>';
    
    // Corps du tableau
    html += '<tbody>';
    section.contenu.forEach(row => {
        html += '<tr>';
        if (typeof row === 'object') {
            section.headers.forEach(header => {
                html += `<td>${row[header] || ''}</td>`;
            });
        } else {
            html += `<td colspan="${section.headers.length}">${row}</td>`;
        }
        html += '</tr>';
    });
    html += '</tbody>';
    
    html += '</table>';
    return html;
}

// Fonction pour générer une liste ordonnée
function renderListeOrdonnee(items) {
    let html = '<ol>';
    
    items.forEach(item => {
        if (typeof item === 'object' && item.type === 'liste_non_ordonnee') {
            html = html.replace(/<\/li>$/, '');
            html += renderListeNonOrdonnee(item.contenu);
            html += '</li>';
        } else {
            html += `<li>${wrapFormula(item)}</li>`;
        }
    });
    
    html += '</ol>';
    return html;
}

// Fonction pour générer une liste non ordonnée
function renderListeNonOrdonnee(items) {
    let html = '<ul class="nested-list">';
    
    items.forEach(item => {
        html += `<li>${wrapFormula(item)}</li>`;
    });
    
    html += '</ul>';
    return html;
}

// Fonction pour générer un tableau avec description
function renderDescriptionTableau(section) {
    let html = `<p>${section.description || ''}</p>`;
    
    html += '<table>';
    
    // En-têtes
    html += '<thead><tr>';
    section.headers.forEach(header => {
        html += `<th>${header}</th>`;
    });
    html += '</tr></thead>';
    
    // Note si présente
    if (section.note) {
        html += `<tfoot><tr><td colspan="${section.headers.length}" class="table-note">${section.note}</td></tr></tfoot>`;
    }
    
    html += '</table>';
    return html;
}

// Fonction pour générer un bloc tableau
function renderBlocTableau(section) {
    let html = `<p><strong>${section.bloc}</strong></p>`;
    
    html += '<table>';
    
    // En-têtes
    html += '<thead><tr>';
    section.headers.forEach(header => {
        html += `<th>${header}</th>`;
    });
    html += '</tr></thead>';
    
    // Corps du tableau
    html += '<tbody>';
    section.contenu.forEach(row => {
        html += '<tr>';
        section.headers.forEach(header => {
            html += `<td>${row[header] || ''}</td>`;
        });
        html += '</tr>';
    });
    html += '</tbody>';
    
    // Note si présente
    if (section.note) {
        html += `<tfoot><tr><td colspan="${section.headers.length}" class="table-note">${section.note}</td></tr></tfoot>`;
    }
    
    // Tableau détaillé si présent
    if (section.tableau_detaille) {
        html += `<p class="table-subtitle">${section.note || 'Détail :'}</p>`;
        html += '<table class="subtable">';
        
        // En-têtes du sous-tableau
        html += '<thead><tr>';
        section.tableau_detaille.headers.forEach(header => {
            html += `<th>${header}</th>`;
        });
        html += '</tr></thead>';
        
        // Corps du sous-tableau
        html += '<tbody>';
        section.tableau_detaille.contenu.forEach(row => {
            html += '<tr>';
            section.tableau_detaille.headers.forEach(header => {
                html += `<td>${row[header] || ''}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody>';
        
        html += '</table>';
    }
    
    html += '</table>';
    return html;
}