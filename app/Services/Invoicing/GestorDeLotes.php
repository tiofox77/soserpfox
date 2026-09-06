<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\ProductBatch;
use DomainException;

/**
 * OS LOTES E AS VALIDADES — criar, corrigir e apagar, num sítio só.
 *
 * Vivia dentro do `ProductBatches\ProductBatches`. Ao migrar o ecrã para
 * React, saiu para aqui; o Livewire e a API chamam o mesmo.
 *
 * O QUE ESTE CAMINHO GARANTE:
 *
 *  · O QUE JÁ SAIU DO LOTE É UM FACTO: está em movimentos de stock e em
 *    facturas. Corrigir o total não o pode alterar — o disponível passa a
 *    ser o total novo menos o que já saiu. A regra antiga era proporcional
 *    e inventava unidades: 100 no total, 40 disponíveis (60 saíram),
 *    corrigir para 90 dava 36 quando o certo é 30.
 *
 *  · UM LOTE JÁ USADO NÃO SE APAGA.
 */
class GestorDeLotes
{
    public function regras(): array
    {
        return [
            'product_id' => 'required|integer|exists:invoicing_products,id',
            'warehouse_id' => 'nullable|integer|exists:invoicing_warehouses,id',
            'batch_number' => 'nullable|string|max:100',
            'manufacturing_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:manufacturing_date',
            'quantity' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'alert_days' => 'required|integer|min:1|max:365',
            'notes' => 'nullable|string',
        ];
    }

    public function criar(array $d, int $tenantId): ProductBatch
    {
        return ProductBatch::create($this->valores($d) + [
            'tenant_id' => $tenantId,
            // Inicialmente tudo disponível.
            'quantity_available' => (float) $d['quantity'],
        ]);
    }

    public function actualizar(ProductBatch $lote, array $d): ProductBatch
    {
        $jaSaiu = max(0, (float) $lote->quantity - (float) $lote->quantity_available);

        $lote->update($this->valores($d) + [
            'quantity_available' => max(0, (float) $d['quantity'] - $jaSaiu),
        ]);

        return $lote;
    }

    /** @throws DomainException quando o lote já foi usado */
    public function apagar(ProductBatch $lote): void
    {
        if ((float) $lote->quantity_available < (float) $lote->quantity) {
            throw new DomainException(__('Não é possível excluir lote já utilizado!'));
        }

        $lote->delete();
    }

    private function valores(array $d): array
    {
        return [
            'product_id' => $d['product_id'],
            'warehouse_id' => ($d['warehouse_id'] ?? null) ?: null,
            'batch_number' => $d['batch_number'] ?? null,
            'manufacturing_date' => ($d['manufacturing_date'] ?? null) ?: null,
            'expiry_date' => ($d['expiry_date'] ?? null) ?: null,
            'quantity' => (float) $d['quantity'],
            'cost_price' => (float) ($d['cost_price'] ?? 0),
            'alert_days' => (int) $d['alert_days'],
            'notes' => $d['notes'] ?? null,
        ];
    }
}
