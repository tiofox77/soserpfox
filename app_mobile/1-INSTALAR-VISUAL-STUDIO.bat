@echo off
cd /d "%~dp0"
echo ============================================================
echo   PASSO 1 - Instalar Visual Studio Build Tools (C++)
echo   Vai aparecer um pedido de Administrador (UAC) - clique SIM
echo ============================================================
powershell -ExecutionPolicy Bypass -File "%~dp0install_vs.ps1"
echo.
echo Quando terminar, FECHE esta janela e use o 2-GERAR-INSTALADOR-EXE.bat
pause
