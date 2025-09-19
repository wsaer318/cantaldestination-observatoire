@echo off
echo ===================================
echo Creation de la base de donnees FluxVision
echo ===================================

REM Vérifier si Python est installé
python --version >nul 2>&1
if errorlevel 1 (
    echo Python n'est pas installe. Veuillez installer Python 3.8 ou superieur.
    pause
    exit /b 1
)

REM Vérifier si les dépendances sont installées
echo Installation des dependances requises...
pip install polars mysql-connector-python

REM Vérifier si MySQL est en cours d'exécution
echo Verification de la connexion MySQL...
mysql -u root -P 3307 -e "SELECT 1" >nul 2>&1
if errorlevel 1 (
    echo MySQL n'est pas accessible sur le port 3307.
    echo Veuillez demarrer MySQL et verifier le port.
    pause
    exit /b 1
)

REM Créer la base de données si elle n'existe pas
echo Creation de la base de donnees...
mysql -u root -P 3307 -e "CREATE DATABASE IF NOT EXISTS fluxvision CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

REM Exécuter le script Python
echo Execution du script de creation...
python create_fluxvision_database.py

REM Vérifier si l'exécution a réussi
if errorlevel 1 (
    echo Une erreur s'est produite lors de l'execution du script.
    pause
    exit /b 1
)

echo.
echo ===================================
echo Operation terminee avec succes !
echo ===================================
pause 