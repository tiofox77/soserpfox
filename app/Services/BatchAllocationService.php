<?php

namespace App\Services;

use App\Models\Invoicing\ProductBatch;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class BatchAllocationService
{
    /**
     * Aloca produtos de lotes usando método FIFO (First In, First Out)
     * Prioriza lotes mais antigos (por data de validade)
     * 
     * @param int $productId
     * @param int $warehouseId
     * @param float $quantityNeeded
     * @return array [success, allocations, message]
     */
    public function allocateFIFO($productId, $warehouseId, $quantityNeeded)
    {
        $allocations = [];
        $remainingQuantity = $quantityNeeded;
        
        // Buscar lotes ativos ordenados por FIFO (validade mais próxima primeiro)
        $batches = ProductBatch::where('tenant_id', activeTenantId())
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->orderBy('expiry_date', 'asc') // FIFO: mais antigo primeiro
            ->orderBy('created_at', 'asc')
            ->get();
        
        if ($batches->isEmpty()) {
            return [
                'success' => false,
                'allocations' => [],
                'message' => 'Nenhum lote disponível para este produto',
            ];
        }

        // Os lotes expirados SALTAM-SE; não abortam a alocação.
        //
        // Isto abortava a venda inteira assim que encontrasse um lote expirado
        // do mesmo artigo. Um lote esquecido no armazém — e há sempre um —
        // impedia de vender o produto todo, por tempo indeterminado, com uma
        // mensagem que não dizia o que fazer. Quem estivesse ao balcão não
        // tinha por onde sair.
        //
        // O expirado continua a contar como aviso: quem o vir na resposta sabe
        // que tem lá stock a apodrecer.
        [$expirados, $utilizaveis] = $batches->partition(fn ($batch) => $batch->is_expired);

        if ($utilizaveis->isEmpty()) {
            return [
                'success' => false,
                'allocations' => [],
                'message' => 'Só há lotes expirados deste produto: '
                    . ($expirados->pluck('batch_number')->filter()->join(', ') ?: 'sem número'),
                'expired_batches' => $expirados,
            ];
        }

        // Alocar quantidade de cada lote (FIFO)
        foreach ($utilizaveis as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }
            
            $quantityFromThisBatch = min($batch->quantity_available, $remainingQuantity);
            
            $allocations[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'quantity' => $quantityFromThisBatch,
                'expiry_date' => $batch->expiry_date,
                'days_until_expiry' => $batch->days_until_expiry,
            ];
            
            $remainingQuantity -= $quantityFromThisBatch;
        }
        
        // Verificar se conseguiu alocar toda a quantidade
        if ($remainingQuantity > 0) {
            // O disponível anunciado é o dos lotes UTILIZÁVEIS. Contar os
            // expirados aqui dizia ao operador que havia stock que ele não
            // podia vender, e a mensagem passava a mentir.
            $totalAvailable = $utilizaveis->sum('quantity_available');

            return [
                'success' => false,
                'allocations' => $allocations,
                'message' => sprintf(
                    'Quantidade insuficiente. Necessário: %.2f, Disponível: %.2f',
                    $quantityNeeded,
                    $totalAvailable
                ),
                'expired_batches' => $expirados,
            ];
        }

        return [
            'success' => true,
            'allocations' => $allocations,
            'message' => 'Alocação FIFO bem-sucedida',
            'expired_batches' => $expirados,
        ];
    }
    
    /**
     * Confirma a alocação e diminui quantidade dos lotes
     * 
     * @param array $allocations
     * @return bool
     */
    public function confirmAllocation(array $allocations)
    {
        DB::beginTransaction();
        
        try {
            foreach ($allocations as $allocation) {
                $batch = ProductBatch::findOrFail($allocation['batch_id']);
                $batch->decreaseQuantity($allocation['quantity']);
            }
            
            DB::commit();
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Reverte uma alocação (usado em cancelamento de vendas)
     * 
     * @param array $allocations
     * @return bool
     */
    public function revertAllocation(array $allocations)
    {
        DB::beginTransaction();
        
        try {
            foreach ($allocations as $allocation) {
                $batch = ProductBatch::findOrFail($allocation['batch_id']);
                $batch->increaseQuantity($allocation['quantity']);
            }
            
            DB::commit();
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Verifica disponibilidade de um produto considerando validade
     * 
     * @param int $productId
     * @param int $warehouseId
     * @param float $quantity
     * @return array [available, message, warnings]
     */
    public function checkAvailability($productId, $warehouseId, $quantity)
    {
        $batches = ProductBatch::where('tenant_id', activeTenantId())
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->get();
        
        [$expired, $utilizaveis] = $batches->partition(fn ($b) => $b->is_expired);

        // O disponível é o dos lotes que se PODEM vender. Somar os expirados
        // aqui dava uma resposta que a allocateFIFO logo a seguir desmentia:
        // "há 50 disponíveis" seguido de "quantidade insuficiente".
        $totalAvailable = $utilizaveis->sum('quantity_available');

        $expiringSoon = $utilizaveis->filter(fn ($b) => $b->is_expiring_soon);

        $warnings = [];

        if ($expired->isNotEmpty()) {
            $warnings[] = sprintf('%d lote(s) expirado(s)', $expired->count());
        }

        if ($expiringSoon->isNotEmpty()) {
            $warnings[] = sprintf('%d lote(s) expirando em breve', $expiringSoon->count());
        }

        return [
            'available' => $totalAvailable >= $quantity,
            'total_available' => $totalAvailable,
            'quantity_needed' => $quantity,
            'difference' => $totalAvailable - $quantity,
            'batches_count' => $utilizaveis->count(),
            'expired_count' => $expired->count(),
            'warnings' => $warnings,
        ];
    }
}
