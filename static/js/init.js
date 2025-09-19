// Fichier d'initialisation commun à toutes les pages
document.addEventListener('DOMContentLoaded', function() {
    const target = document.getElementById('tsparticles');
    if (typeof tsParticles !== 'undefined' && target) {
        utils.initParticles('tsparticles');
    }
    
    // Autres initialisations communes si nécessaire
}); 