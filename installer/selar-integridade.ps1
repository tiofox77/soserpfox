# ==========================================================================
#  soserp - selar o manifesto de integridade
# ==========================================================================
#
#  Refaz o manifesto de hashes dos ficheiros PHP da aplicacao e limpa a flag
#  de adulteracao.
#
#  PORQUE EXISTE: o vigia de integridade compara os ficheiros com o manifesto
#  feito na instalacao. Uma actualizacao legitima muda ficheiros -> os hashes
#  deixam de bater -> o vigia bloqueava o sistema todo com "Integridade dos
#  ficheiros comprometida". Ou seja, o proprio canal de actualizacao fazia-se
#  passar por ataque. Quem aplica uma actualizacao tem de voltar a selar.
#
#  CORRER ISTO SO DEPOIS DE UMA ACTUALIZACAO EM QUE SE CONFIA. Selar apaga a
#  prova: se os ficheiros tiverem mesmo sido adulterados, isto legitima-os.
#
#  Uso:
#    powershell -File installer\selar-integridade.ps1 -AppDir C:\soserp\app
#
param(
    [string]$AppDir = "C:\soserp\app"
)

$ErrorActionPreference = "Continue"

if (-not (Test-Path (Join-Path $AppDir "artisan"))) {
    Write-Host "[selar] Nao encontrei uma instalacao do soserp em $AppDir" -ForegroundColor Red
    exit 1
}

$manifesto = Join-Path $AppDir "storage\app\integridade-manifest.txt"
$flag      = Join-Path $AppDir "storage\app\integridade-falha.flag"

# Mesmo conjunto de ficheiros que o provision.ps1 sela na instalacao. Se um dos
# lados mudar, o outro tem de mudar tambem - senao ficam a falar de listas
# diferentes e o vigia acusa ficheiros "em falta".
$linhas = @()
foreach ($d in @('app','config','routes','database')) {
    $base = Join-Path $AppDir $d
    if (-not (Test-Path $base)) { continue }
    Get-ChildItem $base -Recurse -File -Filter *.php -ErrorAction SilentlyContinue | ForEach-Object {
        $rel = $_.FullName.Substring($AppDir.Length + 1) -replace '\\','/'
        $linhas += ((Get-FileHash $_.FullName -Algorithm SHA256).Hash + '|' + $rel)
    }
}
foreach ($f in @('bootstrap\app.php','public\index.php','artisan')) {
    $full = Join-Path $AppDir $f
    if (Test-Path $full) {
        $linhas += ((Get-FileHash $full -Algorithm SHA256).Hash + '|' + ($f -replace '\\','/'))
    }
}

if ($linhas.Count -eq 0) {
    Write-Host "[selar] Nao encontrei ficheiros PHP para selar - manifesto NAO foi tocado." -ForegroundColor Red
    exit 1
}

$pasta = Split-Path $manifesto -Parent
if (-not (Test-Path $pasta)) { New-Item -ItemType Directory -Force $pasta | Out-Null }

Set-Content -Path $manifesto -Value ($linhas -join "`r`n") -Encoding ascii
Remove-Item -Path $flag -Force -ErrorAction SilentlyContinue

Write-Host "[selar] Manifesto refeito: $($linhas.Count) ficheiros PHP." -ForegroundColor Green
Write-Host "[selar] Flag de integridade limpa. O sistema volta a abrir." -ForegroundColor Green
