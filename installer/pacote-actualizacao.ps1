# ==========================================================================
#  soserp - pacote de ACTUALIZACAO (so os ficheiros alterados)
# ==========================================================================
#
#  Produz um ZIP pequeno com os ficheiros da app que mudaram desde uma versao
#  ja instalada, mais um aplicar.bat que os copia e limpa as caches.
#
#  Serve para corrigir uma instalacao sem repetir o instalador de 260 MB.
#  NAO mexe na base de dados: se a actualizacao trouxer migracoes, use o
#  instalador completo (que faz backup antes).
#
#  Uso:
#    powershell -ExecutionPolicy Bypass -File installer\pacote-actualizacao.ps1 -Desde 939f268 -Versao 1.3.0
#
param(
    [string]$Desde = "",          # commit/tag a partir do qual comparar
    [string]$Versao = "1.3.0"
)

$ErrorActionPreference = "Stop"
$raiz  = Split-Path $PSScriptRoot -Parent
$saida = Join-Path $PSScriptRoot "dist"
$temp  = Join-Path $PSScriptRoot "pacote-tmp"

function Log($m) { Write-Host "[pacote] $m" -ForegroundColor Green }

if (-not $Desde) { throw "Indique -Desde <commit da versao instalada>." }

Remove-Item $temp -Recurse -Force -EA SilentlyContinue
New-Item -ItemType Directory -Force (Join-Path $temp "app") | Out-Null

# Só o que a APLICAÇÃO precisa. Fora: testes, installer e docs — nada disso
# corre na máquina do cliente.
Push-Location $raiz
$ficheiros = git diff --name-only $Desde HEAD -- app resources public config routes database |
    Where-Object { $_ -notmatch '^(tests|installer|docs)/' -and (Test-Path (Join-Path $raiz $_)) }
Pop-Location

if (-not $ficheiros) { throw "Nada mudou desde $Desde." }

foreach ($f in $ficheiros) {
    $destino = Join-Path (Join-Path $temp "app") $f
    New-Item -ItemType Directory -Force (Split-Path $destino) | Out-Null
    Copy-Item (Join-Path $raiz $f) $destino -Force
}
Log ("$($ficheiros.Count) ficheiro(s) recolhidos.")

# O selador vai DENTRO do pacote. Sem ele, a actualizacao muda os ficheiros, os
# hashes deixam de bater com o manifesto da instalacao, e o vigia de
# integridade bloqueia o sistema todo a acusar adulteracao - com a
# actualizacao oficial a fazer-se passar por ataque.
Copy-Item (Join-Path $PSScriptRoot "selar-integridade.ps1") (Join-Path $temp "selar-integridade.ps1") -Force

# Migracoes que vao no pacote — se houver, o aplicar.bat corre-as.
$temMigracoes = [bool]($ficheiros | Where-Object { $_ -like 'database/migrations/*' })

# aplicar.bat: copia por cima e LIMPA AS CACHES. Sem limpar, o Blade continua
# a servir as views compiladas antigas e parece que nada mudou.
$bat = @(
    '@echo off',
    'REM soserp - aplicar actualizacao de ficheiros',
    'setlocal',
    'net session >nul 2>&1',
    'if %errorlevel% neq 0 (',
    '    echo A pedir privilegios de administrador...',
    '    powershell -Command "Start-Process -FilePath ''%~f0'' -Verb RunAs"',
    '    exit /b',
    ')',
    '',
    'set "AQUI=%~dp0"',
    'set "AQUI=%AQUI:~0,-1%"',
    'set "DESTINO=C:\soserp"',
    'if not "%~1"=="" set "DESTINO=%~1"',
    '',
    'if not exist "%DESTINO%\app\artisan" (',
    '    echo Nao encontrei uma instalacao do soserp em %DESTINO%.',
    '    echo Uso: aplicar.bat [pasta-de-instalacao]',
    '    pause & exit /b 1',
    ')',
    '',
    'echo A parar servicos...',
    'net stop soserp-apache >nul 2>&1',
    '',
    'echo A copiar ficheiros...',
    'xcopy "%AQUI%\app\*" "%DESTINO%\app\" /E /Y /Q >nul',
    '',
    'echo A limpar caches (senao as paginas antigas continuam a aparecer)...',
    'pushd "%DESTINO%\app"',
    '"%DESTINO%\xampp\php\php.exe" artisan view:clear',
    '"%DESTINO%\xampp\php\php.exe" artisan config:clear',
    '"%DESTINO%\xampp\php\php.exe" artisan cache:clear',
    $(if ($temMigracoes) { '"%DESTINO%\xampp\php\php.exe" artisan migrate --force' } else { 'REM sem migracoes neste pacote' }),
    '"%DESTINO%\xampp\php\php.exe" artisan view:cache',
    'popd',
    '',
    'echo A selar a integridade (senao o vigia acusa esta actualizacao de adulteracao)...',
    'powershell -NoProfile -File "%AQUI%\selar-integridade.ps1" -AppDir "%DESTINO%\app"',
    '',
    'echo A arrancar servicos...',
    'net start soserp-apache >nul 2>&1',
    '',
    'echo.',
    'echo Actualizacao aplicada. Abra http://localhost:8080',
    'pause'
) -join "`r`n"

Set-Content -Path (Join-Path $temp "aplicar.bat") -Value $bat -Encoding ascii

# Lista do que vai dentro, para se saber o que se está a aplicar.
Set-Content -Path (Join-Path $temp "CONTEUDO.txt") `
    -Value ((@("soserp - actualizacao $Versao", "", "Ficheiros:") + ($ficheiros | ForEach-Object { "  $_" })) -join "`r`n") `
    -Encoding utf8

New-Item -ItemType Directory -Force $saida | Out-Null
$zip = Join-Path $saida "soserp-actualizacao-$Versao.zip"
Remove-Item $zip -Force -EA SilentlyContinue
Compress-Archive -Path (Join-Path $temp "*") -DestinationPath $zip
Remove-Item $temp -Recurse -Force -EA SilentlyContinue

Log ("Feito: $zip (" + [math]::Round((Get-Item $zip).Length/1KB) + " KB)")
Log "No cliente: extrair e correr aplicar.bat como Administrador."
