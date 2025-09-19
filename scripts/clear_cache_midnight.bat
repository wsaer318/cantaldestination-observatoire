@echo off
REM Script batch pour nettoyage automatique des caches FluxVision
REM Exécution programmée à minuit via le Planificateur de tâches Windows

REM Configuration des chemins
set PHP_PATH=C:\xampp\php\php.exe
set SCRIPT_PATH=%~dp0clear_cache_midnight.php
set LOG_PATH=%~dp0..\logs\cache_cleanup_batch.log

REM Créer le répertoire de logs s'il n'existe pas
if not exist "%~dp0..\logs" mkdir "%~dp0..\logs"

REM Logging du début d'exécution
echo [%date% %time%] === DÉBUT EXÉCUTION BATCH === >> "%LOG_PATH%"
echo [%date% %time%] Chemin PHP: %PHP_PATH% >> "%LOG_PATH%"
echo [%date% %time%] Script: %SCRIPT_PATH% >> "%LOG_PATH%"

REM Vérifier que PHP existe
if not exist "%PHP_PATH%" (
    echo [%date% %time%] ERREUR: PHP non trouvé à %PHP_PATH% >> "%LOG_PATH%"
    echo ERREUR: PHP non trouvé à %PHP_PATH%
    exit /b 1
)

REM Vérifier que le script PHP existe
if not exist "%SCRIPT_PATH%" (
    echo [%date% %time%] ERREUR: Script PHP non trouvé à %SCRIPT_PATH% >> "%LOG_PATH%"
    echo ERREUR: Script PHP non trouvé à %SCRIPT_PATH%
    exit /b 1
)

REM Exécuter le script PHP
echo [%date% %time%] Exécution du nettoyage de cache... >> "%LOG_PATH%"
"%PHP_PATH%" "%SCRIPT_PATH%" >> "%LOG_PATH%" 2>&1

REM Vérifier le code de retour
if %ERRORLEVEL% EQU 0 (
    echo [%date% %time%] ✅ Nettoyage terminé avec succès >> "%LOG_PATH%"
) else (
    echo [%date% %time%] ❌ Erreur lors du nettoyage (Code: %ERRORLEVEL%) >> "%LOG_PATH%"
)

echo [%date% %time%] === FIN EXÉCUTION BATCH === >> "%LOG_PATH%"
echo. >> "%LOG_PATH%"

exit /b %ERRORLEVEL% 