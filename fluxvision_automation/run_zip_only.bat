@echo off
echo ==========================================
echo    FLUXVISION - FICHIERS ZIP CANT-150 BX ANNEE
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
set /p year="Entrez l'annee a filtrer (ex: 2019): "

if "%year%"=="" (
    echo Erreur: Aucune annee saisie.
    goto :end
)

echo.
echo Execution du script de filtrage ZIP CANT-150 B%year%...
python format_FV_Portal_zip_only.py %year%

:end
echo.
echo ==========================================
echo Appuyez sur une touche pour fermer...
pause >nul 