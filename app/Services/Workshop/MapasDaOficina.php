<?php

namespace App\Services\Workshop;

use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * OS CINCO MAPAS DA OFICINA, num sítio só.
 *
 * Cada mapa DECLARA AS SUAS COLUNAS e devolve as linhas já na forma em que se
 * lêem. É isso que faz o ecrã em React desenhar uma tabela só para os cinco, e
 * o papel e o Excel saírem exactamente com o que estava no ecrã — sem uma
 * terceira lista de colunas escrita à mão que diverge à primeira alteração.
 *
 * O QUE MUDA EM RELAÇÃO AO ECRÃ EM LIVEWIRE:
 *
 *  · O MAPA DE MECÂNICOS lia `hr_employees`. A coluna `mechanic_id` aponta
 *    para `workshop_mechanics` desde 2025-11-05, e a caixa de escolha do ecrã
 *    sempre ofereceu mecânicos da oficina: o mapa mostrava os funcionários do
 *    RH com os mesmos números — outras pessoas — ou não mostrava ninguém.
 *  · O INTERVALO vai do INÍCIO do primeiro dia ao FIM do último. As colunas
 *    são DATETIME, e com datas secas o limite de cima era a meia-noite do
 *    último dia: o trabalho desse dia não contava. O painel já tinha esta
 *    correcção e os mapas não.
 *  · O PAPEL E O EXCEL EXISTEM. Os dois botões estavam lá e respondiam
 *    «Funcionalidade de exportação em desenvolvimento».
 */
final class MapasDaOficina
{
    /** Os cinco mapas: o rótulo, o ícone, e se aceita filtro de estado. */
    public const MAPAS = [
        'servicos' => ['rotulo' => 'Serviços', 'icone' => 'fa-screwdriver-wrench', 'estado' => false],
        'receita' => ['rotulo' => 'Receita', 'icone' => 'fa-money-bill-wave', 'estado' => false],
        'viaturas' => ['rotulo' => 'Viaturas', 'icone' => 'fa-car', 'estado' => false],
        'mecanicos' => ['rotulo' => 'Mecânicos', 'icone' => 'fa-user-gear', 'estado' => false],
        'ordens' => ['rotulo' => 'Ordens de Serviço', 'icone' => 'fa-clipboard-list', 'estado' => true],
    ];

    /** Os seis estados de uma ordem. */
    public const ESTADOS = [
        'pending' => 'Pendente',
        'scheduled' => 'Agendada',
        'in_progress' => 'Em curso',
        'completed' => 'Concluída',
        'delivered' => 'Entregue',
        'cancelled' => 'Cancelada',
    ];

    public static function existe(string $mapa): bool
    {
        return array_key_exists($mapa, self::MAPAS);
    }

    /**
     * Um mapa inteiro: as colunas, as linhas e a linha dos totais.
     *
     * @return array{colunas: array, linhas: array, totais: ?array, nada: ?string}
     */
    public function mapa(string $qual, int $tenantId, array $filtros): array
    {
        $de = Carbon::parse($filtros['de'] ?? now()->startOfMonth())->startOfDay();
        $ate = Carbon::parse($filtros['ate'] ?? now())->endOfDay();

        $resultado = match ($qual) {
            'servicos' => $this->servicos($tenantId, $de, $ate),
            'receita' => $this->receita($tenantId, $de, $ate),
            'viaturas' => $this->viaturas($tenantId, $de, $ate),
            'mecanicos' => $this->mecanicos($tenantId, $de, $ate),
            'ordens' => $this->ordens($tenantId, $de, $ate, $filtros['estado'] ?? null),
        };

        return $resultado + [
            'totais' => null,
            'nada' => null,
            'periodo' => ['de' => $de->toDateString(), 'ate' => $ate->toDateString()],
        ];
    }

    /* ─── Serviços ────────────────────────────────────────────────────── */

    /**
     * O QUE A OFICINA FAZ E QUANTO RENDE.
     *
     * Agrupa pelo NOME DA LINHA e não pelo serviço do catálogo — de propósito:
     * as linhas escritas à mão («Soldar escape»), que não estão no catálogo,
     * são trabalho feito e têm de contar. Era assim no ecrã de sempre.
     */
    private function servicos(int $tenantId, Carbon $de, Carbon $ate): array
    {
        $linhas = DB::table('workshop_work_order_items as i')
            ->join('workshop_work_orders as o', 'i.work_order_id', '=', 'o.id')
            ->where('o.tenant_id', $tenantId)
            ->whereNull('o.deleted_at')
            ->where('i.type', 'service')
            // Só o trabalho aprovado pelo cliente conta como feito (OF-03).
            ->where('i.approval', 'approved')
            ->whereBetween('o.received_at', [$de, $ate])
            ->groupBy('i.name')
            ->selectRaw('i.name as nome, COUNT(*) as vezes, SUM(i.quantity) as quantidade,'
                . ' AVG(i.unit_price) as preco_medio, SUM(i.subtotal) as receita')
            ->orderByDesc('receita')
            ->get();

        return [
            'colunas' => [
                ['chave' => 'nome', 'rotulo' => 'Serviço', 'formato' => 'texto'],
                ['chave' => 'vezes', 'rotulo' => 'Utilizações', 'formato' => 'numero'],
                ['chave' => 'quantidade', 'rotulo' => 'Quantidade', 'formato' => 'numero'],
                ['chave' => 'preco_medio', 'rotulo' => 'Preço médio', 'formato' => 'dinheiro'],
                ['chave' => 'receita', 'rotulo' => 'Receita', 'formato' => 'dinheiro'],
            ],
            'linhas' => $linhas->map(fn ($l) => [
                'nome' => $l->nome,
                'vezes' => (int) $l->vezes,
                'quantidade' => (float) $l->quantidade,
                'preco_medio' => (float) $l->preco_medio,
                'receita' => (float) $l->receita,
            ])->all(),
            'totais' => [
                'nome' => __('Total'),
                'vezes' => (int) $linhas->sum('vezes'),
                'quantidade' => (float) $linhas->sum('quantidade'),
                'preco_medio' => null,
                'receita' => (float) $linhas->sum('receita'),
            ],
            'nada' => $linhas->isEmpty() ? __('Não houve serviços neste período.') : null,
        ];
    }

    /* ─── Receita ─────────────────────────────────────────────────────── */

    private function receita(int $tenantId, Carbon $de, Carbon $ate): array
    {
        $linhas = WorkOrder::where('tenant_id', $tenantId)
            ->whereBetween('received_at', [$de, $ate])
            ->groupBy('dia')
            ->selectRaw('DATE(received_at) as dia, COUNT(*) as ordens,'
                . ' SUM(labor_total) as mao_de_obra, SUM(parts_total) as pecas, SUM(total) as total,'
                . " SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as pago,"
                . " SUM(CASE WHEN payment_status <> 'paid' THEN total ELSE 0 END) as pendente")
            ->orderByDesc('dia')
            ->get();

        return [
            'colunas' => [
                ['chave' => 'dia', 'rotulo' => 'Data', 'formato' => 'data'],
                ['chave' => 'ordens', 'rotulo' => 'Ordens', 'formato' => 'numero'],
                ['chave' => 'mao_de_obra', 'rotulo' => 'Mão-de-obra', 'formato' => 'dinheiro'],
                ['chave' => 'pecas', 'rotulo' => 'Peças', 'formato' => 'dinheiro'],
                ['chave' => 'total', 'rotulo' => 'Total', 'formato' => 'dinheiro'],
                ['chave' => 'pago', 'rotulo' => 'Pago', 'formato' => 'dinheiro'],
                ['chave' => 'pendente', 'rotulo' => 'Por receber', 'formato' => 'dinheiro'],
            ],
            'linhas' => $linhas->map(fn ($l) => [
                'dia' => (string) $l->dia,
                'ordens' => (int) $l->ordens,
                'mao_de_obra' => (float) $l->mao_de_obra,
                'pecas' => (float) $l->pecas,
                'total' => (float) $l->total,
                'pago' => (float) $l->pago,
                'pendente' => (float) $l->pendente,
            ])->all(),
            'totais' => [
                'dia' => __('Total'),
                'ordens' => (int) $linhas->sum('ordens'),
                'mao_de_obra' => (float) $linhas->sum('mao_de_obra'),
                'pecas' => (float) $linhas->sum('pecas'),
                'total' => (float) $linhas->sum('total'),
                'pago' => (float) $linhas->sum('pago'),
                'pendente' => (float) $linhas->sum('pendente'),
            ],
            'nada' => $linhas->isEmpty() ? __('Não entraram ordens neste período.') : null,
        ];
    }

    /* ─── Viaturas ────────────────────────────────────────────────────── */

    private function viaturas(int $tenantId, Carbon $de, Carbon $ate): array
    {
        /*
         * A CONTA TOTAL É DE SEMPRE; a do período é do período.
         *
         * São duas perguntas diferentes na mesma linha — «este carro já cá
         * veio quantas vezes?» e «quanto deixou este mês?» — e é por isso que
         * a segunda vive num CASE e não num `where`.
         */
        $linhas = Vehicle::where('workshop_vehicles.tenant_id', $tenantId)
            ->leftJoin('workshop_work_orders as o', function ($j) {
                $j->on('workshop_vehicles.id', '=', 'o.vehicle_id')->whereNull('o.deleted_at');
            })
            ->groupBy('workshop_vehicles.id', 'workshop_vehicles.plate', 'workshop_vehicles.brand',
                'workshop_vehicles.model', 'workshop_vehicles.owner_name')
            ->selectRaw('workshop_vehicles.id, workshop_vehicles.plate, workshop_vehicles.brand,'
                . ' workshop_vehicles.model, workshop_vehicles.owner_name,'
                . ' COUNT(o.id) as ordens_total,'
                . ' SUM(CASE WHEN o.received_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as ordens_periodo,'
                . ' SUM(CASE WHEN o.received_at BETWEEN ? AND ? THEN o.total ELSE 0 END) as receita_periodo',
                [$de, $ate, $de, $ate])
            ->orderByDesc('receita_periodo')
            ->get();

        return [
            'colunas' => [
                ['chave' => 'matricula', 'rotulo' => 'Matrícula', 'formato' => 'texto'],
                ['chave' => 'viatura', 'rotulo' => 'Viatura', 'formato' => 'texto'],
                ['chave' => 'proprietario', 'rotulo' => 'Proprietário', 'formato' => 'texto'],
                ['chave' => 'ordens_total', 'rotulo' => 'Ordens (sempre)', 'formato' => 'numero'],
                ['chave' => 'ordens_periodo', 'rotulo' => 'Ordens no período', 'formato' => 'numero'],
                ['chave' => 'receita_periodo', 'rotulo' => 'Receita no período', 'formato' => 'dinheiro'],
            ],
            'linhas' => $linhas->map(fn ($v) => [
                'matricula' => $v->plate,
                'viatura' => trim("{$v->brand} {$v->model}"),
                'proprietario' => $v->owner_name,
                'ordens_total' => (int) $v->ordens_total,
                'ordens_periodo' => (int) $v->ordens_periodo,
                'receita_periodo' => (float) $v->receita_periodo,
            ])->all(),
            'totais' => [
                'matricula' => __('Total'),
                'viatura' => null,
                'proprietario' => null,
                'ordens_total' => (int) $linhas->sum('ordens_total'),
                'ordens_periodo' => (int) $linhas->sum('ordens_periodo'),
                'receita_periodo' => (float) $linhas->sum('receita_periodo'),
            ],
            'nada' => $linhas->isEmpty() ? __('Ainda não há viaturas registadas.') : null,
        ];
    }

    /* ─── Mecânicos ───────────────────────────────────────────────────── */

    /**
     * QUEM FEZ O QUÊ — dos mecânicos DA OFICINA.
     *
     * Lia `hr_employees`. A chave estrangeira aponta para
     * `workshop_mechanics`, e a caixa de escolha do ecrã sempre listou
     * mecânicos da oficina: este mapa mostrava outra gente, ou ninguém.
     */
    private function mecanicos(int $tenantId, Carbon $de, Carbon $ate): array
    {
        $linhas = DB::table('workshop_mechanics as m')
            ->leftJoin('workshop_work_orders as o', function ($j) use ($de, $ate) {
                $j->on('m.id', '=', 'o.mechanic_id')
                    ->whereNull('o.deleted_at')
                    ->whereBetween('o.received_at', [$de, $ate]);
            })
            ->where('m.tenant_id', $tenantId)
            ->whereNull('m.deleted_at')
            ->groupBy('m.id', 'm.name', 'm.level')
            ->selectRaw('m.id as id, m.name as nome, m.level as nivel, COUNT(o.id) as ordens,'
                . " SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) as concluidas,"
                . " SUM(CASE WHEN o.status = 'in_progress' THEN 1 ELSE 0 END) as em_curso,"
                . ' SUM(o.total) as receita')
            ->orderByDesc('ordens')
            ->get();

        /*
         * AS HORAS (OF-06): trabalhadas = os períodos do relógio que começaram no
         * intervalo; vendidas = as horas das linhas de serviço aprovadas em que o
         * mecânico trabalhou. Eficiência = vendidas ÷ trabalhadas.
         */
        $trabalhadas = DB::table('workshop_time_entries')->where('tenant_id', $tenantId)->whereNotNull('ended_at')
            ->whereBetween('started_at', [$de, $ate])->groupBy('mechanic_id')->selectRaw('mechanic_id, SUM(minutes) as minutos')->pluck('minutos', 'mechanic_id');
        $vendidas = DB::table('workshop_work_order_items as i')
            ->join('workshop_work_orders as o', 'i.work_order_id', '=', 'o.id')
            ->where('o.tenant_id', $tenantId)->whereNull('o.deleted_at')->where('i.type', 'service')->where('i.approval', 'approved')
            ->whereBetween('o.received_at', [$de, $ate])
            ->selectRaw('COALESCE(i.mechanic_id, o.mechanic_id) as mecanico, SUM(i.hours * GREATEST(i.quantity, 1)) as horas')
            ->groupByRaw('COALESCE(i.mechanic_id, o.mechanic_id)')->pluck('horas', 'mecanico');

        $linhas = $linhas->map(function ($l) use ($trabalhadas, $vendidas) {
            $l->trabalhadas = round(((int) ($trabalhadas[$l->id] ?? 0)) / 60, 2);
            $l->vendidas = round((float) ($vendidas[$l->id] ?? 0), 2);
            $l->eficiencia = $l->trabalhadas > 0 ? (int) round($l->vendidas / $l->trabalhadas * 100) : null;

            return $l;
        });

        return [
            'colunas' => [
                ['chave' => 'nome', 'rotulo' => 'Mecânico', 'formato' => 'texto'],
                ['chave' => 'ordens', 'rotulo' => 'Ordens', 'formato' => 'numero'],
                ['chave' => 'concluidas', 'rotulo' => 'Concluídas', 'formato' => 'numero'],
                ['chave' => 'em_curso', 'rotulo' => 'Em curso', 'formato' => 'numero'],
                ['chave' => 'receita', 'rotulo' => 'Receita', 'formato' => 'dinheiro'],
                ['chave' => 'trabalhadas', 'rotulo' => 'Horas trabalhadas', 'formato' => 'numero'],
                ['chave' => 'vendidas', 'rotulo' => 'Horas vendidas', 'formato' => 'numero'],
                ['chave' => 'eficiencia', 'rotulo' => 'Eficiência %', 'formato' => 'numero'],
            ],
            /*
             * QUEM NÃO TEVE ORDENS NENHUMAS TAMBÉM APARECE, com zeros.
             *
             * O ecrã de sempre tinha um `having total_orders > 0` e escondia-o
             * — e é justamente essa a linha que interessa a quem abre um mapa
             * de produtividade: o mecânico que não teve trabalho nenhum no mês.
             */
            'linhas' => $linhas->map(fn ($l) => [
                'nome' => $l->nome,
                'ordens' => (int) $l->ordens,
                'concluidas' => (int) $l->concluidas,
                'em_curso' => (int) $l->em_curso,
                'receita' => (float) $l->receita,
                'trabalhadas' => $l->trabalhadas,
                'vendidas' => $l->vendidas,
                'eficiencia' => $l->eficiencia,
            ])->all(),
            'totais' => [
                'nome' => __('Total'),
                'ordens' => (int) $linhas->sum('ordens'),
                'concluidas' => (int) $linhas->sum('concluidas'),
                'em_curso' => (int) $linhas->sum('em_curso'),
                'receita' => (float) $linhas->sum('receita'),
                'trabalhadas' => round((float) $linhas->sum('trabalhadas'), 2),
                'vendidas' => round((float) $linhas->sum('vendidas'), 2),
                'eficiencia' => $linhas->sum('trabalhadas') > 0 ? (int) round($linhas->sum('vendidas') / $linhas->sum('trabalhadas') * 100) : null,
            ],
            'nada' => $linhas->isEmpty() ? __('Ainda não há mecânicos nesta oficina.') : null,
        ];
    }

    /* ─── Ordens de serviço ───────────────────────────────────────────── */

    private function ordens(int $tenantId, Carbon $de, Carbon $ate, ?string $estado): array
    {
        $linhas = WorkOrder::where('tenant_id', $tenantId)
            ->with(['vehicle:id,plate,brand,model', 'mechanic:id,name'])
            ->whereBetween('received_at', [$de, $ate])
            ->when($estado, fn ($q) => $q->where('status', $estado))
            ->orderByDesc('received_at')
            ->get();

        return [
            'colunas' => [
                ['chave' => 'numero', 'rotulo' => 'Nº', 'formato' => 'texto'],
                ['chave' => 'viatura', 'rotulo' => 'Viatura', 'formato' => 'texto'],
                ['chave' => 'mecanico', 'rotulo' => 'Mecânico', 'formato' => 'texto'],
                ['chave' => 'quando', 'rotulo' => 'Entrada', 'formato' => 'data'],
                ['chave' => 'estado', 'rotulo' => 'Estado', 'formato' => 'estado'],
                ['chave' => 'total', 'rotulo' => 'Total', 'formato' => 'dinheiro'],
            ],
            'linhas' => $linhas->map(fn (WorkOrder $o) => [
                'numero' => $o->order_number,
                'viatura' => $o->vehicle
                    ? trim("{$o->vehicle->plate} — {$o->vehicle->brand} {$o->vehicle->model}")
                    : '—',
                'mecanico' => $o->mechanic?->name ?? __('Por atribuir'),
                'quando' => $o->received_at?->toDateString(),
                'estado' => $o->status,
                'estado_rotulo' => __(self::ESTADOS[$o->status] ?? $o->status),
                'total' => (float) $o->total,
            ])->all(),
            'totais' => [
                'numero' => __('Total'),
                'viatura' => null,
                'mecanico' => null,
                'quando' => null,
                'estado' => null,
                'total' => (float) $linhas->sum('total'),
            ],
            'nada' => $linhas->isEmpty() ? __('Não há ordens neste período.') : null,
        ];
    }
}
