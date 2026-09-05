<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockCount;
use App\Models\Invoicing\StockCountItem;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A contagem física, do abrir ao fechar.
 *
 * AS REGRAS QUE NÃO SE NEGOCEIAM:
 *
 *   · o esperado congela-se À ABERTURA — as vendas continuam enquanto se
 *     conta, e comparar o contado com um sistema em movimento dava
 *     diferenças de fantasma;
 *   · cada diferença fecha-se com um MOVIMENTO verdadeiro (in/out,
 *     `reference_type='contagem'`) — as linhas são a fonte de verdade e o
 *     observer aplica o agregado; nunca se escreve um número por cima;
 *   · a contagem fica FECHADA como documento: quem contou, quando, quanto.
 */
class ContagemFisica
{
    /** Abre a contagem e congela a fotografia do sistema. */
    public function abrir(int $warehouseId, int $tenantId, ?int $userId, ?string $notas = null): StockCount
    {
        Warehouse::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($warehouseId);

        // Uma contagem aberta por armazém: duas ao mesmo tempo contavam o
        // mesmo stock e fechavam a mesma diferença duas vezes.
        $jaAberta = StockCount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'open')
            ->exists();

        if ($jaAberta) {
            throw new InvalidArgumentException('Já há uma contagem aberta neste armazém — feche-a ou cancele-a primeiro.');
        }

        return DB::transaction(function () use ($warehouseId, $tenantId, $userId, $notas) {
            $contagem = StockCount::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'warehouse_id' => $warehouseId,
                'status' => 'open',
                'notes' => $notas,
                'opened_by' => $userId,
            ]);

            // A FOTOGRAFIA: todos os artigos com stock gerido, com o que o
            // sistema diz AGORA. Inclui os a zero — a prateleira pode ter o
            // que o sistema perdeu, e é precisamente isso que se procura.
            $produtos = Product::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where('manage_stock', true)
                ->where('type', '!=', 'servico')
                ->get(['id', 'cost']);

            $saldos = Stock::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('warehouse_id', $warehouseId)
                ->pluck('quantity', 'product_id');

            $agora = now();

            $linhas = $produtos->map(fn ($p) => [
                'tenant_id' => $tenantId,
                'stock_count_id' => $contagem->id,
                'product_id' => $p->id,
                'expected_quantity' => (float) ($saldos[$p->id] ?? 0),
                'counted_quantity' => null,
                'unit_cost' => round((float) $p->cost, 2),
                'created_at' => $agora,
                'updated_at' => $agora,
            ])->all();

            foreach (array_chunk($linhas, 500) as $bloco) {
                StockCountItem::withoutGlobalScopes()->insert($bloco);
            }

            return $contagem->fresh();
        });
    }

    /** Regista o contado de um artigo. Null volta a «por contar». */
    public function contar(StockCount $contagem, int $productId, ?float $contado, int $tenantId, ?int $userId): StockCountItem
    {
        $this->minhaEAberta($contagem, $tenantId);

        if ($contado !== null && $contado < 0) {
            throw new InvalidArgumentException('Não se conta uma quantidade negativa.');
        }

        $linha = StockCountItem::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('stock_count_id', $contagem->id)
            ->where('product_id', $productId)
            ->firstOrFail();

        $linha->update([
            'counted_quantity' => $contado,
            'counted_by' => $contado === null ? null : $userId,
        ]);

        return $linha->fresh();
    }

    /**
     * Fecha a contagem: cada diferença vira um movimento verdadeiro.
     *
     * Só o que foi CONTADO conta — um artigo deixado em branco não é um
     * zero, é um «não fui lá ver», e acertá-lo a zero seria inventar uma
     * perda gigante por preguiça do software.
     */
    public function fechar(StockCount $contagem, int $tenantId, ?int $userId): StockCount
    {
        $this->minhaEAberta($contagem, $tenantId);

        return DB::transaction(function () use ($contagem, $tenantId, $userId) {
            $linhas = StockCountItem::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('stock_count_id', $contagem->id)
                ->whereNotNull('counted_quantity')
                ->lockForUpdate()
                ->get();

            if ($linhas->isEmpty()) {
                throw new InvalidArgumentException('Nada foi contado — não há nada para fechar.');
            }

            $ajustadas = 0;
            $custoDosAjustes = 0.0;

            foreach ($linhas as $linha) {
                $diferenca = round((float) $linha->counted_quantity - (float) $linha->expected_quantity, 4);

                if (abs($diferenca) < 0.0001) {
                    continue;
                }

                // PELO MECANISMO DA CASA: um movimento `adjustment`, cuja
                // quantidade é o VALOR FINAL — o observer fixa o agregado a
                // esse valor (é a semântica documentada do ajuste). A
                // contagem é a única autoridade que pode dizer «o stock É
                // isto»: o número veio da prateleira, não de um ecrã.
                $movimento = StockMovement::create([
                    'tenant_id' => $tenantId,
                    'warehouse_id' => $contagem->warehouse_id,
                    'product_id' => $linha->product_id,
                    'type' => StockMovement::TYPE_ADJUSTMENT,
                    'quantity' => (float) $linha->counted_quantity,
                    'balance_before' => (float) $linha->expected_quantity,
                    'balance_after' => (float) $linha->counted_quantity,
                    'unit_cost' => (float) $linha->unit_cost,
                    'total_cost' => round(abs($diferenca) * (float) $linha->unit_cost, 2),
                    'reference_type' => 'contagem',
                    'reference_id' => $contagem->id,
                    'user_id' => $userId,
                    'notes' => sprintf('Contagem #%d: esperado %s, contado %s (diferença %+.4f)',
                        $contagem->id, $linha->expected_quantity, $linha->counted_quantity, $diferenca),
                ]);

                $linha->update(['adjustment_movement_id' => $movimento->id]);

                $ajustadas++;
                $custoDosAjustes += abs($diferenca) * (float) $linha->unit_cost;
            }

            $contagem->update([
                'status' => 'closed',
                'closed_by' => $userId,
                'closed_at' => now(),
                'items_counted' => $linhas->count(),
                'items_adjusted' => $ajustadas,
                'adjustment_cost' => round($custoDosAjustes, 2),
            ]);

            return $contagem->fresh();
        });
    }

    public function cancelar(StockCount $contagem, int $tenantId): StockCount
    {
        $this->minhaEAberta($contagem, $tenantId);

        $contagem->update(['status' => 'cancelled']);

        return $contagem->fresh();
    }

    private function minhaEAberta(StockCount $contagem, int $tenantId): void
    {
        if ($contagem->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Contagem de outra empresa.');
        }

        if (! $contagem->aberta()) {
            throw new InvalidArgumentException('Esta contagem já foi fechada ou cancelada.');
        }
    }
}
