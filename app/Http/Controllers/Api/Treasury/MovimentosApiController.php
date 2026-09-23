<?php

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Controller;
use App\Models\Treasury\Account;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\Transaction;
use App\Models\Treasury\TransactionCategory;
use App\Models\Treasury\TransactionType;
use App\Services\POS\GavetaDoTurno;
use App\Services\Treasury\TreasuryMovementService;
use App\Support\CategoriasDeTesouraria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OS MOVIMENTOS DA TESOURARIA, para o ecrã em React.
 *
 * O DINHEIRO MEXE-SE NUM SÍTIO SÓ — o `TreasuryMovementService`. Ele grava o
 * movimento, gera o número pela sequência real do ano e move o saldo da conta
 * ou do caixa sob bloqueio. O ecrã em Livewire chamava-o para gravar mas
 * tinha, ao lado, um `updateBalance()` próprio com `increment`/`decrement`
 * soltos — uma segunda maneira de mexer no mesmo saldo, sem bloqueio. Aqui
 * há uma só.
 *
 * O QUE NÃO PASSOU PARA CÁ, e porquê:
 *
 *  · O `transaction_number` calculado à mão antes de gravar. O serviço
 *    ignora-o e gera o seu — era um número que nunca chegava à base.
 *
 *  · O `createCreditNoteFromTransaction`, que montava uma nota de crédito
 *    com `CreditNote::create` e copiava as linhas da factura. Era código
 *    MORTO: o `openCreditModal` já desviava para o relatório do POS sempre
 *    que a transacção tinha factura, e sem factura aquele ramo nunca corria.
 *    Ainda bem — a nota que ele fazia nascia sem série, sem hash SAFT e sem ir
 *    à AGT, e punha a factura como creditada à mesma. Anular uma venda tem
 *    uma porta: o `EmissorDeNotas`, pelo relatório do POS ou pelo ecrã das
 *    notas de crédito.
 *
 * AS PERMISSÕES, que a morada `/treasury/transactions` não exigia. As quatro
 * `treasury.transactions.*` existiam e nenhuma rota as aplicava: bastava ter
 * o módulo activo para lançar, editar e apagar movimentos de dinheiro.
 */
class MovimentosApiController extends Controller
{
    /* ─── O que o ecrã precisa de saber uma vez ───────────────────────── */

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'treasury.transactions.view');

        $tenantId = (int) activeTenantId();

        // As categorias de omissão nascem à primeira abertura, como no ecrã
        // de sempre — uma empresa nova não pode ficar com a lista vazia.
        TransactionCategory::seedDefaultsForTenant($tenantId);

        return response()->json([
            'formas_de_pagamento' => PaymentMethod::where('tenant_id', $tenantId)
                ->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'type', 'default_account_id', 'default_cash_register_id'])
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'nome' => $m->name,
                    'tipo' => $m->type,
                    // O DESTINO QUE O MÉTODO JÁ SABE. É o que o ecrã escrevia
                    // sozinho ao escolher o método (`updatedFormPaymentMethodId`):
                    // dinheiro cai no caixa, o resto na conta.
                    'conta_padrao' => $m->default_account_id,
                    'caixa_padrao' => $m->default_cash_register_id,
                ])->values(),

            'contas' => Account::where('tenant_id', $tenantId)
                ->where('is_active', true)->orderBy('account_name')
                ->get(['id', 'account_name'])
                ->map(fn ($c) => ['id' => $c->id, 'nome' => $c->account_name])->values(),

            'caixas' => CashRegister::where('tenant_id', $tenantId)
                ->where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'nome' => $c->name])->values(),

            'tipos' => TransactionType::where('tenant_id', $tenantId)
                ->where('is_active', true)->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'nature'])
                ->map(fn ($t) => ['id' => $t->id, 'nome' => $t->name, 'natureza' => $t->nature])->values(),

            /*
             * TODAS as categorias, cada uma com o tipo a que pertence.
             *
             * O ecrã em Livewire pedia-as ao servidor outra vez a cada troca
             * de tipo. Aqui vêm de uma vez e o ecrã filtra: as que não têm
             * tipo servem sempre, as outras só ao tipo que declaram.
             */
            'categorias' => TransactionCategory::where('tenant_id', $tenantId)
                ->where('is_active', true)->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'code', 'transaction_type_id'])
                ->map(fn ($c) => [
                    'id' => $c->id, 'nome' => $c->name, 'codigo' => $c->code,
                    'tipo_id' => $c->transaction_type_id,
                ])->values(),

            // O filtro conta com mais do que o catálogo: as categorias que o
            // POS e o restaurante escrevem existem nos movimentos sem estarem
            // na tabela, e sem isto não apareciam no filtro.
            'categorias_para_filtrar' => collect(CategoriasDeTesouraria::paraEmpresa($tenantId))
                ->map(fn ($nome, $chave) => ['valor' => (string) $chave, 'rotulo' => $nome])
                ->values(),

            'moedas' => ['AOA', 'USD', 'EUR'],

            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('treasury.transactions.create'),
                'pode_editar' => (bool) $request->user()?->can('treasury.transactions.edit'),
                'pode_apagar' => (bool) $request->user()?->can('treasury.transactions.delete'),
            ],
        ]);
    }

    /* ─── A lista ─────────────────────────────────────────────────────── */

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'treasury.transactions.view');

        $f = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'tipo' => ['nullable', 'in:income,expense,transfer'],
            'estado' => ['nullable', 'in:pending,completed,cancelled'],
            'categoria' => ['nullable', 'string', 'max:120'],
            'conta' => ['nullable', 'integer'],
            'caixa' => ['nullable', 'integer'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'por_pagina' => ['nullable', 'integer', 'in:10,15,25,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenantId = (int) activeTenantId();

        $lista = $this->filtrar(
            Transaction::with(['paymentMethod', 'account', 'cashRegister', 'user'])->where('tenant_id', $tenantId),
            $f
        );

        // O ESTADO fica fora dos filtros partilhados: os totais em cima só
        // contam as concluídas, a lista mostra o que o utilizador escolher.
        if (! empty($f['estado'])) {
            $lista->where('status', $f['estado']);
        }

        $pagina = $lista->orderBy('transaction_date', 'desc')->orderBy('id', 'desc')
            ->paginate((int) ($f['por_pagina'] ?? 25))->withQueryString();

        // OS TOTAIS SEGUEM OS MESMOS FILTROS DA LISTA. Somar tudo desde
        // sempre por cima de uma lista filtrada por um mês faz desconfiar
        // dos dois números — e com razão.
        $base = fn () => $this->filtrar(
            Transaction::where('tenant_id', $tenantId)->where('status', 'completed'),
            $f
        );

        $entradas = (float) $base()->where('type', 'income')->sum('amount');
        $saidas = (float) $base()->where('type', 'expense')->sum('amount');

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Transaction $t) => $this->linha($t))->values(),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'per_page' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
            'resumo' => [
                'movimentos' => $pagina->total(),
                'entradas' => round($entradas, 2),
                'saidas' => round($saidas, 2),
                'saldo' => round($entradas - $saidas, 2),
            ],
        ]);
    }

    /* ─── A ficha ─────────────────────────────────────────────────────── */

    public function mostrar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'treasury.transactions.view');

        $t = Transaction::with(['paymentMethod', 'account', 'cashRegister', 'user',
            'salesInvoice.client', 'transactionType', 'transactionCategory'])
            ->where('tenant_id', activeTenantId())->findOrFail($id);

        $ficha = $this->linha($t) + [
            'notas' => $t->notes,
            'moeda' => $t->currency,
            'conta' => $t->account?->account_name,
            'caixa' => $t->cashRegister?->name,
            'tipo_nome' => $t->transactionType?->name,
            'criado_por' => $t->user?->name,
            'criado_em' => $t->created_at?->format('d/m/Y H:i'),
            'factura' => null,
            'compra' => null,
            'nota_de_credito' => null,
        ];

        if ($f = $t->salesInvoice) {
            $ficha['factura'] = [
                'id' => $f->id,
                'numero' => method_exists($f, 'numeroInterno') ? $f->numeroInterno() : $f->invoice_number,
                'numero_agt' => method_exists($f, 'numeroAgt') ? $f->numeroAgt() : null,
                'data' => $f->invoice_date?->format('d/m/Y'),
                'cliente' => $f->client->name ?? null,
                'total' => (float) $f->total,
                'estado' => $f->status,
                'estado_rotulo' => $this->rotuloDaFactura($f->status),
                'morada' => route('invoicing.sales.invoices.preview', $f->id),
            ];
        }

        /*
         * A FACTURA DE COMPRA, que o ecrã de sempre nunca chegou a mostrar.
         *
         * A relação `purchaseInvoice()` do modelo aponta para uma coluna
         * `purchase_invoice_id` que não existe — a coluna chama-se
         * `purchase_id`. Sem erro nenhum: o Eloquent lê um atributo que não
         * está lá, dá null, e o bloco inteiro do modal ficava por desenhar.
         * Aqui lê-se a coluna verdadeira.
         */
        if ($t->purchase_id) {
            $c = \App\Models\Invoicing\PurchaseInvoice::with('supplier')
                ->where('tenant_id', $t->tenant_id)->find($t->purchase_id);

            if ($c) {
                $ficha['compra'] = [
                    'id' => $c->id,
                    'numero' => $c->invoice_number,
                    'data' => $c->invoice_date?->format('d/m/Y'),
                    'fornecedor' => $c->supplier->name ?? null,
                    'total' => (float) $c->total,
                    'estado' => $c->status,
                    'estado_rotulo' => $this->rotuloDaFactura($c->status),
                    'morada' => route('invoicing.purchases.invoices.preview', $c->id),
                ];
            }
        }

        // A NOTA DE CRÉDITO ligada a um estorno: o movimento guarda
        // `CREDIT-<número original>` na referência, e é por aí que se chega
        // à factura que a nota anulou.
        if ($t->category === 'credit_note' && str_starts_with((string) $t->reference, 'CREDIT-')) {
            $original = Transaction::where('tenant_id', $t->tenant_id)
                ->where('transaction_number', substr((string) $t->reference, 7))->first();

            if ($original?->invoice_id) {
                $nc = \App\Models\Invoicing\CreditNote::with('client')
                    ->where('tenant_id', $t->tenant_id)
                    ->where('invoice_id', $original->invoice_id)
                    ->orderByDesc('created_at')->first();

                if ($nc) {
                    $ficha['nota_de_credito'] = [
                        'id' => $nc->id,
                        'numero' => $nc->credit_note_number,
                        'data' => $nc->issue_date?->format('d/m/Y'),
                        'motivo' => $nc->reason_label,
                        'cliente' => $nc->client->name ?? null,
                        'total' => (float) $nc->total,
                        'estado' => $nc->status,
                        'morada' => route('invoicing.credit-notes.edit', $nc->id),
                    ];
                }
            }
        }

        return response()->json($ficha);
    }

    /* ─── Gravar ──────────────────────────────────────────────────────── */

    public function criar(Request $request): JsonResponse
    {
        $this->exigir($request, 'treasury.transactions.create');

        $dados = $this->validado($request);

        $movimento = DB::transaction(function () use ($dados) {
            $m = app(TreasuryMovementService::class)->post($dados + [
                'tenant_id' => activeTenantId(),
                'user_id' => auth()->id(),
            ]);

            // Na caixa de um operador com turno aberto, o turno fica a saber
            // (uma despesa paga da gaveta, um reforço de troco).
            GavetaDoTurno::registar($m);

            return $m;
        });

        return response()->json([
            'id' => $movimento->id,
            'numero' => $movimento->transaction_number,
            'message' => __('Transação criada e saldo atualizado com sucesso!'),
        ], 201);
    }

    public function actualizar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'treasury.transactions.edit');

        $dados = $this->validado($request);

        DB::transaction(function () use ($id, $dados) {
            $servico = app(TreasuryMovementService::class);

            $m = Transaction::where('tenant_id', activeTenantId())->lockForUpdate()->findOrFail($id);

            /*
             * DESFAZER COM OS VALORES ANTIGOS, refazer com os novos.
             *
             * A ordem importa: o primeiro `apply(-1)` usa o valor, o tipo e o
             * destino que a linha AINDA tem. Mudar a conta de destino de um
             * movimento já lançado sem isto deixava o saldo antigo inflado
             * para sempre.
             */
            if ($m->status === 'completed') {
                $servico->apply($m, -1);
            }

            // O turno também se refaz: sai o que lá estava, entra o novo.
            GavetaDoTurno::desfazer($m);

            $m->update($dados);

            if ($m->status === 'completed') {
                $servico->apply($m, 1);
            }

            if (GavetaDoTurno::eDaGaveta($m)) {
                GavetaDoTurno::registar($m->fresh());
            }
        });

        return response()->json(['message' => __('Transação atualizada e saldos recalculados com sucesso!')]);
    }

    public function eliminar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'treasury.transactions.delete');

        DB::transaction(function () use ($id) {
            $m = Transaction::where('tenant_id', activeTenantId())->lockForUpdate()->findOrFail($id);

            if ($m->status === 'completed') {
                app(TreasuryMovementService::class)->apply($m, -1);
            }

            GavetaDoTurno::desfazer($m);

            $m->delete();
        });

        return response()->json(['message' => __('Transação eliminada com sucesso!')]);
    }

    /* ─── Estornar ────────────────────────────────────────────────────── */

    /**
     * O ESTORNO: uma saída que devolve o que entrou.
     *
     * Só entradas se estornam, e só as concluídas — estornar uma que ainda
     * não mexeu no saldo tirava dinheiro que nunca lá esteve.
     *
     * COM FACTURA ASSOCIADA NÃO SE FAZ AQUI. Anular uma venda é emitir uma
     * nota de crédito: com linhas escolhidas, imposto recalculado, stock
     * reposto, hash e comunicação à AGT. O ecrã recebe a morada do relatório
     * do POS, que faz isso pela porta própria.
     */
    public function creditar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'treasury.transactions.create');

        $m = Transaction::where('tenant_id', activeTenantId())->findOrFail($id);

        if ($m->type !== 'income') {
            return response()->json(['message' => __('Apenas transações de entrada podem ser creditadas!')], 422);
        }

        if ($m->status !== 'completed') {
            return response()->json(['message' => __('Só se estorna uma transação concluída.')], 422);
        }

        if ($m->invoice_id) {
            return response()->json([
                'message' => __('Esta transação tem uma factura associada: anule-a com uma nota de crédito.'),
                'redireccionar' => route('invoicing.pos.reports', ['credit_transaction' => $m->id]),
            ], 409);
        }

        $estorno = DB::transaction(fn () => GavetaDoTurno::comRegisto(app(TreasuryMovementService::class)->post([
            'tenant_id' => $m->tenant_id,
            'user_id' => auth()->id(),
            'type' => 'expense',
            'category' => 'credit_note',
            'amount' => $m->amount,
            'currency' => $m->currency,
            'transaction_date' => now()->toDateString(),
            'payment_method_id' => $m->payment_method_id,
            'account_id' => $m->account_id,
            'cash_register_id' => $m->cash_register_id,
            'reference' => 'CREDIT-' . $m->transaction_number,
            'description' => __('Crédito/Estorno da transação :numero', ['numero' => $m->transaction_number]),
            'notes' => __('Creditado em :quando', ['quando' => now()->format('d/m/Y H:i')]),
            'status' => 'completed',
        ])));

        return response()->json([
            'id' => $estorno->id,
            'numero' => $estorno->transaction_number,
            'message' => __('Transação creditada com sucesso!'),
        ], 201);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function validado(Request $request): array
    {
        $tenantId = (int) activeTenantId();

        $d = $request->validate([
            'transaction_type_id' => ['required', Rule::exists('treasury_transaction_types', 'id')
                ->where('tenant_id', $tenantId)->where('is_active', true)],
            'transaction_category_id' => ['nullable', Rule::exists('treasury_transaction_categories', 'id')
                ->where('tenant_id', $tenantId)->where('is_active', true)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'in:AOA,USD,EUR'],
            'transaction_date' => ['required', 'date'],
            'payment_method_id' => ['required', Rule::exists('treasury_payment_methods', 'id')->where('tenant_id', $tenantId)],
            'account_id' => ['nullable', Rule::exists('treasury_accounts', 'id')->where('tenant_id', $tenantId)],
            'cash_register_id' => ['nullable', Rule::exists('treasury_cash_registers', 'id')->where('tenant_id', $tenantId)],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:pending,completed,cancelled'],
        ]);

        /*
         * OMITIR UM CAMPO NÃO É ERRO DE SERVIDOR.
         *
         * Uma regra `nullable` que não recebe nada NÃO põe a chave no array
         * validado. Ler `$d['account_id']` directamente rebentava com 500 em
         * qualquer cliente que simplesmente não mandasse o campo — e a
         * diferença entre «mandou null» e «não mandou» não interessa a
         * ninguém aqui.
         */
        $d['account_id'] = ($d['account_id'] ?? null) ?: null;
        $d['cash_register_id'] = ($d['cash_register_id'] ?? null) ?: null;
        $d['transaction_category_id'] = ($d['transaction_category_id'] ?? null) ?: null;
        $d['reference'] = $d['reference'] ?? null;
        $d['notes'] = $d['notes'] ?? null;

        /*
         * UM DESTINO, E SÓ UM.
         *
         * O saldo move-se numa conta OU num caixa. Com os dois preenchidos o
         * serviço escolhia a conta e ignorava o caixa em silêncio; com nenhum
         * o movimento ficava a pairar, sem mexer em saldo nenhum.
         */
        if (empty($d['account_id']) === empty($d['cash_register_id'])) {
            throw ValidationException::withMessages([
                'account_id' => __('Seleccione exactamente um destino: conta bancária ou caixa.'),
            ]);
        }

        // A NATUREZA VEM DO TIPO, não do ecrã. É o tipo de movimento que diz
        // se é entrada ou saída, e é isso que decide o sinal no saldo.
        $tipo = TransactionType::where('tenant_id', $tenantId)->find($d['transaction_type_id']);
        $d['type'] = $tipo?->nature ?? 'income';

        // O código textual da categoria acompanha o id: os filtros antigos, o
        // POS e os relatórios lêem-no.
        $d['category'] = $d['transaction_category_id']
            ? (TransactionCategory::where('tenant_id', $tenantId)->find($d['transaction_category_id'])?->code ?? '')
            : '';

        return $d;
    }

    /**
     * Os filtros da lista e dos totais — num sítio só.
     *
     * A lista e os números do topo têm de ver exactamente o mesmo. Repetir as
     * condições em dois sítios é como eles passam a discordar.
     *
     * @param  array<string, mixed>  $f
     */
    private function filtrar($q, array $f)
    {
        if (! empty($f['procura'])) {
            $p = '%' . $f['procura'] . '%';
            $q->where(fn ($s) => $s->where('transaction_number', 'like', $p)
                ->orWhere('description', 'like', $p)
                ->orWhere('reference', 'like', $p));
        }

        if (! empty($f['tipo'])) {
            $q->where('type', $f['tipo']);
        }

        if (! empty($f['categoria'])) {
            $q->where('category', $f['categoria']);
        }

        if (! empty($f['conta'])) {
            $q->where('account_id', $f['conta']);
        }

        if (! empty($f['caixa'])) {
            $q->where('cash_register_id', $f['caixa']);
        }

        if (! empty($f['de'])) {
            $q->whereDate('transaction_date', '>=', $f['de']);
        }

        if (! empty($f['ate'])) {
            $q->whereDate('transaction_date', '<=', $f['ate']);
        }

        return $q;
    }

    /** @return array<string, mixed> */
    private function linha(Transaction $t): array
    {
        return [
            'id' => $t->id,
            'numero' => $t->transaction_number,
            'data' => $t->transaction_date?->format('Y-m-d'),
            'data_curta' => $t->transaction_date?->format('d/m/Y'),
            'descricao' => $t->description,
            'tipo' => $t->type,
            'categoria' => $t->category,
            'categoria_nome' => $t->category ? CategoriasDeTesouraria::nome($t->category) : null,
            'forma_de_pagamento' => $t->paymentMethod->name ?? null,
            'valor' => (float) $t->amount,
            'moeda' => $t->currency,
            'estado' => $t->status,
            'referencia' => $t->reference,
            // O ecrã precisa de saber se há factura por trás para mandar o
            // estorno pela porta certa antes mesmo de a tentar.
            'factura_id' => $t->invoice_id,
            'compra_id' => $t->purchase_id,
            // Os campos que o modal de editar volta a pôr no formulário.
            'transaction_type_id' => $t->transaction_type_id,
            'transaction_category_id' => $t->transaction_category_id,
            'payment_method_id' => $t->payment_method_id,
            'account_id' => $t->account_id,
            'cash_register_id' => $t->cash_register_id,
            'notas' => $t->notes,
        ];
    }

    private function rotuloDaFactura(?string $estado): string
    {
        return match ($estado) {
            'draft' => __('Rascunho'),
            'paid' => __('Paga'),
            'credited' => __('Creditada'),
            'cancelled' => __('Cancelada'),
            default => ucfirst((string) $estado),
        };
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
