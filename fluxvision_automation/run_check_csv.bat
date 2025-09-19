@echo off
echo ==========================================
echo    FLUXVISION - VERIFICATION CSV
echo ==========================================
echo.

cd /d "%~dp0"

echo Execution du script de verification...
powershell -ExecutionPolicy Bypass -File check_csv_headers.ps1

echo.
echo ==========================================
echo Appuyez sur une touche pour fermer...
pause >nul 