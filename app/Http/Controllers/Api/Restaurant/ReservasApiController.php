<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Reservation;
use App\Models\Restaurant\Venue;
use App\Services\Restaurant\RestaurantReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS RESERVAS DE MESA.
 *
 * O serviço é que sabe as regras — a mesa tem de caber a gente toda, e duas
 * reservas não se sobrepõem na mesma mesa. Aqui valida-se a forma e confirma-se
 * que o estabelecimento e a mesa são desta empresa.
 *
 * AS TRANSIÇÕES TÊM PORTA PRÓPRIA e não são um `update` de uma coluna:
 * confirmar põe a mesa em «reservada», cancelar devolve-a a «livre». Deixar o
 * ecrã escrever o estado directamente era ficar com mesas reservadas para
 * reservas que já tinham sido canceladas.
 */
class ReservasApiController extends Controller
{
    public const ESTADOS = [
        'pending' => 'Por confirmar',
        'confirmed' => 'Confirmada',
        'seated' => 'Sentados',
        'completed' => 'Concluída',
        'cancelled' => 'Cancelada',
        'no_show' => 'Não compareceu',
    ];

    /** O que cada estado pode ser a seguir — o mesmo mapa do serviço. */
    private const TRANSICOES = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['seated', 'cancelled', 'no_show'],
        'seated' => ['completed'],
    ];

    public function __construct(private readonly RestaurantReservationService $reservas) {}

    /** @param  string|list<string>  $permissao  basta uma. */
    private function exigir(Request $request, string|array $permissao): void
    {
        abort_unless(self::temUma($request, (array) $permissao), 403, __('Sem permissão para esta operação.'));
    }

    /** @param  list<string>  $permissoes */
    private static function temUma(Request $request, array $permissoes): bool
    {
        foreach ($permissoes as $p) {
            if ($request->user()?->can($p)) {
                return true;
            }
        }

        return false;
    }

    /*
     * CANCELAR É EDITAR A RESERVA.
     *
     * `restaurant.reservations.cancel` nunca existiu na base — nem em produção
     * nem nos papéis por omissão — e um `can()` sobre uma permissão que não
     * existe é sempre falso: ninguém cancelava nem marcava a falta, nem o
     * Super Admin (auditoria de 2026-09-13). Vale a de editar, e a de cancelar
     * se um dia uma empresa a criar.
     */
    private const CANCELAR = ['restaurant.reservations.cancel', 'restaurant.reservations.edit'];

    private function recusa(\Throwable $e): never
    {
        throw ValidationException::withMessages(['geral' => [$e->getMessage()]]);
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.reservations.view');

        $tenantId = activeTenantId();

        return response()->json([
            'estabelecimentos' => Venue::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Venue $v) => [
                    'valor' => (string) $v->id,
                    'rotulo' => $v->name,
                    'mesas' => DiningTable::where('tenant_id', $tenantId)->where('venue_id', $v->id)
                        ->where('is_active', true)->orderBy('code')->get(['id', 'name', 'code', 'capacity'])
                        ->map(fn ($m) => [
                            'valor' => (string) $m->id,
                            'rotulo' => ($m->name ?: $m->code).' · '.__(':n lugares', ['n' => (int) $m->capacity]),
                            'lugares' => (int) $m->capacity,
                        ])->values(),
                ])->values(),
            'estados' => collect(self::ESTADOS)->map(fn ($r, $v) => ['valor' => (string) $v, 'rotulo' => __($r)])->values(),
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('restaurant.reservations.create'),
                'pode_editar' => (bool) $request->user()?->can('restaurant.reservations.edit'),
                'pode_cancelar' => self::temUma($request, self::CANCELAR),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.reservations.view');

        $filtros = $request->validate([
            'dia' => ['nullable', 'date'],
            'estabelecimento' => ['nullable', 'integer'],
            'estado' => ['nullable', Rule::in(array_keys(self::ESTADOS))],
        ]);

        $dia = $filtros['dia'] ?? now()->format('Y-m-d');

        $lista = Reservation::with(['table:id,name,code', 'venue:id,name'])
            ->whereDate('reserved_at', $dia)
            ->when($filtros['estabelecimento'] ?? null, fn ($q, $v) => $q->where('venue_id', $v))
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->orderBy('reserved_at')
            ->get();

        return response()->json([
            'dia' => $dia,
            'data' => $lista->map(fn (Reservation $r) => [
                'id' => $r->id,
                'numero' => $r->reservation_number,
                'nome' => $r->guest_name,
                'telefone' => $r->phone,
                'email' => $r->email,
                'pessoas' => (int) $r->guest_count,
                'mesa' => $r->table?->name ?? $r->table?->code,
                'table_id' => $r->table_id,
                'estabelecimento' => $r->venue?->name,
                'venue_id' => $r->venue_id,
                'quando' => $r->reserved_at?->toIso8601String(),
                'duracao' => (int) $r->duration_minutes,
                'estado' => $r->status,
                'estado_rotulo' => __(self::ESTADOS[$r->status] ?? $r->status),
                'observacoes' => $r->notes,
                'pode' => collect(self::TRANSICOES[$r->status] ?? [])
                    ->map(fn ($e) => ['valor' => $e, 'rotulo' => __(self::ESTADOS[$e])])->values(),
            ])->values(),
            'resumo' => collect(self::ESTADOS)->keys()
                ->mapWithKeys(fn ($e) => [$e => $lista->where('status', $e)->count()]),
            'pessoas' => (int) $lista->whereNotIn('status', ['cancelled', 'no_show'])->sum('guest_count'),
        ]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, $id ? 'restaurant.reservations.edit' : 'restaurant.reservations.create');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'venue_id' => ['required', Rule::exists('restaurant_venues', 'id')->where('tenant_id', $tenantId)],
            'table_id' => ['nullable', Rule::exists('restaurant_tables', 'id')->where('tenant_id', $tenantId)],
            'guest_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:100'],
            'reserved_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:30', 'max:720'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'guest_name' => __('nome'), 'guest_count' => __('pessoas'),
            'reserved_at' => __('data e hora'), 'duration_minutes' => __('duração'),
        ]);

        $existente = $id ? Reservation::where('tenant_id', $tenantId)->findOrFail($id) : null;

        try {
            $this->reservas->save($dados, $tenantId, $request->user()?->id, $existente);
        } catch (\Throwable $e) {
            $this->recusa($e);
        }

        return response()->json(['message' => __('Reserva guardada.')], $id ? 200 : 201);
    }

    public function estado(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate(['estado' => ['required', Rule::in(array_keys(self::ESTADOS))]]);

        $this->exigir($request, in_array($dados['estado'], ['cancelled', 'no_show'], true)
            ? self::CANCELAR
            : 'restaurant.reservations.edit');

        $reserva = Reservation::where('tenant_id', activeTenantId())->findOrFail($id);

        try {
            $this->reservas->changeStatus($reserva, $dados['estado'], activeTenantId());
        } catch (\Throwable $e) {
            $this->recusa($e);
        }

        return response()->json([
            'message' => __('Reserva em :estado.', ['estado' => mb_strtolower(__(self::ESTADOS[$dados['estado']]))]),
        ]);
    }
}
