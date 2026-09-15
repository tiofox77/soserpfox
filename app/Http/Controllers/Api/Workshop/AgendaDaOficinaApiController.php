<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Workshop\Appointment;
use App\Models\Workshop\Bay;
use App\Models\Workshop\Mechanic;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrderHistory;
use App\Services\Workshop\OrdensDeServico;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A AGENDA DA OFICINA (15/09/2026, OF-05).
 *
 * Marca-se um carro para um elevador (ou baia) e um mecânico, das tantas às
 * tantas. Um lugar ou um mecânico não se marca duas vezes na mesma hora — o
 * pedido volta com o nome de quem já lá está. Quando o carro chega, «Chegou»
 * abre a ordem de serviço (e cria a viatura, se ainda não existia) e a marcação
 * fica ligada a ela.
 *
 * Ver pede a permissão de ver ordens; marcar a de criar; mudar a de editar.
 */
class AgendaDaOficinaApiController extends Controller
{
    public const ABRE = '08:00';
    public const FECHA = '18:00';

    public function __construct(private readonly OrdensDeServico $ordens) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function marcacao(int $id): Appointment
    {
        return Appointment::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');

        $dados = $request->validate([
            'de' => ['required', 'date'],
            'ate' => ['required', 'date', 'after_or_equal:de'],
        ]);

        $de = Carbon::parse($dados['de'])->startOfDay();
        $ate = Carbon::parse($dados['ate'])->endOfDay();
        abort_if($de->diffInDays($ate) > 45, 422, __('Escolha no máximo 45 dias.'));

        $tenantId = activeTenantId();
        Bay::garantirCatalogo($tenantId);

        $marcacoes = Appointment::with(['vehicle:id,plate,brand,model,owner_name,owner_phone', 'bay:id,name,color', 'mechanic:id,name', 'workOrder:id,order_number,status'])
            ->where('tenant_id', $tenantId)
            ->where('starts_at', '<=', $ate)->where('ends_at', '>=', $de)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $a) => self::paraEcra($a))
            ->values();

        return response()->json([
            'marcacoes' => $marcacoes,
            'lugares' => Bay::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')
                ->get()->map(fn (Bay $b) => ['valor' => (string) $b->id, 'rotulo' => $b->name, 'cor' => $b->color, 'tipo' => __(Bay::TIPOS[$b->kind] ?? $b->kind)])->values(),
            'mecanicos' => Mechanic::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($m) => ['valor' => (string) $m->id, 'rotulo' => $m->name])->values(),
            'estados' => collect(Appointment::ESTADOS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'horario' => ['abre' => self::ABRE, 'fecha' => self::FECHA],
            'pode_criar' => (bool) $request->user()?->can('workshop.work-orders.create'),
            'pode_editar' => (bool) $request->user()?->can('workshop.work-orders.edit'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.create');
        $dados = $this->validar($request, null);

        $marcacao = Appointment::create($dados + ['tenant_id' => activeTenantId(), 'status' => 'marcada', 'user_id' => auth()->id()]);

        return response()->json(['data' => self::paraEcra($marcacao->fresh(['vehicle', 'bay', 'mechanic'])), 'message' => __('Marcação para :quando gravada.', ['quando' => $marcacao->starts_at->format('d/m H:i')])], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $marcacao = $this->marcacao($id);

        abort_if($marcacao->status === 'chegou', 422, __('Esta marcação já passou a ordem de serviço.'));

        $marcacao->update($this->validar($request, $marcacao));

        return response()->json(['data' => self::paraEcra($marcacao->fresh(['vehicle', 'bay', 'mechanic'])), 'message' => __('Marcação actualizada.')]);
    }

    public function estado(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $marcacao = $this->marcacao($id);

        $dados = $request->validate(['estado' => ['required', Rule::in(['marcada', 'confirmada', 'faltou', 'cancelada'])]]);

        abort_if($marcacao->status === 'chegou', 422, __('Esta marcação já passou a ordem de serviço.'));

        // Voltar a «marcada» ou «confirmada» ocupa outra vez o lugar: não pode chocar com outra.
        if (in_array($dados['estado'], Appointment::OCUPAM, true)) {
            $this->semChoques($marcacao->tenant_id, $marcacao->bay_id, $marcacao->mechanic_id, $marcacao->starts_at, $marcacao->ends_at, $marcacao->id);
        }

        $marcacao->update(['status' => $dados['estado']]);

        return response()->json(['data' => self::paraEcra($marcacao->fresh(['vehicle', 'bay', 'mechanic'])), 'message' => __('Marcação: :estado.', ['estado' => __(Appointment::ESTADOS[$dados['estado']])])]);
    }

    /**
     * O CARRO CHEGOU — a marcação passa a ordem de serviço.
     *
     * Sem viatura na casa, cria-se uma com a matrícula, o nome e o telefone da
     * marcação (a marca e o modelo completam-se depois na ficha).
     */
    public function chegou(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.create');
        $marcacao = $this->marcacao($id);

        abort_if($marcacao->work_order_id, 422, __('Esta marcação já passou a ordem de serviço.'));
        abort_if(in_array($marcacao->status, ['cancelada'], true), 422, __('Uma marcação cancelada não abre ordem.'));

        $ordem = DB::transaction(function () use ($marcacao) {
            $tenantId = $marcacao->tenant_id;
            $viatura = $marcacao->vehicle_id ? Vehicle::where('tenant_id', $tenantId)->find($marcacao->vehicle_id) : null;

            if (! $viatura) {
                $matricula = mb_strtoupper(trim((string) $marcacao->plate));
                $viatura = Vehicle::where('tenant_id', $tenantId)->where('plate', $matricula)->first()
                    ?? Vehicle::createWithTenantNumber([
                        'tenant_id' => $tenantId,
                        'plate' => $matricula,
                        'owner_name' => $marcacao->customer_name ?: __('Por identificar'),
                        'owner_phone' => $marcacao->customer_phone,
                        'brand' => __('Por identificar'),
                        'model' => '—',
                        'status' => \App\Models\Workshop\VehicleStatus::padraoDe($tenantId),
                    ], 'vehicle_number', 'VEH-');
            }

            [$ordem] = $this->ordens->guardar([
                'vehicle_id' => $viatura->id,
                'mechanic_id' => $marcacao->mechanic_id,
                'received_at' => now()->toDateTimeString(),
                'scheduled_for' => $marcacao->starts_at->toDateTimeString(),
                'mileage_in' => (int) $viatura->mileage,
                'problem_description' => trim($marcacao->service . ($marcacao->notes ? "\n" . $marcacao->notes : '')),
                'priority' => 'normal',
            ], null, $tenantId);

            $marcacao->update(['status' => 'chegou', 'work_order_id' => $ordem->id, 'vehicle_id' => $viatura->id]);

            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                __('Aberta a partir da marcação de :quando.', ['quando' => $marcacao->starts_at->format('d/m/Y H:i')]));

            return $ordem;
        });

        return response()->json([
            'data' => self::paraEcra($marcacao->fresh(['vehicle', 'bay', 'mechanic', 'workOrder'])),
            'ordem_id' => $ordem->id,
            'message' => __('Ordem :numero aberta.', ['numero' => $ordem->order_number]),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $marcacao = $this->marcacao($id);

        abort_if($marcacao->work_order_id, 422, __('Esta marcação já passou a ordem de serviço: cancele a ordem, e não a marcação.'));
        $marcacao->delete();

        return response()->json(['message' => __('Marcação apagada.')]);
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function validar(Request $request, ?Appointment $actual): array
    {
        $dados = $request->validate([
            'vehicle_id' => ['nullable', 'integer'],
            'plate' => ['nullable', 'required_without:vehicle_id', 'string', 'max:20'],
            'customer_name' => ['nullable', 'required_without:vehicle_id', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'bay_id' => ['nullable', 'integer'],
            'mechanic_id' => ['nullable', 'integer'],
            'inicio' => ['required', 'date'],
            'duracao' => ['required', 'integer', 'min:15', 'max:720'],
            'service' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'plate.required_without' => __('Escolha a viatura ou escreva a matrícula.'),
            'customer_name.required_without' => __('Escolha a viatura ou escreva o nome do cliente.'),
        ]);

        $tenantId = activeTenantId();

        foreach (['vehicle_id' => Vehicle::class, 'bay_id' => Bay::class, 'mechanic_id' => Mechanic::class] as $campo => $modelo) {
            if (! empty($dados[$campo]) && ! $modelo::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($dados[$campo])->exists()) {
                throw ValidationException::withMessages([$campo => [__('Esse registo não é desta empresa.')]]);
            }
        }

        $inicio = Carbon::parse($dados['inicio']);
        $fim = $inicio->copy()->addMinutes((int) $dados['duracao']);

        $this->semChoques($tenantId, $dados['bay_id'] ?? null, $dados['mechanic_id'] ?? null, $inicio, $fim, $actual?->id);

        return [
            'vehicle_id' => ($dados['vehicle_id'] ?? null) ?: null,
            'plate' => ($dados['vehicle_id'] ?? null) ? null : mb_strtoupper(trim((string) ($dados['plate'] ?? ''))),
            'customer_name' => ($dados['vehicle_id'] ?? null) ? null : trim((string) ($dados['customer_name'] ?? '')),
            'customer_phone' => trim((string) ($dados['customer_phone'] ?? '')) ?: null,
            'bay_id' => ($dados['bay_id'] ?? null) ?: null,
            'mechanic_id' => ($dados['mechanic_id'] ?? null) ?: null,
            'starts_at' => $inicio,
            'ends_at' => $fim,
            'service' => trim($dados['service']),
            'notes' => trim((string) ($dados['notes'] ?? '')) ?: null,
        ];
    }

    /** Um lugar e um mecânico não se marcam duas vezes para a mesma hora. */
    private function semChoques(int $tenantId, $bayId, $mecanicoId, Carbon $inicio, Carbon $fim, ?int $excepto): void
    {
        $base = fn () => Appointment::with(['vehicle:id,plate', 'bay:id,name', 'mechanic:id,name'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', Appointment::OCUPAM)
            ->where('starts_at', '<', $fim)->where('ends_at', '>', $inicio)
            ->when($excepto, fn ($q) => $q->whereKeyNot($excepto));

        if ($bayId && ($choque = $base()->where('bay_id', $bayId)->first())) {
            throw ValidationException::withMessages(['bay_id' => [__(':lugar já está ocupado das :de às :ate (:quem).', [
                'lugar' => $choque->bay?->name, 'de' => $choque->starts_at->format('H:i'), 'ate' => $choque->ends_at->format('H:i'),
                'quem' => $choque->vehicle?->plate ?? $choque->plate,
            ])]]);
        }

        if ($mecanicoId && ($choque = $base()->where('mechanic_id', $mecanicoId)->first())) {
            throw ValidationException::withMessages(['mechanic_id' => [__(':mecanico já tem marcação das :de às :ate (:quem).', [
                'mecanico' => $choque->mechanic?->name, 'de' => $choque->starts_at->format('H:i'), 'ate' => $choque->ends_at->format('H:i'),
                'quem' => $choque->vehicle?->plate ?? $choque->plate,
            ])]]);
        }
    }

    public static function paraEcra(Appointment $a): array
    {
        return [
            'id' => $a->id,
            'inicio' => $a->starts_at->format('Y-m-d\TH:i'),
            'fim' => $a->ends_at->format('Y-m-d\TH:i'),
            'duracao' => (int) $a->starts_at->diffInMinutes($a->ends_at),
            'estado' => $a->status,
            'estado_rotulo' => __(Appointment::ESTADOS[$a->status] ?? $a->status),
            'servico' => $a->service,
            'notas' => $a->notes,
            'vehicle_id' => $a->vehicle_id,
            'matricula' => $a->vehicle?->plate ?? $a->plate,
            'viatura' => $a->vehicle ? trim("{$a->vehicle->brand} {$a->vehicle->model}") : null,
            'cliente' => $a->vehicle?->owner_name ?? $a->customer_name,
            'telefone' => $a->customer_phone ?? $a->vehicle?->owner_phone,
            'plate' => $a->plate,
            'customer_name' => $a->customer_name,
            'customer_phone' => $a->customer_phone,
            'bay_id' => $a->bay_id,
            'lugar' => $a->bay?->name,
            'cor' => $a->bay?->color,
            'mechanic_id' => $a->mechanic_id,
            'mecanico' => $a->mechanic?->name,
            'ordem_id' => $a->work_order_id,
            'ordem' => $a->workOrder?->order_number,
        ];
    }
}
