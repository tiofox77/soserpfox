; ============================================================================
; soserp — instalador Windows (Inno Setup)  [SCAFFOLD]
; ============================================================================
;
; Empacota o soserp + XAMPP embutido num único .exe que instala tudo e corre o
; provision.ps1. Compilar com o Inno Setup Compiler numa máquina de build, com:
;   - payload\xampp\   → XAMPP portátil (Apache + MariaDB + PHP na versão certa)
;   - payload\app\      → o soserp (git export, SEM .env, SEM node_modules)
;   - a chave PÚBLICA da licença passada ao provision.ps1
;
; NÃO incluir a chave privada. NÃO incluir .env real.

#define AppName "soserp"
#define AppVersion "1.0.0"
#define AppPublisher "Softec Angola"
#define InstallDir "C:\soserp"
#define Port "8080"

[Setup]
AppName={#AppName}
AppVersion={#AppVersion}
AppPublisher={#AppPublisher}
DefaultDirName={#InstallDir}
DefaultGroupName={#AppName}
DisableProgramGroupPage=yes
PrivilegesRequired=admin
OutputBaseFilename=soserp-setup-{#AppVersion}
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
; Assinar o instalador (Authenticode) para o Windows não o marcar como suspeito:
; SignTool=signtool $f

[Files]
; XAMPP embutido → {app}\xampp
Source: "payload\xampp\*"; DestDir: "{app}\xampp"; Flags: recursesubdirs ignoreversion
; A aplicação → {app}\app
Source: "payload\app\*"; DestDir: "{app}\app"; Flags: recursesubdirs ignoreversion
; Scripts de provisionamento
Source: "provision.ps1"; DestDir: "{app}"; Flags: ignoreversion
; Licença opcional colocada ao lado do instalador
Source: "license.key"; DestDir: "{app}"; Flags: skipifsourcedoesntexist

[Run]
; Provisionamento pós-instalação (elevado). Ajustar -PublicKey no build.
Filename: "powershell.exe"; \
  Parameters: "-ExecutionPolicy Bypass -File ""{app}\provision.ps1"" -InstallDir ""{app}"" -Port {#Port} -LicenseFile ""{app}\license.key"""; \
  StatusMsg: "A configurar o soserp (base de dados, serviços, licença)..."; \
  Flags: runhidden waituntilterminated
; Abrir no browser no fim
Filename: "http://localhost:{#Port}"; Flags: postinstall shellexec

[UninstallRun]
; Parar e remover serviços antes de apagar
Filename: "{app}\xampp\apache\bin\httpd.exe"; Parameters: "-k uninstall -n ""soserp-apache"""; Flags: runhidden
Filename: "sc.exe"; Parameters: "delete soserp-mysql"; Flags: runhidden
; TODO(build): oferecer backup da BD (mysqldump) antes de remover.
