# Instalador Windows do soserp (offline / on-premise)

Scaffold para construir o `.exe` que instala o soserp com **XAMPP embutido** —
Apache + MariaDB + PHP — configura a base de dados, regista os serviços com
arranque automático e activa a licença. Ver [PRD](../docs/PRD-LICENCIAMENTO-OFFLINE.md) (RF1).

> **Estado:** scaffold. Os ficheiros aqui são reais e usáveis, mas o `.exe`
> compila-se numa **máquina de build Windows** com o payload do XAMPP e o
> Inno Setup. Testar numa VM limpa antes de distribuir.

## Peças

| Ficheiro | Papel |
|---|---|
| `soserp.iss` | Script do **Inno Setup** — empacota tudo num `.exe` |
| `provision.ps1` | Corre no fim da instalação: BD, `.env`, migrations, serviços, licença |
| `payload/xampp/` | *(a criar no build)* XAMPP portátil na versão de PHP certa |
| `payload/app/` | *(a criar no build)* o soserp exportado (sem `.env`, sem `node_modules`) |
| `license.key` | *(opcional)* licença colada ao lado do instalador para activação automática |

## Como construir (máquina de build)

1. **Preparar o payload da app** (git export, dependências de produção, assets):
   ```bash
   git archive --format=tar HEAD | tar -x -C payload/app
   cd payload/app
   composer install --no-dev --optimize-autoloader --classmap-authoritative
   npm ci && npm run build          # gera public/build
   # (opcional, recomendado) encriptar o PHP com ionCube/SourceGuardian aqui
   rm -f .env                       # o .env é gerado na máquina do cliente
   ```
2. **Preparar o XAMPP portátil** em `payload/xampp/` (versão de PHP compatível;
   se usar ionCube, incluir o loader para essa versão em `php\ext` + `php.ini`).
3. **Definir a chave pública da licença**: editar o `-PublicKey` na secção
   `[Run]` do `soserp.iss` (ou deixar o `provision.ps1` lê-la do `.env.example`).
   A **chave privada nunca entra no instalador**.
4. **Compilar**: abrir `soserp.iss` no Inno Setup Compiler → *Compile*. Sai
   `soserp-setup-<versão>.exe`.
5. **Assinar** o `.exe` (Authenticode) para o Windows/SmartScreen não o marcar.

## O que o instalador faz na máquina do cliente

1. Copia XAMPP + app para `C:\soserp`.
2. `provision.ps1`: gera password de BD aleatória, cria a BD, escreve o `.env`
   (`APP_KEY` única, ligação à BD, `LICENSE_ENFORCE=true`), corre
   `migrate --seed`, faz `view:cache`.
3. Configura o Apache (docroot → `app\public`, escuta em `localhost:8080`).
4. Regista `soserp-apache` e `soserp-mysql` como **serviços com arranque
   automático**.
5. Activa a licença (`licenca:instalar`) se `license.key` estiver presente.
6. Abre `http://localhost:8080`.

## Por afinar (TODO no build)

- Vhost do Apache escrito por `provision.ps1` (secção 5) — hoje é um TODO.
- Backup automático da BD no desinstalador (mysqldump) antes de remover.
- Deteção de portas ocupadas (se 8080/3306 já estiverem em uso).
- Fluxo de **actualização** do stack e da app (ver PRD RF5 / checklist secção E).
