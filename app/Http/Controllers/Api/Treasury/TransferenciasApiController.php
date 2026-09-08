<?php

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Controller;
use App\Models\Treasury\Account;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\Transaction;
use App\Models\Treasury\Transfer;
use App\Services\Treasury\TreasuryMovementService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MOVER DINHEIRO ENTRE CONTAS E CAIXAS, para o ecrã em React.
 *
 * Uma transferência são TRÊS COISAS que têm de acontecer juntas ou nenhuma:
 * a saída na origem, a entrada no destino e — quando a há — a taxa, também
 * na origem. Tudo numa transacção da base.
 *
 * O QUE ESTAVA MAL NO ECRÃ DE SEMPRE:
 *
 *  · UMA TERCEIRA MANEIRA DE MEXER NO SALDO. O `applyBalance()` daqui fazia
 *    `increment` solto, sem bloqueio, ao lado do `TreasuryMovementService`
 *    que os outros ecrãs usam e do `updateBalance()` dos movimentos. Três
 *    implementações do mesmo facto divergem numa delas. Ficou uma.
 *
 *  · O NÚMERO REINICIAVA E CHOCAVA. `orderByDesc('id')` + `substr(-4) + 1`
 *    é exactamente o padrão que envenenou a sequência dos movimentos: basta
 *    um número fora do formato para o contador recomeçar. E a coluna tem
 *    índice único por empresa — duas transferências ao mesmo tempo davam
 *    1062 na cara do utilizador. Agora lê-se o MÁXIMO real da sequência do
 *    ano e repete-se em caso de colisão.
 *
 *  · A ORIGEM E O DESTINO NÃO ERAM VERIFICADOS. O ecrã mandava
 *    `"account:5"` e o servidor acreditava. Com um id de outra empresa
 *    nascia a transferência, nasciam os movimentos — e o saldo não mexia em
 *    lado nenhum, porque o `increment` já era filtrado pela empresa. Uma
 *    transferência a fingir, sem erro nenhum.
 *
 *  · ANULAR ADIVINHAVA O QUE TINHA SIDO FEITO. Devolvia `amount + fee` à
 *    origem por fórmula. Agora desfaz-se o que ESTÁ LÁ: cada movimento
 *    ligado à transferência é revertido pelo seu próprio valor, e só os
 *    concluídos — se a taxa nunca chegou a ser lançada, não se devolve.
 *
 *  · A MORADA NÃO PEDIA PERMISSÃO NENHUMA.
 */
class TransferenciasApiController extends Controller
{
    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'treasury.transfers.view');

        $tenantId = (int) activeTenantId();

        return response()->json([
            // O SALDO VAI JUNTO com cada origem possível: escolher de onde
            // sai o dinheiro sem ver quanto lá está é escolher às cegas.
            'contas' => Account::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('account_name')->get(['id', 'account_name', 'current_balance'])
                ->map(fn ($c) => ['id' => $c->id, 'nome' => $c->account_name, 'saldo' => (float) $c->current_balance])
                ->values(),

            'caixas' => CashRegister::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'current_balance'])
                ->map(fn ($c) => ['id' => $c->id, 'nome' => $c->name, 'saldo' => (float) $c->current_balance])
                ->values(),

            'moedas' => ['AOA', 'USD', 'EUR'],

            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('treasury.transfers.create'),
                'pode_anular' => (bool) $request->user()?->can('treasury.transfers.delete'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'treasury.transfers.view');

        $f = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'por_pagina' => ['nullable', 'integer', 'in:15,25,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenantId = (int) activeTenantId();

        $q = Transfer::with(['fromAccount', 'toAccount', 'fromCashRegister', 'toCashRegister', 'user'])
            ->where('tenant_id', $tenantId);

        if (! empty($f['procura'])) {
            $p = '%' . $f['procura'] . '%';
            $q->where(fn ($s) => $s->where('transfer_number', 'like', $p)
                ->orWhere('description', 'like', $p)
                ->orWhere('reference', 'like', $p));
        }

        $pagina = $q->orderByDesc('transfer_date')->orderByDesc('id')
            ->paginate((int) ($f['por_pagina'] ?? 15))->withQueryString();

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Transfer $t) => [
                'id' => $t->id,
                'numero' => $t->transfer_number,
                'data' => $t->transfer_date?->format('d/m/Y'),
                'de' => $t->fromAccount->account_name ?? $t->fromCashRegister->name ?? null,
                'de_e_caixa' => (bool) $t->from_cash_register_id,
                'para' => $t->toAccount->account_name ?? $t->toCashRegister->name ?? null,
                'para_e_caixa' => (bool) $t->to_cash_register_id,
                'valor' => (float) $t->amount,
                'taxa' => (float) $t->fee,
                'moeda' => $t->currency,
                'descricao' => $t->description,
                'referencia' => $t->reference,
                'autor' => $t->user->name ?? null,
            ])->values(),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'per_page' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
            'resumo' => [
                'transferencias' => Transfer::where('tenant_id', $tenantId)->count(),
                'movido' => (float) Transfer::where('tenant_id', $tenantId)->sum('amount'),
                'taxas' => (float) Transfer::where('tenant_id', $tenantId)->sum('fee'),
            ],
        ]);
    }

    public function criar(Request $request): JsonResponse
    {
        $this->exigir($request, 'treasury.transfers.create');

        $d = $request->validate([
            'de' => ['required', 'string', 'regex:/^(account|cash):[0-9]+$/'],
            'para' => ['required', 'string', 'different:de', 'regex:/^(account|cash):[0-9]+$/'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'in:AOA,USD,EUR'],
            'transfer_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
        ], [
            'para.different' => __('A conta de destino deve ser diferente da origem.'),
        ]);

        $tenantId = (int) activeTenantId();

        // A ORIGEM E O DESTINO TÊM DE SER DESTA EMPRESA, e existir. Sem isto
        // nascia uma transferência a fingir: documento e movimentos gravados,
        // saldo intocado.
        $origem = $this->destino($d['de'], $tenantId, 'de');
        $destino = $this->destino($d['para'], $tenantId, 'para');

        $valor = round((float) $d['amount'], 2);
        $taxa = round((float) ($d['fee'] ?? 0), 2);
        $moeda = $d['currency'] ?? 'AOA';

        $transferencia = DB::transaction(function () use ($d, $tenantId, $origem, $destino, $valor, $taxa, $moeda) {
            $t = $this->gravarTransferencia($tenantId, $d, $origem, $destino, $valor, $taxa, $moeda);

            $servico = app(TreasuryMovementService::class);

            $lancar = fn (array $alvo, string $tipo, float $quanto, string $texto, string $categoria) => $servico->post([
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'account_id' => $alvo['account_id'],
                'cash_register_id' => $alvo['cash_register_id'],
                'type' => $tipo,
                'category' => $categoria,
                'amount' => $quanto,
                'currency' => $moeda,
                'transaction_date' => $d['transfer_date'],
                'reference' => $t->transfer_number,
                'related_type' => Transfer::class,
                'related_id' => $t->id,
                'description' => $texto,
                'status' => 'completed',
            ]);

            // Saída na origem, entrada no destino — e a taxa, que sai de
            // quem manda. É o serviço que move o saldo, sob bloqueio.
            $lancar($origem['alvo'], 'expense', $valor,
                __('Transferência :numero → :destino', ['numero' => $t->transfer_number, 'destino' => $destino['nome']]), 'transfer');

            $lancar($destino['alvo'], 'income', $valor,
                __('Transferência :numero ← :origem', ['numero' => $t->transfer_number, 'origem' => $origem['nome']]), 'transfer');

            if ($taxa > 0) {
                $lancar($origem['alvo'], 'expense', $taxa,
                    __('Taxa da transferência :numero', ['numero' => $t->transfer_number]), 'transfer_fee');
            }

            return $t;
        });

        return response()->json([
            'id' => $transferencia->id,
            'numero' => $transferencia->transfer_number,
            'message' => __('Transferência registada com sucesso!'),
        ], 201);
    }

    /**
     * ANULAR: desfazer o que ESTÁ LÁ, e não o que se supõe ter sido feito.
     *
     * Cada movimento ligado à transferência é revertido pelo seu próprio
     * valor — e só os concluídos. O ecrã de sempre devolvia `amount + fee`
     * à origem por fórmula: se a taxa nunca chegasse a ser lançada, devolvia
     * dinheiro que nunca saiu.
     */
    public function anular(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'treasury.transfers.delete');

        DB::transaction(function () use ($id) {
            $t = Transfer::where('tenant_id', activeTenantId())->lockForUpdate()->findOrFail($id);

            $servico = app(TreasuryMovementService::class);

            $ligados = Transaction::where('tenant_id', $t->tenant_id)
                ->where('related_type', Transfer::class)
                ->where('related_id', $t->id)
                ->lockForUpdate()->get();

            foreach ($ligados as $m) {
                if ($m->status === 'completed') {
                    $servico->apply($m, -1);
                }

                $m->delete();
            }

            $t->delete();
        });

        return response()->json(['message' => __('Transferência anulada e saldos revertidos.')]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /**
     * A transferência, com número à prova de colisão.
     *
     * A coluna tem índice único por empresa. Duas pessoas a registar ao
     * mesmo tempo calculam o mesmo número; em vez de rebentar, apanha-se o
     * 1062 e tenta-se o seguinte.
     *
     * @param  array{alvo: array{account_id: int|null, cash_register_id: int|null}, nome: string}  $origem
     * @param  array{alvo: array{account_id: int|null, cash_register_id: int|null}, nome: string}  $destino
     */
    private function gravarTransferencia(int $tenantId, array $d, array $origem, array $destino, float $valor, float $taxa, string $moeda): Transfer
    {
        $base = [
            'tenant_id' => $tenantId,
            'user_id' => auth()->id(),
            'from_account_id' => $origem['alvo']['account_id'],
            'from_cash_register_id' => $origem['alvo']['cash_register_id'],
            'to_account_id' => $destino['alvo']['account_id'],
            'to_cash_register_id' => $destino['alvo']['cash_register_id'],
            'amount' => $valor,
            'currency' => $moeda,
            'fee' => $taxa,
            'transfer_date' => $d['transfer_date'],
            'description' => $d['description'] ?? null,
            'reference' => $d['reference'] ?? null,
            'status' => 'completed',
        ];

        for ($tentativa = 0; $tentativa < 6; $tentativa++) {
            try {
                return Transfer::create($base + ['transfer_number' => $this->proximoNumero($tenantId)]);
            } catch (QueryException $e) {
                if (($e->getCode() === '23000' || str_contains($e->getMessage(), '1062')) && $tentativa < 5) {
                    usleep(random_int(1000, 6000));

                    continue;
                }

                throw $e;
            }
        }

        // Último recurso: um número único garantido, para nunca prender a
        // operação por causa do nome do papel.
        return Transfer::create($base + [
            'transfer_number' => 'TRF-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4))),
        ]);
    }

    /**
     * O próximo número, pelo MÁXIMO real da sequência do ano.
     *
     * O `substr(-4)` sobre a última linha por id dava lixo assim que
     * aparecia um número fora do formato — e o contador recomeçava no 1.
     */
    private function proximoNumero(int $tenantId): string
    {
        $inicio = 'TRF-' . date('Y') . '-';

        $ultimo = Transfer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('transfer_number', 'like', $inicio . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(transfer_number, "-", -1) AS UNSIGNED) DESC')
            ->value('transfer_number');

        $seq = $ultimo ? ((int) substr($ultimo, strrpos($ultimo, '-') + 1)) + 1 : 1;

        return $inicio . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * `"account:5"` ou `"cash:3"` → o destino verdadeiro, desta empresa.
     *
     * @return array{alvo: array{account_id: int|null, cash_register_id: int|null}, nome: string}
     */
    private function destino(string $escolha, int $tenantId, string $campo): array
    {
        [$que, $id] = array_pad(explode(':', $escolha), 2, null);

        if ($que === 'cash') {
            $caixa = CashRegister::where('tenant_id', $tenantId)->where('is_active', true)->find((int) $id);

            if (! $caixa) {
                throw ValidationException::withMessages([$campo => __('Escolha um caixa activo desta empresa.')]);
            }

            return ['alvo' => ['account_id' => null, 'cash_register_id' => $caixa->id], 'nome' => $caixa->name];
        }

        $conta = Account::where('tenant_id', $tenantId)->where('is_active', true)->find((int) $id);

        if (! $conta) {
            throw ValidationException::withMessages([$campo => __('Escolha uma conta bancária activa desta empresa.')]);
        }

        return ['alvo' => ['account_id' => $conta->id, 'cash_register_id' => null], 'nome' => $conta->account_name];
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
