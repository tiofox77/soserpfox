<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * MEXER NO STOCK À MÃO — ajustar, transferir, e a movimentação em lote.
 *
 * Vivia dentro do `StockManagement`. Ao migrar o ecrã para React, saiu para
 * aqui; o Livewire e a API chamam o mesmo. As REGRAS de stock continuam
 * onde sempre estiveram — nos ganchos do `StockMovement` e no `Stock`, que
 * mantêm o agregado do artigo — e este serviço não lhes passa por cima:
 * nunca `increment`, nunca `DB::table`.
 *
 * A MOVIMENTAÇÃO EM LOTE tem duas regras que custaram caro a aprender:
 *
 *  · UMA TRANSACÇÃO POR LINHA, e nenhuma a envolver o lote todo. Em
 *    REPEATABLE READ o instantâneo é tirado na primeira leitura: com 40
 *    linhas, o saldo lido para a última já não era o real, e uma venda feita
 *    entretanto era esmagada. E o lote inteiro bloqueava o stock da empresa
 *    enquanto alguém conferia um contentor.
 *
 *  · A REFERÊNCIA (MOV/AAAA/NNNNNN) é reservada por um bloqueio nomeado que
 *    a segura até ao fim — é isso que a torna única, não a transacção.
 */
class MovimentacaoDeStock
{
    public function ajustar(int $armazemId, int $produtoId, float $novaQuantidade, ?string $notas): void
    {
        StockMovement::createAdjustment($armazemId, $produtoId, $novaQuantidade, $notas);
    }

    /** @throws \Exception quando não há stock que chegue na origem */
    public function transferir(int $deArmazemId, int $paraArmazemId, int $produtoId, float $quantidade, ?string $notas): void
    {
        StockMovement::createTransfer($deArmazemId, $paraArmazemId, $produtoId, $quantidade, $notas);
    }

    /**
     * @param  array $itens  [{product_id, op: add|sub, quantity, unit_cost?, product_name?}]
     * @return array{referencia: ?string, ok: int, erros: string[]}
     */
    public function registarLote(int $armazemId, array $itens, ?string $notas, int $tenantId): array
    {
        $ok = 0;
        $erros = [];
        $referencia = null;

        StockMovement::comLoteReservado($tenantId, function (string $ref) use ($armazemId, $itens, $notas, $tenantId, &$ok, &$erros, &$referencia) {
            $referencia = $ref;

            foreach ($itens as $idx => $it) {
                try {
                    // Transacção da linha. Sem ela, uma saída sem stock deixava
                    // o movimento GRAVADO e o stock intacto.
                    DB::transaction(function () use ($it, $armazemId, $notas, $tenantId, $referencia, &$ok) {
                        $sai = ($it['op'] ?? 'add') === 'sub';
                        $qtd = (float) $it['quantity'];

                        // O saldo resultante calcula-se ANTES de criar o movimento:
                        // reler depois obrigava a um segundo save() por linha, e
                        // como o movimento é auditado isso duplicava a trilha.
                        $saldoActual = (float) Stock::where('tenant_id', $tenantId)
                            ->where('warehouse_id', $armazemId)
                            ->where('product_id', $it['product_id'])
                            ->value('quantity');

                        $dados = [
                            'warehouse_id' => $armazemId,
                            'product_id' => $it['product_id'],
                            'quantity' => $qtd,
                            'balance_after' => $sai ? $saldoActual - $qtd : $saldoActual + $qtd,
                            'unit_cost' => ! empty($it['unit_cost']) ? $it['unit_cost'] : null,
                            'batch_reference' => $referencia,
                            'notes' => $notas ?: ($sai ? 'Saída manual em lote' : 'Entrada manual em lote'),
                        ];

                        // Se o stock não chegar, `createExit` lança dentro do
                        // savepoint e nada disto fica.
                        $sai ? StockMovement::createExit($dados) : StockMovement::createEntry($dados);

                        // O agregado products.stock_quantity é mantido pelo
                        // StockObserver — nunca se soma aqui uma segunda vez.
                        $ok++;
                    });
                } catch (\Throwable $e) {
                    $erros[] = ($it['product_name'] ?? '#' . $idx) . ': ' . $e->getMessage();
                }
            }
        });

        return ['referencia' => $referencia, 'ok' => $ok, 'erros' => $erros];
    }

    /** Os últimos movimentos de um artigo, os mais recentes primeiro. */
    public function movimentos(int $produtoId, int $tenantId, int $limite = 50): Collection
    {
        return StockMovement::where('tenant_id', $tenantId)
            ->where('product_id', $produtoId)
            ->with(['warehouse:id,name', 'user:id,name'])
            ->latest('id')
            ->limit($limite)
            ->get();
    }
}
