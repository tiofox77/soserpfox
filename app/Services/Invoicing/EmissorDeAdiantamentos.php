<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\Advance;
use DomainException;

/**
 * REGISTAR UM ADIANTAMENTO — num sítio só.
 *
 * Vivia dentro do `Advances\AdvanceCreate`. Ao migrar o ecrã para React,
 * saiu para aqui; o Livewire e a API chamam o mesmo.
 *
 * O adiantamento é dinheiro recebido de um cliente ANTES de haver factura.
 * Nasce disponível por inteiro, e vai sendo usado quando se paga uma
 * factura com ele (ver o modal de pagamento). Por isso:
 *
 *  · UM ADIANTAMENTO JÁ USADO NÃO SE EDITA. Mudar-lhe o valor depois de
 *    parte dele ter abatido uma factura deixava o que resta sem conta.
 *
 *  · NUMA EDIÇÃO O RESTANTE VOLTA A SER O VALOR — só é possível porque
 *    nada foi usado ainda.
 *
 * O número (ADV-…) e o hash saem dos ganchos do modelo.
 */
class EmissorDeAdiantamentos
{
    /** As formas de pagamento, com o rótulo que o ecrã mostra. */
    public const FORMAS = [
        'cash' => 'Dinheiro',
        'transfer' => 'Transferência',
        'multicaixa' => 'Multicaixa',
        'tpa' => 'TPA',
        'check' => 'Cheque',
        'mbway' => 'MB Way',
        'other' => 'Outro',
    ];

    public function regras(): array
    {
        return [
            'client_id' => 'required|integer',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:' . implode(',', array_keys(self::FORMAS)),
            'purpose' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function criar(array $d, int $tenantId, ?int $autorId): Advance
    {
        return Advance::create([
            'tenant_id' => $tenantId,
            'type' => 'sale',
            'client_id' => $d['client_id'],
            'payment_date' => $d['payment_date'],
            'amount' => $d['amount'],
            'payment_method' => $d['payment_method'],
            'purpose' => $d['purpose'] ?? null,
            'notes' => $d['notes'] ?? null,
            'status' => 'available',
            'created_by' => $autorId,
        ]);
    }

    /** @throws DomainException quando já foi usado */
    public function actualizar(Advance $a, array $d): Advance
    {
        if ((float) $a->used_amount > 0) {
            throw new DomainException(__('Não é possível editar adiantamento já utilizado.'));
        }

        $a->update([
            'client_id' => $d['client_id'],
            'payment_date' => $d['payment_date'],
            'amount' => $d['amount'],
            'remaining_amount' => $d['amount'],
            'payment_method' => $d['payment_method'],
            'purpose' => $d['purpose'] ?? null,
            'notes' => $d['notes'] ?? null,
        ]);

        return $a;
    }
}
