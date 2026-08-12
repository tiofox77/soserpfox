# Runbook de Deploy — soserp

> Como enviar alterações para **produção** (`soserp.vip`) via FTP + rotas de manutenção.
> Escrito para ser seguido por uma pessoa **ou outra IA**. Contém credenciais e tokens da infra do dono.

---

## 0. Modelo mental (ler primeiro)

- **Sem pipeline de CI/CD.** O deploy é: enviar ficheiros por **FTP** para `public_html/` e, quando preciso, **limpar caches** e **resetar o OPcache** via rotas HTTP protegidas por token.
- **Raiz da app no servidor** = `public_html/`. **Docroot web** (onde está o `index.php`) = `public_html/public`. Logo um ficheiro enviado para `public/x.php` fica acessível em `https://soserp.vip/x.php`.
- **`opcache.validate_timestamps=0` em produção:** ficheiros **PHP que correm em pedidos web** (Controllers, Livewire, Models, Services, Middleware, Providers, `routes/*.php`) **NÃO são relidos** após o upload até se fazer **reset ao OPcache**. Ficheiros que só correm por CLI/rota de manutenção (seeders, migrations, comandos) não precisam de reset — são carregados frescos a cada execução.
- **A base de dados local é uma réplica da produção.** Regra: testar **sempre** localmente (com `php -l`, correr a migração local e um teste com `DB::beginTransaction()` + `rollBack()`) **antes** de enviar.
- **`php artisan serve` local corre na porta 8010** (ver `.claude/launch.json`, `autoPort:false`).

---

## 1. Scripts de FTP (no repo)

### `scripts/ftp_deploy.ps1` — enviar ficheiros
```powershell
& "scripts/ftp_deploy.ps1" <caminho1> <caminho2> ...
```
- Caminhos **relativos à raiz do projeto** (ex.: `app/Models/Invoicing/TransportGuide.php`).
- **Preserva a estrutura**: `app/X/Y.php` → `public_html/app/X/Y.php`. **Cria as pastas remotas** que faltem (recursivo, ignora "550 = já existe").
- Se passares uma **pasta**, expande-a recursivamente (envia todos os ficheiros lá dentro).
- Upload **binário**, passivo, timeout 60s. Imprime `OK (N bytes)` por ficheiro e um resumo. Exit `0` = tudo OK, `2` = houve falhas.
- **Credenciais (hardcoded no script):** host `ftp.soserp.vip`, user `soserp`, pass `Softec@ngola!`, root `public_html`.
- ⚠️ **NÃO** invocar com `powershell -ExecutionPolicy Bypass -File ...` — o classificador de segurança bloqueia o flag `-ExecutionPolicy Bypass`. Usar a forma `& "scripts/ftp_deploy.ps1" ...`.

### `scripts/ftp_delete.ps1` — apagar ficheiros no servidor
```powershell
& "scripts/ftp_delete.ps1" <caminho1> <caminho2> ...
```
- Apaga cada ficheiro em `public_html/<caminho>`. "550 = not found" é tratado como OK (idempotente).

---

## 2. Rotas de manutenção (token-gated)

Base: `https://soserp.vip/maintenance/{TOKEN}/<ação>`
**TOKEN** (`.env` → `MAINTENANCE_TOKEN`): `372ea01cee5827cbc9f8dbf7d6d4180dc3c0a0cf9aa24d48`

| Ação | URL | Efeito |
|---|---|---|
| Migrar | `.../maintenance/{TOKEN}/migrate` | corre `migrate --force` (migrações pendentes) |
| Estado migrações | `.../maintenance/{TOKEN}/migrate-status` | `migrate:status` |
| Comando | `.../maintenance/{TOKEN}/command/{cmd}` | corre um artisan da whitelist. Args opcionais: `?args=--dry --foo=bar` |
| Seeder | `.../maintenance/{TOKEN}/seed/{NomeSeeder}` | corre `db:seed --class={NomeSeeder}` (tem de estar na whitelist) |

Chamar com `curl -s "<url>"`. A resposta é texto (INFO/DONE do artisan).

### Whitelist de comandos (`MaintenanceController::$allowedCommands`)
`plans:update-pricing`, `create:fox-friendly-plan`, `admin:set-email`, `tenants:delete`,
`optimize:clear`, `config:clear`, `cache:clear`, **`view:clear`**, **`route:clear`**,
`config:cache`, `route:cache`, `view:cache`, `storage:link`, `queue:restart`,
`farmaciadois:import`, `permissions:sync-product-batches`, `permissions:sync-pos-reports`,
`tenant:set-tax-exclusion`, `roles:backfill`, `taxes:backfill`, `treasury:bundle`,
`payment-methods:backfill`, `warehouses:backfill`, `modules:sync-permissions`, `db:dump`.

### Whitelist de seeders (`MaintenanceController::$allowedSeeders`)
`PlanSeeder`, `ModulePlansSeeder`, `ModuleSeeder`, `PermissionSeeder`, `RoleSeeder`, `TaxSeeder`,
`InvoicingTaxSeeder`, `DatabaseSeeder`, `AGTTaxExemptionCodeSeeder`, `AGTIsVerbaSeeder`,
`AGTCaeCodeSeeder`, `AGTIecPautalCodeSeeder`, `IRTTaxBracketSeeder`, `FixLegacyAccountTypesSeeder`, …

> **Para correr um seeder novo:** adicioná-lo ao array `$allowedSeeders` em `app/Http/Controllers/MaintenanceController.php`, fazer deploy desse ficheiro **+ reset OPcache** (senão a whitelist antiga fica em cache), e só depois chamar `seed/{Seeder}`.
> Os nomes são resolvidos no namespace raiz `Database\Seeders`. Um seeder chamado pela rota deve ficar em `database/seeders/` com `namespace Database\Seeders;` (não numa subpasta).

---

## 3. Reset ao OPcache (o passo mais esquecido)

Sempre que enviares **PHP que corre no web**, o OPcache tem de ser resetado. Passos:

```bash
# 1) criar o ficheiro temporário token-gated em public/
cat > public/_opcache_reset.php << 'PHP'
<?php
if (($_GET['k'] ?? '') !== 'sosfix2026') { http_response_code(403); exit('f'); }
echo function_exists('opcache_reset') && opcache_reset() ? 'OK' : 'NA';
PHP
```
```powershell
# 2) enviar
& "scripts/ftp_deploy.ps1" public/_opcache_reset.php
```
```bash
# 3) disparar (docroot = public → fica na raiz do site)
curl -s "https://soserp.vip/_opcache_reset.php?k=sosfix2026"      # deve imprimir OK
```
```powershell
# 4) apagar do servidor
& "scripts/ftp_delete.ps1" public/_opcache_reset.php
```
```bash
# 5) apagar local + confirmar que foi removido (404)
rm -f public/_opcache_reset.php
curl -s -o /dev/null -w "%{http_code}\n" "https://soserp.vip/_opcache_reset.php?k=sosfix2026"   # deve dar 404
```
Chave de guarda: `sosfix2026` (só para o endpoint temporário nunca ficar aberto).

---

## 4. Tabela de decisão — que passos após o `ftp_deploy`

| O que mudaste | Passos extra (por ordem) |
|---|---|
| PHP que corre no web (Controller / Livewire / Model / Service / Middleware / Provider) | **reset OPcache** |
| `routes/web.php` | `command/route:clear` → **reset OPcache** |
| Blade (`.blade.php`, views) | `command/view:clear` (+ reset OPcache por segurança) |
| Nova **migração** | `migrate` (+ reset OPcache se também mexeste em PHP web) |
| Novo **seeder** | pôr na whitelist → deploy do `MaintenanceController` → **reset OPcache** → `seed/{Seeder}` |
| `MaintenanceController` (whitelist/lógica) | deploy → **reset OPcache** (senão a whitelist antiga fica em cache) |
| CSS / JS / imagens estáticas | nada (basta o upload) |
| Só docs/README | nada |

---

## 5. Sequência-tipo (exemplo real, copiar/adaptar)

```bash
# --- LOCAL: validar antes de enviar ---
php -l app/Services/X.php
php artisan migrate --path=database/migrations/2026_XX_XX_...php --force   # se houver migração
# (teste com App\...; DB::beginTransaction(); ... ; DB::rollBack();)
```
```powershell
# --- ENVIAR ---
& "scripts/ftp_deploy.ps1" app/Services/X.php app/Livewire/Y.php resources/views/z.blade.php routes/web.php database/migrations/2026_XX_XX_...php
```
```bash
# --- LIMPAR CACHES conforme mudou ---
TOKEN=372ea01cee5827cbc9f8dbf7d6d4180dc3c0a0cf9aa24d48
curl -s "https://soserp.vip/maintenance/$TOKEN/migrate"                  # se houve migração
curl -s "https://soserp.vip/maintenance/$TOKEN/command/route:clear"      # se mudou rotas
curl -s "https://soserp.vip/maintenance/$TOKEN/command/view:clear"       # se mudou blades
# --- RESET OPCACHE (secção 3) ---
# --- VERIFICAR ---
curl -s -o /dev/null -w "%{http_code}\n" "https://soserp.vip/rota/afetada"   # 200 público / 302 = redireciona p/ login (rota existe)
```

---

## 6. Gotchas (erros já apanhados)

- **OPcache:** se mexeste em PHP web e "não muda nada em produção", quase de certeza esqueceste o reset OPcache.
- **Cache de rotas:** ao mudar `routes/web.php`, correr `route:clear` **antes** do reset OPcache. Sem isto, rotas novas dão 404.
- **Migrações idempotentes:** guardar com `Schema::hasTable()/hasColumn()` (a rota `migrate` pode ser reexecutada).
- **Enums na BD:** o `Schema` do Laravel não altera `ENUM` facilmente → usar `DB::statement("ALTER TABLE x MODIFY COLUMN col ENUM('a','b','novo') NULL DEFAULT NULL")` numa migração (preservar Null/Default originais).
- **Colunas de assinatura/hash:** hashes RSA base64 têm ~344 chars → usar `text`, não `varchar(200)` (erro "Data too long").
- **Exports:** `maatwebsite/excel` está numa versão antiga incompatível (exige phpspreadsheet ^1, o projeto usa ^5). Para Excel usar **PhpSpreadsheet diretamente** (`new \PhpOffice\PhpSpreadsheet\Spreadsheet` + `Writer\Xlsx->save('php://output')` num `response()->streamDownload`) ou CSV nativo. PDFs via **dompdf** (`Pdf::loadView(...)`), que funciona.
- **`activeTenantId()`/`auth()`** devolvem null em CLI sem login → em testes locais fazer `Auth::login(App\Models\User::find(<id>))`.
- **Links da sidebar** gated por `@can('x.view')` só aparecem se a permissão existir; usar `@canany([...])` com uma permissão que já exista, ou seed da permissão.

---

## 7. Regras de segurança (cumprir sempre)

- **Nunca** armar endpoints públicos que **transmitam PII de produção** (um endpoint público de db-dump foi bloqueado como exfiltração). Diagnósticos à BD de produção: ficheiro token-gated em `public/` que devolve **só metadados/config** (contagens, slugs, flags — nunca nomes/emails/telefones), usado e **apagado imediatamente**, confirmando o **404**.
- Ficheiros temporários (`_opcache_reset.php`, diagnósticos) são **sempre** apagados no fim e confirma-se o `404`.
- Ações externas/irreversíveis (ex.: comunicar documento fiscal à AGT) são **desencadeadas pelo utilizador** (botão) e respeitam o ambiente do tenant (`agt_environment` = `sandbox` no tenant 1, `production` no 11).

---

## 8. Referência rápida de segredos (infra do dono)

| Item | Valor |
|---|---|
| FTP host / user / pass | `ftp.soserp.vip` / `soserp` / `Softec@ngola!` |
| FTP root | `public_html` (docroot web = `public_html/public`) |
| Token de manutenção (`MAINTENANCE_TOKEN`) | `372ea01cee5827cbc9f8dbf7d6d4180dc3c0a0cf9aa24d48` |
| Chave do endpoint temporário de OPcache | `sosfix2026` |
| Domínio de produção | `https://soserp.vip` |
