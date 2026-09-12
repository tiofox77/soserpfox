<?php

namespace App\Http\Controllers\Api\Contabilidade;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\Tax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * O PLANO DE CONTAS.
 *
 * O QUE ESTAVA PARTIDO: uma conta apagava-se sem uma pergunta. Apagar uma conta
 * que já tem movimento deixa a razão dela órfã — lançamentos confirmados a
 * apontar para um id que não existe — e o balanço passa a ter um buraco que
 * ninguém consegue explicar. Apagar uma conta de agregação que tem filhas
 * arranca o meio da árvore e deixa as folhas soltas.
 *
 * E O CÓDIGO NÃO ERA ÚNICO: duas contas `11` no mesmo plano, e nenhum relatório
 * a saber de qual é que fala.
 */
class ContasApiController extends Controller
{
    private const TIPOS = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    private function tenantId(): int
    {
        return (int) activeTenantId();
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.accounts.view');

        $tenantId = $this->tenantId();

        return response()->json([
            'tipos' => [
                ['valor' => 'asset', 'rotulo' => __('Activo')],
                ['valor' => 'liability', 'rotulo' => __('Passivo')],
                ['valor' => 'equity', 'rotulo' => __('Capital próprio')],
                ['valor' => 'revenue', 'rotulo' => __('Proveitos')],
                ['valor' => 'expense', 'rotulo' => __('Gastos')],
            ],
            'naturezas' => [
                ['valor' => 'debit', 'rotulo' => __('Débito')],
                ['valor' => 'credit', 'rotulo' => __('Crédito')],
            ],
            /*
             * AS CONTAS-MÃE são as de AGREGAÇÃO (`is_view`): são as únicas que
             * podem ter filhas, porque a razão de existirem é somá-las.
             */
            'maes' => Account::where('tenant_id', $tenantId)->where('is_view', true)
                ->orderBy('code')->get(['id', 'code', 'name', 'level'])
                ->map(fn ($c) => [
                    'valor' => (string) $c->id,
                    'rotulo' => $c->code.' · '.$c->name,
                    'nivel' => (int) $c->level,
                ])->values(),
            // A TAXA VAI NO RÓTULO: escolher entre «IVA» e «IVA» sem ver a
            // percentagem é escolher às escuras — a janela de ver já a mostrava.
            'impostos' => Tax::where('tenant_id', $tenantId)->where('active', true)
                ->orderBy('name')->get(['id', 'name', 'rate'])
                ->map(fn ($t) => [
                    'valor' => (string) $t->id,
                    'rotulo' => $t->name.' ('.rtrim(rtrim(number_format((float) $t->rate, 2, ',', '.'), '0'), ',').'%)',
                ])->values(),
            'centros_de_custo' => CostCenter::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn ($c) => ['valor' => (string) $c->id, 'rotulo' => $c->code.' · '.$c->name])->values(),
            'contas' => Account::where('tenant_id', $tenantId)->where('is_view', false)
                ->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn ($c) => ['valor' => (string) $c->id, 'rotulo' => $c->code.' · '.$c->name])->values(),
            'permissoes' => [
                'criar' => (bool) $request->user()?->can('accounting.accounts.manage'),
                'editar' => (bool) $request->user()?->can('accounting.accounts.manage'),
                'eliminar' => (bool) $request->user()?->can('accounting.accounts.manage'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.accounts.view');

        $tenantId = $this->tenantId();

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'tipo' => ['nullable', Rule::in(self::TIPOS)],
            'nivel' => ['nullable', 'integer', 'min:1', 'max:10'],
            'natureza' => ['nullable', Rule::in(['debit', 'credit'])],
            'estado' => ['nullable', Rule::in(['todas', 'activas', 'bloqueadas', 'agregacao'])],
            'por_pagina' => ['nullable', 'integer', 'min:10', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $base = fn () => Account::where('tenant_id', $tenantId)
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->where(fn ($w) => $w->where('code', 'like', $t)->orWhere('name', 'like', $t));
            })
            ->when(! empty($filtros['tipo']), fn ($q) => $q->where('type', $filtros['tipo']))
            ->when(! empty($filtros['nivel']), fn ($q) => $q->where('level', $filtros['nivel']))
            ->when(! empty($filtros['natureza']), fn ($q) => $q->where('nature', $filtros['natureza']))
            ->when(($filtros['estado'] ?? 'todas') === 'activas', fn ($q) => $q->where('blocked', false))
            ->when(($filtros['estado'] ?? 'todas') === 'bloqueadas', fn ($q) => $q->where('blocked', true))
            ->when(($filtros['estado'] ?? 'todas') === 'agregacao', fn ($q) => $q->where('is_view', true));

        $lista = $base()
            ->with(['parent:id,code,name'])
            ->withCount('moveLines')
            ->orderBy('code')
            ->paginate($filtros['por_pagina'] ?? 25);

        return response()->json([
            'data' => collect($lista->items())->map(fn (Account $c) => $this->linha($c))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'total' => $base()->count(),
                'activas' => $base()->where('blocked', false)->count(),
                'bloqueadas' => $base()->where('blocked', true)->count(),
                'agregacao' => $base()->where('is_view', true)->count(),
            ],
        ]);
    }

    private function linha(Account $c): array
    {
        return [
            'id' => $c->id,
            'codigo' => $c->code,
            'nome' => $c->name,
            'tipo' => $c->type,
            'tipo_rotulo' => $this->rotuloDoTipo($c->type),
            'natureza' => $c->nature,
            'nivel' => (int) $c->level,
            'mae' => $c->parent ? $c->parent->code.' · '.$c->parent->name : null,
            'mae_id' => $c->parent_id,
            // AGREGAÇÃO: soma as filhas e não recebe movimento próprio.
            'agregacao' => (bool) $c->is_view,
            'bloqueada' => (bool) $c->blocked,
            'descricao' => $c->description,
            'chave' => $c->account_key,
            'subtipo' => $c->account_subtype,
            'custo_fixo' => (bool) $c->is_fixed_cost,
            // QUANTAS LINHAS JÁ TEM: é o que decide se se pode apagar.
            'linhas' => (int) ($c->move_lines_count ?? 0),
        ];
    }

    private function rotuloDoTipo(?string $tipo): string
    {
        return [
            'asset' => __('Activo'), 'liability' => __('Passivo'), 'equity' => __('Capital próprio'),
            'revenue' => __('Proveitos'), 'expense' => __('Gastos'),
        ][$tipo] ?? (string) $tipo;
    }

    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.accounts.view');

        $c = Account::with([
            'parent:id,code,name', 'defaultTax:id,name,rate', 'defaultCostCenter:id,code,name',
            'debitReflectionAccount:id,code,name', 'creditReflectionAccount:id,code,name',
        ])->withCount('moveLines')->findOrFail($id);

        return response()->json([
            'data' => $this->linha($c) + [
                'imposto_id' => $c->default_tax_id,
                'imposto' => $c->defaultTax
                    ? $c->defaultTax->name.' ('.rtrim(rtrim(number_format((float) $c->defaultTax->rate, 2, ',', '.'), '0'), ',').'%)'
                    : null,
                'centro_de_custo_id' => $c->default_cost_center_id,
                'centro_de_custo' => $c->defaultCostCenter
                    ? $c->defaultCostCenter->code.' · '.$c->defaultCostCenter->name : null,
                'reflexao_debito_id' => $c->debit_reflection_account_id,
                'reflexao_debito' => $c->debitReflectionAccount
                    ? $c->debitReflectionAccount->code.' · '.$c->debitReflectionAccount->name : null,
                'reflexao_credito_id' => $c->credit_reflection_account_id,
                'reflexao_credito' => $c->creditReflectionAccount
                    ? $c->creditReflectionAccount->code.' · '.$c->creditReflectionAccount->name : null,
                'filhas' => $c->children()->count(),
                // A CHAVE DE INTEGRAÇÃO não se edita à mão — é o que liga esta
                // conta ao balanço e à demonstração de resultados do PGC-AO —
                // mas vê-se, que era o que a janela de ver já mostrava.
                'chave_de_integracao' => $c->integration_key,
                'criada_em' => $c->created_at?->format('d/m/Y H:i'),
                'actualizada_em' => $c->updated_at?->format('d/m/Y H:i'),
            ],
        ]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'accounting.accounts.manage');

        $tenantId = $this->tenantId();

        $dados = $request->validate([
            /*
             * O CÓDIGO É ÚNICO NO PLANO DA EMPRESA.
             *
             * Não havia verificação nenhuma: duas contas «11» no mesmo plano, e
             * nenhum relatório a saber de qual é que fala.
             */
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('accounting_accounts', 'code')->where('tenant_id', $tenantId)->ignore($id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TIPOS)],
            'nature' => ['required', Rule::in(['debit', 'credit'])],
            'parent_id' => ['nullable', 'integer'],
            'is_view' => ['boolean'],
            'blocked' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_tax_id' => ['nullable', 'integer'],
            'debit_reflection_account_id' => ['nullable', 'integer'],
            'credit_reflection_account_id' => ['nullable', 'integer'],
            'default_cost_center_id' => ['nullable', 'integer'],
            'account_key' => ['nullable', 'string', 'max:50'],
            'account_subtype' => ['nullable', 'string', 'max:50'],
            'is_fixed_cost' => ['boolean'],
        ], [], ['code' => __('código'), 'name' => __('nome'), 'type' => __('tipo'), 'nature' => __('natureza')]);

        $c = $id ? Account::findOrFail($id) : new Account();

        /*
         * A MÃE TEM DE SER UMA CONTA DE AGREGAÇÃO, E DESTA EMPRESA.
         *
         * Pendurar uma conta numa conta de movimento faz um nó que não é nem
         * árvore nem folha: o total da mãe passa a somar o movimento dela mais o
         * das filhas, e o mesmo valor conta duas vezes.
         */
        $mae = null;

        if (! empty($dados['parent_id'])) {
            $mae = Account::find($dados['parent_id']);

            if (! $mae) {
                throw ValidationException::withMessages([
                    'parent_id' => [__('Conta-mãe não encontrada nesta empresa.')],
                ]);
            }

            if (! $mae->is_view) {
                throw ValidationException::withMessages([
                    'parent_id' => [__('A conta-mãe tem de ser de agregação: só essas somam filhas.')],
                ]);
            }

            if ($id && (int) $mae->id === (int) $id) {
                throw ValidationException::withMessages([
                    'parent_id' => [__('Uma conta não se pendura em si própria.')],
                ]);
            }
        }

        // UMA CONTA COM MOVIMENTO NÃO PASSA A SER DE AGREGAÇÃO: o movimento que
        // já tem passaria a somar-se ao das filhas, em duplicado.
        if ($id && ($dados['is_view'] ?? false) && ! $c->is_view && $c->moveLines()->exists()) {
            throw ValidationException::withMessages([
                'is_view' => [__('Esta conta já tem movimento: não pode passar a ser de agregação.')],
            ]);
        }

        $c->fill([
            'tenant_id' => $tenantId,
            'code' => trim($dados['code']),
            'name' => trim($dados['name']),
            'type' => $dados['type'],
            'nature' => $dados['nature'],
            'parent_id' => $mae?->id,
            // O NÍVEL SAI DA MÃE, não da mão de quem escreve: era um campo livre
            // e um nível errado desalinha a árvore inteira nos relatórios.
            'level' => $mae ? ((int) $mae->level + 1) : 1,
            'is_view' => (bool) ($dados['is_view'] ?? false),
            'blocked' => (bool) ($dados['blocked'] ?? false),
            'description' => $dados['description'] ?? null,
            'default_tax_id' => ($dados['default_tax_id'] ?? null) ?: null,
            'debit_reflection_account_id' => ($dados['debit_reflection_account_id'] ?? null) ?: null,
            'credit_reflection_account_id' => ($dados['credit_reflection_account_id'] ?? null) ?: null,
            'default_cost_center_id' => ($dados['default_cost_center_id'] ?? null) ?: null,
            'account_key' => $dados['account_key'] ?? null,
            'account_subtype' => $dados['account_subtype'] ?? null,
            'is_fixed_cost' => (bool) ($dados['is_fixed_cost'] ?? false),
        ]);

        $c->tenant_id = $tenantId;
        $c->save();

        return response()->json([
            'message' => $id ? __('Conta actualizada.') : __('Conta criada.'),
            'data' => $this->linha($c->fresh(['parent'])),
        ], $id ? 200 : 201);
    }

    public function alternar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.accounts.manage');

        $c = Account::findOrFail($id);

        $c->update(['blocked' => ! $c->blocked]);

        return response()->json([
            'message' => $c->blocked ? __('Conta bloqueada.') : __('Conta desbloqueada.'),
            'bloqueada' => (bool) $c->blocked,
        ]);
    }

    /**
     * APAGAR UMA CONTA.
     *
     * Não havia pergunta nenhuma. Apagar uma conta com movimento deixa a razão
     * dela órfã — lançamentos confirmados a apontar para um id que não existe —
     * e o balanço fica com um buraco que ninguém consegue explicar. Apagar uma
     * conta de agregação com filhas arranca o meio da árvore.
     *
     * Quando não se pode apagar, BLOQUEIA-SE: deixa de aparecer ao lançar e o
     * que já lá está continua legível.
     */
    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.accounts.manage');

        $c = Account::findOrFail($id);

        $linhas = $c->moveLines()->count();

        if ($linhas > 0) {
            $this->recusa(__('Esta conta tem :n linha(s) de lançamento. Bloqueie-a em vez de a apagar — o que já foi lançado tem de continuar legível.', [
                'n' => $linhas,
            ]));
        }

        $filhas = $c->children()->count();

        if ($filhas > 0) {
            $this->recusa(__('Esta conta tem :n conta(s) dependente(s). Mude-as de mãe primeiro.', [
                'n' => $filhas,
            ]));
        }

        // E se alguma outra conta a usa como reflexão ou o plano a referencia.
        $apontam = Account::where('tenant_id', $this->tenantId())
            ->where(fn ($q) => $q->where('debit_reflection_account_id', $id)
                ->orWhere('credit_reflection_account_id', $id))
            ->count();

        if ($apontam > 0) {
            $this->recusa(__('Há :n conta(s) a usar esta como conta de reflexão.', ['n' => $apontam]));
        }

        $c->delete();

        return response()->json(['message' => __('Conta eliminada.')]);
    }

    /**
     * A RAZÃO DA CONTA — o extracto dela, linha a linha.
     *
     * O ecrã antigo não a tinha: via-se o plano de contas e mais nada, e para
     * saber o que uma conta tem dentro era preciso ir aos relatórios e montar
     * o balancete inteiro.
     */
    public function razao(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.accounts.view');

        $c = Account::findOrFail($id);

        $filtros = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
        ]);

        $de = $filtros['de'] ?? now()->startOfYear()->format('Y-m-d');
        $ate = $filtros['ate'] ?? now()->format('Y-m-d');

        $linhas = DB::table('accounting_move_lines as l')
            ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
            ->leftJoin('accounting_journals as d', 'd.id', '=', 'm.journal_id')
            ->where('l.tenant_id', $this->tenantId())
            ->where('l.account_id', $id)
            // SÓ O QUE ESTÁ CONFIRMADO: um rascunho não é saldo de conta nenhuma.
            ->where('m.state', 'posted')
            ->whereBetween('m.date', [$de, $ate])
            ->orderBy('m.date')->orderBy('m.id')
            ->select([
                'l.id', 'l.debit', 'l.credit', 'l.narration',
                'm.id as move_id', 'm.ref', 'm.date', 'm.narration as nota_do_lancamento',
                'd.name as diario',
            ])
            ->get();

        /*
         * O SALDO ANTERIOR.
         *
         * Uma razão que começa a zero no dia 1 de Janeiro do filtro escolhido
         * não é a razão da conta: é um pedaço dela. Sem o saldo de abertura, o
         * saldo final não bate com nada.
         */
        $anterior = DB::table('accounting_move_lines as l')
            ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
            ->where('l.tenant_id', $this->tenantId())
            ->where('l.account_id', $id)
            ->where('m.state', 'posted')
            ->where('m.date', '<', $de)
            ->selectRaw('COALESCE(SUM(l.debit),0) as debito, COALESCE(SUM(l.credit),0) as credito')
            ->first();

        $aDebito = in_array($c->type, \App\Services\Accounting\Lancamentos::A_DEBITO, true);

        $abertura = $aDebito
            ? (float) $anterior->debito - (float) $anterior->credito
            : (float) $anterior->credito - (float) $anterior->debito;

        $acumulado = $abertura;
        $movimentos = [];

        foreach ($linhas as $l) {
            $delta = $aDebito
                ? (float) $l->debit - (float) $l->credit
                : (float) $l->credit - (float) $l->debit;

            $acumulado = round($acumulado + $delta, 2);

            $movimentos[] = [
                'id' => (int) $l->id,
                'lancamento' => (int) $l->move_id,
                'ref' => $l->ref,
                'dia' => $l->date,
                'diario' => $l->diario,
                'nota' => $l->narration ?: $l->nota_do_lancamento,
                'debito' => round((float) $l->debit, 2),
                'credito' => round((float) $l->credit, 2),
                'acumulado' => $acumulado,
            ];
        }

        return response()->json([
            'conta' => [
                'id' => $c->id,
                'codigo' => $c->code,
                'nome' => $c->name,
                'tipo_rotulo' => $this->rotuloDoTipo($c->type),
                'natureza' => $c->nature,
                'cresce_a_debito' => $aDebito,
            ],
            'periodo' => ['de' => $de, 'ate' => $ate],
            'abertura' => round($abertura, 2),
            'data' => $movimentos,
            'totais' => [
                'debito' => round(collect($movimentos)->sum('debito'), 2),
                'credito' => round(collect($movimentos)->sum('credito'), 2),
                'saldo' => round($acumulado, 2),
            ],
        ]);
    }
}
