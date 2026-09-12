<?php

namespace App\Http\Controllers\Api\Contabilidade;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Period;
use App\Services\Accounting\PeriodClosingService;
use Database\Seeders\Accounting\PeriodSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OS PERÍODOS CONTABILÍSTICOS.
 *
 * O QUE FALTAVA POR COMPLETO: criar um. O ecrã em Livewire só sabia fechar e
 * reabrir — os períodos nasciam de um seeder que alguém tinha de correr à mão
 * na consola. Uma empresa nova abria os Lançamentos, via «não há períodos
 * abertos» e não tinha por onde resolver.
 *
 * E O QUE SE FECHAVA ÀS ESCURAS: o serviço recusa fechar um período com
 * rascunhos ou com o balancete desequilibrado, mas o ecrã não dizia nem quantos
 * rascunhos havia nem de quanto era a diferença — carregava-se em Fechar para
 * descobrir. Aqui cada linha traz o seu balancete e o que a segura.
 */
class PeriodosApiController extends Controller
{
    public function __construct(private PeriodClosingService $fecho) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function tenantId(): int
    {
        return (int) activeTenantId();
    }

    private function daCasa(int $id): Period
    {
        return Period::where('tenant_id', $this->tenantId())->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.periods.view');

        $tenantId = $this->tenantId();

        $filtros = $request->validate([
            'ano' => ['nullable', 'integer', 'min:1900', 'max:2200'],
        ]);

        $ano = (int) ($filtros['ano'] ?? now()->year);

        $periodos = Period::where('tenant_id', $tenantId)
            ->whereYear('date_start', $ano)
            ->with('closedBy:id,name')
            ->orderBy('date_start')
            ->get();

        /*
         * O BALANCETE DE CADA PERÍODO, NUMA CONSULTA SÓ.
         *
         * O serviço calculava-o com um `sum()` por lançamento — um N+1 que num
         * ano com mil lançamentos são mil consultas. Aqui é um `group by`, e o
         * ecrã mostra-o em todas as linhas sem custo.
         */
        $balancetes = DB::table('accounting_move_lines as l')
            ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
            ->where('m.tenant_id', $tenantId)
            ->where('m.state', 'posted')
            ->whereIn('m.period_id', $periodos->pluck('id'))
            ->groupBy('m.period_id')
            ->selectRaw('m.period_id, SUM(l.debit) as debito, SUM(l.credit) as credito, COUNT(DISTINCT m.id) as lancamentos')
            ->get()->keyBy('period_id');

        $rascunhos = DB::table('accounting_moves')
            ->where('tenant_id', $tenantId)
            ->where('state', 'draft')
            ->whereIn('period_id', $periodos->pluck('id'))
            ->groupBy('period_id')
            ->selectRaw('period_id, COUNT(*) as quantos')
            ->pluck('quantos', 'period_id');

        $hoje = now()->format('Y-m-d');

        return response()->json([
            'ano' => $ano,
            // OS ANOS QUE EXISTEM, para o selector não ser uma caixa de escrever.
            'anos' => Period::where('tenant_id', $tenantId)
                ->selectRaw('DISTINCT YEAR(date_start) as ano')
                ->orderByDesc('ano')->pluck('ano')->map(fn ($a) => (int) $a)->values(),

            'data' => $periodos->map(function (Period $p) use ($balancetes, $rascunhos, $hoje) {
                $b = $balancetes[$p->id] ?? null;
                $debito = round((float) ($b->debito ?? 0), 2);
                $credito = round((float) ($b->credito ?? 0), 2);
                $diferenca = round($debito - $credito, 2);
                $comRascunhos = (int) ($rascunhos[$p->id] ?? 0);
                $aberto = $p->state === 'open';

                $de = $p->date_start?->format('Y-m-d');
                $ate = $p->date_end?->format('Y-m-d');

                return [
                    'id' => $p->id,
                    'codigo' => $p->code,
                    'nome' => $p->name ?: $p->code,
                    'de' => $de,
                    'ate' => $ate,
                    'estado' => $p->state,
                    'estado_rotulo' => $aberto ? __('Aberto') : __('Fechado'),
                    'fechado_em' => $p->closed_at?->format('Y-m-d H:i'),
                    'fechado_por' => $p->closedBy?->name,
                    // O período em que hoje cai: é o que se usa ao lançar.
                    'e_o_de_hoje' => $de && $ate && $hoje >= $de && $hoje <= $ate,
                    'lancamentos' => (int) ($b->lancamentos ?? 0),
                    'rascunhos' => $comRascunhos,
                    'debito' => $debito,
                    'credito' => $credito,
                    'diferenca' => $diferenca,
                    'equilibrado' => abs($diferenca) < 0.01,
                    /*
                     * O QUE SEGURA O FECHO, dito antes de se carregar no botão.
                     *
                     * São as duas recusas do serviço: rascunhos por confirmar e
                     * balancete desequilibrado.
                     */
                    'pode_fechar' => $aberto && $comRascunhos === 0 && abs($diferenca) < 0.01,
                    'porque_nao_fecha' => ! $aberto ? null : ($comRascunhos > 0
                        ? __('Há :n lançamento(s) em rascunho. Confirme-os ou apague-os antes de fechar.', ['n' => $comRascunhos])
                        : (abs($diferenca) >= 0.01
                            ? __('O balancete não bate: diferença de :valor.', ['valor' => number_format(abs($diferenca), 2, ',', '.')])
                            : null)),
                    'pode_reabrir' => ! $aberto,
                ];
            })->values(),

            'resumo' => [
                'total' => $periodos->count(),
                'abertos' => $periodos->where('state', 'open')->count(),
                'fechados' => $periodos->where('state', '!=', 'open')->count(),
                /*
                 * OS RASCUNHOS DO ANO. É o que impede fechar o exercício, e
                 * ninguém sabia que estavam lá até tentar.
                 */
                'rascunhos' => (int) $rascunhos->sum(),
            ],

            'permissoes' => [
                'gerir' => (bool) $request->user()?->can('accounting.periods.manage'),
            ],
        ]);
    }

    /**
     * GERAR O EXERCÍCIO — os doze meses de um ano.
     *
     * É a única forma prática de montar um ano: doze períodos escritos à mão,
     * um a um, com as datas certas, é trabalho que ninguém faz duas vezes sem
     * se enganar. Reaproveita o seeder, que já era INCREMENTAL — só cria os
     * meses em falta e nunca toca num período existente, que pode estar
     * fechado.
     */
    public function gerar(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.periods.manage');

        $dados = $request->validate([
            'ano' => ['required', 'integer', 'min:1900', 'max:2200'],
        ]);

        $criados = (new PeriodSeeder())->runForTenant($this->tenantId(), (int) $dados['ano']);

        return response()->json([
            'message' => $criados > 0
                ? __(':n período(s) criado(s) para :ano.', ['n' => $criados, 'ano' => $dados['ano']])
                : __('O exercício de :ano já estava completo — nada foi mudado.', ['ano' => $dados['ano']]),
            'criados' => $criados,
        ], $criados > 0 ? 201 : 200);
    }

    /**
     * UM PERÍODO À MÃO — para os que não são meses.
     *
     * O período de apuramento no fim do exercício, ou um exercício que não
     * começa em Janeiro. NÃO PODE SOBREPOR-SE a outro: dois períodos a cobrir
     * o mesmo dia fazem o «período de hoje» depender da ordem da consulta, e um
     * lançamento cai num ou noutro por sorte.
     */
    public function criar(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.periods.manage');

        $tenantId = $this->tenantId();

        $dados = $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('accounting_periods', 'code')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'date_start' => ['required', 'date_format:Y-m-d'],
            'date_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_start'],
        ], [], [
            'code' => __('código'), 'name' => __('nome'),
            'date_start' => __('início'), 'date_end' => __('fim'),
        ]);

        $sobrepoe = Period::where('tenant_id', $tenantId)
            ->where('date_start', '<=', $dados['date_end'])
            ->where('date_end', '>=', $dados['date_start'])
            ->first();

        if ($sobrepoe) {
            throw ValidationException::withMessages([
                'date_start' => [__('Este intervalo sobrepõe-se ao período :nome (:de a :ate).', [
                    'nome' => $sobrepoe->name ?: $sobrepoe->code,
                    'de' => $sobrepoe->date_start?->format('Y-m-d'),
                    'ate' => $sobrepoe->date_end?->format('Y-m-d'),
                ])],
            ]);
        }

        $periodo = Period::create($dados + ['tenant_id' => $tenantId, 'state' => 'open']);

        return response()->json([
            'message' => __('Período :nome criado.', ['nome' => $periodo->name]),
        ], 201);
    }

    public function fechar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.periods.manage');

        // O PERÍODO RESOLVE-SE FORA DO `try`: um período de outra empresa é um
        // 404, e não uma recusa de negócio a dizer «No query results for…».
        $periodo = $this->daCasa($id);

        return $this->pelaPorta(fn () => $this->fecho->closePeriod($periodo));
    }

    public function reabrir(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.periods.manage');

        $periodo = $this->daCasa($id);

        return $this->pelaPorta(fn () => $this->fecho->reopenPeriod($periodo));
    }

    /**
     * O serviço recusa por excepção, e o ecrã precisa de um 422 com a
     * mensagem no sítio dos erros — não de um 500 com um rasto de pilha.
     */
    private function pelaPorta(callable $accao): JsonResponse
    {
        try {
            $resultado = $accao();
        } catch (ValidationException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['geral' => [$e->getMessage()]]);
        }

        return response()->json(['message' => $resultado['message']]);
    }
}
