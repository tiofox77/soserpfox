# =============================================================================
# soserp — provisionamento pós-instalação (chamado pelo instalador .exe)
# =============================================================================
#
# Configura o XAMPP embutido, cria a base de dados, gera o .env, corre as
# migrations, regista os serviços com arranque automático e activa a licença.
#
# SCAFFOLD: pensado para correr numa máquina Windows com o payload do XAMPP já
# copiado para "$InstallDir\xampp". Testar e afinar numa máquina de build real.
#
# Uso (elevado):
#   powershell -ExecutionPolicy Bypass -File provision.ps1 -InstallDir "C:\soserp" -Port 8080 -LicenseFile "C:\soserp\license.key"
#
param(
    [string]$InstallDir = "C:\soserp",
    [int]$Port = 8080,
    [string]$DbName = "soserp",
    [string]$DbUser = "soserp",
    [string]$LicenseFile = "",
    [string]$PublicKey = ""   # LICENSE_PUBLIC_KEY (senão fica no .env.example)
)

$ErrorActionPreference = "Stop"
$xampp = Join-Path $InstallDir "xampp"
$app   = Join-Path $InstallDir "app"          # raiz do soserp (docroot = app\public)
$php   = Join-Path $xampp "php\php.exe"
$mysql = Join-Path $xampp "mysql\bin\mysql.exe"

function Log($m) { Write-Host "[soserp] $m" -ForegroundColor Cyan }

# 1) Password aleatória para a BD (nunca fixa) --------------------------------
$dbPass = -join ((48..57)+(65..90)+(97..122) | Get-Random -Count 24 | ForEach-Object {[char]$_})

# 2) Arrancar o MySQL do XAMPP e criar BD + utilizador ------------------------
Log "A arrancar MariaDB..."
Start-Process -FilePath (Join-Path $xampp "mysql\bin\mysqld.exe") `
    -ArgumentList "--defaults-file=`"$xampp\mysql\bin\my.ini`"" -WindowStyle Hidden
Start-Sleep -Seconds 6

Log "A criar base de dados e utilizador..."
$sql = @"
CREATE DATABASE IF NOT EXISTS $DbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DbUser'@'localhost' IDENTIFIED BY '$dbPass';
GRANT ALL PRIVILEGES ON $DbName.* TO '$DbUser'@'localhost';
FLUSH PRIVILEGES;
"@
$sql | & $mysql --user=root

# 3) Gerar o .env -------------------------------------------------------------
Log "A gerar .env..."
$envPath = Join-Path $app ".env"
if (-not (Test-Path $envPath)) {
    Copy-Item (Join-Path $app ".env.example") $envPath
}
function Set-Env($key, $value) {
    $content = Get-Content $envPath
    if ($content -match "^$key=") {
        $content = $content -replace "^$key=.*", "$key=$value"
    } else {
        $content += "$key=$value"
    }
    $content | Set-Content $envPath -Encoding utf8
}
Set-Env "APP_ENV" "production"
Set-Env "APP_DEBUG" "false"
Set-Env "APP_URL" "http://localhost:$Port"
Set-Env "DB_CONNECTION" "mysql"
Set-Env "DB_HOST" "127.0.0.1"
Set-Env "DB_DATABASE" $DbName
Set-Env "DB_USERNAME" $DbUser
Set-Env "DB_PASSWORD" "`"$dbPass`""
Set-Env "LICENSE_ENFORCE" "true"          # build OFFLINE: liga o bloqueio
Set-Env "LICENSE_BIND_MACHINE" "true"
if ($PublicKey) { Set-Env "LICENSE_PUBLIC_KEY" "`"$PublicKey`"" }

# 4) APP_KEY + migrations + caches -------------------------------------------
Log "A preparar a aplicação..."
Push-Location $app
& $php artisan key:generate --force
& $php artisan migrate --force --seed
& $php artisan storage:link
& $php artisan view:cache            # NUNCA deixar as views a frio (lição da cloud)
Pop-Location

# 5) Configurar o Apache (docroot -> app\public, porta) ----------------------
Log "A configurar o Apache (docroot=app\public, porta $Port)..."
# TODO(build): escrever um vhost em xampp\apache\conf\extra\httpd-soserp.conf
#   Listen $Port ; DocumentRoot "$app\public" ; <Directory> AllowOverride All
#   e incluí-lo no httpd.conf. Manter escuta em localhost.

# 6) Registar serviços com arranque automático -------------------------------
Log "A registar serviços Windows (arranque automático)..."
& (Join-Path $xampp "apache\bin\httpd.exe") -k install -n "soserp-apache" | Out-Null
& (Join-Path $xampp "mysql\bin\mysqld.exe") --install "soserp-mysql" --defaults-file="$xampp\mysql\bin\my.ini" | Out-Null
Set-Service -Name "soserp-apache" -StartupType Automatic
Set-Service -Name "soserp-mysql"  -StartupType Automatic
Start-Service "soserp-mysql"
Start-Service "soserp-apache"

# 7) Activar a licença, se veio no instalador --------------------------------
if ($LicenseFile -and (Test-Path $LicenseFile)) {
    Log "A activar licença..."
    Push-Location $app
    & $php artisan licenca:instalar "$LicenseFile"
    Pop-Location
}

Log "Concluído. Abra http://localhost:$Port"
