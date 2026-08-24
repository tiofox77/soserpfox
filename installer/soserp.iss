; ============================================================================
;  soserp - instalador Windows profissional (Inno Setup 6)
; ============================================================================
;
;  Compila para um .exe REAL com assistente, pagina de licenca, icone, entrada
;  em "Adicionar/Remover Programas", desinstalador e assinatura. Os .ps1/.bat
;  sao a logica interna (invisivel ao utilizador) - o produto e este .exe.
;
;  Compilar: installer\build.ps1 (passa os /D...) ou abrir no Inno Setup 6.
;
;  Payload esperado (montado pelo build.ps1):
;    payload\xampp\  -> binarios portateis (apache, mysql, php)
;    payload\app\    -> o soserp (idealmente com o PHP encriptado por ionCube)
;    payload\vc_redist.x64.exe

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
#define MyName "soserp"
#define MyPublisher "Softec Angola"

[Setup]
; AppId FIXO (nao mudar entre versoes - e o que liga upgrades e desinstalacao)
AppId={{8F3B2A10-9C4D-4E77-B2A1-5E6F7A8B9C0D}
AppName={#MyName}
AppVersion={#MyVersion}
AppVerName={#MyName} {#MyVersion}
AppPublisher={#MyPublisher}
VersionInfoVersion={#MyVersion}
VersionInfoCompany={#MyPublisher}
VersionInfoDescription=Instalador do {#MyName}
DefaultDirName=C:\soserp
DefaultGroupName={#MyName}
DisableProgramGroupPage=yes
AllowNoIcons=yes
PrivilegesRequired=admin
MinVersion=10.0
ArchitecturesInstallIn64BitMode=x64
ArchitecturesAllowed=x64
OutputDir=dist
OutputBaseFilename=soserp-setup-{#MyVersion}
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
UninstallDisplayName={#MyName} {#MyVersion}
LicenseFile=EULA.txt
; Descomentar quando houver um icone/imagens:
; SetupIconFile=soserp.ico
; WizardImageFile=wizard.bmp
; WizardSmallImageFile=wizard-small.bmp
; Assinatura Authenticode (SmartScreen): configurar uma ferramenta 'signtool':
; SignTool=signtool $f

[Languages]
Name: "pt"; MessagesFile: "compiler:Languages\Portuguese.isl"

[Tasks]
Name: "desktopicon"; Description: "Criar um atalho no Ambiente de Trabalho"; GroupDescription: "Atalhos:"

[Files]
Source: "payload\xampp\*"; DestDir: "{app}\xampp"; Flags: recursesubdirs ignoreversion
Source: "payload\app\*";   DestDir: "{app}\app";   Flags: recursesubdirs ignoreversion
Source: "payload\vc_redist.x64.exe"; DestDir: "{app}"; Flags: skipifsourcedoesntexist
Source: "provision.ps1"; DestDir: "{app}"; Flags: ignoreversion
Source: "license.key";   DestDir: "{app}"; Flags: skipifsourcedoesntexist

[Icons]
Name: "{group}\soserp"; Filename: "http://localhost:{#MyPort}"
Name: "{group}\Desinstalar soserp"; Filename: "{uninstallexe}"
Name: "{autodesktop}\soserp"; Filename: "http://localhost:{#MyPort}"; Tasks: desktopicon

[Run]
Filename: "powershell.exe"; \
  Parameters: "-ExecutionPolicy Bypass -File ""{app}\provision.ps1"" -InstallDir ""{app}"" -Port {#MyPort} -DbPort {#MyDbPort} -LicenseFile ""{app}\license.key"" -PublicKey ""{#MyPublicKey}"""; \
  StatusMsg: "A configurar o soserp (base de dados, servicos, licenca)..."; \
  Flags: runhidden waituntilterminated
Filename: "http://localhost:{#MyPort}"; Description: "Abrir o soserp"; Flags: postinstall shellexec

[UninstallRun]
; Para e remove os servicos antes de apagar os ficheiros
Filename: "net"; Parameters: "stop soserp-apache"; Flags: runhidden; RunOnceId: "StopApache"
Filename: "net"; Parameters: "stop soserp-mysql";  Flags: runhidden; RunOnceId: "StopMysql"
Filename: "{app}\xampp\apache\bin\httpd.exe"; Parameters: "-k uninstall -n ""soserp-apache"""; Flags: runhidden; RunOnceId: "DelApache"
Filename: "sc"; Parameters: "delete soserp-mysql"; Flags: runhidden; RunOnceId: "DelMysql"

[Code]
// Oferece backup da BD (mysqldump) antes de desinstalar.
function InitializeUninstall(): Boolean;
var
  Resp: Integer;
  Dump, BackupFile, Cmd: String;
  RC: Integer;
begin
  Result := True;
  Dump := ExpandConstant('{app}\xampp\mysql\bin\mysqldump.exe');
  if FileExists(Dump) then
  begin
    Resp := MsgBox('Fazer backup da base de dados antes de remover?', mbConfirmation, MB_YESNO);
    if Resp = IDYES then
    begin
      BackupFile := ExpandConstant('{app}\backup-soserp.sql');
      Cmd := '/C ""' + Dump + '" --host=127.0.0.1 --port=3307 --user=root soserp > "' + BackupFile + '""';
      Exec(ExpandConstant('{cmd}'), Cmd, '', SW_HIDE, ewWaitUntilTerminated, RC);
      MsgBox('Backup em: ' + BackupFile, mbInformation, MB_OK);
    end;
  end;
end;
