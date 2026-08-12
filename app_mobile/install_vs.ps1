# ============================================================
#  Instala o Visual Studio Build Tools (C++) - pre-requisito do build nativo.
#  Auto-eleva (pede permissoes de administrador via UAC).
#  Uso:  powershell -ExecutionPolicy Bypass -File install_vs.ps1
# ============================================================

$principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltinRole]::Administrator)) {
    Write-Host "A pedir permissoes de administrador (UAC)..." -ForegroundColor Yellow
    Start-Process powershell -Verb RunAs -ArgumentList "-NoExit -ExecutionPolicy Bypass -File `"$PSCommandPath`""
    exit
}

Write-Host "==== Instalacao do Visual Studio Build Tools (C++) ====" -ForegroundColor Cyan
Write-Host "Download grande (~2-5 GB). Aguarde ate ao fim..." -ForegroundColor Yellow

winget install --id Microsoft.VisualStudio.2022.BuildTools -e --override "--quiet --wait --norestart --add Microsoft.VisualStudio.Workload.VCTools --includeRecommended" --accept-package-agreements --accept-source-agreements

$vswhere = "${env:ProgramFiles(x86)}\Microsoft Visual Studio\Installer\vswhere.exe"
$ok = (Test-Path $vswhere) -and (& $vswhere -products * -requires Microsoft.VisualStudio.Component.VC.Tools.x86.x64 -property installationPath 2>$null)
if ($ok) {
    Write-Host ""
    Write-Host "[OK] Visual Studio C++ instalado!" -ForegroundColor Green
    Write-Host "Agora FECHE este terminal, abra um novo e gere o instalador (2-GERAR-INSTALADOR-EXE.bat)." -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "[!] Instalacao terminou mas o C++ ainda nao e detetado." -ForegroundColor Yellow
    Write-Host "    Abra o 'Visual Studio Installer' e confirme a carga 'Desktop development with C++'." -ForegroundColor Yellow
}
Write-Host ""
Read-Host "Prima Enter para fechar"
