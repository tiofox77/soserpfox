<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Invoicing\Waste;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * O registo de uma quebra — e a sua anulação.
 *
 * A REGRA DO STOCK MANDA AQUI: as linhas de movimento são a fonte de verdade
 * e o observer aplica o agregado. Esta classe cria o movimento e deixa-o
 * trabalhar — nunca toca no stock à mão.
 *
 * O CUSTO CONGELA-SE no momento: o relatório de perdas tem de sobreviver a
 * mudanças de preço posteriores. Congela-se o CUSTO do artigo (não o preço de
 * venda — a perda é o que se gastou, não o que se sonhava ganhar); quem não
 * preenche custos vê zeros, que é a verdade possível.
 */
class QuebraDeStock
{
    public function registar(array $dados, int $tenantId, ?int $userId): Waste
    {
        $quantidade = (float) ($dados['quantity'] ?? 0);

        if ($quantidade <= 0) {
            throw new InvalidArgumentException('A quantidade da quebra tem de ser maior que zero.');
        }

        if (! array_key_exists($dados['reason'] ?? '', Waste::MOTIVOS)) {
            throw new InvalidArgumentException('Escolha um motivo da lista.');
        }

        $produto = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $dados['product_id']);

        // Um serviço não expira nem se parte — quebra de serviço é um engano
        // de escolha, e é melhor dizê-lo já do que sujar o relatório.
        if ($produto->type === 'servico') {
            throw new InvalidArgumentException('Um serviço não tem stock para quebrar — escolha um produto.');
        }

        // O movimento de stock exige armazém — e uma quebra sem armazém era
        // uma perda sem lugar. O indicado, senão o primeiro activo da casa.
        $armazemId = $dados['warehouse_id'] ?? null;

        if ($armazemId) {
            Warehouse::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail((int) $armazemId);
        } else {
            $armazemId = Warehouse::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');

            if (! $armazemId) {
                throw new InvalidArgumentException('A empresa não tem armazéns — crie um antes de registar quebras.');
            }
        }

        return DB::transaction(function () use ($dados, $produto, $armazemId, $quantidade, $tenantId, $userId) {
            $custo = round((float) $produto->cost, 2);

            // O movimento aplica o stock sozinho (observer) — é assim que as
            // vendas e as compras o fazem, e a quebra não é especial.
            $movimento = StockMovement::create([
                'tenant_id' => $tenantId,
                'warehouse_id' => $armazemId,
                'product_id' => $produto->id,
                'type' => StockMovement::TYPE_OUT,
                'quantity' => $quantidade,
                'unit_cost' => $custo,
                'total_cost' => round($custo * $quantidade, 2),
                'reference_type' => 'quebra',
                'user_id' => $userId,
                'notes' => 'Quebra: '.(Waste::MOTIVOS[$dados['reason']] ?? $dados['reason']),
            ]);

            return Waste::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'product_id' => $produto->id,
                'warehouse_id' => $armazemId,
                'quantity' => $quantidade,
                'reason' => $dados['reason'],
                'notes' => trim((string) ($dados['notes'] ?? '')) ?: null,
                'unit_cost' => $custo,
                'total_cost' => round($custo * $quantidade, 2),
                'stock_movement_id' => $movimento->id,
                'source_module' => $dados['source_module'] ?? 'invoicing',
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * Anula uma quebra registada por engano — com o movimento CONTRÁRIO,
     * nunca apagando nada. O histórico do stock tem de contar a história
     * toda, incluindo os enganos.
     */
    public function anular(Waste $quebra, int $tenantId, ?int $userId): Waste
    {
        if ($quebra->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Quebra de outra empresa.');
        }

        if ($quebra->anulada()) {
            return $quebra;
        }

        return DB::transaction(function () use ($quebra, $tenantId, $userId) {
            $reverso = StockMovement::create([
                'tenant_id' => $tenantId,
                'warehouse_id' => $quebra->warehouse_id,
                'product_id' => $quebra->product_id,
                'type' => StockMovement::TYPE_IN,
                'quantity' => (float) $quebra->quantity,
                'unit_cost' => (float) $quebra->unit_cost,
                'total_cost' => (float) $quebra->total_cost,
                'reference_type' => 'quebra_anulada',
                'reference_id' => $quebra->id,
                'user_id' => $userId,
                'notes' => 'Anulação da quebra #'.$quebra->id,
            ]);

            $quebra->update([
                'annulled_at' => now(),
                'annulled_by' => $userId,
                'reversal_movement_id' => $reverso->id,
            ]);

            return $quebra->fresh();
        });
    }
}
