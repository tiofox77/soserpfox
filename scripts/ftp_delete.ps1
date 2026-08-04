param([Parameter(ValueFromRemainingArguments=$true)][string[]]$Files)

$ErrorActionPreference = 'Continue'

$ftpHost = 'ftp.soserp.vip'
$ftpUser = 'soserp'
$ftpPass = 'Softec@ngola!'
$ftpRoot = 'public_html'

if (-not $Files) {
    Write-Host "Uso: scripts\ftp_delete.ps1 path1 path2 ..." -ForegroundColor Yellow
    exit 1
}

Write-Host "==== FTP DELETE $ftpHost ====" -ForegroundColor Cyan
$ok = 0
$fail = 0
$cred = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)

foreach ($f in $Files) {
    $remote = ($f -replace '\\','/').TrimStart('/')
    $uri = "ftp://$ftpHost/$ftpRoot/$remote"
    try {
        $req = [System.Net.FtpWebRequest]::Create($uri)
        $req.Method = [System.Net.WebRequestMethods+Ftp]::DeleteFile
        $req.Credentials = $cred
        $req.UsePassive = $true
        $req.UseBinary = $true
        $req.KeepAlive = $false
        $resp = $req.GetResponse()
        Write-Host "   OK  $remote" -ForegroundColor Green
        $resp.Close()
        $ok++
    } catch {
        $msg = $_.Exception.Message
        if ($msg -match '550') {
            Write-Host "   skip $remote (not found)" -ForegroundColor DarkGray
            $ok++
        } else {
            Write-Host "   ERR $remote -- $msg" -ForegroundColor Red
            $fail++
        }
    }
}

Write-Host ""
Write-Host "  Total OK: $ok  Falhas: $fail" -ForegroundColor Cyan
if ($fail -eq 0) {
    Write-Host "==== DELETE OK ====" -ForegroundColor Green
} else {
    Write-Host "==== COM ERROS ====" -ForegroundColor Yellow
    exit 1
}
