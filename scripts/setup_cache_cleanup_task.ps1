# Script PowerShell pour configurer la tâche planifiée de nettoyage des caches
# FluxVision - Automatisation du nettoyage à minuit
# 
# Exécution: Clic droit > Exécuter avec PowerShell (en tant qu'administrateur)

param(
    [string]$TaskName = "FluxVision_Cache_Cleanup",
    [string]$Description = "Nettoyage automatique des caches FluxVision à minuit",
    [string]$Time = "00:00"
)

Write-Host "🔧 Configuration de la tâche planifiée FluxVision" -ForegroundColor Cyan
Write-Host "=================================================" -ForegroundColor Cyan

# Vérifier les privilèges administrateur
if (-NOT ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Host "❌ ERREUR: Ce script doit être exécuté en tant qu'administrateur" -ForegroundColor Red
    Write-Host "   Clic droit > Exécuter en tant qu'administrateur" -ForegroundColor Yellow
    Read-Host "Appuyez sur Entrée pour fermer"
    exit 1
}

# Chemins
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$BatchPath = Join-Path $ScriptDir "clear_cache_midnight.bat"
$FluxVisionRoot = Split-Path -Parent $ScriptDir

Write-Host "📁 Répertoire du script: $ScriptDir" -ForegroundColor Gray
Write-Host "📁 Racine FluxVision: $FluxVisionRoot" -ForegroundColor Gray

# Vérifier que le fichier batch existe
if (-not (Test-Path $BatchPath)) {
    Write-Host "❌ ERREUR: Fichier batch non trouvé: $BatchPath" -ForegroundColor Red
    Read-Host "Appuyez sur Entrée pour fermer"
    exit 1
}

try {
    # Supprimer la tâche existante si elle existe
    $existingTask = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    if ($existingTask) {
        Write-Host "🗑️ Suppression de la tâche existante..." -ForegroundColor Yellow
        Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
    }

    # Créer l'action (exécution du batch)
    $Action = New-ScheduledTaskAction -Execute $BatchPath

    # Créer le déclencheur (tous les jours à minuit)
    $Trigger = New-ScheduledTaskTrigger -Daily -At $Time

    # Créer les paramètres de la tâche
    $Settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

    # Créer le principal (utilisateur système pour éviter les problèmes de session)
    $Principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

    # Enregistrer la tâche planifiée
    Write-Host "⚙️ Création de la tâche planifiée..." -ForegroundColor Green
    Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger $Trigger -Settings $Settings -Principal $Principal -Description $Description

    Write-Host ""
    Write-Host "✅ SUCCÈS: Tâche planifiée configurée avec succès!" -ForegroundColor Green
    Write-Host ""
    Write-Host "📋 Détails de la tâche:" -ForegroundColor Cyan
    Write-Host "   • Nom: $TaskName" -ForegroundColor White
    Write-Host "   • Heure d'exécution: $Time (tous les jours)" -ForegroundColor White
    Write-Host "   • Script: $BatchPath" -ForegroundColor White
    Write-Host "   • Utilisateur: SYSTEM" -ForegroundColor White
    Write-Host ""
    Write-Host "🔍 Pour vérifier:" -ForegroundColor Yellow
    Write-Host "   1. Ouvrir le Planificateur de tâches Windows" -ForegroundColor White
    Write-Host "   2. Aller dans 'Bibliothèque du Planificateur de tâches'" -ForegroundColor White
    Write-Host "   3. Chercher '$TaskName'" -ForegroundColor White
    Write-Host ""
    Write-Host "🧪 Pour tester maintenant:" -ForegroundColor Yellow
    Write-Host "   Start-ScheduledTask -TaskName '$TaskName'" -ForegroundColor White
    Write-Host ""

    # Proposer un test immédiat
    $test = Read-Host "Voulez-vous tester la tâche maintenant? (o/N)"
    if ($test -eq "o" -or $test -eq "O" -or $test -eq "oui") {
        Write-Host "🧪 Test de la tâche en cours..." -ForegroundColor Yellow
        Start-ScheduledTask -TaskName $TaskName
        Start-Sleep 3
        
        $taskInfo = Get-ScheduledTask -TaskName $TaskName
        $lastResult = (Get-ScheduledTaskInfo -TaskName $TaskName).LastTaskResult
        
        if ($lastResult -eq 0) {
            Write-Host "✅ Test réussi! Vérifiez les logs dans $FluxVisionRoot\logs\" -ForegroundColor Green
        } else {
            Write-Host "⚠️ Test terminé avec le code: $lastResult" -ForegroundColor Yellow
            Write-Host "   Vérifiez les logs pour plus de détails." -ForegroundColor White
        }
    }

} catch {
    Write-Host "❌ ERREUR lors de la configuration:" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
}

Write-Host ""
Write-Host "Appuyez sur Entrée pour fermer..." -ForegroundColor Gray
Read-Host 