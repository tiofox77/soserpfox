; ============================================================
;  SOS ERP — Faturação :: Instalador Windows (Inno Setup)
;
;  Pré-requisito: ter gerado o build nativo primeiro:
;     powershell -ExecutionPolicy Bypass -File build_windows.ps1
;  Depois compilar este script:
;     "C:\Program Files (x86)\Inno Setup 6\ISCC.exe" installer\installer.iss
;  (ou abrir no Inno Setup Compiler e carregar em Compile)
;
;  Gera: installer\Output\SOSERP-Faturacao-Setup.exe
; ============================================================

#define AppName "SOS ERP - Faturacao"
#define AppVersion "1.0.0"
#define AppPublisher "Softec Angola"
#define AppExeName "soserp_faturacao.exe"
; Pasta do build Release (relativa a este .iss em app_mobile\installer)
#define BuildDir "..\build\windows\x64\runner\Release"

[Setup]
AppId={{B8E1F2A0-5C3D-4E7A-9F2B-SOSERPFATURA01}
AppName={#AppName}
AppVersion={#AppVersion}
AppPublisher={#AppPublisher}
AppPublisherURL=https://soserp.vip
DefaultDirName={autopf}\SOS ERP Faturacao
DefaultGroupName=SOS ERP
UninstallDisplayIcon={app}\{#AppExeName}
OutputDir=Output
OutputBaseFilename=SOSERP-Faturacao-Setup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
PrivilegesRequired=lowest
; SetupIconFile=app_icon.ico  ; (opcional) coloque um app_icon.ico nesta pasta para personalizar o instalador

[Languages]
Name: "pt"; MessagesFile: "compiler:Languages\Portuguese.isl"

[Tasks]
Name: "desktopicon"; Description: "Criar atalho no Ambiente de Trabalho"; GroupDescription: "Atalhos:"

[Files]
; Copia toda a pasta Release (exe + dlls + data/)
Source: "{#BuildDir}\*"; DestDir: "{app}"; Flags: recursesubdirs createallsubdirs ignoreversion

[Icons]
Name: "{group}\SOS ERP — Faturação"; Filename: "{app}\{#AppExeName}"
Name: "{group}\Desinstalar SOS ERP — Faturação"; Filename: "{uninstallexe}"
Name: "{autodesktop}\SOS ERP — Faturação"; Filename: "{app}\{#AppExeName}"; Tasks: desktopicon

[Run]
Filename: "{app}\{#AppExeName}"; Description: "Abrir SOS ERP — Faturação"; Flags: nowait postinstall skipifsilent
