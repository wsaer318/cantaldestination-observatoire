@echo off
echo ==========================================
echo    FLUXVISION - TELECHARGEMENT ZIP CANT-150
echo ==========================================
echo.

cd /d "%~dp0"

echo Activation de l'environnement virtuel...
if exist "..\venv\Scripts\activate.bat" (
    call "..\venv\Scripts\activate.bat"
    echo Environnement virtuel active.
) else (
    echo Attention: Environnement virtuel non trouve, utilisation de Python systeme.
)

echo.
set /p year="Entrez l'annee a telecharger (ex: 2025): "

if "%year%"=="" (
    echo Erreur: Aucune annee saisie.
    goto :end
)

echo.
echo Telechargement des fichiers ZIP CANT-150 B%year%...
python download_FV_Portal_zip.py %year%

:end
echo.
echo ==========================================
echo Appuyez sur une touche pour fermer...
pause >nul 