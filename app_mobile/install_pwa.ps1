# ============================================================
#  SOS ERP - Faturacao :: correr/instalar como PWA no Windows
#  (NAO precisa de Visual Studio nem de emulador Android)
#  Uso:  powershell -ExecutionPolicy Bypass -File install_pwa.ps1
#  Depois, no Edge: clique em "Instalar" na barra de endereco.
# ============================================================
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

flutter config --enable-web | Out-Null
Write-Host "A compilar e a servir em http://localhost:8090 ..." -ForegroundColor Cyan
Write-Host "Abra esse endereco no Edge e clique em Instalar." -ForegroundColor Green

Start-Process "http://localhost:8090"
flutter run -d web-server --web-hostname 0.0.0.0 --web-port 8090 --release
