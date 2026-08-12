# POS Offline — Especificação Técnica para Reimplementação

> Documento destinado a ser usado por outra IA / equipa para **reproduzir** o
> sistema POS Offline existente noutro stack (mobile nativo, outra framework
> web, etc.).
>
> Sistema de referência: **Laravel 11 + Alpine.js + Service Worker + IndexedDB**,
> conectado a uma BD ERP **ICG Manager** (SQL Server) com tabelas legacy
> (`ARTICULOS`, `PEDVENTACAB`, `CLIENTES`, `STOCKS`, etc.).
>
> Ficheiros canónicos:
> - `public/sw-pos-offline.js` — Service Worker (cache/sync)
> - `resources/views/pos/offline.blade.php` — UI (Alpine.js + Tailwind)
> - `public/js/pos-offline.js` — lógica cliente (IndexedDB + state + sync)
> - `app/Http/Controllers/Api/PosOfflineController.php` — API REST
> - `routes/api.php` — rotas `/api/pos/*`

---

## 1. Objetivo do Sistema

Permitir a um **vendedor externo** (com PDA ou tablet, sem 4G fiável) consultar
catálogo, criar pedidos e gerar pré-faturas em **qualquer rede ou sem rede**,
sincronizando depois com o ERP quando voltar a haver ligação.

### Garantias funcionais

1. **Arranque < 2s** mesmo com 5 000+ produtos (carregamento da BD local, não da rede).
2. **Funcionamento 100% offline** após primeira sincronização.
3. **Sem perda de vendas** se a rede cair a meio (fila persistente + retry).
4. **Sem duplicados** se o utilizador clicar duas vezes ou ficar offline a meio.
5. **Isolamento por vendedor**: trocar de login limpa toda a BD local.
6. **Atualização periódica** silenciosa em background a cada 3 horas.

---

## 2. Arquitetura em Camadas

```
┌─────────────────────────────────────────────────────────┐
│  UI (Alpine.js / qualquer framework reativa)            │
│  - Componentes: Catalog, Cart, ClientPicker, Modals     │
│  - State global em "store" (Alpine.store('pos'))        │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│  Domain / Service Layer (em JS)                         │
│  - PosDB      → wrapper IndexedDB (CRUD por store)      │
│  - PosSync    → sincronização com servidor (REST)       │
│  - ActivityLogger → telemetria (offline-tolerant)       │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│  Persistência local (browser)                           │
│  - IndexedDB (dados estruturados, ~10–50 MB)            │
│  - localStorage (estado leve: carrinho, config, prefs)  │
│  - Cache API via Service Worker (imagens, JS, HTML)     │
└──────────────────────┬──────────────────────────────────┘
                       │  rede (quando disponível)
┌──────────────────────▼──────────────────────────────────┐
│  Backend Laravel — /api/pos/*                           │
│  - PosOfflineController  → produtos, clientes, vendas   │
│  - PedidoVentaService    → cria registos no ICG         │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│  ICG Manager (SQL Server) — BD legacy ERP                │
│  ARTICULOS, ARTICULOSLIN, PRECIOSVENTA, STOCKS,         │
│  CLIENTES, TARIFASCLIENTE, PEDVENTACAB, PEDVENTALIN     │
└─────────────────────────────────────────────────────────┘
```

---

## 3. Armazenamento Local

### 3.1 IndexedDB

- **Nome:** `pos-offline-db`
- **Versão:** `3` (incrementar a cada migração de schema)
- **Object stores:**

| Store | Key | Indexes | Propósito |
|---|---|---|---|
| `produtos` | `CODARTICULO` | `departamento` (DPTO), `marca` (MARCA), `nome` (DESCRIPCION) | Catálogo completo |
| `vendas_pendentes` | `id` autoincrement | — | Fila de envio offline |
| `config` | `key` | — | Pares chave/valor (lastSync, novidades cache, etc.) |
| `departamentos` | `valor` | — | Lista para filtros |
| `marcas` | `valor` | — | Lista para filtros |
| `clientes` | `CODCLIENTE` | — | Carteira do vendedor |
| `stocks` | `CODARTICULO` | — | Stock por artigo (admin) |
| `historico_pedidos` | `id` autoincrement | `data`, `numero` | Últimos 100 pedidos |

**Padrão de transação (ler/escrever em lote):**

```js
async putMany(storeName, items) {
  if (!this.db) await this.init();
  const tx = this.db.transaction(storeName, 'readwrite');
  const store = tx.objectStore(storeName);
  for (const item of items) store.put(item);
  return new Promise((resolve, reject) => {
    tx.oncomplete = () => resolve(items.length);
    tx.onerror = () => reject(tx.error);
  });
}
```

> Notas para reimplementação:
> - Numa plataforma nativa usar **SQLite** com as mesmas tabelas (igual ao que
>   o Flutter app deste projeto já faz). Manter a *mesma* chave primária.
> - As escritas devem ser **idempotentes** (`put`, não `add`).
> - Sempre limpar o store inteiro antes de re-inserir o catálogo completo,
>   para garantir que produtos removidos do ERP desaparecem do app.

### 3.2 localStorage (estado leve)

| Chave | Conteúdo | Quando se limpa |
|---|---|---|
| `pos_carrinho` | Array do carrinho atual | `finalizarVenda` ok / `limparCarrinho` |
| `pos_cliente_selecionado` | Cliente do carrinho actual | Idem |
| `pos_endereco_selecionado` | Endereço de envio | Idem |
| `pos_endereco_default_{CODCLIENTE}` | Última morada usada por cliente | Logout / wipe |
| `pos-config-{vendedorId}` | JSON com preferências do vendedor | Nunca (manual) |
| `pos_ultimo_vendedor` | `vendor_code` da última sessão | Logout completo |
| `pos-ultima-sync-periodica-{vendedorId}` | Timestamp ms da última sync 3h | Wipe |

> O carrinho **nunca** vai para IndexedDB enquanto está aberto — fica em
> localStorage para escrita síncrona muito rápida em cada `add/remove`.

### 3.3 Cache API (via Service Worker)

- `pos-static-v{N}` — JS/CSS/manifest/icons (estratégia **Network First**)
- `pos-images-v{N}` — imagens de produtos `/storage/...` (estratégia **Cache First**)
- `pos-offline-v{N}` — HTML da página `/pos-offline` (Network First com fallback)

**O `{N}` deve ser bumped em cada deploy** que mude assets, para forçar o SW
a ativar e o cliente a recarregar.

---

## 4. Service Worker

Padrão de **scope restrito**: este SW **só intercepta** rotas do POS. Nunca
toca em rotas do resto da aplicação (`/dashboard`, `/articulos`, etc.).

```js
const POS_ROUTES = ['/pos-offline', '/js/pos-offline.js'];

function isPosRequest(url) {
  return POS_ROUTES.some(r => url.pathname.startsWith(r))
      || url.pathname.startsWith('/storage/')
      || url.pathname.endsWith('.css')
      || url.pathname.endsWith('.png')
      || url.pathname.endsWith('.svg')
      || url.pathname.endsWith('.ico')
      || (url.pathname.startsWith('/api/') && url.pathname.includes('produtos-offline'));
}

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (event.request.method !== 'GET') return;
  if (!url.protocol.startsWith('http')) return;
  if (!isPosRequest(url)) return;   // ← ignora tudo o que não é POS
  // ...estratégias por tipo
});
```

### 4.1 Estratégias por tipo de recurso

| Tipo | URL pattern | Estratégia |
|---|---|---|
| Imagens produtos | `/storage/*` | **Cache First** + fetch fallback |
| Ficheiros JS | `*.js` | **Network First** (para apanhar updates) → cache fallback |
| Outros estáticos | manifest, icons, css | **Cache First** |
| HTML | `Accept: text/html` | **Network First** → cache fallback |
| API | `/api/*` | **Não intercepta** (excepto produtos-offline) |

### 4.2 Ativação — limpeza segura de caches antigos

> **NUNCA** apagar TODOS os caches. Apagar **apenas os que começam com `pos-`**
> (outras features da app podem ter SWs próprios).

```js
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(k => k.startsWith('pos-') && ![CACHE_NAME, STATIC_CACHE, IMAGE_CACHE].includes(k))
          .map(k => caches.delete(k))
    )).then(() => self.clients.claim())
  );
});
```

### 4.3 Background Sync e Periodic Sync

```js
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-vendas-pendentes') {
    event.waitUntil(syncVendasPendentes());
  }
});

self.addEventListener('periodicsync', (event) => {
  if (event.tag === 'atualizar-dados-pos') {
    event.waitUntil(atualizarDadosPOS());
  }
});
```

O SW não tem acesso a IndexedDB do lado do cliente Alpine sem reabrir o handle,
por isso **delega**: faz `postMessage` para todos os `clients` e o store no UI
trata da sync. Isto garante uma única lógica de sync.

```js
async function syncVendasPendentes() {
  const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
  for (const client of clients) {
    client.postMessage({ action: 'syncVendasPendentes', source: 'background-sync' });
  }
}
```

---

## 5. Estado do Cliente (Alpine Store)

Toda a UI lê e escreve numa única `store`. Reimplementação noutra framework
(React/Vue/Svelte) pode usar Zustand/Pinia/store equivalente. **Não usar
contexto local por componente** — a coerência de carrinho + cliente + descontos
exige estado global.

### 5.1 Forma do estado

```ts
type PosStore = {
  // Conectividade
  online: boolean;
  syncing: boolean;
  syncProgress: number;     // 0..100
  syncMessage: string;
  lastSync: Date | null;
  syncPeriodicoIntervaloMs: number;  // 3h
  proximoSyncMs: number | null;
  ultimoSyncPeriodicoMs: number | null;

  // Catálogo
  produtos: Produto[];
  produtosFiltrados: Produto[];      // derivado de filtros
  departamentos: { valor: number; nome: string; total: number }[];
  marcas: { valor: number; nome: string; total: number }[];
  clientes: Cliente[];

  // Filtros activos
  pesquisa: string;
  departamento: number | '';
  marca: number | '';
  ordenacao: 'nome_asc' | 'nome_desc' | 'preco_asc' | 'preco_desc' | 'stock_desc';
  apenasComStock: boolean;
  paginaAtual: number;
  porPagina: number;

  // Carrinho
  carrinho: ItemCarrinho[];
  clienteSelecionado: Cliente | null;
  enderecoSelecionado: Endereco | null;
  descontoCliente: number;   // % aplicada
  modalCliente: boolean;
  modalEndereco: boolean;
  pedidoEmEdicao: Pedido | null;   // se !== null, finalizar chama /atualizar-pedido

  // Modal adicionar produto
  modalAddProduto: boolean;
  produtoParaAdicionar: Produto | null;
  qtdCaixas: number;
  qtdUnidades: number;

  // Operações em curso
  vendasPendentes: number;   // count(vendas_pendentes)
  finalizando: boolean;       // guard de duplo-clique

  // Vendedor
  vendedorId: string;
  isAdmin: boolean;

  // Modais auxiliares
  modalHistorico, modalProdutosFrequentes, modalNovidades, modalFatura,
  modalConfig, modalShortcuts: boolean;

  config: {
    abrirModalQuantidade: boolean;
    somAdicionar: boolean;
    confirmarLimpar: boolean;
    mostrarPrecoComIva: boolean;
    mostrarStock: boolean;       // só admin
    mostrarDescatalogados: boolean;
  };
};
```

### 5.2 Métodos públicos do store

```
async init()                       // bootstrap completo
carregarConfig() / salvarConfig()  // localStorage
async carregarDadosLocais()        // IndexedDB → state
async sincronizar(comImagens)      // full sync
async syncVendasPendentes()        // só fila
async sincronizarStock()           // só stock (admin)
async atualizarStockBackground()   // silencioso
iniciarSyncPeriodico()             // setInterval 3h
executarSyncPeriodico()
filtrarProdutos(comDebounce?)
adicionarAoCarrinho(produto)
abrirModalQuantidade(produto)
adicionarComQuantidade(produto, caixas, unidades)
removerDoCarrinho(codarticulo)
atualizarQuantidade(codarticulo, quantidade)
atualizarUnidades(codarticulo, unidades)
atualizarPrecoUnitario(codarticulo, novoPreco)
limparCarrinho()
selecionarCliente(cliente)
selecionarEndereco(endereco)
async finalizarVenda()             // cria ou edita
async abrirHistorico()
async wipeDadosVendedor()          // troca de utilizador / logout
```

---

## 6. Fluxos Críticos

### 6.1 Bootstrap (`init`)

```
1. Ler vendedorId + isAdmin do DOM (data-attributes)
2. Comparar com pos_ultimo_vendedor em localStorage
   - Se diferente → wipeDadosVendedor() (IndexedDB + localStorage + Caches)
3. localStorage.setItem('pos_ultimo_vendedor', vendedorId)
4. carregarConfig()                          (síncrono, localStorage)
5. PosDB.init()                              (abrir IndexedDB)
6. carregarDadosLocais()                     (produtos/clientes/etc para state)
7. carregarCarrinho()                        (localStorage)
8. vendasPendentes = count('vendas_pendentes')
9. lastSync = PosSync.getLastSync()
10. Se online + admin → atualizarStockBackground() (delay 5s)
11. iniciarSyncPeriodico()                   (setInterval 3h)
```

Ouvir eventos `online` / `offline` no `window` para flipar o badge e disparar
`syncVendasPendentes` ao voltar online.

### 6.2 Adicionar ao carrinho

Sempre que se adiciona, aplicar **na hora** o desconto do cliente seleccionado
(armazenado em `descontoCliente` ou `clienteSelecionado.desconto`):

```
preco_caixa_sem_iva = preco_unitario_sem_iva * unidades_caixa
valor_iva           = preco_caixa_sem_iva * (% iva / 100)
preco_com_iva       = preco_caixa_sem_iva + valor_iva
desconto_aplicar    = descontoCliente || clienteSelecionado?.desconto || 0
valor_desconto      = preco_com_iva * (desconto_aplicar / 100)
preco_final         = preco_com_iva - valor_desconto
```

O item do carrinho guarda **todas as componentes** (preço sem IVA, IVA,
desconto, etc.) para permitir edição inline e recálculo sem voltar ao servidor.

### 6.3 Finalizar venda (online + fallback offline)

```
1. Validar carrinho não vazio + cliente selecionado
2. Guard finalizando (impede double-click)
3. Clonar deep (JSON.parse(JSON.stringify(...))) para fugir ao Proxy do Alpine
4. Construir payload:
   {
     cliente: { ...cliente, desconto: descontoCliente },
     itens: [...],        // todos os campos calculados
     subtotal, desconto, total,
     data: ISO8601,
     codenvio, endereco_envio,
     pedido_original?  // se for edição
   }
5. Se online:
   - POST /api/pos/finalizar-venda  (ou /atualizar-pedido se edição)
   - Em sucesso: abrirFaturaModal, salvarNoHistorico, limparCarrinho, return
   - Em erro: cair para o passo offline
6. Offline path:
   - Gerar fingerprint = `{CODCLIENTE}_{itens ordenados}_{total}`
   - Procurar venda igual nas vendas_pendentes existentes nos últimos 60s
     → se encontrar, NÃO gravar (deduplicação)
   - PosDB.put('vendas_pendentes', venda)
   - Atualizar vendasPendentes count
   - Salvar no histórico local como "Pendente"
   - Limpar carrinho
7. Sempre: finally { finalizando = false }
```

### 6.4 Sync de vendas pendentes

```
Disparado por:
- Evento window 'online'
- Botão manual "Sincronizar"
- Background Sync do SW
- Periodic Sync (3h)

Para cada venda na fila:
  POST /api/pos/finalizar-venda
  Se 200 + success:
    PosDB.delete('vendas_pendentes', venda.id)
    Coleccionar fatura_url para mostrar à última
  Senão:
    Manter na fila + acumular erros

Retorna { synced, total, errors, faturas }
```

### 6.5 Sync periódica (3h)

```
chave_ts = `pos-ultima-sync-periodica-${vendedorId}`
ultima   = localStorage[chave_ts]

Se nunca || (agora - ultima >= 3h):
  setTimeout(executar, 5000)         // delay para não disputar boot
setInterval(executar, 3h)

executar():
  GET /api/pos/clientes-sync         // leve, só clientes
  Se admin: GET /api/articles/stock/bulk?warehouse=G1
  localStorage[chave_ts] = Date.now()
```

### 6.6 Troca de utilizador (wipe)

Crítico para impedir que o vendedor B veja clientes/vendas do vendedor A se
fizer login na mesma máquina:

```js
async wipeDadosVendedor() {
  // 1. Limpar todas as object stores
  for (const store of POS_STORES) await PosDB.clear(store);

  // 2. Limpar todas as chaves localStorage que começam com 'pos_' ou 'pos-'
  Object.keys(localStorage)
    .filter(k => k.startsWith('pos_') || k.startsWith('pos-'))
    .forEach(k => localStorage.removeItem(k));

  // 3. Apagar caches do SW (só os pos-*)
  const keys = await caches.keys();
  await Promise.all(keys.filter(k => k.startsWith('pos-')).map(k => caches.delete(k)));
}
```

---

## 7. API REST (servidor)

Todos os endpoints sob `/api/pos/*` com middleware **session-based** (cookie
de vendedor). Conteúdo: JSON. Erros sempre retornam `{success: false, error: '...'}` com HTTP 4xx/5xx.

### 7.1 Catálogo

```
GET /api/pos/produtos-offline
Auth: sessão de vendedor (ou ?vendor_code=XX para mobile nativo)
Resposta:
{
  produtos: [{
    CODARTICULO, DESCRIPCION, DPTO, MARCA,
    unidades_caixa, preco, preco_sem_iva, preco_unitario,
    percentual_iva, stock, descatalogado,
    imagem, has_image,
    codigos_barras: [...]      // de ARTICULOSLIN (CODBARRAS/CODBARRAS2/CODBARRAS3, distinct)
  }],
  departamentos: [{ valor, nome, total }],
  marcas:        [{ valor, nome, total }],
  clientes:      [{ CODCLIENTE, NOMBRECLIENTE, ALIAS, DIRECCION1, desconto, ... }]
}
```

Regra: `JOIN PRECIOSVENTA (IDTARIFAV=1)` + `JOIN STOCKS (CODALMACEN='G1')` para
trazer só o que tem preço. Os descatalogados vêm na resposta — o frontend
decide se mostra ou não (`config.mostrarDescatalogados`).

```
GET /api/pos/clientes-sync
Resposta leve (só clientes + descontos) para sync periódica de 3h.

GET /api/pos/historico-pedidos?offset=0&limit=50&q=...
Resposta paginada com últimos 100 pedidos do vendedor.

GET /api/articles/stock/bulk?warehouse=G1
Resposta {data: {CODARTICULO: {STOCK, STOCKMINIMO, AVAILABLE}}, count, synced_at}

GET /api/articles/client/{cliente}/top-products?warehouse=G1
GET /api/articles/new-products?warehouse=G1
```

### 7.2 Criar/atualizar venda

```
POST /api/pos/finalizar-venda
Body:
{
  cliente: {CODCLIENTE, desconto, ...},
  itens: [{
    CODARTICULO, quantidade, total_unidades, unidades_caixa,
    preco, preco_unitario, preco_sem_iva,
    percentual_iva, valor_iva, valor_desconto, desconto_item,
    preco_editado: bool
  }],
  subtotal, desconto, total,
  data, codenvio, endereco_envio,
  source?: 'flutter' | undefined   // determina série (GVAFT vs GVFX)
}
Resposta sucesso:
{ success: true, pedido: 'GVFX-12345', serie: 'GVFX', total, fatura_url }
```

```
POST /api/pos/atualizar-pedido
Idêntico mas com body.pedido_original = {numero, serie, ...}
```

### 7.3 Backend — fluxo de criação

O controller chama `PedidoVentaService::createFromCart($itens, $orderData)`
que:

1. Reserva próximo `NUMPEDIDO` para a `NUMSERIE` apropriada (`GVFX`, `GVAFT`, `GWB`).
2. Cria `PEDVENTACAB` (cabeçalho) + linhas `PEDVENTALIN`.
3. Aplica desconto, IVA por linha, totais.
4. Se `codenvio` foi fornecido → `UPDATE PEDVENTACAB SET CODENVIO`.
5. Devolve `supedido` (`{serie}-{numero}`) para gerar URL de preview/PDF.

---

## 8. UI — Componentes e Padrões

A page é uma SPA simples num único Blade + Alpine. Equivalente em React/Vue:

### 8.1 Layout principal

```
┌─ Header ────────────────────────────────────────────────┐
│ Logo │ Status Online │ Auto-sync 2h 30m │ Histórico │   │
│      │ Pedidos │ Pendentes(N) │ Sync ▼ │ Config │ Help  │
├─ Filtros ───────────────────────────────────────────────┤
│ [F6 Pesquisa] [Departamento ▼] [Marca ▼] [Ordenar ▼]    │
│ ☑ Apenas com stock  ☐ Descatalogados                    │
├─ Grid ──────────────────────┬─ Carrinho ────────────────┤
│ Cards de produto            │ Cliente: [____] (F4)       │
│ (imagem, nome, preço,       │ Itens                      │
│  stock se admin, +caixa)    │ Total / Sub / Desc         │
│                             │ [Finalizar]                │
└─────────────────────────────┴────────────────────────────┘
```

### 8.2 Atalhos de teclado (importante para PDA + tablet com teclado)

| Tecla | Ação |
|---|---|
| `F6` | Foco no campo de pesquisa |
| `F4` | Abrir modal de cliente |
| `F2` | Abrir modal de finalizar venda |
| `Enter` no scan | Adicionar produto (via código de barras) |
| `Esc` | Fechar modal aberto |
| Setas | Navegar entre cards do grid (com `keyboard-selected` outline) |
| `+` `-` | Ajustar quantidade do item selecionado |

Ver `public/js/pos-keyboard-navigation.js` para a implementação de referência.

### 8.3 Scanner integrado

O input de pesquisa **também aceita códigos de barras**. Quando o termo digitado
tem >= 8 chars numéricos e o utilizador faz Enter → procurar match em
`produto.codigos_barras` e se houver um único → adicionar 1 caixa directamente.

Importante: aceitar **GS1-128** (códigos longos com `(01)XXXXXXXXXXXXXX(20)..(10)..`).
Ver `_extrairCodigosGS1()` no app Flutter deste projeto — extrai o GTIN-14 e
EAN-13 do AI `(01)` e procura por cada um.

### 8.4 Toasts (feedback não-bloqueante)

Container fixo bottom-right. 4 variantes: `success` (verde), `error` (vermelho),
`info` (azul), `warning` (amarelo). Duração default 2s. Implementação trivial
via `position: fixed` + `appendChild` + `setTimeout(remove)`.

---

## 9. Telemetria (Activity Logger)

Todos os eventos relevantes são enviados para `/api/pos/log-activity` em
fire-and-forget (não bloqueia UI). Os logs persistem no servidor (BD ICG ou
SQLite externa) para auditoria.

Eventos canónicos:

```
pos_init                  → bootstrap
pos_init_complete         → após carregar tudo
pos_online / pos_offline  → mudança de conectividade
pos_sync_complete         → sync OK (com contagens e duração)
pos_add_to_cart           → produto adicionado
pos_sale_completed        → venda online OK
pos_sale_updated          → pedido editado OK
pos_sale_offline          → venda guardada na fila
pos_sale_error / pos_sale_exception
```

Cada log leva: `action`, `description`, `properties` (objeto JSON com contexto).

---

## 10. Padrões a Replicar / Pitfalls

### Idempotência
- **Vendas**: fingerprint `cliente+itens+total` + janela 60s impede duplicados
  se o utilizador clica 2× ou se há retry de SW.
- **Histórico**: pedidos com mesmo `numero` actualizam (não duplicam).
- **Catálogo**: cada sync **limpa e re-insere** — produtos removidos no ERP
  desaparecem do app.

### Performance
- IndexedDB `putMany` em transação única (não `put` em loop com promises).
- Filtros com **debounce 150ms** quando vem de teclado.
- Cache de `Intl.NumberFormat` (instanciar uma vez no init do store).
- Lista de produtos com **virtualização** se > 500 itens visíveis (não está
  na versão Alpine actual, mas é recomendação para React/Vue).
- Imagens **lazy-load** + Cache First no SW.

### Segurança
- Cookie de sessão com `SameSite=Lax`, `Secure`, `HttpOnly`.
- CSRF token em todas as POST (`X-CSRF-TOKEN` header).
- Validar **server-side** o `vendor_code` em todas as queries — nunca confiar
  no body do request para filtrar carteira de clientes.
- `wipeDadosVendedor()` ao detectar troca de utilizador.

### UX em rede instável
- Não desactivar o botão "Finalizar" no offline — guardar local e continuar.
- Mostrar **número de vendas pendentes** sempre visível no header.
- Botão "Sync agora" em destaque + contagem decrescente do próximo sync auto.
- Snackbar/toast a confirmar **cada** sincronização (mesmo silenciosa).

### Versionamento
- Bumpar `POS_DB_VERSION` em cada mudança de schema IndexedDB e tratar
  `onupgradeneeded`.
- Bumpar `CACHE_NAME` (`pos-static-vN`) em cada deploy que mude JS/CSS.
- Manter `posVersion` exposto no header (canto do logo) para debug remoto.

---

## 11. Checklist de Re-implementação

Para outra IA / equipa, validar:

- [ ] BD local com pelo menos 6 stores (produtos, vendas_pendentes, config,
      departamentos, marcas, clientes) e migração versionada.
- [ ] Single global store reactivo (Alpine/Zustand/Pinia/Riverpod).
- [ ] Eventos `online`/`offline` ligados a triggers de sync.
- [ ] Fila persistente de vendas + retry automático.
- [ ] Deduplicação por fingerprint nos últimos 60s.
- [ ] Guard `finalizando` contra double-click.
- [ ] Sync periódico 3h com timestamp em storage local.
- [ ] Service Worker (web) ou WorkManager/BackgroundFetch (mobile) com
      scope restrito.
- [ ] Cache de imagens com TTL longo + invalidação por URL.
- [ ] Wipe completo na troca de utilizador.
- [ ] Toasts para feedback de cada operação.
- [ ] Telemetria fire-and-forget.
- [ ] Suporte GS1-128 no input de scanner.
- [ ] Atalhos de teclado para PDA.
- [ ] CSRF + cookie sessão validados no servidor.
- [ ] Bump de versão (DB + cache) em cada deploy com schema/JS novo.

---

## 12. Modelo de dados de referência

### Produto (cliente)
```ts
type Produto = {
  CODARTICULO: string;        // PK
  DESCRIPCION: string;
  DPTO: number;
  MARCA: number;
  unidades_caixa: number;     // ARTICULOS.UNID2C
  preco: number;              // caixa com IVA
  preco_sem_iva: number;      // caixa sem IVA
  preco_unitario: number;     // por unidade sem IVA
  percentual_iva: number;     // ex 14
  stock: number;
  descatalogado: boolean;
  imagem: string | null;      // URL ou null
  has_image: boolean;
  codigos_barras: string[];   // distinct de ARTICULOSLIN
};
```

### Item do Carrinho
```ts
type ItemCarrinho = {
  CODARTICULO: string;
  DESCRIPCION: string;
  unidades_caixa: number;
  quantidade: number;            // em caixas (pode ser decimal)
  total_unidades: number;        // quantidade * unidades_caixa
  preco_unitario: number;        // sem IVA, edited?
  preco_sem_iva: number;         // caixa sem IVA
  percentual_iva: number;
  valor_iva: number;             // por caixa
  preco_original: number;        // antes de desconto
  valor_desconto: number;        // por caixa
  desconto_item: number;         // %
  preco: number;                 // FINAL com IVA e desconto (por caixa)
  total: number;                 // preco * quantidade
  imagem: string | null;
};
```

### Venda (payload finalizar)
```ts
type Venda = {
  cliente: Cliente & { desconto: number };
  itens: VendaItem[];
  subtotal: number;
  desconto: number;       // valor absoluto
  total: number;
  data: string;           // ISO8601
  codenvio: number | null;
  endereco_envio: Endereco | null;
  pedido_original?: { numero: string; serie: string; ... };  // só edição
  source?: 'flutter';     // determina série server-side
};
```

---

## 13. Diferenças entre cliente Web (PWA) e Mobile Nativo

| Aspecto | PWA (Alpine + SW) | Mobile Nativo (Flutter aqui) |
|---|---|---|
| BD local | IndexedDB | SQLite |
| Sync background | Service Worker + Periodic Sync | WorkManager (Android) / BGTaskScheduler (iOS) |
| Cache imagens | Cache API | `cached_network_image` + filesystem |
| Auth | Cookie de sessão | Token Bearer + cookie |
| Sync periódico | `setInterval` no main thread | `Timer.periodic` na app + WorkManager fora |
| Scope | `/pos-offline` route | App separada |
| Atualização | `CACHE_NAME` bump + reload | OTA via APK + `app-version.json` |

A lógica de domínio (idempotência, deduplicação, recálculo de preços com IVA
e desconto, gestão de fila) é **a mesma** em ambos.

---

## 14. Referências cruzadas no código

| Conceito | Ficheiro | Linhas |
|---|---|---|
| Bootstrap | `public/js/pos-offline.js` | `init()` ~576 |
| IndexedDB schema | idem | `PosDB.init` ~51 |
| Sync de vendas | idem | `PosSync.syncVendasPendentes` ~282 |
| Sync completa | idem | `sincronizar` ~1208 |
| Finalizar venda | idem | `finalizarVenda` ~2057 |
| Deduplicação | idem | ~2204 |
| Wipe | idem | `wipeDadosVendedor` ~1903 |
| SW estratégias | `public/sw-pos-offline.js` | ~78 |
| SW background sync | idem | ~196 |
| Endpoint catálogo | `app/Http/Controllers/Api/PosOfflineController.php` | `getProdutosOffline` ~33 |
| Endpoint finalizar | idem | `finalizarVenda` ~382 |

---

**Versão deste documento:** 1.0 (POS web v2.0.6, DB v3, SW v23)
