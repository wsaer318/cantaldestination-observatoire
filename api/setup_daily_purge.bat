@echo off
REM ========================================
REM CONFIGURATION PURGE QUOTIDIENNE CACHE
REM ========================================
REM 
REM Ce script configure une tâche Windows pour exécuter 
REM la purge quotidienne des caches à minuit
REM

echo.
echo  🌙 CONFIGURATION PURGE QUOTIDIENNE FLUXVISION
echo  =============================================
echo.

REM Configuration
set TASK_NAME=FluxVision_Cache_Daily_Purge
set SCRIPT_PATH=%~dp0cache_purge_daily.php
set PHP_PATH=C:\xampp\php\php.exe

echo  📋 Configuration:
echo     - Tâche: %TASK_NAME%
echo     - Script: %SCRIPT_PATH%
echo     - PHP: %PHP_PATH%
echo     - Heure: Tous les jours à minuit
echo.

REM Vérifier que PHP existe
if not exist "%PHP_PATH%" (
    echo  ❌ ERREUR: PHP introuvable à %PHP_PATH%
    echo     Veuillez modifier PHP_PATH dans ce script
    pause
    exit /b 1
)

REM Vérifier que le script existe
if not exist "%SCRIPT_PATH%" (
    echo  ❌ ERREUR: Script introuvable à %SCRIPT_PATH%
    pause
    exit /b 1
)

REM Supprimer l'ancienne tâche si elle existe
echo  🗑️  Suppression ancienne tâche...
schtasks /delete /tn "%TASK_NAME%" /f >nul 2>&1

REM Créer la nouvelle tâche
echo  📅 Création de la tâche quotidienne...
schtasks /create ^
    /tn "%TASK_NAME%" ^
    /tr "\"%PHP_PATH%\" \"%SCRIPT_PATH%\"" ^
    /sc daily ^
    /st 00:00 ^
    /ru "SYSTEM" ^
    /rl highest ^
    /f

if %ERRORLEVEL% equ 0 (
    echo  ✅ Tâche créée avec succès!
    echo.
    echo  📊 Détails de la tâche:
    schtasks /query /tn "%TASK_NAME%" /fo list
    echo.
    echo  🔧 Commandes utiles:
    echo     - Exécuter maintenant: schtasks /run /tn "%TASK_NAME%"
    echo     - Voir les logs: schtasks /query /tn "%TASK_NAME%" /v
    echo     - Supprimer: schtasks /delete /tn "%TASK_NAME%" /f
    echo.
    echo  📝 Test manuel:
    echo     php "%SCRIPT_PATH%"
    echo.
) else (
    echo  ❌ ERREUR: Impossible de créer la tâche
    echo     Veuillez exécuter ce script en tant qu'administrateur
)

echo  ✅ Configuration terminée!
pause 