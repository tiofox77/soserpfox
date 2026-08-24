# ==========================================================================
#  soserp - build do instalador (Opcao B)
# ==========================================================================
#
#  Monta o payload (app + binarios portateis Apache/MariaDB/PHP + vc_redist) e
#  produz o instalador:
#    - se o Inno Setup (ISCC.exe) existir  -> dist\soserp-setup-<versao>.exe
#    - senao                               -> dist\soserp-portable-<versao>.zip
#      (extrai numa maquina Windows e corre instalar.bat, sem compilador)
#
#  Corre numa MAQUINA DE BUILD (nao no cliente). Exemplo:
#    powershell -ExecutionPolicy Bypass -File installer\build.ps1 -Version 1.0.0 -PublicKey "<base64>" -SourceStack "C:\laragon2\bin"
#
param(
    [string]$Version = "1.0.0",
    [string]$PublicKey = "",
    [int]$Port = 8080,
    [int]$DbPort = 3307,
    [string]$SourceStack = "C:\laragon2\bin",
    [switch]$SkipAssets
)

$ErrorActionPreference = "Stop"
$root   = Split-Path $PSScriptRoot -Parent
$build  = Join-Path $PSScriptRoot "payload"
$appOut = Join-Path $build "app"
$stkOut = Join-Path $build "xampp"
$dist   = Join-Path $PSScriptRoot "dist"

function Log($m) { Write-Host "[build] $m" -ForegroundColor Green }
function Newest($dir) { Get-ChildItem $dir -Directory -EA SilentlyContinue | Sort-Object Name -Descending | Select-Object -First 1 }

# 1) App
Log "A montar payload/app..."
if (Test-Path $appOut) { Remove-Item $appOut -Recurse -Force }
New-Item -ItemType Directory -Force $appOut | Out-Null
robocopy $root $appOut /E /NFL /NDL /NJH /NJS /XD "$root\node_modules" "$root\.git" "$root\tests" "$root\installer" "$root\storage\logs" "$root\.idea" /XF "$root\.env" | Out-Null

Push-Location $appOut
Log "composer install --no-dev..."
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction
if (-not $SkipAssets) {
    Log "npm ci + npm run build..."
    npm ci
    npm run build
    Remove-Item (Join-Path $appOut "node_modules") -Recurse -Force -EA SilentlyContinue
}
Remove-Item (Join-Path $appOut ".env") -Force -EA SilentlyContinue
if (Test-Path (Join-Path $appOut "bootstrap\cache")) {
    Get-ChildItem (Join-Path $appOut "bootstrap\cache") -Filter *.php | Remove-Item -Force -EA SilentlyContinue
}
Pop-Location

# 2) Stack portatil (so apache/mysql/php)
Log "A montar payload/xampp (binarios portateis)..."
if (Test-Path $stkOut) { Remove-Item $stkOut -Recurse -Force }
New-Item -ItemType Directory -Force $stkOut | Out-Null
foreach ($svc in @("apache","mysql","php")) {
    $srcRoot = Join-Path $SourceStack $svc
    if (-not (Test-Path $srcRoot)) { throw "Nao encontrei $srcRoot. Ajuste -SourceStack." }
    if (Test-Path (Join-Path $srcRoot "bin")) { $src = $srcRoot }
    elseif ($svc -eq "php" -and (Test-Path (Join-Path $srcRoot "php.exe"))) { $src = $srcRoot }
    else { $n = Newest $srcRoot; if ($n) { $src = $n.FullName } else { $src = $null } }
    if (-not $src) { throw "Nao resolvi os binarios de $svc em $srcRoot." }
    Log ("  " + $svc + "  <-  " + $src)
    robocopy $src (Join-Path $stkOut $svc) /E /NFL /NDL /NJH /NJS | Out-Null
}

# 3) VC++ Redistributable
$vc = Join-Path $build "vc_redist.x64.exe"
if (-not (Test-Path $vc)) {
    try {
        Log "A descarregar vc_redist.x64.exe..."
        Invoke-WebRequest "https://aka.ms/vs/17/release/vc_redist.x64.exe" -OutFile $vc
    } catch { Log "AVISO: nao consegui descarregar o vc_redist. Coloque-o em payload a mao." }
}

# 4) Produzir o instalador
New-Item -ItemType Directory -Force $dist | Out-Null
$iscc = @(
    "C:\Program Files (x86)\Inno Setup 6\ISCC.exe",
    "C:\Program Files\Inno Setup 6\ISCC.exe",
    "$env:LOCALAPPDATA\Programs\Inno Setup 6\ISCC.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1

if ($iscc) {
    Log "Inno Setup encontrado. A compilar o .exe..."
    & $iscc "/DMyVersion=$Version" "/DMyPort=$Port" "/DMyDbPort=$DbPort" "/DMyPublicKey=$PublicKey" (Join-Path $PSScriptRoot "soserp.iss")
    Log "Feito: $dist\soserp-setup-$Version.exe"
} else {
    Log "Inno Setup NAO instalado. A produzir o pacote PORTATIL (sem compilador)..."
    Copy-Item (Join-Path $PSScriptRoot "provision.ps1")   $build -Force
    Copy-Item (Join-Path $PSScriptRoot "instalar.bat")    $build -Force
    Copy-Item (Join-Path $PSScriptRoot "desinstalar.bat") $build -Force
    Set-Content -Path (Join-Path $build "public_key.txt") -Value $PublicKey -Encoding ascii
    Set-Content -Path (Join-Path $build "portas.txt") -Value @("$Port","$DbPort") -Encoding ascii
    $zip = Join-Path $dist "soserp-portable-$Version.zip"
    if (Test-Path $zip) { Remove-Item $zip -Force }
    Log "A comprimir (pode demorar)..."
    Compress-Archive -Path (Join-Path $build "*") -DestinationPath $zip
    Log "Feito: $zip"
    Log "No cliente: extrair e correr instalar.bat como Administrador."
}
