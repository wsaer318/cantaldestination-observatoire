/**
 * JavaScript pour l'interface utilisateur des espaces partagés
 */

// Variables globales
let allSpaces = [];
let filteredSpaces = [];

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    loadUserSpaces();
    loadAvailableUsers();
    
    // Gestionnaire pour le formulaire de création
    const createForm = document.getElementById('create-space-form');
    if (createForm) {
        createForm.addEventListener('submit', handleCreateSpace);
    }
});

/**
 * Charger les espaces de l'utilisateur
 */
async function loadUserSpaces() {
    try {
        const response = await fetch(SharedSpacesConfig.baseUrl, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            console.error('Erreur HTTP:', response.status, response.statusText);
            
            // Si c'est une erreur 401 (non authentifié), rediriger vers la page de login
            if (response.status === 401) {
                window.location.href = (window.CantalDestinationConfig ? window.CantalDestinationConfig.url('/login') : '/login');
                return;
            }
            
            throw new Error(`Erreur HTTP ${response.status}: ${response.statusText}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.error('Réponse non-JSON reçue:', contentType);
            
            // Si on reçoit du HTML au lieu de JSON, c'est probablement une redirection vers login
            if (contentType && contentType.includes('text/html')) {
                window.location.href = (window.CantalDestinationConfig ? window.CantalDestinationConfig.url('/login') : '/login');
                return;
            }
            
            throw new Error('Réponse non-JSON reçue du serveur');
        }
        
        const data = await response.json();
        
        if (data.success) {
            allSpaces = data.data;
            filteredSpaces = [...allSpaces];
            renderSpaces();
            updateSpacesCount();
        } else {
            throw new Error(data.message || 'Erreur lors du chargement des espaces');
        }
    } catch (error) {
        console.error('Erreur:', error);
        
        // Si l'erreur indique une redirection vers login, ne pas afficher d'erreur
        if (error.message.includes('login') || error.message.includes('401')) {
            return;
        }
        
        showErrorMessage('Erreur lors du chargement des espaces: ' + error.message);
        showEmptyState();
    }
}

/**
 * Charger les utilisateurs disponibles pour la création d'espace
 */
async function loadAvailableUsers() {
    try {
        const response = await fetch((window.CantalDestinationConfig ? window.CantalDestinationConfig.url('/api/users/available.php') : '/api/users/available.php'), {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (response.ok) {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                const data = await response.json();
                if (data.success) {
                    renderAvailableUsers(data.data);
                } else {
                    console.warn('Erreur API:', data.message);
                }
            } else {
                console.warn('Réponse non-JSON reçue pour les utilisateurs');
            }
        } else {
            console.warn('Erreur HTTP:', response.status, response.statusText);
            // En cas d'erreur, on continue sans les utilisateurs
        }
    } catch (error) {
        console.error('Erreur lors du chargement des utilisateurs:', error);
        // En cas d'erreur, on continue sans les utilisateurs
    }
}

/**
 * Afficher les espaces
 */
function renderSpaces() {
    const container = document.getElementById('spaces-container');
    
    if (filteredSpaces.length === 0) {
        showEmptyState();
        return;
    }
    
    const spacesHTML = filteredSpaces.map(space => `
        <div class="space-card" data-space-id="${space.id}">
            <div class="space-header">
                <h3>${escapeHtml(space.name)}</h3>
                <span class="role-badge ${space.user_role}">
                    ${capitalizeFirst(space.user_role)}
                </span>
            </div>
            
            <div class="space-description">
                ${escapeHtml(space.description || 'Aucune description')}
            </div>
            
            <div class="space-stats">
                <div class="stat">
                    <i class="fas fa-users"></i>
                    <span>${space.stats?.member_count || 0} membres</span>
                </div>
                <div class="stat">
                    <i class="fas fa-chart-bar"></i>
                    <span>${space.stats?.infographic_count || 0} infographies</span>
                </div>
                <div class="stat">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Créé le ${formatDate(space.created_at)}</span>
                </div>
            </div>
            
            <div class="space-actions">
                <button type="button" class="btn btn--small btn--primary" onclick="viewSpaceDetails(${space.id})">
                    <i class="fas fa-eye"></i>
                    Voir détails
                </button>
                ${space.user_role === 'admin' ? `
                    <button type="button" class="btn btn--small btn--secondary" onclick="manageSpace(${space.id})">
                        <i class="fas fa-cog"></i>
                        Gérer
                    </button>
                    <button type="button" class="btn btn--small btn--danger" onclick="disableSpace(${space.id}, '${escapeHtml(space.name)}')">
                        <i class="fas fa-times"></i>
                        Désactiver
                    </button>
                ` : ''}
                <button type="button" class="btn btn--small btn--secondary" onclick="viewSpaceInfographics(${space.id})">
                    <i class="fas fa-chart-bar"></i>
                    Voir les infographies
                </button>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = `
        <div class="spaces-grid">
            ${spacesHTML}
        </div>
    `;
}

/**
 * Afficher l'état vide
 */
function showEmptyState() {
    const container = document.getElementById('spaces-container');
    container.innerHTML = `
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <h3>Aucun espace partagé</h3>
            <p>Vous n'avez pas encore d'espaces de travail collaboratif.</p>
            <button type="button" class="btn btn--primary" onclick="showCreateSpaceModal()">
                <i class="fas fa-plus"></i>
                Créer votre premier espace
            </button>
        </div>
    `;
}

/**
 * Afficher les utilisateurs disponibles
 */
function renderAvailableUsers(users) {
    const container = document.getElementById('available-users-grid');
    
    if (users.length === 0) {
        container.innerHTML = '<p class="text-muted">Aucun utilisateur disponible</p>';
        return;
    }
    
    const usersHTML = users.map(user => `
        <div class="user-select-item">
            <label class="checkbox-label">
                <input type="checkbox" name="members[]" value="${user.id}">
                <span class="checkmark"></span>
                <span class="username">${escapeHtml(user.username)}</span>
            </label>
        </div>
    `).join('');
    
    container.innerHTML = usersHTML;
}

/**
 * Gérer la création d'un espace
 */
async function handleCreateSpace(event) {
    event.preventDefault();
    
    // Rediriger vers la page de création d'espaces
    window.location.href = `${SharedSpacesConfig.baseUrl.replace('/api/shared-spaces', '')}/shared-spaces/create`;
}

/**
 * Filtrer les espaces par rôle
 */
function filterSpacesByRole() {
    const roleFilter = document.getElementById('role-filter').value;
    const searchTerm = document.getElementById('search-spaces').value.toLowerCase();
    
    filteredSpaces = allSpaces.filter(space => {
        const matchesRole = !roleFilter || space.user_role === roleFilter;
        const matchesSearch = !searchTerm || space.name.toLowerCase().includes(searchTerm);
        return matchesRole && matchesSearch;
    });
    
    renderSpaces();
    updateSpacesCount();
}

/**
 * Rechercher dans les espaces
 */
function searchSpaces() {
    filterSpacesByRole(); // Réutilise la logique de filtrage
}

/**
 * Actualiser les espaces
 */
function refreshSpaces() {
    loadUserSpaces();
}

/**
 * Mettre à jour le compteur d'espaces
 */
function updateSpacesCount() {
    const countElement = document.getElementById('spaces-count');
    const count = filteredSpaces.length;
    countElement.textContent = `${count} espace${count > 1 ? 's' : ''}`;
}

/**
 * Afficher le modal de création d'espace
 */
function showCreateSpaceModal() {
    document.getElementById('create-space-modal').style.display = 'flex';
    document.getElementById('space-name').focus();
}

/**
 * Masquer le modal de création d'espace
 */
function hideCreateSpaceModal() {
    document.getElementById('create-space-modal').style.display = 'none';
    document.getElementById('create-space-form').reset();
}

/**
 * Afficher le modal de détails d'espace
 */
async function viewSpaceDetails(spaceId) {
    try {
        // Si c'est l'API de test, utiliser les données locales
        if (SharedSpacesConfig.baseUrl.includes('test.php')) {
            const space = allSpaces.find(s => s.id == spaceId);
            if (!space) {
                throw new Error('Espace non trouvé');
            }
            
            // Simuler des données de membres pour l'API de test
            const mockMembers = [
                { username: 'admin', role: 'admin' },
                { username: 'demo', role: 'editor' },
                { username: 'user1', role: 'reader' }
            ];
            
            displaySpaceDetails({
                success: true,
                data: {
                    space: space,
                    members: mockMembers,
                    stats: space.stats || { member_count: 3, infographic_count: 1 }
                }
            });
            return;
        }
        
        const response = await fetch(`${SharedSpacesConfig.baseUrl}/${spaceId}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error('Erreur lors du chargement des détails');
        }
        
        const data = await response.json();
        
        if (data.success) {
            displaySpaceDetails(data);
        } else {
            throw new Error(data.message || 'Erreur lors du chargement des détails');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showErrorMessage('Erreur lors du chargement des détails: ' + error.message);
    }
}

/**
 * Afficher les détails d'un espace
 */
function displaySpaceDetails(data) {
    const space = data.data.space || data.data;
    const members = data.data.members || [];
    
    document.getElementById('space-details-title').innerHTML = 
        `<i class="fas fa-info-circle"></i> ${escapeHtml(space.name)}`;
    
    document.getElementById('space-details-content').innerHTML = `
        <div class="space-info-grid">
            <div class="info-card">
                <h3>Description</h3>
                <p>${escapeHtml(space.description || 'Aucune description')}</p>
            </div>
            <div class="info-card">
                <h3>Mon rôle</h3>
                <p><span class="role-badge ${space.user_role}">${capitalizeFirst(space.user_role)}</span></p>
            </div>
            <div class="info-card">
                <h3>Créé le</h3>
                <p>${formatDate(space.created_at)}</p>
            </div>
            <div class="info-card">
                <h3>Dernière modification</h3>
                <p>${formatDate(space.updated_at)}</p>
            </div>
        </div>
        
        <div class="space-members-section">
            <h3><i class="fas fa-users"></i> Membres (${members.length})</h3>
            <div class="members-grid">
                ${members.map(member => `
                    <div class="member-card">
                        <div class="member-info">
                            <h4>${escapeHtml(member.username)}</h4>
                            <span class="role-badge ${member.role}">${capitalizeFirst(member.role)}</span>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
        
        <div class="space-infographics-section">
            <h3><i class="fas fa-chart-bar"></i> Infographies partagées (0)</h3>
            <p class="text-muted">Aucune infographie partagée</p>
        </div>
    `;
    
    document.getElementById('space-details-modal').style.display = 'flex';
}

/**
 * Masquer le modal de détails d'espace
 */
function hideSpaceDetailsModal() {
    document.getElementById('space-details-modal').style.display = 'none';
}

/**
 * Gérer un espace (redirection vers l'admin)
 */
function manageSpace(spaceId) {
    window.location.href = `${SharedSpacesConfig.baseUrl.replace('/api/shared-spaces', '')}/admin/shared-spaces/${spaceId}/manage`;
}

/**
 * Désactiver un espace (soft delete)
 */
async function disableSpace(spaceId, spaceName) {
    if (!confirm(`Êtes-vous sûr de vouloir désactiver l'espace "${spaceName}" ?\n\nL'espace sera désactivé mais pourra être restauré par un administrateur.`)) {
        return;
    }
    
    try {
        const response = await fetch(`${SharedSpacesConfig.baseUrl}/${spaceId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                csrf_token: SharedSpacesConfig.csrfToken
            })
        });
        
        if (!response.ok) {
            throw new Error('Erreur lors de la désactivation de l\'espace');
        }
        
        const data = await response.json();
        
        if (data.success) {
            showSuccessMessage('Espace désactivé avec succès !');
            loadUserSpaces(); // Recharger la liste
        } else {
            throw new Error(data.message || 'Erreur lors de la désactivation de l\'espace');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showErrorMessage('Erreur lors de la désactivation de l\'espace: ' + error.message);
    }
}

/**
 * Voir les infographies d'un espace
 */
function viewSpaceInfographics(spaceId) {
    // Rediriger vers la page des infographies de l'espace
    window.location.href = `${SharedSpacesConfig.baseUrl.replace('/api/shared-spaces', '')}/shared-spaces/${spaceId}/infographics`;
}

/**
 * Voir une infographie
 */
function viewInfographic(infographicId) {
    // TODO: Implémenter la visualisation d'infographie
    showErrorMessage('Fonctionnalité de visualisation d\'infographie à implémenter');
}

/**
 * Sélectionner tous les utilisateurs
 */
function selectAllUsers() {
    const checkboxes = document.querySelectorAll('#available-users-grid input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

/**
 * Désélectionner tous les utilisateurs
 */
function deselectAllUsers() {
    const checkboxes = document.querySelectorAll('#available-users-grid input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}

// Fonctions utilitaires
function showSuccessMessage(message) {
    const container = document.getElementById('messages-container');
    const messageDiv = document.createElement('div');
    messageDiv.className = 'success-message';
    messageDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
    container.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

function showErrorMessage(message) {
    const container = document.getElementById('messages-container');
    const messageDiv = document.createElement('div');
    messageDiv.className = 'error-message';
    messageDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
    container.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function capitalizeFirst(str) {
    if (!str || typeof str !== 'string') {
        return '';
    }
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}
