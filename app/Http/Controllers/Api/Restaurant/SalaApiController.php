<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\MenuOrder;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\Venue;
use App\Models\Restaurant\Waitlist;
use App\Services\Restaurant\ListaDeEspera;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A SALA: as mesas, quem está à espera e os pedidos que entraram pela carta.
 *
 * Serve dois ecrãs — o mapa da sala e o balcão — porque são a mesma pergunta
 * feita de duas maneiras: que mesas há, quais estão ocupadas, e o que se pode
 * fazer a cada uma. Uma segunda cópia disto divergiria ao primeiro estado novo.
 *
 * O ID DA MESA VEM DO BROWSER e por isso é sempre confirmado contra a empresa
 * activa antes de se lhe tocar — o `where('tenant_id')` em cada busca não é
 * zelo a mais: é a diferença entre libertar a mesa 4 desta casa e a de outra.
 */
class SalaApiController extends Controller
{
    public function __construct(
        private readonly RestaurantOrderService $comandas,
        private readonly ListaDeEspera $fila,
    ) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    /** Uma falha de regra do serviço é 422, com a frase que ele escreveu. */
    private function recusa(\Throwable $e): never
    {
        throw ValidationException::withMessages(['geral' => [$e->getMessage()]]);
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.floor.view');

        $tenantId = activeTenantId();

        return response()->json([
            'estabelecimentos' => Venue::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn (Venue $v) => ['valor' => (string) $v->id, 'rotulo' => $v->name])->values(),
            'estados_da_mesa' => collect(DiningTable::STATUSES)
                ->map(fn ($r, $v) => ['valor' => (string) $v, 'rotulo' => __($r)])->values(),
            'canais' => collect(Order::CANAIS)
                ->map(fn ($r, $v) => ['valor' => (string) $v, 'rotulo' => __($r)])->values(),
            'permissoes' => [
                'pode_gerir' => (bool) $request->user()?->can('restaurant.floor.manage'),
                'pode_abrir' => (bool) $request->user()?->can('restaurant.orders.create'),
            ],
        ]);
    }

    /** O mapa: as zonas do estabelecimento e as mesas de cada uma. */
    public function mapa(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.floor.view');

        $tenantId = activeTenantId();

        /*
         * O PADRÃO É O MESMO QUE O ECRÃ MOSTRA: o primeiro POR NOME.
         *
         * As opções saem daqui ordenadas por nome; ordenar o padrão por id
         * fazia o selector dizer «Restaurante Principal» e o mapa trazer as
         * mesas de outra casa — uma sala vazia ao lado de um nome que tinha
         * mesas.
         */
        $estabelecimento = $request->integer('estabelecimento')
            ?: (int) Venue::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->value('id');

        $zonas = Area::where('tenant_id', $tenantId)
            ->where('venue_id', $estabelecimento)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        /*
         * A COMANDA ABERTA VEM INTEIRA, sem lista de colunas.
         *
         * O `activeOrder` é um `latestOfMany`, e o Laravel resolve-o com uma
         * subconsulta em JOIN. Com as colunas escolhidas à mão, o `table_id`
         * aparece dos dois lados do JOIN e o MySQL responde «Column 'table_id'
         * in field list is ambiguous» — o mapa da sala inteiro deixava de abrir.
         */
        $mesas = DiningTable::with(['area:id,name', 'activeOrder'])
            ->where('tenant_id', $tenantId)
            ->where('venue_id', $estabelecimento)
            ->when($request->integer('zona'), fn ($q, $id) => $q->where('area_id', $id))
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return response()->json([
            'estabelecimento' => $estabelecimento,
            'zonas' => $zonas->map(fn (Area $z) => ['valor' => (string) $z->id, 'rotulo' => $z->name])->values(),
            'mesas' => $mesas->map(fn (DiningTable $m) => $this->mesaParaOEcra($m))->values(),
            'contagem' => collect(DiningTable::STATUSES)->keys()
                ->mapWithKeys(fn ($e) => [$e => $mesas->where('status', $e)->count()]),
            /*
             * Os pedidos que os clientes fizeram na carta online e ainda
             * ninguém atendeu. Aparecem aqui porque é aqui que o empregado
             * olha — não num ecrã à parte que ninguém abre a meio do serviço.
             */
            'pedidos_da_carta' => MenuOrder::where('tenant_id', $tenantId)
                ->aEspera()->with('mesa:id,name,code')->latest()->limit(20)->get()
                ->map(fn (MenuOrder $p) => [
                    'id' => $p->id,
                    'mesa' => $p->mesa?->name ?? $p->table_code,
                    'cliente' => $p->customer_name,
                    'telefone' => $p->customer_phone,
                    'observacoes' => $p->notes,
                    'total_previsto' => (float) $p->estimated_total,
                    'artigos' => collect($p->items ?? [])->map(fn ($l) => [
                        'nome' => $l['name'] ?? $l['product_name'] ?? '—',
                        'quantidade' => (float) ($l['quantity'] ?? 0),
                    ])->values(),
                    'criado_em' => $p->created_at?->toIso8601String(),
                ])->values(),
            'espera' => $this->fila->fila($tenantId, $estabelecimento)
                ->map(fn (Waitlist $e) => [
                    'id' => $e->id,
                    'nome' => $e->guest_name,
                    'telefone' => $e->phone,
                    'pessoas' => (int) $e->guest_count,
                    'minutos' => $e->minutosDeEspera(),
                    'chegou_em' => $e->arrived_at?->toIso8601String(),
                ])->values(),
        ]);
    }

    private function mesaParaOEcra(DiningTable $m): array
    {
        return [
            'id' => $m->id,
            'codigo' => $m->code,
            'nome' => $m->name,
            'lugares' => (int) $m->capacity,
            'zona' => $m->area?->name,
            'estado' => $m->status,
            'estado_rotulo' => __(DiningTable::STATUSES[$m->status] ?? $m->status),
            'comanda' => $m->activeOrder ? [
                'id' => $m->activeOrder->id,
                'numero' => $m->activeOrder->order_number,
                'estado' => $m->activeOrder->status,
                'total' => (float) $m->activeOrder->grand_total,
                'pessoas' => (int) $m->activeOrder->guest_count,
                'aberta_em' => $m->activeOrder->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    public function criarMesa(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.floor.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'venue_id' => ['required', Rule::exists('restaurant_venues', 'id')->where('tenant_id', $tenantId)],
            'area_id' => ['nullable', Rule::exists('restaurant_areas', 'id')->where('tenant_id', $tenantId)],
            'code' => ['required', 'string', 'max:30',
                Rule::unique('restaurant_tables', 'code')->where(
                    fn ($q) => $q->where('tenant_id', $tenantId)->where('venue_id', $request->integer('venue_id')),
                )],
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $mesa = DiningTable::create([
            'tenant_id' => $tenantId,
            'venue_id' => $dados['venue_id'],
            'area_id' => $dados['area_id'] ?? null,
            'code' => mb_strtoupper(trim($dados['code'])),
            'name' => trim($dados['name']),
            'capacity' => $dados['capacity'],
        ]);

        return response()->json(['message' => __('Mesa criada com sucesso.'), 'data' => $this->mesaParaOEcra($mesa)], 201);
    }

    /**
     * Abrir atendimento numa mesa.
     *
     * Uma mesa que já tem comanda não abre outra: devolve a que está lá, e o
     * ecrã segue para ela. Dois atendimentos na mesma mesa é uma conta partida
     * ao meio sem ninguém ter pedido.
     */
    public function abrir(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.create');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'table_id' => ['required', Rule::exists('restaurant_tables', 'id')->where('tenant_id', $tenantId)],
            'guest_count' => ['required', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $mesa = DiningTable::where('tenant_id', $tenantId)->where('is_active', true)->findOrFail($dados['table_id']);

        if ($jaAberta = $mesa->activeOrder()->value('id')) {
            return response()->json(['message' => __('Esta mesa já tem uma comanda aberta.'), 'order_id' => $jaAberta]);
        }

        if ($mesa->status === 'cleaning') {
            $this->recusa(new \InvalidArgumentException(
                __('A mesa aguarda limpeza. Marque-a como limpa antes de abrir novo atendimento.')));
        }

        if ($mesa->status === 'blocked') {
            $this->recusa(new \InvalidArgumentException(__('A mesa está bloqueada e não pode receber atendimento.')));
        }

        try {
            $comanda = $this->comandas->open([
                'venue_id' => $mesa->venue_id,
                'table_id' => $mesa->id,
                'guest_count' => $dados['guest_count'],
                'notes' => $dados['notes'] ?? null,
            ], $tenantId, $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            // Falta de turno tem resposta própria: o ecrã mostra o caminho
            // para o abrir, em vez de um aviso que se apaga sozinho.
            if (str_contains($e->getMessage(), 'turno')) {
                return response()->json(['message' => $e->getMessage(), 'falta_turno' => true], 409);
            }

            $this->recusa($e);
        }

        return response()->json(['message' => __('Comanda aberta.'), 'order_id' => $comanda->id], 201);
    }

    /** Balcão, take-away e entrega: comanda sem mesa. */
    public function abrirSemMesa(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.create');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'venue_id' => ['required', Rule::exists('restaurant_venues', 'id')->where('tenant_id', $tenantId)],
            'channel' => ['required', Rule::in(Order::CANAIS_SEM_MESA)],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_phone' => [$request->input('channel') === 'counter' ? 'nullable' : 'required', 'string', 'max:30'],
            'delivery_address' => [$request->input('channel') === 'delivery' ? 'required' : 'nullable', 'string', 'max:1000'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'customer_phone' => __('telefone'),
            'delivery_address' => __('morada'),
            'delivery_fee' => __('taxa de entrega'),
        ]);

        try {
            $comanda = $this->comandas->open([
                'venue_id' => $dados['venue_id'],
                'channel' => $dados['channel'],
                'guest_count' => 1,
                'customer_name' => $dados['customer_name'] ?? null,
                'customer_phone' => $dados['customer_phone'] ?? null,
                'delivery_address' => $dados['delivery_address'] ?? null,
                'delivery_fee' => $dados['channel'] === 'delivery' ? (float) ($dados['delivery_fee'] ?? 0) : 0,
            ], $tenantId, $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'turno')) {
                return response()->json(['message' => $e->getMessage(), 'falta_turno' => true], 409);
            }

            $this->recusa($e);
        }

        return response()->json([
            'message' => __(':canal aberto: :numero.', [
                'canal' => __(Order::CANAIS[$dados['channel']]),
                'numero' => $comanda->order_number,
            ]),
            'order_id' => $comanda->id,
        ], 201);
    }

    /** O estado da mesa mudado à mão: bloquear, desbloquear, pôr em limpeza. */
    public function estadoDaMesa(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.floor.manage');

        $dados = $request->validate(['estado' => ['required', Rule::in(array_keys(DiningTable::STATUSES))]]);

        $mesa = DiningTable::where('tenant_id', activeTenantId())->findOrFail($id);

        try {
            $this->comandas->changeTableStatus($mesa, $dados['estado'], activeTenantId());
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e);
        }

        return response()->json([
            'message' => __('Estado da mesa actualizado para :estado.', ['estado' => $mesa->fresh()->status_label]),
            'data' => $this->mesaParaOEcra($mesa->fresh(['area', 'activeOrder'])),
        ]);
    }

    /**
     * A mesa foi limpa e volta ao serviço.
     *
     * Passa pelo `releaseTable` da última comanda facturada — é ele que sabe
     * o que fazer. Só quando não há comanda nenhuma é que se corrige o estado
     * à mão, e isso fica registado: é um estado órfão de alguma coisa antiga,
     * não um caminho normal.
     */
    public function limpar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.floor.manage');

        $tenantId = activeTenantId();
        $mesa = DiningTable::where('tenant_id', $tenantId)->where('is_active', true)->findOrFail($id);

        if ($mesa->status !== 'cleaning') {
            $this->recusa(new \InvalidArgumentException(__('A mesa já não está em limpeza. Actualize a sala.')));
        }

        $ultima = Order::where('tenant_id', $tenantId)->where('table_id', $mesa->id)
            ->where('status', 'billed')->latest('closed_at')->first();

        try {
            if ($ultima) {
                $this->comandas->releaseTable($ultima, $tenantId, $request->user()?->id);
            } elseif (! $mesa->activeOrder()->exists()) {
                $mesa->update(['status' => 'available']);

                Log::warning('Estado de limpeza órfão corrigido no mapa do restaurante.', [
                    'tenant_id' => $tenantId, 'table_id' => $mesa->id, 'user_id' => $request->user()?->id,
                ]);
            } else {
                throw new \InvalidArgumentException(__('A mesa ainda possui uma comanda pendente.'));
            }
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e);
        }

        return response()->json(['message' => __('Mesa limpa e disponível para novo atendimento.')]);
    }

    /* ─── Os pedidos que entram pela carta online ─────────────────────── */

    /**
     * Aceitar um pedido que o cliente fez na carta.
     *
     * É AQUI que o pedido vira comanda, e não antes: a comanda exige turno
     * aberto, e o turno é o de quem está a aceitar. O cliente sentado à mesa
     * não tem turno nenhum.
     *
     * OS PREÇOS SÃO OS DE AGORA. O pedido guardou o que o cliente VIU, mas
     * quem manda é o catálogo neste momento: uma carta aberta há uma hora
     * numa página com um preço congelado seria um desconto que ninguém deu.
     */
    public function aceitarPedidoDaCarta(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.create');

        $tenantId = activeTenantId();
        $userId = $request->user()?->id;

        $pedido = MenuOrder::where('tenant_id', $tenantId)->where('status', 'pending')->findOrFail($id);

        $estabelecimento = $request->integer('venue_id')
            ?: (int) Venue::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->value('id');

        try {
            $comanda = $this->comandas->open([
                'venue_id' => $estabelecimento,
                'table_id' => $pedido->table_id,
                'guest_count' => 1,
                'notes' => trim(__('Pedido pela carta online').' · '.(string) $pedido->notes, ' ·'),
            ], $tenantId, $userId);

            foreach ($pedido->items ?? [] as $linha) {
                $this->comandas->addItem(
                    $comanda,
                    (int) $linha['product_id'],
                    (float) $linha['quantity'],
                    null,
                    $tenantId,
                    $userId,
                );
            }

            $pedido->update([
                'status' => 'accepted',
                'order_id' => $comanda->id,
                'handled_by' => $userId,
                'handled_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // A mesa pode ter sido ocupada entretanto, ou o turno pode estar
            // fechado. Dizer porquê — o empregado tem o cliente à frente.
            $this->recusa($e);
        }

        return response()->json(['message' => __('Pedido aceite — comanda aberta.'), 'order_id' => $comanda->id]);
    }

    /** Descartar: um engano, uma brincadeira, ou já foi resolvido à mão. */
    public function descartarPedidoDaCarta(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.create');

        MenuOrder::where('tenant_id', activeTenantId())->where('status', 'pending')->whereKey($id)
            ->update(['status' => 'dismissed', 'handled_by' => $request->user()?->id, 'handled_at' => now()]);

        return response()->json(['message' => __('Pedido descartado.')]);
    }

    /* ─── A fila à porta ───────────────────────────────────────────────── */

    public function chegouAFila(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.create');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'venue_id' => ['required', Rule::exists('restaurant_venues', 'id')->where('tenant_id', $tenantId)],
            'guest_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:100'],
        ], [], ['guest_name' => __('nome'), 'guest_count' => __('pessoas')]);

        $this->fila->chegar($dados, $tenantId, $request->user()?->id);

        return response()->json(['message' => __('Na fila. A ordem de chegada manda.')], 201);
    }

    public function sentarDaFila(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.create');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'table_id' => ['required', Rule::exists('restaurant_tables', 'id')->where('tenant_id', $tenantId)],
        ]);

        $entrada = Waitlist::where('tenant_id', $tenantId)->findOrFail($id);

        try {
            $comanda = $this->fila->sentar($entrada, $dados['table_id'], $tenantId, $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'turno')) {
                return response()->json(['message' => $e->getMessage(), 'falta_turno' => true], 409);
            }

            // A entrada fica na fila: sentar meio cliente era perdê-lo.
            $this->recusa($e);
        }

        return response()->json([
            'message' => __(':nome sentado — comanda aberta.', ['nome' => $entrada->guest_name]),
            'order_id' => $comanda->id,
        ]);
    }

    public function desistiuDaFila(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.orders.create');

        $entrada = Waitlist::where('tenant_id', activeTenantId())->findOrFail($id);

        try {
            $this->fila->desistir($entrada, activeTenantId());
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e);
        }

        return response()->json(['message' => __('Registado — conta para as mesas que faltam.')]);
    }
}
