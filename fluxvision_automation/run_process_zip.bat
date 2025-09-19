@echo off
echo ==========================================
echo    FLUXVISION - TRAITEMENT ZIP CANT-150
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
echo Traitement des fichiers ZIP CANT-150...
python process_zip.py

echo.
echo Verification des fichiers CSV...
if exist "check_csv_headers.ps1" (
    powershell -ExecutionPolicy Bypass -File check_csv_headers.ps1
) else (
    echo [WARNING] Script de verification CSV non trouve
)

echo.
echo ==========================================
