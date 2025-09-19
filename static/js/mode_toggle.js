// Gestion du toggle entre les modes (Normal vs Comparaison)
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du toggle entre les modes
    const modeToggle = document.querySelectorAll('input[name="view-mode"]');
    const normalFilters = document.getElementById('normal-filters');
    const comparisonFilters = document.getElementById('comparison-filters');
    const comparisonResults = document.getElementById('comparison-results');
    
    modeToggle.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'normal') {
                // Mode normal
                normalFilters.style.display = 'block';
                comparisonFilters.style.display = 'none';
                comparisonResults.style.display = 'none';
                
                // Réactiver les filtres standards si nécessaire
                document.querySelectorAll('#normal-filters .filter-select').forEach(select => {
                    select.disabled = false;
                });
                
                
            } else if (this.value === 'comparison') {
                // Mode comparaison
                normalFilters.style.display = 'none';
                comparisonFilters.style.display = 'block';
                comparisonResults.style.display = 'block';
                
                
            }
        });
    });
    
    // Initialiser en mode normal par défaut
    document.getElementById('mode-normal').checked = true;
}); 