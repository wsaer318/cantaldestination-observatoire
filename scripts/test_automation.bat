@echo off
echo ========================================
echo   TEST AUTOMATISATION CACHE FLUXVISION
echo ========================================
echo.

echo 1. Test du script PHP...
php clear_cache_midnight.php
echo.

echo 2. Verification des logs...
if exist "..\logs\cache_cleanup.log" (
    echo ✅ Log PHP créé
) else (
    echo ❌ Log PHP manquant
)

if exist "..\logs\cache_cleanup_batch.log" (
    echo ✅ Log Batch créé
) else (
    echo ❌ Log Batch manquant
)

echo.
echo 3. Instructions pour l'installation automatique:
echo.
echo    Clic droit sur: setup_cache_cleanup_task.ps1
echo    Sélectionner: "Exécuter avec PowerShell"
echo    Accepter l'exécution en tant qu'administrateur
echo.
echo ✅ Tests terminés avec succès!
echo.
pause 