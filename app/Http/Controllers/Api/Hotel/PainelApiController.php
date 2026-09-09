<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\MaintenanceOrder;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * O PAINEL DO HOTEL — quem chega hoje, quem sai, e como está a casa.
 *
 * A pergunta que traz alguém a este ecrã de manhã é «o que é que acontece
 * hoje»: por isso as chegadas e as saídas do dia vêm primeiro, e o MAPA DOS
 * QUARTOS — o desenho da casa, piso a piso, com a cor do estado — vem logo a
 * seguir. É esse mapa que a recepção olha antes de dizer «temos quarto».
 *
 * O QUE MUDA EM RELAÇÃO AO PAINEL EM BLADE:
 *
 *  · O NOME DO HÓSPEDE APARECE. O painel lia `->guest`, que aponta para
 *    `hotel_guests`; as reservas gravam `client_id` e deixam `guest_id` a
 *    nulo. Resultado: as chegadas do dia saíam sem o nome de ninguém.
 *  · Os valores em dinheiro só saem a quem pode ver relatórios — do lado do
 *    servidor, que é onde esconder um número é esconder mesmo.
 */
class PainelApiController extends Controller
{
    /** Os estados de um quarto, num sítio só. */
    public const ESTADOS_DO_QUARTO = [
        'available' => 'Livre',
        'occupied' => 'Ocupado',
        'reserved' => 'Reservado',
        'cleaning' => 'Em limpeza',
        'maintenance' => 'Em manutenção',
    ];

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('hotel.dashboard.view'), 403, __('Sem permissão para esta operação.'));

        $tenantId = activeTenantId();
        $veDinheiro = (bool) $request->user()?->can('hotel.reports.view');

        $quartos = Room::where('tenant_id', $tenantId)->where('is_active', true);

        $porEstado = (clone $quartos)
            ->selectRaw('status, COUNT(*) as quantos')->groupBy('status')->pluck('quantos', 'status');

        $total = (int) $porEstado->sum();
        $ocupados = (int) ($porEstado['occupied'] ?? 0);

        return response()->json([
            've_dinheiro' => $veDinheiro,
            'quartos' => [
                'total' => $total,
                'livres' => (int) ($porEstado['available'] ?? 0),
                'ocupados' => $ocupados,
                'manutencao' => (int) ($porEstado['maintenance'] ?? 0),
                'limpeza' => (int) ($porEstado['cleaning'] ?? 0),
                // A TAXA DE OCUPAÇÃO é o número que a direcção pergunta.
                'ocupacao' => $total > 0 ? round($ocupados / $total * 100, 1) : 0.0,
            ],
            'hoje' => [
                'chegadas' => $this->reservas(Reservation::where('tenant_id', $tenantId)
                    ->whereDate('check_in_date', today())
                    ->whereIn('status', ['pending', 'confirmed'])),
                'saidas' => $this->reservas(Reservation::where('tenant_id', $tenantId)
                    ->whereDate('check_out_date', today())
                    ->where('status', 'checked_in')),
                'hospedados' => $this->reservas(Reservation::where('tenant_id', $tenantId)
                    ->where('status', 'checked_in')),
            ],
            'por_decidir' => $this->reservas(Reservation::where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->orderBy('check_in_date')
                ->limit(10)),
            'proximas' => $this->reservas(Reservation::where('tenant_id', $tenantId)
                ->whereBetween('check_in_date', [today(), today()->addDays(7)])
                ->whereIn('status', ['pending', 'confirmed'])
                ->orderBy('check_in_date')
                ->limit(10)),
            'dinheiro' => $veDinheiro ? [
                'do_mes' => (float) Reservation::where('tenant_id', $tenantId)
                    ->whereMonth('check_out_date', now()->month)
                    ->whereYear('check_out_date', now()->year)
                    ->where('status', 'checked_out')->sum('total'),
                'por_receber' => (float) Reservation::where('tenant_id', $tenantId)
                    ->whereIn('status', ['checked_in', 'checked_out'])
                    ->where('payment_status', '!=', 'paid')
                    ->sum(DB::raw('total - paid_amount')),
            ] : null,
            /*
             * A MANUTENÇÃO ABERTA vem ao painel do hotel.
             *
             * Um quarto com uma fuga de água não se vende, e quem está na
             * recepção não abre o ecrã da manutenção para saber disso.
             */
            'manutencao' => [
                'abertas' => MaintenanceOrder::where('tenant_id', $tenantId)
                    ->whereNotIn('status', ['completed', 'cancelled'])->count(),
                'urgentes' => MaintenanceOrder::where('tenant_id', $tenantId)
                    ->where('priority', 'urgent')
                    ->whereNotIn('status', ['completed', 'cancelled'])->count(),
            ],
            'series' => [
                'mensal' => $this->receitaPorMes($tenantId, $veDinheiro),
                'ocupacao' => $this->ocupacaoPorDia($tenantId),
                'por_tipo' => $this->receitaPorTipo($tenantId, $veDinheiro),
                'estados' => [
                    'etiquetas' => $porEstado->keys()->map(fn ($e) => __(self::ESTADOS_DO_QUARTO[$e] ?? $e))->all(),
                    'chaves' => $porEstado->keys()->all(),
                    'valores' => $porEstado->values()->map(fn ($n) => (int) $n)->all(),
                ],
            ],
            'mapa' => $this->mapaDosQuartos($tenantId),
        ]);
    }

    /**
     * O MAPA DA CASA, piso a piso.
     *
     * É o desenho que a recepção olha antes de dizer «temos quarto». Cada
     * quadrado leva o número, o estado e — se estiver ocupado — quem lá está e
     * até quando.
     */
    private function mapaDosQuartos(int $tenantId): array
    {
        return Room::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with(['roomType:id,name', 'currentReservation.client:id,name', 'currentReservation.guest:id,name'])
            ->orderBy('floor')->orderBy('number')
            ->get()
            ->groupBy(fn (Room $q) => $q->floor ?: '—')
            ->map(fn ($quartos, $piso) => [
                'piso' => (string) $piso,
                'quartos' => $quartos->map(fn (Room $q) => [
                    'id' => $q->id,
                    'numero' => $q->number,
                    'tipo' => $q->roomType?->name,
                    'estado' => $q->status,
                    'estado_rotulo' => __(self::ESTADOS_DO_QUARTO[$q->status] ?? (string) $q->status),
                    'limpeza' => $q->housekeeping_status,
                    'hospede' => $q->currentReservation?->nome_do_hospede,
                    'ate' => $q->currentReservation?->check_out_date?->toDateString(),
                ])->values()->all(),
            ])->values()->all();
    }

    /** Um punhado de reservas, na forma que o painel mostra. */
    private function reservas($consulta): array
    {
        return $consulta
            ->with(['client:id,name', 'guest:id,name', 'room:id,number', 'roomType:id,name'])
            ->get()
            ->map(fn (Reservation $r) => [
                'id' => $r->id,
                'numero' => $r->reservation_number,
                // O NOME DE QUEM FICA. O painel lia `->guest`, que está a nulo
                // em tudo o que se cria hoje: as chegadas saíam sem nome.
                'hospede' => $r->nome_do_hospede,
                'quarto' => $r->room?->number,
                'tipo' => $r->roomType?->name,
                'entrada' => $r->check_in_date?->toDateString(),
                'saida' => $r->check_out_date?->toDateString(),
                'noites' => (int) $r->nights,
                'estado' => $r->status,
            ])->values()->all();
    }

    /**
     * A RECEITA DOS ÚLTIMOS 12 MESES.
     *
     * Num hotel a sazonalidade é o negócio: sem os doze meses não se distingue
     * um mau mês de uma época baixa, e são decisões opostas.
     */
    private function receitaPorMes(int $tenantId, bool $veDinheiro): array
    {
        $desde = now()->subMonths(11)->startOfMonth();

        $porMes = Reservation::where('tenant_id', $tenantId)
            ->where('status', 'checked_out')
            ->where('check_out_date', '>=', $desde)
            ->groupBy('mes')
            ->selectRaw("DATE_FORMAT(check_out_date, '%Y-%m') as mes, SUM(total) as total, COUNT(*) as quantas")
            ->get()->keyBy('mes');

        $etiquetas = [];
        $valores = [];

        for ($m = 0; $m < 12; $m++) {
            $quando = $desde->copy()->addMonths($m);
            $linha = $porMes[$quando->format('Y-m')] ?? null;

            $etiquetas[] = $quando->translatedFormat('M/y');
            // Sem permissão de relatórios, a série conta ESTADAS em vez de
            // kwanzas: a forma da curva é a informação.
            $valores[] = $linha ? ($veDinheiro ? (float) $linha->total : (int) $linha->quantas) : 0;
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /**
     * QUANTOS QUARTOS ESTIVERAM OCUPADOS EM CADA UM DOS ÚLTIMOS 30 DIAS.
     *
     * Conta-se por NOITE e não por reserva: uma estada de cinco noites ocupa
     * cinco dias, e contá-la uma vez no dia da chegada dava uma ocupação em
     * forma de serrote — picos nos check-in e vazio no meio.
     */
    private function ocupacaoPorDia(int $tenantId): array
    {
        $dias = 30;
        $desde = today()->subDays($dias - 1);

        $reservas = Reservation::where('tenant_id', $tenantId)
            ->whereIn('status', ['checked_in', 'checked_out', 'confirmed'])
            ->where('check_out_date', '>=', $desde)
            ->where('check_in_date', '<=', today())
            ->get(['check_in_date', 'check_out_date']);

        $etiquetas = [];
        $valores = [];

        for ($d = 0; $d < $dias; $d++) {
            $quando = $desde->copy()->addDays($d);

            $etiquetas[] = $quando->format('d/m');
            // A noite de saída não conta: quem sai de manhã liberta o quarto.
            $valores[] = $reservas->filter(fn ($r) => $r->check_in_date <= $quando && $r->check_out_date > $quando)->count();
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /** Quanto rende cada tipo de quarto — decide onde investir. */
    private function receitaPorTipo(int $tenantId, bool $veDinheiro): array
    {
        $linhas = DB::table('hotel_reservations as r')
            ->leftJoin('hotel_room_types as t', 't.id', '=', 'r.room_type_id')
            ->where('r.tenant_id', $tenantId)
            ->whereNull('r.deleted_at')
            ->where('r.status', 'checked_out')
            ->where('r.check_out_date', '>=', now()->subMonths(6))
            ->groupBy('t.id', 't.name')
            ->selectRaw('COALESCE(t.name, "—") as nome, SUM(r.total) as total, COUNT(*) as quantas')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores' => $linhas->map(fn ($l) => $veDinheiro ? (float) $l->total : (int) $l->quantas)->all(),
        ];
    }
}
