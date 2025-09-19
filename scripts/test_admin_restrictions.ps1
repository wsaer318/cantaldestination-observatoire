# Script PowerShell pour tester les restrictions d'accès administrateur
# Teste que seuls les administrateurs peuvent accéder aux espaces partagés et infographies

Write-Host "=== TEST DES RESTRICTIONS D'ACCÈS ADMINISTRATEUR ===" -ForegroundColor Green
Write-Host ""

# Vérifier que PHP est disponible
try {
    $phpVersion = php -v 2>&1 | Select-String "PHP"
    if ($phpVersion) {
        Write-Host "✅ PHP détecté: $phpVersion" -ForegroundColor Green
    } else {
        Write-Host "❌ PHP non trouvé" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "❌ Erreur lors de la vérification de PHP: $_" -ForegroundColor Red
    exit 1
}

# Définir le chemin du script de test
$scriptPath = Join-Path $PSScriptRoot "test_admin_restrictions.php"

# Vérifier que le fichier existe
if (-not (Test-Path $scriptPath)) {
    Write-Host "❌ Fichier de test non trouvé: $scriptPath" -ForegroundColor Red
    exit 1
}

Write-Host "📁 Exécution du script: $scriptPath" -ForegroundColor Yellow
Write-Host ""

# Exécuter le test
try {
    $output = php $scriptPath 2>&1
    
    # Afficher la sortie avec coloration
    foreach ($line in $output) {
        if ($line -match "✅") {
            Write-Host $line -ForegroundColor Green
        } elseif ($line -match "❌") {
            Write-Host $line -ForegroundColor Red
        } elseif ($line -match "⚠️") {
            Write-Host $line -ForegroundColor Yellow
        } elseif ($line -match "===") {
            Write-Host $line -ForegroundColor Cyan
        } else {
            Write-Host $line
        }
    }
    
    # Vérifier le code de sortie
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "🎉 Tests terminés avec succès!" -ForegroundColor Green
    } else {
        Write-Host ""
        Write-Host "💥 Tests terminés avec des erreurs (code: $LASTEXITCODE)" -ForegroundColor Red
    }
    
} catch {
    Write-Host "❌ Erreur lors de l'exécution du test: $_" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "=== FIN DU TEST ===" -ForegroundColor Green
