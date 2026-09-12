<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * A CONTA PARA ONDE OS CLIENTES TRANSFEREM.
 *
 * Estava escrita à mão em DOIS Blade — o assistente de registo e o modal de
 * mudança de plano — e as duas cópias já não diziam o mesmo:
 *
 *   registo:     «SOSERP Sistemas Lda»          AO06 0000 0000 1234 5678 9012 3
 *   minha conta: «SOS ERP - SISTEMAS DE GESTÃO» AO06 0040 0000 1234 5678 9012 3
 *
 * Pelo menos uma delas estava errada, e o que está errado num IBAN é dinheiro
 * de um cliente que não chega a lado nenhum. É a armadilha de sempre: a mesma
 * regra escrita em dois sítios acaba diferente.
 *
 * Agora vive nas definições do sistema (grupo `billing`), lê-se daqui, e
 * muda-se num sítio só.
 */
final class ContaDaPlataforma
{
    /** Os valores de arranque — os que estavam no assistente de registo. */
    private const PADRAO = [
        'billing_bank_name' => 'BAI - Banco Angolano de Investimentos',
        'billing_account_holder' => 'SOSERP Sistemas Lda',
        'billing_iban' => 'AO06 0000 0000 1234 5678 9012 3',
    ];

    /**
     * @return array{banco:string, titular:string, iban:string}
     */
    public static function dados(): array
    {
        return [
            'banco' => (string) SystemSetting::get('billing_bank_name', self::PADRAO['billing_bank_name']),
            'titular' => (string) SystemSetting::get('billing_account_holder', self::PADRAO['billing_account_holder']),
            'iban' => (string) SystemSetting::get('billing_iban', self::PADRAO['billing_iban']),
        ];
    }

    /**
     * A REFERÊNCIA QUE O CLIENTE ESCREVE NA TRANSFERÊNCIA.
     *
     * É por ela que se liga o dinheiro que entrou ao pedido que o espera —
     * sem ela, um extracto bancário é uma lista de valores sem dono.
     */
    public static function referencia(int $utilizador, ?int $pedido = null): string
    {
        return $pedido
            ? 'ORD-'.str_pad((string) $pedido, 6, '0', STR_PAD_LEFT)
            : 'REF-'.$utilizador.'-'.now()->format('Ymd');
    }
}
