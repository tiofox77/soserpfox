<?php

namespace App\Traits;

/**
 * O número do documento nas DUAS séries: a da casa e a da AGT.
 *
 * O `..._number` gravado leva a série registada na AGT, que é um código
 * críptico — FT4226S75324N/000002. A série INTERNA, aquela por que a empresa
 * chama o documento (SOSFT/000002), vive na série ligada. Nas listagens e nos
 * papéis mostram-se as duas: a interna primeiro, porque é a que se reconhece e
 * por onde se procura, e a da AGT logo abaixo.
 *
 * PORQUE É UM TRAIT. Isto nasceu dentro do modelo das facturas de venda. As
 * notas de crédito e de débito, os recibos e as proformas têm exactamente a
 * mesma estrutura de série, e cada um deles a ia repetir. Duas cópias da mesma
 * fórmula divergem à primeira alteração — a numeração é a última coisa onde se
 * quer isso.
 *
 * Quem usar isto precisa de uma relação `series()` e de uma coluna com o
 * número fiscal; a coluna é encontrada sozinha, porque cada documento lhe
 * chama um nome diferente.
 */
trait NumeracaoInternaEAgt
{
    /** Os nomes que a coluna do número fiscal toma, por tipo de documento. */
    private const COLUNAS_DO_NUMERO = [
        'invoice_number',
        'credit_note_number',
        'debit_note_number',
        'receipt_number',
        'proforma_number',
        'quote_number',
        'number',
    ];

    /** O número fiscal gravado, seja qual for o nome da coluna. */
    public function numeroFiscalBruto(): string
    {
        foreach (self::COLUNAS_DO_NUMERO as $coluna) {
            if (!empty($this->{$coluna})) {
                return (string) $this->{$coluna};
            }
        }

        return '';
    }

    /** Parte sequencial do número (ex.: "000001"), comum às duas séries. */
    public function numeroSequencia(): ?string
    {
        return preg_match('~/(\d+)\s*$~', $this->numeroFiscalBruto(), $m) ? $m[1] : null;
    }

    /** Número na SÉRIE INTERNA gravada, ex.: "NC SOSNC/000001". */
    public function numeroInterno(): string
    {
        $serie = $this->series;

        return static::comporNumeroInterno(
            $serie?->prefix,
            $serie?->series_code,
            $this->numeroFiscalBruto()
        );
    }

    /**
     * A composição do número interno, num sítio só.
     *
     * Existe como método estático PURO porque há consumidores que não têm o
     * modelo à mão — o relatório do POS trabalha sobre linhas cruas de um
     * UNION. Repetir a fórmula lá seria outra implementação da mesma regra.
     */
    public static function comporNumeroInterno(?string $prefixo, ?string $serieInterna, ?string $numeroFiscal): string
    {
        $seq = preg_match('~/(\d+)\s*$~', (string) $numeroFiscal, $m) ? $m[1] : null;

        if (!$serieInterna || !$seq) {
            return (string) $numeroFiscal;
        }

        return trim(($prefixo ?: '') . ' ' . $serieInterna) . '/' . $seq;
    }

    /** Número na SÉRIE DA AGT; null quando a série ainda não está registada. */
    public function numeroAgt(): ?string
    {
        $serie = $this->series;
        $seq = $this->numeroSequencia();

        if (!$serie || empty($serie->agt_series_id) || !$seq) {
            return null;
        }

        return trim(($serie->prefix ?: '') . ' ' . $serie->agt_series_id) . '/' . $seq;
    }
}
