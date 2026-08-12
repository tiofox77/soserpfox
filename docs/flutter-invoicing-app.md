# SOS ERP — App Flutter do Módulo de Faturação (Especificação)

Documento de referência para construir uma app **Flutter** (Android/iOS) do módulo de
**Faturação** do SOS ERP, replicando o que a PWA offline já faz (catálogo, clientes,
rascunhos e POS Fatura-Recibo), com sincronização **offline-first**.

> Backend: Laravel 12 + Livewire, multi-tenant (single DB, `tenant_id`), AGT Angola / SAFT-AO.
> A PWA existente (`public/js/pwa-invoicing.js` + `resources/views/invoicing/offline/*`) é a
> referência funcional — a app Flutter deve manter o mesmo contrato de API e idempotência.

---

## 1. Arquitetura (offline-first)

```
┌────────────────────────────────────────────┐
│  Flutter App                                 │
│  UI (POS, Catálogo, Clientes, Rascunhos)     │
│  ├─ Estado (Riverpod/Bloc)                   │
│  ├─ DB local (Isar ou sqflite/Drift)         │
│  │    products, clients, series, tax_rates,  │
│  │    pos_sales, draft_documents, sync_queue,│
│  │    meta                                    │
│  └─ SyncEngine (Dio)                          │
│        - pull catálogo (incremental)          │
│        - push fila (FIFO, idempotente)        │
└──────────────┬───────────────────────────────┘
               │ HTTPS (JSON)
┌──────────────▼───────────────────────────────┐
│  Laravel  /api/v1/invoicing/*                 │
│  SyncController, ClientController,            │
│  DraftController, PosSaleController           │
└───────────────────────────────────────────────┘
```

Princípios:
- **Tudo grava primeiro localmente** e entra numa **fila de sincronização** (`sync_queue`).
- Cada operação tem um **`local_uuid`** → o servidor é **idempotente** (não duplica).
- **Imprimir/registar a venda funciona offline**; o número fiscal AGT e o QR só chegam ao sincronizar.
- Sincronização do catálogo é **incremental** (`?since=<ISO8601>`), por `updated_at`.

---

## 2. Autenticação

A API atual (`routes/web.php`, grupo `api/v1/invoicing`) usa **sessão web** (`middleware('auth')`),
porque a PWA corre no mesmo domínio. Para uma app Flutter (cliente nativo) recomenda-se
**Laravel Sanctum (token)**:

1. Adicionar rota de login token (a criar no backend):
   ```
   POST /api/v1/auth/login   { email, password, device_name }
   → 200 { token, user, tenant_id }
   ```
   No backend: `$user->createToken($deviceName)->plainTextToken`.
2. A app guarda o token de forma segura (`flutter_secure_storage`).
3. Todos os pedidos enviam `Authorization: Bearer <token>`.
4. **Tenant ativo:** o backend resolve via `activeTenantId()` (sessão). Para tokens, expor
   o `tenant_id` no login e enviar header `X-Tenant-Id: <id>` **ou** adaptar o backend para
   ler o tenant do token. (Ver §11 — Pendências no backend.)

> Enquanto Sanctum não existir, é possível autenticar por cookie de sessão
> (`POST /login` + cookie jar no Dio), mas **não é recomendado** para produção mobile.

Headers comuns:
```
Accept: application/json
Content-Type: application/json
Authorization: Bearer <token>
X-Requested-With: XMLHttpRequest
```

---

## 3. Endpoints da API

Base: `https://soserp.vip/api/v1/invoicing`

### 3.1 `GET /ping`
Verifica autenticação/online real.
```json
{ "ok": true, "server_time": "2026-06-01T12:00:00+00:00", "tenant_id": 17 }
```

### 3.2 `GET /sync?since=<ISO8601>`
Descarrega catálogo. Sem `since` = sync completo; com `since` = só o que mudou (`updated_at >= since`).
```json
{
  "server_time": "2026-06-01T12:00:00+00:00",
  "tenant_id": 17,
  "user": { "id": 5, "name": "Suzana" },
  "company": {
    "name": "Farmácia Neves Bendinha", "nif": "5417289442",
    "address": "...", "phone": "...", "logo": "https://...",
    "agt_cert": "FE/351/AGT/2026"
  },
  "shift": { "open": true, "number": "TRN-2026-0001", "opened_at": "..." },
  "data": {
    "products": [
      { "id": 15, "name": "PARACETAMOL", "sku": "P001", "barcode": "...",
        "type": "produto", "price": 500.0, "cost": 300.0, "tax_rate": 14.0,
        "stock_quantity": 102, "category": "Comprimidos", "updated_at": "..." }
    ],
    "clients": [
      { "id": 1, "name": "Consumidor Final", "nif": "999999999",
        "email": null, "phone": null, "type": "pessoa_fisica",
        "tax_regime": "geral", "is_iva_subject": false, "updated_at": "..." }
    ],
    "series":   [ { "id": 3, "document_type": "pos", "series_code": "A", "prefix": "FR", "next_number": 42 } ],
    "tax_rates":[ { "rate": 0, "label": "Isento (0%)" }, { "rate": 14, "label": "IVA Normal (14%)" } ]
  },
  "meta": { "incremental": true, "since": "...", "counts": { "products": 1, "clients": 1 } }
}
```

### 3.3 `GET /diagnose`
Contagens cruas por tabela (debug).

### 3.4 `POST /clients`  (criar cliente offline → servidor)
Idempotente por `nif` (devolve o existente se já houver no tenant).
```json
// request
{ "local_uuid": "c_...", "name": "João", "type": "pessoa_fisica",
  "nif": "5417...", "email": null, "phone": "9xx", "mobile": null,
  "address": null, "city": null, "province": null, "country": "Angola",
  "tax_regime": "geral", "is_iva_subject": false }
// 201
{ "id": 88, "local_uuid": "c_...", "name": "João", "nif": "5417...", "created": true }
// (ou) { "id": 88, "duplicated": true }
```
> `type` ENUM **`pessoa_fisica` | `pessoa_juridica`** (mapear "singular/empresa" → estes).
> `tax_regime` default **`geral`**. NIF é **único por tenant** (não global).

### 3.5 `POST /drafts`  (rascunho FT/FR/NC/proforma)
```json
// request
{ "local_uuid": "d_...", "doc_type": "FT",  // FT|FR|NC|proforma
  "client_id": 88, "client_local_uuid": null,
  "invoice_date": "2026-06-01", "due_date": "2026-07-01",
  "reference": "FT 2025/123",  // para NC
  "notes": "...",
  "items": [ { "product_id": 15, "product_name": "PARACETAMOL", "quantity": 2,
               "unit_price": 500, "tax_rate": 14, "discount_percent": 0 } ] }
// 201
{ "id": 120, "doc_type": "FT", "local_uuid": "d_...",
  "invoice_number": null, "total": 1140.0, "status": "draft",
  "message": "Rascunho criado. Finalize no módulo de Faturação para obter número AGT." }
```
> Rascunhos ficam `status='draft'`, `invoice_status='N'` — **sem validade fiscal** até finalização no ERP.

### 3.6 `POST /pos/sale`  (venda POS → Fatura-Recibo fiscal real)
Idempotente por `local_uuid`. Cria a FR com hash SAFT-AO, transação de tesouraria, turno e stock.
```json
// request
{ "local_uuid": "pos_...",
  "client_id": null, "client_local_uuid": null,  // null → Consumidor Final (NIF 999999999)
  "payment_method": "CASH",          // código do método (ver §7) — case-insensitive no servidor
  "amount_received": 1140.0,
  "discount_commercial": 0,
  "notes": "POS Offline · Pagamento: Dinheiro",
  "created_at_local": "2026-06-01T12:00:00Z",
  "items": [ { "product_id": 15, "product_name": "PARACETAMOL", "quantity": 2,
               "unit_price": 500, "tax_rate": 14, "is_service": false, "unit": "UN" } ] }
// 201
{ "success": true, "id": 543, "local_uuid": "pos_...",
  "invoice_number": "FR A/2026/42", "total": 1140.0,
  "atcud": "...", "qr_image": "data:image/png;base64,...",
  "hash_short": "AB12", "hash_control": "1" }
// 500 → { "success": false, "error": "<mensagem>" }
```

Validação relevante (HTTP 422): `items` obrigatório (mín. 1), `quantity > 0`, `unit_price >= 0`,
`tax_rate` 0–100, `payment_method` ≤ 30 chars.

---

## 4. Base de dados local (Flutter)

Recomendado **Isar** (rápido, sem SQL) ou **Drift/sqflite**. Tabelas (espelham o IndexedDB da PWA):

| Store            | Campos-chave |
|------------------|--------------|
| `products`       | id, name, sku, barcode, type, price, cost, tax_rate, stock_quantity, category, updated_at |
| `clients`        | id (ou local id), local_uuid, name, nif, type, …, synced (bool) |
| `series`         | id, document_type, series_code, prefix, next_number |
| `tax_rates`      | rate, label |
| `pos_sales`      | local_uuid (PK), provisional_number, items(json), totais, payment_method, synced, server_id, server_number, atcud, qr, hash |
| `draft_documents`| local_uuid (PK), doc_type, client_id/local_uuid, items(json), totais, synced, server_id, server_number |
| `sync_queue`     | id, op (`create_client`|`create_draft`|`create_pos_sale`), payload(json), status (`pending`|`done`|`failed`), retries, last_error, created_at |
| `meta`           | key/value: `last_sync`, `catalog_version`, `company`, `shift`, `tenant_id`, `user` |

---

## 5. Motor de sincronização (algoritmo)

Replicar `pwa-invoicing.js`:

1. **Pull catálogo**: `GET /sync?since=last_sync`. Guardar `products/clients/series/tax_rates`
   (upsert), `company`, `shift` em `meta`. Atualizar `last_sync = server_time`.
2. **Push fila (FIFO por `created_at`)**: para cada job `pending` em `sync_queue`:
   - `create_client` → `POST /clients`; ao sucesso, atualizar o cliente local com o `id` do servidor e `synced=1`.
   - `create_draft` / `create_pos_sale` → resolver `client_local_uuid → client_id` (se o cliente
     ainda não sincronizou, **reagendar** o job); POST; ao sucesso, gravar `server_id/server_number/atcud/qr/hash` e `synced=1`.
   - Erro → `retries++`, guardar `last_error`; após **5 tentativas** → `status='failed'`.
3. **Sync forçada / arranque**: repor jobs `failed` → `pending` (uma vez por arranque) e voltar a tentar.
4. **Cache do catálogo**: manter um `CATALOG_VERSION`; se mudar o formato do payload, limpar
   `products` + `last_sync` para forçar pull completo.
5. **Conectividade**: usar `connectivity_plus`; ao voltar online, disparar sync. Confirmar online
   real via `/ping` (Wi-Fi sem internet engana).

Eventos para a UI (equivalente aos `pwa:synced` / `pwa:pos-sale-synced`): usar streams/notifiers
para a UI recarregar o catálogo e substituir o nº provisório pelo nº AGT após sync.

---

## 6. Fluxo POS (Fatura-Recibo)

1. Selecionar produtos → carrinho (totais por linha com IVA; desconto comercial opcional).
2. Cliente: **Consumidor Final por omissão** (NIF 999999999); permitir criar cliente rápido (§3.4).
3. **Verificar turno**: ler `meta.shift`. Se `open=false`, avisar ("Abra um turno") — abrir/fechar
   turno é feito no ERP web (não há abertura offline). Permitir venda com confirmação.
4. Finalizar → gravar `pos_sales` com **número provisório** (`PEND-YYYYMMDD-XXXXXX`), enfileirar
   `create_pos_sale`, **imprimir já** (ticket offline).
5. Ao sincronizar → substituir nº provisório pelo **nº AGT** + QR + hash (UI atualiza).
6. **Antes de fechar turno**: sincronização **obrigatória** de todos os pendentes (bloquear se offline com pendentes).

Troco (dinheiro): `amount_received - total`.

---

## 7. Métodos de pagamento (vêm da Tesouraria)

Os métodos vêm do módulo **Tesouraria** (`treasury_payment_methods`). Conjunto padrão Angola
(códigos): `CASH`, `MCX` (Multicaixa Express), `TPA`, `TRANSFER`, `CHECK`, `DEBIT`, `MBWAY`.
- O servidor faz o match do código **case-insensitive** e deriva a categoria pelo **tipo**
  (`cash`/`card`/`bank_transfer`/`digital_wallet`).
- Idealmente a app deve **listar os métodos reais do tenant** (expor no `/sync` — ver §11),
  em vez de hardcodear. Enquanto isso, usar a lista padrão acima.

---

## 8. Regras fiscais (Angola / SAFT-AO)

- **Impostos (IVA)**: 14% (normal, padrão), 7%, 5% (reduzidas), 0% (exportação), Isento, Não Sujeito; IRT 6,5%.
  saft_type: `NOR`/`RED`/`ISE`/`NS`/`OUT`.
- **Cliente**: `type` ∈ {`pessoa_fisica`, `pessoa_juridica`}; `tax_regime` default `geral`; NIF **único por tenant**.
- **Documentos**: `invoice_type` (2 chars) `FT`/`FR`/`FS`/`NC`/`ND`; `invoice_status` ENUM `N`/`A`/`F`.
- **FR (Fatura-Recibo)**: o POS gera FR; série `document_type='pos'`.
- **AGT**: certificado/Product ID/Versão SAFT são **globais** (config do dono do sistema); o QR e o
  hash são gerados no servidor — a app só os exibe/imprime.

---

## 9. Pacotes Flutter recomendados

```yaml
dependencies:
  dio: ^5            # HTTP
  isar: ^3           # DB local (ou drift/sqflite)
  isar_flutter_libs: ^3
  flutter_riverpod: ^2   # estado (ou flutter_bloc)
  connectivity_plus: ^6  # online/offline
  flutter_secure_storage: ^9  # token
  uuid: ^4           # local_uuid
  intl: ^0            # datas/moeda pt-AO
  esc_pos_bluetooth / blue_thermal_printer  # impressão de talão (opcional)
  qr_flutter: ^4     # render QR se necessário
```

Estrutura sugerida:
```
lib/
  core/        (dio_client, auth, connectivity)
  data/
    db/        (isar collections: Product, Client, PosSale, Draft, SyncJob, Meta)
    api/       (invoicing_api.dart — ping/sync/clients/drafts/pos)
    sync/      (sync_engine.dart)
  features/
    pos/  catalog/  clients/  drafts/  shift/
  shared/      (money, validators, widgets)
```

Exemplo (Dio + sync):
```dart
final dio = Dio(BaseOptions(baseUrl: 'https://soserp.vip/api/v1/invoicing'));
dio.options.headers['Authorization'] = 'Bearer $token';

Future<void> pull() async {
  final last = await meta.get('last_sync');
  final r = await dio.get('/sync', queryParameters: last == null ? {} : {'since': last});
  await db.upsertProducts(r.data['data']['products']);
  await db.upsertClients(r.data['data']['clients']);
  await meta.set('last_sync', r.data['server_time']);
  await meta.set('shift', r.data['shift']);
}

Future<void> pushPosSale(PosSale s) async {
  try {
    final r = await dio.post('/pos/sale', data: s.toPayload());
    if (r.data['success'] == true) {
      await db.markSynced(s.localUuid, r.data); // grava invoice_number, atcud, qr...
    }
  } on DioException catch (e) {
    await db.bumpRetry(s.localUuid, e.message);
  }
}
```

---

## 10. Checklist de funcionalidades (paridade com a PWA)

- [ ] Login (Sanctum) + escolha/identificação do tenant.
- [ ] Sync catálogo (incremental + completo + versão).
- [ ] Catálogo: pesquisa, scan código de barras, categorias, scroll infinito.
- [ ] Clientes: lista, pesquisa, criar offline (Consumidor Final por defeito).
- [ ] POS: carrinho, IVA por linha, desconto, troco, pagamento, finalizar offline, imprimir talão.
- [ ] Estado do turno + bloqueio de fecho com pendentes.
- [ ] Rascunhos FT/FR/NC/Proforma.
- [ ] Fila de sync com retry, FIFO, recuperação de falhados.
- [ ] Indicador online/offline + nº de pendentes.
- [ ] Substituição nº provisório → nº AGT + QR.

---

## 11. Pendências no backend (para suportar a app nativa)

1. **Auth por token (Sanctum)**: criar `POST /api/v1/auth/login` e proteger as rotas
   `api/v1/invoicing/*` com `auth:sanctum` (além de `web`), resolvendo o tenant do token.
2. **Métodos de pagamento no `/sync`**: incluir `data.payment_methods` (id, name, code, type, is_active)
   para a app não hardcodear.
3. **Resolução de tenant sem sessão**: garantir `activeTenantId()` a partir do token/header `X-Tenant-Id`
   (hoje depende da sessão web).
4. (Opcional) Endpoint para **abrir/fechar turno** via API, se quiser POS 100% mobile.

> Gotchas confirmados (já corrigidos no web, manter na app): NIF **único por tenant**;
> `client.type` = `pessoa_fisica`/`pessoa_juridica`; método de pagamento **case-insensitive**;
> usar sempre o **tenant ativo** (não o tenant default do utilizador).
