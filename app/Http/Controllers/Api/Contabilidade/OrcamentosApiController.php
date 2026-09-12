<?php

namespace App\Http\Controllers\Api\Contabilidade;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\Budget;
use App\Models\Accounting\CostCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OS ORÇAMENTOS — o que se previu para cada conta, mês a mês.
 *
 * O QUE FALTAVA E É O PONTO INTEIRO DE UM ORÇAMENTO: a COMPARAÇÃO COM O REAL. O
 * ecrã antigo gravava doze números e mostrava-os outra vez; um orçamento que não
 * se compara com o que aconteceu é uma folha de cálculo com mais passos. Aqui
 * cada linha traz o REALIZADO — a soma dos lançamentos confirmados daquela conta
 * no mês — e o desvio.
 *
 * E O QUE ESTAVA PARTIDO:
 *
 *  · não havia como APAGAR um orçamento nem mudar-lhe o ESTADO: gravava sempre
 *    `status => 'draft'`, pelo que um orçamento aprovado voltava a rascunho na
 *    edição seguinte;
 *  · o TOTAL vinha do browser em vez de sair da soma dos meses — um total que
 *    não bate com as parcelas não se explica a ninguém;
 *  · a conta e o centro de custo podiam ser de OUTRA empresa;
 *  · e nada impedia DOIS orçamentos para a mesma conta no mesmo ano, que é a
 *    forma mais fácil de orçamentar a dobrar sem dar por isso.
 *
 * O CENTRO DE CUSTO É INFORMATIVO, e diz-se: as linhas de lançamento não têm
 * coluna de centro de custo, pelo que o realizado é o da CONTA e não o daquele
 * centro. Fingir que filtrava seria pior do que dizê-lo.
 */
class OrcamentosApiController extends Controller
{
    private const MESES = [
        'january', 'february', 'march', 'april', 'may', 'june',
        'july', 'august', 'september', 'october', 'november', 'december',
    ];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function tenantId(): int
    {
        return (int) activeTenantId();
    }

    private function daCasa(int $id): Budget
    {
        return Budget::where('tenant_id', $this->tenantId())->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.budgets.view');

        $tenantId = $this->tenantId();

        $filtros = $request->validate([
            'ano' => ['nullable', 'integer', 'min:1900', 'max:2200'],
            'procura' => ['nullable', 'string', 'max:80'],
            'estado' => ['nullable', Rule::in(['todos', 'draft', 'approved', 'closed'])],
        ]);

        $ano = (int) ($filtros['ano'] ?? now()->year);

        $orcamentos = Budget::where('tenant_id', $tenantId)
            ->where('year', $ano)
            ->when(trim($filtros['procura'] ?? '') !== '',
                fn ($q) => $q->where('name', 'like', '%'.trim($filtros['procura']).'%'))
            ->when(! empty($filtros['estado']) && $filtros['estado'] !== 'todos',
                fn ($q) => $q->where('status', $filtros['estado']))
            ->with(['account:id,code,name,type,nature', 'costCenter:id,code,name'])
            ->orderBy('name')->get();

        /*
         * O REALIZADO, numa consulta só: por conta e por mês.
         *
         * SÓ OS CONFIRMADOS contam — um rascunho é uma intenção, e comparar o
         * orçamento com intenções não mede nada.
         */
        $realizado = DB::table('accounting_move_lines as l')
            ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
            ->where('l.tenant_id', $tenantId)
            ->where('m.state', 'posted')
            ->whereYear('m.date', $ano)
            ->whereIn('l.account_id', $orcamentos->pluck('account_id')->filter()->unique())
            ->groupBy('l.account_id', 'mes')
            ->selectRaw('l.account_id, MONTH(m.date) as mes, SUM(l.debit) as debito, SUM(l.credit) as credito')
            ->get()
            ->groupBy('account_id');

        $aDebito = \App\Services\Accounting\Lancamentos::A_DEBITO;

        return response()->json([
            'ano' => $ano,
            'anos' => Budget::where('tenant_id', $tenantId)
                ->selectRaw('DISTINCT year')->orderByDesc('year')
                ->pluck('year')->map(fn ($a) => (int) $a)->values(),

            'data' => $orcamentos->map(function (Budget $o) use ($realizado, $aDebito) {
                $conta = $o->account;
                $cresceADebito = $conta && in_array($conta->type, $aDebito, true);

                $porMes = collect($realizado[$o->account_id] ?? [])->keyBy('mes');

                $meses = [];
                $realTotal = 0.0;

                foreach (self::MESES as $i => $mes) {
                    $linha = $porMes[$i + 1] ?? null;

                    // O SINAL SEGUE A NATUREZA da conta: um gasto cresce a
                    // débito, um proveito a crédito. Somar tudo igual daria
                    // realizados negativos nas contas de receita.
                    $real = $linha
                        ? ($cresceADebito
                            ? (float) $linha->debito - (float) $linha->credito
                            : (float) $linha->credito - (float) $linha->debito)
                        : 0.0;

                    $previsto = round((float) $o->{$mes}, 2);
                    $realTotal += $real;

                    $meses[] = [
                        'mes' => $i + 1,
                        'chave' => $mes,
                        'previsto' => $previsto,
                        'realizado' => round($real, 2),
                        'desvio' => round($real - $previsto, 2),
                    ];
                }

                $previstoTotal = round((float) $o->total, 2);
                $realTotal = round($realTotal, 2);

                return [
                    'id' => $o->id,
                    'nome' => $o->name,
                    'ano' => (int) $o->year,
                    'conta_id' => $o->account_id,
                    'conta' => $conta ? $conta->code.' · '.$conta->name : null,
                    'centro_de_custo_id' => $o->cost_center_id,
                    'centro_de_custo' => $o->costCenter ? $o->costCenter->code.' · '.$o->costCenter->name : null,
                    'estado' => $o->status,
                    'estado_rotulo' => $this->rotuloDoEstado($o->status),
                    'previsto' => $previstoTotal,
                    'realizado' => $realTotal,
                    'desvio' => round($realTotal - $previstoTotal, 2),
                    /*
                     * A EXECUÇÃO EM PERCENTAGEM, que é como se lê um orçamento.
                     * Sem previsto não há percentagem — e zero não é «100%».
                     */
                    'execucao' => $previstoTotal > 0 ? round($realTotal / $previstoTotal * 100, 1) : null,
                    'meses' => $meses,
                    'valores' => collect(self::MESES)->mapWithKeys(fn ($m) => [$m => round((float) $o->{$m}, 2)]),
                ];
            })->values(),

            'resumo' => [
                'orcamentos' => $orcamentos->count(),
                'rascunhos' => $orcamentos->where('status', 'draft')->count(),
                'aprovados' => $orcamentos->where('status', 'approved')->count(),
                'previsto' => round((float) $orcamentos->sum('total'), 2),
            ],

            'estados' => [
                ['valor' => 'draft', 'rotulo' => __('Rascunho')],
                ['valor' => 'approved', 'rotulo' => __('Aprovado')],
                ['valor' => 'closed', 'rotulo' => __('Encerrado')],
            ],

            // As contas que recebem movimento: orçamentar uma conta de agregação
            // é orçamentar o que já se orçamentou nas filhas.
            'contas' => Account::where('tenant_id', $tenantId)
                ->where('is_view', false)->where('blocked', false)
                ->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn ($c) => ['valor' => (string) $c->id, 'rotulo' => $c->code.' · '.$c->name])->values(),

            'centros_de_custo' => CostCenter::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn ($c) => ['valor' => (string) $c->id, 'rotulo' => $c->code.' · '.$c->name])->values(),

            'permissoes' => [
                'gerir' => (bool) $request->user()?->can('accounting.budgets.manage'),
            ],
        ]);
    }

    private function rotuloDoEstado(?string $estado): string
    {
        return [
            'draft' => __('Rascunho'), 'approved' => __('Aprovado'), 'closed' => __('Encerrado'),
        ][$estado] ?? (string) $estado;
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'accounting.budgets.manage');

        $tenantId = $this->tenantId();

        // Confirma que é desta empresa antes de validar contra ela.
        $orcamento = $id ? $this->daCasa($id) : new Budget();

        $regras = [
            'name' => ['required', 'string', 'max:150'],
            'year' => ['required', 'integer', 'min:1900', 'max:2200'],
            'account_id' => ['required', 'integer'],
            'cost_center_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['draft', 'approved', 'closed'])],
        ];

        foreach (self::MESES as $mes) {
            $regras[$mes] = ['nullable', 'numeric', 'min:0'];
        }

        $dados = $request->validate($regras, [], [
            'name' => __('nome'), 'year' => __('ano'), 'account_id' => __('conta'),
        ]);

        // A CONTA É DESTA EMPRESA, e recebe movimento.
        $conta = Account::where('tenant_id', $tenantId)->find($dados['account_id']);

        if (! $conta) {
            throw ValidationException::withMessages([
                'account_id' => [__('Conta não encontrada nesta empresa.')],
            ]);
        }

        if ($conta->is_view) {
            throw ValidationException::withMessages([
                'account_id' => [__('Esta conta é de agregação: orçamentá-la é orçamentar o que já está nas filhas.')],
            ]);
        }

        if (! empty($dados['cost_center_id'])
            && ! CostCenter::where('tenant_id', $tenantId)->whereKey($dados['cost_center_id'])->exists()) {
            throw ValidationException::withMessages([
                'cost_center_id' => [__('Centro de custo não encontrado nesta empresa.')],
            ]);
        }

        /*
         * UM ORÇAMENTO POR CONTA E POR ANO.
         *
         * Dois para a mesma conta no mesmo ano é a forma mais fácil de
         * orçamentar a dobrar sem dar por isso — e nenhum relatório saberia
         * qual dos dois é o bom.
         */
        $repetido = Budget::where('tenant_id', $tenantId)
            ->where('year', $dados['year'])
            ->where('account_id', $conta->id)
            ->when($id, fn ($q) => $q->where('id', '!=', $id))
            ->exists();

        if ($repetido) {
            throw ValidationException::withMessages([
                'account_id' => [__('Já existe um orçamento desta conta para :ano.', ['ano' => $dados['year']])],
            ]);
        }

        $meses = [];
        $total = 0.0;

        foreach (self::MESES as $mes) {
            $valor = round((float) ($dados[$mes] ?? 0), 2);
            $meses[$mes] = $valor;
            $total += $valor;
        }

        $orcamento->fill($meses + [
            'tenant_id' => $tenantId,
            'name' => trim($dados['name']),
            'year' => (int) $dados['year'],
            'account_id' => $conta->id,
            'cost_center_id' => ($dados['cost_center_id'] ?? null) ?: null,
            // O TOTAL SAI DA SOMA e não do browser: um total que não bate com as
            // parcelas não se explica a ninguém.
            'total' => round($total, 2),
            // E O ESTADO respeita-se: gravava sempre `draft`, pelo que um
            // orçamento aprovado voltava a rascunho na edição seguinte.
            'status' => $dados['status'] ?? ($id ? $orcamento->status : 'draft'),
        ])->save();

        return response()->json([
            'message' => $id
                ? __('Orçamento :nome actualizado.', ['nome' => $orcamento->name])
                : __('Orçamento :nome criado.', ['nome' => $orcamento->name]),
        ], $id ? 200 : 201);
    }

    /** O estado sozinho — aprovar não obriga a reabrir o formulário todo. */
    public function estado(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.budgets.manage');

        $dados = $request->validate([
            'estado' => ['required', Rule::in(['draft', 'approved', 'closed'])],
        ]);

        $orcamento = $this->daCasa($id);
        $orcamento->update(['status' => $dados['estado']]);

        return response()->json([
            'message' => __('Orçamento :nome agora está :estado.', [
                'nome' => $orcamento->name,
                'estado' => mb_strtolower($this->rotuloDoEstado($dados['estado'])),
            ]),
        ]);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.budgets.manage');

        $orcamento = $this->daCasa($id);
        $nome = $orcamento->name;

        $orcamento->delete();

        return response()->json(['message' => __('Orçamento :nome eliminado.', ['nome' => $nome])]);
    }
}
