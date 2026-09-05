param(
    [string]$ServerIp = '185.73.8.1',
    [string[]]$Domains = @('soserp.vip', 'superloja.vip'),
    [ValidateRange(15, 300)]
    [int]$DurationSeconds = 60,
    [ValidateRange(1, 10)]
    [int]$Attempts = 4
)

$ErrorActionPreference = 'Stop'

function Test-IsAdministrator {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Invoke-Pktmon {
    param([string[]]$Arguments)

    & pktmon.exe @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "pktmon falhou: pktmon $($Arguments -join ' ')"
    }
}

function Invoke-PktmonCleanup {
    param([string[]]$Arguments)

    # Cleanup commands can legitimately report that there is nothing to stop
    # or remove. Those messages must never abort the diagnostic capture.
    $previousPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        & pktmon.exe @Arguments 2>$null | Out-Null
    } catch {
        # Best effort cleanup only.
    } finally {
        $ErrorActionPreference = $previousPreference
    }
}

if (-not (Test-IsAdministrator)) {
    Write-Host ''
    Write-Host 'ERRO: abra o PowerShell como Administrador e execute novamente.' -ForegroundColor Red
    Write-Host ''
    Write-Host 'Exemplo:' -ForegroundColor Yellow
    Write-Host "  powershell -ExecutionPolicy Bypass -File `"$PSCommandPath`""
    exit 1
}

if (-not (Get-Command pktmon.exe -ErrorAction SilentlyContinue)) {
    throw 'O Packet Monitor (pktmon) nao existe nesta versao do Windows.'
}

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$downloads = Join-Path $env:USERPROFILE 'Downloads'
$outputDir = Join-Path $downloads "Voxility-Capture-$timestamp"
$etlPath = Join-Path $outputDir 'trafego.etl'
$pcapPath = Join-Path $outputDir 'trafego.pcapng'
$reportPath = Join-Path $outputDir 'relatorio.txt'
$zipPath = "$outputDir.zip"

New-Item -ItemType Directory -Path $outputDir -Force | Out-Null

$targetIps = New-Object System.Collections.Generic.List[string]
$targetIps.Add($ServerIp)

foreach ($domain in $Domains) {
    try {
        $resolved = Resolve-DnsName $domain -Type A -DnsOnly -ErrorAction Stop |
            Where-Object { $_.IPAddress } |
            Select-Object -ExpandProperty IPAddress -Unique

        foreach ($ip in $resolved) {
            if (-not $targetIps.Contains($ip)) {
                $targetIps.Add($ip)
            }
        }
    } catch {
        Write-Warning "Nao foi possivel resolver $domain antes da captura: $($_.Exception.Message)"
    }
}

$publicIp = 'indisponivel'
try {
    $trace = Invoke-WebRequest -UseBasicParsing -Uri 'https://www.cloudflare.com/cdn-cgi/trace' -TimeoutSec 15
    $ipLine = ($trace.Content -split "`n" | Where-Object { $_ -like 'ip=*' } | Select-Object -First 1)
    if ($ipLine) {
        $publicIp = $ipLine.Substring(3).Trim()
    }
} catch {
    $publicIp = "erro: $($_.Exception.Message)"
}

$report = New-Object System.Collections.Generic.List[string]
$report.Add('Captura para diagnostico Voxility')
$report.Add("Data: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss K')")
$report.Add("Computador: $env:COMPUTERNAME")
$report.Add("IP publico: $publicIp")
$report.Add("IP do servidor: $ServerIp")
$report.Add("Dominios: $($Domains -join ', ')")
$report.Add("IPs capturados: $($targetIps -join ', ')")
$report.Add('')

$captureStarted = $false

try {
    # The filters are temporary and are removed in the finally block.
    Invoke-PktmonCleanup @('stop')
    Invoke-PktmonCleanup @('filter', 'remove')

    $filterNumber = 0
    foreach ($ip in $targetIps) {
        $filterNumber++
        Invoke-Pktmon @('filter', 'add', "Voxility-$filterNumber", '-i', $ip)
    }

    Invoke-Pktmon @(
        'start', '--capture',
        '--comp', 'nics',
        '--type', 'all',
        '--pkt-size', '0',
        '--file-name', $etlPath,
        '--file-size', '256',
        '--log-mode', 'circular'
    )
    $captureStarted = $true

    Write-Host ''
    Write-Host 'CAPTURA INICIADA' -ForegroundColor Green
    Write-Host "IP publico: $publicIp"
    Write-Host "Destino: $outputDir"
    Write-Host ''
    Write-Host 'O navegador sera aberto. Atualize cada site 3 ou 4 vezes.' -ForegroundColor Yellow

    foreach ($domain in $Domains) {
        Start-Process "https://$domain/"
    }

    for ($attempt = 1; $attempt -le $Attempts; $attempt++) {
        $report.Add("Tentativa $attempt - $(Get-Date -Format 'HH:mm:ss')")

        foreach ($domain in $Domains) {
            $report.Add("  DNS ${domain}:")
            try {
                $dns = Resolve-DnsName $domain -Type A -DnsOnly -ErrorAction Stop |
                    Where-Object { $_.IPAddress } |
                    Select-Object -ExpandProperty IPAddress -Unique
                $report.Add("    $($dns -join ', ')")
            } catch {
                $report.Add("    ERRO: $($_.Exception.Message)")
            }

            $report.Add("  HTTPS ${domain}:")
            try {
                $curlResult = & curl.exe -4 -sS -I --connect-timeout 12 --max-time 20 "https://$domain/" 2>&1
                $report.Add("    $((($curlResult | Select-Object -First 8) -join ' | '))")
            } catch {
                $report.Add("    ERRO: $($_.Exception.Message)")
            }
        }

        $report.Add("  HTTPS direto ao servidor ${ServerIp}:")
        foreach ($domain in $Domains) {
            try {
                $direct = & curl.exe -4 -sS -I --connect-timeout 12 --max-time 20 `
                    --resolve "${domain}:443:${ServerIp}" "https://$domain/" 2>&1
                $report.Add("    ${domain}: $((($direct | Select-Object -First 5) -join ' | '))")
            } catch {
                $report.Add("    ${domain}: ERRO: $($_.Exception.Message)")
            }
        }

        try {
            $ping = Test-Connection -ComputerName $ServerIp -Count 2 -ErrorAction Stop
            $avg = [math]::Round(($ping | Measure-Object -Property ResponseTime -Average).Average, 2)
            $report.Add("  Ping ${ServerIp}: OK, media ${avg}ms")
        } catch {
            $report.Add("  Ping ${ServerIp}: ERRO: $($_.Exception.Message)")
        }

        $report.Add('')
        if ($attempt -lt $Attempts) {
            Start-Sleep -Seconds 5
        }
    }

    $elapsed = ($Attempts - 1) * 5
    $remaining = [math]::Max(0, $DurationSeconds - $elapsed)
    if ($remaining -gt 0) {
        Write-Host "A capturar por mais $remaining segundos..."
        Start-Sleep -Seconds $remaining
    }
} finally {
    if ($captureStarted) {
        Invoke-PktmonCleanup @('stop')
    }
    Invoke-PktmonCleanup @('filter', 'remove')
}

$report.Add("Fim: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss K')")
$report | Set-Content -Path $reportPath -Encoding UTF8

Invoke-Pktmon @('etl2pcap', $etlPath, '--out', $pcapPath)

$filesToZip = @($pcapPath, $reportPath)
Compress-Archive -Path $filesToZip -DestinationPath $zipPath -Force

Write-Host ''
Write-Host 'CAPTURA CONCLUIDA' -ForegroundColor Green
Write-Host "PCAPNG: $pcapPath"
Write-Host "Relatorio: $reportPath"
Write-Host "ZIP para suporte: $zipPath" -ForegroundColor Cyan
Write-Host ''
Write-Host 'Envie o ZIP ao suporte e informe que o teste foi feito na rede afetada.'
