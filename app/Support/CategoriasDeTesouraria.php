<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * As categorias de uma transacção de tesouraria, e como se lêem.
 *
 * O ecrã de transacções oferecia seis opções escritas à mão — venda, compra,
 * salário, aluguer, utilidades, outro — e NENHUMA delas existia na base. O que
 * lá está de facto foi escrito por quem cria as transacções a sério:
 *
 *   · o POS grava o TIPO DO MÉTODO DE PAGAMENTO (cash, card, bank_transfer,
 *     digital_payment) — 1163 linhas numa base real;
 *   · o pagamento de facturas grava customer_payment / supplier_payment;
 *   · a nota de crédito grava credit_note.
 *
 * Ou seja: a coluna foi sendo usada como "por onde entrou o dinheiro" enquanto
 * o formulário perguntava "para que serviu". Quem escolhia uma categoria no
 * ecrã ficava com uma transacção que nenhum filtro e nenhum relatório
 * encontrava, porque o resto do sistema fala outra língua.
 *
 * Isto não inventa uma terceira língua. Assume as duas famílias que existem,
 * dá-lhes nome em português, e a lista que se mostra ao utilizador é sempre a
 * união do canónico com o que a empresa REALMENTE tem na base — assim nada do
 * que já lá está fica invisível nos filtros.
 */
class CategoriasDeTesouraria
{
    /**
     * O que a empresa faz com o dinheiro. É isto que se escolhe à mão.
     */
    public const OPERACIONAIS = [
        'sale'             => 'Venda',
        'customer_payment' => 'Recebimento de cliente',
        'purchase'         => 'Compra',
        'supplier_payment' => 'Pagamento a fornecedor',
        'salary'           => 'Salários',
        'rent'             => 'Renda',
        'utilities'        => 'Água, luz e comunicações',
        'tax'              => 'Impostos',
        'transfer'         => 'Transferência interna',
        'credit_note'      => 'Nota de crédito',
        'other'            => 'Outro',
    ];

    /**
     * Por onde o dinheiro entrou ou saiu.
     *
     * Escrito pelo POS e pelos pagamentos, não escolhido à mão — mas tem de
     * aparecer nos filtros e nos relatórios, senão a maior parte do movimento
     * de uma empresa fica de fora.
     */
    public const MEIOS = [
        'cash'            => 'Numerário',
        'card'            => 'Cartão / TPA',
        'bank_transfer'   => 'Transferência bancária',
        'digital_payment' => 'Pagamento digital',
        'check'           => 'Cheque',
        'mobile_money'    => 'Dinheiro móvel',
    ];

    /** Tudo o que se conhece, por chave. */
    public static function todas(): array
    {
        return self::OPERACIONAIS + self::MEIOS;
    }

    /** Como se lê uma chave. Desconhecidas devolvem-se legíveis, não em branco. */
    public static function nome(?string $chave): string
    {
        $chave = trim((string) $chave);

        if ($chave === '') {
            return 'Sem categoria';
        }

        return self::todas()[$chave] ?? ucfirst(str_replace('_', ' ', $chave));
    }

    /**
     * As categorias a mostrar a uma empresa: as conhecidas mais as que ela tem.
     *
     * A união com o que está na base é o que impede uma categoria antiga de
     * ficar invisível no filtro — e era exactamente isso que acontecia com as
     * quatro que o POS escreve.
     *
     * @return array<string, string> chave => nome legível
     */
    public static function paraEmpresa(?int $tenantId): array
    {
        $lista = self::todas();

        if (!$tenantId) {
            return $lista;
        }

        $naBase = DB::table('treasury_transactions')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->distinct()
            ->pluck('category');

        foreach ($naBase as $chave) {
            $lista[$chave] ??= self::nome($chave);
        }

        return $lista;
    }

    /**
     * Só as que fazem sentido escolher à mão, para o formulário.
     *
     * Os meios de pagamento ficam de fora: quem lança uma transacção à mão já
     * escolhe o método de pagamento no campo próprio, e repeti-lo aqui era
     * pedir a mesma coisa duas vezes com nomes diferentes.
     */
    public static function paraEscolher(): array
    {
        return self::OPERACIONAIS;
    }
}
