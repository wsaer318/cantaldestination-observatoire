# Script de vérification et suppression des CSV (logique : OR, suppression automatique)
# Pas d'interruption sur erreur ($ErrorActionPreference reste à la valeur par défaut)

# 1) Liste des fichiers toujours protégés (exceptions)
$alwaysKeep = @(
    'Nuitee.csv',
    'Diurne.csv',
    'Nuitee_Departement.csv',
    'Diurne_Departement.csv',
    'Nuitee_Pays.csv',
    'Diurne_Pays.csv',
    'Nuitee_Age.csv',
    'Diurne_Age.csv',
    'Nuitee_Geolife.csv',
    'Diurne_Geolife.csv'
)

# 2) Chemin du fichier de la liste officielle
$sourceListFile = Join-Path $PSScriptRoot "database_source_files.txt"
if (-not (Test-Path $sourceListFile)) {
    Write-Error "Le fichier de référence $sourceListFile est introuvable."
    exit 1
}

# Lire et nettoyer la liste (sans vides ni commentaires)
$filesToKeep = Get-Content $sourceListFile |
    Where-Object {
        $line = $_.Trim()
        ($line -ne "") -and (-not $line.StartsWith('#'))
    }

# 3) Répertoire des CSV
$csvDirectory = Join-Path $PSScriptRoot "data\data_clean_tmp\data_merged_csv"
if (-not (Test-Path $csvDirectory)) {
    Write-Error "Le répertoire $csvDirectory est introuvable."
    exit 1
}

# 4) Parcours et détection
$allCsvFiles   = Get-ChildItem -Path $csvDirectory -Filter *.csv
$filesToDelete = @()

Write-Host "`nAnalyse des fichiers CSV dans $csvDirectory..."
Write-Host '--------------------------------------------------'
foreach ($fileInfo in $allCsvFiles) {
    $fileName = $fileInfo.Name
    $filePath = $fileInfo.FullName

    # 4a) Exception ? on skip
    if ($alwaysKeep -contains $fileName) {
        Write-Host "[EXCEPTION]   $fileName - Protégé en dur"
        continue
    }

    # 4b) Lire la première ligne (header)
    try {
        $firstLine = Get-Content $filePath -TotalCount 1 -Encoding UTF8
    } catch {
        Write-Host "[ERREUR]      $fileName - Impossible de lire : $_"
        continue
    }

    # 4c) Tests
    $hasDateHeader = $firstLine -match '^Date\b'
    $inKeepList    = $filesToKeep -contains $fileName

    # 4d) OR logique : supprimer si (pas de Date) OU (pas dans la liste)
    if (-not $hasDateHeader -or -not $inKeepList) {
        # Construire dynamiquement le message
        $msg = "[À SUPPRIMER] $fileName -"
        if (-not $hasDateHeader) {
            $msg += " Pas d'en-tête 'Date'"
        }
        if (-not $hasDateHeader -and -not $inKeepList) {
            $msg += " et"
        }
        if (-not $inKeepList) {
            $msg += " Non listé dans database_source_files.txt"
        }
        Write-Host $msg

        # Suppression immédiate
        try {
            Remove-Item $filePath -Force
            Write-Host "Supprimé : $fileName"
        } catch {
            Write-Host "Erreur suppression $fileName : $_"
        }
    }
    else {
        Write-Host "[GARDÉ]        $fileName - En-tête 'Date' OK et listé"
    }
}

# 5) Résumé
Write-Host "`nRésumé :"
Write-Host '--------------------------------------------------'
Write-Host "Total analysés  : $($allCsvFiles.Count)"
Write-Host "Exceptions      : $($alwaysKeep.Count)"
# Comme on supprime immédiatement, le nombre à supprimer est implicite
Write-Host "Traitement terminé, suppressions effectuées au fil de l'eau."
