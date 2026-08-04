# Envia um pacote de vendor/ para o servidor, sem o que nao corre.
#
# NOTA DE CODIFICACAO: este ficheiro e ASCII puro, de proposito. O PowerShell
# 5.1 le um .ps1 sem BOM como ANSI, e um acento numa string ou comentario
# rebenta o parser com "the string is missing the terminator". Os scripts que
# ja existiam aqui seguem a mesma regra.
#
# Fica de fora: .git/, tests/, docs/, exemplos e ficheiros de CI. Num pacote
# como o twilio/sdk isso e a diferenca entre centenas e milhares de ficheiros.
#
#   powershell -File scripts\ftp_vendor_push.ps1 composer/pcre
#   powershell -File scripts\ftp_vendor_push.ps1 phpoffice/phpexcel twilio/sdk

param([Parameter(ValueFromRemainingArguments = $true)][string[]]$pacotes)

$ErrorActionPreference = 'Stop'

if (-not $pacotes -or $pacotes.Count -eq 0) {
    Write-Host "Indique pelo menos um pacote (ex.: composer/pcre)." -ForegroundColor Red
    exit 1
}

$localRoot = (Resolve-Path "$PSScriptRoot\..").Path
$excluir   = '[\\/](\.git|\.github|tests?|Tests|docs?|examples?)[\\/]'

$ficheiros = @()

foreach ($pacote in $pacotes) {
    $dir = Join-Path $localRoot ("vendor\" + $pacote.Replace('/', '\'))

    if (-not (Test-Path $dir)) {
        Write-Host "Pacote nao encontrado localmente: $pacote" -ForegroundColor Red
        continue
    }

    $ficheiros += Get-ChildItem $dir -Recurse -File |
        Where-Object { $_.FullName -notmatch $excluir } |
        ForEach-Object { $_.FullName.Substring($localRoot.Length + 1).Replace('\', '/') }
}

if ($ficheiros.Count -eq 0) {
    Write-Host "Nada a enviar." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "$($ficheiros.Count) ficheiro(s) a enviar de $($pacotes.Count) pacote(s)" -ForegroundColor Cyan
Write-Host ""

# Em lotes: os caminhos vao como argumentos e o Windows corta a linha de
# comandos aos ~32 mil caracteres. Um pacote como o twilio/sdk tem milhares de
# ficheiros e rebentava com "is too long" antes de enviar um unico.
$loteMax    = 150
$total      = $ficheiros.Count
$totalLotes = [Math]::Ceiling($total / $loteMax)
$enviados   = 0
$falhas     = 0

for ($i = 0; $i -lt $total; $i += $loteMax) {
    $lote  = $ficheiros[$i..([Math]::Min($i + $loteMax - 1, $total - 1))]
    $nLote = [Math]::Floor($i / $loteMax) + 1

    Write-Host "--- lote $nLote/$totalLotes ($($lote.Count) ficheiros) ---" -ForegroundColor DarkGray

    $saida = & powershell -File (Join-Path $PSScriptRoot 'ftp_deploy.ps1') @lote 2>&1

    $linha = $saida | Select-String -Pattern 'Enviados:\s*(\d+)\s+Falhas:\s*(\d+)' | Select-Object -First 1

    if ($linha) {
        $enviados += [int]$linha.Matches[0].Groups[1].Value
        $falhas   += [int]$linha.Matches[0].Groups[2].Value
    } else {
        Write-Host "  lote sem confirmacao. Ultimas linhas:" -ForegroundColor Yellow
        $saida | Select-Object -Last 5 | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkYellow }
        $falhas += $lote.Count
    }
}

$cor = if ($falhas -eq 0) { 'Green' } else { 'Red' }

Write-Host ""
Write-Host "========================================"
Write-Host "  TOTAL  Enviados: $enviados   Falhas: $falhas   de $total" -ForegroundColor $cor
