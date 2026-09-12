<?php

namespace App\Http\Controllers\Api\Contabilidade;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\BankReconciliationItem;
use App\Services\Accounting\BankReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A RECONCILIAÇÃO BANCÁRIA — o extracto do banco contra os lançamentos.
 *
 * IMPORTAR UM EXTRACTO NUNCA FUNCIONOU. O serviço procura uma relação
 * `bankReconciliationItem` no `MoveLine` que NÃO EXISTIA: o auto-match corre no
 * fim da importação, o Eloquent atirava «Call to undefined relationship», e o
 * ecrã mostrava-o como «Erro ao importar». Desde o primeiro dia.
 *
 * E SE TIVESSE FUNCIONADO, não havia por onde continuar: o botão «Ver» da lista
 * era `<button class="text-blue-600...">` sem `wire:click` nenhum. As linhas do
 * extracto não se viam, não se casavam à mão, e o `manualMatch()` e o
 * `findMatchingSuggestions()` — escritos, completos — não tinham quem os
 * chamasse.
 *
 * O QUE MAIS ESTAVA PARTIDO: a janela de sugestões procurava lançamentos pela
 * data em que a LINHA FOI INSERIDA e não pela data do lançamento; uma referência
 * vazia dava sempre os vinte pontos da descrição e empurrava a linha errada
 * acima dos 90, conciliando-a sozinho; o leitor de OFX não extraía transacção
 * nenhuma; e uma importação sem linhas dava-se por «reconciliada».
 */
class ReconciliacaoApiController extends Controller
{
    public function __construct(private BankReconciliationService $servico) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function tenantId(): int
    {
        return (int) activeTenantId();
    }

    private function daCasa(int $id): BankReconciliation
    {
        return BankReconciliation::where('tenant_id', $this->tenantId())->findOrFail($id);
    }

    /**
     * AS CONTAS DE BANCO.
     *
     * O ecrã antigo procurava-as por `code like '11%' or '12%'` — o prefixo do
     * PGC-AO escrito à mão, que falha em qualquer plano importado de outro
     * sistema. Aqui pergunta-se pela CHAVE DE INTEGRAÇÃO, que é o que liga uma
     * conta ao seu papel, e só se cai no prefixo quando não há chave nenhuma.
     */
    private function contasDeBanco(int $tenantId)
    {
        $porChave = Account::where('tenant_id', $tenantId)
            ->where('is_view', false)
            ->whereIn('integration_key', ['bank', 'banks', 'cash_and_banks'])
            ->orderBy('code')->get(['id', 'code', 'name']);

        if ($porChave->isNotEmpty()) {
            return $porChave;
        }

        return Account::where('tenant_id', $tenantId)
            ->where('is_view', false)
            ->where(fn ($q) => $q->where('code', 'like', '11%')->orWhere('code', 'like', '12%'))
            ->orderBy('code')->get(['id', 'code', 'name']);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.reconciliation.view');

        $tenantId = $this->tenantId();

        $filtros = $request->validate([
            'conta' => ['nullable', 'integer'],
            'estado' => ['nullable', Rule::in(['todos', 'draft', 'reconciled', 'approved'])],
            'por_pagina' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $base = fn () => BankReconciliation::where('tenant_id', $tenantId)
            ->when(! empty($filtros['conta']), fn ($q) => $q->where('account_id', $filtros['conta']))
            ->when(! empty($filtros['estado']) && $filtros['estado'] !== 'todos',
                fn ($q) => $q->where('status', $filtros['estado']));

        $lista = $base()
            ->with(['account:id,code,name'])
            ->withCount([
                'items',
                'items as casadas' => fn ($q) => $q->where('status', 'matched'),
            ])
            ->orderByDesc('statement_date')->orderByDesc('id')
            ->paginate($filtros['por_pagina'] ?? 20);

        return response()->json([
            'data' => collect($lista->items())->map(fn (BankReconciliation $r) => $this->linha($r))->values(),
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
                'por_conciliar' => $base()->where('status', 'draft')->count(),
                'conciliadas' => $base()->where('status', 'reconciled')->count(),
                'com_diferenca' => $base()->where('difference', '!=', 0)->count(),
            ],
            'contas' => $this->contasDeBanco($tenantId)->map(fn ($c) => [
                'valor' => (string) $c->id, 'rotulo' => $c->code.' · '.$c->name,
            ])->values(),
            'formatos' => [
                ['valor' => 'csv', 'rotulo' => __('CSV (data, referência, descrição, valor)')],
                ['valor' => 'mt940', 'rotulo' => __('MT940 (padrão bancário)')],
                ['valor' => 'ofx', 'rotulo' => __('OFX')],
            ],
            'estados' => [
                ['valor' => 'draft', 'rotulo' => __('Por conciliar')],
                ['valor' => 'reconciled', 'rotulo' => __('Conciliada')],
                ['valor' => 'approved', 'rotulo' => __('Aprovada')],
            ],
            'permissoes' => [
                'gerir' => (bool) $request->user()?->can('accounting.reconciliation.manage'),
            ],
        ]);
    }

    private function linha(BankReconciliation $r): array
    {
        $total = (int) ($r->items_count ?? 0);
        $casadas = (int) ($r->casadas ?? 0);

        return [
            'id' => $r->id,
            'conta_id' => $r->account_id,
            'conta' => $r->account ? $r->account->code.' · '.$r->account->name : null,
            'dia' => $r->statement_date instanceof \DateTimeInterface
                ? $r->statement_date->format('Y-m-d') : (string) $r->statement_date,
            'saldo_do_extracto' => round((float) $r->statement_balance, 2),
            'saldo_contabilistico' => round((float) $r->book_balance, 2),
            'diferenca' => round((float) $r->difference, 2),
            'estado' => $r->status,
            'estado_rotulo' => $this->rotuloDoEstado($r->status),
            'linhas' => $total,
            'casadas' => $casadas,
            'por_casar' => max(0, $total - $casadas),
            'formato' => $r->file_type,
            'conciliado_em' => $r->reconciled_at?->format('Y-m-d H:i'),
        ];
    }

    private function rotuloDoEstado(?string $estado): string
    {
        return [
            'draft' => __('Por conciliar'),
            'reconciled' => __('Conciliada'),
            'approved' => __('Aprovada'),
        ][$estado] ?? (string) $estado;
    }

    /**
     * A FICHA — as linhas do extracto, com as sugestões de cada uma.
     *
     * É o ecrã que não existia: o botão «Ver» da lista não fazia nada, e sem ele
     * um extracto importado era um saco fechado.
     */
    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.reconciliation.view');

        $reconciliacao = $this->daCasa($id);
        $reconciliacao->load(['account:id,code,name', 'items.moveLine.move:id,ref,date']);

        $tenantId = $this->tenantId();

        return response()->json([
            'data' => $this->linha(
                $reconciliacao->loadCount(['items', 'items as casadas' => fn ($q) => $q->where('status', 'matched')])
            ) + [
                'linhas_do_extracto' => $reconciliacao->items
                    ->sortBy('transaction_date')
                    ->map(function (BankReconciliationItem $i) use ($tenantId, $reconciliacao) {
                        $casada = $i->status === 'matched';

                        return [
                            'id' => $i->id,
                            'dia' => $i->transaction_date instanceof \DateTimeInterface
                                ? $i->transaction_date->format('Y-m-d') : (string) $i->transaction_date,
                            'referencia' => $i->reference,
                            'descricao' => $i->description,
                            'valor' => round((float) $i->amount, 2),
                            'tipo' => $i->type,
                            'tipo_rotulo' => $i->type === 'credit' ? __('Entrada') : __('Saída'),
                            'estado' => $i->status,
                            'confianca' => $i->match_confidence === null ? null : (int) $i->match_confidence,
                            'lancamento' => $i->moveLine?->move?->ref,
                            'lancamento_dia' => $i->moveLine?->move?->date instanceof \DateTimeInterface
                                ? $i->moveLine->move->date->format('Y-m-d') : null,
                            /*
                             * AS SUGESTÕES só se calculam para as que faltam:
                             * são uma consulta por linha, e numa conciliação já
                             * fechada não servem para nada.
                             */
                            'sugestoes' => $casada ? [] : collect(
                                $this->servico->findMatchingSuggestions($i, $tenantId, $reconciliacao->account_id)
                            )->take(5)->map(fn ($s) => [
                                'linha_id' => $s['move_line_id'],
                                'confianca' => (int) $s['confidence'],
                                'lancamento' => $s['move_line']->move?->ref,
                                'dia' => $s['move_line']->move?->date instanceof \DateTimeInterface
                                    ? $s['move_line']->move->date->format('Y-m-d') : null,
                                'nota' => $s['move_line']->name ?: $s['move_line']->narration,
                                'debito' => round((float) $s['move_line']->debit, 2),
                                'credito' => round((float) $s['move_line']->credit, 2),
                            ])->values(),
                        ];
                    })->values(),
            ],
        ]);
    }

    public function importar(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.reconciliation.manage');

        $tenantId = $this->tenantId();

        $dados = $request->validate([
            'account_id' => ['required', 'integer'],
            'file_type' => ['required', Rule::in(['csv', 'mt940', 'ofx'])],
            'file' => ['required', 'file', 'max:10240'],
        ], [], [
            'account_id' => __('conta'), 'file_type' => __('formato'), 'file' => __('ficheiro'),
        ]);

        // A CONTA É DESTA EMPRESA: era `required` e mais nada, pelo que o
        // extracto podia ir conciliar contra a conta de outra companhia.
        $conta = Account::where('tenant_id', $tenantId)->find($dados['account_id']);

        if (! $conta) {
            throw ValidationException::withMessages([
                'account_id' => [__('Conta não encontrada nesta empresa.')],
            ]);
        }

        try {
            $reconciliacao = $this->servico->importStatementFile(
                $request->file('file'),
                $tenantId,
                $conta->id,
                $dados['file_type'],
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['file' => [$e->getMessage()]]);
        }

        $linhas = $reconciliacao->items()->count();

        /*
         * UM EXTRACTO SEM LINHAS NÃO É UMA IMPORTAÇÃO: é um ficheiro que o
         * leitor não soube ler. Dizê-lo é o mínimo — antes ficava uma
         * conciliação vazia dada por «reconciliada».
         */
        if ($linhas === 0) {
            $reconciliacao->delete();

            throw ValidationException::withMessages([
                'file' => [__('Não se leu nenhuma transacção deste ficheiro. Confirme o formato escolhido.')],
            ]);
        }

        return response()->json([
            'message' => __('Extracto importado: :n transacção(ões), :c casada(s) automaticamente.', [
                'n' => $linhas,
                'c' => $reconciliacao->items()->where('status', 'matched')->count(),
            ]),
            'id' => $reconciliacao->id,
        ], 201);
    }

    /** Casar uma linha do extracto com uma linha de lançamento, à mão. */
    public function casar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.reconciliation.manage');

        $dados = $request->validate([
            'linha_id' => ['required', 'integer'],
        ]);

        try {
            $this->servico->manualMatch($id, (int) $dados['linha_id'], $this->tenantId());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        }

        return response()->json(['message' => __('Linha conciliada.')]);
    }

    /** Desfazer: a linha volta a estar por conciliar. */
    public function desfazer(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.reconciliation.manage');

        $item = BankReconciliationItem::whereHas(
            'reconciliation',
            fn ($q) => $q->where('tenant_id', $this->tenantId())
        )->findOrFail($id);

        $this->servico->desfazer($item);

        return response()->json(['message' => __('Conciliação desfeita.')]);
    }

    /** Correr o casamento automático outra vez — depois de lançar o que faltava. */
    public function automatico(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.reconciliation.manage');

        $reconciliacao = $this->daCasa($id);

        $antes = $reconciliacao->items()->where('status', 'matched')->count();

        $this->servico->autoMatch($reconciliacao);
        $this->servico->recalcular($reconciliacao);

        $depois = $reconciliacao->items()->where('status', 'matched')->count();

        return response()->json([
            'message' => $depois > $antes
                ? __(':n linha(s) casada(s) automaticamente.', ['n' => $depois - $antes])
                : __('Nenhuma linha nova foi casada — as que faltam precisam de escolha à mão.'),
        ]);
    }

    /**
     * APROVAR a conciliação.
     *
     * O estado `approved` existia no `enum` e em lado nenhum: nada o punha lá. É
     * o que diz «isto foi conferido por alguém», e por isso só se aprova o que
     * está conciliado — com todas as linhas casadas.
     */
    public function aprovar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.reconciliation.manage');

        $reconciliacao = $this->daCasa($id);

        $porCasar = $reconciliacao->items()->where('status', '!=', 'matched')->count();

        if ($porCasar > 0) {
            throw ValidationException::withMessages([
                'geral' => [__('Faltam :n linha(s) por conciliar. Aprovar assim era dar por conferido o que não está.', [
                    'n' => $porCasar,
                ])],
            ]);
        }

        $reconciliacao->update([
            'status' => 'approved',
            'reconciled_by' => $request->user()?->id,
            'reconciled_at' => now(),
        ]);

        return response()->json(['message' => __('Conciliação aprovada.')]);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.reconciliation.manage');

        $reconciliacao = $this->daCasa($id);

        if ($reconciliacao->status === 'approved') {
            throw ValidationException::withMessages([
                'geral' => [__('Uma conciliação aprovada não se apaga: é o registo de que as contas foram conferidas.')],
            ]);
        }

        // As linhas vão com ela (a chave estrangeira é em cascata) — e nenhuma
        // delas mexeu em lançamento nenhum: conciliar não lança nada.
        $reconciliacao->delete();

        return response()->json(['message' => __('Conciliação eliminada.')]);
    }
}
