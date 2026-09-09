<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\MaintenanceOrder;
use App\Models\Hotel\Room;
use App\Models\Hotel\Staff;
use App\Services\Hotel\OrdensDeManutencao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AS ORDENS DE MANUTENÇÃO DO HOTEL.
 *
 * O ecrã tem duas vistas da mesma coisa — a LISTA e o QUADRO por estado — e é
 * de propósito: a lista serve para procurar uma ordem, o quadro serve para ver
 * onde está a fila. Ambas vêm daqui, para não haver duas contagens.
 *
 * O QUE ESTA MIGRAÇÃO CORRIGE, e é o principal: O ECRÃ NUNCA CONSEGUIU GRAVAR
 * UMA ORDEM. O modelo declarava colunas que a tabela não tem (`type`,
 * `estimated_cost`, `resolution_notes`, …) e o insert respondia «Unknown
 * column 'type'» — em qualquer ordem, sempre.
 */
class ManutencaoApiController extends Controller
{
    public function __construct(private readonly OrdensDeManutencao $manutencao) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    /** @return list<array{valor: string, rotulo: string}> */
    private static function escolhas(array $mapa): array
    {
        return collect($mapa)->map(fn ($r, $v) => ['valor' => (string) $v, 'rotulo' => __($r)])->values()->all();
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.maintenance.view');

        $tenantId = activeTenantId();

        return response()->json([
            'tipos' => self::escolhas(OrdensDeManutencao::TIPOS),
            'prioridades' => self::escolhas(OrdensDeManutencao::PRIORIDADES),
            'categorias' => self::escolhas(OrdensDeManutencao::CATEGORIAS),
            'estados' => self::escolhas(OrdensDeManutencao::ESTADOS),
            'colunas_do_quadro' => collect(OrdensDeManutencao::COLUNAS_DO_QUADRO)
                ->map(fn ($e) => ['valor' => $e, 'rotulo' => __(OrdensDeManutencao::ESTADOS[$e])])->all(),
            'quartos' => Room::where('tenant_id', $tenantId)->orderBy('number')
                ->get(['id', 'number', 'floor'])
                ->map(fn (Room $q) => [
                    'valor' => (string) $q->id,
                    'rotulo' => $q->floor ? __('Quarto :n (piso :p)', ['n' => $q->number, 'p' => $q->floor]) : $q->number,
                ])->values(),
            'pessoal' => Staff::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($p) => ['valor' => (string) $p->id, 'rotulo' => $p->name])->values(),
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('hotel.maintenance.create'),
                'pode_editar' => (bool) $request->user()?->can('hotel.maintenance.edit'),
                'pode_apagar' => (bool) $request->user()?->can('hotel.maintenance.delete'),
            ],
            /*
             * QUEM ESTÁ A VER TEM FICHA DE PESSOAL?
             *
             * O botão «atribuir-me» só faz alguma coisa a quem tem, e o ecrã
             * de sempre mostrava-o a toda a gente: quem não tinha carregava e
             * não acontecia nada.
             */
            'tenho_ficha' => Staff::where('tenant_id', $tenantId)
                ->where('user_id', $request->user()?->id)->exists(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.maintenance.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(array_keys(OrdensDeManutencao::ESTADOS))],
            'prioridade' => ['nullable', Rule::in(array_keys(OrdensDeManutencao::PRIORIDADES))],
            'categoria' => ['nullable', Rule::in(array_keys(OrdensDeManutencao::CATEGORIAS))],
            'quarto' => ['nullable', 'integer'],
            'responsavel' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenantId = activeTenantId();

        $consulta = MaintenanceOrder::where('tenant_id', $tenantId)
            ->with(['room:id,number,floor', 'assignee:id,name', 'reporter:id,name'])
            ->when($filtros['procura'] ?? null, fn ($q, $p) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$p}%")
                ->orWhere('order_number', 'like', "%{$p}%")
                ->orWhere('description', 'like', "%{$p}%")))
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->when($filtros['prioridade'] ?? null, fn ($q, $p) => $q->where('priority', $p))
            ->when($filtros['categoria'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($filtros['quarto'] ?? null, fn ($q, $r) => $q->where('room_id', $r))
            ->when($filtros['responsavel'] ?? null, fn ($q, $r) => $q->where('assigned_to', $r))
            // O URGENTE PRIMEIRO. Uma lista de manutenção por data de criação
            // põe a fuga de água de hoje abaixo da lâmpada de anteontem.
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderByDesc('created_at');

        $pagina = $consulta->paginate($filtros['por_pagina'] ?? 15)->withQueryString();

        $porEstado = MaintenanceOrder::where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as quantas')->groupBy('status')->pluck('quantas', 'status');

        return response()->json([
            'data' => collect($pagina->items())->map(fn (MaintenanceOrder $o) => $this->linha($o))->all(),
            'meta' => [
                'total' => $pagina->total(),
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'per_page' => $pagina->perPage(),
            ],
            // O RESUMO CONTA A CASA e não a página filtrada.
            'resumo' => [
                'total' => (int) $porEstado->sum(),
                'pendentes' => (int) ($porEstado['pending'] ?? 0),
                'em_curso' => (int) ($porEstado['in_progress'] ?? 0),
                'a_espera' => (int) ($porEstado['waiting_parts'] ?? 0),
                'concluidas' => (int) ($porEstado['completed'] ?? 0),
                'urgentes' => MaintenanceOrder::where('tenant_id', $tenantId)
                    ->where('priority', 'urgent')
                    ->whereNotIn('status', ['completed', 'cancelled'])->count(),
            ],
        ]);
    }

    /**
     * O QUADRO — as ordens por coluna de estado.
     *
     * As concluídas vêm limitadas às últimas: o quadro é para ver a fila, e
     * uma coluna com dois anos de arranjos feitos não é uma fila.
     */
    public function quadro(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.maintenance.view');

        $tenantId = activeTenantId();

        $colunas = [];

        foreach (OrdensDeManutencao::COLUNAS_DO_QUADRO as $estado) {
            $ordens = MaintenanceOrder::where('tenant_id', $tenantId)
                ->with(['room:id,number,floor', 'assignee:id,name'])
                ->where('status', $estado)
                ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
                ->when($estado === 'completed', fn ($q) => $q->orderByDesc('completed_at')->limit(20))
                ->get();

            $colunas[] = [
                'estado' => $estado,
                'rotulo' => __(OrdensDeManutencao::ESTADOS[$estado]),
                'quantas' => MaintenanceOrder::where('tenant_id', $tenantId)->where('status', $estado)->count(),
                'ordens' => $ordens->map(fn (MaintenanceOrder $o) => $this->linha($o))->all(),
            ];
        }

        return response()->json(['colunas' => $colunas]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.maintenance.create');

        $dados = $this->validar($request);
        $tenantId = activeTenantId();

        /*
         * QUEM ABRIU A ORDEM fica escrito — se tiver ficha de pessoal. É a
         * primeira pergunta de qualquer manutenção: quem é que reportou isto?
         */
        $dados['reported_by'] = Staff::where('tenant_id', $tenantId)
            ->where('user_id', $request->user()?->id)->value('id');

        $ordem = MaintenanceOrder::create($dados + ['tenant_id' => $tenantId, 'status' => 'pending']);

        return response()->json([
            'data' => $this->linha($ordem->fresh(['room', 'assignee', 'reporter'])),
            'message' => __('Ordem de manutenção aberta.'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.maintenance.edit');

        $ordem = $this->encontrar($id);
        $ordem->update($this->validar($request));

        return response()->json([
            'data' => $this->linha($ordem->fresh(['room', 'assignee', 'reporter'])),
            'message' => __('Ordem guardada.'),
        ]);
    }

    /**
     * MUDAR O ESTADO — e, ao concluir, escrever o que se fez.
     *
     * Passar a «em curso» marca a hora de início e concluir marca a de fim: é
     * daí que sai o tempo real do arranjo.
     */
    public function estado(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.maintenance.edit');

        $dados = $request->validate([
            'estado' => ['required', Rule::in(array_keys(OrdensDeManutencao::ESTADOS))],
            'resolucao' => ['nullable', 'string', 'max:5000'],
            'custo' => ['nullable', 'numeric', 'min:0'],
        ]);

        $ordem = $this->encontrar($id);

        $mensagem = $this->manutencao->aplicarEstado($ordem, $dados['estado'], $dados);

        return response()->json([
            'data' => $this->linha($ordem->fresh(['room', 'assignee', 'reporter'])),
            'message' => $mensagem,
        ]);
    }

    public function atribuirMe(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.maintenance.edit');

        $ordem = $this->encontrar($id);

        $nome = $this->manutencao->atribuirA($ordem, $request->user()?->id, activeTenantId());

        if ($nome === null) {
            return response()->json([
                'message' => __('A sua conta não tem ficha de pessoal do hotel.'),
            ], 422);
        }

        return response()->json([
            'data' => $this->linha($ordem->fresh(['room', 'assignee', 'reporter'])),
            'message' => __('Ordem atribuída a :nome.', ['nome' => $nome]),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.maintenance.delete');

        $this->encontrar($id)->delete();

        return response()->json(['message' => __('Ordem apagada.')]);
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function encontrar(int $id): MaintenanceOrder
    {
        return MaintenanceOrder::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'type' => ['required', Rule::in(array_keys(OrdensDeManutencao::TIPOS))],
            'priority' => ['required', Rule::in(array_keys(OrdensDeManutencao::PRIORIDADES))],
            'category' => ['required', Rule::in(array_keys(OrdensDeManutencao::CATEGORIAS))],
            'room_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'estimated_time' => ['nullable', 'integer', 'min:0'],
            'scheduled_date' => ['nullable', 'date'],
        ]);

        // Os ids vêm do pedido e não provam nada.
        foreach ([['room_id', Room::class], ['assigned_to', Staff::class]] as [$campo, $modelo]) {
            if (! empty($dados[$campo])) {
                abort_unless(
                    $modelo::withoutGlobalScopes()->where('tenant_id', activeTenantId())->whereKey($dados[$campo])->exists(),
                    422, __('Esse registo não é desta empresa.')
                );
            }
        }

        foreach (['room_id', 'assigned_to', 'scheduled_date'] as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dados[$campo] = $dados[$campo] ?: null;
            }
        }

        foreach (['estimated_cost', 'estimated_time'] as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dados[$campo] = ($dados[$campo] ?? '') === '' ? null : $dados[$campo];
            }
        }

        return $dados;
    }

    private function linha(MaintenanceOrder $o): array
    {
        return [
            'id' => $o->id,
            'numero' => $o->order_number,
            'titulo' => $o->title,
            'descricao' => $o->description,
            'local' => $o->location,
            'quarto' => $o->room?->number,
            'room_id' => $o->room_id,
            'responsavel' => $o->assignee?->name,
            'assigned_to' => $o->assigned_to,
            'quem_reportou' => $o->reporter?->name,
            'tipo' => $o->type,
            'tipo_rotulo' => __(OrdensDeManutencao::TIPOS[$o->type] ?? (string) $o->type),
            'prioridade' => $o->priority,
            'prioridade_rotulo' => __(OrdensDeManutencao::PRIORIDADES[$o->priority] ?? (string) $o->priority),
            'categoria' => $o->category,
            'categoria_rotulo' => __(OrdensDeManutencao::CATEGORIAS[$o->category] ?? (string) $o->category),
            'estado' => $o->status,
            'estado_rotulo' => __(OrdensDeManutencao::ESTADOS[$o->status] ?? (string) $o->status),
            'agendada' => $o->scheduled_date?->toDateString(),
            'iniciada' => $o->started_at?->toIso8601String(),
            'concluida' => $o->completed_at?->toIso8601String(),
            'custo_previsto' => $o->estimated_cost === null ? null : (float) $o->estimated_cost,
            'tempo_previsto' => $o->estimated_time,
            'custo' => $o->cost === null ? null : (float) $o->cost,
            // O TEMPO REAL sai das duas datas, e não de uma coluna à parte que
            // podia discordar delas.
            'minutos_gastos' => OrdensDeManutencao::minutosGastos($o),
            'resolucao' => $o->resolution,
            'aberta_em' => $o->created_at?->toIso8601String(),
        ];
    }
}
