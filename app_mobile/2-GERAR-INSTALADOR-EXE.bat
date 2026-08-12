@echo off
cd /d "%~dp0"
echo ============================================================
echo   PASSO 2 - Gerar o instalador SOSERP-Faturacao-Setup.exe
echo   (compila a app nativa e cria o instalador com atalhos)
echo ============================================================
powershell -ExecutionPolicy Bypass -File "%~dp0installer\build_installer.ps1"
echo.
pause
