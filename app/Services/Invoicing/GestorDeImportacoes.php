<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\Import;

/**
 * AS IMPORTAÇÕES — o registo e o percurso, num sítio só.
 *
 * Vivia dentro do `Imports\Imports`. Ao migrar o ecrã para React, saiu para
 * aqui; o Livewire e a API chamam o mesmo.
 *
 * Uma importação é um processo, não um documento fiscal: nasce em cotação,
 * anda pelos estados (pedido, pagamento, trânsito, alfândega, armazém) até
 * concluída. O CIF é sempre FOB + frete + seguro — calcula-se aqui e não se
 * aceita do ecrã, para os três valores e a soma nunca discordarem.
 */
class GestorDeImportacoes
{
    public const ESTADOS = [
        'quotation' => 'Cotação',
        'order_placed' => 'Pedido Realizado',
        'payment_pending' => 'Pagamento Pendente',
        'payment_confirmed' => 'Pagamento Confirmado',
        'in_transit' => 'Em Trânsito',
        'customs_pending' => 'Desembaraço Pendente',
        'customs_inspection' => 'Inspeção Alfandegária',
        'customs_cleared' => 'Desembaraçado',
        'in_warehouse' => 'No Armazém',
        'completed' => 'Concluído',
        'cancelled' => 'Cancelado',
    ];

    public const TRANSPORTES = [
        'maritime' => 'Marítimo',
        'air' => 'Aéreo',
        'land' => 'Terrestre',
    ];

    public function regras(): array
    {
        return [
            'supplier_id' => 'required|integer|exists:invoicing_suppliers,id',
            'warehouse_id' => 'nullable|integer|exists:invoicing_warehouses,id',
            'reference' => 'nullable|string|max:100',
            'order_date' => 'required|date',
            'expected_arrival_date' => 'nullable|date|after_or_equal:order_date',
            'origin_country' => 'required|string|max:100',
            'origin_port' => 'nullable|string|max:100',
            'destination_port' => 'nullable|string|max:100',
            'shipping_company' => 'nullable|string|max:150',
            'transport_type' => 'required|in:' . implode(',', array_keys(self::TRANSPORTES)),
            'fob_value' => 'required|numeric|min:0',
            'freight_cost' => 'nullable|numeric|min:0',
            'insurance_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function criar(array $d, int $tenantId): Import
    {
        return Import::create(array_merge($this->valores($d), [
            'tenant_id' => $tenantId,
            'status' => 'quotation',
        ]));
    }

    public function actualizar(Import $i, array $d): Import
    {
        $i->update($this->valores($d));

        return $i;
    }

    public function mudarEstado(Import $i, string $estado): Import
    {
        if (! array_key_exists($estado, self::ESTADOS)) {
            throw new \InvalidArgumentException("Estado desconhecido: {$estado}");
        }

        $i->status = $estado;
        $i->save();

        return $i;
    }

    /** O CIF é FOB + frete + seguro — sempre, calculado aqui. */
    public static function cif(float $fob, float $frete, float $seguro): float
    {
        return round($fob + $frete + $seguro, 2);
    }

    private function valores(array $d): array
    {
        $fob = (float) ($d['fob_value'] ?? 0);
        $frete = (float) ($d['freight_cost'] ?? 0);
        $seguro = (float) ($d['insurance_cost'] ?? 0);

        return [
            'supplier_id' => $d['supplier_id'],
            'warehouse_id' => ($d['warehouse_id'] ?? null) ?: null,
            'reference' => $d['reference'] ?? null,
            'order_date' => $d['order_date'],
            'expected_arrival_date' => ($d['expected_arrival_date'] ?? null) ?: null,
            'origin_country' => $d['origin_country'],
            'origin_port' => $d['origin_port'] ?? null,
            'destination_port' => ($d['destination_port'] ?? null) ?: 'Luanda',
            'shipping_company' => $d['shipping_company'] ?? null,
            'transport_type' => $d['transport_type'],
            'fob_value' => $fob,
            'freight_cost' => $frete,
            'insurance_cost' => $seguro,
            'cif_value' => self::cif($fob, $frete, $seguro),
            'notes' => $d['notes'] ?? null,
        ];
    }
}
