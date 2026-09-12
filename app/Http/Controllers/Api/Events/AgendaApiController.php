<?php

namespace App\Http\Controllers\Api\Events;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Events\Checklist;
use App\Models\Events\Event;
use App\Models\Events\EventType;
use App\Models\Events\Venue;
use App\Services\Events\Eventos;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A AGENDA DOS EVENTOS — a lista e o calendário, no mesmo ecrã.
 *
 * O CALENDÁRIO DEIXOU DE SER UMA BIBLIOTECA DE FORA. O ecrã antigo puxava o
 * FullCalendar de um CDN: numa instalação sem internet — que é a versão
 * on-premise — a página ficava um quadrado branco sem aviso nenhum, e mesmo com
 * rede o calendário só aparecia depois de a biblioteca descer. A grelha do mês
 * é agora desenhada aqui, e o mês vem do servidor já com as semanas inteiras.
 *
 * E O ESTADO PASSOU A TER REGRAS. Ver `App\Services\Events\Eventos`.
 */
class AgendaApiController extends Controller
{
    public function __construct(private readonly Eventos $eventos) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    /* ─── As escolhas do ecrã ──────────────────────────────────────────── */

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'events.calendar.view');

        return response()->json([
            'clientes' => Client::where('tenant_id', activeTenantId())->where('is_active', true)
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['valor' => (string) $c->id, 'rotulo' => $c->name])->values(),
            'locais' => Venue::forTenant()->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'capacity'])
                ->map(fn ($v) => [
                    'valor' => (string) $v->id, 'rotulo' => $v->name, 'capacidade' => (int) $v->capacity,
                ])->values(),
            'tipos' => EventType::forTenant()->where('is_active', true)
                ->orderBy('order')->orderBy('name')->get(['id', 'name', 'icon', 'color'])
                ->map(fn ($t) => [
                    'valor' => (string) $t->id, 'rotulo' => $t->name,
                    'icone' => $t->icon, 'cor' => $t->color,
                ])->values(),
            'estados' => collect(Eventos::ESTADOS)
                ->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r), 'icone' => Eventos::ICONE_DO_ESTADO[$v] ?? 'fa-circle'])
                ->values(),
            'fases' => collect(Eventos::FASES)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'emojis' => self::EMOJIS,
            'permissoes' => [
                'pode_gerir' => (bool) $request->user()?->can('events.calendar.manage'),
                'pode_criar_cliente' => (bool) $request->user()?->can('invoicing.clients.create'),
                'pode_criar_local' => (bool) $request->user()?->can('events.venues.manage'),
                'pode_criar_tipo' => (bool) $request->user()?->can('events.types.manage'),
            ],
        ]);
    }

    /** Os emojis que um tipo de evento pode ter — a mesma lista de sempre. */
    public const EMOJIS = [
        ['valor' => '🏢', 'rotulo' => 'Corporativo'],
        ['valor' => '💍', 'rotulo' => 'Casamento'],
        ['valor' => '🎤', 'rotulo' => 'Conferência'],
        ['valor' => '🎸', 'rotulo' => 'Espectáculo'],
        ['valor' => '📹', 'rotulo' => 'Transmissão'],
        ['valor' => '🎉', 'rotulo' => 'Festa'],
        ['valor' => '🎓', 'rotulo' => 'Formatura'],
        ['valor' => '🎂', 'rotulo' => 'Aniversário'],
        ['valor' => '🎭', 'rotulo' => 'Teatro'],
        ['valor' => '🏆', 'rotulo' => 'Entrega de prémios'],
        ['valor' => '📚', 'rotulo' => 'Formação'],
        ['valor' => '🎨', 'rotulo' => 'Cultural'],
        ['valor' => '⚽', 'rotulo' => 'Desportivo'],
        ['valor' => '🎪', 'rotulo' => 'Festival'],
        ['valor' => '📌', 'rotulo' => 'Outro'],
    ];

    /* ─── A lista ──────────────────────────────────────────────────────── */

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'events.calendar.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string'],
            'fase' => ['nullable', 'string'],
            'tipo' => ['nullable', 'integer'],
            'cliente' => ['nullable', 'integer'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $lista = $this->comFiltros($filtros)
            ->with(['client:id,name', 'venue:id,name', 'type:id,name,icon,color', 'responsible:id,name'])
            ->orderBy('start_date', 'desc')
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json([
            'data' => collect($lista->items())->map(fn (Event $e) => $this->linha($e))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'total' => Event::forTenant()->count(),
                'orcamento' => Event::forTenant()->where('status', 'orcamento')->count(),
                'confirmados' => Event::forTenant()->where('status', 'confirmado')->count(),
                'a_decorrer' => Event::forTenant()->whereIn('status', ['em_montagem', 'em_andamento'])->count(),
                'concluidos_no_mes' => Event::forTenant()->where('status', 'concluido')
                    ->whereMonth('end_date', now()->month)->whereYear('end_date', now()->year)->count(),
            ],
        ]);
    }

    private function comFiltros(array $f)
    {
        return Event::forTenant()
            ->when($f['procura'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$t}%")
                ->orWhere('event_number', 'like', "%{$t}%")))
            ->when($f['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->when($f['fase'] ?? null, fn ($q, $p) => $q->where('phase', $p))
            ->when($f['tipo'] ?? null, fn ($q, $t) => $q->where('type_id', $t))
            ->when($f['cliente'] ?? null, fn ($q, $c) => $q->where('client_id', $c))
            ->when($f['de'] ?? null, fn ($q, $d) => $q->whereDate('start_date', '>=', $d))
            ->when($f['ate'] ?? null, fn ($q, $d) => $q->whereDate('start_date', '<=', $d));
    }

    /**
     * O MÊS, em semanas inteiras.
     *
     * A grelha começa na segunda-feira da semana do dia 1 e acaba no domingo da
     * semana do último dia: senão, a primeira linha aparece encostada à direita
     * e o mês lê-se torto.
     */
    public function calendario(Request $request): JsonResponse
    {
        $this->exigir($request, 'events.calendar.view');

        $dados = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
            'estado' => ['nullable', 'string'],
            'fase' => ['nullable', 'string'],
            'tipo' => ['nullable', 'integer'],
        ]);

        $mes = Carbon::createFromFormat('Y-m', $dados['mes'] ?? now()->format('Y-m'))->startOfMonth();

        $inicio = $mes->copy()->startOfWeek(Carbon::MONDAY);
        $fim = $mes->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        /*
         * UM EVENTO DE TRÊS DIAS APARECE NOS TRÊS.
         *
         * A consulta antiga comparava só a data de início: um evento que
         * começasse a 31 de Janeiro e acabasse a 2 de Fevereiro desaparecia do
         * calendário de Fevereiro — e era o mês em que estava a acontecer.
         */
        $eventos = $this->comFiltros($dados)
            ->where('start_date', '<=', $fim->copy()->endOfDay())
            ->where('end_date', '>=', $inicio->copy()->startOfDay())
            ->with(['client:id,name', 'venue:id,name', 'type:id,name,icon,color'])
            ->orderBy('start_date')
            ->get();

        $porDia = [];

        foreach ($eventos as $evento) {
            $de = $evento->start_date->copy()->startOfDay()->max($inicio);
            $ate = $evento->end_date->copy()->startOfDay()->min($fim);

            for ($d = $de->copy(); $d->lte($ate); $d->addDay()) {
                $porDia[$d->format('Y-m-d')][] = $this->linha($evento);
            }
        }

        $dias = [];

        for ($d = $inicio->copy(); $d->lte($fim); $d->addDay()) {
            $chave = $d->format('Y-m-d');

            $dias[] = [
                'dia' => $chave,
                'numero' => (int) $d->day,
                'do_mes' => $d->month === $mes->month,
                'hoje' => $d->isToday(),
                'eventos' => $porDia[$chave] ?? [],
            ];
        }

        return response()->json([
            'mes' => $mes->format('Y-m'),
            'rotulo' => $mes->translatedFormat('F Y'),
            'dias' => $dias,
        ]);
    }

    /* ─── A ficha ──────────────────────────────────────────────────────── */

    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.calendar.view');

        $evento = Event::forTenant()
            ->with([
                'client:id,name,phone,email', 'venue:id,name,address,city,capacity',
                'type:id,name,icon,color', 'responsible:id,name',
                'checklists' => fn ($q) => $q->orderBy('phase')->orderBy('order'),
            ])
            ->findOrFail($id);

        return response()->json([
            'data' => $this->linha($evento) + [
                'descricao' => $evento->description,
                'notas' => $evento->notes,
                'responsavel' => $evento->responsible?->name,
                'cliente_telefone' => $evento->client?->phone,
                'cliente_email' => $evento->client?->email,
                'local_morada' => $evento->venue?->address,
                'local_cidade' => $evento->venue?->city,
                'local_capacidade' => (int) $evento->venue?->capacity,
                'montagem_em' => $evento->setup_start?->format('Y-m-d H:i'),
                'desmontagem_em' => $evento->teardown_end?->format('Y-m-d H:i'),
                'confirmado_em' => $evento->confirmed_at?->format('Y-m-d H:i'),
                'concluido_em' => $evento->completed_at?->format('Y-m-d H:i'),
                'pode' => $this->eventos->seguintes($evento),
                'pode_avancar' => $evento->canAdvancePhase()
                    && ! in_array($evento->status, ['cancelado', 'concluido'], true),
            ],
            'tarefas' => $evento->checklists->map(fn (Checklist $t) => [
                'id' => $t->id,
                'tarefa' => $t->task,
                'descricao' => $t->description,
                'fase' => $t->phase,
                'fase_rotulo' => __(Eventos::FASES[$t->phase] ?? $t->phase),
                'obrigatoria' => (bool) $t->is_required,
                'feita' => $t->status === 'concluido',
                'feita_em' => $t->completed_at?->format('Y-m-d H:i'),
            ])->values(),
        ]);
    }

    private function linha(Event $e): array
    {
        return [
            'id' => $e->id,
            'numero' => $e->event_number,
            'nome' => $e->name,
            'client_id' => $e->client_id,
            'cliente' => $e->client?->name,
            'venue_id' => $e->venue_id,
            'local' => $e->venue?->name,
            'type_id' => $e->type_id,
            'tipo' => $e->type?->name,
            'tipo_icone' => $e->type?->icon,
            'tipo_cor' => $e->type?->color,
            'inicio' => $e->start_date?->format('Y-m-d\TH:i'),
            'fim' => $e->end_date?->format('Y-m-d\TH:i'),
            'estado' => $e->status,
            'estado_rotulo' => __(Eventos::ESTADOS[$e->status] ?? $e->status),
            'estado_icone' => Eventos::ICONE_DO_ESTADO[$e->status] ?? 'fa-circle',
            'fase' => $e->phase,
            'fase_rotulo' => __(Eventos::FASES[$e->phase] ?? $e->phase),
            'progresso' => (int) $e->checklist_progress,
            'pessoas' => (int) $e->expected_attendees,
            'valor' => (float) $e->total_value,
            'cor' => $e->calendar_color,
        ];
    }

    /* ─── Gravar ───────────────────────────────────────────────────────── */

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'events.calendar.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'type_id' => ['required', Rule::exists('events_types', 'id')->where('tenant_id', $tenantId)],
            'client_id' => ['nullable', Rule::exists('invoicing_clients', 'id')->where('tenant_id', $tenantId)],
            'venue_id' => ['nullable', Rule::exists('events_venues', 'id')->where('tenant_id', $tenantId)],
            'expected_attendees' => ['nullable', 'integer', 'min:1'],
            'total_value' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'setup_start' => ['nullable', 'date'],
            'teardown_end' => ['nullable', 'date'],
            'calendar_color' => ['nullable', 'string', 'max:7'],
        ], [
            'end_date.after' => __('A data de fim tem de ser depois da de início.'),
        ], [
            'name' => __('nome'), 'start_date' => __('início'), 'end_date' => __('fim'),
            'type_id' => __('tipo'),
        ]);

        /*
         * O LOCAL TEM CAPACIDADE, e ela quer dizer alguma coisa.
         *
         * Marcava-se um evento de 500 pessoas numa sala de 80 e ninguém dizia
         * nada — descobria-se no dia, com as pessoas à porta.
         */
        if (($dados['venue_id'] ?? null) && ($dados['expected_attendees'] ?? null)) {
            $local = Venue::forTenant()->find($dados['venue_id']);

            if ($local && $local->capacity && $dados['expected_attendees'] > $local->capacity) {
                $this->recusa(__('O local :local leva :cabem pessoas, e o evento espera :esperadas.', [
                    'local' => $local->name,
                    'cabem' => $local->capacity,
                    'esperadas' => $dados['expected_attendees'],
                ]));
            }
        }

        $valores = [
            'name' => $dados['name'],
            'start_date' => $dados['start_date'],
            'end_date' => $dados['end_date'],
            'type_id' => $dados['type_id'],
            'client_id' => $dados['client_id'] ?? null,
            'venue_id' => $dados['venue_id'] ?? null,
            'expected_attendees' => $dados['expected_attendees'] ?? null,
            'total_value' => $dados['total_value'] ?? 0,
            'description' => $dados['description'] ?? null,
            'notes' => $dados['notes'] ?? null,
            'setup_start' => $dados['setup_start'] ?? null,
            'teardown_end' => $dados['teardown_end'] ?? null,
            'calendar_color' => $dados['calendar_color'] ?? null,
        ];

        if ($id) {
            $evento = Event::forTenant()->findOrFail($id);
            $evento->update($valores);
        } else {
            $evento = Event::create($valores + [
                'tenant_id' => $tenantId,
                'status' => 'orcamento',
                'phase' => 'planejamento',
                'responsible_user_id' => $request->user()?->id,
            ]);

            // O checklist da primeira fase nasce com o evento: sem ele, a fase
            // avançava sem nada por cumprir e a guarda não guardava nada.
            $evento->createDefaultChecklistForPhase('planejamento');
            $evento->updateChecklistProgress();
        }

        return response()->json([
            'message' => $id ? __('Evento actualizado.') : __('Evento criado: :n', ['n' => $evento->event_number]),
            'data' => $this->linha($evento->fresh(['client', 'venue', 'type'])),
        ], $id ? 200 : 201);
    }

    /**
     * ARRASTAR NO CALENDÁRIO só muda as datas.
     *
     * É uma porta à parte de propósito: o arrastar mexe em duas colunas e mais
     * nenhuma, e não devia poder passar pelo caminho que reescreve o evento
     * inteiro.
     */
    public function mover(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.calendar.manage');

        $dados = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ], ['end_date.after' => __('A data de fim tem de ser depois da de início.')]);

        $evento = Event::forTenant()->findOrFail($id);

        if (in_array($evento->status, ['concluido', 'cancelado'], true)) {
            $this->recusa(__('Um evento :estado não muda de data.', [
                'estado' => __(Eventos::ESTADOS[$evento->status]),
            ]));
        }

        $evento->update($dados);

        return response()->json([
            'message' => __('Datas actualizadas.'),
            'data' => $this->linha($evento->fresh(['client', 'venue', 'type'])),
        ]);
    }

    public function estado(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.calendar.manage');

        $dados = $request->validate([
            'estado' => ['required', Rule::in(array_keys(Eventos::ESTADOS))],
        ]);

        $evento = $this->eventos->estado(Event::forTenant()->findOrFail($id), $dados['estado']);

        return response()->json([
            'message' => __('Evento :estado.', ['estado' => __(Eventos::ESTADOS[$evento->status])]),
            'data' => $this->linha($evento->fresh(['client', 'venue', 'type'])),
        ]);
    }

    public function avancarFase(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.calendar.manage');

        $evento = $this->eventos->avancarFase(Event::forTenant()->findOrFail($id));

        return response()->json([
            'message' => __('Fase avançada para :fase.', ['fase' => __(Eventos::FASES[$evento->phase] ?? $evento->phase)]),
            'data' => $this->linha($evento->fresh(['client', 'venue', 'type'])),
        ]);
    }

    public function tarefa(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.calendar.manage');

        $tarefa = $this->eventos->alternarTarefa($id);

        return response()->json([
            'message' => $tarefa->status === 'concluido'
                ? __('Tarefa concluída: :t', ['t' => $tarefa->task])
                : __('Tarefa por fazer: :t', ['t' => $tarefa->task]),
            'feita' => $tarefa->status === 'concluido',
            'progresso' => (int) $tarefa->event?->fresh()->checklist_progress,
        ]);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'events.calendar.manage');

        $evento = Event::forTenant()->findOrFail($id);

        /*
         * UM EVENTO QUE JÁ ACONTECEU NÃO SE APAGA.
         *
         * Apagá-lo levava com ele o valor facturado, as horas dos técnicos e o
         * histórico dos equipamentos que lá estiveram — e os relatórios do ano
         * passavam a dar outro número sem ninguém saber porquê. Cancele-se.
         */
        if ($evento->status === 'concluido') {
            $this->recusa(__('Um evento concluído não se apaga — fica no historial.'));
        }

        $evento->delete();

        return response()->json(['message' => __('Evento removido.')]);
    }

    /* ─── Os atalhos de criar sem sair do ecrã ─────────────────────────── */

    /**
     * O CLIENTE NOVO, sem sair da marcação.
     *
     * Quem liga a marcar um evento raramente já está no sistema, e mandar a
     * pessoa a outro ecrã era perder o que já tinha escrito.
     */
    public function clienteRapido(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.clients.create');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'nif' => ['required', 'string', 'max:30',
                Rule::unique('invoicing_clients', 'nif')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'country' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ], [
            'nif.unique' => __('Já existe um cliente com este NIF nesta empresa.'),
        ], ['name' => __('nome'), 'nif' => __('NIF')]);

        $cliente = Client::create([
            'tenant_id' => $tenantId,
            'name' => $dados['name'],
            'nif' => $dados['nif'],
            'country' => $dados['country'] ?? 'AO',
            'email' => $dados['email'] ?? null,
            'phone' => $dados['phone'] ?? null,
            'type' => 'pessoa_fisica',
            'is_active' => true,
        ]);

        return response()->json([
            'message' => __('Cliente criado.'),
            'data' => ['valor' => (string) $cliente->id, 'rotulo' => $cliente->name],
        ], 201);
    }

    public function localRapido(Request $request): JsonResponse
    {
        $this->exigir($request, 'events.venues.manage');

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ], [], ['name' => __('nome')]);

        $local = Venue::create($dados + ['tenant_id' => activeTenantId(), 'is_active' => true]);

        return response()->json([
            'message' => __('Local criado.'),
            'data' => [
                'valor' => (string) $local->id, 'rotulo' => $local->name,
                'capacidade' => (int) $local->capacity,
            ],
        ], 201);
    }

    public function tipoRapido(Request $request): JsonResponse
    {
        $this->exigir($request, 'events.types.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'icon' => ['required', 'string', 'max:10'],
            'color' => ['required', 'string', 'max:7'],
        ], [], ['name' => __('nome'), 'icon' => __('ícone'), 'color' => __('cor')]);

        $tipo = EventType::create($dados + [
            'tenant_id' => $tenantId,
            'order' => (int) EventType::forTenant()->max('order') + 1,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => __('Tipo criado.'),
            'data' => [
                'valor' => (string) $tipo->id, 'rotulo' => $tipo->name,
                'icone' => $tipo->icon, 'cor' => $tipo->color,
            ],
        ], 201);
    }
}
