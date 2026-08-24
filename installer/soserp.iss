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
; Ícone da marca (gerado por installer\gerar-icone.php a partir do logo)
SetupIconFile=soserp.ico
UninstallDisplayIcon={app}\soserp.ico
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
Source: "provision.ps1";   DestDir: "{app}"; Flags: ignoreversion
Source: "vigia.ps1";       DestDir: "{app}"; Flags: ignoreversion
Source: "integridade.ps1"; DestDir: "{app}"; Flags: ignoreversion
Source: "soserp.ico";      DestDir: "{app}"; Flags: ignoreversion
Source: "soserp-tray.exe"; DestDir: "{app}"; Flags: ignoreversion skipifsourcedoesntexist
Source: "license.key";     DestDir: "{app}"; Flags: skipifsourcedoesntexist

[Icons]
Name: "{group}\soserp"; Filename: "http://localhost:{#MyPort}"; IconFilename: "{app}\soserp.ico"
Name: "{group}\Desinstalar soserp"; Filename: "{uninstallexe}"
Name: "{autodesktop}\soserp"; Filename: "http://localhost:{#MyPort}"; IconFilename: "{app}\soserp.ico"; Tasks: desktopicon
; Agente da bandeja arranca com o Windows (todos os utilizadores)
Name: "{commonstartup}\soserp (agente)"; Filename: "{app}\soserp-tray.exe"; \
  Parameters: "--dir ""{app}"" --port {#MyPort}"; IconFilename: "{app}\soserp.ico"

[Run]
Filename: "powershell.exe"; \
  Parameters: "-ExecutionPolicy Bypass -File ""{app}\provision.ps1"" -InstallDir ""{app}"" -Port {#MyPort} -DbPort {#MyDbPort} -LicenseFile ""{app}\license.key"" -PublicKey ""{#MyPublicKey}"""; \
  StatusMsg: "A configurar o soserp (base de dados, servicos, licenca)..."; \
  Flags: runhidden waituntilterminated
; Arranca já o agente da bandeja (sem esperar pelo próximo login)
Filename: "{app}\soserp-tray.exe"; Parameters: "--dir ""{app}"" --port {#MyPort}"; \
  Flags: nowait postinstall skipifsilent runasoriginaluser; Description: "Iniciar o agente na bandeja"
Filename: "http://localhost:{#MyPort}"; Description: "Abrir o soserp"; Flags: postinstall shellexec

[UninstallRun]
; O agente da bandeja segura ficheiros — fechar primeiro
Filename: "{sys}\taskkill.exe"; Parameters: "/IM soserp-tray.exe /F"; Flags: runhidden; RunOnceId: "KillTray"
; Remove as tarefas de vigilancia
Filename: "schtasks"; Parameters: "/delete /tn ""soserp-vigia"" /f"; Flags: runhidden; RunOnceId: "DelVigia"
Filename: "schtasks"; Parameters: "/delete /tn ""soserp-integridade"" /f"; Flags: runhidden; RunOnceId: "DelInteg"
; Para e remove os servicos antes de apagar os ficheiros
Filename: "net"; Parameters: "stop soserp-apache"; Flags: runhidden; RunOnceId: "StopApache"
Filename: "net"; Parameters: "stop soserp-mysql";  Flags: runhidden; RunOnceId: "StopMysql"
Filename: "{app}\xampp\apache\bin\httpd.exe"; Parameters: "-k uninstall -n ""soserp-apache"""; Flags: runhidden; RunOnceId: "DelApache"
Filename: "sc"; Parameters: "delete soserp-mysql"; Flags: runhidden; RunOnceId: "DelMysql"

[Code]
// ---------------------------------------------------------------------------
//  Actualizar por cima de uma instalação a trabalhar
// ---------------------------------------------------------------------------
//  Sem isto, o Apache/MySQL ficam a correr e o Windows recusa substituir o
//  httpd.exe ("DeleteFile falhou; código 5"). Param-se os serviços e o agente
//  da bandeja ANTES de copiar, e o provision.ps1 volta a arrancá-los no fim.

var
  EraActualizacao: Boolean;

function InstalacaoExistente(): Boolean;
begin
  Result := FileExists(ExpandConstant('{app}\xampp\apache\bin\httpd.exe'))
         or FileExists(ExpandConstant('{app}\app\.env'));
end;

procedure PararTudo();
var
  RC: Integer;
begin
  // O agente da bandeja segura o soserp.ico e o próprio .exe
  Exec(ExpandConstant('{sys}\taskkill.exe'), '/IM soserp-tray.exe /F', '', SW_HIDE, ewWaitUntilTerminated, RC);
  Exec(ExpandConstant('{sys}\net.exe'), 'stop soserp-apache', '', SW_HIDE, ewWaitUntilTerminated, RC);
  Exec(ExpandConstant('{sys}\net.exe'), 'stop soserp-mysql', '', SW_HIDE, ewWaitUntilTerminated, RC);
  // O Windows leva um instante a largar os ficheiros depois de o serviço parar
  Sleep(4000);
end;

function InitializeSetup(): Boolean;
begin
  Result := True;
  EraActualizacao := False;
end;

// Corre depois de o utilizador escolher a pasta e ANTES de copiar ficheiros.
function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  Resp: Integer;
begin
  Result := '';
  NeedsRestart := False;

  if not InstalacaoExistente() then
    Exit;

  EraActualizacao := True;

  Resp := MsgBox(
    'Já existe uma instalação do soserp nesta pasta.' + #13#10#13#10 +
    'ACTUALIZAR mantém a base de dados, a licença e as configurações — ' +
    'é feito um backup da base de dados antes de qualquer alteração.' + #13#10#13#10 +
    'Os serviços (Apache e MySQL) vão ser parados durante a actualização e ' +
    'reiniciados no fim.' + #13#10#13#10 +
    'Continuar com a actualização?',
    mbConfirmation, MB_YESNO);

  if Resp <> IDYES then
  begin
    Result := 'Actualização cancelada pelo utilizador.';
    Exit;
  end;

  WizardForm.StatusLabel.Caption := 'A parar os serviços do soserp...';
  PararTudo();
end;

// ---------------------------------------------------------------------------
//  Desinstalar: parcial (guarda os dados) ou COMPLETA (apaga tudo)
// ---------------------------------------------------------------------------
//  Por omissão o Inno só remove o que instalou — a base de dados, a licença e
//  os backups nasceram DEPOIS e ficavam para trás, ocupando espaço e deixando
//  dados do cliente no disco sem ninguém saber. Aqui pergunta-se, com o aviso
//  de que a remoção completa não tem volta.

var
  RemocaoCompleta: Boolean;

function InitializeUninstall(): Boolean;
var
  Resp: Integer;
  Dump, Pasta, BackupFile, Cmd: String;
  RC: Integer;
begin
  Result := True;
  RemocaoCompleta := False;

  Resp := MsgBox(
    'Como quer desinstalar o soserp?' + #13#10#13#10 +
    'SIM  —  REMOÇÃO COMPLETA: apaga também a BASE DE DADOS, a licença e as ' +
    'configurações. Não há volta.' + #13#10#13#10 +
    'NÃO  —  Remover só o programa e guardar a base de dados, a licença e os ' +
    'backups (para reinstalar mais tarde).' + #13#10#13#10 +
    'CANCELAR  —  Não desinstalar nada.',
    mbConfirmation, MB_YESNOCANCEL);

  if Resp = IDCANCEL then
  begin
    Result := False;
    Exit;
  end;

  RemocaoCompleta := (Resp = IDYES);

  // Backup: oferecido sempre, mas ESPECIALMENTE antes de uma remoção completa.
  Dump := ExpandConstant('{app}\xampp\mysql\bin\mysqldump.exe');
  if FileExists(Dump) then
  begin
    if RemocaoCompleta then
      Resp := MsgBox('Vai apagar TUDO. Guardar antes uma cópia da base de dados?', mbError, MB_YESNO)
    else
      Resp := MsgBox('Fazer backup da base de dados antes de remover?', mbConfirmation, MB_YESNO);

    if Resp = IDYES then
    begin
      Pasta := ExpandConstant('{userdocs}\soserp-backups');
      ForceDirectories(Pasta);
      BackupFile := Pasta + '\soserp-' + GetDateTimeString('yyyymmdd_hhnnss', #0, #0) + '.sql';
      // Guardado nos Documentos, não em {app}: numa remoção completa a pasta
      // da aplicação desaparece, e levaria o backup com ela.
      Cmd := '/C ""' + Dump + '" --host=127.0.0.1 --port=3307 --user=root soserp > "' + BackupFile + '""';
      Exec(ExpandConstant('{cmd}'), Cmd, '', SW_HIDE, ewWaitUntilTerminated, RC);
      if FileExists(BackupFile) then
        MsgBox('Backup guardado em:' + #13#10 + BackupFile, mbInformation, MB_OK)
      else
        MsgBox('Não foi possível criar o backup (a base de dados pode estar parada).', mbError, MB_OK);
    end;
  end;
end;

// Depois de o Inno remover o que instalou, limpa o que nasceu em uso.
procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
var
  Base: String;
begin
  if CurUninstallStep <> usPostUninstall then
    Exit;

  Base := ExpandConstant('{app}');

  if RemocaoCompleta then
  begin
    // Tudo o que nasceu depois da instalação e o Inno não conhece.
    DelTree(Base + '\data', True, True, True);        // base de dados
    DelTree(Base + '\cache', True, True, True);
    DelTree(Base + '\app\storage', True, True, True); // licença, logs, uploads
    DelTree(Base + '\backups', True, True, True);
    DeleteFile(Base + '\app\.env');
    DelTree(Base, True, True, True);                  // e a pasta em si
  end
  else
  begin
    // Parcial: só o que não é dado do cliente.
    DelTree(Base + '\cache', True, True, True);
    MsgBox('O programa foi removido.' + #13#10#13#10 +
           'A base de dados, a licença e os backups ficaram em:' + #13#10 + Base,
           mbInformation, MB_OK);
  end;
end;
