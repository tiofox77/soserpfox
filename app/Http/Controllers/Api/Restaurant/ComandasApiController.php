<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\OrderItem;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Treasury\PaymentMethod;
use App\Services\Restaurant\RestaurantCheckoutService;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS COMANDAS — abrir, servir, juntar, dividir e fechar.
 *
 * Serve a lista de comandas E o balcão, que são a mesma coisa com dois
 * desenhos: a lista serve para encontrar uma comanda, o balcão serve para a
 * atender. Ter duas portas para o mesmo ficheiro é ter duas regras de conta
 * dividida, e mais cedo ou mais tarde elas divergem.
 *
 * AS REGRAS NÃO VIVEM AQUI. O `RestaurantOrderService` e o
 * `RestaurantCheckoutService` são a porta única de sempre — turno obrigatório,
 * cozinha, stock, facturação, taxa de serviço, gorjeta. Este controlador
 * valida a forma do pedido, confirma que os ids são desta empresa e traduz a
 * recusa do serviço numa mensagem que o ecrã sabe mostrar.
 */
class ComandasApiController extends Controller
{
    public function __construct(
        private readonly RestaurantOrderService $comandas,
        private readonly RestaurantCheckoutService $fecho,
    ) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(\Throwable $e): never
    {
        throw ValidationException::withMessages(['geral' => [$e->getMessage()]]);
    }

    private function comanda(int $id): Order
    {
        return Order::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    private function temTurnoAberto(?int $userId): bool
    {
        return PosShift::withoutGlobalScopes()
            ->where('tenant_id', activeTenantId())
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where('status', 'open')
            ->exists();
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.view');

        $tenantId = activeTenantId();
        $definicoes = RestaurantSettings::forTenant($tenantId);

        return response()->json([
            'clientes' => Client::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('name')->limit(200)->get(['id', 'name', 'nif'])
                ->map(fn (Client $c) => [
                    'valor' => (string) $c->id,
                    'rotulo' => $c->nif ? $c->name.' · '.$c->nif : $c->name,
                ])->values(),
            'formas_de_pagamento' => PaymentMethod::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'name'])
                ->map(fn ($f) => ['valor' => (string) $f->id, 'rotulo' => $f->name])->values(),
            'categorias' => Category::where('tenant_id', $tenantId)->where('is_active', true)
                ->whereHas('products', fn ($q) => $q->where('is_active', true))
                ->orderBy('order')->get(['id', 'name', 'icon', 'color'])
                ->map(fn (Category $c) => [
                    'valor' => (string) $c->id, 'rotulo' => $c->name,
                    'icone' => $c->icon ?: 'fa-utensils', 'cor' => $c->color ?: '#EA580C',
                ])->values(),
            'impostos' => Tax::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('rate')->get(['id', 'name', 'rate'])
                ->map(fn (Tax $i) => ['valor' => (string) $i->id, 'rotulo' => $i->name.' · '.rtrim(rtrim(number_format((float) $i->rate, 2, ',', '.'), '0'), ',').'%'])->values(),
            'canais' => collect(Order::CANAIS)->map(fn ($r, $v) => ['valor' => (string) $v, 'rotulo' => __($r)])->values(),
            'estados' => collect(Order::OPEN_STATUSES)->map(fn ($e) => ['valor' => $e, 'rotulo' => __($this->rotuloDoEstado($e))])
                ->push(['valor' => 'billed', 'rotulo' => __('Facturada')])
                ->push(['valor' => 'cancelled', 'rotulo' => __('Anulada')])->values(),
            'definicoes' => [
                'gorjetas' => (bool) ($definicoes->tips_enabled ?? true),
                'taxa_de_servico' => (float) ($definicoes->service_charge_percent ?? 0),
                'cozinha' => (bool) ($definicoes->use_kitchen_workflow ?? true),
                'exige_ficha' => (bool) ($definicoes->require_recipe_for_products ?? false),
            ],
            'tem_turno' => $this->temTurnoAberto($request->user()?->id),
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('restaurant.orders.create'),
                'pode_editar' => (bool) $request->user()?->can('restaurant.orders.edit'),
                'pode_anular' => (bool) $request->user()?->can('restaurant.orders.cancel'),
                'pode_transferir' => (bool) $request->user()?->can('restaurant.orders.transfer'),
                'pode_dividir' => (bool) $request->user()?->can('restaurant.orders.split'),
                'pode_facturar' => (bool) $request->user()?->can('restaurant.checkout.charge'),
            ],
        ]);
    }

    private function rotuloDoEstado(string $estado): string
    {
        return [
            'draft' => 'Aberta', 'confirmed' => 'Confirmada', 'in_preparation' => 'Em preparação',
            'ready' => 'Pronta', 'served' => 'Servida', 'partially_billed' => 'Parcialmente facturada',
            'billed' => 'Facturada', 'cancelled' => 'Anulada',
        ][$estado] ?? $estado;
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'max:30'],
            'canal' => ['nullable', Rule::in(array_keys(Order::CANAIS))],
            'estabelecimento' => ['nullable', 'integer'],
            'abertas' => ['nullable', 'boolean'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $lista = Order::with(['table:id,name,code', 'waiter:id,name'])
            ->when($filtros['procura'] ?? null, function ($q, $termo) {
                $t = '%'.$termo.'%';
                $q->where(fn ($w) => $w->where('order_number', 'like', $t)
                    ->orWhere('customer_name', 'like', $t)
                    ->orWhereHas('table', fn ($m) => $m->where('name', 'like', $t)->orWhere('code', 'like', $t)));
            })
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->when($filtros['canal'] ?? null, fn ($q, $c) => $q->where('channel', $c))
            ->when($filtros['estabelecimento'] ?? null, fn ($q, $v) => $q->where('venue_id', $v))
            ->when($filtros['abertas'] ?? false, fn ($q) => $q->open())
            ->latest()
            ->paginate($filtros['por_pagina'] ?? 12);

        return response()->json([
            'data' => collect($lista->items())->map(fn (Order $o) => $this->linha($o))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
        ]);
    }

    private function linha(Order $o): array
    {
        return [
            'id' => $o->id,
            'numero' => $o->order_number,
            'mesa' => $o->table?->name ?? $o->table?->code,
            'empregado' => $o->waiter?->name,
            'canal' => $o->channel,
            'canal_rotulo' => __(Order::CANAIS[$o->channel] ?? $o->channel),
            'estado' => $o->status,
            'estado_rotulo' => __($this->rotuloDoEstado($o->status)),
            'pessoas' => (int) $o->guest_count,
            'cliente' => $o->customer_name,
            'total' => (float) $o->grand_total,
            'criada_em' => $o->created_at?->toIso8601String(),
        ];
    }

    /** A ficha: a comanda, os artigos, e o que se pode fazer-lhe agora. */
    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.view');

        $tenantId = activeTenantId();

        $o = Order::with(['items.product:id,name', 'table:id,name,code', 'venue:id,name', 'waiter:id,name'])
            ->where('tenant_id', $tenantId)->findOrFail($id);

        $aberta = in_array($o->status, Order::OPEN_STATUSES, true);

        return response()->json([
            'comanda' => array_merge($this->linha($o), [
                'estabelecimento' => $o->venue?->name,
                'venue_id' => $o->venue_id,
                'table_id' => $o->table_id,
                'client_id' => $o->client_id,
                'telefone' => $o->customer_phone,
                'morada' => $o->delivery_address,
                'taxa_de_entrega' => (float) $o->delivery_fee,
                'gorjeta' => (float) $o->tip_amount,
                'observacoes' => $o->notes,
                'subtotal' => (float) $o->subtotal,
                'imposto' => (float) $o->tax_total,
                'despachada_em' => $o->dispatched_at?->toIso8601String(),
                'aberta' => $aberta,
                'para_fora' => $o->paraFora(),
                'entrega_ja_facturada' => $o->deliveryJaFacturada(),
            ]),
            'artigos' => $o->items->map(fn (OrderItem $l) => [
                'id' => $l->id,
                'product_id' => $l->product_id,
                'nome' => $l->product_name,
                'quantidade' => (float) $l->quantity,
                'facturada' => (float) $l->billed_quantity,
                'por_facturar' => round((float) $l->quantity - (float) $l->billed_quantity, 4),
                'unidade' => $l->unit,
                'preco' => (float) $l->unit_price,
                'total' => (float) $l->line_total,
                'estado_na_cozinha' => $l->kitchen_status,
                'observacoes' => $l->notes,
                // Antes de ir para a cozinha remove-se; depois de produzido só
                // se anula, e isso escreve um desperdício com motivo.
                'so_anulavel' => in_array($l->kitchen_status, ['queued', 'accepted', 'preparing', 'ready'], true),
            ])->values(),
            'mesas_livres' => $aberta
                ? DiningTable::where('tenant_id', $tenantId)->where('venue_id', $o->venue_id)
                    ->where('status', 'available')->where('is_active', true)->orderBy('name')
                    ->get(['id', 'name', 'code'])
                    ->map(fn ($m) => ['valor' => (string) $m->id, 'rotulo' => $m->name ?: $m->code])->values()
                : [],
            'comandas_para_juntar' => $aberta
                ? Order::where('tenant_id', $tenantId)->where('venue_id', $o->venue_id)
                    ->where('id', '!=', $o->id)->open()->with('table:id,name,code')->latest()->get()
                    ->map(fn (Order $x) => [
                        'valor' => (string) $x->id,
                        'rotulo' => $x->order_number.($x->table ? ' · '.($x->table->name ?: $x->table->code) : ''),
                    ])->values()
                : [],
        ]);
    }

    /** A grelha de artigos do balcão. */
    public function artigos(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.view');

        $tenantId = activeTenantId();
        $definicoes = RestaurantSettings::forTenant($tenantId);

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:100'],
            'categoria' => ['nullable', 'integer'],
            'limite' => ['nullable', 'integer', 'min:10', 'max:120'],
        ]);

        $artigos = Product::where('tenant_id', $tenantId)->where('is_active', true)
            // Sem isto, a grelha do POS fazia uma consulta por artigo (N+1).
            ->with('category:id,name')
            // Com fichas obrigatórias, um prato sem ficha NÃO se vende — e é
            // por isto que ele não aparece aqui.
            ->when($definicoes->require_recipe_for_products, fn ($q) => $q->whereExists(fn ($r) => $r
                ->selectRaw('1')->from('restaurant_recipes')
                ->whereColumn('restaurant_recipes.product_id', 'invoicing_products.id')
                ->where('restaurant_recipes.tenant_id', $tenantId)
                ->where('restaurant_recipes.is_active', true)))
            ->when($filtros['categoria'] ?? null, fn ($q, $c) => $q->where('category_id', $c))
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';
                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('code', 'like', $t)->orWhere('barcode', 'like', $t));
            })
            ->orderBy('name')
            ->limit($filtros['limite'] ?? 60)
            ->get(['id', 'name', 'code', 'price', 'unit', 'category_id', 'featured_image']);

        return response()->json([
            'data' => $artigos->map(fn (Product $p) => [
                'id' => $p->id,
                'nome' => $p->name,
                'codigo' => $p->code,
                'preco' => (float) $p->price,
                'unidade' => $p->unit,
                'categoria' => $p->category?->name,
                'imagem' => $p->featured_image,
            ])->values(),
        ]);
    }

    /* ─── Os artigos da comanda ────────────────────────────────────────── */

    public function acrescentar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.edit');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'product_id' => ['required', Rule::exists('invoicing_products', 'id')->where('tenant_id', $tenantId)],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->comandas->addItem(
                $this->comanda($id), $dados['product_id'], (float) $dados['quantity'],
                $dados['notes'] ?? null, $tenantId, $request->user()?->id,
            );
        } catch (\Throwable $e) {
            $this->recusa($e);
        }

        return response()->json(['message' => __('Artigo adicionado à comanda.')], 201);
    }

    /**
     * A quantidade muda por um passo (+1 / −1) ou por um valor certo.
     *
     * Chegar a zero é REMOVER, e não gravar uma linha de quantidade nenhuma:
     * uma linha a zero numa comanda é uma linha que ninguém consegue apagar.
     */
    public function quantidade(Request $request, int $id, int $item): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.edit');

        $dados = $request->validate([
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'delta' => ['nullable', 'numeric'],
        ]);

        $linha = OrderItem::where('tenant_id', activeTenantId())->where('order_id', $id)->findOrFail($item);

        $novo = array_key_exists('quantity', $dados) && $dados['quantity'] !== null
            ? round((float) $dados['quantity'], 3)
            : round((float) $linha->quantity + (float) ($dados['delta'] ?? 0), 3);

        try {
            if ($novo <= 0) {
                $this->comandas->removeItem($linha, activeTenantId(), $request->user()?->id,
                    __('Quantidade reduzida a zero no balcão'));
            } else {
                $this->comandas->updateItemQuantity($linha, $novo, activeTenantId(), $request->user()?->id);
            }
        } catch (\Throwable $e) {
            $this->recusa($e);
        }

        return response()->json(['message' => __('Comanda actualizada.')]);
    }

    public function remover(Request $request, int $id, int $item): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.edit');

        $linha = OrderItem::where('tenant_id', activeTenantId())->where('order_id', $id)->findOrFail($item);

        try {
            $this->comandas->removeItem($linha, activeTenantId(), $request->user()?->id,
                __('Removido antes da confirmação'));
        } catch (\Throwable $e) {
            $this->recusa($e);
        }

        return response()->json(['message' => __('Artigo removido.')]);
    }

    /** Anular um artigo JÁ PRODUZIDO: exige motivo e regista desperdício. */
    public function anularArtigo(Request $request, int $id, int $item): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.cancel');

        $dados = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']],
            [], ['reason' => __('motivo')]);

        $linha = OrderItem::where('tenant_id', activeTenantId())->where('order_id', $id)->findOrFail($item);

        try {
            $this->comandas->voidProducedItem($linha, $dados['reason'], activeTenantId(), $request->user()?->id);
        } catch (\Throwable $e) {
            $this->recusa($e);
        }

        return response()->json(['message' => __('Artigo anulado e desperdício registado.')]);
    }

    /* ─── O percurso da comanda ────────────────────────────────────────── */

    public function confirmar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.edit');

        try {
            $this->comandas->confirm(
                Order::with('items')->where('tenant_id', activeTenantId())->findOrFail($id),
                activeTenantId(), $request->user()?->id,
            );
        } catch (\Throwable $e) {
            $this->recusa($e);
        }

        return response()->json(['message' => __('Comanda enviada para preparação.')]);
    }

    /** A comida saiu para o cliente (take-away e entrega). */
    public function despachar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.edit');

        try {
            $this->comandas->despachar($this->comanda($id), activeTenantId(), $request->user()?->id);
        } catch (\Throwable $e) {
            $this->recusa($e);
        }

        return response()->json(['message' => __('Saiu para o cliente.')]);
    }

    public function libertarMesa(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.floor.manage');

        try {
            $this->comandas->releaseTable($this->comanda($id), activeTenantId(), $request->user()?->id);
        } catch (\Throwable $e) {
            $this->recusa($e);
        }

        return response()->json(['message' => __('Mesa limpa e novamente disponível.')]);
    }

    public function transferir(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.transfer');

        $dados = $request->validate([
            'table_id' => ['required', Rule::exists('restaurant_tables', 'id')->where('tenant_id', activeTenantId())],
        ]);

        try {
            $this->comandas->transfer($this->comanda($id), $dados['table_id'], activeTenantId(), $request->user()?->id);
        } catch (\Throwable $e) {
            $this->recusa($e);
        }

        return response()->json(['message' => __('Comanda transferida para a nova mesa.')]);
    }

    public function juntar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.split');

        $dados = $request->validate(['target_order_id' => ['required', 'integer']]);

        try {
            $junta = $this->comandas->merge(
                $this->comanda($id), $this->comanda($dados['target_order_id']),
                activeTenantId(), $request->user()?->id,
            );
        } catch (\Throwable $e) {
            $this->recusa($e);
        }

        return response()->json(['message' => __('Comandas juntadas com sucesso.'), 'order_id' => $junta->id]);
    }

    /* ─── O fecho ──────────────────────────────────────────────────────── */

    /**
     * Fechar a conta.
     *
     * SEM TURNO NÃO SE FACTURA, e a resposta diz-o com um 409 próprio: o ecrã
     * mostra o caminho para abrir o turno, em vez de um aviso que se apaga
     * sozinho e não diz para onde ir.
     *
     * A CONTA DIVIDIDA é `item_ids`: escolhem-se as linhas que vão nesta
     * factura, e o resto fica na comanda (que passa a `partially_billed`).
     */
    public function fechar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.checkout.charge');

        $tenantId = activeTenantId();

        if (! $this->temTurnoAberto($request->user()?->id)) {
            return response()->json([
                'message' => __('Abra um turno antes de facturar.'),
                'falta_turno' => true,
            ], 409);
        }

        $dados = $request->validate([
            'document_type' => ['required', Rule::in(['FR', 'FT'])],
            'client_id' => ['nullable', Rule::exists('invoicing_clients', 'id')->where('tenant_id', $tenantId)],
            'payment_method_id' => [$request->input('document_type') === 'FR' ? 'required' : 'nullable',
                Rule::exists('treasury_payment_methods', 'id')->where('tenant_id', $tenantId)],
            'idempotency_key' => ['required', 'uuid'],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
            'payments' => ['nullable', 'array'],
            'payments.*.payment_method_id' => ['required_with:payments', 'integer'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $comanda = $this->comanda($id);

        if (! in_array($comanda->status, ['ready', 'served', 'partially_billed'], true)) {
            $this->recusa(new \InvalidArgumentException(
                __('A comanda deve estar pronta ou servida antes de facturar.')));
        }

        // As linhas têm de ser DESTA comanda: um id vindo do browser não
        // escolhe artigos de outra mesa para a factura desta.
        $linhas = OrderItem::where('tenant_id', $tenantId)->where('order_id', $comanda->id)
            ->whereIn('id', $dados['item_ids'])->get();

        if ($linhas->count() !== count(array_unique($dados['item_ids']))) {
            $this->recusa(new \InvalidArgumentException(__('Há artigos que já não pertencem a esta comanda.')));
        }

        $multiplo = ! empty($dados['payments']) && count($dados['payments']) > 0;
        $total = round((float) $linhas->sum('line_total'), 2);

        if ($dados['document_type'] === 'FR' && $multiplo) {
            $formas = collect($dados['payments'])->filter(fn ($p) => (float) $p['amount'] > 0)->values();

            if ($formas->count() < 2) {
                $this->recusa(new \InvalidArgumentException(
                    __('Adicione pelo menos dois métodos para usar pagamento múltiplo.')));
            }

            if ($formas->pluck('payment_method_id')->unique()->count() !== $formas->count()) {
                $this->recusa(new \InvalidArgumentException(
                    __('Não repita o mesmo método de pagamento. Some os valores numa única linha.')));
            }

            $porDistribuir = round($total - (float) $formas->sum('amount'), 2);

            if (abs($porDistribuir) > 0.02) {
                $this->recusa(new \InvalidArgumentException($porDistribuir > 0
                    ? __('Ainda falta distribuir :v Kz.', ['v' => number_format($porDistribuir, 2, ',', '.')])
                    : __('Os pagamentos excedem o total em :v Kz.', ['v' => number_format(abs($porDistribuir), 2, ',', '.')])));
            }

            $tenders = $formas->all();
        } else {
            $tenders = [['payment_method_id' => $dados['payment_method_id'] ?? null, 'amount' => $total]];
        }

        try {
            $factura = $this->fecho->checkout($comanda, [
                'document_type' => $dados['document_type'],
                'client_id' => $dados['client_id'] ?? null,
                'payment_method_id' => $dados['payment_method_id'] ?? null,
                'idempotency_key' => $dados['idempotency_key'],
                'item_ids' => $dados['item_ids'],
                'payments' => $dados['document_type'] === 'FR' ? $tenders : null,
                'tip_amount' => $dados['document_type'] === 'FR' ? max(0, (float) ($dados['tip_amount'] ?? 0)) : 0,
            ], $tenantId, $request->user()?->id);
        } catch (\Throwable $e) {
            $this->recusa($e);
        }

        return response()->json([
            'message' => __('Documento :n emitido com sucesso.', ['n' => $factura->invoice_number]),
            'factura' => [
                'id' => $factura->id,
                'numero' => $factura->invoice_number,
                'total' => (float) $factura->total,
            ],
        ]);
    }

    /* ─── O prato criado sem sair do balcão ────────────────────────────── */

    public function artigoRapido(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.menu.manage');

        $tenantId = activeTenantId();
        $definicoes = RestaurantSettings::forTenant($tenantId);

        if ($definicoes->require_recipe_for_products) {
            $this->recusa(new \InvalidArgumentException(__(
                'A configuração exige ficha técnica. Crie o prato na Carta e complete os ingredientes em Fichas Técnicas.')));
        }

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'category_id' => ['nullable', Rule::exists('invoicing_categories', 'id')->where('tenant_id', $tenantId)],
            'tax_rate_id' => ['required', Rule::exists('invoicing_taxes', 'id')->where('tenant_id', $tenantId)],
            'manage_stock' => ['boolean'],
        ]);

        $artigo = Product::create([
            'tenant_id' => $tenantId,
            'category_id' => $dados['category_id'] ?? null,
            'type' => 'produto',
            'name' => trim($dados['name']),
            'price' => $dados['price'],
            'cost' => 0,
            'unit' => 'UN',
            'tax_type' => 'iva',
            'tax_rate_id' => $dados['tax_rate_id'],
            'manage_stock' => (bool) ($dados['manage_stock'] ?? false),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => __('Prato :n criado e disponível no balcão.', ['n' => $artigo->name]),
            'data' => ['id' => $artigo->id, 'nome' => $artigo->name, 'preco' => (float) $artigo->price],
        ], 201);
    }
}
