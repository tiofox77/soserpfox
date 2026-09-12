<?php

namespace App\Http\Controllers\Api\Salon;

use App\Http\Controllers\Controller;
use App\Models\Salon\Appointment;
use App\Models\Salon\Client;
use App\Models\Salon\Professional;
use App\Models\Salon\Service;
use App\Services\Salon\Marcacoes;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS MARCAÇÕES — a lista, o calendário e o percurso de cada uma.
 *
 * DUAS VISTAS DA MESMA COISA, e de propósito: a LISTA serve para encontrar uma
 * marcação, o CALENDÁRIO serve para ver onde há espaço. Ambas vêm daqui, para
 * não haver duas contagens.
 *
 * AS TRANSIÇÕES SÃO DO SERVIÇO (`App\Services\Salon\Marcacoes`) e cada resposta
 * traz o que se pode fazer a seguir. Não havia tabela nenhuma: o ecrã mostrava
 * os botões todos e dava para concluir uma marcação cancelada.
 */
class MarcacoesApiController extends Controller
{
    public function __construct(private readonly Marcacoes $marcacoes) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(\Throwable $e): never
    {
        throw ValidationException::withMessages(['geral' => [$e->getMessage()]]);
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.appointments.view');

        return response()->json([
            'profissionais' => Professional::forTenant()->active()->orderBy('name')
                ->get(['id', 'name', 'specialization'])
                ->map(fn (Professional $p) => [
                    'valor' => (string) $p->id,
                    'rotulo' => $p->specialization ? $p->name.' · '.$p->specialization : $p->name,
                ])->values(),
            'servicos' => Service::forTenant()->active()->orderBy('name')->get()
                ->map(fn (Service $s) => [
                    'valor' => (string) $s->id,
                    'rotulo' => $s->name,
                    'duracao' => (int) $s->duration,
                    'preco' => (float) $s->price,
                ])->values(),
            'estados' => collect(Appointment::STATUSES)
                ->map(fn ($r, $v) => ['valor' => (string) $v, 'rotulo' => __($r)])->values(),
            'origens' => collect(Appointment::SOURCES)
                ->map(fn ($r, $v) => ['valor' => (string) $v, 'rotulo' => __($r)])->values(),
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('salon.appointments.create'),
                'pode_editar' => (bool) $request->user()?->can('salon.appointments.edit'),
                'pode_apagar' => (bool) $request->user()?->can('salon.appointments.delete'),
                'pode_criar_cliente' => (bool) $request->user()?->can('salon.clients.create'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.appointments.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(array_keys(Appointment::STATUSES))],
            'profissional' => ['nullable', 'integer'],
            'origem' => ['nullable', 'string', 'max:30'],
            'quando' => ['nullable', Rule::in(['hoje', 'amanha', 'semana'])],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $lista = Appointment::forTenant()
            ->with(['client:id,name,phone', 'professional:id,name', 'services.service'])
            ->when($filtros['procura'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('appointment_number', 'like', "%{$t}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$t}%")->orWhere('phone', 'like', "%{$t}%"))))
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->when($filtros['profissional'] ?? null, fn ($q, $p) => $q->where('professional_id', $p))
            ->when($filtros['origem'] ?? null, fn ($q, $o) => $q->where('source', $o))
            ->when(($filtros['quando'] ?? null) === 'hoje', fn ($q) => $q->forDate(today()))
            ->when(($filtros['quando'] ?? null) === 'amanha', fn ($q) => $q->forDate(today()->addDay()))
            ->when(($filtros['quando'] ?? null) === 'semana',
                fn ($q) => $q->whereBetween('date', [today()->toDateString(), today()->addWeek()->toDateString()]))
            ->orderByDesc('date')->orderBy('start_time')
            ->paginate($filtros['por_pagina'] ?? 15);

        $doMes = fn () => Appointment::forTenant()
            ->whereMonth('date', now()->month)->whereYear('date', now()->year)
            ->where('status', 'completed');

        return response()->json([
            'data' => collect($lista->items())->map(fn (Appointment $m) => $this->linha($m))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'hoje' => Appointment::forTenant()->forDate(today())->count(),
                'por_atender' => Appointment::forTenant()->upcoming()->count(),
                // O MÊS E O ANO: sem o ano, Setembro conta Setembro de todos
                // os anos que a base tiver.
                'concluidas_no_mes' => $doMes()->count(),
                'receita_do_mes' => (float) $doMes()->sum('total'),
                'online' => Appointment::forTenant()->onlineBooking()->upcoming()->count(),
                'no_sistema' => Appointment::forTenant()->systemBooking()->upcoming()->count(),
            ],
        ]);
    }

    /** O calendário: um dia, uma semana ou um mês, já agrupado por data. */
    public function calendario(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.appointments.view');

        $filtros = $request->validate([
            'dia' => ['nullable', 'date'],
            'vista' => ['nullable', Rule::in(['dia', 'semana', 'mes'])],
            'profissional' => ['nullable', 'integer'],
        ]);

        $data = Carbon::parse($filtros['dia'] ?? today()->toDateString());
        $vista = $filtros['vista'] ?? 'mes';

        [$inicio, $fim] = match ($vista) {
            'dia' => [$data->copy()->startOfDay(), $data->copy()->endOfDay()],
            'semana' => [$data->copy()->startOfWeek(), $data->copy()->endOfWeek()],
            // O mês desenha-se em semanas inteiras: sem isso, a primeira linha
            // do quadro ficava com metade dos dias em branco.
            default => [$data->copy()->startOfMonth()->startOfWeek(), $data->copy()->endOfMonth()->endOfWeek()],
        };

        $marcacoes = Appointment::forTenant()
            ->with(['client:id,name', 'professional:id,name', 'services.service'])
            ->whereBetween('date', [$inicio->toDateString(), $fim->toDateString()])
            ->when($filtros['profissional'] ?? null, fn ($q, $p) => $q->where('professional_id', $p))
            ->orderBy('date')->orderBy('start_time')
            ->get();

        return response()->json([
            'vista' => $vista,
            'dia' => $data->toDateString(),
            'de' => $inicio->toDateString(),
            'ate' => $fim->toDateString(),
            'dias' => $marcacoes->groupBy(fn (Appointment $m) => $m->date->format('Y-m-d'))
                ->map(fn ($doDia) => $doDia->map(fn (Appointment $m) => $this->linha($m))->values()),
        ]);
    }

    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'salon.appointments.view');

        $m = Appointment::forTenant()
            ->with(['client', 'professional', 'services.service'])
            ->findOrFail($id);

        return response()->json(['data' => $this->linha($m, true)]);
    }

    private function linha(Appointment $m, bool $completa = false): array
    {
        $base = [
            'id' => $m->id,
            'numero' => $m->appointment_number,
            'client_id' => $m->client_id,
            'cliente' => $m->client?->name ?? __('Sem cliente'),
            'telefone' => $m->client?->phone,
            'professional_id' => $m->professional_id,
            'profissional' => $m->professional?->name,
            'dia' => $m->date?->format('Y-m-d'),
            'inicio' => $m->start_time?->format('H:i'),
            'fim' => $m->end_time?->format('H:i'),
            'duracao' => (int) $m->total_duration,
            'estado' => $m->status,
            'estado_rotulo' => __(Appointment::STATUSES[$m->status] ?? $m->status),
            'origem' => $m->source,
            'origem_rotulo' => __(Appointment::SOURCES[$m->source] ?? $m->source),
            'total' => (float) $m->total,
            'observacoes' => $m->notes,
            'servicos' => $m->services->map(fn ($s) => [
                'id' => $s->service_id,
                'nome' => $s->service?->name ?? '—',
                'duracao' => (int) $s->duration,
                'preco' => (float) $s->price,
            ])->values(),
            // O QUE SE PODE FAZER A SEGUIR — e mais nada. Sem isto, o ecrã
            // mostrava os botões todos e dava para concluir uma cancelada.
            'pode' => collect($this->marcacoes->seguintes($m))
                ->map(fn ($e) => ['valor' => $e, 'rotulo' => __(Appointment::STATUSES[$e])])->values(),
        ];

        if (! $completa) {
            return $base;
        }

        return $base + [
            'chegou_em' => $m->arrived_at?->toIso8601String(),
            'comecou_em' => $m->started_at?->toIso8601String(),
            'acabou_em' => $m->completed_at?->toIso8601String(),
            'duracao_real' => $m->actual_duration,
            'espera' => $m->wait_time,
            'motivo_do_cancelamento' => $m->cancellation_reason,
        ];
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, $id ? 'salon.appointments.edit' : 'salon.appointments.create');

        $tenantId = activeTenantId();

        /*
         * O CLIENTE É OBRIGATÓRIO — e sempre foi, só que ninguém dizia.
         *
         * `salon_appointments.client_id` é NOT NULL. O ecrã em Livewire dava-o
         * como opcional («nullable» na validação), e marcar sem cliente
         * rebentava com «Column 'client_id' cannot be null»: um erro de base de
         * dados à frente de quem estava ao telefone a marcar. A regra certa é a
         * que a tabela sempre teve, dita a tempo — e o formulário tem uma porta
         * para criar a cliente ali mesmo.
         */
        $dados = $request->validate([
            'client_id' => ['required', Rule::exists('invoicing_clients', 'id')->where('tenant_id', $tenantId)],
            'professional_id' => ['required', Rule::exists('salon_professionals', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'string', 'max:8'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', Rule::in(array_keys(Appointment::SOURCES))],
        ], [], [
            'client_id' => __('cliente'), 'professional_id' => __('profissional'), 'date' => __('data'),
            'start_time' => __('hora'), 'service_ids' => __('serviços'),
        ]);

        $existente = $id ? Appointment::forTenant()->findOrFail($id) : null;

        try {
            $m = $this->marcacoes->guardar($dados, $tenantId, $request->user()?->id, $existente);
        } catch (\Throwable $e) {
            $this->recusa($e);
        }

        return response()->json([
            'message' => $id ? __('Marcação actualizada.') : __('Marcação criada.'),
            'data' => $this->linha($m),
        ], $id ? 200 : 201);
    }

    public function estado(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate([
            'estado' => ['required', Rule::in(array_keys(Appointment::STATUSES))],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $this->exigir($request, in_array($dados['estado'], ['cancelled', 'no_show'], true)
            ? 'salon.appointments.delete'
            : 'salon.appointments.edit');

        $m = Appointment::forTenant()->findOrFail($id);

        try {
            $m = $this->marcacoes->estado($m, $dados['estado'], $dados['motivo'] ?? null);
        } catch (\Throwable $e) {
            $this->recusa($e);
        }

        return response()->json([
            'message' => __('Marcação em :estado.', ['estado' => mb_strtolower(__(Appointment::STATUSES[$dados['estado']]))]),
            'data' => $this->linha($m),
        ]);
    }

    /**
     * O CLIENTE CRIADO SEM SAIR DA MARCAÇÃO.
     *
     * Quem liga a marcar raramente já está no sistema, e mandar o empregado ao
     * ecrã dos clientes a meio de uma chamada é perder a marcação.
     */
    public function clienteRapido(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.clients.create');

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'nif' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
        ], [], ['name' => __('nome')]);

        $cliente = Client::create([
            'tenant_id' => activeTenantId(),
            'name' => $dados['name'],
            'phone' => $dados['phone'] ?? null,
            'mobile' => $dados['phone'] ?? null,
            'email' => $dados['email'] ?? null,
            'nif' => ($dados['nif'] ?? '') ?: '999999999',
            'address' => $dados['address'] ?? null,
            'city' => $dados['city'] ?? null,
            'country' => $dados['country'] ?? 'AO',
            /*
             * A coluna é um `enum('pessoa_fisica','pessoa_juridica')`. Escrever
             * 'particular' dava «Data truncated for column type» e o cliente
             * simplesmente NÃO era criado.
             */
            'type' => 'pessoa_fisica',
            'is_active' => true,
        ]);

        return response()->json([
            'message' => __('Cliente criado: :nome', ['nome' => $cliente->name]),
            'data' => ['valor' => (string) $cliente->id, 'rotulo' => $cliente->name],
        ], 201);
    }

    /** A procura de clientes, para a caixa da marcação. */
    public function clientes(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.appointments.view');

        $termo = trim((string) $request->string('procura'));

        return response()->json([
            'data' => Client::forTenant()
                ->when($termo !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$termo}%")
                    ->orWhere('phone', 'like', "%{$termo}%")
                    ->orWhere('email', 'like', "%{$termo}%")))
                ->orderBy('name')->limit(15)->get(['id', 'name', 'phone'])
                ->map(fn (Client $c) => [
                    'valor' => (string) $c->id,
                    'rotulo' => $c->phone ? $c->name.' · '.$c->phone : $c->name,
                ])->values(),
        ]);
    }
}
