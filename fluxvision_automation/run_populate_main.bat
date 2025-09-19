@echo off
echo ==========================================
echo   FLUXVISION - ALIMENTATION TABLES PRINCIPALES
echo ==========================================
echo.

echo Activation de l'environnement virtuel...
call ..\venv\Scripts\activate.bat
if errorlevel 1 (
    echo Erreur: Impossible d'activer l'environnement virtuel
    pause
    exit /b 1
)
echo Environnement virtuel active.
echo.

echo Alimentation des tables principales...
python populate_main_tables.py --host localhost --port 3307 --database fluxvision
if errorlevel 1 (
    echo Erreur lors de l'alimentation des tables principales
    pause
    exit /b 1
)

echo.
echo ==========================================
echo Appuyez sur une touche pour fermer...
pause > nul 