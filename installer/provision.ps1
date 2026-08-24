# ==========================================================================
#  soserp - provisionamento pos-instalacao (Opcao B: binarios portateis)
# ==========================================================================
#
#  Configura o stack portatil (Apache + MariaDB + PHP do payload), cria a base
#  de dados, gera o .env, migra, escreve o vhost, regista os servicos com
#  arranque automatico e activa a licenca. NAO corre o instalador do XAMPP.
#
#  Uso (elevado):
#    powershell -ExecutionPolicy Bypass -File provision.ps1 -InstallDir "C:\soserp" -Port 8080 -DbPort 3307 -LicenseFile "C:\soserp\license.key" -PublicKey "<base64>"
#
param(
    [string]$InstallDir = "C:\soserp",
    [int]$Port = 8080,
    [int]$DbPort = 3307,
    [string]$DbName = "soserp",
    [string]$DbUser = "soserp",
    [string]$LicenseFile = "",
    [string]$PublicKey = ""
)

$ErrorActionPreference = "Stop"
$stack  = Join-Path $InstallDir "xampp"
$app    = Join-Path $InstallDir "app"
$php    = Join-Path $stack "php\php.exe"
$mysql  = Join-Path $stack "mysql\bin\mysql.exe"
$mysqld = Join-Path $stack "mysql\bin\mysqld.exe"
$httpd  = Join-Path $stack "apache\bin\httpd.exe"

function Log($m)  { Write-Host "[soserp] $m" -ForegroundColor Cyan }
function Fail($m) { Write-Host "[soserp] ERRO: $m" -ForegroundColor Red; exit 1 }
function PortaOcupada($p) { return [bool](Get-NetTCPConnection -State Listen -LocalPort $p -ErrorAction SilentlyContinue) }

# 0) Portas livres?
if (PortaOcupada $Port)   { Fail "A porta $Port (web) ja esta em uso. Use -Port com outra." }
if (PortaOcupada $DbPort) { Fail "A porta $DbPort (BD) ja esta em uso. Use -DbPort com outra." }

# 1) VC++ Redistributable (Apache/PHP no Windows precisam)
$vc = Join-Path $InstallDir "vc_redist.x64.exe"
if (Test-Path $vc) {
    Log "A instalar VC++ Redistributable..."
    Start-Process -FilePath $vc -ArgumentList "/install","/quiet","/norestart" -Wait
} else {
    Log "AVISO: vc_redist.x64.exe nao encontrado. Se o Apache/PHP nao arrancar, e isto."
}

# 2) Password aleatoria da BD
$dbPass = -join ((48..57)+(65..90)+(97..122) | Get-Random -Count 24 | ForEach-Object {[char]$_})

# 3) Arrancar MariaDB temporario e criar BD + utilizador
Log "A arrancar MariaDB (porta $DbPort)..."
$myArgs = "--defaults-file=`"$stack\mysql\bin\my.ini`"","--port=$DbPort"
$proc = Start-Process -FilePath $mysqld -ArgumentList $myArgs -PassThru -WindowStyle Hidden
Start-Sleep -Seconds 8

Log "A criar base de dados e utilizador..."
$sql = @(
    "CREATE DATABASE IF NOT EXISTS $DbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
    ("CREATE USER IF NOT EXISTS '" + $DbUser + "'@'localhost' IDENTIFIED BY '" + $dbPass + "';"),
    ("GRANT ALL PRIVILEGES ON " + $DbName + ".* TO '" + $DbUser + "'@'localhost';"),
    "FLUSH PRIVILEGES;"
) -join "`r`n"
$sql | & $mysql --host=127.0.0.1 --port=$DbPort --user=root
if ($LASTEXITCODE -ne 0) { Fail "Falha ao criar a base de dados." }

# 4) Gerar o .env
Log "A gerar .env..."
$envPath = Join-Path $app ".env"
if (-not (Test-Path $envPath)) { Copy-Item (Join-Path $app ".env.example") $envPath }
function Set-Env($key, $value) {
    $content = Get-Content $envPath
    if ($content -match "^$key=") { $content = $content -replace "^$key=.*", "$key=$value" }
    else { $content += "$key=$value" }
    $content | Set-Content $envPath -Encoding utf8
}
Set-Env "APP_ENV" "production"
Set-Env "APP_DEBUG" "false"
Set-Env "APP_URL" "http://localhost:$Port"
Set-Env "DB_CONNECTION" "mysql"
Set-Env "DB_HOST" "127.0.0.1"
Set-Env "DB_PORT" "$DbPort"
Set-Env "DB_DATABASE" $DbName
Set-Env "DB_USERNAME" $DbUser
Set-Env "DB_PASSWORD" ('"' + $dbPass + '"')
Set-Env "LICENSE_ENFORCE" "true"
Set-Env "LICENSE_BIND_MACHINE" "true"
if ($PublicKey) { Set-Env "LICENSE_PUBLIC_KEY" ('"' + $PublicKey + '"') }

# 5) Preparar a aplicacao
Log "A preparar a aplicacao (key, migrate, caches)..."
Push-Location $app
& $php artisan key:generate --force
& $php artisan migrate --force --seed
& $php artisan storage:link
& $php artisan view:cache
Pop-Location

# 6) Escrever o vhost do Apache (docroot -> app\public)
Log "A configurar o Apache (docroot=app\public, porta $Port, so localhost)..."
$conf = Join-Path $stack "apache\conf\extra\httpd-soserp.conf"
$docroot = ($app + "\public") -replace '\\','/'
$vhost = @(
    "# Gerado pelo instalador do soserp",
    "Listen 127.0.0.1:$Port",
    "ServerName localhost:$Port",
    "<VirtualHost 127.0.0.1:$Port>",
    ('    DocumentRoot "' + $docroot + '"'),
    ('    <Directory "' + $docroot + '">'),
    "        Options -Indexes +FollowSymLinks",
    "        AllowOverride All",
    "        Require local",
    "    </Directory>",
    "</VirtualHost>"
) -join "`r`n"
Set-Content -Path $conf -Value $vhost -Encoding ascii

# Incluir no httpd.conf (uma vez) e desligar o Listen 80 default
$httpdConf = Join-Path $stack "apache\conf\httpd.conf"
$hc = Get-Content $httpdConf -Raw
if ($hc -notmatch 'httpd-soserp\.conf') {
    $hc = $hc -replace '(?m)^\s*Listen\s+80\s*$', '# Listen 80 (desligado pelo soserp)'
    $hc = $hc + "`r`nInclude conf/extra/httpd-soserp.conf`r`n"
    Set-Content -Path $httpdConf -Value $hc -Encoding ascii
}

# 7) Registar servicos com arranque automatico
Log "A registar servicos Windows (arranque automatico)..."
if ($proc -and -not $proc.HasExited) { Stop-Process -Id $proc.Id -Force }
Start-Sleep -Seconds 2

& $mysqld --install "soserp-mysql" --defaults-file="$stack\mysql\bin\my.ini" | Out-Null
& $httpd -k install -n "soserp-apache" | Out-Null
# TODO(hardening): correr os servicos sob conta dedicada de baixo privilegio.
Set-Service -Name "soserp-mysql"  -StartupType Automatic
Set-Service -Name "soserp-apache" -StartupType Automatic
Start-Service "soserp-mysql"
Start-Sleep -Seconds 3
Start-Service "soserp-apache"

# 8) Activar a licenca
if ($LicenseFile -and (Test-Path $LicenseFile)) {
    Log "A activar licenca..."
    Push-Location $app
    & $php artisan licenca:instalar "$LicenseFile"
    Pop-Location
}

Log "Concluido. Abra http://localhost:$Port"
