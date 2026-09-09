<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * O PAINEL DA OFICINA — o que está em cima da bancada, hoje.
 *
 * Não é uma lista de números bonitos: é a resposta a «o que é que eu tenho de
 * fazer». Por isso as ordens urgentes e os documentos a caducar vêm com o
 * resto, e a carga por mecânico está lá porque é ela que decide a próxima
 * marcação.
 *
 * O QUE MUDA EM RELAÇÃO AO PAINEL EM BLADE:
 *
 *  · A CARGA POR MECÂNICO era lida da tabela `users`. `mechanic_id` aponta
 *    para `workshop_mechanics` desde 2025-11-05: o gráfico mostrava o nome do
 *    UTILIZADOR com o mesmo número, ou «Por atribuir» para toda a gente.
 *  · O intervalo continua a ir do INÍCIO do primeiro dia ao FIM do último —
 *    as colunas são DATETIME e com datas secas o trabalho de hoje não contava.
 *  · Os valores em dinheiro só saem a quem pode ver relatórios, como o
 *    `valorProtegido()` do Blade fazia — mas do lado do servidor, que é onde
 *    esconder um número é esconder mesmo.
 */
class PainelApiController extends Controller
{
    /** Os rótulos dos seis estados de uma ordem, num sítio só. */
    public const ESTADOS = [
        'pending' => 'Pendente',
        'scheduled' => 'Agendada',
        'in_progress' => 'Em curso',
        'completed' => 'Concluída',
        'delivered' => 'Entregue',
        'cancelled' => 'Cancelada',
    ];

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('workshop.dashboard.view'), 403, __('Sem permissão para esta operação.'));

        $filtros = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
        ]);

        $tenantId = activeTenantId();

        $de = Carbon::parse($filtros['de'] ?? now()->startOfMonth())->startOfDay();
        $ate = Carbon::parse($filtros['ate'] ?? now())->endOfDay();
        $intervalo = [$de, $ate];

        /*
         * QUEM NÃO PODE VER RELATÓRIOS NÃO VÊ DINHEIRO.
         *
         * O Blade escondia-o com `valorProtegido()`, que troca o número por
         * «•••» no HTML — o valor ia na mesma para o browser. Aqui não sai do
         * servidor.
         */
        $veDinheiro = (bool) $request->user()?->can('workshop.reports.view');

        $ordens = fn () => WorkOrder::where('tenant_id', $tenantId);

        $porEstado = (clone $ordens())
            ->whereBetween('received_at', $intervalo)
            ->selectRaw('status, COUNT(*) as quantos')
            ->groupBy('status')
            ->pluck('quantos', 'status');

        $topServicos = DB::table('workshop_work_order_items as i')
            ->join('workshop_work_orders as o', 'i.work_order_id', '=', 'o.id')
            ->join('workshop_services as s', 'i.service_id', '=', 's.id')
            ->where('o.tenant_id', $tenantId)
            ->where('i.type', 'service')
            ->whereBetween('o.received_at', $intervalo)
            ->groupBy('s.id', 's.name')
            ->selectRaw('s.name as nome, COUNT(*) as vezes, SUM(i.subtotal) as receita')
            ->orderByDesc('vezes')
            ->limit(5)
            ->get();

        return response()->json([
            'periodo' => ['de' => $de->toDateString(), 'ate' => $ate->toDateString()],
            've_dinheiro' => $veDinheiro,
            'cartoes' => [
                'ordens' => (clone $ordens())->whereBetween('received_at', $intervalo)->count(),
                // As pendentes e as em curso NÃO se filtram pelo período: o que
                // está em cima da bancada está lá hoje, tenha entrado quando
                // tiver entrado. Era assim no ecrã de sempre.
                'pendentes' => (clone $ordens())->whereIn('status', ['pending', 'scheduled'])->count(),
                'em_curso' => (clone $ordens())->where('status', 'in_progress')->count(),
                'concluidas' => (clone $ordens())->where('status', 'completed')
                    ->whereBetween('completed_at', $intervalo)->count(),
            ],
            'dinheiro' => $veDinheiro ? [
                'facturado' => (float) (clone $ordens())->where('payment_status', 'paid')
                    ->whereBetween('received_at', $intervalo)->sum('total'),
                'a_receber' => (float) (clone $ordens())->whereIn('status', ['completed', 'delivered'])
                    ->where('payment_status', '!=', 'paid')->sum('total'),
            ] : null,
            'viaturas' => [
                'total' => Vehicle::where('tenant_id', $tenantId)->count(),
                'activas' => Vehicle::where('tenant_id', $tenantId)->where('status', 'active')->count(),
            ],
            'series' => [
                'mensal' => $this->receitaPorMes($tenantId, $veDinheiro),
                'estados' => $this->porEstado($porEstado),
                'servicos' => [
                    'etiquetas' => $topServicos->pluck('nome')->all(),
                    'valores' => $topServicos->map(fn ($s) => $veDinheiro ? (float) $s->receita : (int) $s->vezes)->all(),
                ],
                'mecanicos' => $this->cargaPorMecanico($tenantId),
            ],
            'top_servicos' => $topServicos->map(fn ($s) => [
                'nome' => $s->nome,
                'vezes' => (int) $s->vezes,
                'receita' => $veDinheiro ? (float) $s->receita : null,
            ])->all(),
            'urgentes' => (clone $ordens())
                ->with('vehicle:id,plate,owner_name,brand,model')
                ->whereIn('status', ['pending', 'scheduled', 'in_progress'])
                ->where('priority', 'urgent')
                ->orderByDesc('received_at')
                ->limit(5)->get()
                ->map(fn (WorkOrder $o) => [
                    'id' => $o->id,
                    'numero' => $o->order_number,
                    'matricula' => $o->vehicle?->plate,
                    'dono' => $o->vehicle?->owner_name,
                    'estado' => $o->status,
                    'estado_rotulo' => __(self::ESTADOS[$o->status] ?? $o->status),
                ])->all(),
            'documentos_a_caducar' => $this->documentosACaducar($tenantId),
        ]);
    }

    /**
     * A FACTURAÇÃO DA OFICINA MÊS A MÊS.
     *
     * Um total do período não diz se a oficina está a crescer — e é essa a
     * pergunta que se faz a seguir ao total.
     */
    private function receitaPorMes(int $tenantId, bool $veDinheiro): array
    {
        $desde = now()->subMonths(11)->startOfMonth();

        $porMes = WorkOrder::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('received_at', '>=', $desde)
            ->groupBy('mes')
            ->selectRaw("DATE_FORMAT(received_at, '%Y-%m') as mes, SUM(total) as total, COUNT(*) as quantas")
            ->get()
            ->keyBy('mes');

        $etiquetas = [];
        $valores = [];

        for ($m = 0; $m < 12; $m++) {
            $quando = $desde->copy()->addMonths($m);
            $linha = $porMes[$quando->format('Y-m')] ?? null;

            $etiquetas[] = $quando->translatedFormat('M/y');
            // Sem permissão de relatórios, o gráfico conta ORDENS em vez de
            // dinheiro: a forma da curva é a informação, e não o valor.
            $valores[] = $linha ? ($veDinheiro ? (float) $linha->total : (int) $linha->quantas) : 0;
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    private function porEstado($porEstado): array
    {
        return [
            'etiquetas' => $porEstado->keys()->map(fn ($e) => __(self::ESTADOS[$e] ?? $e))->all(),
            'chaves' => $porEstado->keys()->all(),
            'valores' => $porEstado->values()->map(fn ($n) => (int) $n)->all(),
        ];
    }

    /**
     * A CARGA POR MECÂNICO — quem está sobrecarregado.
     *
     * Lia-se da tabela `users`, e `mechanic_id` aponta para
     * `workshop_mechanics`: o gráfico mostrava o nome do utilizador com o
     * mesmo número — outra pessoa — ou «Por atribuir» para toda a gente.
     */
    private function cargaPorMecanico(int $tenantId): array
    {
        $linhas = DB::table('workshop_work_orders as o')
            ->leftJoin('workshop_mechanics as m', 'm.id', '=', 'o.mechanic_id')
            ->where('o.tenant_id', $tenantId)
            ->whereNull('o.deleted_at')
            ->whereIn('o.status', ['pending', 'scheduled', 'in_progress'])
            ->groupBy('m.id', 'm.name')
            ->selectRaw('m.name as nome, COUNT(*) as total')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->map(fn ($l) => $l->nome ?: __('Por atribuir'))->all(),
            'valores' => $linhas->map(fn ($l) => (int) $l->total)->all(),
        ];
    }

    /**
     * OS DOCUMENTOS A CADUCAR — e QUAL deles.
     *
     * O ecrã em Blade dizia «documentos a vencer» e depois listava as três
     * datas em texto corrido, sem separar o que já passou do que está quase.
     * Aqui cada viatura traz a lista do que está em causa, com os dias que
     * faltam: negativo é «já caducou».
     */
    private function documentosACaducar(int $tenantId): array
    {
        $limite = now()->addDays(30);

        return Vehicle::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(fn ($q) => $q
                ->where('registration_expiry', '<=', $limite)
                ->orWhere('insurance_expiry', '<=', $limite)
                ->orWhere('inspection_expiry', '<=', $limite))
            ->orderByRaw('LEAST(COALESCE(registration_expiry, \'9999-12-31\'), COALESCE(insurance_expiry, \'9999-12-31\'), COALESCE(inspection_expiry, \'9999-12-31\'))')
            ->limit(10)
            ->get()
            ->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'matricula' => $v->plate,
                'viatura' => trim("{$v->brand} {$v->model}"),
                'documentos' => collect([
                    ['Livrete', $v->registration_expiry],
                    ['Seguro', $v->insurance_expiry],
                    ['Inspecção', $v->inspection_expiry],
                ])->filter(fn ($d) => $d[1] && $d[1]->lte($limite))
                    ->map(fn ($d) => [
                        'nome' => __($d[0]),
                        'quando' => $d[1]->toDateString(),
                        'dias' => (int) now()->startOfDay()->diffInDays($d[1]->copy()->startOfDay(), false),
                    ])->values()->all(),
            ])->all();
    }
}
