# ==========================================================================
#  soserp - integridade dos ficheiros PHP. Tarefa agendada (arranque + horaria).
#  Compara os hashes actuais com o manifesto assinado no momento da instalacao;
#  se algo foi adulterado, regista e levanta uma flag que a app le para bloquear.
# ==========================================================================
param(
    [string]$AppDir   = "C:\soserp\app",
    [string]$Manifest = "C:\soserp\app\storage\app\integridade-manifest.txt"
)
$ErrorActionPreference = "Continue"
$log  = Join-Path $PSScriptRoot "integridade.log"
$flag = Join-Path $AppDir "storage\app\integridade-falha.flag"
function L($m) { try { ("{0}  {1}" -f (Get-Date -Format s), $m) | Add-Content -Path $log } catch {} }

if (-not (Test-Path $Manifest)) { L "sem manifesto - nada a verificar"; exit }

# manifesto: cada linha "<sha256>|<caminho relativo>"
$esperado = @{}
foreach ($linha in Get-Content $Manifest) {
    $p = $linha -split '\|', 2
    if ($p.Count -eq 2) { $esperado[$p[1]] = $p[0] }
}

$problemas = @()
foreach ($rel in $esperado.Keys) {
    $f = Join-Path $AppDir $rel
    if (-not (Test-Path $f)) { $problemas += "FALTA $rel"; continue }
    $h = (Get-FileHash -Path $f -Algorithm SHA256).Hash
    if ($h -ne $esperado[$rel]) { $problemas += "ALTERADO $rel" }
}

if ($problemas.Count -gt 0) {
    L ("INTEGRIDADE FALHOU (" + $problemas.Count + "): " + (($problemas | Select-Object -First 20) -join '; '))
    Set-Content -Path $flag -Value ($problemas -join "`r`n") -Encoding ascii
} else {
    L ("integridade OK (" + $esperado.Count + " ficheiros)")
    Remove-Item -Path $flag -Force -ErrorAction SilentlyContinue
}
