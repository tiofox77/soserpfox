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
        
        // Verificar se há lotes expirados
        $expiredBatches = $batches->filter(function ($batch) {
            return $batch->is_expired;
        });
        
        if ($expiredBatches->isNotEmpty()) {
            $expiredBatchNumbers = $expiredBatches->pluck('batch_number')->filter()->join(', ');
            return [
                'success' => false,
                'allocations' => [],
                'message' => 'Lotes expirados encontrados: ' . ($expiredBatchNumbers ?: 'Sem número'),
                'expired_batches' => $expiredBatches,
            ];
        }
        
        // Alocar quantidade de cada lote (FIFO)
        foreach ($batches as $batch) {
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
            $totalAvailable = $batches->sum('quantity_available');
            return [
                'success' => false,
                'allocations' => $allocations,
                'message' => sprintf(
                    'Quantidade insuficiente. Necessário: %.2f, Disponível: %.2f',
                    $quantityNeeded,
                    $totalAvailable
                ),
            ];
        }
        
        return [
            'success' => true,
            'allocations' => $allocations,
            'message' => 'Alocação FIFO bem-sucedida',
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
        
        $totalAvailable = $batches->sum('quantity_available');
        $expiringSoon = $batches->filter(fn($b) => $b->is_expiring_soon);
        $expired = $batches->filter(fn($b) => $b->is_expired);
        
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
            'batches_count' => $batches->count(),
            'warnings' => $warnings,
        ];
    }
}
