<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\HousekeepingTask;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A LIMPEZA DOS QUARTOS — o que há para limpar hoje, e quem o está a fazer.
 *
 * O ecrã é do DIA: escolhe-se uma data e vê-se a mesma coisa de duas maneiras —
 * o QUADRO por estado, que é a fila de trabalho, e os QUARTOS por piso, que é a
 * planta da casa pintada pelo estado de limpeza. Ambas vêm daqui, para não
 * haver duas contagens.
 *
 * AS REGRAS VIVEM NO MODELO (`HousekeepingTask::start/complete/verify`), e é
 * bom que vivam: começar uma limpeza põe o QUARTO em «em limpeza», e acabar
 * põe-no limpo e disponível. Reimplementar isso aqui era ter duas verdades
 * sobre o estado de um quarto.
 *
 * QUEM LIMPA É UM UTILIZADOR e não uma ficha de pessoal do hotel — ao
 * contrário da manutenção, que usa `hotel_staff`. É o que a coluna, a chave
 * estrangeira, as relações do modelo e a caixa de escolha sempre disseram, os
 * quatro de acordo: aqui não há defeito nenhum para corrigir.
 */
class LimpezaApiController extends Controller
{
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
        $this->exigir($request, 'hotel.housekeeping.view');

        $tenantId = activeTenantId();

        $quartos = Room::where('tenant_id', $tenantId)->where('is_active', true)
            ->with('roomType:id,name')->orderBy('floor')->orderBy('number')->get();

        return response()->json([
            'tipos' => self::escolhas(HousekeepingTask::TASK_TYPES),
            'prioridades' => self::escolhas(HousekeepingTask::PRIORITIES),
            'estados' => self::escolhas(HousekeepingTask::STATUSES),
            'limpezas' => self::escolhas(Room::HOUSEKEEPING_STATUSES),
            'quartos' => $quartos->map(fn (Room $q) => [
                'valor' => (string) $q->id,
                'rotulo' => trim($q->number . ' — ' . ($q->roomType?->name ?? ''), ' —'),
            ])->values(),
            'andares' => $quartos->pluck('floor')->filter()->unique()->sort()->values()
                ->map(fn ($p) => ['valor' => (string) $p, 'rotulo' => __('Piso :p', ['p' => $p])])->values(),
            'pessoas' => User::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($u) => ['valor' => (string) $u->id, 'rotulo' => $u->name])->values(),
            'permissoes' => [
                /*
                 * A LIMPEZA TEM UMA PERMISSÃO DE ESCRITA SÓ («gerir»), e não
                 * quatro por verbo: abrir, atribuir, começar e dar por
                 * concluída uma tarefa é o mesmo trabalho da mesma pessoa, e
                 * separá-las dava um papel que pode começar mas não acabar.
                 */
                'pode_gerir' => (bool) $request->user()?->can('hotel.housekeeping.manage'),
            ],
        ]);
    }

    /** O dia inteiro: as tarefas, os números e a planta dos quartos. */
    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.housekeeping.view');

        $filtros = $request->validate([
            'dia' => ['nullable', 'date'],
            'prioridade' => ['nullable', Rule::in(array_keys(HousekeepingTask::PRIORITIES))],
            'responsavel' => ['nullable', 'integer'],
            'andar' => ['nullable', 'string', 'max:20'],
        ]);

        $tenantId = activeTenantId();
        $dia = \Carbon\Carbon::parse($filtros['dia'] ?? today())->toDateString();

        $tarefas = HousekeepingTask::where('tenant_id', $tenantId)
            ->with($this->comAsFichas())
            ->whereDate('scheduled_date', $dia)
            ->when($filtros['prioridade'] ?? null, fn ($q, $p) => $q->where('priority', $p))
            ->when($filtros['responsavel'] ?? null, fn ($q, $r) => $q->where('assigned_to', $r))
            // O URGENTE PRIMEIRO, e depois pela hora marcada. O `orderBy` de
            // sempre era alfabético sobre a chave — «high, low, normal,
            // urgent» — e punha justamente a urgente em último.
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderByRaw('scheduled_time IS NULL, scheduled_time')
            ->get();

        /*
         * OS NÚMEROS SÃO OS DO DIA INTEIRO e não os do filtro: uma contagem que
         * encolhe quando se filtra por pessoa deixa de dizer quanto falta
         * limpar na casa, que é a pergunta que os cartões respondem.
         */
        $doDia = HousekeepingTask::where('tenant_id', $tenantId)->whereDate('scheduled_date', $dia)
            ->selectRaw('status, COUNT(*) as quantas')->groupBy('status')->pluck('quantas', 'status');

        $porLimpeza = Room::where('tenant_id', $tenantId)->where('is_active', true)
            ->selectRaw('housekeeping_status, COUNT(*) as quantos')
            ->groupBy('housekeeping_status')->pluck('quantos', 'housekeeping_status');

        $totalDeQuartos = (int) $porLimpeza->sum();
        $limpos = (int) ($porLimpeza['clean'] ?? 0);

        /*
         * A TAREFA DE CADA QUARTO É A DO DIA INTEIRO, sem filtro.
         *
         * A planta pinta-se pelo estado do quarto e o pino em cima diz se há
         * tarefa; filtrar por prioridade não pode fazer desaparecer o pino de
         * um quarto que continua sujo.
         */
        $porQuarto = HousekeepingTask::where('tenant_id', $tenantId)
            ->with('assignedUser:id,name')
            ->whereDate('scheduled_date', $dia)
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->get()->groupBy('room_id');

        $quartos = Room::where('tenant_id', $tenantId)->where('is_active', true)
            ->with('roomType:id,name')
            ->when($filtros['andar'] ?? null, fn ($q, $p) => $q->where('floor', $p))
            ->orderBy('floor')->orderBy('number')->get();

        return response()->json([
            'dia' => $dia,
            'tarefas' => $tarefas->map(fn (HousekeepingTask $t) => $this->linha($t))->values(),
            'resumo' => [
                'total' => (int) $doDia->sum(),
                'pendentes' => (int) ($doDia['pending'] ?? 0),
                'em_curso' => (int) ($doDia['in_progress'] ?? 0),
                'concluidas' => (int) (($doDia['completed'] ?? 0) + ($doDia['verified'] ?? 0)),
                'com_problema' => (int) ($doDia['issue'] ?? 0),
                /*
                 * ATRASADAS SÃO DE TODOS OS DIAS, e não do dia escolhido: uma
                 * tarefa de anteontem por acabar é justamente o que se quer
                 * ver, e no dia de hoje ela não aparece.
                 */
                'atrasadas' => HousekeepingTask::where('tenant_id', $tenantId)
                    ->whereDate('scheduled_date', '<', today())
                    ->whereNotIn('status', ['completed', 'verified'])->count(),
                'quartos' => $totalDeQuartos,
                'quartos_limpos' => $limpos,
                'quartos_sujos' => (int) ($porLimpeza['dirty'] ?? 0),
                'quartos_em_limpeza' => (int) ($porLimpeza['in_progress'] ?? 0),
                'limpeza' => $totalDeQuartos > 0 ? (int) round($limpos / $totalDeQuartos * 100) : 0,
            ],
            'quartos' => $quartos->map(function (Room $q) use ($porQuarto) {
                $tarefa = $porQuarto->get($q->id)?->first();

                return [
                    'id' => $q->id,
                    'numero' => $q->number,
                    'piso' => $q->floor,
                    'tipo' => $q->roomType?->name,
                    'estado' => $q->status,
                    'limpeza' => $q->housekeeping_status,
                    'limpeza_rotulo' => __(Room::HOUSEKEEPING_STATUSES[$q->housekeeping_status] ?? (string) $q->housekeeping_status),
                    'tarefa' => $tarefa ? [
                        'id' => $tarefa->id,
                        'prioridade' => $tarefa->priority,
                        'estado' => $tarefa->status,
                        'responsavel' => $tarefa->assignedUser?->name,
                    ] : null,
                ];
            })->values(),
        ]);
    }

    /**
     * GERAR AS TAREFAS DO DIA a partir das saídas e das estadas.
     *
     * Quem sai hoje deixa um quarto para limpar a fundo; quem fica leva
     * arrumação de estadia. É o botão que a governanta carrega de manhã, e é o
     * que evita abrir vinte tarefas à mão.
     */
    public function gerar(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.housekeeping.manage');

        $tenantId = activeTenantId();
        $hoje = today();

        /*
         * OS QUARTOS QUE JÁ TÊM TAREFA HOJE, numa consulta só.
         *
         * O ecrã de sempre perguntava-o DENTRO do ciclo — um `exists()` por
         * reserva, em dois ciclos. Num hotel cheio de cem quartos eram duzentas
         * consultas para gerar as tarefas de uma manhã.
         */
        $jaTem = HousekeepingTask::where('tenant_id', $tenantId)
            ->whereDate('scheduled_date', $hoje)
            ->pluck('room_id')->filter()->all();

        $criadas = 0;

        $abrir = function (Reservation $reserva, string $tipo, string $prioridade, int $minutos) use (&$jaTem, &$criadas, $tenantId, $hoje) {
            if (in_array($reserva->room_id, $jaTem, true)) {
                return;
            }

            HousekeepingTask::create([
                'tenant_id' => $tenantId,
                'room_id' => $reserva->room_id,
                'reservation_id' => $reserva->id,
                'task_type' => $tipo,
                'priority' => $prioridade,
                'scheduled_date' => $hoje,
                'estimated_duration' => $minutos,
            ]);

            $jaTem[] = $reserva->room_id;
            $criadas++;
        };

        $saidas = Reservation::where('tenant_id', $tenantId)
            ->whereDate('check_out_date', $hoje)
            ->where('status', 'checked_in')
            ->whereNotNull('room_id')
            ->get(['id', 'room_id']);

        foreach ($saidas as $reserva) {
            $abrir($reserva, 'checkout_clean', 'high', 45);

            // Quem sai deixa o quarto sujo — e é isso que o tira da lista de
            // disponíveis até alguém o limpar.
            Room::where('tenant_id', $tenantId)->where('id', $reserva->room_id)
                ->update(['housekeeping_status' => 'dirty']);
        }

        $ficam = Reservation::where('tenant_id', $tenantId)
            ->where('status', 'checked_in')
            ->whereDate('check_out_date', '>', $hoje)
            ->whereNotNull('room_id')
            ->get(['id', 'room_id']);

        foreach ($ficam as $reserva) {
            $abrir($reserva, 'stay_clean', 'normal', 20);
        }

        return response()->json([
            'quantas' => $criadas,
            'message' => $criadas > 0
                ? __(':quantas tarefa(s) gerada(s).', ['quantas' => $criadas])
                : __('Já estavam todas geradas.'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.housekeeping.manage');

        $dados = $this->validar($request);
        $tenantId = activeTenantId();

        $tarefa = HousekeepingTask::create($dados + ['tenant_id' => $tenantId]);

        // Uma tarefa aberta à mão marca o quarto como sujo: é isso que ela
        // quer dizer.
        Room::where('tenant_id', $tenantId)->where('id', $dados['room_id'])
            ->update(['housekeeping_status' => 'dirty']);

        return response()->json([
            'data' => $this->linha($this->recarregar($tarefa)),
            'message' => __('Tarefa criada.'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.housekeeping.manage');

        $tarefa = $this->encontrar($id);
        $tarefa->update($this->validar($request));

        return response()->json([
            'data' => $this->linha($this->recarregar($tarefa)),
            'message' => __('Tarefa guardada.'),
        ]);
    }

    /**
     * COMEÇAR, ACABAR, VERIFICAR — pelos métodos do modelo.
     *
     * Começar põe o QUARTO em «em limpeza»; acabar e verificar põem-no limpo e
     * disponível. É por isso que isto não é um `update` de uma coluna.
     */
    public function estado(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.housekeeping.manage');

        $dados = $request->validate([
            'accao' => ['required', Rule::in(['comecar', 'acabar', 'verificar', 'problema'])],
            'problema' => ['nullable', 'string', 'max:2000'],
        ]);

        $tarefa = $this->encontrar($id);

        $mensagem = match ($dados['accao']) {
            'comecar' => $this->comecar($tarefa),
            'acabar' => $this->acabar($tarefa),
            'verificar' => $this->verificar($tarefa, $request->user()?->id),
            'problema' => $this->problema($tarefa, $dados['problema'] ?? null),
        };

        return response()->json([
            'data' => $this->linha($this->recarregar($tarefa)),
            'message' => $mensagem,
        ]);
    }

    private function comecar(HousekeepingTask $t): string
    {
        $t->start();

        return __('Limpeza começada.');
    }

    private function acabar(HousekeepingTask $t): string
    {
        $t->complete();

        return __('Limpeza concluída.');
    }

    private function verificar(HousekeepingTask $t, ?int $quem): string
    {
        $t->verify($quem);

        return __('Limpeza verificada.');
    }

    /**
     * UM PROBLEMA NO QUARTO — e o que ele quer dizer.
     *
     * O ecrã de sempre tinha a coluna «Com Problemas» no quadro e NENHUMA
     * maneira de lá pôr uma tarefa: o `reportIssue()` do modelo existia e nada
     * o chamava, e a coluna ficava sempre vazia. Marcar um problema deixa
     * também o quarto FORA DE SERVIÇO, que é o que impede a recepção de o
     * vender enquanto ninguém o resolve.
     */
    private function problema(HousekeepingTask $t, ?string $descricao): string
    {
        $t->reportIssue($descricao);

        $t->room?->update(['housekeeping_status' => 'out_of_order']);

        return __('Problema registado — o quarto ficou fora de serviço.');
    }

    /** Marcar (ou desmarcar) um ponto da lista de verificação. */
    public function ponto(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.housekeeping.manage');

        $dados = $request->validate(['indice' => ['required', 'integer', 'min:0']]);

        $tarefa = $this->encontrar($id);
        $lista = $tarefa->checklist ?? [];

        abort_unless(isset($lista[$dados['indice']]), 404, __('Esse ponto não existe nesta tarefa.'));

        // UMA TAREFA VERIFICADA NÃO SE MEXE. O ecrã desactivava as caixas, mas
        // a acção do servidor aceitava na mesma — e quem soubesse o pedido
        // reescrevia uma lista já dada por boa.
        abort_if($tarefa->status === 'verified', 422,
            __('Esta tarefa já foi verificada: a lista não se altera.'));

        $tarefa->updateChecklist($dados['indice'], ! ($lista[$dados['indice']]['done'] ?? false));

        return response()->json(['data' => $this->linha($this->recarregar($tarefa))]);
    }

    public function atribuir(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.housekeeping.manage');

        $dados = $request->validate(['pessoa' => ['nullable', 'integer']]);

        $tarefa = $this->encontrar($id);

        if (! empty($dados['pessoa'])) {
            abort_unless(
                User::where('tenant_id', activeTenantId())->whereKey($dados['pessoa'])->exists(),
                422, __('Essa pessoa não é desta empresa.')
            );
        }

        $tarefa->update(['assigned_to' => $dados['pessoa'] ?: null]);

        return response()->json([
            'data' => $this->linha($this->recarregar($tarefa)),
            'message' => empty($dados['pessoa']) ? __('Tarefa sem responsável.') : __('Tarefa atribuída.'),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.housekeeping.manage');

        $this->encontrar($id)->delete();

        return response()->json(['message' => __('Tarefa removida.')]);
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function encontrar(int $id): HousekeepingTask
    {
        return HousekeepingTask::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    /** @return list<string> */
    private function comAsFichas(): array
    {
        return [
            'room:id,number,floor,room_type_id', 'room.roomType:id,name',
            'assignedUser:id,name', 'verifiedByUser:id,name',
            'reservation:id,client_id,guest_id,check_out_date', 'reservation.client:id,name',
        ];
    }

    private function recarregar(HousekeepingTask $t): HousekeepingTask
    {
        return $t->fresh($this->comAsFichas());
    }

    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'room_id' => ['required', 'integer'],
            'task_type' => ['required', Rule::in(array_keys(HousekeepingTask::TASK_TYPES))],
            'priority' => ['required', Rule::in(array_keys(HousekeepingTask::PRIORITIES))],
            'assigned_to' => ['nullable', 'integer'],
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'estimated_duration' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        /*
         * O QUARTO E A PESSOA TÊM DE SER DESTA EMPRESA.
         *
         * O ecrã de sempre validava `exists:hotel_rooms,id` — a tabela inteira,
         * sem empresa nenhuma — e o id vinha do browser: abrir uma tarefa sobre
         * o quarto de outro hotel era escrever um número diferente.
         */
        abort_unless(
            Room::where('tenant_id', activeTenantId())->whereKey($dados['room_id'])->exists(),
            422, __('Esse quarto não é desta casa.')
        );

        if (! empty($dados['assigned_to'])) {
            abort_unless(
                User::where('tenant_id', activeTenantId())->whereKey($dados['assigned_to'])->exists(),
                422, __('Essa pessoa não é desta empresa.')
            );
        }

        foreach (['assigned_to', 'scheduled_time', 'estimated_duration', 'notes'] as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dados[$campo] = $dados[$campo] ?: null;
            }
        }

        return $dados;
    }

    private function linha(HousekeepingTask $t): array
    {
        $lista = $t->checklist ?? [];
        $feitos = collect($lista)->filter(fn ($p) => $p['done'] ?? false)->count();

        return [
            'id' => $t->id,
            'room_id' => $t->room_id,
            'quarto' => $t->room?->number,
            'piso' => $t->room?->floor,
            'tipo_de_quarto' => $t->room?->roomType?->name,
            'tipo' => $t->task_type,
            'tipo_rotulo' => __(HousekeepingTask::TASK_TYPES[$t->task_type] ?? (string) $t->task_type),
            'prioridade' => $t->priority,
            'prioridade_rotulo' => __(HousekeepingTask::PRIORITIES[$t->priority] ?? (string) $t->priority),
            'estado' => $t->status,
            'estado_rotulo' => __(HousekeepingTask::STATUSES[$t->status] ?? (string) $t->status),
            'assigned_to' => $t->assigned_to,
            'responsavel' => $t->assignedUser?->name,
            'verificada_por' => $t->verifiedByUser?->name,
            /*
             * O HÓSPEDE VEM DA RESERVA, e da ficha certa.
             *
             * O cartão do quadro lia `reservation->guest` — a ficha antiga
             * (`hotel_guests`), que está vazia — e nunca mostrou nome nenhum,
             * enquanto o modal ao lado lia `reservation->client` e mostrava.
             * O acessor `nome_do_hospede` é a resposta única.
             */
            'hospede' => $t->reservation?->nome_do_hospede,
            'saida' => $t->reservation?->check_out_date?->toDateString(),
            'dia' => $t->scheduled_date?->toDateString(),
            'hora' => $t->scheduled_time ? substr((string) $t->scheduled_time, 0, 5) : null,
            'minutos_previstos' => $t->estimated_duration,
            'minutos_gastos' => $t->actual_duration,
            'comecou' => $t->started_at?->toIso8601String(),
            'acabou' => $t->completed_at?->toIso8601String(),
            'notas' => $t->notes,
            'problema' => $t->issues,
            'atrasada' => (bool) $t->is_overdue,
            'lista' => collect($lista)->map(fn ($p, $i) => [
                'indice' => $i,
                'item' => $p['item'] ?? '',
                'feito' => (bool) ($p['done'] ?? false),
            ])->values(),
            'feitos' => $feitos,
            'pontos' => count($lista),
            'progresso' => count($lista) > 0 ? (int) round($feitos / count($lista) * 100) : 0,
        ];
    }
}
