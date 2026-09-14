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
    /*
     * O AJUSTE E A TRANSFERÊNCIA DE UM ARTIGO SÓ SÃO UM LOTE DE UMA LINHA.
     *
     * Gravavam-se com `createAdjustment`/`createTransfer`: sem referência, sem
     * saldos, sem quem — e a lista das transferências, que agrupa pelo lote,
     * juntava-os TODOS numa «transferência» sem número (na farmácia, 5468
     * ajustes e 8 transferências de meses, com o nome e a hora do último). E a
     * transferência avulsa nem mexia nos lotes de validade.
     *
     * Agora passam pelo mesmo serviço do modal «Transferir» e do «Ajustar em
     * lote»: referência MOV/, saldos antes/depois, os lotes por FEFO e o papel
     * em PDF. Devolvem a referência.
     */

    /**
     * Põe a linha na quantidade pedida. Regista a DIFERENÇA como entrada ou
     * saída; se já estava certa, não regista nada e devolve null.
     *
     * @throws \DomainException
     */
    public function ajustar(int $armazemId, int $produtoId, float $novaQuantidade, ?string $notas, ?int $tenantId = null, ?int $userId = null): ?string
    {
        $tenantId ??= (int) activeTenantId();
        $userId ??= auth()->id();

        $actual = (float) (Stock::where('tenant_id', $tenantId)->where('warehouse_id', $armazemId)->where('product_id', $produtoId)->value('quantity') ?? 0);
        $diferenca = round($novaQuantidade - $actual, 4);

        if (abs($diferenca) < 0.0001) {
            return null;
        }

        $motivo = trim((string) $notas) !== ''
            ? trim((string) $notas)
            : __('Correcção de stock: de :antes para :depois', ['antes' => $actual + 0, 'depois' => $novaQuantidade + 0]);

        return app(TransferenciaDeStock::class)->ajustarEmLote(
            $armazemId,
            $diferenca > 0 ? 'in' : 'out',
            $motivo,
            [['product_id' => $produtoId, 'quantity' => abs($diferenca)]],
            $tenantId,
            $userId
        )['referencia'];
    }

    /** @throws \DomainException quando não há stock que chegue na origem */
    public function transferir(int $deArmazemId, int $paraArmazemId, int $produtoId, float $quantidade, ?string $notas, ?int $tenantId = null, ?int $userId = null): string
    {
        return app(TransferenciaDeStock::class)->entreArmazens(
            $deArmazemId,
            $paraArmazemId,
            [['product_id' => $produtoId, 'quantity' => $quantidade]],
            $notas,
            $tenantId ?? (int) activeTenantId(),
            $userId ?? auth()->id()
        )['referencia'];
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
