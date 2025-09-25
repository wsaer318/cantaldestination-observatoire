/**
 * JavaScript pour la gestion des espaces partagés
 */

// Configuration globale
const SharedSpacesConfig = {
    csrfToken: null,
    baseUrl: null
};

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    // Récupérer le token CSRF depuis les formulaires existants
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    SharedSpacesConfig.csrfToken = csrfInput ? csrfInput.value : '';
    
    // Utiliser le chemin de base du projet
    SharedSpacesConfig.baseUrl = window.location.origin + (window.CantalDestinationConfig ? window.CantalDestinationConfig.basePath : '');
    
    window.fvLog('SharedSpacesConfig initialisé:', {
        csrfToken: SharedSpacesConfig.csrfToken ? 'Présent' : 'Manquant',
        baseUrl: SharedSpacesConfig.baseUrl
    });
});

/**
 * Mettre à jour le rôle d'un membre
 */
function updateMemberRole(spaceId, memberId, newRole) {
    if (!SharedSpacesConfig.csrfToken) {
        alert('Erreur: Token de sécurité manquant. Veuillez recharger la page.');
        return;
    }
    
    window.fvLog('Mise à jour du rôle:', { spaceId, memberId, newRole });
    
    // Créer les données à envoyer
    const formData = new FormData();
    formData.append('action', 'update_role');
    formData.append('member_id', memberId);
    formData.append('new_role', newRole);
    formData.append('csrf_token', SharedSpacesConfig.csrfToken);
    
    // Envoyer la requête avec fetch
    fetch(`${SharedSpacesConfig.baseUrl}/admin/shared-spaces/${spaceId}/manage`, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            // Mettre à jour l'interface sans recharger
            showSuccessMessage('Rôle mis à jour avec succès !');
            // Mettre à jour le badge du rôle dans l'interface
            updateRoleBadge(memberId, newRole);
        } else {
            throw new Error('Erreur lors de la mise à jour du rôle');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showErrorMessage('Erreur lors de la mise à jour du rôle. Veuillez réessayer.');
    });
}

/**
 * Retirer un membre d'un espace
 */
function removeMember(spaceId, memberId) {
    if (!SharedSpacesConfig.csrfToken) {
        alert('Erreur: Token de sécurité manquant. Veuillez recharger la page.');
        return;
    }
    
    window.fvLog('Retrait du membre:', { spaceId, memberId });
    
    // Créer les données à envoyer
    const formData = new FormData();
    formData.append('action', 'remove_member');
    formData.append('member_id', memberId);
    formData.append('csrf_token', SharedSpacesConfig.csrfToken);
    
    // Envoyer la requête avec fetch
    fetch(`${SharedSpacesConfig.baseUrl}/admin/shared-spaces/${spaceId}/manage`, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            // Mettre à jour l'interface sans recharger
            showSuccessMessage('Membre retiré avec succès !');
            // Supprimer la carte du membre de l'interface
            removeMemberCard(memberId);
        } else {
            throw new Error('Erreur lors du retrait du membre');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showErrorMessage('Erreur lors du retrait du membre. Veuillez réessayer.');
    });
}

/**
 * Confirmer la suppression d'un espace
 */
function confirmDeleteSpace(spaceId) {
    const spaceName = document.querySelector('.admin-header p')?.textContent || 'cet espace';
    
    if (confirm(`Êtes-vous ABSOLUMENT sûr de vouloir supprimer l'espace "${spaceName}" ?\n\nCette action est irréversible !`)) {
        if (confirm('Dernière chance : confirmez-vous la suppression ?')) {
            // Créer les données à envoyer
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('csrf_token', SharedSpacesConfig.csrfToken);
            
            // Envoyer la requête avec fetch
            fetch(`${SharedSpacesConfig.baseUrl}/admin/shared-spaces/${spaceId}/manage`, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    // Rediriger vers la liste des espaces
                    window.location.href = `${SharedSpacesConfig.baseUrl}/admin/shared-spaces`;
                } else {
                    throw new Error('Erreur lors de la suppression de l\'espace');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la suppression de l\'espace. Veuillez réessayer.');
            });
        }
    }
}

/**
 * Valider le formulaire de création d'espace
 */
function validateSpaceForm() {
    const spaceName = document.getElementById('space_name')?.value.trim();
    const members = document.querySelectorAll('input[name="members[]"]:checked');
    
    if (!spaceName) {
        alert('Le nom de l\'espace est requis');
        return false;
    }
    
    if (members.length === 0) {
        if (!confirm('Aucun membre sélectionné. Voulez-vous créer l\'espace avec vous seul comme membre ?')) {
            return false;
        }
    }
    
    return true;
}

/**
 * Gérer la sélection/désélection de tous les membres
 */
function toggleAllMembers(checked) {
    const checkboxes = document.querySelectorAll('input[name="members[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = checked;
    });
}

/**
 * Mettre à jour les rôles par défaut lors de la sélection de membres
 */
function updateDefaultRoles() {
    const checkboxes = document.querySelectorAll('input[name="members[]"]:checked');
    checkboxes.forEach(checkbox => {
        const userId = checkbox.value;
        const roleSelect = document.querySelector(`select[name="member_role_${userId}"]`);
        if (roleSelect && roleSelect.value === '') {
            roleSelect.value = 'reader'; // Rôle par défaut
        }
    });
}

// Fonctions utilitaires pour les messages et mises à jour d'interface
function showSuccessMessage(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'success-message';
    messageDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
    messageDiv.style.opacity = '0';
    
    // Insérer le message après le header
    const header = document.querySelector('.admin-header');
    if (header) {
        header.parentNode.insertBefore(messageDiv, header.nextSibling);
    }
    
    // Animation d'apparition
    setTimeout(() => {
        messageDiv.style.opacity = '1';
    }, 100);
    
    // Auto-hide après 5 secondes
    setTimeout(() => {
        messageDiv.style.opacity = '0';
        setTimeout(() => messageDiv.remove(), 300);
    }, 5000);
}

function showErrorMessage(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'error-message';
    messageDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
    messageDiv.style.opacity = '0';
    
    // Insérer le message après le header
    const header = document.querySelector('.admin-header');
    if (header) {
        header.parentNode.insertBefore(messageDiv, header.nextSibling);
    }
    
    // Animation d'apparition
    setTimeout(() => {
        messageDiv.style.opacity = '1';
    }, 100);
    
    // Auto-hide après 5 secondes
    setTimeout(() => {
        messageDiv.style.opacity = '0';
        setTimeout(() => messageDiv.remove(), 300);
    }, 5000);
}

function updateRoleBadge(memberId, newRole) {
    // Trouver la carte du membre et mettre à jour le badge de rôle
    const memberCard = document.querySelector(`[data-member-id="${memberId}"]`);
    if (memberCard) {
        const roleBadge = memberCard.querySelector('.role-badge');
        if (roleBadge) {
            roleBadge.textContent = newRole.charAt(0).toUpperCase() + newRole.slice(1);
            roleBadge.className = `role-badge ${newRole}`;
        }
    }
}

function removeMemberCard(memberId) {
    // Trouver et supprimer la carte du membre
    const memberCard = document.querySelector(`[data-member-id="${memberId}"]`);
    if (memberCard) {
        // Récupérer le nom d'utilisateur avant de supprimer la carte
        const username = memberCard.querySelector('.member-info h4')?.textContent || 'Utilisateur';
        
        memberCard.style.opacity = '0';
        memberCard.style.transform = 'translateX(-100%)';
        setTimeout(() => {
            memberCard.remove();
            // Mettre à jour le compteur de membres
            updateMemberCount();
            
            // Ajouter l'utilisateur à la liste des disponibles
            addUserToAvailableList(memberId, username);
        }, 300);
    }
}

function addUserToAvailableList(userId, username) {
    // Créer un nouvel élément utilisateur disponible
    const userItem = document.createElement('div');
    userItem.className = 'user-select-item';
    userItem.innerHTML = `
        <label class="checkbox-label">
            <input type="checkbox" name="users[]" value="${userId}">
            <span class="checkmark"></span>
            <span class="username">${username}</span>
        </label>
    `;
    
    // Ajouter à la grille des utilisateurs disponibles
    const usersGrid = document.querySelector('.users-grid');
    if (usersGrid) {
        usersGrid.appendChild(userItem);
    }
    
    // Mettre à jour les compteurs
    updateAvailableUsersCount();
}



// Fonction pour récupérer le nom d'utilisateur depuis les données stockées
function getUserNameFromStoredData(userId) {
    // Chercher dans les éléments existants pour récupérer le nom
    const memberCard = document.querySelector(`[data-member-id="${userId}"]`);
    if (memberCard) {
        return memberCard.querySelector('.member-info h4')?.textContent || 'Utilisateur';
    }
    
    // Si pas trouvé, chercher dans les options de sélection
    const selectOption = document.querySelector(`select[name="user_id"] option[value="${userId}"]`);
    if (selectOption) {
        return selectOption.textContent;
    }
    
    return 'Utilisateur';
}

function updateMemberCount() {
    const memberCards = document.querySelectorAll('.member-card');
    const countBadge = document.querySelector('.member-count .badge');
    if (countBadge) {
        const count = memberCards.length;
        countBadge.textContent = `${count} membre${count > 1 ? 's' : ''}`;
    }
    
    // Mettre à jour aussi le compteur d'utilisateurs disponibles
    updateAvailableUsersCount();
}

function updateAvailableUsersCount() {
    // Compter les utilisateurs disponibles (non membres)
    const availableUsers = document.querySelectorAll('.users-grid .user-select-item');
    const availableCount = availableUsers.length;
    
    // Mettre à jour le badge dans le mode individuel
    const availableBadge = document.querySelector('.available-users-count .badge');
    if (availableBadge) {
        availableBadge.textContent = `${availableCount} utilisateur${availableCount > 1 ? 's' : ''} disponible${availableCount > 1 ? 's' : ''}`;
    }
    
    // Mettre à jour le badge dans le mode multiple
    const multipleAvailableBadge = document.querySelector('.multiple-actions .available-users-count .badge');
    if (multipleAvailableBadge) {
        multipleAvailableBadge.textContent = `${availableCount} utilisateur${availableCount > 1 ? 's' : ''} disponible${availableCount > 1 ? 's' : ''}`;
    }
    
    // Masquer/afficher le message "aucun utilisateur disponible"
    const noAvailableMessage = document.querySelector('.no-available-users');
    if (noAvailableMessage) {
        if (availableCount === 0) {
            noAvailableMessage.style.display = 'flex';
        } else {
            noAvailableMessage.style.display = 'none';
        }
    }
}

// ========================================
// FONCTIONS POUR LA GESTION MULTIPLE
// ========================================

/**
 * Basculer entre les modes de gestion
 */
function toggleManagementMode(mode) {
    const singlePanel = document.getElementById('single-management');
    const multiplePanel = document.getElementById('multiple-management');
    const singleBtn = document.getElementById('single-mode-btn');
    const multipleBtn = document.getElementById('multiple-mode-btn');
    
    if (mode === 'single') {
        singlePanel.style.display = 'block';
        multiplePanel.style.display = 'none';
        singleBtn.classList.add('btn--primary');
        singleBtn.classList.remove('btn--secondary');
        multipleBtn.classList.add('btn--secondary');
        multipleBtn.classList.remove('btn--primary');
    } else {
        singlePanel.style.display = 'none';
        multiplePanel.style.display = 'block';
        multipleBtn.classList.add('btn--primary');
        multipleBtn.classList.remove('btn--secondary');
        singleBtn.classList.add('btn--secondary');
        singleBtn.classList.remove('btn--primary');
    }
}

/**
 * Sélectionner tous les utilisateurs disponibles
 */
function selectAllUsers() {
    const checkboxes = document.querySelectorAll('.users-grid input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

/**
 * Désélectionner tous les utilisateurs
 */
function deselectAllUsers() {
    const checkboxes = document.querySelectorAll('.users-grid input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}

/**
 * Sélectionner tous les membres
 */
function selectAllMembers() {
    const checkboxes = document.querySelectorAll('.members-grid-compact input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

/**
 * Désélectionner tous les membres
 */
function deselectAllMembers() {
    const checkboxes = document.querySelectorAll('.members-grid-compact input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}

/**
 * Sélectionner tous les membres pour suppression
 */
function selectAllMembersForRemoval() {
    const checkboxes = document.querySelectorAll('.bulk-remove-form input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

/**
 * Désélectionner tous les membres pour suppression
 */
function deselectAllMembersForRemoval() {
    const checkboxes = document.querySelectorAll('.bulk-remove-form input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}

/**
 * Filtrer les membres par rôle
 */
function filterMembersByRole(role) {
    const memberItems = document.querySelectorAll('.member-select-item');
    
    memberItems.forEach(item => {
        if (!role || item.dataset.role === role) {
            item.classList.remove('filtered');
        } else {
            item.classList.add('filtered');
        }
    });
}

// ========================================
// ÉVÉNEMENTS
// ========================================

// Événements
document.addEventListener('DOMContentLoaded', function() {
    // Validation du formulaire de création
    const createForm = document.querySelector('form[action*="/admin/shared-spaces"]');
    if (createForm && createForm.action && typeof createForm.action === 'string' && !createForm.action.includes('/manage')) {
        createForm.addEventListener('submit', function(e) {
            if (!validateSpaceForm()) {
                e.preventDefault();
            }
        });
    }
    
    // Gestion des checkboxes de membres
    const memberCheckboxes = document.querySelectorAll('input[name="members[]"]');
    memberCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateDefaultRoles);
    });
    
    // Auto-hide des messages de succès/erreur existants
    const messages = document.querySelectorAll('.success-message, .error-message');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.opacity = '0';
            setTimeout(() => message.remove(), 300);
        }, 5000);
    });
    
    // Validation des formulaires multiples
    const multipleAddForm = document.querySelector('.multiple-add-form');
    if (multipleAddForm) {
        multipleAddForm.addEventListener('submit', function(e) {
            const selectedUsers = document.querySelectorAll('.users-grid input[type="checkbox"]:checked');
            if (selectedUsers.length === 0) {
                e.preventDefault();
                showErrorMessage('Veuillez sélectionner au moins un utilisateur à ajouter.');
            } else {
                // Mettre à jour l'interface après soumission
                setTimeout(() => {
                    updateInterfaceAfterBulkAction();
                }, 500);
            }
        });
    }
    
    const bulkRoleForm = document.querySelector('.bulk-role-form');
    if (bulkRoleForm) {
        bulkRoleForm.addEventListener('submit', function(e) {
            const selectedMembers = document.querySelectorAll('.members-grid-compact input[type="checkbox"]:checked');
            const newRole = document.querySelector('select[name="new_role"]').value;
            
            if (selectedMembers.length === 0) {
                e.preventDefault();
                showErrorMessage('Veuillez sélectionner au moins un membre à modifier.');
            } else if (!newRole) {
                e.preventDefault();
                showErrorMessage('Veuillez sélectionner un nouveau rôle.');
            }
        });
    }
    
    const bulkRemoveForm = document.querySelector('.bulk-remove-form');
    if (bulkRemoveForm) {
        bulkRemoveForm.addEventListener('submit', function(e) {
            const selectedMembers = document.querySelectorAll('.bulk-remove-form input[type="checkbox"]:checked');
            
            if (selectedMembers.length === 0) {
                e.preventDefault();
                showErrorMessage('Veuillez sélectionner au moins un membre à retirer.');
            } else {
                const confirmMessage = `Êtes-vous sûr de vouloir retirer ${selectedMembers.length} membre${selectedMembers.length > 1 ? 's' : ''} de cet espace ?`;
                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                } else {
                    // Mettre à jour l'interface après soumission
                    setTimeout(() => {
                        updateInterfaceAfterBulkAction();
                    }, 500);
                }
            }
        });
    }
    
    // Gestion des formulaires d'ajout de membres pour mise à jour dynamique
    const addMemberForm = document.querySelector('.add-member-form');
    if (addMemberForm) {
        addMemberForm.addEventListener('submit', function(e) {
            // Intercepter la soumission pour mise à jour dynamique
            e.preventDefault();
            
            const formData = new FormData(addMemberForm);
            const userId = formData.get('user_id');
            const role = formData.get('role');
            
            // Simuler l'ajout dynamique
            addMemberDynamically(userId, role);
            
            // Soumettre le formulaire normalement
            addMemberForm.submit();
        });
    }
    
    // Initialiser les compteurs
    updateAvailableUsersCount();
});

function addMemberDynamically(userId, role) {
    // Trouver l'utilisateur dans la liste des disponibles
    const userItem = document.querySelector(`.users-grid input[value="${userId}"]`).closest('.user-select-item');
    if (userItem) {
        const username = userItem.querySelector('.username').textContent;
        
        // Supprimer de la liste des disponibles
        userItem.remove();
        
        // Mettre à jour les compteurs
        updateAvailableUsersCount();
    }
}

// Fonction pour mettre à jour l'interface après les actions multiples
function updateInterfaceAfterBulkAction() {
    // Mettre à jour tous les compteurs
    updateMemberCount();
    updateAvailableUsersCount();
    
    // Recharger les listes si nécessaire
    setTimeout(() => {
        // Vérifier si les formulaires multiples existent et les mettre à jour
        const multipleAddForm = document.querySelector('.multiple-add-form');
        if (multipleAddForm) {
            // Recharger la liste des utilisateurs disponibles
            location.reload();
        }
    }, 1000);
}
