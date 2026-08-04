<#
.SYNOPSIS
    Testa conexão FTP/FTPS ao servidor soserp.vip

.DESCRIPTION
    - Testa TCP na porta 21
    - Testa autenticação FTP
    - Lista conteúdo da pasta public_html
    - Valida credenciais e permissões
#>

$ErrorActionPreference = 'Stop'

# ═══ Configuração ═══
$ftpHost   = 'ftp.soserp.vip'
$ftpPort   = 21
$ftpUser   = 'soserp'
$ftpPass   = 'Softec@ngola!'
$ftpFolder = 'public_html'

Write-Host "`n════════ TESTE FTP soserp.vip ════════`n" -ForegroundColor Cyan

# ─── 1. Teste TCP (porta 21) ───
Write-Host "[1] Teste TCP $ftpHost`:$ftpPort ... " -NoNewline
try {
    $tcp = New-Object System.Net.Sockets.TcpClient
    $tcp.Connect($ftpHost, $ftpPort)
    if ($tcp.Connected) {
        Write-Host "OK" -ForegroundColor Green
        $tcp.Close()
    }
} catch {
    Write-Host "FALHOU" -ForegroundColor Red
    Write-Host "   Erro: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

# ─── 2. Autenticação FTP ───
Write-Host "[2] Autenticação FTP (user: $ftpUser) ... " -NoNewline
try {
    $uri = "ftp://$ftpHost/$ftpFolder/"
    $request = [System.Net.FtpWebRequest]::Create($uri)
    $request.Method      = [System.Net.WebRequestMethods+Ftp]::ListDirectoryDetails
    $request.Credentials = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
    $request.UseBinary   = $true
    $request.UsePassive  = $true
    $request.KeepAlive   = $false
    $request.Timeout     = 15000

    $response = $request.GetResponse()
    Write-Host "OK ($($response.StatusDescription.Trim()))" -ForegroundColor Green

    # ─── 3. Listar pasta ───
    Write-Host "[3] Listar /$ftpFolder :`n" -ForegroundColor Cyan
    $stream = $response.GetResponseStream()
    $reader = New-Object System.IO.StreamReader($stream)
    $listing = $reader.ReadToEnd()
    $reader.Close()
    $response.Close()

    if ([string]::IsNullOrWhiteSpace($listing)) {
        Write-Host "   (pasta vazia)" -ForegroundColor Yellow
    } else {
        $lines = $listing -split "`n" | Where-Object { $_.Trim() }
        $count = $lines.Count
        Write-Host "   $count entradas encontradas:" -ForegroundColor White
        $lines | Select-Object -First 20 | ForEach-Object { Write-Host "   $_" -ForegroundColor Gray }
        if ($count -gt 20) {
            Write-Host "   ... (e mais $($count - 20) entradas)" -ForegroundColor DarkGray
        }
    }

    Write-Host "`n════════ ✅ CONEXÃO FTP OK ════════`n" -ForegroundColor Green
    exit 0
}
catch [System.Net.WebException] {
    Write-Host "FALHOU" -ForegroundColor Red
    $resp = $_.Exception.Response
    if ($resp) {
        Write-Host "   Status: $($resp.StatusCode) - $($resp.StatusDescription)" -ForegroundColor Red
    }
    Write-Host "   Erro: $($_.Exception.Message)" -ForegroundColor Red
    exit 2
}
catch {
    Write-Host "FALHOU" -ForegroundColor Red
    Write-Host "   Erro: $($_.Exception.Message)" -ForegroundColor Red
    exit 3
}
