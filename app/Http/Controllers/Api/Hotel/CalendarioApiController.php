<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\RoomType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * O CALENDÁRIO DE RESERVAS — a planta do hotel ao longo do tempo.
 *
 * Uma linha por quarto, uma coluna por dia, e cada estada é uma barra que
 * atravessa as noites que ocupa. É o ecrã onde se vê o que a lista não mostra:
 * os buracos.
 *
 * O QUE ESTA MIGRAÇÃO CORRIGE:
 *
 *  • O NOME DO HÓSPEDE lia a ficha antiga (`hotel_guests`), que está vazia:
 *    todas as barras diziam «Sem hóspede».
 *  • A RESERVA RÁPIDA criava um `Hotel\Guest` — nessa mesma ficha morta — e
 *    deixava `client_id` a nulo. A estada nascia SEM ADQUIRENTE, e no
 *    check-out não havia a quem facturar. Agora a reserva rápida passa pela
 *    porta das reservas, que exige o hóspede.
 *  • ARRASTAR UMA BARRA não verificava nada: mudava o quarto e as datas sem
 *    perguntar se o destino estava livre, e sem olhar ao tipo. Duas estadas
 *    ficavam por cima uma da outra, e o conflito só aparecia ao balcão.
 *  • UMA RESERVA SEM QUARTO aparecia em TODOS os quartos do seu tipo. Cinco
 *    barras para uma estada, e cinco quartos a parecer vendidos.
 */
class CalendarioApiController extends Controller
{
    /** Uma cor por tipo de quarto, estável — a coluna `color` nunca existiu. */
    private const CORES_DOS_TIPOS = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#14b8a6'];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.view');

        return response()->json([
            'estados' => collect(Reservation::STATUSES)
                ->map(fn ($r, $v) => ['valor' => (string) $v, 'rotulo' => __($r)])->values(),
            'tipos_de_quarto' => RoomType::where('tenant_id', activeTenantId())->where('is_active', true)
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn (RoomType $t) => ['valor' => (string) $t->id, 'rotulo' => $t->name])->values(),
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('hotel.reservations.create'),
                'pode_editar' => (bool) $request->user()?->can('hotel.reservations.edit'),
            ],
        ]);
    }

    /** A grelha: os dias em cima, os quartos à esquerda, as barras no meio. */
    public function grelha(Request $request): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.view');

        $filtros = $request->validate([
            'dia' => ['nullable', 'date'],
            'vista' => ['nullable', Rule::in(['semana', 'mes'])],
            'tipo_de_quarto' => ['nullable', 'integer'],
            'estado' => ['nullable', Rule::in(array_keys(Reservation::STATUSES))],
        ]);

        $tenantId = activeTenantId();
        $vista = $filtros['vista'] ?? 'mes';
        $base = Carbon::parse($filtros['dia'] ?? today());

        $de = $vista === 'semana' ? $base->copy()->startOfWeek() : $base->copy()->startOfMonth();
        $ate = $vista === 'semana' ? $de->copy()->endOfWeek() : $de->copy()->endOfMonth();

        $dias = collect(CarbonPeriod::create($de, $ate))->map(fn (Carbon $d) => [
            'dia' => $d->toDateString(),
            'numero' => $d->day,
            'nome' => $d->locale(app()->getLocale())->shortDayName,
            'hoje' => $d->isToday(),
            'fim_de_semana' => $d->isWeekend(),
            'passado' => $d->isPast() && ! $d->isToday(),
        ])->values();

        $quartos = Room::where('tenant_id', $tenantId)->where('is_active', true)
            ->with('roomType:id,name')
            ->when($filtros['tipo_de_quarto'] ?? null, fn ($q, $t) => $q->where('room_type_id', $t))
            ->orderBy('floor')->orderBy('number')->get();

        $estadas = Reservation::where('tenant_id', $tenantId)
            ->with(['client:id,name', 'roomType:id,name'])
            // As que TOCAM no período: as que entram, as que saem, e as que
            // atravessam o mês inteiro sem entrar nem sair dentro dele.
            ->where(fn ($q) => $q
                ->whereBetween('check_in_date', [$de, $ate])
                ->orWhereBetween('check_out_date', [$de, $ate])
                ->orWhere(fn ($w) => $w->where('check_in_date', '<=', $de)->where('check_out_date', '>=', $ate)))
            ->where('status', '!=', Reservation::STATUS_CANCELLED)
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->get();

        $totalDeDias = $de->diffInDays($ate) + 1;

        $porQuarto = $estadas->whereNotNull('room_id')->groupBy('room_id');

        return response()->json([
            'periodo' => ['de' => $de->toDateString(), 'ate' => $ate->toDateString(), 'vista' => $vista],
            'dias' => $dias,
            'quartos' => $quartos->map(fn (Room $q) => [
                'id' => $q->id,
                'numero' => $q->number,
                'piso' => $q->floor,
                'tipo' => $q->roomType?->name ?? '—',
                /*
                 * A COR DO TIPO DE QUARTO.
                 *
                 * O ecrã de sempre lia `roomType->color` — uma coluna que
                 * NUNCA EXISTIU — e caía sempre no mesmo indigo: os tipos de
                 * quarto eram todos da mesma cor num ecrã que existe para os
                 * distinguir. Sai do id, para o mesmo tipo ter sempre a mesma.
                 */
                'cor' => self::CORES_DOS_TIPOS[($q->room_type_id ?? 0) % count(self::CORES_DOS_TIPOS)],
                'estado' => $q->status,
                'barras' => ($porQuarto->get($q->id) ?? collect())
                    ->map(fn (Reservation $r) => $this->barra($r, $de, $totalDeDias))->values(),
            ])->values(),
            /*
             * AS ESTADAS SEM QUARTO ficam numa faixa à parte, e não repetidas
             * em cima de cada quarto do tipo. O ecrã de sempre punha a mesma
             * reserva em todas as linhas do tipo — cinco barras para uma
             * estada, e cinco quartos a parecer vendidos.
             */
            'por_atribuir' => $estadas->whereNull('room_id')
                ->map(fn (Reservation $r) => $this->barra($r, $de, $totalDeDias) + [
                    'tipo_de_quarto' => $r->roomType?->name,
                ])->values(),
            'resumo' => $this->resumo($tenantId, $de, $ate),
        ]);
    }

    /**
     * ARRASTAR UMA BARRA — mudar de quarto, ou de dia.
     *
     * O ecrã de sempre gravava sem perguntar nada: nem se o destino estava
     * livre, nem se era do tipo certo. Duas estadas ficavam por cima uma da
     * outra, e o conflito só aparecia com os dois hóspedes ao balcão.
     */
    public function mover(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hotel.reservations.edit');

        $dados = $request->validate([
            'quarto' => ['required', 'integer'],
            'dia' => ['required', 'date'],
        ]);

        $tenantId = activeTenantId();

        $reserva = Reservation::where('tenant_id', $tenantId)->findOrFail($id);

        // QUEM JÁ ENTROU NÃO SE ARRASTA. Mudar as datas de uma estada a
        // decorrer reescreve o que já aconteceu — e o quarto onde o hóspede
        // está a dormir passava a ser outro.
        abort_if(
            ! in_array($reserva->status, [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED], true),
            422, __('Só se movem reservas por confirmar ou confirmadas.')
        );

        $quarto = Room::where('tenant_id', $tenantId)->find($dados['quarto']);

        abort_unless($quarto, 422, __('Esse quarto não é desta casa.'));

        $entrada = Carbon::parse($dados['dia'])->toDateString();
        $saida = Carbon::parse($dados['dia'])->addDays(max(1, (int) $reserva->nights))->toDateString();

        if (! $quarto->isAvailableForDates($entrada, $saida, $reserva->id)) {
            throw ValidationException::withMessages([
                'quarto' => __('O quarto :n já está reservado nestas datas. Escolha outro quarto ou outras datas.', [
                    'n' => $quarto->number,
                ]),
            ]);
        }

        $reserva->update([
            'room_id' => $quarto->id,
            // O TIPO SEGUE O QUARTO. Mover uma estada de um duplo para uma
            // suite sem mudar o tipo deixava a reserva a dizer «duplo» num
            // quarto que não é — e a taxa por noite continuava a do duplo.
            'room_type_id' => $quarto->room_type_id,
            'check_in_date' => $entrada,
            'check_out_date' => $saida,
        ]);

        return response()->json([
            'message' => __('Reserva movida para o quarto :n.', ['n' => $quarto->number]),
        ]);
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function barra(Reservation $r, Carbon $de, int $totalDeDias): array
    {
        $entrada = Carbon::parse($r->check_in_date);
        $saida = Carbon::parse($r->check_out_date);

        // A barra ocupa as NOITES, e não os dias: quem entra a 3 e sai a 5
        // dorme duas noites e a barra tem dois dias de largura.
        $inicio = max(0, (int) $de->diffInDays($entrada, false));
        $fim = min($totalDeDias, (int) $de->diffInDays($saida, false));

        return [
            'id' => $r->id,
            'numero' => $r->reservation_number,
            // O NOME PELA FICHA CERTA: a antiga está vazia e todas as barras
            // diziam «Sem hóspede».
            'hospede' => $r->nome_do_hospede,
            'entrada' => $entrada->toDateString(),
            'saida' => $saida->toDateString(),
            'noites' => (int) $r->nights,
            'adultos' => (int) $r->adults,
            'criancas' => (int) $r->children,
            'estado' => $r->status,
            'estado_rotulo' => __(Reservation::STATUSES[$r->status] ?? (string) $r->status),
            'estado_de_pagamento' => $r->payment_status,
            'fonte' => $r->source,
            'total' => (float) $r->total,
            'inicio' => $inicio,
            'largura' => max(1, $fim - $inicio),
            'vem_de_tras' => $entrada->lt($de),
            'segue_para_a_frente' => $saida->gt($de->copy()->addDays($totalDeDias - 1)),
        ];
    }

    private function resumo(int $tenantId, Carbon $de, Carbon $ate): array
    {
        $quartos = Room::where('tenant_id', $tenantId)->where('is_active', true)->count();

        $doPeriodo = Reservation::where('tenant_id', $tenantId)
            ->whereBetween('check_in_date', [$de, $ate])
            ->where('status', '!=', Reservation::STATUS_CANCELLED)
            ->selectRaw('COUNT(*) as quantas, COALESCE(SUM(total), 0) as receita')
            ->first();

        $ocupados = Reservation::where('tenant_id', $tenantId)
            ->where('check_in_date', '<=', today())
            ->where('check_out_date', '>', today())
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->count();

        return [
            'quartos' => $quartos,
            'reservas' => (int) ($doPeriodo->quantas ?? 0),
            'receita' => (float) ($doPeriodo->receita ?? 0),
            'entram_hoje' => Reservation::where('tenant_id', $tenantId)
                ->whereDate('check_in_date', today())
                ->whereIn('status', [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED])->count(),
            'saem_hoje' => Reservation::where('tenant_id', $tenantId)
                ->whereDate('check_out_date', today())
                ->where('status', Reservation::STATUS_CHECKED_IN)->count(),
            'ocupados' => $ocupados,
            'ocupacao' => $quartos > 0 ? (int) round($ocupados / $quartos * 100) : 0,
        ];
    }
}
