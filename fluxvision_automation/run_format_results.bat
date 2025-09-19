@echo off
echo ==========================================
echo    FLUXVISION - FORMATAGE DES RESULTATS
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
echo Execution du script de formatage...
python format_FV_Portal_results.py

echo.
echo ==========================================
echo Appuyez sur une touche pour fermer...
pause >nul 