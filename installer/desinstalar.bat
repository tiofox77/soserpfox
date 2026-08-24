@echo off
REM ============================================================================
REM  soserp - desinstalador portatil. Para e remove os servicos; oferece
REM  backup da BD antes. Correr como Administrador.
REM ============================================================================
setlocal

net session >nul 2>&1
if %errorlevel% neq 0 (
    powershell -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

set "AQUI=%~dp0"
set "AQUI=%AQUI:~0,-1%"
set "MYSQLDUMP=%AQUI%\xampp\mysql\bin\mysqldump.exe"

echo.
set /p BACKUP="Fazer backup da base de dados antes de remover? (S/N) "
if /I "%BACKUP%"=="S" (
    if exist "%MYSQLDUMP%" (
        echo A exportar a BD para backup-soserp.sql ...
        "%MYSQLDUMP%" --host=127.0.0.1 --port=3307 --user=root soserp > "%AQUI%\backup-soserp.sql"
        echo Backup em %AQUI%\backup-soserp.sql
    ) else (
        echo mysqldump nao encontrado — a saltar o backup.
    )
)

echo A remover tarefas de vigilancia...
schtasks /delete /tn "soserp-vigia" /f 2>nul
schtasks /delete /tn "soserp-integridade" /f 2>nul

echo A parar e remover servicos...
net stop soserp-apache 2>nul
net stop soserp-mysql 2>nul
"%AQUI%\xampp\apache\bin\httpd.exe" -k uninstall -n "soserp-apache" 2>nul
sc delete soserp-mysql 2>nul

echo.
echo Servicos removidos. Os ficheiros ficam em %AQUI% (apague a pasta a mao).
pause
