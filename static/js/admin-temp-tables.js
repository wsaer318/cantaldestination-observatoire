/**
 * Admin Temp Tables - JavaScript
 * Gestion de l'interface d'administration des tables temporaires
 */

document.addEventListener('DOMContentLoaded', function() {
    initFileUpload();
    initConsoleLogging();
    initImportProgressTracking();
});

/**
 * Initialise l'interface d'upload de fichiers avec drag & drop
 */
function initFileUpload() {
    const fileInput = document.getElementById('csv_files') || document.getElementById('csv_file');
    const fileLabel = document.querySelector('.file-input-label');
    
    if (!fileInput || !fileLabel) return;
    
    const originalText = fileLabel.innerHTML;
    
    // Gestion du changement de fichier
    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            if (this.files.length === 1) {
                const fileName = this.files[0].name;
                fileLabel.innerHTML = `<i class="fas fa-file-csv"></i> ${fileName}`;
            } else {
                fileLabel.innerHTML = `<i class="fas fa-file-csv"></i> ${this.files.length} fichiers sélectionnés`;
            }
            fileLabel.style.borderColor = 'var(--accent4)';
            fileLabel.style.color = 'var(--accent4)';
        } else {
            fileLabel.innerHTML = originalText;
            fileLabel.style.borderColor = '';
            fileLabel.style.color = '';
        }
    });
    
    // Gestion du drag & drop
    setupDragAndDrop(fileLabel, fileInput);
}

/**
 * Configure le drag & drop pour l'upload de fichiers
 */
function setupDragAndDrop(fileLabel, fileInput) {
    // Prévenir les comportements par défaut
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        fileLabel.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    // Highlight lors du drag
    ['dragenter', 'dragover'].forEach(eventName => {
        fileLabel.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        fileLabel.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight(e) {
        fileLabel.style.borderColor = 'var(--primary)';
        fileLabel.style.backgroundColor = 'var(--surface-glass)';
    }
    
    function unhighlight(e) {
        if (!fileInput.files.length) {
            fileLabel.style.borderColor = '';
            fileLabel.style.backgroundColor = '';
        }
    }
    
    // Gestion du drop
    fileLabel.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (!files || files.length === 0) return;
        // Filtrer CSV uniquement
        const csvFiles = Array.from(files).filter(f => f && (f.type === 'text/csv' || f.name.toLowerCase().endsWith('.csv')));
        if (csvFiles.length === 0) {
            alert('Seuls les fichiers CSV sont acceptés.');
            return;
        }
        const dataTransfer = new DataTransfer();
        csvFiles.forEach(f => dataTransfer.items.add(f));
        fileInput.files = dataTransfer.files;
        const event = new Event('change', { bubbles: true });
        fileInput.dispatchEvent(event);
    }
}

/**
 * Confirme une action avec un message personnalisé
 */
function confirmAction(message) {
    return confirm(message);
}

/**
 * Affiche un message de statut temporaire
 */
function showStatusMessage(message, type = 'info', duration = 3000) {
    const statusDiv = document.createElement('div');
    statusDiv.className = `status-message ${type}`;
    statusDiv.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;
    
    // Styles inline pour le message temporaire
    statusDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        z-index: 1000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(statusDiv);
    
    // Supprimer après la durée spécifiée
    setTimeout(() => {
        statusDiv.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (statusDiv.parentNode) {
                statusDiv.parentNode.removeChild(statusDiv);
            }
        }, 300);
    }, duration);
}

/**
 * Ajoute les animations CSS nécessaires
 */
function addAnimationStyles() {
    if (document.getElementById('temp-tables-animations')) return;
    
    const style = document.createElement('style');
    style.id = 'temp-tables-animations';
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
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        .status-message {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-message.info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #2563eb;
        }
        
        .status-message.success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #059669;
        }
        
        .status-message.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #dc2626;
        }
    `;
    
    document.head.appendChild(style);
}

// Initialiser les animations au chargement
addAnimationStyles(); 

/**
 * Console logs pour tracer les actions côté admin
 */
function initConsoleLogging() {
    try {
        const ts = () => new Date().toISOString();

        // Logs génériques pour les formulaires d'action
        document.querySelectorAll('form.admin-action-form').forEach(form => {
            if (form.dataset.ajaxBound === '1') return;
            form.dataset.ajaxBound = '1';
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const action = (form.querySelector('input[name="action"]') || {}).value;
                const provTable = (form.querySelector('input[name="provisoire_table"]') || {}).value;
                window.fvLog(`[AdminTempTables ${ts()}] submit(AJAX) action="${action || 'unknown'}"${provTable ? ` provisoire_table="${provTable}"` : ''}`);
                submitAdminFormAjax(form, action || 'unknown');
            });
        });

        // Log spécifiques: upload
        const uploadForm = document.querySelector('form.upload-form');
        const uploadInput = document.getElementById('csv_files') || document.getElementById('csv_file');
        if (uploadForm && uploadInput) {
            uploadForm.addEventListener('submit', function () {
                const files = uploadInput.files ? Array.from(uploadInput.files) : [];
                const names = files.slice(0, 10).map(f => f.name);
                window.fvLog(`[AdminTempTables ${ts()}] upload submit: count=${files.length} sample=[${names.join(', ')}]`);
            });
        }

        // Log: suppression de fichier dans la liste
        document.querySelectorAll('form input[name="file_action"][value="delete"]').forEach(inp => {
            const form = inp.closest('form');
            if (!form) return;
            if (form.dataset.ajaxBound === '1') return;
            form.dataset.ajaxBound = '1';
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const filename = (form.querySelector('input[name="filename"]') || {}).value;
                window.fvLog(`[AdminTempTables ${ts()}] delete file submit: ${filename || '(unknown)'}`);
                submitAdminFormAjax(form, 'delete_file');
            });
        });

        // Log: boutons clés
        const logButtonSubmit = (selector, label) => {
            document.querySelectorAll(selector).forEach(btn => {
                const form = btn.closest('form');
                if (!form) return;
                if (form.dataset.ajaxBound === '1') return;
                form.dataset.ajaxBound = '1';
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    window.fvLog(`[AdminTempTables ${ts()}] ${label} submitted (AJAX)`);
                    submitAdminFormAjax(form, label);
                });
            });
        };

        // Migration
        logButtonSubmit('input[name="action"][value="migrate_to_main"]', 'migrate_to_main');
        logButtonSubmit('input[name="action"][value="verify_migration"]', 'verify_migration');
        // Mise à jour des temp tables
        logButtonSubmit('input[name="action"][value="check"]', 'check');
        logButtonSubmit('input[name="action"][value="force"]', 'force');
        logButtonSubmit('input[name="action"][value="refresh"]', 'refresh');
        // Nettoyage
        logButtonSubmit('input[name="action"][value="clear_temp_tables"]', 'clear_temp_tables');
        logButtonSubmit('input[name="action"][value="clear_provisional_data"]', 'clear_provisional_data');
        logButtonSubmit('input[name="action"][value="clear_cache"]', 'clear_cache');
        logButtonSubmit('input[name="action"][value="clear_logs"]', 'clear_logs');

        window.fvLog(`[AdminTempTables ${ts()}] console logging initialisé`);
    } catch (e) {
        // Ne jamais bloquer la page pour un log
        try { console.warn('AdminTempTables logging init error:', e); } catch (_) {}
    }
}

/**
 * Soumet un formulaire admin en AJAX et log le résultat
 */
function submitAdminFormAjax(form, label = 'action') {
    const ts = () => new Date().toISOString();
    const formData = new FormData(form);
    formData.set('ajax', '1');
    // Assurer le CSRF
    const csrf = form.querySelector('input[name="csrf_token"]');
    if (!csrf) {
        console.warn('[AdminTempTables] CSRF token manquant');
    }
    window.fvLog(`[AdminTempTables ${ts()}] ${label}: sending AJAX request to:`, window.location.href);
    const t0 = performance.now();
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    }).then(async (res) => {
        let data = null;
        let rawResponse = '';
        try { 
            rawResponse = await res.text();
            data = JSON.parse(rawResponse);
        } catch (parseError) {
            console.warn(`[AdminTempTables ${ts()}] ${label}: JSON parse error, raw response:`, rawResponse.substring(0, 500));
        }
        const t1 = performance.now();
        window.fvLog(`[AdminTempTables ${ts()}] ${label}: AJAX response status=${res.status} in ${(t1 - t0).toFixed(0)}ms`, data);
        
        if (!res.ok) {
            console.error(`[AdminTempTables ${ts()}] ${label}: HTTP error ${res.status}`, rawResponse);
            alert(`Erreur HTTP ${res.status} lors de l'action "${label}"`);
            return;
        }
        // Optionnel: rafraîchir les panneaux (statut/logs) sans rechargement dur
        if (data && data.logs) {
            // Juste un log pour preuve de réception; on peut implémenter un refresh DOM plus tard
            window.fvLog(`[AdminTempTables ${ts()}] update logs (first 3):`, data.logs.slice(0, 3));
        }
        if (data && data.migration_logs) {
            window.fvLog(`[AdminTempTables ${ts()}] migration logs (first 5):`, data.migration_logs.slice(0, 5));
        }
        if (data && data.stats) {
            window.fvLog(`[AdminTempTables ${ts()}] stats:`, data.stats);
        }
    }).catch(err => {
        console.error(`[AdminTempTables ${ts()}] ${label}: AJAX error to ${window.location.href}`, err);
        // Afficher une alerte utilisateur en cas d'erreur
        alert(`Erreur lors de l'exécution de l'action "${label}". Vérifiez la console pour plus de détails.`);
    });
}

/**
 * Initialise le suivi de progression de l'import en arrière-plan
 */
function initImportProgressTracking() {
    // Vérifier si un import est en cours au chargement de la page
    checkImportProgress();

    // Actualiser automatiquement toutes les 30 secondes si un import est en cours
    setInterval(function() {
        checkImportProgress();
    }, 30000);
}

/**
 * Vérifie la progression de l'import en arrière-plan
 */
function checkImportProgress() {
    // Détecter l'environnement pour construire le bon chemin
    const basePath = window.CantalDestinationConfig ? window.CantalDestinationConfig.basePath : '';

    fetch(basePath + '/tools/import/check_import_progress.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        updateProgressDisplay(data);
    })
    .catch(error => {
        console.error('[AdminTempTables] Erreur lors de la vérification de progression:', error);
    });
}

/**
 * Met à jour l'affichage de la progression
 */
function updateProgressDisplay(data) {
    // Supprimer l'ancien message de progression s'il existe
    const existingProgress = document.querySelector('.import-progress-notification');
    if (existingProgress) {
        existingProgress.remove();
    }

    // Créer le message de progression si nécessaire
    if (data.status === 'running' || data.status === 'completed' || data.status === 'error') {
        const progressDiv = document.createElement('div');
        progressDiv.className = `import-progress-notification alert alert-${data.status === 'completed' ? 'success' : (data.status === 'error' ? 'danger' : 'info')}`;

        let content = `<h4><i class="fas fa-${data.status === 'completed' ? 'check-circle' : (data.status === 'error' ? 'exclamation-triangle' : 'spinner fa-spin')}"></i> ${data.message}</h4>`;

        if (data.elapsed_time) {
            content += `<p><strong>Temps écoulé:</strong> ${Math.floor(data.elapsed_time / 60)}m ${data.elapsed_time % 60}s</p>`;
        }

        if (data.imported_rows > 0) {
            content += `<p><strong>Lignes importées:</strong> ${data.imported_rows.toLocaleString()}</p>`;
        }

        if (data.details && data.details.length > 0) {
            content += `<p><strong>Dernière activité:</strong> ${data.details[data.details.length - 1]}</p>`;
        }

        progressDiv.innerHTML = content;

        // Insérer après le message principal
        const mainMessage = document.querySelector('.success-message, .error-message');
        if (mainMessage) {
            mainMessage.insertAdjacentElement('afterend', progressDiv);
        } else {
            // Insérer au début de la section admin-header
            const adminHeader = document.querySelector('.admin-header');
            if (adminHeader) {
                adminHeader.insertAdjacentElement('afterend', progressDiv);
            }
        }

        // Si l'import est terminé, arrêter les vérifications automatiques
        if (data.status === 'completed' || data.status === 'error') {
            setTimeout(() => {
                location.reload(); // Recharger la page pour voir les résultats finaux
            }, 3000);
        }
    }
}