; ============================================================================
; soserp — instalador Windows (Inno Setup)  — Opção B
; ============================================================================
;
; Empacota o soserp + binários portáteis (Apache+MariaDB+PHP, SEM o instalador
; do XAMPP) num único .exe que corre o provision.ps1. Compila-se pelo
; installer\build.ps1 (que passa os /D... abaixo) ou à mão no Inno Setup.
;
; Payload esperado (montado pelo build.ps1):
;   payload\xampp\   -> binários portáteis (apache, mysql, php)
;   payload\app\     -> o soserp (sem .env, sem node_modules)
;   payload\vc_redist.x64.exe
;
; NÃO incluir a chave privada. NÃO incluir .env real.

#ifndef MyVersion
  #define MyVersion "1.0.0"
#endif
#ifndef MyPort
  #define MyPort "8080"
#endif
#ifndef MyDbPort
  #define MyDbPort "3307"
#endif
#ifndef MyPublicKey
  #define MyPublicKey ""
#endif

[Setup]
AppName=soserp
AppVersion={#MyVersion}
AppPublisher=Softec Angola
DefaultDirName=C:\soserp
DisableProgramGroupPage=yes
PrivilegesRequired=admin
OutputDir=dist
OutputBaseFilename=soserp-setup-{#MyVersion}
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
; Assinar o instalador (Authenticode) para o SmartScreen não o marcar:
; SignTool=signtool $f

[Files]
Source: "payload\xampp\*"; DestDir: "{app}\xampp"; Flags: recursesubdirs ignoreversion
Source: "payload\app\*";   DestDir: "{app}\app";   Flags: recursesubdirs ignoreversion
Source: "payload\vc_redist.x64.exe"; DestDir: "{app}"; Flags: skipifsourcedoesntexist
Source: "provision.ps1"; DestDir: "{app}"; Flags: ignoreversion
Source: "license.key";   DestDir: "{app}"; Flags: skipifsourcedoesntexist

[Run]
Filename: "powershell.exe"; \
  Parameters: "-ExecutionPolicy Bypass -File ""{app}\provision.ps1"" -InstallDir ""{app}"" -Port {#MyPort} -DbPort {#MyDbPort} -LicenseFile ""{app}\license.key"" -PublicKey ""{#MyPublicKey}"""; \
  StatusMsg: "A configurar o soserp (base de dados, serviços, licença)..."; \
  Flags: runhidden waituntilterminated
Filename: "http://localhost:{#MyPort}"; Flags: postinstall shellexec

[UninstallRun]
Filename: "{app}\xampp\apache\bin\httpd.exe"; Parameters: "-k uninstall -n ""soserp-apache"""; Flags: runhidden
Filename: "sc.exe"; Parameters: "delete soserp-mysql"; Flags: runhidden
; TODO(build): oferecer backup da BD (mysqldump) antes de remover.
