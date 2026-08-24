# Instalador Windows do soserp (offline / on-premise)

Scaffold para construir o `.exe` que instala o soserp com **Apache + MariaDB +
PHP**, configura a base de dados, regista os serviços com arranque automático e
activa a licença. Ver [PRD](../docs/PRD-LICENCIAMENTO-OFFLINE.md) (RF1).

> **Abordagem — Opção B (decidida):** empacotamos os **binários portáteis** e
> **registamos nós os serviços Windows**. **NÃO** corremos o instalador GUI do
> XAMPP por dentro, nem os MSI oficiais. Os binários podem sair do **zip
> portátil do XAMPP** (curadoria já casada de Apache+MariaDB+PHP) — usa-se o
> conteúdo, não o *instalador*. Ganha-se: instalação silenciosa, footprint
> mínimo (sem phpMyAdmin/Mercury/Tomcat/FileZilla), config endurecida (o XAMPP
> é "só para desenvolvimento"), versões fixas e arranque automático.

> **Estado:** scaffold. Os ficheiros aqui são reais e usáveis, mas o `.exe`
> compila-se numa **máquina de build Windows** com o payload do XAMPP e o
> Inno Setup. Testar numa VM limpa antes de distribuir.

## Peças

| Ficheiro | Papel |
|---|---|
| `soserp.iss` | Script do **Inno Setup** — empacota tudo num `.exe` |
| `provision.ps1` | Corre no fim da instalação: BD, `.env`, migrations, serviços, licença |
| `payload/xampp/` | *(a criar no build)* **binários portáteis** Apache+MariaDB+PHP (só isto — tirados do zip portátil do XAMPP, sem os extras) |
| `payload/vc_redist.x64.exe` | *(a criar no build)* VC++ Redistributable (Apache/PHP precisam) |
| `payload/app/` | *(a criar no build)* o soserp exportado (sem `.env`, sem `node_modules`) |
| `license.key` | *(opcional)* licença colada ao lado do instalador para activação automática |

## O que o utilizador recebe (é um .exe compilado)

O produto final é um **`soserp-setup-<versão>.exe`** — um executável Windows
**compilado** pelo Inno Setup 6, com assistente, página de licença, ícone,
barra de progresso, entrada em *Adicionar/Remover Programas*, desinstalador e
**assinatura Authenticode**. O utilizador faz **duplo-clique** e segue o
assistente; **nunca vê PowerShell nem `.bat`** — esses são a lógica interna do
instalador (como em qualquer produto Windows profissional). O `soserp.iss` é o
código-fonte desse `.exe`.

> **Para compilar o `.exe` é preciso o Inno Setup 6** (gratuito) na máquina de
> build. Sem ele, o `build.ps1` produz o pacote portátil (ZIP) como alternativa.

## Código compilado / encriptado (ionCube)

"Tudo compilado" no mundo PHP faz-se com **ionCube** (ou SourceGuardian): o PHP
é transformado em **bytecode encriptado** — o cliente **não lê nem altera** o
código-fonte, e corre com o *loader* do ionCube (incluído no `php\ext` do
payload). É o standard da indústria para produtos PHP comerciais e a peça que
protege o soserp de ser copiado/adulterado. Encode-se o `payload\app\` no build,
**antes** de empacotar, com o encoder do ionCube (ferramenta licenciada à parte;
os flags dependem da versão). Nota: uma app Laravel **não** se transforma num
único binário nativo — o "compilado" que importa é o installer (.exe) + o
bytecode encriptado (ionCube). Um único binário com a app embutida só via
FrankenPHP, imaturo em Windows (ver PRD, estudo futuro).

## Construir com um comando (recomendado)

O `build.ps1` faz tudo — monta o payload (app + binários + `vc_redist`) e produz
o instalador. **Se o Inno Setup estiver instalado**, sai um `.exe`; **senão**,
sai um **ZIP portátil que se instala sem compilador** (extrair + `instalar.bat`):

```powershell
powershell -ExecutionPolicy Bypass -File installer\build.ps1 `
    -Version 1.0.0 -PublicKey "<base64 da chave publica>" -SourceStack "C:\laragon2\bin"
```

- `-SourceStack` = onde estão os binários (o `bin` do Laragon serve, ou um zip
  portátil do XAMPP). O script copia só `apache/`, `mysql/`, `php/`.
- Saída em `installer\dist\` — `soserp-setup-<versão>.exe` **ou** `soserp-portable-<versão>.zip`.
- Requer, na máquina de build: `composer`, `npm`, e rede (para o `composer install`,
  `npm run build` e descarregar o `vc_redist`).

**Instalar o ZIP portátil no cliente:** extrair para uma pasta (ex.: `C:\soserp`)
e correr **`instalar.bat` como Administrador** — pede UAC, corre o `provision.ps1`,
regista os serviços e abre o soserp. Para remover: `desinstalar.bat`.

## Construir à mão (passo a passo)

1. **Preparar o payload da app** (git export, dependências de produção, assets):
   ```bash
   git archive --format=tar HEAD | tar -x -C payload/app
   cd payload/app
   composer install --no-dev --optimize-autoloader --classmap-authoritative
   npm ci && npm run build          # gera public/build
   # (opcional, recomendado) encriptar o PHP com ionCube/SourceGuardian aqui
   rm -f .env                       # o .env é gerado na máquina do cliente
   ```
2. **Preparar os binários portáteis** em `payload/xampp/` — do **zip portátil do
   XAMPP**, mantendo só `apache/`, `mysql/` e `php/` (apagar phpMyAdmin, Mercury,
   Tomcat, FileZilla). Versão de PHP compatível; se usar ionCube, incluir o
   loader para essa versão em `php\ext` + `php.ini`. Juntar o `vc_redist.x64.exe`.
3. **Definir a chave pública da licença**: editar o `-PublicKey` na secção
   `[Run]` do `soserp.iss` (ou deixar o `provision.ps1` lê-la do `.env.example`).
   A **chave privada nunca entra no instalador**.
4. **Compilar**: abrir `soserp.iss` no Inno Setup Compiler → *Compile*. Sai
   `soserp-setup-<versão>.exe`.
5. **Assinar** o `.exe` (Authenticode) para o Windows/SmartScreen não o marcar.

## O que o instalador faz na máquina do cliente

1. Copia os binários portáteis + a app para `C:\soserp` (portas próprias, ex.: 8080/3307).
2. Instala o **VC++ Redistributable** (Apache/PHP precisam dele).
3. `provision.ps1`: gera password de BD aleatória, cria a BD, escreve o `.env`
   (`APP_KEY` única, ligação à BD, `LICENSE_ENFORCE=true`), corre
   `migrate --seed`, faz `view:cache`.
4. Configura o Apache (docroot → `app\public`, `AllowOverride All`, escuta só em `localhost:<porta>`).
5. Regista `soserp-apache` e `soserp-mysql` como **serviços com arranque
   automático**, sob **conta dedicada de baixo privilégio**.
6. Activa a licença (`licenca:instalar`) se `license.key` estiver presente.
7. Abre `http://localhost:<porta>`.

## Por afinar (TODO no build)

- Vhost do Apache escrito por `provision.ps1` (secção 5) — hoje é um TODO.
- Passo do **VC++ Redistributable** no `provision.ps1`/`[Run]`.
- Serviços sob **conta dedicada** (não LocalSystem).
- Backup automático da BD no desinstalador (mysqldump) antes de remover.
- Deteção de **portas ocupadas** (se as portas escolhidas já estiverem em uso).
- Fluxo de **actualização** do stack e da app (ver PRD RF5 / checklist secção E).
