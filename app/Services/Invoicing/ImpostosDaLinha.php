<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\LineTax;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * IEC e Imposto de Selo de uma linha de documento.
 *
 * Vivia dentro do ecrã de criar factura de venda. Os outros documentos —
 * proforma de venda, factura e proforma de compra — não os calculavam nem os
 * gravavam: o mesmo artigo com IEC saía com o imposto na factura e sem ele na
 * proforma que a antecedeu.
 *
 * Estes impostos ACRESCEM ao IVA e têm de entrar no total do documento. Sem
 * eles netTotal + taxPayable ≠ grossTotal, e a AGT recusa.
 *
 * Onde ficam gravados: `invoicing_line_taxes`, a tabela polimórfica de
 * impostos por linha. É a forma que a AGT usa — `taxes[]` é uma LISTA, porque
 * a mesma linha pode ter IVA, IEC e IS ao mesmo tempo. Não são colunas na
 * linha; já se tentou por engano acrescentá-las e foi desfeito.
 */
class ImpostosDaLinha
{
    /**
     * Calcula os impostos extra de uma linha.
     *
     * @param  float       $base        preço × quantidade, já com desconto
     * @param  string|null $pautalIec   código pautal escolhido, se houver
     * @param  string|null $verbaIs     número da verba de selo, se houver
     */
    public static function calcular(float $base, ?string $pautalIec, ?string $verbaIs): array
    {
        $taxas = [];

        if (!empty($pautalIec)) {
            $iec = DB::table('agt_iec_pautal_codes')->where('pautal_code', $pautalIec)->first();

            if ($iec) {
                $pct = (float) ($iec->rate_percentage ?? 0);

                $taxas[] = [
                    'tax_type'       => 'IEC',
                    'tax_percentage' => $pct,
                    'tax_amount'     => round($base * $pct / 100, 2),
                    'pautal_code'    => $iec->pautal_code,
                    'verba_no'       => null,
                    'description'    => $iec->description,
                ];
            }
        }

        if (!empty($verbaIs)) {
            $verba = DB::table('agt_is_verbas')->where('verba_no', $verbaIs)->first();

            if ($verba) {
                // A verba pode ser percentual ("1%") ou de valor fixo ("AOA 100").
                $numero = (float) preg_replace('/[^0-9.]/', '', (string) $verba->rate);
                $fixa   = ($verba->rate_type ?? '') === 'FIXED';

                $taxas[] = [
                    'tax_type'       => 'IS',
                    'tax_percentage' => $fixa ? 0 : $numero,
                    'tax_amount'     => $fixa ? round($numero, 2) : round($base * $numero / 100, 2),
                    'pautal_code'    => null,
                    'verba_no'       => (string) $verba->verba_no,
                    'description'    => $verba->description,
                ];
            }
        }

        return $taxas;
    }

    /**
     * Grava os impostos extra de uma linha, substituindo os que lá estavam.
     *
     * Apaga primeiro: numa edição, os impostos antigos deixam de valer e
     * somar-lhes os novos daria imposto a dobrar.
     *
     * @return float o total gravado, para entrar no total do documento
     */
    public static function gravar(Model $linha, array $taxas, ?string $regiao = null): float
    {
        LineTax::where('line_type', get_class($linha))
            ->where('line_id', $linha->getKey())
            ->delete();

        $total = 0.0;

        foreach ($taxas as $taxa) {
            LineTax::create(array_merge($taxa, [
                'tenant_id'          => $linha->tenant_id ?: activeTenantId(),
                'line_type'          => get_class($linha),
                'line_id'            => $linha->getKey(),
                'tax_country_region' => $regiao ?: ($linha->tax_country_region ?: 'AO'),
                // No Imposto de Selo o código é a VERBA. 'NOR' é código de IVA
                // e a AGT recusa a combinação.
                'tax_code'           => $taxa['tax_type'] === 'IS'
                    ? (string) $taxa['verba_no']
                    : 'NOR',
            ]));

            $total += (float) $taxa['tax_amount'];
        }

        return round($total, 2);
    }
}
