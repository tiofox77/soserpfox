# ============================================================
#  SOS ERP - Faturacao :: Executar como app NATIVA no Windows
#  Uso:  powershell -ExecutionPolicy Bypass -File run_windows.ps1
# ============================================================
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

flutter config --enable-windows-desktop | Out-Null

function Test-VsCpp {
    $vswhere = "${env:ProgramFiles(x86)}\Microsoft Visual Studio\Installer\vswhere.exe"
    if (-not (Test-Path $vswhere)) { return $false }
    $p = & $vswhere -products * -requires Microsoft.VisualStudio.Component.VC.Tools.x86.x64 -property installationPath 2>$null
    return [bool]$p
}

if (-not (Test-VsCpp)) {
    Write-Host "Visual Studio C++ nao encontrado. A lancar o instalador (aprove o UAC)..." -ForegroundColor Yellow
    & powershell -ExecutionPolicy Bypass -File "$PSScriptRoot\install_vs.ps1"
    Write-Host "Apos instalar, FECHE este terminal, abra um novo e repita." -ForegroundColor Yellow
    exit 1
}

flutter pub get
Write-Host "A compilar e iniciar a app nativa do Windows..." -ForegroundColor Green
flutter run -d windows
