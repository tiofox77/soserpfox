# Compara os PACOTES de vendor/ entre o local e o servidor.
#
# O `php artisan deploy:verify` nao olha para vendor/ de proposito: e enorme e
# instalado pelo composer no servidor. So que um vendor incompleto nao da erro
# nenhum ate alguem abrir a pagina que usa a classe em falta, e ai aparece um
# 500 sem relacao aparente com nada. Foi o que aconteceu com composer/pcre:
# faltava no servidor e so a exportacao do POS para Excel o tocava.
#
# Isto lista os directorios de pacotes nos dois lados e diz quais faltam.
# E so LEITURA: nao envia nem apaga nada.
#
# ASCII puro de proposito (ver nota em ftp_vendor_push.ps1).
#
#   powershell -File scripts\ftp_vendor_audit.ps1

$ErrorActionPreference = 'Stop'

$ftpHost   = 'ftp.soserp.vip'
$ftpUser   = 'soserp'
$ftpPass   = 'Softec@ngola!'
$ftpRoot   = 'public_html'
$localRoot = (Resolve-Path "$PSScriptRoot\..").Path

$cred = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)

function Get-FtpDirs([string]$relativo) {
    $uri = "ftp://$ftpHost/$ftpRoot/$relativo"
    try {
        $req = [System.Net.FtpWebRequest]::Create($uri)
        $req.Method      = [System.Net.WebRequestMethods+Ftp]::ListDirectory
        $req.Credentials = $cred
        $req.UseBinary   = $true
        $req.UsePassive  = $true
        $req.Timeout     = 30000

        $resp   = $req.GetResponse()
        $leitor = New-Object System.IO.StreamReader($resp.GetResponseStream())
        $linhas = $leitor.ReadToEnd() -split "`r?`n" | Where-Object { $_ -and $_ -notmatch '^\.\.?$' }
        $leitor.Close(); $resp.Close()

        # A listagem pode vir com caminho completo em alguns servidores.
        return $linhas | ForEach-Object { ($_ -split '/')[-1] } | Where-Object { $_ }
    } catch {
        return @()
    }
}

Write-Host ""
Write-Host "==== AUDITORIA DE vendor/ em $ftpHost ====" -ForegroundColor Cyan
Write-Host ""

$vendorLocal  = Join-Path $localRoot 'vendor'
$fornecedores = Get-ChildItem $vendorLocal -Directory | Select-Object -ExpandProperty Name | Sort-Object

$emFalta = @()
$total   = 0

foreach ($fornecedor in $fornecedores) {
    $pacotesLocais = Get-ChildItem (Join-Path $vendorLocal $fornecedor) -Directory -ErrorAction SilentlyContinue |
                     Select-Object -ExpandProperty Name

    if (-not $pacotesLocais) { continue }

    $pacotesRemotos = Get-FtpDirs "vendor/$fornecedor"

    foreach ($pacote in $pacotesLocais) {
        $total++
        if ($pacotesRemotos -notcontains $pacote) {
            $emFalta += "vendor/$fornecedor/$pacote"
        }
    }
}

Write-Host "  $total pacotes locais comparados" -ForegroundColor Gray
Write-Host ""

if ($emFalta.Count -eq 0) {
    Write-Host "  TODOS OS PACOTES PRESENTES no servidor." -ForegroundColor Green
} else {
    Write-Host "AUSENTES no servidor ($($emFalta.Count))" -ForegroundColor Red
    foreach ($p in $emFalta) { Write-Host "  $p" -ForegroundColor Red }
    Write-Host ""
    Write-Host "Confirme primeiro se estao no composer.lock. Para enviar:" -ForegroundColor Yellow
    Write-Host "  powershell -File scripts\ftp_vendor_push.ps1 <pacote> [<pacote>...]" -ForegroundColor Gray
}

Write-Host ""
