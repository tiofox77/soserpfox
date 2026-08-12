# ============================================================
#  SOS ERP - Faturacao :: Build do .exe + Instalador (Inno Setup)
#  Uso:  powershell -ExecutionPolicy Bypass -File installer\build_installer.ps1
# ============================================================
$ErrorActionPreference = 'Stop'
$app = Split-Path $PSScriptRoot -Parent
Set-Location $app

# 1) Build nativo (build_windows.ps1 instala o VS C++ se faltar)
Write-Host "==> A compilar a app nativa (release)..." -ForegroundColor Cyan
& powershell -ExecutionPolicy Bypass -File "$app\build_windows.ps1"
if ($LASTEXITCODE -ne 0) { exit 1 }

$rel = "build\windows\x64\runner\Release"
if (-not (Test-Path "$rel\soserp_faturacao.exe")) {
    Write-Host "Build nao encontrado. Verifique o Visual Studio C++." -ForegroundColor Red
    exit 1
}

# 2) Localizar Inno Setup (ISCC) em varias localizacoes (inclui instalacao por-utilizador)
function Find-Iscc {
    $cands = @(
        "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
        "${env:ProgramFiles}\Inno Setup 6\ISCC.exe",
        "${env:LOCALAPPDATA}\Programs\Inno Setup 6\ISCC.exe"
    )
    foreach ($c in $cands) { if (Test-Path $c) { return $c } }
    $reg = Get-ItemProperty `
        "HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*", `
        "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*", `
        "HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*" -ErrorAction SilentlyContinue |
        Where-Object { $_.DisplayName -like "*Inno Setup*" -and $_.InstallLocation }
    foreach ($r in $reg) {
        $p = Join-Path $r.InstallLocation "ISCC.exe"
        if (Test-Path $p) { return $p }
    }
    $cmd = Get-Command iscc -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    return $null
}

$iscc = Find-Iscc
if (-not $iscc) {
    Write-Host "Inno Setup nao encontrado. A instalar via winget..." -ForegroundColor Yellow
    winget install --id JRSoftware.InnoSetup -e --accept-package-agreements --accept-source-agreements
    $iscc = Find-Iscc
}
if (-not $iscc) {
    Write-Host "Inno Setup instalado mas ISCC.exe nao localizado. Instale manualmente em https://jrsoftware.org/isdl.php" -ForegroundColor Red
    exit 1
}

# 3) Compilar instalador
Write-Host "==> A gerar o instalador (ISCC: $iscc)..." -ForegroundColor Cyan
& $iscc "installer\installer.iss"

$out = "installer\Output\SOSERP-Faturacao-Setup.exe"
if (Test-Path $out) {
    Write-Host ""
    Write-Host "[OK] Instalador pronto: $(Resolve-Path $out)" -ForegroundColor Green
} else {
    Write-Host "Instalador nao gerado - verifique os erros acima." -ForegroundColor Red
    exit 1
}
