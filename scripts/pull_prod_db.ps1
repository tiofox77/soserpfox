# Replica a BD de PRODUCAO para LOCAL (para os testes locais serem iguais a producao).
# Fluxo: (1) dispara `db:dump` na producao (rota manutencao, token) -> gera .sql.gz em
# storage/app/db-dump (NAO-publico); (2) descarrega por FTP autenticado; (3) importa para
# a BD local (DROP + CREATE); (4) apaga o dump remoto.
#
# Uso: powershell -ExecutionPolicy Bypass -File scripts\pull_prod_db.ps1
#
# ATENCAO: a BD de producao tem dados reais (PII). O dump fica no teu TEMP durante o import.

$ErrorActionPreference = 'Stop'

# --- Config ---
$ftpHost = 'ftp.soserp.vip'
$ftpUser = 'soserp'
$ftpPass = 'Softec@ngola!'
$token   = '372ea01cee5827cbc9f8dbf7d6d4180dc3c0a0cf9aa24d48'
$remotePathFtp = "ftp://$ftpHost/public_html/storage/app/db-dump/soserp_prod.sql.gz"
$mysql   = 'C:\laragon2\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$localDb = 'soserp'
$dbUser  = 'root'

$gz  = Join-Path $env:TEMP 'soserp_prod.sql.gz'
$sql = Join-Path $env:TEMP 'soserp_prod.sql'

Write-Host '1) A gerar dump na producao (db:dump)...' -ForegroundColor Cyan
$dumpUrl = "https://soserp.vip/maintenance/$token/command/db:dump"
$resp = Invoke-WebRequest -Uri $dumpUrl -TimeoutSec 300 -UseBasicParsing
# O comando devolve caminho relativo nalguns hosts e absoluto noutros.
# Validar o nome exacto do artefacto, sem depender do prefixo do caminho.
if ($resp.Content -notmatch 'OK:\s*[^\r\n]*soserp_prod\.sql\.gz') {
    Write-Host $resp.Content -ForegroundColor Red
    throw 'db:dump nao devolveu OK. Abortado.'
}
Write-Host ('   ' + (($resp.Content -split "`n" | Where-Object { $_ -match 'OK:' }) -join '')) -ForegroundColor Green

Write-Host '2) A descarregar o dump por FTP...' -ForegroundColor Cyan
if (Test-Path $gz) { Remove-Item $gz -Force }
$wc = New-Object System.Net.WebClient
$wc.Credentials = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
$wc.DownloadFile($remotePathFtp, $gz)
$mb = [math]::Round((Get-Item $gz).Length / 1MB, 2)
Write-Host "   descarregado: $gz ($mb MB)" -ForegroundColor Green

Write-Host '3) A descomprimir...' -ForegroundColor Cyan
if (Test-Path $sql) { Remove-Item $sql -Force }
$in  = [System.IO.File]::OpenRead($gz)
$dec = New-Object System.IO.Compression.GzipStream($in, [System.IO.Compression.CompressionMode]::Decompress)
$out = [System.IO.File]::Create($sql)
$dec.CopyTo($out)
$out.Close(); $dec.Close(); $in.Close()

# MariaDB inclui no INSERT os valores das colunas VIRTUAL. MySQL 8 recusa
# valores explicitos nessas colunas (erro 3105). Durante a importacao elas
# ficam normais; no fim voltam a ser calculadas, preservando a semantica.
$dump = [System.IO.File]::ReadAllText($sql)
$fullNameVirtual = '`full_name` varchar(255) GENERATED ALWAYS AS (concat(`first_name`,'' '',`last_name`)) VIRTUAL,'
$seriesVirtual = '`padrao_unico` varchar(64) GENERATED ALWAYS AS (if(`is_default` = 1,concat(`tenant_id`,''-'',`document_type`),NULL)) VIRTUAL,'
$dump = $dump.Replace($fullNameVirtual, '`full_name` varchar(255) DEFAULT NULL,')
$dump = $dump.Replace($seriesVirtual, '`padrao_unico` varchar(64) DEFAULT NULL,')
[System.IO.File]::WriteAllText($sql, $dump, (New-Object System.Text.UTF8Encoding($false)))
$dump = $null
[GC]::Collect()

Write-Host '4) A recriar a BD local e importar...' -ForegroundColor Cyan
& $mysql -u $dbUser -e "DROP DATABASE IF EXISTS ``$localDb``; CREATE DATABASE ``$localDb`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cmd /c "`"$mysql`" -u $dbUser $localDb < `"$sql`""
if ($LASTEXITCODE -ne 0) { throw "Import falhou (exit $LASTEXITCODE)" }

& $mysql -u $dbUser $localDb -e "ALTER TABLE hr_employees MODIFY full_name VARCHAR(255) GENERATED ALWAYS AS (CONCAT(first_name, ' ', last_name)) VIRTUAL; ALTER TABLE invoicing_series MODIFY padrao_unico VARCHAR(64) GENERATED ALWAYS AS (IF(is_default = 1, CONCAT(tenant_id, '-', document_type), NULL)) VIRTUAL;"
if ($LASTEXITCODE -ne 0) { throw "Reposicao das colunas calculadas falhou (exit $LASTEXITCODE)" }

Write-Host '5) A apagar o dump remoto (FTP) e limpar temporarios...' -ForegroundColor Cyan
try {
    $req = [System.Net.FtpWebRequest]::Create($remotePathFtp)
    $req.Method = [System.Net.WebRequestMethods+Ftp]::DeleteFile
    $req.Credentials = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
    $req.GetResponse().Close()
    Write-Host '   dump remoto apagado' -ForegroundColor Green
} catch { Write-Host "   aviso: nao apagou remoto: $($_.Exception.Message)" -ForegroundColor DarkYellow }
Remove-Item $gz, $sql -Force -ErrorAction SilentlyContinue

Write-Host ''
Write-Host '==== BD LOCAL SINCRONIZADA COM PRODUCAO ====' -ForegroundColor Green
Write-Host 'NOTA: colunas encriptadas (se existirem) exigem o mesmo APP_KEY da producao no .env local.' -ForegroundColor Gray
