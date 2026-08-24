# ==========================================================================
#  soserp - build do instalador (Opcao B)
# ==========================================================================
#
#  Monta o payload (app + binarios portateis Apache/MariaDB/PHP + vc_redist) e
#  produz o instalador:
#    - se o Inno Setup (ISCC.exe) existir  -> dist\soserp-setup-<versao>.exe
#    - senao                               -> dist\soserp-portable-<versao>.zip
#      (extrai numa maquina Windows e corre instalar.bat, sem compilador)
#
#  Corre numa MAQUINA DE BUILD (nao no cliente). Exemplo:
#    powershell -ExecutionPolicy Bypass -File installer\build.ps1 -Version 1.0.0 -PublicKey "<base64>" -SourceStack "C:\laragon2\bin"
#
param(
    [string]$Version = "1.0.0",
    [string]$PublicKey = "",
    [int]$Port = 8080,
    [int]$DbPort = 3307,
    [string]$SourceStack = "C:\laragon2\bin",
    # PHP tem de ser THREAD-SAFE (TS) para o Apache mod_php. O default e o
    # 8.3.16 TS do Laragon; os "-nts" NAO servem.
    [string]$PhpDir = "C:\laragon2\bin\php\php-8.3.16-Win32-vs16-x64",
    [switch]$SkipAssets
)

$ErrorActionPreference = "Stop"
$root   = Split-Path $PSScriptRoot -Parent
$build  = Join-Path $PSScriptRoot "payload"
$appOut = Join-Path $build "app"
$stkOut = Join-Path $build "xampp"
$dist   = Join-Path $PSScriptRoot "dist"

function Log($m) { Write-Host "[build] $m" -ForegroundColor Green }
function Newest($dir) { Get-ChildItem $dir -Directory -EA SilentlyContinue | Sort-Object Name -Descending | Select-Object -First 1 }

# 1) App
Log "A montar payload/app..."
if (Test-Path $appOut) { Remove-Item $appOut -Recurse -Force }
New-Item -ItemType Directory -Force $appOut | Out-Null
robocopy $root $appOut /E /NFL /NDL /NJH /NJS /XD "$root\node_modules" "$root\.git" "$root\tests" "$root\installer" "$root\storage\logs" "$root\.idea" /XF "$root\.env" | Out-Null

Push-Location $appOut
Log "composer install --no-dev..."
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction
if (-not $SkipAssets) {
    Log "npm ci + npm run build..."
    npm ci
    npm run build
    Remove-Item (Join-Path $appOut "node_modules") -Recurse -Force -EA SilentlyContinue
}
Remove-Item (Join-Path $appOut ".env") -Force -EA SilentlyContinue
if (Test-Path (Join-Path $appOut "bootstrap\cache")) {
    Get-ChildItem (Join-Path $appOut "bootstrap\cache") -Filter *.php | Remove-Item -Force -EA SilentlyContinue
}
Pop-Location

# 1b) Esquema de instalacao (estrutura + migrations, SEM dados de negocio).
# As migracoes do soserp nao correm de raiz; o cliente importa este dump e faz
# db:seed. Requer o MySQL de dev (BD 'soserp') a correr em 127.0.0.1:3306.
Log "A gerar install-schema.sql (do MySQL de dev)..."
$dump = $null
$mysqlSrc = Join-Path $SourceStack "mysql"
if (Test-Path (Join-Path $mysqlSrc "bin\mysqldump.exe")) { $dump = Join-Path $mysqlSrc "bin\mysqldump.exe" }
else { $n = Newest $mysqlSrc; if ($n) { $dump = Join-Path $n.FullName "bin\mysqldump.exe" } }
$schemaOut = Join-Path $appOut "database\install-schema.sql"
if ($dump -and (Test-Path $dump)) {
    cmd /c "`"$dump`" --host=127.0.0.1 --port=3306 --user=root --no-data --skip-comments --skip-triggers soserp > `"$schemaOut`" 2>nul"
    cmd /c "`"$dump`" --host=127.0.0.1 --port=3306 --user=root --no-create-info --skip-comments soserp migrations >> `"$schemaOut`" 2>nul"
    if ((Test-Path $schemaOut) -and ((Get-Item $schemaOut).Length -gt 10000)) { Log ("  esquema: " + [math]::Round((Get-Item $schemaOut).Length/1KB) + " KB") }
    else { throw "install-schema.sql vazio - o MySQL de dev (BD soserp) esta a correr em 3306?" }
} else { throw "mysqldump nao encontrado em $mysqlSrc" }

# 2) Stack portatil (so apache/mysql/php)
Log "A montar payload/xampp (binarios portateis)..."
if (Test-Path $stkOut) { Remove-Item $stkOut -Recurse -Force }
New-Item -ItemType Directory -Force $stkOut | Out-Null
foreach ($svc in @("apache","mysql","php")) {
    $srcRoot = Join-Path $SourceStack $svc
    if ($svc -eq "php" -and $PhpDir -and (Test-Path $PhpDir)) { $src = $PhpDir }
    elseif (-not (Test-Path $srcRoot)) { throw "Nao encontrei $srcRoot. Ajuste -SourceStack." }
    elseif (Test-Path (Join-Path $srcRoot "bin")) { $src = $srcRoot }
    elseif ($svc -eq "php" -and (Test-Path (Join-Path $srcRoot "php.exe"))) { $src = $srcRoot }
    else { $n = Newest $srcRoot; if ($n) { $src = $n.FullName } else { $src = $null } }
    if (-not $src) { throw "Nao resolvi os binarios de $svc em $srcRoot." }
    Log ("  " + $svc + "  <-  " + $src)
    robocopy $src (Join-Path $stkOut $svc) /E /NFL /NDL /NJH /NJS | Out-Null
}

# 3) VC++ Redistributable
$vc = Join-Path $build "vc_redist.x64.exe"
if (-not (Test-Path $vc)) {
    try {
        Log "A descarregar vc_redist.x64.exe..."
        Invoke-WebRequest "https://aka.ms/vs/17/release/vc_redist.x64.exe" -OutFile $vc
    } catch { Log "AVISO: nao consegui descarregar o vc_redist. Coloque-o em payload a mao." }
}

# 3b) Icone da marca + agente da bandeja (compilado com o csc.exe do .NET
# Framework, que existe em qualquer Windows - nao precisa de Visual Studio).
Log "A gerar icone da marca..."
$icone = Join-Path $PSScriptRoot "soserp.ico"
& php (Join-Path $PSScriptRoot "gerar-icone.php") | Out-Null
if (-not (Test-Path $icone)) { Log "AVISO: soserp.ico nao gerado (o instalador fica com o icone por omissao)." }

Log "A compilar o agente da bandeja (soserp-tray.exe)..."
$csc = @(
    "$env:WINDIR\Microsoft.NET\Framework64\v4.0.30319\csc.exe",
    "$env:WINDIR\Microsoft.NET\Framework\v4.0.30319\csc.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1
$trayExe = Join-Path $PSScriptRoot "soserp-tray.exe"
if ($csc) {
    $refs = "/r:System.dll","/r:System.Drawing.dll","/r:System.Windows.Forms.dll","/r:System.ServiceProcess.dll"
    $args = @("/target:winexe","/optimize+","/nologo","/out:$trayExe") + $refs
    if (Test-Path $icone) { $args += "/win32icon:$icone" }
    $args += (Join-Path $PSScriptRoot "tray\SoserpTray.cs")
    & $csc $args 2>&1 | ForEach-Object { if ($_ -match "error") { Log "  csc: $_" } }
    if (Test-Path $trayExe) { Log ("  soserp-tray.exe: " + [math]::Round((Get-Item $trayExe).Length/1KB) + " KB") }
    else { Log "AVISO: o agente da bandeja NAO compilou." }
} else { Log "AVISO: csc.exe nao encontrado - sem agente da bandeja." }

# 4) Produzir o instalador
New-Item -ItemType Directory -Force $dist | Out-Null
$iscc = @(
    "C:\Program Files (x86)\Inno Setup 6\ISCC.exe",
    "C:\Program Files\Inno Setup 6\ISCC.exe",
    "$env:LOCALAPPDATA\Programs\Inno Setup 6\ISCC.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1

if ($iscc) {
    Log "Inno Setup encontrado. A compilar o .exe..."
    & $iscc "/DMyVersion=$Version" "/DMyPort=$Port" "/DMyDbPort=$DbPort" "/DMyPublicKey=$PublicKey" (Join-Path $PSScriptRoot "soserp.iss")
    Log "Feito: $dist\soserp-setup-$Version.exe"
} else {
    Log "Inno Setup NAO instalado. A produzir o pacote PORTATIL (sem compilador)..."
    Copy-Item (Join-Path $PSScriptRoot "provision.ps1")   $build -Force
    Copy-Item (Join-Path $PSScriptRoot "vigia.ps1")       $build -Force
    Copy-Item (Join-Path $PSScriptRoot "integridade.ps1") $build -Force
    Copy-Item (Join-Path $PSScriptRoot "instalar.bat")    $build -Force
    Copy-Item (Join-Path $PSScriptRoot "desinstalar.bat") $build -Force
    Set-Content -Path (Join-Path $build "public_key.txt") -Value $PublicKey -Encoding ascii
    Set-Content -Path (Join-Path $build "portas.txt") -Value @("$Port","$DbPort") -Encoding ascii
    $zip = Join-Path $dist "soserp-portable-$Version.zip"
    if (Test-Path $zip) { Remove-Item $zip -Force }
    Log "A comprimir (pode demorar)..."
    Compress-Archive -Path (Join-Path $build "*") -DestinationPath $zip
    Log "Feito: $zip"
    Log "No cliente: extrair e correr instalar.bat como Administrador."
}
