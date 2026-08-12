# SOSERP — Product Requirements Document (PRD)

> **Documento de contexto para agentes de IA.**
> Lê isto antes de escrever qualquer código neste repositório. Descreve o que o
> sistema é, as regras de negócio que não podem ser inferidas do código, e as
> armadilhas que já causaram bugs em produção.
>
> Complemento visual: `arquitetura/arquitetura.html` (abrir no browser).
>
> Última revisão: 2026-08-02 · Versão do sistema: v1.4.x (FE/AGT multi-tenant)

---

## 1. O que é o SOSERP

ERP SaaS **multi-empresa** para o mercado **angolano**, em produção em
`https://soserp.vip`. Vende-se por subscrição mensal em Kwanzas (Kz), com
módulos activáveis por plano.

O diferenciador é a **conformidade fiscal angolana**: facturação certificada
pela AGT (Administração Geral Tributária) ao abrigo do Decreto 71/25, exportação
SAFT-AO, plano de contas PGC-AO e cálculo de IRT sobre salários segundo a tabela
angolana em vigor.

**Público-alvo:** PME angolanas — farmácias, retalho, oficinas, salões, hotéis,
prestadores de serviços. Muitas operam com **internet intermitente**, daí o POS
funcionar offline.

**Idioma:** todo o produto, comentários de código e mensagens de commit em
**português de Portugal**. Nomes de classes/métodos em inglês (convenção
Laravel), comentários e strings visíveis em português.

---

## 2. Stack técnica

| Camada | Tecnologia |
|---|---|
| Runtime | PHP 8.3 (composer exige `^8.2`) |
| Framework | Laravel 12 |
| UI | Livewire 3.6 + Blade + Tailwind CSS + Alpine.js |
| Permissões | spatie/laravel-permission 6.21 **com `teams = true`** |
| Base de dados | MySQL (207 tabelas no esquema validado em 2026-08-02; negócio isolado por `tenant_id`) |
| Offline | PWA: Service Worker + Dexie/IndexedDB (`SosPwa`) |
| Deploy | FTP (`scripts/ftp_deploy.ps1`) + rotas de manutenção com token |

**Dimensão do código:** 160 models · 191 componentes Livewire · 42 serviços ·
48 controllers · 19 observers · 66 comandos artisan · 262 migrações · 513 vistas.

**Arquitectura de UI: Livewire-first.** A esmagadora maioria dos ecrãs é um
componente Livewire montado directamente na rota. Os controllers existem quase
só para PDFs, exportações e a API móvel. **Não crie um controller para um ecrã
novo** — crie um componente Livewire.

---

## 3. Multi-tenancy — a regra número um

**Base de dados única, partilhada.** O isolamento é por coluna `tenant_id`.
Não há uma BD por cliente. Não há schemas separados.

```
tenants (empresas)
   ├── users            (um user pode pertencer a várias empresas)
   ├── roles            (roles.tenant_id — as roles são POR EMPRESA)
   └── 122 tabelas de negócio, todas com tenant_id
```

### Como se aplica

- **Trait `BelongsToTenant`** (`app/Traits/BelongsToTenant.php`) — aplica um
  global scope que filtra por `tenant_id` e preenche-o ao criar. Todo o model de
  negócio novo deve usá-lo.
- **Helper `activeTenantId()`** — a empresa activa da sessão. Usa-o sempre que
  escreveres uma query manual (`DB::table(...)`).
- **Um utilizador pode ter várias empresas** e trocar entre elas. O plano define
  o limite (`plans.max_companies`).

### Armadilhas reais (já aconteceram)

1. **Índices únicos têm de ser compostos.** Qualquer identificador gerado por
   empresa (`nif`, `shift_number`, `code` de método de pagamento, numeração de
   documentos) precisa de `UNIQUE (tenant_id, coluna)` — **nunca** único global.
   Um índice global faz a empresa B falhar ao criar um código que a empresa A já
   usa. Já partiu a criação de métodos de pagamento em produção.
2. **Validação `unique` do Laravel também tem de ser por empresa:**
   ```php
   Rule::unique('tabela', 'coluna')
       ->where(fn($q) => $q->where('tenant_id', activeTenantId()))
       ->ignore($this->id)
   ```
3. **Documentos não podem apontar para clientes de outra empresa.** Já houve
   1537 documentos com referências cruzadas, corrigidos por
   `FixCrossTenantClients`.

---

## 4. Permissões — o erro mais fácil de cometer

Usa-se **spatie/laravel-permission com `teams = true`**, em que o "team" é o
tenant. Consequência crítica:

> **`hasRole('Super Admin')` resolve no contexto da empresa activa.**
> Uma empresa qualquer pode criar uma role chamada "Super Admin" e ganhá-la.

**Nunca uses `hasRole()` para controlar acesso ao nível da plataforma.**
Isto já causou uma escalação de privilégios real: 5 donos de empresa chegaram ao
painel `/superadmin`, incluindo à chave privada SAFT.

Usa **`$user->isPlatformSuperAdmin()`** — verifica a flag `users.is_super_admin`
OU uma role global.

> **Subtileza que já reabriu o buraco uma segunda vez:** "role global" exige
> **duas** condições, não uma. A role tem de ter `roles.tenant_id IS NULL` **e** a
> atribuição tem de ter `model_has_roles.tenant_id IS NULL`. A role "Super Admin"
> original ficou com `tenant_id` nulo na tabela `roles` mas foi atribuída dentro
> do contexto de uma empresa (`model_has_roles.tenant_id = 1`) — verificar só a
> primeira condição dava acesso ao `/superadmin` a um utilizador de empresa.
> Auditoria: `php artisan superadmin:audit` (e `--fix` para limpar).

### Estrutura de permissões

- Nome no formato `modulo.recurso.acao` — ex.: `invoicing.sales.invoices.create`,
  `accounting.moves.view`, `treasury.cash-registers.view`.
- O mapa canónico role → permissões vive em
  **`getDefaultRolePermissionMap()`** (`app/Helpers/RoleHelper.php`). É a fonte
  única de verdade; usada tanto na criação da empresa como na sincronização de
  planos.
- Roles por omissão: `Super Admin`, `Admin`, `Gestor`, `Utilizador`,
  `Administrador Faturação`, `Vendedor`, `Caixa`, `Contabilista`,
  `Operador Stock`.

### Visibilidade do menu

`User::canAccessModuleMenu($slug)` decide o que aparece na barra lateral:
módulo activo no plano **E** o utilizador tem ≥1 permissão com o prefixo do
módulo. **Se criares um módulo, cria também as permissões** — senão o menu fica
invisível para toda a gente. (O módulo Contabilidade esteve assim: zero
permissões `accounting.*` existiam.) Há uma salvaguarda que mostra o módulo
quando o sistema não tem nenhuma permissão registada com esse prefixo, mas não
confies nela: define as permissões.

Comando de reparação: `php artisan modules:sync-permissions`.

---

## 5. Módulos e planos

### Catálogo (14 módulos)

| Slug | Nome | Estado |
|---|---|---|
| `invoicing` | Facturação | completo |
| `treasury` | Tesouraria | completo |
| `contabilidade` | Contabilidade | completo (PGC-AO) |
| `rh` | Recursos Humanos | completo (IRT, folha) |
| `oficina` | Gestão de Oficina | completo |
| `eventos` | Gestão de Eventos | completo |
| `hotel` | Gestão de Hotel | completo (reservas públicas) |
| `salon` | Salão de Beleza | completo (marcações públicas) |
| `restaurant` | Gestão de Restaurante | operacional: sala, KDS, reservas, receitas, stock, checkout e relatórios |
| `compras` | Compras | completo |
| `notifications` | Notificações | completo (SMS/WhatsApp) |
| `crm` | CRM | em construção |
| `inventario` | Inventário | em construção |
| `projetos` | Projectos | em construção |

### Dependências entre módulos — obrigatórias

`TenantModuleSyncService::MODULE_DEPENDENCIES`:

```php
'invoicing' => ['treasury'],   // bidireccional: trabalham sempre juntos
'treasury'  => ['invoicing'],
'compras'   => ['invoicing'],
'salon'     => ['invoicing'],
'hotel'     => ['invoicing'],
'restaurant'=> ['invoicing'], // implementado; invoicing activa também treasury
```

**Facturação e Tesouraria são inseparáveis.** Uma factura precisa de métodos de
pagamento e de caixa; um recebimento precisa de um documento. Activar um sem o
outro deixa o módulo partido — foi um bug real (facturação sem acesso a formas
de pagamento).

**Toda a activação de módulo passa por `TenantModuleSyncService::activateModule()`**,
que resolve dependências transitivamente e semeia pré-requisitos
(`seedModulePrerequisites`: métodos de pagamento, impostos, armazém por omissão,
configurações). Nunca escrevas directamente na tabela pivot `tenant_module`.

### Planos (10)

| Slug | Preço/mês | Trial | Auto-activa | Users | Empresas |
|---|---|---|---|---|---|
| `fox-friendly` | 0 Kz | 180 d | **sim** | 999 | 50 |
| `starter` | 4.900 Kz | 14 d | não | 3 | 1 |
| `pacote-vendas` | 5.900 Kz | 14 d | não | 5 | 1 |
| `pacote-salao` | 7.900 Kz | 14 d | não | 6 | 1 |
| `pacote-rh` | 9.900 Kz | 14 d | não | 8 | 1 |
| `professional` | 11.900 Kz | 14 d | não | 10 | 3 |
| `pacote-oficina` | 12.900 Kz | 14 d | não | 8 | 1 |
| `pacote-hotel` | 19.900 Kz | 30 d | não | 10 | 1 |
| `business` | 24.900 Kz | 30 d | não | 50 | 10 |
| `enterprise` | 49.900 Kz | 30 d | não | 999 | 999 |

### Regra de activação — não a inverta

- **Plano gratuito** (`price_monthly <= 0` ou `auto_activate = 1`) → activa
  **automaticamente** no registo.
- **Plano pago** → fica em `pending`. **O super admin da plataforma activa
  manualmente** depois de validar o comprovativo de pagamento.
- **Enquanto `pending`, nenhum módulo é activado.** Um bug anterior activava
  planos pagos automaticamente — buraco de receita directo.
- Planos com `is_promotional = 1` (FOX Friendly) só podem ser activados **uma
  vez por empresa**.

### Multi-empresa

Quando um utilizador cria uma 2.ª empresa, ela **herda os módulos do plano**,
não os da 1.ª empresa. A propagação respeita `plans.max_companies`
(`CheckSubscription`).

---

## 6. Facturação e conformidade AGT

Este é o coração fiscal do produto. **Erros aqui têm consequências legais para o
cliente.**

### Tipos de documento (SAFT-AO)

| Código | Documento | Notas |
|---|---|---|
| `FT` | Factura | o mais comum |
| `FR` | Factura-Recibo | pago no acto (POS) |
| `FS` | Factura Simplificada | |
| `NC` | Nota de Crédito | corrige/anula uma factura |
| `ND` | Nota de Débito | acresce valor a uma factura |
| `GT` | Guia de Transporte | |

### Regras invioláveis

1. **Documentos fiscais emitidos NÃO se editam nem se apagam.** A AGT não o
   permite. Os botões de editar/apagar foram removidos das facturas de venda e
   compra por esta razão. Correcções fazem-se por **nota de crédito ou débito**.
   Se implementares um ecrã de documentos, não reintroduzas esses botões para
   documentos com `invoice_status = 'F'`.
2. **Cadeia de hash.** Cada documento assinado encadeia com o anterior. O hash
   cobre `data + número + total ilíquido + hash anterior` — **o cliente e o tipo
   de documento NÃO entram no hash**. Alterar a fórmula invalida toda a série.
3. **`invoice_status`**: `N` (normal/rascunho) → `F` (facturado/definitivo) →
   `A` (anulado). A transição para `F` é o ponto de não retorno.
4. **Linhas isentas exigem código de isenção** (`varchar 10`) **e motivo**
   (`varchar 255`). Uma linha a 0% sem código é rejeitada.
5. **`gross_total = net_total + tax_payable`.** A retenção (IRT) declara-se no
   seu campo próprio e **nunca** se abate ao `gross_total` — o que o cliente
   paga vive em `total`. O POS gravava aqui o `total` (já líquido de retenção) e
   o documento não fechava.
6. **`net_total`, `tax_payable` e `gross_total` têm default `0.00`, não `NULL`.**
   Os serviços da AGT fazem `$doc->gross_total ?? $doc->total`; como `0.00` não
   é `null`, o fallback **nunca** dispara e o documento seria assinado com
   totais a zero. Preencher sempre.
7. **Retenção de IRT é decisão do adquirente, não automática.** Retém quem está
   obrigado — `client.type === 'pessoa_juridica'`. Um particular (hóspede,
   cliente de oficina) não retém. Aplicá-la a toda a gente descontava do
   documento um imposto que ninguém entregaria ao Estado.

### Faturação dos módulos de negócio — um só emissor

**Oficina, hotel e salão não emitem facturas por conta própria.**
`App\Services\Invoicing\ModuleInvoiceService::emitir()` é o emissor único: o
módulo descreve **o que vendeu** (linhas, com `is_service` por linha) e o
serviço decide a fiscalidade.

Trata de: imposto por linha via `TaxResolver`, código de isenção obrigatório,
retenção só quando `retencao_irt` é pedido e só sobre serviços, descontos de
linha no total do documento, campos SAFT, hash encadeado, e o **vínculo de cada
linha a um artigo do catálogo** — a linha aponta para um artigo tal como o
documento aponta para um cliente, porque é do artigo que sai o regime fiscal.

Cada módulo tinha a sua cópia e todas divergiam: IVA fixo a 14%, IRT sobre
peças, descontos perdidos, totais SAFT a zero. Ao tocar em faturação de um
módulo, chamar o serviço — nunca `SalesInvoice::create()`.

**Origem do documento:** `invoicing_sales_invoices.source_module` +
`source_reference` (ex.: `hotel` / `RES-000123`). Não confundir com
`source_id`/`source_billing`, que são campos SAFT. É esta ligação que permite
listar todas as facturas de uma reserva ou OS — o `invoice_id` do lado do
módulo é 1:1 e vai sendo sobrescrito. Histórico:
`php artisan invoices:backfill-origem --dry-run`.

**Comunicação à AGT.** O emissor submete com `DB::afterCommit`, **nunca**
directamente: os chamadores (check-out do hotel, conversão de OS) abrem a sua
própria transacção antes de emitir, pelo que a do emissor fica aninhada e o
"commit" dela é só um savepoint. Comunicar aí significa, num rollback do
chamador, ter enviado à AGT um documento que deixou de existir na base — sem
sequer forma de o anular, porque não há documento. A submissão respeita
`agt_auto_submit`, nunca lança e nunca bloqueia a gravação.

**Adiantamentos:** um sinal gera FT própria; no check-out fatura-se **apenas o
que ainda não foi facturado**. O valor já facturado é **consumido contra as
linhas**: a totalmente coberta sai do documento, a parcialmente coberta entra
só pelo que falta, as restantes ficam intactas. Se cobrir tudo, não se emite
documento nenhum.

Três formas que **não** servem:
- **Linha negativa** — uma FT com quantidade positiva e valor negativo é
  estrutura que a AGT não prevê; rectificação de valor faz-se por NC/ND.
- **Desconto de documento** — o imposto é somado linha a linha *antes* dos
  descontos, pelo que a base descia e ficava o IVA da estadia inteira.
- **Abater pelo pago** em vez do facturado — pode haver dinheiro recebido sem
  documento, e isso troca a sobre-liquidação por uma sub-liquidação, que é pior.

### Os 3 regimes de IVA — nunca fixes 14% no código

`Tenant::REGIMES` — cada empresa escolhe **um**:

| Regime | Taxa | Código SAFT | Quando |
|---|---|---|---|
| `regime_geral` | 14% | `NOR` | volume > 350.000.000 Kz (ou por opção) |
| `regime_simplificado` | 7% | `RED` | volume entre 10M e 350M Kz |
| `regime_nao_sujeicao` | 0% | `ISE` (motivo `M04`) | não sujeição |

Aliases legados: `regime_isencao` → `regime_nao_sujeicao`,
`regime_misto` → `regime_geral`.

- **`App\Services\Invoicing\TaxResolver` é a fonte única do imposto por linha.**
  Qualquer ecrã, API ou importação que calcule imposto tem de passar por aqui.
  Taxa 0 ⇒ `ISE` + código de isenção obrigatório.
- **`TaxRegimeSyncer`** propaga o regime da empresa para impostos, configurações
  e produtos quando o regime muda.
- **Nunca escrevas `14` nem `0.14` no código.** Já houve regressões por isso.

### Integração AGT

- Serviços em `app/Services/AGT/` — assinatura JWS, construção de payload,
  QR code, registo de séries, validação, consulta.
- Ambiente por empresa em `invoicing_settings.agt_environment`: `sandbox` usa
  `https://sifphml.minfin.gov.ao/sigt/fe/v1`; produção usa
  `https://sifp.minfin.gov.ao/sigt/fe/v1`.
- Credenciais **Basic Auth do produtor** são globais e geridas apenas pelo Super
  Admin da plataforma. As chaves RSA pública/privada do contribuinte são
  isoladas por empresa em **`storage/app/private/agt/tenants/{tenant_id}/`**.
  O código usa `Storage::disk('local')` com o caminho relativo
  `agt/tenants/{id}`; no Laravel 11+ a raiz do disco `local` é
  `storage/app/private`, **não** `storage/app`. Procurar em `storage/app/agt/…`
  leva à conclusão errada de que as chaves não existem.
- O tenant escolhe ambiente, auto-submissão e validação obrigatória, mas não pode
  alterar as credenciais globais do produtor.
- Com `agt_auto_submit = true`, FT/FR/NC/ND/RC são submetidos após emissão. O
  `PollAGTStatusJob` consulta `ObterEstado` em background e persiste
  `submitted`, `validated`, `rejected` ou `cancelled`.
- Uma submissão deve ser única por `(tenant_id, document_type, document_id)`.
  Reenvios reutilizam o registo; um documento já validado nunca é reenviado.
  Falhas DNS posteriores não podem substituir um estado fiscal validado.
  ⚠️ **A unicidade é só ao nível da aplicação — não existe índice único na BD.**
  A migração `2026_07_28_190000_deduplicate_agt_submissions` apenas limpou os
  duplicados que já existiam; nada impede que uma corrida os volte a criar.
  Contramedida em falta: `UNIQUE (tenant_id, document_type, document_id)`.
- `ListarFacturas` lista **documentos recebidos** — aqueles em que a empresa é o
  **adquirente**. Não devolve os documentos que a empresa emitiu. Para quem só
  emite, `documentResultCount = 0` é o resultado normal e correcto, não um erro.
  Confirmado no sandbox do tenant 11 (NIF 5001363476): 5 documentos emitidos a
  "Consumidor Final" visíveis no separador *Documentos emitidos* do portal, e
  `listarFacturas` a devolver 0 — porque nenhum deles tem o tenant como
  adquirente. Documentos emitidos consultam-se com `ConsultarFactura` (número
  fiscal completo) ou no separador Submissões.
- `ConsultarFactura` exige o número fiscal legal completo, por exemplo
  `NC NC7626S7057N/000003`. A referência amigável `SOS-NC-000003` é apenas UI.
- `requestErrorList: [""]` e `errorList: [""]` significam lista sem erro. Os
  clientes normalizam erros aninhados, devolvem HTTP real e repetem falhas
  transitórias de DNS/conexão.

### Séries fiscais e Fatura-Recibo

- Séries são estritamente por tenant. Com chaves RSA configuradas, a emissão só
  aceita séries ativas e registadas na AGT (`agt_series_id` preenchido).
- `FR` usa `document_type = pos` e a **mesma série** é partilhada pelo POS online,
  POS offline e criação manual de Fatura-Recibo — nenhum destes três caminhos
  pode inventar uma sequência própria fora do sistema de séries. Isto **não**
  significa uma série por empresa: uma empresa pode ter várias séries FR (por
  estabelecimento, por exemplo) e o tenant 1 tem de facto duas activas
  (`A` e `B`, esta última por omissão). O que não pode existir é numeração FR
  gerada fora de `invoicing_series`.
- Todos os tenants existentes receberam backfill da série FR pela migração
  `2026_07_28_193000_backfill_fr_series_for_all_tenants.php`; novos tenants e a
  ativação do módulo de faturação também a criam de forma idempotente.
- O código da série devolvido pela AGT é usado no número e ATCUD:
  `FR {seriesCode}/000001` e `{seriesCode}-1`.
- `ObterEstado` não devolve ATCUD. `AGTSubmission::markAsValidated()` deve
  preservar o ATCUD já gerado, nunca substituí-lo por `null`.
- Validação comprovada no sandbox do tenant 11:
  série `FR7626S6286N`, documento `FR FR7626S6286N/000001`,
  ATCUD `FR7626S6286N-1`, request `202600002492155`, estado AGT `V`.

---

## 7. Stock — invariante que já foi corrompida

> **As linhas de `invoicing_stocks` são a fonte de verdade.
> `products.stock_quantity` é um agregado derivado.**

O agregado é mantido **exclusivamente** pelo `StockObserver`, que reage aos
eventos Eloquent `saved` e `deleted`.

### Regras

- ❌ **NUNCA** uses `increment()` / `decrement()` em stock — disparam apenas
  `updating`/`updated`, o observer não os apanha e o agregado dessincroniza.
- ❌ **NUNCA** escrevas stock com `DB::table(...)->update(...)` — não passa pelo
  Eloquent, logo não passa pelo observer.
- ✅ Carrega o model, altera a propriedade, chama `save()`.
- ✅ Quando o stock **já foi actualizado** e só queres registar o movimento, usa
  `StockMovement::withoutEvents()` — senão contas duas vezes.

Reparação: `php artisan stock:reconcile --fix-negatives`.

Este conjunto de regras nasceu de uma corrupção massiva de stock em produção
(causa raiz + 12 bugs de código + reparação de dados).

### Movimento e baixa têm de ser atómicos

O hook que desconta é o `created` do `StockMovement` — corre **depois** do
insert. Sem transacção, quando `Stock::removeStock()` rebenta por falta de
stock a linha do movimento fica gravada na mesma: a verificação de duplicado
passa a saltar aquele documento para sempre e uma devolução posterior
**inflaciona** o stock com unidades que nunca saíram. Envolver sempre em
`DB::transaction()`.

### Agregado órfão

`stock_quantity > 0` com **zero linhas** de armazém é a origem do "o produto
aparece disponível mas a baixa falha": o ecrã lista pelo agregado e a saída não
encontra linha nenhuma para descontar. Ao criar um artigo com stock inicial,
materializar sempre a linha — se a empresa não tiver armazém, criar um em vez
de deixar o agregado sem suporte.

`invoicing_products.stock_quantity` é `decimal(15,3)` (era `int`, e arredondava
2,5 L de óleo para 2 ou 3 a cada sincronização).

### Um documento, uma baixa

Quando duas origens podem descontar a mesma peça (a OS ao concluir, e a factura
ao ser emitida), a segunda tem de reconhecer a primeira. O guarda de
idempotência do `SalesInvoiceObserver` procura movimentos por
`reference_type = SalesInvoice::class`; os da oficina têm
`reference_type = 'WorkOrder'` e não são vistos por ele — daí a verificação
explícita.

---

## 8. Contabilidade (PGC-AO)

- Novas empresas recebem o **plano de contas PGC-AO** por omissão
  (`AccountSeeder::runForTenant`).
- Relatórios são **agnósticos ao plano de contas**: o balanço agrupa por **tipo
  de conta**; a demonstração de resultados agrupa por **subárvore de prefixo de
  código** via `integration_key`. Não fixes números de conta nos relatórios.
- **Integração Facturação → Contabilidade**: `SalesInvoiceAccountingObserver`
  dispara quando a factura fica definitiva (`invoice_status = 'F'`), nunca na
  criação — no `create()` a factura ainda não tem linhas nem totais. É
  idempotente (não gera dois movimentos para o mesmo `ref`) e nunca deixa
  rebentar a facturação.
- ⚠️ `App\Models\Invoice` (tabela `invoices`) é **legado morto, 0 registos**.
  As facturas reais são `App\Models\Invoicing\SalesInvoice`
  (`invoicing_sales_invoices`). Se vires código a apontar para o primeiro, é bug.
- Requisitos para um lançamento nascer: integração activa na empresa
  (`tenants.accounting_integration_enabled`), mapeamento activo para o evento e
  **período contabilístico aberto** para a data do documento.

---

## 9. Recursos Humanos — IRT

O **IRT** (Imposto sobre o Rendimento do Trabalho) angolano segue uma tabela
progressiva com:
- **isenção até 150.000 Kz**;
- progressão **contínua, sem saltos** entre escalões.

A tabela existe em **dois sítios que têm de coincidir**: o seeder na base de
dados e o `AngolanTaxHelper`. Se alterares um, altera o outro — já houve
divergência em produção.

---

## 10. POS e modo offline

- POS é uma PWA. Vendas continuam a funcionar **sem internet**, guardadas em
  IndexedDB (Dexie) pelo motor `SosPwa`, e sincronizadas depois.
- Padrão de sincronização: evento `pwa:synced` → recarregar a vista.
- **Resiliência de sessão**: sessão expirada já não congela o POS — devolve 419
  para pedidos Livewire (bootstrap), mostra um overlay no cliente, e espelha o
  carrinho em `localStorage` para restauro.
- Vendas POS a consumidor final são legítimas: **cada empresa tem o seu próprio
  cliente "Consumidor Final"**.

---

## 11. Portais públicos

| Portal | Guard | Notas |
|---|---|---|
| Portal do cliente `/client/*` | `client` | facturas, extractos, proformas |
| Reservas de hotel | público | por `booking_slug` |
| Marcações de salão | público | por `booking_slug` |

- Disponibilidade de quartos calcula-se **por datas** via
  `Room::isAvailableForDates()` — **não** pela coluna `rooms.status`.
- "Factura em dívida" determina-se pelo **saldo** (`total - paid_amount`), **não**
  por `status = 'pending'` (esse estado muitas vezes nem existe).

---

## 12. Deploy e operação

**Não há CI/CD.** O deploy é por FTP para `soserp.vip`.

```powershell
# enviar ficheiros específicos
.\scripts\ftp_deploy.ps1 -Files @('app/Models/User.php', 'routes/web.php')

# remover ficheiros do servidor
.\scripts\ftp_delete.ps1 -Files @('public/_temp.php')
```

**Comandos artisan em produção** correm por rota HTTP protegida por token:

```
https://soserp.vip/maintenance/{TOKEN}/command/{comando}
```

O comando tem de estar na **whitelist** de `MaintenanceController`. O formato usa
`:` (ex.: `optimize:clear`), nunca `/`. Comandos úteis: `optimize:clear`,
`modules:repair`, `modules:sync-permissions`, `migrate`.

### OPcache

Produção corre com `opcache.validate_timestamps = 0` — **código novo não entra
em vigor sem reset de OPcache**. O procedimento é enviar um script temporário
protegido por chave, chamá-lo, e **apagá-lo imediatamente**, confirmando 404.

### Regras de segurança operacional (obrigatórias)

1. **Nunca** exponhas um endpoint público que devolva dados pessoais de
   produção. Diagnósticos em produção devem devolver **apenas contagens e
   metadados**, estar protegidos por token, e ser apagados logo a seguir com
   confirmação de 404.
2. **Testa localmente antes de fazer deploy**: `php -l` em todos os ficheiros +
   `php artisan test` (ver secção 14).
3. Não uses `-ExecutionPolicy Bypass` no PowerShell.
4. Não peças nem introduzas credenciais do utilizador.

---

## 13. Convenções de código

- **Comentários em português**, a explicar o *porquê* e não o *quê*. Quando um
  troço de código existe para evitar um bug conhecido, o comentário deve dizê-lo.
- Componentes Livewire em `app/Livewire/{Modulo}/`, vistas espelhadas em
  `resources/views/livewire/{modulo}/`.
- Models de negócio em namespaces por módulo: `App\Models\Invoicing\`,
  `App\Models\Accounting\`, `App\Models\Treasury\`, `App\Models\HR\`,
  `App\Models\Hotel\`, `App\Models\Salon\`, `App\Models\Workshop\`,
  `App\Models\Events\`, `App\Models\AGT\`, `App\Models\Support\`.
- Helpers globais em `app/Helpers/` (carregados pelo composer): `activeTenantId()`,
  `setPermissionsTeamId()`, `getDefaultRolePermissionMap()`,
  `getModulePermissionPrefixes()`, `createDefaultRolesForTenant()`.
- Comandos de reparação de dados devem ser **idempotentes**, oferecer
  `--dry-run` e **nunca destruir** — só acrescentar ou normalizar.
- **Blade: usa sempre `@php ... @endphp` em bloco.** A forma de uma linha,
  `@php(...)`, parte a compilação quando a expressão tem comparações ou
  ternários — o Blade fecha mal o `<?php` e o erro sai como *"unexpected token
  class"*, já dentro do HTML seguinte. Rebentou a listagem de facturas inteira.
- **Antes de escrever numa coluna, confirma que ela existe.** Esta base tem
  vários nomes parecidos e enganadores: a categoria é `App\Models\Category`
  (`invoicing_categories`) e não `Invoicing\ProductCategory`; o tipo de produto
  é `ENUM('produto','servico')` em português; o preço é `price`, não
  `selling_price`; o tipo de cliente é `type`
  (`pessoa_fisica`/`pessoa_juridica`), não `client_type`. Campos não-fillable
  são descartados **em silêncio** — o erro só aparece muito mais tarde.
- **Campos opcionais de formulário chegam como `''`, não `null`.** Gravar `''`
  numa coluna `datetime` rebenta com SQL 1292 e numa FK com erro de
  integridade. Normalizar com `?: null`.
- **Não faças `save()` dentro de um evento `updated`.** O Eloquent só sincroniza
  o `original` **depois** de o evento disparar: o modelo continua a parecer
  "sujo" e reentra em recursão infinita. Calcula no `saving`.

### Middleware disponível

| Alias | Função |
|---|---|
| `tenant.access` | garante que o user pertence à empresa |
| `tenant.active` | empresa não suspensa |
| `tenant.module:{slug}` | módulo activo no plano |
| `subscription` | subscrição válida |
| `permission:{nome}` | permissão Spatie |
| `superadmin` | super admin **da plataforma** (não da empresa) |
| `api.token` | autenticação da API móvel |

---

## 14. Estado de implementação e handoff para a próxima IA

### Concluído e publicado

- Correções do POS offline: sincronização resiliente, produtos sem limite fixo
  de 60, scroll incremental, categorias, turno aberto, sincronização obrigatória
  antes de fechar e criação rápida de cliente.
- API móvel com token próprio (`ResolveApiToken`), CORS, rotas
  `/api/v1/auth/*` e `/api/v1/invoicing/*`.
- App Flutter em `app_mobile/`, com build nativo Windows, SQLite offline,
  sincronização, sidebar por módulos/plano, branding oficial e páginas genéricas
  de faturação.
- Configuração FE separada entre produtor (Super Admin) e contribuinte (tenant).
- Séries AGT FT/FR/RC/NC/ND, referências amigáveis SOS apenas para UI,
  submissão assíncrona, polling e consolidação de duplicados.
- Migrações mais recentes:
  - `2026_07_28_190000_deduplicate_agt_submissions.php`
  - `2026_07_28_193000_backfill_fr_series_for_all_tenants.php`
  - `2026_08_02_180000_create_restaurant_foundation_tables.php` até
    `2026_08_02_230000_create_restaurant_wastes_table.php`

### Módulo Restaurante concluído (2026-08-02)

O módulo `restaurant` está operacional e depende de `invoicing`, que garante
também a Tesouraria. A comanda permanece uma entidade operacional: só o
`RestaurantCheckoutService`, através do `ModuleInvoiceService`, pode emitir
FR/FT, selecionar séries do tenant, resolver impostos e registar pagamentos.

- 16 tabelas próprias com `tenant_id`, `BelongsToTenant`, FKs e índices por empresa;
- Dashboard, mapa de sala/mesas, comandas, KDS, reservas, fichas técnicas,
  stock/desperdícios, relatórios e configurações;
- transferência de mesa, junção de comandas, conta por artigos, faturação
  parcial e pagamentos mistos;
- CRUD de estabelecimentos, zonas, mesas e estações, com eliminação bloqueada
  quando existe histórico;
- anulação pós-produção mantém o consumo e cria desperdício auditável com motivo;
- API `/api/v1/restaurant/*` protegida por token, subscrição, módulo/plano e tenant;
- tenant demo 11 com produtos, mesas, comandas, reservas e ficha técnica;
- fichas técnicas redesenhadas com pesquisa, grelha/lista, custos, estados e
  editor responsivo de receita/ingredientes;
- landing pública com cartão do módulo e `Pacote Restaurante` (14.900 Kz/mês),
  incluindo explicitamente `restaurant`, `invoicing` e `treasury`;
- FOX Friendly gratuito inclui automaticamente todos os módulos existentes,
  incluindo `restaurant`; `create:fox-friendly-plan` atualiza o JSON, o pivot
  `plan_module` e sincroniza tenants gratuitos já ativos;
- validação visual no navegador interno e **108 testes / 225 asserções** aprovadas.

Incidente conhecido e resolvido: durante a recompilação, o KDS apresentou 500
por uma vista compilada antiga. O procedimento após alterações Livewire é
`php artisan optimize:clear` seguido de `php artisan view:cache`. A rota
`/restaurant/kitchen` foi novamente validada com ticket, impressão e ação de aceitar.

Detalhes e roadmap não bloqueante: `arquitetura/PRD_MODULO_RESTAURANTE.md`.

### Unificação da faturação dos módulos (2026-08)

Auditoria aos módulos de oficina, hotel e salão. Cada um tinha a sua cópia da
fiscalidade e todas divergiam. Criado `ModuleInvoiceService` (secção 6) e
ligados os três; POS e PWA alinhados na identidade SAFT.

Defeitos corrigidos que vale a pena conhecer, porque a mesma classe de erro
existe provavelmente noutros módulos:

- **Oficina:** `workshop_work_order_items` não tinha `product_id` nem
  `invoice_id`, apesar de o modelo os declarar — adicionar um item rebentava.
  `WorkOrderItem::booted()` fazia `save()` no `updated` e entrava em recursão
  infinita. Nenhum blade chamava `updateStatus()`, pelo que `markAsCompleted()`
  nunca corria e **as peças nunca saíam do stock**.
- **Hotel:** walk-in e reserva online gravavam `source` fora do ENUM
  (`walkin`/`online` em vez de `walk_in`/`website`) — **nenhum dos dois fluxos
  alguma vez gravou uma reserva**. O check-in público por QR aceitava qualquer
  estado, ressuscitando reservas canceladas e reabrindo estadias facturadas.
  Consumos do folio nunca eram facturados. Sinal e check-out facturavam a
  estadia duas vezes.
- **Transversal:** `TaxResolver::forProductId` não usava `withTrashed()`, pelo
  que um artigo isento eliminado passava a ser facturado a 14% num documento já
  assinado.

Migrações: `add_product_id_to_workshop_work_order_items_table`,
`make_product_stock_quantity_decimal`, `add_source_module_to_sales_invoices_table`.

### Auditoria de conformidade AGT (2026-08-02)

Verificação de cada caminho que emite documento contra os requisitos AGT.
Lacunas fechadas:

- **Oficina e hotel emitiam sem comunicar.** O `ModuleInvoiceService` produzia
  documentos completos — série, número, ATCUD, hash — e **nunca** chamava
  `submitToAGT()`, apesar de o prometer no próprio comentário. Entravam na
  cadeia de hash sem existir para a AGT.
- **A Nota de Débito também não.** O modelo usa `HasAGTSignature` mas nada em
  todo o projecto a submetia; só a NC era comunicada.
- **ND: base do documento errada.** `net_total` usava o subtotal bruto enquanto
  as linhas descontavam — o cliente era debitado a mais no valor do desconto.
- **ND: retenção da factura inteira.** O `withholding_tax_amount` era copiado
  tal e qual: uma ND de 10.000 Kz sobre factura de 1.000.000 Kz declarava
  65.000 Kz de retenção. Agora é proporcional à base da nota.
- **ND: `addProduct` sem scope de empresa** — artigo de outra empresa entrava
  num documento fiscal desta. E `tax_code` fixo em `NOR` declarava taxas
  reduzidas como normais; região fixa em `AO` ignorava Cabinda.
- **Artigos criados pelos módulos** nasciam sem `tax_type`/`tax_rate_id`:
  funcionavam por acidente (o resolvedor recorria ao imposto da empresa) mas
  ficavam incompletos no catálogo.

**Por decidir com o cliente:** nenhum caminho de módulo declara **IEC** nem
**Imposto de Selo** — e o POS e o PWA estão na mesma situação. Antes de
investir, confirmar com o contabilista se algum tenant é sujeito passivo destes
impostos nestas operações; a correção exige colunas novas no artigo.

### Testes (novo)

`php artisan test` — 108 testes / 225 asserções contra MySQL `soserp_test`.
**Preparar uma vez:** `php scripts/prepare_test_db.php`. Ver `tests/README.md`.

Não usa sqlite: 85 das 272 migrações têm SQL específico de MySQL. Não usa
`migrate:fresh` porque **as migrações não correm de raiz** (ver pendência 11).

### Pendências prioritárias

1. **Produção sem credenciais globais AGT:** `soserp.vip` devolveu “Credenciais
   Basic Auth do produtor não configuradas”. O Super Admin deve guardar
   username/password diretamente em produção. Não copiar `.env` completo do
   localhost. Depois executar “Sincronizar com AGT” no tenant 11 para registar a
   FR criada pelo backfill.
2. **Chaves RSA por tenant em produção:** confirmar a existência de
   **`storage/app/private/agt/tenants/{id}/public_key.pem`** e `private_key.pem`
   (raiz do disco `local` no Laravel 11+). Não enviar chaves de um tenant para
   outro. Localmente só o tenant 11 tem par de chaves; a pasta do tenant 17
   existe mas está **vazia**.
3. **Worker de filas:** confirmar processo permanente para a fila
   `agt-polling`. Sem worker, documentos permanecem `submitted` até atualização
   manual, mesmo já validados na AGT.
4. **Unificar clientes AGT:** `AGTClient` (5 ficheiros, usado pela UI, com
   retry/normalização) e `AGTHttpClient` + `AGTPayloadBuilder` (7 ficheiros,
   camada de serviços: `SeriesService`, `RegisterService`, `QueryService`).
   **Já não há divergência de endpoint** — ambos usam
   `sifphml…/sigt/fe/v1` em sandbox e `sifp…/sigt/fe/v1` em produção; o bug do
   host errado (405) está corrigido. Falta só eliminar a duplicação, depois de
   testes de contrato para os sete endpoints.
5. **ResultCode misto (`1`):** atualizar o estado de cada documento com base em
   `documentStatusList`, não marcar todo o batch como validado.
6. **Ferramentas API:** renderizar `resultEntryList` e documentos consultados em
   tabelas amigáveis; o JSON bruto deve ficar apenas como detalhe técnico.
7. **Flutter:** completar CRUD real de documentos com linhas, pagamentos,
   validações fiscais e conflitos offline; os CRUD genéricos atuais cobrem
   sobretudo cadastros. Criar páginas nativas de Tesouraria e restantes módulos.
8. **Build Windows:** manter o instalador Inno Setup e validar assinatura,
   atualização automática e persistência após upgrade antes de distribuir.
9. **Stock:** rever e concluir `_sma_stock_chunk.php`; executar em blocos com
   offset verificável, reconciliar contagens e remover tabelas/ficheiros
   temporários apenas depois de validar o total final.
10. **Segurança:** remover credenciais FTP e token de manutenção hardcoded dos
    scripts e rodá-los. Migrar para variáveis seguras fora do repositório.
11. **As migrações não correm de raiz.**
    `2025_01_11_220000_create_hr_departments_table` referencia `tenants`, criada
    só em `2025_10_02_001_create_tenants_table`; uma base limpa morre em
    *"Failed to open the referenced table 'tenants'"* às 11 de 191 tabelas.
    Bloqueia CI e qualquer instalação nova. Enquanto não for resolvido, a base
    de testes é construída por cópia do esquema
    (`scripts/prepare_test_db.php`).
12. **Retenção de IRT na oficina** segue agora `client.type`. Confirmar com o
    cliente se é essa a regra de negócio pretendida para todos os casos.

### Verificação rápida para retomar

```powershell
php artisan test
php artisan migrate:status
php artisan queue:failed
php artisan route:list --path=api/v1
php artisan tinker --execute="dump(
    App\Models\Invoicing\InvoicingSeries::where('tenant_id', 11)
      ->where('document_type', 'pos')->first()
);"
```

No tenant 11, validar na UI:

- série FR com `agt_series_id`;
- uma nova FR recebe `invoice_type = FR`, `series_id`, ATCUD e pagamento;
- submissão passa de `submitted` para `validated`;
- `ConsultarFactura` encontra o número legal completo;
- `ListarFacturas` é interpretado como lista do adquirente.

---

## 15. Checklist antes de dar uma tarefa por concluída

- [ ] Nenhuma query nova sem filtro `tenant_id`
- [ ] Índices e validações `unique` compostos com `tenant_id`
- [ ] Nenhum acesso de plataforma controlado por `hasRole()`
- [ ] Nenhuma taxa de IVA fixa no código — passa pelo `TaxResolver`
- [ ] Módulo de negócio a facturar ⇒ usa `ModuleInvoiceService`, não
      `SalesInvoice::create()`
- [ ] `gross_total = net_total + tax_payable`; retenção fora do `gross_total`
- [ ] Nenhum `increment`/`decrement`/`DB::table` sobre stock
- [ ] Baixa de stock envolvida em `DB::transaction()`
- [ ] Colunas escritas confirmadas no esquema (não-fillable falha em silêncio)
- [ ] Blade sem `@php(...)` de uma linha com comparações
- [ ] Módulo novo ⇒ permissões criadas e mapeadas em `RoleHelper`
- [ ] Módulo novo ⇒ dependências declaradas em `MODULE_DEPENDENCIES`
- [ ] Documentos com `invoice_status = 'F'` sem botões de editar/apagar
- [ ] `php -l` limpo em todos os ficheiros alterados
- [ ] `php artisan test` verde
- [ ] Defeito corrigido ⇒ caso acrescentado à suite (senão volta)
- [ ] Ecrã novo ou alterado ⇒ renderiza (o teste de fumo descobre-o sozinho)
- [ ] Ficheiros temporários apagados de produção (404 confirmado)
