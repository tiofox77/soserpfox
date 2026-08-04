# Deploy FTP - envia ficheiros especificos para soserp.vip
# Uso:
#   powershell -ExecutionPolicy Bypass -File scripts\ftp_deploy.ps1 file1.php file2.blade.php ...
#   Os caminhos sao relativos a raiz do projecto (ex: app/Livewire/Sales/InvoiceCreate.php)

param(
    [Parameter(ValueFromRemainingArguments=$true)]
    [string[]]$FilesToDeploy,

    # Confirma so pelo tamanho, sem reler o ficheiro para comparar o MD5.
    # Util em lotes muito grandes; por omissao confirma o conteudo.
    [switch]$Rapido
)

$ErrorActionPreference = 'Stop'

# === Configuracao ===
$ftpHost   = 'ftp.soserp.vip'
$ftpUser   = 'soserp'
$ftpPass   = 'Softec@ngola!'
$ftpRoot   = 'public_html'
$localRoot = (Resolve-Path "$PSScriptRoot\..").Path

# === Validar argumentos ===
if (-not $FilesToDeploy -or $FilesToDeploy.Count -eq 0) {
    Write-Host ""
    Write-Host "USO: ftp_deploy.ps1 <ficheiro1> <ficheiro2> ..." -ForegroundColor Yellow
    Write-Host "Exemplo:" -ForegroundColor Gray
    Write-Host "  .\scripts\ftp_deploy.ps1 app/Livewire/Invoicing/Sales/InvoiceCreate.php resources/views/livewire/invoicing/faturas-venda/create.blade.php" -ForegroundColor Gray
    Write-Host ""
    exit 1
}

# === Validar ficheiros (expande dirs recursivamente) ===
$files = @()
foreach ($f in $FilesToDeploy) {
    $f = $f.Replace('\', '/')
    $localPath = Join-Path $localRoot $f
    if (-not (Test-Path $localPath)) {
        Write-Host "  [SKIP] $f (nao existe)" -ForegroundColor DarkYellow
        continue
    }
    $item = Get-Item $localPath
    if ($item.PSIsContainer) {
        # Expandir diretorio recursivamente
        $children = Get-ChildItem $localPath -Recurse -File
        foreach ($c in $children) {
            $rel = $c.FullName.Substring($localRoot.Length).TrimStart('\','/').Replace('\','/')
            $files += $rel
        }
        Write-Host ("  [DIR] " + $f + " -> " + $children.Count + " ficheiros") -ForegroundColor Cyan
    } else {
        $files += $f
    }
}

if ($files.Count -eq 0) {
    Write-Host "Nenhum ficheiro valido para enviar." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "==== DEPLOY FTP soserp.vip ====" -ForegroundColor Cyan
Write-Host "Origem:  $localRoot" -ForegroundColor Gray
Write-Host "Destino: ftp://$ftpHost/$ftpRoot/" -ForegroundColor Gray
Write-Host "Ficheiros: $($files.Count)" -ForegroundColor White
Write-Host ""

$cred = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
$createdDirs = @{}

function Ensure-FtpDir([string]$relativeDir) {
    if ([string]::IsNullOrWhiteSpace($relativeDir)) { return }
    $relativeDir = $relativeDir.Replace('\','/')
    if ($createdDirs.ContainsKey($relativeDir)) { return }

    $parent = Split-Path $relativeDir -Parent
    if ($parent) { Ensure-FtpDir($parent.Replace('\','/')) }

    $uri = "ftp://$ftpHost/$ftpRoot/$relativeDir"
    try {
        $req = [System.Net.FtpWebRequest]::Create($uri)
        $req.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
        $req.Credentials = $cred
        $req.UsePassive = $true
        $req.KeepAlive = $false
        $resp = $req.GetResponse()
        $resp.Close()
    } catch {
        # 550 = ja existe; ignorar
    }
    $createdDirs[$relativeDir] = $true
}

# MD5 do ficheiro NO SERVIDOR: descarrega-o de volta e calcula o hash.
# Confirma o CONTEUDO, nao so o tamanho. $null se nao der para ler.
function Get-RemoteMd5([string]$rel) {
    $uri = "ftp://$ftpHost/$ftpRoot/$rel"
    try {
        $req = [System.Net.FtpWebRequest]::Create($uri)
        $req.Method = [System.Net.WebRequestMethods+Ftp]::DownloadFile
        $req.Credentials = $cred
        $req.UseBinary = $true
        $req.UsePassive = $true
        $req.KeepAlive = $false
        $req.Timeout = 60000

        $resp = $req.GetResponse()
        $ms = New-Object System.IO.MemoryStream
        $resp.GetResponseStream().CopyTo($ms)
        $resp.Close()

        $bytes = $ms.ToArray()
        $ms.Dispose()

        $md5 = [System.Security.Cryptography.MD5]::Create()
        $hash = [System.BitConverter]::ToString($md5.ComputeHash($bytes)).Replace('-','').ToLower()
        $md5.Dispose()

        return $hash
    } catch {
        return $null
    }
}

function Get-LocalMd5([byte[]]$bytes) {
    $md5 = [System.Security.Cryptography.MD5]::Create()
    $hash = [System.BitConverter]::ToString($md5.ComputeHash($bytes)).Replace('-','').ToLower()
    $md5.Dispose()
    return $hash
}

# Tamanho do ficheiro NO SERVIDOR. -1 se nao existir ou nao der para saber.
function Get-RemoteSize([string]$rel) {
    $uri = "ftp://$ftpHost/$ftpRoot/$rel"
    try {
        $req = [System.Net.FtpWebRequest]::Create($uri)
        $req.Method = [System.Net.WebRequestMethods+Ftp]::GetFileSize
        $req.Credentials = $cred
        $req.UseBinary = $true
        $req.UsePassive = $true
        $req.KeepAlive = $false
        $req.Timeout = 30000
        $resp = $req.GetResponse()
        $size = $resp.ContentLength
        $resp.Close()
        return [int64]$size
    } catch {
        return [int64](-1)
    }
}

function Send-Bytes([string]$rel, [byte[]]$bytes) {
    $uri = "ftp://$ftpHost/$ftpRoot/$rel"
    $req = [System.Net.FtpWebRequest]::Create($uri)
    $req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $req.Credentials = $cred
    $req.UseBinary = $true
    $req.UsePassive = $true
    $req.KeepAlive = $false
    $req.Timeout = 60000
    $req.ContentLength = $bytes.Length

    $stream = $req.GetRequestStream()
    $stream.Write($bytes, 0, $bytes.Length)
    $stream.Close()

    $resp = $req.GetResponse()
    $status = $resp.StatusCode
    $resp.Close()

    return $status
}

# Envia e CONFIRMA que ficou no servidor, com tentativas.
#
# A versao anterior fazia GetResponse() sem olhar para o codigo de estado e
# escrevia "OK (N bytes)" com o tamanho do ficheiro LOCAL — ou seja, dizia
# sempre OK. Numa auditoria apareceram 260 ficheiros que o script tinha dado
# como enviados e nao estavam no servidor: classes em falta que so se viam
# como erro 500 aleatorio para os utilizadores, e correccoes que se julgavam
# publicadas. Agora o tamanho e lido DO SERVIDOR e comparado.
function Upload-File([string]$localPath, [string]$relativePath) {
    $rel = $relativePath.Replace('\','/')
    $remoteDir = (Split-Path $rel -Parent).Replace('\','/')
    if ($remoteDir) { Ensure-FtpDir $remoteDir }

    $bytes = [System.IO.File]::ReadAllBytes($localPath)
    $esperado = [int64]$bytes.Length
    $hashLocal = Get-LocalMd5 $bytes
    $ultimoErro = $null

    for ($tentativa = 1; $tentativa -le 3; $tentativa++) {
        try {
            $status = Send-Bytes -rel $rel -bytes $bytes
        } catch {
            $ultimoErro = $_.Exception.Message
            Start-Sleep -Milliseconds (400 * $tentativa)
            continue
        }

        # 1) tamanho: barato, descarta logo a maioria das falhas
        $remoto = Get-RemoteSize $rel

        if ($remoto -lt 0) {
            $ultimoErro = "enviado (estado $status) mas o servidor nao confirma o ficheiro"
            Start-Sleep -Milliseconds (400 * $tentativa)
            continue
        }

        if ($remoto -ne $esperado) {
            $ultimoErro = "tamanho no servidor $remoto, esperado $esperado"
            Start-Sleep -Milliseconds (400 * $tentativa)
            continue
        }

        # 2) conteudo: le o ficheiro de volta e compara o MD5. E o que garante
        #    que o que la esta e mesmo o que saiu daqui, e nao apenas algo do
        #    mesmo tamanho. Pode saltar-se com -Rapido em lotes grandes.
        if ($Rapido) {
            return @{ Ok = $true; Size = $esperado; Tentativas = $tentativa; Verificacao = 'tamanho' }
        }

        $hashRemoto = Get-RemoteMd5 $rel

        if ($hashRemoto -eq $hashLocal) {
            return @{ Ok = $true; Size = $esperado; Tentativas = $tentativa; Verificacao = 'md5' }
        }

        if ($null -eq $hashRemoto) {
            $ultimoErro = "tamanho confere mas nao foi possivel reler o ficheiro para confirmar o conteudo"
        } else {
            $ultimoErro = "CONTEUDO DIFERENTE: md5 no servidor $hashRemoto, esperado $hashLocal"
        }

        Start-Sleep -Milliseconds (400 * $tentativa)
    }

    return @{ Ok = $false; Size = $esperado; Erro = $ultimoErro; Tentativas = 3 }
}

$ok = 0
$fail = 0
$totalBytes = 0

$falhados = @()

foreach ($rel in $files) {
    $local = Join-Path $localRoot $rel
    Write-Host ("   -> " + $rel + " ... ") -NoNewline
    try {
        $r = Upload-File -localPath $local -relativePath $rel

        if ($r.Ok) {
            $extra = ''
            if ($r.Tentativas -gt 1) { $extra = ' apos ' + $r.Tentativas + ' tentativas' }
            # "confirmado" e nao "OK": foi relido do servidor
            $como = if ($r.Verificacao -eq 'md5') { 'md5' } else { 'tamanho' }
            Write-Host ('confirmado por ' + $como + ' (' + $r.Size + ' bytes' + $extra + ')') -ForegroundColor Green
            $ok++
            $totalBytes += $r.Size
        } else {
            Write-Host "FALHOU (3 tentativas)" -ForegroundColor Red
            Write-Host ("      " + $r.Erro) -ForegroundColor Red
            $fail++
            $falhados += $rel
        }
    } catch {
        Write-Host "FALHOU" -ForegroundColor Red
        Write-Host ("      " + $_.Exception.Message) -ForegroundColor Red
        $fail++
        $falhados += $rel
    }
}

Write-Host ""
Write-Host "----------------------------------------" -ForegroundColor Gray
$kb = [math]::Round($totalBytes / 1024, 2)
$summary = ('  Enviados: ' + $ok + '   Falhas: ' + $fail + '   Total: ' + $kb + ' KB')
Write-Host $summary -ForegroundColor White

if ($fail -eq 0) {
    Write-Host ""
    Write-Host "==== DEPLOY CONFIRMADO ====" -ForegroundColor Green
    if ($Rapido) {
        Write-Host "  Confirmado pelo tamanho lido do servidor (-Rapido: sem comparar conteudo)." -ForegroundColor DarkGray
    } else {
        Write-Host "  Cada ficheiro foi relido do servidor e o MD5 bate certo com o local." -ForegroundColor DarkGray
    }
    exit 0
} else {
    Write-Host ""
    Write-Host "==== DEPLOY COM FALHAS ====" -ForegroundColor Yellow
    Write-Host "  NAO confirmados no servidor:" -ForegroundColor Yellow
    foreach ($f in $falhados) { Write-Host ("    " + $f) -ForegroundColor Red }
    Write-Host ""
    Write-Host "  Repita o envio destes ficheiros antes de dar o deploy por terminado." -ForegroundColor Yellow
    exit 2
}
