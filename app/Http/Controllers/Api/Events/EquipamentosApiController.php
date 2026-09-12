<?php

namespace App\Http\Controllers\Api\Events;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentHistory;
use App\Models\EquipmentSet;
use App\Models\Events\Technician;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OS EQUIPAMENTOS DOS EVENTOS — o que há, onde está, e com quem.
 *
 * O DEFEITO QUE ISTO FECHA, e era um erro 500 em produção: gravar um
 * equipamento COM NÚMERO DE SÉRIE rebentava. A regra dizia
 * `unique:equipment,serial_number` e a tabela chama-se
 * `events_equipments_manager` — a consulta batia numa tabela que não existe e
 * respondia «Base table or view not found». Quem escrevesse o número de série
 * — que é toda a gente que tem equipamento caro — não conseguia gravar.
 *
 * E O NÚMERO DE SÉRIE É ÚNICO POR EMPRESA, e não no mundo: duas casas podem ter
 * o mesmo equipamento, e a regra global impedia a segunda de o registar.
 */
class EquipamentosApiController extends Controller
{
    /** Os estados de um equipamento, e o que cada um quer dizer. */
    public const ESTADOS = [
        'disponivel' => 'Disponível',
        'reservado' => 'Reservado',
        'em_uso' => 'Em uso',
        'emprestado' => 'Emprestado',
        'manutencao' => 'Em manutenção',
        'avariado' => 'Avariado',
        'descartado' => 'Abatido',
    ];

    public const ICONE_DO_ESTADO = [
        'disponivel' => 'fa-circle-check',
        'reservado' => 'fa-bookmark',
        'em_uso' => 'fa-play',
        'emprestado' => 'fa-hand-holding',
        'manutencao' => 'fa-screwdriver-wrench',
        'avariado' => 'fa-triangle-exclamation',
        'descartado' => 'fa-ban',
    ];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    /* ─── As escolhas ──────────────────────────────────────────────────── */

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'events.equipment.view');

        return response()->json([
            'categorias' => $this->categorias(),
            'estados' => collect(self::ESTADOS)->map(fn ($r, $v) => [
                'valor' => $v, 'rotulo' => __($r), 'icone' => self::ICONE_DO_ESTADO[$v] ?? 'fa-circle',
            ])->values(),
            'clientes' => Client::where('tenant_id', activeTenantId())->where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])->map(fn ($c) => ['valor' => (string) $c->id, 'rotulo' => $c->name])->values(),
            'tecnicos' => Technician::forTenant()->where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])->map(fn ($t) => ['valor' => (string) $t->id, 'rotulo' => $t->name])->values(),
            'locais' => Equipment::forTenant()->whereNotNull('location')
                ->distinct()->orderBy('location')->pluck('location')->values(),
            'emojis' => AgendaApiController::EMOJIS,
            'permissoes' => [
                'pode_gerir' => (bool) $request->user()?->can('events.equipment.manage'),
            ],
        ]);
    }

    private function categorias(): array
    {
        $contagens = Equipment::forTenant()
            ->selectRaw('category_id, COUNT(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        return EquipmentCategory::forTenant()->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (EquipmentCategory $c) => [
                'id' => $c->id,
                'nome' => $c->name,
                'icone' => $c->icon ?: '📦',
                'cor' => $c->color ?: '#6366f1',
                'ordem' => (int) $c->sort_order,
                'activa' => (bool) $c->is_active,
                'equipamentos' => (int) ($contagens[$c->id] ?? 0),
            ])->values()->all();
    }

    /* ─── A lista ──────────────────────────────────────────────────────── */

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'events.equipment.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'categoria' => ['nullable', 'integer'],
            'estado' => ['nullable', 'string'],
            'local' => ['nullable', 'string', 'max:255'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $lista = Equipment::forTenant()
            ->when($filtros['procura'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$t}%")
                ->orWhere('serial_number', 'like', "%{$t}%")
                ->orWhere('location', 'like', "%{$t}%")))
            ->when($filtros['categoria'] ?? null, fn ($q, $c) => $q->where('category_id', $c))
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->when($filtros['local'] ?? null, fn ($q, $l) => $q->where('location', 'like', "%{$l}%"))
            ->with(['category:id,name,icon,color', 'borrowedToClient:id,name', 'borrowedToTechnician:id,name'])
            ->orderBy('name')
            ->paginate($filtros['por_pagina'] ?? 12);

        return response()->json([
            'data' => collect($lista->items())->map(fn (Equipment $e) => $this->linha($e))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => $this->resumo(),
            'categorias' => $this->categorias(),
            'avisos' => $this->avisos(),
        ]);
    }

    private function linha(Equipment $e): array
    {
        $atrasado = $e->status === 'emprestado'
            && $e->return_due_date
            && ! $e->actual_return_date
            && $e->return_due_date->lt(today());

        return [
            'id' => $e->id,
            'nome' => $e->name,
            'category_id' => $e->category_id,
            'categoria' => $e->category?->name,
            'categoria_icone' => $e->category?->icon,
            'categoria_cor' => $e->category?->color,
            'numero_de_serie' => $e->serial_number,
            'local' => $e->location,
            'descricao' => $e->description,
            'estado' => $e->status,
            'estado_rotulo' => __(self::ESTADOS[$e->status] ?? $e->status),
            'estado_icone' => self::ICONE_DO_ESTADO[$e->status] ?? 'fa-circle',
            'aquisicao' => $e->acquisition_date?->format('Y-m-d'),
            'preco_de_compra' => (float) $e->purchase_price,
            'valor_actual' => (float) $e->current_value,
            'emprestado_a' => $e->borrowedToClient?->name ?? $e->borrowedToTechnician?->name,
            'borrowed_to_client_id' => $e->borrowed_to_client_id,
            'borrowed_to_technician_id' => $e->borrowed_to_technician_id,
            'emprestado_em' => $e->borrow_date?->format('Y-m-d'),
            'devolver_em' => $e->return_due_date?->format('Y-m-d'),
            'devolvido_em' => $e->actual_return_date?->format('Y-m-d'),
            'preco_por_dia' => (float) $e->rental_price_per_day,
            'ultima_manutencao' => $e->last_maintenance_date?->format('Y-m-d'),
            'proxima_manutencao' => $e->next_maintenance_date?->format('Y-m-d'),
            'notas_de_manutencao' => $e->maintenance_notes,
            'utilizacoes' => (int) $e->total_uses,
            'horas' => (int) $e->total_hours_used,
            'imagem' => $e->image_path ? asset('storage/'.$e->image_path) : null,
            'activo' => (bool) $e->is_active,
            'atrasado' => $atrasado,
            'dias_de_atraso' => $atrasado ? (int) today()->diffInDays($e->return_due_date) : 0,
        ];
    }

    private function resumo(): array
    {
        $contagens = Equipment::forTenant()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) $contagens->sum();

        return [
            'total' => $total,
            'disponivel' => (int) ($contagens['disponivel'] ?? 0),
            'em_uso' => (int) ($contagens['em_uso'] ?? 0),
            'emprestado' => (int) ($contagens['emprestado'] ?? 0),
            'manutencao' => (int) ($contagens['manutencao'] ?? 0),
            'avariado' => (int) ($contagens['avariado'] ?? 0),
            'valor' => (float) Equipment::forTenant()->sum('current_value'),
            // Quanto do parque está parado à espera de alguém: é o número que
            // diz se vale a pena comprar mais um.
            'taxa_de_uso' => $total > 0
                ? round(((($contagens['em_uso'] ?? 0) + ($contagens['emprestado'] ?? 0)) / $total) * 100, 1)
                : 0.0,
        ];
    }

    /** Os avisos: o que está atrasado e o que precisa de manutenção. */
    private function avisos(): array
    {
        $avisos = [];

        $atrasados = Equipment::forTenant()
            ->where('status', 'emprestado')
            ->whereNotNull('return_due_date')->whereNull('actual_return_date')
            ->whereDate('return_due_date', '<', today())
            ->get(['id', 'name', 'return_due_date']);

        foreach ($atrasados as $e) {
            $avisos[] = [
                'tipo' => 'perigo',
                'icone' => 'fa-clock',
                'equipamento_id' => $e->id,
                'mensagem' => __(':nome está :dias dias atrasado.', [
                    'nome' => $e->name, 'dias' => (int) today()->diffInDays($e->return_due_date),
                ]),
            ];
        }

        $manutencao = Equipment::forTenant()
            ->whereNotNull('next_maintenance_date')
            ->whereDate('next_maintenance_date', '<=', today()->addDays(15))
            ->get(['id', 'name', 'next_maintenance_date']);

        foreach ($manutencao as $e) {
            $avisos[] = [
                'tipo' => 'aviso',
                'icone' => 'fa-screwdriver-wrench',
                'equipamento_id' => $e->id,
                'mensagem' => __(':nome tem manutenção marcada para :dia.', [
                    'nome' => $e->name, 'dia' => $e->next_maintenance_date->format('d/m/Y'),
                ]),
            ];
        }

        return $avisos;
    }

    /* ─── Gravar ───────────────────────────────────────────────────────── */

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'events.equipment.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'category_id' => ['required', Rule::exists('events_equipment_categories', 'id')->where('tenant_id', $tenantId)],
            /*
             * A TABELA CERTA, E POR EMPRESA.
             *
             * Era `unique:equipment,serial_number` — uma tabela que não existe,
             * e a gravação morria em SQL. É `events_equipments_manager`, e o
             * número de série é único DENTRO da empresa: duas casas podem ter o
             * mesmo aparelho.
             */
            'serial_number' => ['nullable', 'string', 'max:120',
                Rule::unique('events_equipments_manager', 'serial_number')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at'))
                    ->ignore($id),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_keys(self::ESTADOS))],
            'acquisition_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'current_value' => ['nullable', 'numeric', 'min:0'],
            'last_maintenance_date' => ['nullable', 'date'],
            'next_maintenance_date' => ['nullable', 'date'],
            'maintenance_notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ], [
            'serial_number.unique' => __('Já existe um equipamento com este número de série.'),
        ], [
            'name' => __('nome'), 'category_id' => __('categoria'),
            'serial_number' => __('número de série'), 'status' => __('estado'),
        ]);

        $valores = [
            'name' => $dados['name'],
            'category_id' => $dados['category_id'],
            'serial_number' => ($dados['serial_number'] ?? '') ?: null,
            'location' => ($dados['location'] ?? '') ?: null,
            'description' => ($dados['description'] ?? '') ?: null,
            'status' => $dados['status'],
            'acquisition_date' => $dados['acquisition_date'] ?? null,
            'purchase_price' => $dados['purchase_price'] ?? null,
            'current_value' => $dados['current_value'] ?? null,
            'last_maintenance_date' => $dados['last_maintenance_date'] ?? null,
            'next_maintenance_date' => $dados['next_maintenance_date'] ?? null,
            'maintenance_notes' => ($dados['maintenance_notes'] ?? '') ?: null,
            'is_active' => (bool) ($dados['is_active'] ?? true),
        ];

        if ($id) {
            $equipamento = Equipment::forTenant()->findOrFail($id);
            $equipamento->update($valores + ['updated_by' => $request->user()?->id]);
        } else {
            $equipamento = Equipment::create($valores + [
                'tenant_id' => $tenantId,
                'created_by' => $request->user()?->id,
            ]);
        }

        return response()->json([
            'message' => $id ? __('Equipamento actualizado.') : __('Equipamento criado.'),
            'data' => $this->linha($equipamento->fresh(['category'])),
        ], $id ? 200 : 201);
    }

    /** A fotografia do equipamento — a única forma de o identificar no armazém. */
    public function imagem(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.equipment.manage');

        $request->validate(['imagem' => ['required', 'image', 'max:5120']]);

        $equipamento = Equipment::forTenant()->findOrFail($id);

        $equipamento->update([
            'image_path' => $request->file('imagem')->store('equipment', 'public'),
        ]);

        return response()->json([
            'message' => __('Fotografia guardada.'),
            'imagem' => asset('storage/'.$equipamento->fresh()->image_path),
        ]);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.equipment.manage');

        $equipamento = Equipment::forTenant()->findOrFail($id);

        /*
         * O QUE ESTÁ FORA DE CASA NÃO SE APAGA.
         *
         * Apagar a ficha de um equipamento emprestado era perder o rasto de
         * quem o tem: ficava sem dono, sem data de devolução e sem aparecer nos
         * atrasos. Devolva-se primeiro.
         */
        if (in_array($equipamento->status, ['emprestado', 'em_uso'], true)) {
            $this->recusa(__('Este equipamento está fora de casa. Dê-o por devolvido antes de o apagar.'));
        }

        $equipamento->delete();

        return response()->json(['message' => __('Equipamento removido.')]);
    }

    /* ─── O empréstimo ─────────────────────────────────────────────────── */

    public function emprestar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.equipment.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'para' => ['required', Rule::in(['cliente', 'tecnico'])],
            'borrowed_to_client_id' => ['required_if:para,cliente', 'nullable',
                Rule::exists('invoicing_clients', 'id')->where('tenant_id', $tenantId)],
            'borrowed_to_technician_id' => ['required_if:para,tecnico', 'nullable',
                Rule::exists('events_technicians', 'id')->where('tenant_id', $tenantId)],
            'borrow_date' => ['required', 'date'],
            'return_due_date' => ['required', 'date', 'after:borrow_date'],
            'rental_price_per_day' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'return_due_date.after' => __('A data de devolução tem de ser depois da de saída.'),
            'borrowed_to_client_id.required_if' => __('Escolha o cliente.'),
            'borrowed_to_technician_id.required_if' => __('Escolha o técnico.'),
        ]);

        $equipamento = Equipment::forTenant()->findOrFail($id);

        /*
         * O MESMO APARELHO NÃO SAI DUAS VEZES.
         *
         * Emprestava-se um equipamento que já estava emprestado e o registo
         * anterior era simplesmente reescrito: o primeiro cliente desaparecia
         * da ficha e ninguém sabia com quem estava afinal.
         */
        if (in_array($equipamento->status, ['emprestado', 'em_uso'], true)) {
            $this->recusa(__('Este equipamento já está fora de casa.'));
        }

        if (in_array($equipamento->status, ['manutencao', 'avariado', 'descartado'], true)) {
            $this->recusa(__('Um equipamento :estado não se empresta.', [
                'estado' => __(self::ESTADOS[$equipamento->status]),
            ]));
        }

        $aCliente = $dados['para'] === 'cliente';

        $equipamento->update([
            'status' => 'emprestado',
            'borrow_date' => $dados['borrow_date'],
            'return_due_date' => $dados['return_due_date'],
            'actual_return_date' => null,
            'borrowed_to_client_id' => $aCliente ? $dados['borrowed_to_client_id'] : null,
            'borrowed_to_technician_id' => $aCliente ? null : $dados['borrowed_to_technician_id'],
            // Um técnico da casa não paga aluguer do material da casa.
            'rental_price_per_day' => $aCliente ? ($dados['rental_price_per_day'] ?? null) : null,
        ]);

        /*
         * O TÉCNICO VAI NAS NOTAS, e não numa coluna.
         *
         * `events_equipment_history` tem `client_id` e mais nenhum destinatário
         * — o ecrã antigo passava um `technician_id` que a atribuição em massa
         * deitava fora em silêncio, e o historial de um empréstimo a um técnico
         * ficava sem dizer a quem. Até haver coluna, fica escrito.
         */
        $paraQuem = $aCliente
            ? null
            : Technician::forTenant()->find($dados['borrowed_to_technician_id'])?->name;

        $equipamento->addToHistory('emprestimo', array_filter([
            'client_id' => $aCliente ? $dados['borrowed_to_client_id'] : null,
            'start_datetime' => $dados['borrow_date'],
            'notes' => $dados['notes']
                ?? ($paraQuem
                    ? __('Emprestado ao técnico :nome', ['nome' => $paraQuem])
                    : __('Equipamento emprestado')),
        ]));

        return response()->json([
            'message' => __('Equipamento emprestado.'),
            'data' => $this->linha($equipamento->fresh(['category', 'borrowedToClient', 'borrowedToTechnician'])),
        ]);
    }

    public function devolver(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.equipment.manage');

        $equipamento = Equipment::forTenant()->findOrFail($id);

        if ($equipamento->status !== 'emprestado') {
            $this->recusa(__('Este equipamento não está emprestado.'));
        }

        $deQuem = $equipamento->borrowed_to_client_id;

        $equipamento->update([
            'status' => 'disponivel',
            'actual_return_date' => now(),
            'borrowed_to_client_id' => null,
            'borrowed_to_technician_id' => null,
            // Cada saída conta uma utilização: é o que alimenta o «mais usados».
            'total_uses' => (int) $equipamento->total_uses + 1,
        ]);

        $equipamento->addToHistory('devolucao', array_filter([
            'client_id' => $deQuem,
            'end_datetime' => now(),
            'notes' => __('Equipamento devolvido'),
        ]));

        return response()->json([
            'message' => __('Equipamento devolvido.'),
            'data' => $this->linha($equipamento->fresh(['category'])),
        ]);
    }

    /** A manutenção: marca a data da próxima e o que se fez na desta. */
    public function manutencao(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.equipment.manage');

        $dados = $request->validate([
            'maintenance_notes' => ['nullable', 'string', 'max:2000'],
            'next_maintenance_date' => ['nullable', 'date', 'after_or_equal:today'],
            'terminada' => ['boolean'],
        ], ['next_maintenance_date.after_or_equal' => __('A próxima manutenção é no futuro.')]);

        $equipamento = Equipment::forTenant()->findOrFail($id);

        $terminada = (bool) ($dados['terminada'] ?? false);

        $equipamento->update([
            'status' => $terminada ? 'disponivel' : 'manutencao',
            'maintenance_notes' => $dados['maintenance_notes'] ?? $equipamento->maintenance_notes,
            'next_maintenance_date' => $dados['next_maintenance_date'] ?? $equipamento->next_maintenance_date,
            'last_maintenance_date' => $terminada ? today() : $equipamento->last_maintenance_date,
        ]);

        $equipamento->addToHistory('manutencao', [
            'notes' => $dados['maintenance_notes'] ?? ($terminada ? __('Manutenção concluída') : __('Entrou em manutenção')),
        ]);

        return response()->json([
            'message' => $terminada ? __('Manutenção concluída.') : __('Equipamento em manutenção.'),
            'data' => $this->linha($equipamento->fresh(['category'])),
        ]);
    }

    /** O historial de um equipamento — por onde andou e com quem. */
    public function historial(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.equipment.view');

        $equipamento = Equipment::forTenant()->findOrFail($id);

        return response()->json([
            'data' => EquipmentHistory::where('tenant_id', activeTenantId())
                ->where('equipment_id', $equipamento->id)
                ->with(['client:id,name', 'user:id,name', 'event:id,name,event_number'])
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn (EquipmentHistory $h) => [
                    'id' => $h->id,
                    'accao' => $h->action_type,
                    'quando' => $h->created_at?->format('Y-m-d H:i'),
                    'de' => $h->start_datetime?->format('Y-m-d H:i'),
                    'ate' => $h->end_datetime?->format('Y-m-d H:i'),
                    'cliente' => $h->client?->name,
                    'utilizador' => $h->user?->name,
                    'evento' => $h->event?->name,
                    'notas' => $h->notes,
                ])->values(),
        ]);
    }

    /* ─── As categorias ────────────────────────────────────────────────── */

    public function guardarCategoria(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'events.equipment.manage');

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'icon' => ['nullable', 'string', 'max:10'],
            'color' => ['required', 'string', 'max:7'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [], ['name' => __('nome'), 'color' => __('cor')]);

        $valores = [
            'name' => $dados['name'],
            'icon' => ($dados['icon'] ?? '') ?: '📦',
            'color' => $dados['color'],
            'sort_order' => $dados['sort_order'] ?? ((int) EquipmentCategory::forTenant()->max('sort_order') + 1),
            'is_active' => (bool) ($dados['is_active'] ?? true),
        ];

        $categoria = $id
            ? tap(EquipmentCategory::forTenant()->findOrFail($id))->update($valores)
            : EquipmentCategory::create($valores + ['tenant_id' => activeTenantId()]);

        return response()->json([
            'message' => $id ? __('Categoria actualizada.') : __('Categoria criada.'),
            'data' => [
                'valor' => (string) $categoria->id, 'rotulo' => $categoria->name,
                'icone' => $categoria->icon, 'cor' => $categoria->color,
            ],
        ], $id ? 200 : 201);
    }

    public function alternarCategoria(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.equipment.manage');

        $categoria = EquipmentCategory::forTenant()->findOrFail($id);
        $categoria->update(['is_active' => ! $categoria->is_active]);

        return response()->json([
            'message' => $categoria->is_active ? __('Categoria activa.') : __('Categoria desligada.'),
            'activa' => (bool) $categoria->is_active,
        ]);
    }

    public function apagarCategoria(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.equipment.manage');

        $categoria = EquipmentCategory::forTenant()->findOrFail($id);

        if (Equipment::forTenant()->where('category_id', $categoria->id)->exists()) {
            $this->recusa(__('Esta categoria tem equipamentos. Mova-os primeiro.'));
        }

        $categoria->delete();

        return response()->json(['message' => __('Categoria removida.')]);
    }

    /* ─── Os conjuntos ─────────────────────────────────────────────────── */

    /**
     * UM CONJUNTO é o material que sai sempre junto — a mesa de som com os
     * cabos e os microfones. Serve para não se montar a lista peça a peça de
     * cada vez, e para não se esquecer a peça pequena que faz falta no sítio.
     */
    public function conjuntos(Request $request): JsonResponse
    {
        $this->exigir($request, 'events.equipment.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $lista = EquipmentSet::forTenant()
            ->when($filtros['procura'] ?? null, fn ($q, $t) => $q->where('name', 'like', "%{$t}%"))
            ->with(['category:id,name,icon,color', 'equipments:id,name,status'])
            ->orderBy('name')
            ->paginate($filtros['por_pagina'] ?? 12);

        return response()->json([
            'data' => collect($lista->items())->map(fn (EquipmentSet $c) => [
                'id' => $c->id,
                'nome' => $c->name,
                'descricao' => $c->description,
                'category_id' => $c->category_id,
                'categoria' => $c->category?->name,
                'categoria_icone' => $c->category?->icon,
                'categoria_cor' => $c->category?->color,
                'activo' => (bool) $c->is_active,
                'itens' => $c->equipments->map(fn ($e) => [
                    'id' => $e->id,
                    'nome' => $e->name,
                    'estado' => $e->status,
                    'estado_rotulo' => __(self::ESTADOS[$e->status] ?? $e->status),
                    'quantidade' => (int) ($e->pivot->quantity ?? 1),
                ])->values(),
            ])->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'equipamentos' => Equipment::forTenant()->where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])->map(fn ($e) => ['valor' => (string) $e->id, 'rotulo' => $e->name])->values(),
        ]);
    }

    public function guardarConjunto(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'events.equipment.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['required', Rule::exists('events_equipment_categories', 'id')->where('tenant_id', $tenantId)],
            'is_active' => ['boolean'],
        ], [], ['name' => __('nome'), 'category_id' => __('categoria')]);

        $valores = [
            'name' => $dados['name'],
            'description' => ($dados['description'] ?? '') ?: null,
            'category_id' => $dados['category_id'],
            'is_active' => (bool) ($dados['is_active'] ?? true),
        ];

        $conjunto = $id
            ? tap(EquipmentSet::forTenant()->findOrFail($id))->update($valores + ['updated_by' => $request->user()?->id])
            : EquipmentSet::create($valores + ['tenant_id' => $tenantId, 'created_by' => $request->user()?->id]);

        return response()->json([
            'message' => $id ? __('Conjunto actualizado.') : __('Conjunto criado.'),
            'data' => ['id' => $conjunto->id, 'nome' => $conjunto->name],
        ], $id ? 200 : 201);
    }

    public function juntarAoConjunto(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.equipment.manage');

        $dados = $request->validate([
            'equipment_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ], [], ['equipment_id' => __('equipamento'), 'quantity' => __('quantidade')]);

        $conjunto = EquipmentSet::forTenant()->findOrFail($id);

        /*
         * O EQUIPAMENTO TEM DE SER DESTA EMPRESA.
         *
         * O `syncWithoutDetaching` aceita qualquer id, e um número escrito à
         * mão no pedido punha o material da casa do lado dentro de um conjunto
         * desta — que depois saía na lista de carga da montagem.
         */
        $meu = Equipment::forTenant()->find($dados['equipment_id']);

        if (! $meu) {
            $this->recusa(__('Esse equipamento não é desta empresa.'));
        }

        $conjunto->equipments()->syncWithoutDetaching([
            $meu->id => ['quantity' => $dados['quantity']],
        ]);

        return response()->json(['message' => __('Equipamento juntado ao conjunto.')]);
    }

    public function tirarDoConjunto(Request $request, int $id, int $equipamento): JsonResponse
    {
        $this->exigir($request, 'events.equipment.manage');

        EquipmentSet::forTenant()->findOrFail($id)->equipments()->detach($equipamento);

        return response()->json(['message' => __('Equipamento retirado do conjunto.')]);
    }

    public function apagarConjunto(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.equipment.manage');

        EquipmentSet::forTenant()->findOrFail($id)->delete();

        return response()->json(['message' => __('Conjunto removido.')]);
    }

    /* ─── O painel dos equipamentos ────────────────────────────────────── */

    public function painel(Request $request): JsonResponse
    {
        $this->exigir($request, 'events.equipment.view');

        /*
         * O PARÊNTESIS NÃO É DECORAÇÃO.
         *
         * `(int) $a['dias'] ?? 30` lê-se `((int) $a['dias']) ?? 30`: o acesso
         * ao array acontece PRIMEIRO e, sem a chave, avisa «Undefined array
         * key» antes de o `??` chegar a ser considerado.
         */
        $dias = (int) ($request->validate([
            'dias' => ['nullable', 'integer', 'min:7', 'max:365'],
        ])['dias'] ?? 30);

        $desde = today()->subDays($dias - 1);

        $porCategoria = Equipment::forTenant()
            ->leftJoin('events_equipment_categories as c', 'c.id', '=', 'events_equipments_manager.category_id')
            ->groupBy('c.id', 'c.name')
            ->selectRaw('COALESCE(c.name, "—") as nome, SUM(events_equipments_manager.total_uses) as total')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $movimentos = EquipmentHistory::where('tenant_id', activeTenantId())
            ->where('created_at', '>=', $desde)
            ->selectRaw('DATE(created_at) as dia, COUNT(*) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $etiquetas = [];
        $valores = [];

        for ($d = 0; $d < $dias; $d++) {
            $data = $desde->copy()->addDays($d);

            $etiquetas[] = $data->format('d/m');
            $valores[] = (int) ($movimentos[$data->format('Y-m-d')] ?? 0);
        }

        return response()->json([
            'dias' => $dias,
            'resumo' => $this->resumo(),
            'por_categoria' => [
                'etiquetas' => $porCategoria->pluck('nome')->all(),
                'valores' => $porCategoria->map(fn ($l) => (int) $l->total)->all(),
            ],
            'movimentos' => ['etiquetas' => $etiquetas, 'valores' => $valores],
            'mais_usados' => Equipment::forTenant()
                ->orderByDesc('total_uses')->limit(10)
                ->get(['id', 'name', 'total_uses', 'status', 'current_value'])
                ->map(fn (Equipment $e) => [
                    'id' => $e->id, 'nome' => $e->name,
                    'utilizacoes' => (int) $e->total_uses,
                    'estado' => $e->status,
                    'estado_rotulo' => __(self::ESTADOS[$e->status] ?? $e->status),
                    'valor' => (float) $e->current_value,
                ])->values(),
            'manutencoes' => Equipment::forTenant()
                ->whereNotNull('next_maintenance_date')
                ->orderBy('next_maintenance_date')->limit(10)
                ->get(['id', 'name', 'next_maintenance_date', 'status'])
                ->map(fn (Equipment $e) => [
                    'id' => $e->id, 'nome' => $e->name,
                    'quando' => $e->next_maintenance_date?->format('Y-m-d'),
                    'atrasada' => $e->next_maintenance_date?->lt(today()) ?? false,
                ])->values(),
            'avisos' => $this->avisos(),
            'actividade' => EquipmentHistory::where('tenant_id', activeTenantId())
                ->with(['equipment:id,name', 'user:id,name', 'client:id,name'])
                ->orderByDesc('created_at')->limit(20)->get()
                ->map(fn (EquipmentHistory $h) => [
                    'id' => $h->id,
                    'equipamento' => $h->equipment?->name,
                    'accao' => $h->action_type,
                    'quem' => $h->user?->name,
                    'cliente' => $h->client?->name,
                    'quando' => $h->created_at?->format('Y-m-d H:i'),
                    'notas' => $h->notes,
                ])->values(),
        ]);
    }
}
