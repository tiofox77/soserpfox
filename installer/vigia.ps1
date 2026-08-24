# ==========================================================================
#  soserp - vigia (watchdog). Corre em tarefa agendada (arranque + cada 2 min).
#  Garante Apache + MySQL a correr e a app a responder; reinicia o que cair.
# ==========================================================================
param([int]$Port = 8080, [string]$AppDir = "")
$ErrorActionPreference = "Continue"
$log = Join-Path $PSScriptRoot "vigia.log"
function L($m) { try { ("{0}  {1}" -f (Get-Date -Format s), $m) | Add-Content -Path $log } catch {} }

# 1) Servicos a correr (arranque automatico ja os sobe; isto apanha quedas)
foreach ($s in 'soserp-mysql','soserp-apache') {
    $svc = Get-Service $s -ErrorAction SilentlyContinue
    if (-not $svc) { L "servico $s nao existe"; continue }
    if ($svc.Status -ne 'Running') { L "a arrancar $s (estava $($svc.Status))"; Start-Service $s -ErrorAction SilentlyContinue }
}

# 2) A app responde? (health-check /up do Laravel). Se nao, reinicia o Apache.
try {
    $r = Invoke-WebRequest ("http://127.0.0.1:{0}/up" -f $Port) -TimeoutSec 8 -UseBasicParsing
    if ($r.StatusCode -ne 200) { throw "status $($r.StatusCode)" }
} catch {
    L "app nao responde ($($_.Exception.Message)); a reiniciar Apache"
    Restart-Service soserp-apache -Force -ErrorAction SilentlyContinue
}

# 3) Estado da licenca para o agente da bandeja ler (icone junto ao relogio).
# `licenca:ver --json` corre offline: valida assinatura, validade, maquina,
# relogio recuado e dias sem ligar a casa.
if (-not $AppDir) { $AppDir = Join-Path $PSScriptRoot "app" }
$php = Join-Path $PSScriptRoot "xampp\php\php.exe"
if ((Test-Path $php) -and (Test-Path $AppDir)) {
    try {
        Push-Location $AppDir
        $json = & $php artisan licenca:ver --json 2>$null
        Pop-Location
        if ($json) {
            $destino = Join-Path $AppDir "storage\app\estado-licenca.json"
            Set-Content -Path $destino -Value ($json -join "`n") -Encoding ascii
        }
    } catch { L "falha ao gravar estado da licenca: $($_.Exception.Message)" }
}
