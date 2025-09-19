@echo off
chcp 65001 >nul
echo ==========================================
echo    FLUXVISION - FILTRAGE DATES CSV
echo ==========================================
echo.

:: Activer l'environnement virtuel
echo Activation de l'environnement virtuel...
cd /d "%~dp0.."
call venv\Scripts\activate.bat

:: Retourner dans le répertoire du script
cd /d "%~dp0"

echo.
echo Filtrage des données (suppression des dates <= 2025-02-28)...
echo.

:: Exécuter le script Python
python filter_csv_dates.py

echo.
echo ==========================================
pause 