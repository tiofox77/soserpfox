<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Workshop\Mechanic;
use App\Models\Workshop\Service;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderAttachment;
use App\Models\Workshop\WorkOrderHistory;
use App\Models\Workshop\WorkOrderItem;
use App\Services\Workshop\OrdensDeServico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * AS ORDENS DE SERVIÇO — o ecrã que a oficina abre todos os dias.
 *
 * Todas as regras vivem no serviço (`OrdensDeServico`), e este controlador só
 * valida, chama e devolve. Isso importa numa coisa em particular: A MUDANÇA DE
 * ESTADO TEM UMA PORTA SÓ. Gravar a ficha e carregar no botão de estado entram
 * ambos por `aplicarEstado()`, porque passar a «Concluída» desconta as peças do
 * stock e anular devolve-as — e o ecrã em Livewire já tinha tido o buraco de
 * gravar o estado por fora.
 *
 * CADA VERBO PEDE A SUA PERMISSÃO. As rotas do módulo estão atrás de
 * `workshop.work-orders.view`, que diz quem entra; o que se pode FAZER lá
 * dentro decide-se aqui.
 */
class OrdensApiController extends Controller
{
    public function __construct(private readonly OrdensDeServico $ordens) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    /* ─── O que o ecrã precisa de saber ───────────────────────────────── */

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');

        $tenantId = activeTenantId();

        return response()->json([
            'estados' => self::escolhas(OrdensDeServico::ESTADOS),
            'prioridades' => self::escolhas(OrdensDeServico::PRIORIDADES),
            'categorias_de_anexo' => self::escolhas(OrdensDeServico::CATEGORIAS_DE_ANEXO),
            // As viaturas num estado que pode receber ordens (catálogo Estados de Viatura).
            'viaturas' => Vehicle::where('tenant_id', $tenantId)
                ->whereIn('status', \App\Models\Workshop\VehicleStatus::aceitamOrdens($tenantId))->orderBy('plate')
                ->get(['id', 'plate', 'brand', 'model', 'owner_name', 'mileage'])
                ->map(fn (Vehicle $v) => [
                    'valor' => (string) $v->id,
                    'rotulo' => trim("{$v->plate} — {$v->brand} {$v->model}"),
                    'dono' => $v->owner_name,
                    // Os quilómetros da viatura preenchem o campo de entrada:
                    // escrevê-los outra vez à mão é onde nascem os enganos.
                    'km' => (int) $v->mileage,
                ])->values(),
            'mecanicos' => Mechanic::where('tenant_id', $tenantId)
                ->where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($m) => ['valor' => (string) $m->id, 'rotulo' => $m->name])->values(),
            'servicos' => Service::where('tenant_id', $tenantId)
                ->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'service_code', 'description', 'labor_cost', 'estimated_hours'])
                ->map(fn (Service $s) => [
                    'valor' => (string) $s->id,
                    'rotulo' => $s->name,
                    'codigo' => $s->service_code,
                    'descricao' => $s->description,
                    'preco' => (float) $s->labor_cost,
                    'horas' => (float) $s->estimated_hours,
                ])->values(),
            // OF-10: se o cliente é avisado por SMS/email quando a ordem muda (módulo Notificações).
            'avisos_ao_cliente' => \App\Services\Workshop\AvisosDaOficina::activos($tenantId),
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('workshop.work-orders.create'),
                'pode_editar' => (bool) $request->user()?->can('workshop.work-orders.edit'),
                'pode_apagar' => (bool) $request->user()?->can('workshop.work-orders.delete'),
                // Facturar é emitir um documento fiscal: pede a permissão da
                // FACTURAÇÃO, e não a de mexer numa ordem de serviço.
                'pode_facturar' => (bool) $request->user()?->can('invoicing.sales.invoices.create'),
            ],
        ]);
    }

    /**
     * AS PEÇAS DO CATÁLOGO, procuradas.
     *
     * Não vêm todas nas opções: o catálogo desta casa tem doze mil artigos, e
     * mandá-los todos para o browser para encher um `<select>` era o que o ecrã
     * em Livewire fazia.
     */
    public function artigos(Request $request): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');

        $procura = trim((string) $request->query('procura', ''));
        $tenantId = activeTenantId();
        $armazem = Warehouse::getDefault($tenantId);

        $artigos = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($procura !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$procura}%")
                ->orWhere('code', 'like', "%{$procura}%")
                ->orWhere('barcode', 'like', "%{$procura}%")))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'code', 'description', 'price', 'unit']);

        /*
         * O STOCK É O DO ARMAZÉM DE ONDE A PEÇA VAI SAIR, e não a soma de
         * todos. Somando tudo, uma peça com existência só noutro armazém
         * aparecia disponível e a baixa rebentava depois com «stock
         * insuficiente» — já com a ordem dada por concluída.
         */
        $stock = Stock::where('tenant_id', $tenantId)
            ->when($armazem, fn ($q) => $q->where('warehouse_id', $armazem->id))
            ->whereIn('product_id', $artigos->pluck('id'))
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as quantidade')
            ->pluck('quantidade', 'product_id');

        return response()->json([
            'armazem' => $armazem?->name,
            'data' => $artigos->map(fn (Product $p) => [
                'valor' => (string) $p->id,
                'rotulo' => $p->name,
                'codigo' => $p->code,
                'descricao' => $p->description,
                'preco' => (float) $p->price,
                'unidade' => $p->unit,
                'stock' => (float) ($stock[$p->id] ?? 0),
            ])->values(),
        ]);
    }

    /* ─── A lista ─────────────────────────────────────────────────────── */

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(array_keys(OrdensDeServico::ESTADOS))],
            'prioridade' => ['nullable', Rule::in(array_keys(OrdensDeServico::PRIORIDADES))],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenantId = activeTenantId();

        $consulta = WorkOrder::where('tenant_id', $tenantId)
            ->with(['vehicle:id,plate,brand,model,owner_name', 'mechanic:id,name'])
            ->when($filtros['procura'] ?? null, fn ($q, $p) => $q->where(fn ($w) => $w
                ->where('order_number', 'like', "%{$p}%")
                ->orWhereHas('vehicle', fn ($v) => $v
                    ->where('plate', 'like', "%{$p}%")
                    ->orWhere('owner_name', 'like', "%{$p}%"))))
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->when($filtros['prioridade'] ?? null, fn ($q, $p) => $q->where('priority', $p))
            ->latest('received_at');

        $pagina = $consulta->paginate($filtros['por_pagina'] ?? 10)->withQueryString();

        // O RESUMO CONTA A CASA INTEIRA e não a página: «3 em curso» com os
        // filtros postos é uma resposta a outra pergunta.
        $porEstado = WorkOrder::where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as quantos')->groupBy('status')->pluck('quantos', 'status');

        return response()->json([
            'data' => collect($pagina->items())->map(fn (WorkOrder $o) => $this->linha($o))->all(),
            'meta' => [
                'total' => $pagina->total(),
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'per_page' => $pagina->perPage(),
            ],
            'resumo' => [
                'total' => (int) $porEstado->sum(),
                'em_aberto' => (int) ($porEstado['pending'] ?? 0) + (int) ($porEstado['scheduled'] ?? 0)
                    + (int) ($porEstado['waiting_parts'] ?? 0),
                'em_curso' => (int) ($porEstado['in_progress'] ?? 0),
                'concluidas' => (int) ($porEstado['completed'] ?? 0),
            ],
        ]);
    }

    /** A FICHA INTEIRA — os seis separadores da janela de ver. */
    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');

        $ordem = $this->encontrar($id);
        $ordem->load([
            'vehicle', 'mechanic:id,name', 'items.service:id,name', 'items.product:id,name,code',
            'items.mechanic:id,name', 'history.user:id,name', 'attachments.user:id,name', 'invoice.client:id,name',
        ]);

        return response()->json(['data' => $this->linha($ordem) + [
            /*
             * A VIATURA POR EXTENSO tem chave própria e não sobrepõe a da
             * lista. O `+` de arrays em PHP mantém a chave da ESQUERDA: com o
             * mesmo nome, este bloco era descartado em silêncio e o separador
             * de informação abria sem viatura nenhuma.
             */
            'viatura_ficha' => $ordem->vehicle ? [
                'id' => $ordem->vehicle->id,
                'matricula' => $ordem->vehicle->plate,
                'marca' => $ordem->vehicle->brand,
                'modelo' => $ordem->vehicle->model,
                'ano' => $ordem->vehicle->year,
                'cor' => $ordem->vehicle->color,
                'combustivel' => $ordem->vehicle->fuel_type,
                'dono' => $ordem->vehicle->owner_name,
                'telefone' => $ordem->vehicle->owner_phone,
            ] : null,
            'agendada_para' => $ordem->scheduled_for?->toIso8601String(),
            'iniciada_em' => $ordem->started_at?->toIso8601String(),
            'concluida_em' => $ordem->completed_at?->toIso8601String(),
            'entregue_em' => $ordem->delivered_at?->toIso8601String(),
            'garantia_ate' => $ordem->warranty_expires?->toDateString(),
            'garantia_dias' => (int) $ordem->warranty_days,
            'problema' => $ordem->problem_description,
            'diagnostico' => $ordem->diagnosis,
            'trabalho' => $ordem->work_performed,
            'recomendacoes' => $ordem->recommendations,
            'notas' => $ordem->notes,
            'linhas' => $ordem->items->map(fn (WorkOrderItem $i) => [
                'id' => $i->id,
                'tipo' => $i->type,
                'codigo' => $i->code,
                'nome' => $i->name,
                'descricao' => $i->description,
                'quantidade' => (float) $i->quantity,
                'preco' => (float) $i->unit_price,
                'desconto' => (float) $i->discount_percent,
                'subtotal' => (float) $i->subtotal,
                'horas' => (float) $i->hours,
                'mecanico' => $i->mechanic?->name,
                'referencia' => $i->part_number,
                'marca' => $i->brand,
                'original' => (bool) $i->is_original,
                'aprovacao' => $i->approval ?? 'approved',
                'aprovacao_em' => $i->approval_at?->toIso8601String(),
                'aprovacao_por' => $i->approval_by,
            ])->values(),
            // OF-03: o link de aprovação e a assinatura do cliente.
            'aprovacao' => AprovacaoDoOrcamentoApiController::resumo($ordem),
            'historico' => $ordem->history->map(fn (WorkOrderHistory $h) => [
                'id' => $h->id,
                'accao' => $h->action,
                'descricao' => $h->description,
                'quem' => $h->user?->name,
                'quando' => $h->created_at?->toIso8601String(),
            ])->values(),
            'anexos' => $ordem->attachments->map(fn (WorkOrderAttachment $a) => [
                'id' => $a->id,
                'nome' => $a->original_filename,
                'url' => $a->file_url,
                'categoria' => $a->category,
                'categoria_rotulo' => __(OrdensDeServico::CATEGORIAS_DE_ANEXO[$a->category] ?? $a->category),
                'tamanho' => $a->file_size_formatted,
                'imagem' => (bool) $a->is_image,
                'descricao' => $a->description,
                'quem' => $a->user?->name,
                'quando' => $a->created_at?->toIso8601String(),
            ])->values(),
            'factura' => $ordem->invoice ? [
                'id' => $ordem->invoice->id,
                'numero' => $ordem->invoice->invoice_number,
                'cliente' => $ordem->invoice->client?->name,
                'quando' => $ordem->invoiced_at?->toIso8601String(),
                'morada' => route('invoicing.sales.invoices.preview', $ordem->invoice->id),
            ] : null,
        ]]);
    }

    /* ─── Gravar ──────────────────────────────────────────────────────── */

    public function store(Request $request): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.create');

        $dados = $this->validar($request);

        [$ordem, $mensagem] = $this->ordens->guardar($dados, null, activeTenantId());

        return response()->json(['data' => $this->linha($ordem->fresh(['vehicle', 'mechanic'])), 'message' => $mensagem], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');

        $ordem = $this->encontrar($id);
        $dados = $this->validar($request);

        try {
            [$ordem, $mensagem] = $this->ordens->guardar($dados, $ordem, activeTenantId());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->linha($ordem->fresh(['vehicle', 'mechanic'])), 'message' => $mensagem]);
    }

    /**
     * O QUADRO DE TRABALHO (15/09/2026, OF-04) — as ordens abertas em colunas
     * pelo estado, e as entregues nos últimos 7 dias. As canceladas ficam fora.
     *
     * Cada cartão traz o que se quer ver de relance na parede da oficina: a
     * matrícula e o TAG#, o mecânico, a prioridade, há quanto tempo o carro
     * entrou, se o check-in está feito e quantas linhas esperam pelo cliente.
     */
    public function quadro(Request $request): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');

        $filtros = $request->validate([
            'mecanico' => ['nullable', 'string', 'max:20'],
            'procura' => ['nullable', 'string', 'max:100'],
        ]);

        $tenantId = activeTenantId();

        $ordens = WorkOrder::where('tenant_id', $tenantId)
            ->with(['vehicle:id,plate,brand,model,owner_name,tag_number,work_order_ref', 'mechanic:id,name', 'checkin:id,work_order_id,signed_at'])
            ->withCount(['items as a_espera' => fn ($q) => $q->where('approval', 'pending')])
            ->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->where('status', '!=', 'delivered')->orWhere('delivered_at', '>=', now()->subDays(7)))
            ->when(($filtros['mecanico'] ?? '') === 'sem', fn ($q) => $q->whereNull('mechanic_id'))
            ->when(ctype_digit((string) ($filtros['mecanico'] ?? '')), fn ($q) => $q->where('mechanic_id', (int) $filtros['mecanico']))
            ->when($filtros['procura'] ?? null, fn ($q, $p) => $q->where(fn ($w) => $w
                ->where('order_number', 'like', "%{$p}%")
                ->orWhereHas('vehicle', fn ($v) => $v->where('plate', 'like', "%{$p}%")->orWhere('owner_name', 'like', "%{$p}%")
                    ->orWhere('tag_number', 'like', "%{$p}%")->orWhere('work_order_ref', 'like', "%{$p}%"))))
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('received_at')
            ->limit(300)
            ->get();

        // Quem tem o relógio a correr em cada ordem (OF-06).
        $aTrabalhar = \App\Models\Workshop\TimeEntry::with('mechanic:id,name')->where('tenant_id', $tenantId)->whereNull('ended_at')
            ->whereIn('work_order_id', $ordens->pluck('id'))->get()->groupBy('work_order_id')
            ->map(fn ($r) => $r->pluck('mechanic.name')->filter()->unique()->values()->all());

        $colunas = collect(OrdensDeServico::ESTADOS)->except('cancelled')->map(fn ($rotulo, $estado) => [
            'estado' => $estado,
            'rotulo' => __($rotulo),
            'cartoes' => $ordens->where('status', $estado)->map(fn (WorkOrder $o) => [
                'a_trabalhar' => $aTrabalhar[$o->id] ?? [],
                'id' => $o->id,
                'numero' => $o->order_number,
                'matricula' => $o->vehicle?->plate,
                'tag' => $o->vehicle?->tag_number,
                'wo' => $o->vehicle?->work_order_ref,
                'viatura' => $o->vehicle ? trim("{$o->vehicle->brand} {$o->vehicle->model}") : null,
                'dono' => $o->vehicle?->owner_name,
                'mecanico_id' => $o->mechanic_id,
                'mecanico' => $o->mechanic?->name,
                'prioridade' => $o->priority,
                'prioridade_rotulo' => __(OrdensDeServico::PRIORIDADES[$o->priority] ?? $o->priority),
                'entrada' => $o->received_at?->toIso8601String(),
                'agendada_para' => $o->scheduled_for?->toIso8601String(),
                'atrasada' => (bool) $o->is_overdue,
                'total' => round((float) $o->total, 2),
                'facturada' => (bool) $o->invoice_id,
                'checkin' => $o->checkin ? ($o->checkin->signed_at ? 'assinado' : 'feito') : null,
                'a_espera' => (int) $o->a_espera,
            ])->values(),
        ])->values();

        return response()->json([
            'colunas' => $colunas,
            'mecanicos' => Mechanic::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($m) => ['valor' => (string) $m->id, 'rotulo' => $m->name])->values(),
            'pode_editar' => (bool) $request->user()?->can('workshop.work-orders.edit'),
        ]);
    }

    /** Atribuir o mecânico de uma ordem — do quadro, sem abrir o formulário inteiro. */
    public function mecanico(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');

        $dados = $request->validate(['mechanic_id' => ['nullable', 'integer']]);
        $this->daCasa($dados, 'mechanic_id', Mechanic::class);

        $ordem = $this->encontrar($id);
        $antes = $ordem->mechanic?->name;
        $ordem->update(['mechanic_id' => $dados['mechanic_id'] ?? null]);
        $depois = $ordem->fresh('mechanic')->mechanic?->name;

        WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_UPDATED,
            __('Mecânico: :antes → :depois', ['antes' => $antes ?? __('Por atribuir'), 'depois' => $depois ?? __('Por atribuir')]));

        return response()->json([
            'data' => $this->linha($ordem->fresh(['vehicle', 'mechanic'])),
            'message' => $depois ? __(':ordem entregue a :mecanico.', ['ordem' => $ordem->order_number, 'mecanico' => $depois]) : __(':ordem ficou sem mecânico.', ['ordem' => $ordem->order_number]),
        ]);
    }

    /**
     * MUDAR O ESTADO — a porta única.
     *
     * Passar a «Concluída» desconta as peças do stock e anular devolve-as; é
     * por isso que isto não é um `update` de uma coluna.
     */
    public function estado(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');

        $dados = $request->validate([
            'estado' => ['required', Rule::in(array_keys(OrdensDeServico::ESTADOS))],
        ]);

        $ordem = $this->encontrar($id);

        try {
            [$mensagem, $falhas] = $this->ordens->aplicarEstado($ordem, $dados['estado']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->linha($ordem->fresh(['vehicle', 'mechanic'])),
            'message' => $mensagem,
            // As peças que não saíram vêm à parte para o ecrã as poder mostrar
            // como aviso, e não como um sucesso com uma frase comprida.
            'falhas' => $falhas,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.delete');

        $ordem = $this->encontrar($id);

        /*
         * UMA ORDEM JÁ FACTURADA NÃO SE APAGA. A factura é um documento fiscal
         * e ficaria a apontar para uma ordem que já não existe.
         */
        if ($ordem->invoice_id) {
            return response()->json([
                'message' => __('Esta ordem já foi facturada e não se pode apagar. Anule a factura primeiro.'),
            ], 422);
        }

        // Apagar sem isto deixava as peças fora do stock para sempre.
        $ordem->returnStockMovement();
        $ordem->delete();

        return response()->json(['message' => __('Ordem apagada. Peças devolvidas ao stock.')]);
    }

    /* ─── As linhas ───────────────────────────────────────────────────── */

    public function juntarLinha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');

        $ordem = $this->encontrar($id);

        $dados = $request->validate([
            'type' => ['required', Rule::in(['service', 'part'])],
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'hours' => ['nullable', 'numeric', 'min:0'],
            'mechanic_id' => ['nullable', 'integer'],
            'part_number' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'is_original' => ['nullable', 'boolean'],
            // OF-03: a linha fica à espera da aprovação do cliente.
            'precisa_aprovacao' => ['nullable', 'boolean'],
        ]);

        // Os ids vêm do pedido e não provam nada: um serviço, um artigo ou um
        // mecânico de outra empresa entrava na linha e ia parar à factura.
        $this->daCasa($dados, 'service_id', Service::class);
        $this->daCasa($dados, 'product_id', Product::class);
        $this->daCasa($dados, 'mechanic_id', Mechanic::class);

        $this->ordens->juntarLinha($ordem, $dados);

        return response()->json(['message' => __('Linha adicionada.')], 201);
    }

    public function tirarLinha(Request $request, int $id, int $linha): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');

        $ordem = $this->encontrar($id);

        // A linha tem de ser DESTA ordem: o id vem do URL.
        $item = WorkOrderItem::where('work_order_id', $ordem->id)->findOrFail($linha);

        $this->ordens->tirarLinha($ordem, $item);

        return response()->json(['message' => __('Linha removida.')]);
    }

    public function desconto(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');

        $dados = $request->validate(['desconto' => ['required', 'numeric', 'min:0']]);

        $ordem = $this->encontrar($id);

        $this->ordens->desconto($ordem, (float) $dados['desconto']);

        return response()->json(['message' => __('Desconto guardado.')]);
    }

    /* ─── Facturar ────────────────────────────────────────────────────── */

    /**
     * A ORDEM PASSA A FACTURA — pela porta fiscal partilhada.
     *
     * Toda a fiscalidade (imposto por linha, isenções, retenção de IRT,
     * descontos, totais SAFT, hash) vive no `ModuleInvoiceService`, a mesma que
     * a facturação usa. Aqui só se chama e se devolve a morada da factura.
     */
    public function facturar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.invoices.create');

        $ordem = $this->encontrar($id);

        try {
            $factura = $ordem->convertToInvoice();
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // A linha de «facturada» no histórico é do `WorkOrderObserver`, que a
        // escreve quando `invoice_id` muda — venha a factura de onde vier.
        return response()->json([
            'message' => __('Factura :numero emitida.', ['numero' => $factura->invoice_number]),
            'morada' => route('invoicing.sales.invoices.preview', $factura->id),
        ]);
    }

    /* ─── Anexos ──────────────────────────────────────────────────────── */

    public function anexar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');

        $ordem = $this->encontrar($id);

        $dados = $request->validate([
            'ficheiros' => ['required', 'array', 'max:10'],
            'ficheiros.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,heic,pdf,doc,docx,xls,xlsx,odt,ods,txt,csv'],
            'categoria' => ['required', Rule::in(array_keys(OrdensDeServico::CATEGORIAS_DE_ANEXO))],
            'descricao' => ['nullable', 'string', 'max:500'],
        ]);

        $quantos = 0;

        foreach ($dados['ficheiros'] as $ficheiro) {
            $nome = time() . '_' . uniqid() . '.' . $ficheiro->extension();
            $caminho = $ficheiro->storeAs("workshop/attachments/{$ordem->id}", $nome, 'public');

            WorkOrderAttachment::create([
                'work_order_id' => $ordem->id,
                'user_id' => auth()->id(),
                'filename' => $nome,
                'original_filename' => $ficheiro->getClientOriginalName(),
                'file_path' => $caminho,
                'file_type' => self::tipoDoFicheiro($ficheiro->getMimeType()),
                'file_size' => $ficheiro->getSize(),
                'mime_type' => $ficheiro->getMimeType(),
                'category' => $dados['categoria'],
                'description' => $dados['descricao'] ?? null,
            ]);

            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                __('Ficheiro anexado: :nome', ['nome' => $ficheiro->getClientOriginalName()]),
                ['categoria' => $dados['categoria'], 'tamanho' => $ficheiro->getSize()]);

            $quantos++;
        }

        return response()->json([
            'message' => __(':quantos ficheiro(s) anexado(s).', ['quantos' => $quantos]),
        ], 201);
    }

    public function apagarAnexo(Request $request, int $id, int $anexo): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');

        $ordem = $this->encontrar($id);

        // O anexo tem de ser DESTA ordem — o id vem do URL.
        $ficheiro = WorkOrderAttachment::where('work_order_id', $ordem->id)->findOrFail($anexo);

        WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
            __('Ficheiro removido: :nome', ['nome' => $ficheiro->original_filename]),
            ['categoria' => $ficheiro->category]);

        Storage::disk('public')->delete($ficheiro->file_path);
        $ficheiro->delete();

        return response()->json(['message' => __('Anexo removido.')]);
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    /**
     * A ORDEM DESTA EMPRESA, ou 404.
     *
     * O escopo global do modelo já o garante; isto é a segunda tranca e o
     * sítio onde a resposta fica clara em vez de vir uma consulta vazia.
     */
    private function encontrar(int $id): WorkOrder
    {
        return WorkOrder::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    /** @param class-string $modelo */
    private function daCasa(array $dados, string $campo, string $modelo): void
    {
        if (empty($dados[$campo])) {
            return;
        }

        abort_unless(
            $modelo::withoutGlobalScopes()->where('tenant_id', activeTenantId())->whereKey($dados[$campo])->exists(),
            422, __('Esse registo não é desta empresa.')
        );
    }

    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'vehicle_id' => ['required', 'integer'],
            'mechanic_id' => ['nullable', 'integer'],
            'received_at' => ['required', 'date'],
            'scheduled_for' => ['nullable', 'date'],
            'mileage_in' => ['nullable', 'integer', 'min:0'],
            'problem_description' => ['required', 'string', 'max:5000'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'work_performed' => ['nullable', 'string', 'max:5000'],
            'recommendations' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(array_keys(OrdensDeServico::ESTADOS))],
            'priority' => ['required', Rule::in(array_keys(OrdensDeServico::PRIORIDADES))],
            'warranty_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->daCasa($dados, 'vehicle_id', Vehicle::class);
        $this->daCasa($dados, 'mechanic_id', Mechanic::class);

        return $dados;
    }

    /**
     * AS FOLHAS DE OBRA DE UMA VIATURA, E A FACTURA DE CADA UMA.
     *
     * Pedido de 15/09/2026: na ficha da viatura, ver as ordens de serviço que
     * lhe foram abertas e as facturas que saíram delas — «que fizemos a este
     * carro, quanto se facturou, o que ainda falta receber». A ligação é a
     * `invoice_id` da ordem, a mesma que o `facturar` escreve.
     *
     * O que falta receber conta-se pela regra única das facturas
     * (`SomasDasFacturas::SEM_NADA_A_RECEBER` + FR paga no acto): um rascunho
     * ou uma factura anulada não devem nada.
     */
    public function daViatura(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.vehicles.view');

        $tenantId = activeTenantId();
        $viatura = Vehicle::with('client:id,name')->where('tenant_id', $tenantId)->findOrFail($id);
        $podeVerFacturas = (bool) $request->user()?->can('invoicing.sales.invoices.view');

        $ordens = WorkOrder::with(['mechanic:id,name', 'invoice'])
            ->where('tenant_id', $tenantId)
            ->where('vehicle_id', $viatura->id)
            ->orderByDesc('received_at')->orderByDesc('id')
            ->get();

        $linhas = $ordens->map(function (WorkOrder $o) use ($podeVerFacturas) {
            $f = $o->invoice;
            $falta = 0.0;

            if ($f && ($f->invoice_type ?? 'FT') !== 'FR' && ! in_array($f->status, \App\Services\Invoicing\SomasDasFacturas::SEM_NADA_A_RECEBER, true)) {
                $falta = max(0.0, round((float) $f->total - (float) $f->paid_amount, 2));
            }

            return [
                'id' => $o->id,
                'numero' => $o->order_number,
                'entrada' => $o->received_at?->toIso8601String(),
                'concluida' => $o->completed_at?->toIso8601String(),
                'estado' => $o->status,
                'estado_rotulo' => __(OrdensDeServico::ESTADOS[$o->status] ?? $o->status),
                'mecanico' => $o->mechanic?->name,
                'km' => (int) $o->mileage_in,
                'problema' => $o->problem_description ? mb_strimwidth($o->problem_description, 0, 160, '…') : null,
                'total' => round((float) $o->total, 2),
                'factura' => $f ? [
                    'id' => $f->id,
                    'numero' => $f->invoice_number,
                    'tipo' => $f->invoice_type ?? 'FT',
                    'data' => $f->invoice_date?->toDateString(),
                    'vencimento' => $f->due_date?->toDateString(),
                    'estado' => $f->status,
                    'estado_rotulo' => $f->status_label,
                    'total' => round((float) $f->total, 2),
                    'pago' => round((float) $f->paid_amount, 2),
                    'falta' => $falta,
                    'vencida' => $falta > 0 && $f->due_date && $f->due_date->lt(today()),
                    'preview' => $podeVerFacturas ? "/invoicing/sales/invoices/{$f->id}/preview" : null,
                    'pdf' => $podeVerFacturas ? "/invoicing/sales/invoices/{$f->id}/pdf" : null,
                ] : null,
            ];
        })->values();

        $facturas = $linhas->pluck('factura')->filter();

        return response()->json([
            'viatura' => [
                'id' => $viatura->id,
                'matricula' => $viatura->plate,
                'viatura' => trim("{$viatura->brand} {$viatura->model}"),
                'dono' => $viatura->owner_name,
                'cliente' => $viatura->client?->name,
                'km' => (int) $viatura->mileage,
            ],
            'resumo' => [
                'ordens' => $ordens->count(),
                'abertas' => $ordens->whereNotIn('status', ['completed', 'delivered', 'cancelled'])->count(),
                'facturas' => $facturas->count(),
                'facturado' => round((float) $facturas->where('estado', '!=', 'cancelled')->sum('total'), 2),
                'por_receber' => round((float) $facturas->sum('falta'), 2),
                'ultima_visita' => $ordens->first()?->received_at?->toIso8601String(),
                'fotos' => \App\Models\Workshop\VehiclePhoto::where('tenant_id', $tenantId)->where('vehicle_id', $viatura->id)->count(),
            ],
            'ordens' => $linhas,
            'pode_ver_facturas' => $podeVerFacturas,
        ]);
    }

    /** Uma ordem como a lista a mostra. */
    private function linha(WorkOrder $o): array
    {
        return [
            'id' => $o->id,
            'numero' => $o->order_number,
            'matricula' => $o->vehicle?->plate,
            'viatura' => $o->vehicle ? trim("{$o->vehicle->brand} {$o->vehicle->model}") : null,
            'dono' => $o->vehicle?->owner_name,
            'mecanico' => $o->mechanic?->name,
            'mechanic_id' => $o->mechanic_id,
            'vehicle_id' => $o->vehicle_id,
            'entrada' => $o->received_at?->toIso8601String(),
            'estado' => $o->status,
            'estado_rotulo' => __(OrdensDeServico::ESTADOS[$o->status] ?? $o->status),
            'prioridade' => $o->priority,
            'prioridade_rotulo' => __(OrdensDeServico::PRIORIDADES[$o->priority] ?? $o->priority),
            'km' => (int) $o->mileage_in,
            'mao_de_obra' => (float) $o->labor_total,
            'pecas' => (float) $o->parts_total,
            'desconto' => (float) $o->discount,
            'imposto' => (float) $o->tax,
            'total' => (float) $o->total,
            'pago' => (float) $o->paid_amount,
            'saldo' => (float) $o->balance_due,
            'estado_pagamento' => $o->payment_status,
            'facturada' => (bool) $o->invoice_id,
            // «Atrasada» é a data agendada já passada com a ordem por fechar —
            // é o que faz alguém pegar na lista.
            'atrasada' => (bool) $o->is_overdue,
            'dias_na_oficina' => (int) $o->days_in_service,
        ];
    }

    /** @return list<array{valor: string, rotulo: string}> */
    private static function escolhas(array $mapa): array
    {
        return collect($mapa)->map(fn ($r, $v) => ['valor' => (string) $v, 'rotulo' => __($r)])->values()->all();
    }

    private static function tipoDoFicheiro(?string $mime): string
    {
        return match (true) {
            str_starts_with((string) $mime, 'image/') => 'image',
            str_starts_with((string) $mime, 'video/') => 'video',
            $mime === 'application/pdf' => 'document',
            in_array($mime, ['application/zip', 'application/x-rar'], true) => 'archive',
            default => 'other',
        };
    }
}
