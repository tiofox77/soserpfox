param([Parameter(Mandatory=$true)][string]$Destino, [string]$Responsavel = 'carlosfox1782@gmail.com', [string]$Anterior = 'e85dd5d4e5bb')
$ErrorActionPreference = 'Stop'
if (Test-Path (Join-Path $Destino 'openclaw-token.txt')) { throw 'Token ja preparado; nao sobrescrever.' }
New-Item -ItemType Directory -Force $Destino | Out-Null
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$secret = New-Object byte[] 32
$prefixBytes = New-Object byte[] 6
$rng.GetBytes($secret)
$rng.GetBytes($prefixBytes)
$hex = ([BitConverter]::ToString($secret)).Replace('-', '').ToLowerInvariant()
$prefix = ([BitConverter]::ToString($prefixBytes)).Replace('-', '').ToLowerInvariant()
$sha = [System.Security.Cryptography.SHA256]::Create()
$hash = ([BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($hex)))).Replace('-', '').ToLowerInvariant()
$config = Get-Content (Join-Path $PSScriptRoot '../config/agent.php') -Raw
$scopeSection = $config.Substring($config.IndexOf("'escopos'"), $config.IndexOf("'token' =>") - $config.IndexOf("'escopos'"))
$scopes = @([regex]::Matches($scopeSection, "'([a-z]+:[a-z]+)'\s*=>") | ForEach-Object { $_.Groups[1].Value })
$request = @{prefix=$prefix; token_hash=$hash; owner_email=$Responsavel; replace_prefix=$Anterior; issued_at=[DateTime]::UtcNow.ToString('o'); days=365; scopes=$scopes}
$utf8 = New-Object Text.UTF8Encoding($false)
[IO.File]::WriteAllText((Join-Path $Destino 'openclaw-token.txt'), "oclaw_${prefix}_${hex}", $utf8)
$private = Join-Path $PSScriptRoot '../storage/app/agente'
New-Item -ItemType Directory -Force $private | Out-Null
[IO.File]::WriteAllText((Join-Path $private 'provisionar.json'), ($request | ConvertTo-Json -Depth 5), $utf8)
$rng.Dispose()
$sha.Dispose()
Write-Output "Preparado prefixo $prefix, $($scopes.Count) escopos. Segredo apenas no ficheiro local."
