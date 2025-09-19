// JavaScript pour l'interface de gestion des périodes - FluxVision

document.addEventListener('DOMContentLoaded', function() {
    
    // Gestion des modals
    window.openModal = function(modalId) {
        document.getElementById(modalId).style.display = 'block';
    };
    
    window.closeModal = function(modalId) {
        document.getElementById(modalId).style.display = 'none';
    };
    
    window.openDuplicateModal = function(periodeId, periodeName) {
        document.getElementById('duplicatePeriodId').value = periodeId;
        document.getElementById('duplicatePeriodName').textContent = periodeName;
        openModal('duplicateModal');
    };
    
    // Fonction pour ajouter une année à un groupe existant
    window.ajouterAnnee = function(codePeriode, nomPeriode) {
        document.getElementById('code_periode').value = codePeriode;
        document.getElementById('nom_periode').value = nomPeriode;
        
        // Scroll vers le formulaire
        document.getElementById('periodeForm').scrollIntoView({ 
            behavior: 'smooth',
            block: 'start' 
        });
        
        // Focus sur l'année
        setTimeout(() => {
            document.getElementById('annee').focus();
        }, 500);
    };
    
    // Fonction pour supprimer un groupe de périodes
    window.supprimerGroupe = function(codePeriode, nomPeriode, nbAnnees) {
        document.getElementById('deleteGroupeName').textContent = nomPeriode;
        document.getElementById('deleteGroupeCode').textContent = codePeriode;
        document.getElementById('deleteGroupeCount').textContent = 
            `${nbAnnees} période${nbAnnees > 1 ? 's' : ''} sera${nbAnnees > 1 ? 'ont' : ''} supprimée${nbAnnees > 1 ? 's' : ''}`;
        document.getElementById('deleteGroupeCodeInput').value = codePeriode;
        
        openModal('deleteGroupeModal');
    };
    
    // Fermer les modals en cliquant à l'extérieur
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    };
    
    // Validation des dates
    const periodeForm = document.getElementById('periodeForm');
    if (periodeForm) {
        periodeForm.addEventListener('submit', function(e) {
            const dateDebut = new Date(document.getElementById('date_debut').value);
            const dateFin = new Date(document.getElementById('date_fin').value);
            
            if (dateDebut >= dateFin) {
                alert('La date de début doit être antérieure à la date de fin.');
                e.preventDefault();
                return false;
            }
        });
    }
    
    // Auto-majuscules pour le nom de période
    const nomPeriodeField = document.getElementById('nom_periode');
    if (nomPeriodeField) {
        nomPeriodeField.addEventListener('input', function(e) {
            const words = e.target.value.split(' ');
            const capitalizedWords = words.map(word => 
                word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
            );
            e.target.value = capitalizedWords.join(' ');
        });
    }
    
    // Suggestions de codes de période
    const codeSuggestions = {
        'vacances': 'vacances',
        'été': 'ete',
        'hiver': 'hiver',
        'printemps': 'printemps',
        'automne': 'automne',
        'pâques': 'paques',
        'noël': 'noel',
        'toussaint': 'toussaint',
        'mai': 'mai',
        'weekend': 'weekend'
    };
    
    if (nomPeriodeField) {
        nomPeriodeField.addEventListener('blur', function(e) {
            const nom = e.target.value.toLowerCase();
            const codeField = document.getElementById('code_periode');
            
            if (!codeField.value) {
                for (const [key, value] of Object.entries(codeSuggestions)) {
                    if (nom.includes(key)) {
                        codeField.value = value;
                        break;
                    }
                }
            }
        });
    }
    
    // Animation d'entrée pour les cartes de groupe
    const groupeCards = document.querySelectorAll('.groupe-card');
    groupeCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Amélioration UX : indication de chargement sur les boutons
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.classList.contains('btn--danger')) {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                
                // Réactiver après 5 secondes en cas de problème
                setTimeout(() => {
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                }, 5000);
            }
        });
    });
    
    // Confirmation améliorée pour les suppressions
    const deleteButtons = document.querySelectorAll('button[type="submit"]');
    deleteButtons.forEach(button => {
        const form = button.closest('form');
        if (form && form.querySelector('input[name="action"][value="delete"]')) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const confirmation = confirm(
                    '⚠️ ATTENTION ⚠️\n\n' +
                    'Cette action est IRRÉVERSIBLE !\n\n' +
                    'Êtes-vous absolument sûr de vouloir SUPPRIMER DÉFINITIVEMENT cette période ?\n\n' +
                    'Toutes les données associées seront perdues.'
                );
                
                if (confirmation) {
                    form.submit();
                }
            });
        }
    });
    
    // Raccourcis clavier
    document.addEventListener('keydown', function(e) {
        // Échap pour fermer les modals
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (modal.style.display === 'block') {
                    modal.style.display = 'none';
                }
            });
        }
        
        // Ctrl + N pour nouvelle période
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            document.getElementById('periodeForm').scrollIntoView({ 
                behavior: 'smooth',
                block: 'start' 
            });
            setTimeout(() => {
                document.getElementById('code_periode').focus();
            }, 500);
        }
    });
    
    // Notifications toast pour les actions
    const showToast = function(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
            <span>${message}</span>
        `;
        
        // Styles inline pour le toast
        Object.assign(toast.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '15px 20px',
            borderRadius: '8px',
            color: 'white',
            fontWeight: '500',
            zIndex: '9999',
            transform: 'translateX(100%)',
            transition: 'transform 0.3s ease',
            background: type === 'success' ? '#28a745' : '#dc3545'
        });
        
        document.body.appendChild(toast);
        
        // Animation d'entrée
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 100);
        
        // Suppression automatique
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    };
    
    // Exposer showToast globalement
    window.showToast = showToast;
    
    // Scroll automatique vers les détails du groupe si présents
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('details')) {
        const detailsSection = document.getElementById('details-groupe');
        if (detailsSection) {
            setTimeout(() => {
                detailsSection.scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'start' 
                });
                
                // Petit effet d'highlight pour attirer l'attention
                detailsSection.style.transition = 'box-shadow 0.5s ease';
                detailsSection.style.boxShadow = '0 0 20px rgba(102, 126, 234, 0.4)';
                setTimeout(() => {
                    detailsSection.style.boxShadow = '';
                }, 2000);
            }, 300);
        }
    }
}); 