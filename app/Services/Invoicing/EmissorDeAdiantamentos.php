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
        'card' => 'Cartão',
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

    /**
     * @param  array{account_id?: ?int, cash_register_id?: ?int}  $destino  para onde vai o dinheiro,
     *         quando quem chama já o escolheu (o excedente de um pagamento vai para a
     *         mesma gaveta do recibo). Vazio: a regra de sempre (a caixa do operador).
     * @param  bool  $noTurno  se entra no turno aberto de quem o regista. O ecrã dos
     *         Adiantamentos segue a opção «Documentos no fecho de turno»
     *         (DocumentosNoTurno); o excedente de um pagamento vai sempre, com o recibo.
     */
    public function criar(array $d, int $tenantId, ?int $autorId, array $destino = [], bool $noTurno = true): Advance
    {
        $adiantamento = Advance::create([
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

        $this->lancarNaTesouraria($adiantamento, $autorId, $noTurno, $destino);

        return $adiantamento;
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

        // O VALOR MUDOU: o movimento antigo deixa de valer. Desfaz-se pelo
        // caminho certo — devolvendo o valor ao saldo da caixa — e lança-se o
        // novo. Só se chega aqui com o adiantamento por usar.
        //
        // O TURNO TAMBÉM (23/09/2026): a linha antiga sai do turno ainda aberto
        // e entra a nova. Se o turno de então já fechou, fica como foi contado
        // e a correcção não vai ao turno de hoje — o dinheiro não entrou hoje.
        $dinheiro = app(LancamentoDeDinheiro::class);
        $estavaNumTurnoAberto = $dinheiro->retirarDoTurno($a) > 0;
        $dinheiro->estornar($a);
        $this->lancarNaTesouraria($a->fresh(), $a->created_by, $estavaNumTurnoAberto);

        return $a;
    }

    /**
     * O DINHEIRO DO ADIANTAMENTO EXISTE.
     *
     * O adiantamento é dinheiro que o cliente entregou — muitas vezes em mão,
     * ao balcão. Gravava-se o documento e o valor não aparecia na tesouraria,
     * na gaveta nem no fecho de turno: a empresa tinha o dinheiro e os livros
     * não sabiam dele.
     *
     * Pela mesma porta que o recibo usa, e com a data do adiantamento — não a
     * de agora.
     */
    private function lancarNaTesouraria(Advance $a, ?int $userId, bool $comTurno = true, array $destino = []): void
    {
        $numero = $a->advance_number ?: ('#' . $a->id);

        app(LancamentoDeDinheiro::class)->lancar($a, [
            'valor' => (float) $a->amount,
            'forma' => (string) $a->payment_method,
            'sentido' => 'income',
            'categoria' => 'customer_payment',
            'data' => $a->payment_date,
            'referencia' => 'Adiantamento ' . $numero,
            'descricao' => __('Adiantamento :n', ['n' => $numero])
                . ($a->purpose ? ' — ' . $a->purpose : ''),
            'notas' => $a->notes,
            'destino' => $destino,
            // Entra ao balcão como qualquer recebimento: conta no fecho.
            'turno' => $comTurno ? ['type' => 'receipt', 'reference_number' => $a->advance_number] : null,
        ], $userId);
    }
}
