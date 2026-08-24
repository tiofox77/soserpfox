@echo off
REM ============================================================================
REM  soserp - instalador portatil (Opcao B). Correr como Administrador.
REM  Extraia o soserp-portable-<versao>.zip e faca duplo-clique aqui.
REM ============================================================================
setlocal

REM --- auto-elevacao (pede UAC se nao estiver elevado) ---
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo A pedir privilegios de administrador...
    powershell -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

set "AQUI=%~dp0"
set "AQUI=%AQUI:~0,-1%"

REM --- ler a chave publica e as portas gravadas pelo build ---
set "PUBKEY="
if exist "%AQUI%\public_key.txt" set /p PUBKEY=<"%AQUI%\public_key.txt"
set "PORT=8080"
set "DBPORT=3307"
if exist "%AQUI%\portas.txt" (
    set /p PORT=<"%AQUI%\portas.txt"
    for /f "skip=1 delims=" %%p in (%AQUI%\portas.txt) do set "DBPORT=%%p"
)

echo.
echo  A instalar o soserp em %AQUI%  (web: %PORT%, BD: %DBPORT%)
echo.

powershell -ExecutionPolicy Bypass -File "%AQUI%\provision.ps1" ^
    -InstallDir "%AQUI%" -Port %PORT% -DbPort %DBPORT% ^
    -LicenseFile "%AQUI%\license.key" -PublicKey "%PUBKEY%"

echo.
if %errorlevel% neq 0 ( echo Instalacao FALHOU. Veja as mensagens acima. ) else ( echo Instalacao concluida. )
pause
