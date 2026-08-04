# FTP upload streaming (para ficheiros grandes)
# Uso: powershell -ExecutionPolicy Bypass -File scripts\ftp_upload_big.ps1 <ficheiro-local> <caminho-remoto>

param(
    [Parameter(Mandatory=$true)][string]$LocalFile,
    [Parameter(Mandatory=$true)][string]$RemotePath
)

$ErrorActionPreference = 'Stop'

$ftpHost = 'ftp.soserp.vip'
$ftpUser = 'soserp'
$ftpPass = 'Softec@ngola!'
$ftpRoot = 'public_html'

if (-not (Test-Path $LocalFile)) {
    Write-Host "Ficheiro local nao existe: $LocalFile" -ForegroundColor Red
    exit 1
}

$sizeMb = [math]::Round((Get-Item $LocalFile).Length / 1MB, 1)
Write-Host "Upload streaming: $LocalFile -> ftp://$ftpHost/$ftpRoot/$RemotePath ($sizeMb MB)" -ForegroundColor Cyan

$uri = "ftp://$ftpHost/$ftpRoot/$RemotePath"
$req = [System.Net.FtpWebRequest]::Create($uri)
$req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
$req.Credentials = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
$req.UseBinary = $true
$req.UsePassive = $true
$req.KeepAlive = $false
$req.Timeout = -1
$req.ReadWriteTimeout = -1

$totalBytes = (Get-Item $LocalFile).Length
$req.ContentLength = $totalBytes

$reqStream = $req.GetRequestStream()
$fileStream = [System.IO.File]::OpenRead($LocalFile)

$buf = New-Object byte[] 1048576  # 1 MB chunks
$sent = 0
$startTime = Get-Date
$lastReport = $startTime

try {
    while (($n = $fileStream.Read($buf, 0, $buf.Length)) -gt 0) {
        $reqStream.Write($buf, 0, $n)
        $sent += $n
        $now = Get-Date
        if (($now - $lastReport).TotalSeconds -ge 2) {
            $pct = [math]::Round(($sent / $totalBytes) * 100, 1)
            $mbps = [math]::Round(($sent / 1MB) / ($now - $startTime).TotalSeconds, 2)
            Write-Host ("  $pct% ($([math]::Round($sent/1MB,1))/$sizeMb MB, $mbps MB/s)") -ForegroundColor Gray
            $lastReport = $now
        }
    }
    $fileStream.Close()
    $reqStream.Close()
    $resp = $req.GetResponse()
    $resp.Close()
    $elapsed = [math]::Round(((Get-Date) - $startTime).TotalSeconds, 1)
    Write-Host "OK - $sizeMb MB enviados em $elapsed s" -ForegroundColor Green
    exit 0
} catch {
    if ($fileStream) { $fileStream.Close() }
    if ($reqStream) { $reqStream.Close() }
    Write-Host "FALHOU: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
