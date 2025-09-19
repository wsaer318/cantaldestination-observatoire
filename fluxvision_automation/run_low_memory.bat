@echo off
setlocal enabledelayedexpansion
echo =======================================================
echo    FLUXVISION AUTOMATION - MODE OPTIMISE MEMOIRE
echo =======================================================
echo.

REM Vérifier si Python est installé
python --version >nul 2>&1
if errorlevel 1 (
    echo ERREUR: Python n'est pas installe ou pas dans le PATH
    echo Veuillez installer Python 3.8+ depuis https://python.org
    pause
    exit /b 1
)

REM Vérifier si les dépendances sont installées
echo Verification des dependances...
python -c "import polars, psutil" >nul 2>&1
if errorlevel 1 (
    echo Installation des dependances manquantes...
    pip install polars psutil
    if errorlevel 1 (
        echo ERREUR: Impossible d'installer les dependances
        pause
        exit /b 1
    )
)

REM Afficher l'état de la mémoire
echo.
echo === ETAT SYSTEME AVANT LANCEMENT ===
python -c "import psutil; m=psutil.virtual_memory(); print(f'RAM totale: {m.total/(1024**3):.1f}GB'); print(f'RAM disponible: {m.available/(1024**3):.1f}GB'); print(f'RAM utilisee: {m.percent:.1f}%%')"

REM Vérifier la RAM avec une approche plus simple
echo.
echo Verification de la RAM disponible...
python -c "import psutil; ram_gb = psutil.virtual_memory().available/(1024**3); print(f'RAM disponible: {ram_gb:.1f}GB'); print('OK - Suffisamment de RAM' if ram_gb > 2 else 'ATTENTION - RAM faible')"

REM Demander confirmation si RAM faible (optionnel)
python -c "import psutil; exit(1 if psutil.virtual_memory().available < 2*(1024**3) else 0)" >nul 2>&1
if errorlevel 1 (
    echo.
    echo ATTENTION: Moins de 2GB de RAM disponible!
    echo Recommandations:
    echo - Fermez Chrome, Firefox et autres applications gourmandes
    echo - Fermez les logiciels de bureautique
    echo - Attendez que l'antivirus termine ses scans
    echo.
    set /p "choice=Voulez-vous continuer quand meme? (O/N): "
    if /i not "!choice!"=="O" (
        echo Arret du programme.
        pause
        exit /b 1
    )
)

echo.
echo === LANCEMENT DU PROGRAMME ===
echo Mode optimise memoire active
echo Surveillez l'utilisation RAM pendant le processus...
echo.

REM Lancer avec priorité normale et limitation mémoire
python main-anita.py

REM Vérifier le code de sortie
if errorlevel 1 (
    echo.
    echo ===== ERREUR DETECTEE =====
    echo Le programme s'est termine avec une erreur.
    echo Consultez les logs pour plus de details.
    echo.
) else (
    echo.
    echo ===== SUCCES =====
    echo Le programme s'est termine avec succes!
    echo.
)

REM Afficher l'état final
echo === ETAT SYSTEME APRES TRAITEMENT ===
python -c "import psutil; m=psutil.virtual_memory(); print(f'RAM utilisee: {m.percent:.1f}%%')"

echo.
pause 