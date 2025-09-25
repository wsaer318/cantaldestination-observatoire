/**
 * JavaScript pour la page de sélection d'espace avec prévisualisation
 */

// Variables globales
let selectedSpaceId = null;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
    setupEventListeners();
});

/**
 * Initialiser la page
 */
function initializePage() {
    window.fvLog('Page de sélection d\'espace initialisée');
    window.fvLog('Paramètres infographie:', ShareConfig.infographicParams);
    
    // Activer le bouton de partage si un espace est déjà sélectionné
    updateShareButton();
}

/**
 * Configurer les écouteurs d'événements
 */
function setupEventListeners() {
    // Écouteurs pour la sélection d'espace
    const spaceRadios = document.querySelectorAll('input[name="selected_space"]');
    spaceRadios.forEach(radio => {
        radio.addEventListener('change', handleSpaceSelection);
    });
    
    // Écouteur pour le formulaire de partage
    const shareForm = document.getElementById('share-form');
    if (shareForm) {
        shareForm.addEventListener('submit', handleShareSubmit);
    }
    
    // Écouteurs pour les champs de formulaire
    const titleInput = document.getElementById('infographic-title');
    const descriptionInput = document.getElementById('infographic-description');
    
    if (titleInput) {
        titleInput.addEventListener('input', updateShareButton);
    }
    if (descriptionInput) {
        descriptionInput.addEventListener('input', updateShareButton);
    }
}

/**
 * Gérer la sélection d'un espace
 */
function handleSpaceSelection(event) {
    selectedSpaceId = event.target.value;
    window.fvLog('Espace sélectionné:', selectedSpaceId);
    
    // Mettre à jour l'apparence des options
    updateSpaceOptions();
    
    // Activer/désactiver le bouton de partage
    updateShareButton();
}

/**
 * Mettre à jour l'apparence des options d'espace
 */
function updateSpaceOptions() {
    const spaceOptions = document.querySelectorAll('.space-option');
    
    spaceOptions.forEach(option => {
        const radio = option.querySelector('input[type="radio"]');
        const isSelected = radio.checked;
        
        if (isSelected) {
            option.style.borderColor = 'var(--primary-color)';
            option.style.boxShadow = '0 4px 12px rgba(0, 242, 234, 0.15)';
        } else {
            option.style.borderColor = 'var(--border-color)';
            option.style.boxShadow = 'none';
        }
    });
}

/**
 * Mettre à jour l'état du bouton de partage
 */
function updateShareButton() {
    const shareButton = document.getElementById('share-button');
    const titleInput = document.getElementById('infographic-title');
    
    if (!shareButton) return;
    
    const hasSelectedSpace = selectedSpaceId !== null;
    const hasTitle = titleInput && titleInput.value.trim().length > 0;
    
    if (hasSelectedSpace && hasTitle) {
        shareButton.disabled = false;
        shareButton.classList.remove('btn--disabled');
    } else {
        shareButton.disabled = true;
        shareButton.classList.add('btn--disabled');
    }
}

/**
 * Gérer la soumission du formulaire de partage
 */
async function handleShareSubmit(event) {
    event.preventDefault();
    
    if (!selectedSpaceId) {
        showErrorMessage('Veuillez sélectionner un espace');
        return;
    }
    
    const formData = new FormData(event.target);
    const title = formData.get('title').trim();
    const description = formData.get('description').trim();
    
    if (!title) {
        showErrorMessage('Le titre de l\'infographie est requis');
        return;
    }
    
    // Désactiver le bouton pendant le traitement
    const shareButton = document.getElementById('share-button');
    const originalText = shareButton.innerHTML;
    shareButton.disabled = true;
    shareButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Partage en cours...';
    
    try {
        // Préparer les données de partage
        const shareData = {
            space_id: selectedSpaceId,
            title: title,
            description: description,
            infographic_data: ShareConfig.infographicParams,
            csrf_token: ShareConfig.csrfToken
        };
        
        window.fvLog('Données de partage:', shareData);
        window.fvLog('URL de l\'API:', `${ShareConfig.baseUrl}/infographics/${selectedSpaceId}`);
        window.fvLog('ShareConfig.baseUrl:', ShareConfig.baseUrl);
        window.fvLog('selectedSpaceId:', selectedSpaceId);
        
        // Appeler l'API de partage
        window.fvLog('Envoi de la requête à l\'API...');
        const response = await fetch(`${ShareConfig.baseUrl}/infographics/${selectedSpaceId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': ShareConfig.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin', // Inclure les cookies de session
            body: JSON.stringify(shareData)
        });
        
        window.fvLog('Réponse reçue:', response);
        window.fvLog('Status:', response.status);
        window.fvLog('Status Text:', response.statusText);
        window.fvLog('Headers:', response.headers);
        
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Erreur HTTP ${response.status}: ${response.statusText} - ${errorText}`);
        }
        
        // Lire le contenu de la réponse une seule fois
        const result = await response.json();
        window.fvLog('Contenu de la réponse:', JSON.stringify(result));
        
        if (result.success) {
            showSuccessMessage('Infographie partagée avec succès !');
            
            // Rediriger vers la page des espaces partagés après 2 secondes
            setTimeout(() => {
                window.location.href = (window.CantalDestinationConfig ? window.CantalDestinationConfig.url('/shared-spaces') : '/shared-spaces');
            }, 2000);
        } else {
            throw new Error(result.message || 'Erreur lors du partage');
        }
        
    } catch (error) {
        console.error('Erreur de partage:', error);
        showErrorMessage('Erreur lors du partage : ' + error.message);
        
        // Restaurer le bouton
        shareButton.disabled = false;
        shareButton.innerHTML = originalText;
    }
}

/**
 * Afficher un message de succès
 */
function showSuccessMessage(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'success-message';
    messageDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
    messageDiv.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: var(--success-color);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        z-index: 1000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

/**
 * Afficher un message d'erreur
 */
function showErrorMessage(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'error-message';
    messageDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
    messageDiv.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: var(--error-color);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        z-index: 1000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

/**
 * Générer un ID unique pour l'infographie
 */
function generateUniqueId(params) {
    const date = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    const zone = params.zone || 'CANTAL';
    const year = params.year || '2024';
    const period = (params.period || 'ANNEE').substring(0, 4).toUpperCase();
    const customSuffix = (params.debut && params.fin) ? '_CUSTOM' : '';
    const random = Math.random().toString(36).substring(2, 8).toUpperCase();
    
    return `INF_${date}_${zone}_${year}_${period}${customSuffix}_${random}`;
}

// Animation CSS pour les messages
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .btn--disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
`;
document.head.appendChild(style);
