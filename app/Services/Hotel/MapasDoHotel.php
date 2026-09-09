<?php

namespace App\Services\Hotel;

use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * OS MAPAS DO HOTEL — e os três números que a hotelaria pergunta.
 *
 * OCUPAÇÃO, ADR e RevPAR não são estatística decorativa: são a linguagem do
 * negócio. A ocupação diz quantos quartos se venderam; o ADR diz a que preço
 * médio; o RevPAR multiplica os dois e é o que se compara com o hotel do lado.
 * Vêm por cima de qualquer mapa, porque é a primeira coisa que se olha.
 *
 * Cada mapa DECLARA AS SUAS COLUNAS, como os da oficina: o ecrã desenha uma
 * tabela só para todos, e o papel e o Excel saem com o que estava no ecrã.
 *
 * O QUE MUDA EM RELAÇÃO AO ECRÃ EM BLADE:
 *
 *  · O MAPA DE HÓSPEDES lia `hotel_guests`, que está vazia: as reservas ligam
 *    `client_id`. O mapa mostrava sempre zero hóspedes, zero nacionalidades e
 *    zero repetentes.
 *  · O PAPEL E O EXCEL EXISTEM. Os dois botões respondiam «Exportação em
 *    desenvolvimento».
 */
final class MapasDoHotel
{
    public const MAPAS = [
        'ocupacao' => ['rotulo' => 'Ocupação', 'icone' => 'fa-chart-line'],
        'receita_por_dia' => ['rotulo' => 'Receita por dia', 'icone' => 'fa-calendar-day'],
        'receita_por_tipo' => ['rotulo' => 'Receita por tipo de quarto', 'icone' => 'fa-bed'],
        'receita_por_origem' => ['rotulo' => 'Receita por origem', 'icone' => 'fa-share-nodes'],
        'hospedes' => ['rotulo' => 'Hóspedes', 'icone' => 'fa-user-group'],
    ];

    /** De onde vem a reserva — o mesmo vocabulário do ecrã de sempre. */
    public const ORIGENS = [
        'direct' => 'Directa',
        'online' => 'Site da casa',
        'phone' => 'Telefone',
        'walkin' => 'Ao balcão',
        'booking' => 'Booking.com',
        'expedia' => 'Expedia',
        'airbnb' => 'Airbnb',
    ];

    /**
     * OS ESTADOS QUE CONTAM COMO QUARTO VENDIDO.
     *
     * Uma reserva cancelada não ocupou nada e não rendeu nada; uma por
     * confirmar ainda pode não acontecer, mas está a bloquear o quarto — e é
     * por isso que conta na ocupação. Era esta a regra do ecrã de sempre.
     */
    public const VENDIDAS = ['confirmed', 'checked_in', 'checked_out'];

    public static function existe(string $mapa): bool
    {
        return array_key_exists($mapa, self::MAPAS);
    }

    /**
     * Um mapa inteiro: os números do topo, as colunas, as linhas e os totais.
     *
     * @return array{kpis: array, colunas: array, linhas: array, totais: ?array, nada: ?string, periodo: array}
     */
    public function mapa(string $qual, int $tenantId, array $filtros): array
    {
        $de = Carbon::parse($filtros['de'] ?? now()->startOfMonth())->startOfDay();
        $ate = Carbon::parse($filtros['ate'] ?? now())->endOfDay();
        $tipo = $filtros['tipo_de_quarto'] ?? null;

        $resultado = match ($qual) {
            'ocupacao' => $this->ocupacao($tenantId, $de, $ate, $tipo),
            'receita_por_dia' => $this->receitaPorDia($tenantId, $de, $ate, $tipo),
            'receita_por_tipo' => $this->receitaPorTipo($tenantId, $de, $ate, $tipo),
            'receita_por_origem' => $this->receitaPorOrigem($tenantId, $de, $ate, $tipo),
            'hospedes' => $this->hospedes($tenantId, $de, $ate, $tipo),
        };

        return $resultado + [
            'kpis' => $this->kpis($tenantId, $de, $ate, $tipo),
            'totais' => null,
            'nada' => null,
            'periodo' => ['de' => $de->toDateString(), 'ate' => $ate->toDateString()],
        ];
    }

    /**
     * OS NÚMEROS DO TOPO.
     *
     *  · NOITES VENDIDAS — a soma das noites das reservas que tocam o período;
     *  · NOITES DISPONÍVEIS — quartos × dias, que é o tecto;
     *  · OCUPAÇÃO — a razão entre as duas;
     *  · ADR — quanto rendeu, em média, cada noite vendida;
     *  · RevPAR — quanto rendeu, em média, cada noite DISPONÍVEL. É este que
     *    se compara: um hotel meio vazio a preço alto e um cheio a preço baixo
     *    têm ADR muito diferentes e RevPAR parecido.
     */
    private function kpis(int $tenantId, Carbon $de, Carbon $ate, ?int $tipo): array
    {
        // OS DIAS SÃO DIAS INTEIROS. O `ate` vem no FIM do último dia, e a
        // diferença dava 9,99 — que truncava para 9 e fazia o tecto de noites
        // disponíveis ficar um dia curto.
        $dias = max(1, $de->copy()->startOfDay()->diffInDays($ate->copy()->startOfDay()) + 1);

        $quartos = Room::where('tenant_id', $tenantId)
            ->when($tipo, fn ($q) => $q->where('room_type_id', $tipo))
            ->count();

        $doPeriodo = fn () => Reservation::where('tenant_id', $tenantId)
            ->whereIn('status', self::VENDIDAS)
            ->where('check_in_date', '<=', $ate)
            ->where('check_out_date', '>=', $de)
            ->when($tipo, fn ($q) => $q->where('room_type_id', $tipo));

        $noitesVendidas = (float) $doPeriodo()->sum('nights');
        $receita = (float) $doPeriodo()->sum('total');
        $noitesDisponiveis = $quartos * $dias;

        return [
            'quartos' => $quartos,
            'dias' => $dias,
            'noites_vendidas' => $noitesVendidas,
            'noites_disponiveis' => $noitesDisponiveis,
            'ocupacao' => $noitesDisponiveis > 0 ? round($noitesVendidas / $noitesDisponiveis * 100, 1) : 0.0,
            'receita' => $receita,
            'adr' => $noitesVendidas > 0 ? round($receita / $noitesVendidas, 2) : 0.0,
            'revpar' => $noitesDisponiveis > 0 ? round($receita / $noitesDisponiveis, 2) : 0.0,
            'reservas' => Reservation::where('tenant_id', $tenantId)
                ->where('check_in_date', '<=', $ate)->where('check_out_date', '>=', $de)
                ->when($tipo, fn ($q) => $q->where('room_type_id', $tipo))->count(),
            'chegadas' => Reservation::where('tenant_id', $tenantId)
                ->whereBetween('check_in_date', [$de, $ate])
                ->when($tipo, fn ($q) => $q->where('room_type_id', $tipo))->count(),
            'saidas' => Reservation::where('tenant_id', $tenantId)
                ->whereBetween('check_out_date', [$de, $ate])
                ->when($tipo, fn ($q) => $q->where('room_type_id', $tipo))->count(),
            'canceladas' => Reservation::where('tenant_id', $tenantId)
                ->where('status', 'cancelled')
                ->whereBetween('created_at', [$de, $ate])
                ->when($tipo, fn ($q) => $q->where('room_type_id', $tipo))->count(),
        ];
    }

    /* ─── Ocupação ────────────────────────────────────────────────────── */

    private function ocupacao(int $tenantId, Carbon $de, Carbon $ate, ?int $tipo): array
    {
        $quartos = Room::where('tenant_id', $tenantId)
            ->when($tipo, fn ($q) => $q->where('room_type_id', $tipo))
            ->count();

        /*
         * UMA CONSULTA SÓ, e não uma por dia.
         *
         * O ecrã de sempre corria um `count()` por cada dia do intervalo: um
         * mês eram trinta e uma consultas, um ano trezentas e sessenta e cinco.
         */
        $reservas = Reservation::where('tenant_id', $tenantId)
            ->whereIn('status', self::VENDIDAS)
            ->where('check_in_date', '<=', $ate)
            ->where('check_out_date', '>=', $de)
            ->when($tipo, fn ($q) => $q->where('room_type_id', $tipo))
            ->get(['check_in_date', 'check_out_date']);

        $linhas = [];
        $quando = $de->copy();

        while ($quando <= $ate) {
            // A NOITE DE SAÍDA NÃO CONTA: quem sai de manhã liberta o quarto.
            $ocupados = $reservas->filter(
                fn ($r) => $r->check_in_date <= $quando && $r->check_out_date > $quando
            )->count();

            $linhas[] = [
                'dia' => $quando->toDateString(),
                'ocupados' => $ocupados,
                'livres' => max(0, $quartos - $ocupados),
                'taxa' => $quartos > 0 ? round($ocupados / $quartos * 100, 1) : 0.0,
            ];

            $quando->addDay();
        }

        return [
            'colunas' => [
                ['chave' => 'dia', 'rotulo' => 'Dia', 'formato' => 'data'],
                ['chave' => 'ocupados', 'rotulo' => 'Ocupados', 'formato' => 'numero'],
                ['chave' => 'livres', 'rotulo' => 'Livres', 'formato' => 'numero'],
                ['chave' => 'taxa', 'rotulo' => 'Ocupação', 'formato' => 'percentagem'],
            ],
            'linhas' => $linhas,
            'nada' => $quartos === 0 ? __('Ainda não há quartos registados.') : null,
        ];
    }

    /* ─── Receita ─────────────────────────────────────────────────────── */

    private function receitaPorDia(int $tenantId, Carbon $de, Carbon $ate, ?int $tipo): array
    {
        $linhas = Reservation::where('tenant_id', $tenantId)
            ->whereIn('status', self::VENDIDAS)
            ->whereBetween('check_in_date', [$de, $ate])
            ->when($tipo, fn ($q) => $q->where('room_type_id', $tipo))
            ->groupBy('dia')
            ->selectRaw('DATE(check_in_date) as dia, COUNT(*) as estadas, SUM(total) as receita, SUM(paid_amount) as pago')
            ->orderBy('dia')
            ->get();

        return [
            'colunas' => [
                ['chave' => 'dia', 'rotulo' => 'Dia', 'formato' => 'data'],
                ['chave' => 'estadas', 'rotulo' => 'Estadas', 'formato' => 'numero'],
                ['chave' => 'receita', 'rotulo' => 'Receita', 'formato' => 'dinheiro'],
                ['chave' => 'pago', 'rotulo' => 'Pago', 'formato' => 'dinheiro'],
                ['chave' => 'por_receber', 'rotulo' => 'Por receber', 'formato' => 'dinheiro'],
            ],
            'linhas' => $linhas->map(fn ($l) => [
                'dia' => (string) $l->dia,
                'estadas' => (int) $l->estadas,
                'receita' => (float) $l->receita,
                'pago' => (float) $l->pago,
                'por_receber' => (float) $l->receita - (float) $l->pago,
            ])->all(),
            'totais' => [
                'dia' => __('Total'),
                'estadas' => (int) $linhas->sum('estadas'),
                'receita' => (float) $linhas->sum('receita'),
                'pago' => (float) $linhas->sum('pago'),
                'por_receber' => (float) $linhas->sum('receita') - (float) $linhas->sum('pago'),
            ],
            'nada' => $linhas->isEmpty() ? __('Não houve estadas neste período.') : null,
        ];
    }

    private function receitaPorTipo(int $tenantId, Carbon $de, Carbon $ate, ?int $tipo): array
    {
        $linhas = DB::table('hotel_reservations as r')
            ->leftJoin('hotel_room_types as t', 't.id', '=', 'r.room_type_id')
            ->where('r.tenant_id', $tenantId)
            ->whereNull('r.deleted_at')
            ->whereIn('r.status', self::VENDIDAS)
            ->whereBetween('r.check_in_date', [$de, $ate])
            ->when($tipo, fn ($q) => $q->where('r.room_type_id', $tipo))
            ->groupBy('t.id', 't.name')
            ->selectRaw('COALESCE(t.name, "—") as tipo, COUNT(*) as estadas, SUM(r.nights) as noites, SUM(r.total) as receita')
            ->orderByDesc('receita')
            ->get();

        return [
            'colunas' => [
                ['chave' => 'tipo', 'rotulo' => 'Tipo de quarto', 'formato' => 'texto'],
                ['chave' => 'estadas', 'rotulo' => 'Estadas', 'formato' => 'numero'],
                ['chave' => 'noites', 'rotulo' => 'Noites', 'formato' => 'numero'],
                ['chave' => 'receita', 'rotulo' => 'Receita', 'formato' => 'dinheiro'],
                ['chave' => 'media_por_noite', 'rotulo' => 'Média/noite', 'formato' => 'dinheiro'],
            ],
            'linhas' => $linhas->map(fn ($l) => [
                'tipo' => $l->tipo,
                'estadas' => (int) $l->estadas,
                'noites' => (float) $l->noites,
                'receita' => (float) $l->receita,
                'media_por_noite' => $l->noites > 0 ? round((float) $l->receita / (float) $l->noites, 2) : 0.0,
            ])->all(),
            'totais' => [
                'tipo' => __('Total'),
                'estadas' => (int) $linhas->sum('estadas'),
                'noites' => (float) $linhas->sum('noites'),
                'receita' => (float) $linhas->sum('receita'),
                'media_por_noite' => null,
            ],
            'nada' => $linhas->isEmpty() ? __('Não houve estadas neste período.') : null,
        ];
    }

    private function receitaPorOrigem(int $tenantId, Carbon $de, Carbon $ate, ?int $tipo): array
    {
        $linhas = Reservation::where('tenant_id', $tenantId)
            ->whereIn('status', self::VENDIDAS)
            ->whereBetween('check_in_date', [$de, $ate])
            ->when($tipo, fn ($q) => $q->where('room_type_id', $tipo))
            ->groupBy('source')
            ->selectRaw('source, COUNT(*) as estadas, SUM(total) as receita')
            ->orderByDesc('receita')
            ->get();

        return [
            'colunas' => [
                ['chave' => 'origem', 'rotulo' => 'Origem', 'formato' => 'texto'],
                ['chave' => 'estadas', 'rotulo' => 'Estadas', 'formato' => 'numero'],
                ['chave' => 'receita', 'rotulo' => 'Receita', 'formato' => 'dinheiro'],
            ],
            'linhas' => $linhas->map(fn ($l) => [
                'origem' => __(self::ORIGENS[$l->source] ?? ($l->source ?: 'Outra')),
                'estadas' => (int) $l->estadas,
                'receita' => (float) $l->receita,
            ])->all(),
            'totais' => [
                'origem' => __('Total'),
                'estadas' => (int) $linhas->sum('estadas'),
                'receita' => (float) $linhas->sum('receita'),
            ],
            'nada' => $linhas->isEmpty() ? __('Não houve estadas neste período.') : null,
        ];
    }

    /* ─── Hóspedes ────────────────────────────────────────────────────── */

    /**
     * QUEM CÁ FICOU, quantas vezes e quanto deixou.
     *
     * Lia `hotel_guests`, que está vazia: as reservas ligam `client_id`, e o
     * mapa mostrava sempre zero hóspedes e zero nacionalidades. Agora conta
     * pelo CLIENTE, que é a ficha que a reserva usa e a que a factura precisa.
     */
    private function hospedes(int $tenantId, Carbon $de, Carbon $ate, ?int $tipo): array
    {
        $linhas = DB::table('hotel_reservations as r')
            ->join('invoicing_clients as c', 'c.id', '=', 'r.client_id')
            ->where('r.tenant_id', $tenantId)
            ->whereNull('r.deleted_at')
            ->whereIn('r.status', self::VENDIDAS)
            ->whereBetween('r.check_in_date', [$de, $ate])
            ->when($tipo, fn ($q) => $q->where('r.room_type_id', $tipo))
            ->groupBy('c.id', 'c.name', 'c.nationality')
            ->selectRaw('c.name as nome, COALESCE(NULLIF(c.nationality, ""), "—") as nacionalidade,'
                . ' COUNT(*) as estadas, SUM(r.nights) as noites, SUM(r.total) as receita')
            ->orderByDesc('estadas')
            ->orderByDesc('receita')
            ->limit(100)
            ->get();

        return [
            'colunas' => [
                ['chave' => 'nome', 'rotulo' => 'Hóspede', 'formato' => 'texto'],
                ['chave' => 'nacionalidade', 'rotulo' => 'Nacionalidade', 'formato' => 'texto'],
                ['chave' => 'estadas', 'rotulo' => 'Estadas', 'formato' => 'numero'],
                ['chave' => 'noites', 'rotulo' => 'Noites', 'formato' => 'numero'],
                ['chave' => 'receita', 'rotulo' => 'Receita', 'formato' => 'dinheiro'],
            ],
            'linhas' => $linhas->map(fn ($l) => [
                'nome' => $l->nome,
                'nacionalidade' => $l->nacionalidade,
                'estadas' => (int) $l->estadas,
                'noites' => (float) $l->noites,
                'receita' => (float) $l->receita,
            ])->all(),
            'totais' => [
                'nome' => __('Total'),
                'nacionalidade' => null,
                'estadas' => (int) $linhas->sum('estadas'),
                'noites' => (float) $linhas->sum('noites'),
                'receita' => (float) $linhas->sum('receita'),
            ],
            'nada' => $linhas->isEmpty() ? __('Não houve hóspedes neste período.') : null,
        ];
    }
}
