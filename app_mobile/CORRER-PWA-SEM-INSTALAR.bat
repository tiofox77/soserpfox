@echo off
cd /d "%~dp0"
echo ============================================================
echo   Correr a app no browser (sem instalar nada)
echo   Abre em http://localhost:8090 - clique Instalar no Edge
echo ============================================================
powershell -ExecutionPolicy Bypass -File "%~dp0install_pwa.ps1"
pause
