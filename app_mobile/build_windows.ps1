# ============================================================
#  SOS ERP - Faturacao :: BUILD do executavel Windows (.exe)
#  Gera build\windows\x64\runner\Release e um ZIP distribuivel.
#  Uso:  powershell -ExecutionPolicy Bypass -File build_windows.ps1
# ============================================================
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

function Test-VsCpp {
    $vswhere = "${env:ProgramFiles(x86)}\Microsoft Visual Studio\Installer\vswhere.exe"
    if (-not (Test-Path $vswhere)) { return $false }
    $p = & $vswhere -products * -requires Microsoft.VisualStudio.Component.VC.Tools.x86.x64 -property installationPath 2>$null
    return [bool]$p
}

if (-not (Test-VsCpp)) {
    Write-Host "Visual Studio C++ nao encontrado." -ForegroundColor Yellow
    Write-Host "A instalacao precisa de permissoes de administrador (UAC)." -ForegroundColor Yellow
    Write-Host "A lancar o instalador elevado (aprove o pedido de UAC)..." -ForegroundColor Cyan
    & powershell -ExecutionPolicy Bypass -File "$PSScriptRoot\install_vs.ps1"
    Write-Host ""
    Write-Host "Apos instalar o Visual Studio, FECHE este terminal, abra um novo e repita." -ForegroundColor Yellow
    Write-Host "(Alternativa imediata sem VS: install_pwa.ps1)" -ForegroundColor Cyan
    exit 1
}

flutter config --enable-windows-desktop | Out-Null
flutter pub get
flutter build windows --release

$rel = "build\windows\x64\runner\Release"
if (-not (Test-Path $rel)) { $rel = "build\windows\runner\Release" }

if (Test-Path "$rel\soserp_faturacao.exe") {
    $zip = "soserp_faturacao_windows.zip"
    if (Test-Path $zip) { Remove-Item $zip -Force }
    Compress-Archive -Path "$rel\*" -DestinationPath $zip
    Write-Host ""
    Write-Host "[OK] Build pronto:" -ForegroundColor Green
    Write-Host "   Pasta : $rel\soserp_faturacao.exe"
    Write-Host "   ZIP   : $(Resolve-Path $zip)"
} else {
    Write-Host "Build nao encontrado - verifique os erros acima (Visual Studio C++)." -ForegroundColor Red
    exit 1
}
