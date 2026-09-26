<?php

namespace App\Services\Invoicing;

use Illuminate\Database\Eloquent\Model;

/**
 * O DESCONTO DO DOCUMENTO, LINHA A LINHA — num sítio só.
 *
 * Há documentos cujo desconto não está todo nas linhas:
 *
 *  · O BALCÃO (POS online e PWA) grava o desconto no DOCUMENTO. As linhas
 *    ficam ao preço cheio e o `net_total` já vem descontado.
 *  · A FACTURA NORMAL reparte o desconto global pelas linhas, mas grava a
 *    percentagem com 2 casas e o modelo recalcula o valor a partir dela. A
 *    soma das linhas fica uns kwanzas acima do `net_total`.
 *
 * Quem lia as linhas uma a uma via o preço sem desconto:
 *
 *  · A AGT recebia as linhas ao preço cheio e o netTotal descontado, e
 *    recusava (E23: «netTotal não corresponde à soma das linhas»). Foi o caso
 *    das FR 000099 e 000101 da Tecstore.
 *  · A NOTA DE CRÉDITO copiava as linhas ao preço cheio e anulava mais do que
 *    a factura valia. O travão do E43 parava-a, e a FR/000060 da Tecstore
 *    (289.900 com 15.900 de desconto) não se conseguia anular.
 *  · O SAF-T escrevia as linhas pelo bruto.
 *
 * Aqui a diferença entre a soma das linhas e o `net_total` desce às linhas, na
 * proporção do líquido de cada uma, e o último cêntimo fica na maior, para a
 * soma bater certa. O documento gravado não muda (nem o hash, que assina o
 * total); passa a ser lido da mesma maneira em todo o lado.
 */
final class DescontoDoDocumento
{
    /**
     * O bruto, o desconto e o líquido de cada linha, com o desconto do
     * documento já repartido.
     *
     * `desconto` é TUDO o que a linha teve de desconto: o seu e a sua parte do
     * global. É o `settlementAmount` da AGT. `liquido` é o `creditAmount` (ou o
     * `debitAmount` da NC) e a base do imposto.
     *
     * @param  iterable<Model|object>  $itens  as linhas, pela ordem em que vão ser lidas
     * @return list<array{bruto: float, desconto: float, liquido: float}>
     */
    public static function repartir(Model $documento, iterable $itens): array
    {
        $linhas = [];

        foreach ($itens as $item) {
            $linhas[] = [
                'bruto' => round((float) ($item->unit_price ?? 0) * (float) ($item->quantity ?? 0), 2),
                'liquido' => round(self::liquidoDaLinha($item), 2),
            ];
        }

        $linhas = self::descerAsLinhas($linhas, self::porRepartir($documento, $linhas));

        return array_map(fn (array $l) => [
            'bruto' => $l['bruto'],
            'desconto' => max(0.0, round($l['bruto'] - $l['liquido'], 2)),
            'liquido' => $l['liquido'],
        ], $linhas);
    }

    /**
     * O líquido de uma linha tal como foi gravada, antes do desconto global.
     *
     * As notas guardam-no nas colunas da AGT (a NC em `debit_amount`, a ND em
     * `credit_amount`) e o `subtotal` delas já é líquido. Numa linha de
     * factura, o `subtotal` é o bruto e o desconto da linha está à parte.
     */
    public static function liquidoDaLinha(object $item): float
    {
        foreach (['debit_amount', 'credit_amount'] as $coluna) {
            $valor = (float) ($item->{$coluna} ?? 0);

            if ($valor > 0) {
                return $valor;
            }
        }

        $bruto = isset($item->subtotal)
            ? (float) $item->subtotal
            : (float) ($item->unit_price ?? 0) * (float) ($item->quantity ?? 0);

        return $bruto - (float) ($item->discount_amount ?? 0);
    }

    /**
     * O que as linhas têm A MAIS do que o documento declara, quando é desconto.
     *
     * Só se reparte o que o DOCUMENTO diz que deu de desconto
     * (`discount_amount` + `discount_commercial`, com um cêntimo de folga).
     * Há facturas antigas com `net_total` a 0 (o valor por omissão da coluna)
     * e linhas cheias: sem este limite, a factura inteira passava por desconto
     * e a nota anulava zero. E só para BAIXO: linhas que somam MENOS do que o
     * `net_total` não se inflacionam. Nesses casos fica tudo como estava.
     */
    private static function porRepartir(Model $documento, array $linhas): float
    {
        $net = (float) ($documento->net_total ?? 0);

        if ($net <= 0 || $linhas === []) {
            return 0.0;
        }

        $falta = round(array_sum(array_column($linhas, 'liquido')) - $net, 2);
        $declarado = (float) ($documento->discount_amount ?? 0) + (float) ($documento->discount_commercial ?? 0);

        return $falta > 0 && $falta <= $declarado + 0.01 ? $falta : 0.0;
    }

    /**
     * Tira `$valor` às linhas, na proporção do líquido. As linhas negativas (o
     * sinal deduzido de uma estadia, por exemplo) não entram na conta.
     */
    private static function descerAsLinhas(array $linhas, float $valor): array
    {
        $pesos = array_map(fn (array $l) => max(0.0, $l['liquido']), $linhas);
        $total = array_sum($pesos);

        if ($valor <= 0 || $total <= 0) {
            return $linhas;
        }

        $valor = min($valor, $total);
        $maior = array_keys($pesos, max($pesos))[0];
        $repartido = 0.0;

        foreach ($linhas as $i => $l) {
            $parte = round($valor * $pesos[$i] / $total, 2);
            $linhas[$i]['liquido'] = round($l['liquido'] - $parte, 2);
            $repartido += $parte;
        }

        // O cêntimo que o arredondamento deixou fica na linha maior.
        $linhas[$maior]['liquido'] = round($linhas[$maior]['liquido'] - round($valor - $repartido, 2), 2);

        return $linhas;
    }

    /**
     * O preço unitário já descontado (o `unitPriceBase` da AGT): a quantidade
     * vezes ele tem de dar o líquido da linha (E21). Duas casas quando chegam;
     * quatro quando o líquido não se divide certo pela quantidade.
     */
    public static function precoLiquido(float $liquido, float $quantidade): float
    {
        if ($quantidade == 0.0) {
            return 0.0;
        }

        $duasCasas = round($liquido / $quantidade, 2);

        return abs(round($duasCasas * $quantidade, 2) - round($liquido, 2)) < 0.005
            ? $duasCasas
            : round($liquido / $quantidade, 4);
    }
}
