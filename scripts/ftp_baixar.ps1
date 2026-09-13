# Descarrega ficheiros da producao por FTP (binario, em streaming).
# Uso:
#   & .\scripts\ftp_baixar.ps1 -Destino C:\pasta caminho/remoto1 caminho/remoto2 ...
# Os caminhos sao relativos a raiz do projecto no servidor (public_html).
# As credenciais sao as do ftp_deploy.ps1 (lidas de la, nunca escritas no ecra).

param(
    [Parameter(Mandatory=$true)][string]$Destino,
    [Parameter(ValueFromRemainingArguments=$true)][string[]]$Remotos
)

$ErrorActionPreference = 'Stop'

$deploy = Get-Content (Join-Path $PSScriptRoot 'ftp_deploy.ps1') -Raw
$ftpHost = [regex]::Match($deploy, "\`$ftpHost\s*=\s*'([^']+)'").Groups[1].Value
$ftpUser = [regex]::Match($deploy, "\`$ftpUser\s*=\s*'([^']+)'").Groups[1].Value
$ftpPass = [regex]::Match($deploy, "\`$ftpPass\s*=\s*'([^']+)'").Groups[1].Value
$ftpRoot = [regex]::Match($deploy, "\`$ftpRoot\s*=\s*'([^']+)'").Groups[1].Value
$cred = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)

$falhas = 0

foreach ($remoto in $Remotos) {
    $remoto = $remoto.Replace('\', '/')
    $local = Join-Path $Destino ($remoto.Replace('/', '\'))
    New-Item -ItemType Directory -Force (Split-Path $local) | Out-Null

    try {
        $req = [System.Net.FtpWebRequest]::Create("ftp://$ftpHost/$ftpRoot/$remoto")
        $req.Credentials = $cred
        $req.Method = [System.Net.WebRequestMethods+Ftp]::DownloadFile
        $req.UseBinary = $true
        $req.UsePassive = $true
        $req.KeepAlive = $false
        $req.Timeout = -1
        $req.ReadWriteTimeout = -1

        $resp = $req.GetResponse()
        $entrada = $resp.GetResponseStream()
        $saida = [System.IO.File]::Create($local)
        $entrada.CopyTo($saida, 1048576)
        $saida.Close()
        $entrada.Close()
        $resp.Close()

        $mb = [math]::Round((Get-Item $local).Length / 1MB, 2)
        Write-Host "  [OK] $remoto ($mb MB)" -ForegroundColor Green
    } catch {
        $falhas++
        Write-Host "  [FALHOU] $remoto - $($_.Exception.Message)" -ForegroundColor Red
    }
}

if ($falhas -gt 0) { exit 1 }
exit 0
