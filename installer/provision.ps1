# ==========================================================================
#  soserp - provisionamento pos-instalacao (autonomo, para qualquer PC)
# ==========================================================================
#
#  Gera configs LIMPAS (Apache + PHP + MySQL) apontadas para a pasta de
#  instalacao - nada de caminhos herdados. Inicializa a BD de raiz, migra,
#  regista os servicos com arranque automatico e activa a licenca.
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

# Continue (nao Stop): os exes nativos (mysqld, httpd) escrevem informacao no
# stderr - "Syntax OK", mensagens de init - e com Stop isso aborta o script.
# Os passos criticos verificam-se a mao via $LASTEXITCODE + Fail().
$ErrorActionPreference = "Continue"
# NB: nomes de variaveis do PowerShell sao CASE-INSENSITIVE. Nao usar
# $mysqlD/$mysqld como coisas diferentes - sao a MESMA variavel.
$stack     = Join-Path $InstallDir "xampp"
$app       = Join-Path $InstallDir "app"
$apache    = Join-Path $stack "apache"
$phpDir    = Join-Path $stack "php"
$mysqlDir  = Join-Path $stack "mysql"
$dataDir   = Join-Path $InstallDir "data"
$phpExe    = Join-Path $phpDir "php.exe"
$mysqldExe = Join-Path $mysqlDir "bin\mysqld.exe"
$mysqlExe  = Join-Path $mysqlDir "bin\mysql.exe"
$httpdExe  = Join-Path $apache "bin\httpd.exe"

function Log($m)  { Write-Host "[soserp] $m" -ForegroundColor Cyan }
function Fail($m) { Write-Host "[soserp] ERRO: $m" -ForegroundColor Red; exit 1 }
function PortaOcupada($p) { return [bool](Get-NetTCPConnection -State Listen -LocalPort $p -ErrorAction SilentlyContinue) }
function Fwd($p) { return ($p -replace '\\','/') }
function Escrever($path, $linhas) { Set-Content -Path $path -Value ($linhas -join "`r`n") -Encoding ascii }

# 0) Portas livres? (o Windows/Hyper-V RESERVA gamas de portas que tambem
#    bloqueiam o bind com "access permissions" - contar com isso)
$reservados = @()
foreach ($l in (netsh int ipv4 show excludedportrange protocol=tcp)) {
    if ($l -match '^\s*(\d+)\s+(\d+)') { $reservados += ,@([int]$Matches[1],[int]$Matches[2]) }
}
function PortaReservada($p) { foreach ($r in $reservados) { if ($p -ge $r[0] -and $p -le $r[1]) { return $true } } return $false }
function PortaLivre($p) { return (-not (PortaOcupada $p)) -and (-not (PortaReservada $p)) }

if (-not (PortaLivre $Port)) { Fail "Porta web $Port ocupada/reservada pelo Windows. Use -Port outra." }
# A porta da BD e INTERNA (so a app liga): se estiver ocupada/reservada,
# escolhe automaticamente a proxima livre e usa-a em todo o lado.
if (-not (PortaLivre $DbPort)) {
    $orig = $DbPort
    for ($p = 13306; $p -lt 13906; $p++) { if (PortaLivre $p) { $DbPort = $p; break } }
    if (-not (PortaLivre $DbPort)) { Fail "Nao encontrei porta livre para a BD." }
    Log "Porta BD $orig indisponivel; a usar $DbPort."
}

# 1) VC++ Redistributable (Apache/PHP no Windows precisam)
$vc = Join-Path $InstallDir "vc_redist.x64.exe"
if (Test-Path $vc) { Log "A instalar VC++ Redistributable..."; Start-Process $vc -ArgumentList "/install","/quiet","/norestart" -Wait }

# 2) php.ini limpo (extensoes que o soserp precisa)
Log "A gerar php.ini..."
$phpIni = Join-Path $phpDir "php.ini"
if (Test-Path (Join-Path $phpDir "php.ini-production")) { Copy-Item (Join-Path $phpDir "php.ini-production") $phpIni -Force }
$exts = @('pdo_mysql','mysqli','mbstring','openssl','curl','fileinfo','gd','zip','sodium','intl','bcmath','exif')
$linhasIni = @("", "; --- soserp ---", ('extension_dir = "' + (Fwd (Join-Path $phpDir 'ext')) + '"'), "memory_limit = 512M", "upload_max_filesize = 64M", "post_max_size = 64M", "max_execution_time = 120", 'date.timezone = "Africa/Luanda"')
foreach ($e in $exts) { if (Test-Path (Join-Path $phpDir ("ext\php_" + $e + ".dll"))) { $linhasIni += ("extension=" + $e) } }
# opcache: ZEND extension. Em Apache/Windows o ASLR torna os opcode handlers
# inutilizaveis sem file_cache_fallback - senao o Apache nem arranca.
if (Test-Path (Join-Path $phpDir "ext\php_opcache.dll")) {
    $ocDir = Join-Path $InstallDir "cache\opcache"
    New-Item -ItemType Directory -Force $ocDir | Out-Null
    $linhasIni += "zend_extension=opcache"
    $linhasIni += "opcache.enable=1"
    $linhasIni += "opcache.enable_cli=0"
    $linhasIni += ('opcache.file_cache="' + (Fwd $ocDir) + '"')
    $linhasIni += "opcache.file_cache_fallback=1"
}
Add-Content -Path $phpIni -Value ($linhasIni -join "`r`n") -Encoding ascii

# 2b) Isto é uma instalação NOVA ou uma ACTUALIZAÇÃO?
#
# A diferença não é cosmética: o install-schema.sql traz DROP TABLE em todas as
# tabelas (é um dump), por isso importá-lo por cima de uma instalação a
# trabalhar APAGAVA os dados do cliente. Numa actualização não se toca na base
# — faz-se backup e corre-se migrate.
$eActualizacao = (Test-Path (Join-Path $dataDir "mysql")) -and (Test-Path (Join-Path $app ".env"))
if ($eActualizacao) {
    Log "INSTALACAO EXISTENTE detectada -> modo ACTUALIZACAO (dados preservados)."
} else {
    Log "Instalacao nova."
}

# 3) MySQL: my.ini limpo + inicializar data + criar BD
Log "A gerar my.ini e inicializar a base de dados..."
$myIni = Join-Path $mysqlDir "my.ini"
Escrever $myIni @(
    "[mysqld]",
    ('basedir="' + (Fwd $mysqlDir) + '"'),
    ('datadir="' + (Fwd $dataDir) + '"'),
    "port=$DbPort",
    "bind-address=127.0.0.1",
    "skip-networking=0"
)
if (-not (Test-Path (Join-Path $dataDir "mysql"))) {
    if (Test-Path $dataDir) { Remove-Item $dataDir -Recurse -Force }
    Log "  a inicializar datadir (mysqld --initialize-insecure)..."
    & $mysqldExe "--defaults-file=$myIni" "--initialize-insecure" 2>&1 | Out-Null
}
Log "  a arrancar MySQL temporario..."
$proc = Start-Process $mysqldExe -ArgumentList "--defaults-file=$myIni" -PassThru -WindowStyle Hidden
Start-Sleep -Seconds 8
$sql = @(
    "CREATE DATABASE IF NOT EXISTS $DbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
    ("CREATE USER IF NOT EXISTS '" + $DbUser + "'@'localhost' IDENTIFIED BY '" + $DbUser + "_pw!';"),
    ("GRANT ALL PRIVILEGES ON " + $DbName + ".* TO '" + $DbUser + "'@'localhost';"),
    "FLUSH PRIVILEGES;"
) -join "`r`n"
$sql | & $mysqlExe --host=127.0.0.1 --port=$DbPort --user=root
if ($LASTEXITCODE -ne 0) { Fail "Falha ao criar a base de dados." }

# 4) .env
Log "A gerar .env..."
$envPath = Join-Path $app ".env"
if (-not (Test-Path $envPath)) { Copy-Item (Join-Path $app ".env.example") $envPath }
# Numa ACTUALIZAÇÃO o .env é do cliente: pode ter portas, credenciais e
# afinações próprias. Só se acrescentam chaves NOVAS (as que versões futuras
# passem a precisar); nunca se reescreve o que já lá está.
function Set-Env($k, $v) {
    $c = Get-Content $envPath
    $existe = ($c -match "^$k=").Count -gt 0
    if ($existe -and $eActualizacao) { return }
    if ($existe) { $c = $c -replace "^$k=.*", "$k=$v" } else { $c += "$k=$v" }
    $c | Set-Content $envPath -Encoding utf8
}
Set-Env "APP_ENV" "production"; Set-Env "APP_DEBUG" "false"; Set-Env "APP_URL" "http://localhost:$Port"
Set-Env "DB_CONNECTION" "mysql"; Set-Env "DB_HOST" "127.0.0.1"; Set-Env "DB_PORT" "$DbPort"
Set-Env "DB_DATABASE" $DbName; Set-Env "DB_USERNAME" $DbUser; Set-Env "DB_PASSWORD" ('"' + $DbUser + '_pw!"')
Set-Env "LICENSE_ENFORCE" "true"; Set-Env "LICENSE_BIND_MACHINE" "true"
if ($PublicKey) { Set-Env "LICENSE_PUBLIC_KEY" ('"' + $PublicKey + '"') }
# Validacao online / billing: liga ao servidor de licencas na cloud (renova a
# licenca e recebe suspensoes). Inofensivo se o endpoint ainda nao existir.
Set-Env "LICENSE_CHECKIN_URL" "https://soserp.vip/api/license/checkin"
Set-Env "LICENSE_UPDATE_URL" "https://soserp.vip/api/license/update"

# 5) App
Log "A preparar a aplicacao (key, esquema, seed, caches)..."
Push-Location $app
# A APP_KEY NUNCA se regenera numa actualização: é ela que decifra o que já
# está gravado (sessões e qualquer coluna encriptada). Regenerá-la deixava os
# dados do cliente ilegíveis.
if (-not $eActualizacao) {
    & $phpExe artisan key:generate --force
} else {
    Log "  APP_KEY preservada (actualizacao)."
}
# As migracoes do soserp NAO correm de raiz (ordem de FKs). Importa-se o
# esquema por dump (o mysql desliga FK checks na importacao) e depois semeiam-se
# os dados de referencia (permissoes, modulos, planos, super admin, AGT).
$schema = Join-Path $app "database\install-schema.sql"

if ($eActualizacao) {
    # ACTUALIZAR: a base é do cliente. Backup primeiro, migrar depois. O
    # install-schema.sql NUNCA entra aqui — tem DROP TABLE em tudo.
    $dump = Join-Path $mysqlDir "bin\mysqldump.exe"
    if (Test-Path $dump) {
        $pasta = Join-Path $InstallDir "backups"
        New-Item -ItemType Directory -Force $pasta | Out-Null
        $ficheiro = Join-Path $pasta ("antes-da-actualizacao-" + (Get-Date -Format "yyyyMMdd_HHmmss") + ".sql")
        Log "  backup da base de dados -> $ficheiro"
        cmd /c "`"$dump`" --host=127.0.0.1 --port=$DbPort --user=root $DbName > `"$ficheiro`""
        if (-not (Test-Path $ficheiro) -or (Get-Item $ficheiro).Length -lt 1000) {
            Fail "O backup da base de dados falhou - actualizacao abortada para nao arriscar os dados."
        }
    } else {
        Fail "mysqldump nao encontrado - sem backup nao se actualiza."
    }

    Log "  a aplicar migracoes..."
    & $phpExe artisan migrate --force
} elseif (Test-Path $schema) {
    Log "  a importar esquema (install-schema.sql)..."
    cmd /c "`"$mysqlExe`" --host=127.0.0.1 --port=$DbPort --user=root $DbName < `"$schema`""
    if ($LASTEXITCODE -ne 0) { Fail "Falha ao importar o esquema." }
    & $phpExe artisan db:seed --force
} else {
    & $phpExe artisan migrate --force --seed
}
& $phpExe artisan storage:link
& $phpExe artisan view:cache
Pop-Location

# 5b) Manifesto de integridade: hash dos ficheiros PHP da APP (nao do vendor).
# E o que o servico de integridade compara depois para detectar adulteracao.
Log "A gerar manifesto de integridade dos ficheiros PHP..."
$manifesto = Join-Path $app "storage\app\integridade-manifest.txt"
$linhasMan = @()
foreach ($d in @('app','config','routes','database')) {
    $base = Join-Path $app $d
    if (-not (Test-Path $base)) { continue }
    Get-ChildItem $base -Recurse -File -Filter *.php -ErrorAction SilentlyContinue | ForEach-Object {
        $rel = $_.FullName.Substring($app.Length + 1) -replace '\\','/'
        $linhasMan += ((Get-FileHash $_.FullName -Algorithm SHA256).Hash + '|' + $rel)
    }
}
foreach ($f in @('bootstrap\app.php','public\index.php','artisan')) {
    $full = Join-Path $app $f
    if (Test-Path $full) { $linhasMan += ((Get-FileHash $full -Algorithm SHA256).Hash + '|' + ($f -replace '\\','/')) }
}
Escrever $manifesto $linhasMan
Log "  $($linhasMan.Count) ficheiros PHP no manifesto"

# 6) httpd.conf LIMPO e autonomo (mpm_winnt e estatico no httpd.exe)
Log "A gerar httpd.conf..."
$docroot = Fwd (Join-Path $app "public")
Escrever (Join-Path $apache "conf\httpd.conf") @(
    ('Define SRVROOT "' + (Fwd $apache) + '"'),
    'ServerRoot "${SRVROOT}"',
    "Listen 127.0.0.1:$Port",
    "ServerName localhost:$Port",
    "",
    "LoadModule authn_core_module modules/mod_authn_core.so",
    "LoadModule authz_core_module modules/mod_authz_core.so",
    "LoadModule authz_host_module modules/mod_authz_host.so",
    "LoadModule access_compat_module modules/mod_access_compat.so",
    "LoadModule dir_module modules/mod_dir.so",
    "LoadModule mime_module modules/mod_mime.so",
    "LoadModule log_config_module modules/mod_log_config.so",
    "LoadModule setenvif_module modules/mod_setenvif.so",
    "LoadModule headers_module modules/mod_headers.so",
    "LoadModule rewrite_module modules/mod_rewrite.so",
    ('LoadModule php_module "' + (Fwd (Join-Path $phpDir 'php8apache2_4.dll')) + '"'),
    "",
    ('PHPIniDir "' + (Fwd $phpDir) + '"'),
    "AddHandler application/x-httpd-php .php",
    "<IfModule dir_module>",
    "    DirectoryIndex index.php index.html",
    "</IfModule>",
    "",
    "TypesConfig conf/mime.types",
    'ErrorLog "logs/error.log"',
    "LogLevel warn",
    "",
    ('DocumentRoot "' + $docroot + '"'),
    ('<Directory "' + $docroot + '">'),
    "    Options -Indexes +FollowSymLinks",
    "    AllowOverride All",
    "    Require local",
    "</Directory>",
    "",
    "<Directory />",
    "    AllowOverride none",
    "    Require all denied",
    "</Directory>"
)
# valida a config antes de registar o servico
& $httpdExe -t -f (Join-Path $apache "conf\httpd.conf") 2>&1 | ForEach-Object { Log "  apache: $_" }

# 7) Registar servicos (arranque automatico). Para o mysqld temporario antes.
Log "A registar servicos Windows..."
if ($proc -and -not $proc.HasExited) { Stop-Process -Id $proc.Id -Force }
Start-Sleep -Seconds 3
& $mysqldExe "--install" "soserp-mysql" "--defaults-file=$myIni" | Out-Null
& $httpdExe -k install -n "soserp-apache" | Out-Null
Set-Service -Name "soserp-mysql"  -StartupType Automatic
Set-Service -Name "soserp-apache" -StartupType Automatic
Start-Service "soserp-mysql"; Start-Sleep -Seconds 3
Start-Service "soserp-apache"

# 8) Licenca
if ($LicenseFile -and (Test-Path $LicenseFile)) {
    Log "A activar licenca..."
    Push-Location $app; & $phpExe artisan licenca:instalar "$LicenseFile"; Pop-Location
}

# 9) Servicos de vigilancia (tarefas agendadas, como SYSTEM):
#    - soserp-vigia: cada 2 min garante Apache+MySQL a correr e a app a responder.
#    - soserp-integridade: cada 60 min compara os hashes dos PHP com o manifesto.
Log "A registar vigia e servico de integridade..."
$vigia = Join-Path $InstallDir "vigia.ps1"
$integ = Join-Path $InstallDir "integridade.ps1"
if (Test-Path $vigia) {
    $tr = 'powershell -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $vigia + '" -Port ' + $Port
    schtasks /create /tn "soserp-vigia" /tr $tr /sc minute /mo 2 /ru SYSTEM /rl HIGHEST /f | Out-Null
}
if (Test-Path $integ) {
    $tr = 'powershell -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $integ + '" -AppDir "' + $app + '" -Manifest "' + $manifesto + '"'
    schtasks /create /tn "soserp-integridade" /tr $tr /sc minute /mo 60 /ru SYSTEM /rl HIGHEST /f | Out-Null
}

Log "Concluido. Abra http://localhost:$Port"
