<?php

namespace Tests\Unit\Invoicing;

use App\Helpers\InvoiceCalculationHelper;
use PHPUnit\Framework\TestCase;

/**
 * Uma linha sem taxa resolvida NAO e uma linha a 14%.
 *
 * O erro real: o ecra lia `attributes['tax_rate'] ?? 0` e mostrava "Isento"
 * em todas as linhas, enquanto o resumo lia `?? 14` e cobrava 14% sobre o
 * liquido. A fatura dizia isento e cobrava 15.283,62 sobre 109.168,68.
 */
class ImpostoNuncaInventadoTest extends TestCase
{
    /** Item de carrinho minimo, no formato que o helper consome. */
    private function linha(float $preco, int $qtd, array $atributos = []): object
    {
        return (object) [
            'price' => $preco,
            'quantity' => $qtd,
            'attributes' => $atributos,
        ];
    }

    public function test_linha_sem_taxa_nao_gera_imposto(): void
    {
        // Sem a chave 'tax_rate' - exactamente o caso da fatura de compra.
        $totais = InvoiceCalculationHelper::calculateTotals([
            $this->linha(1000, 1),
        ]);

        $this->assertSame(0.0, $totais['tax_amount'],
            'Uma linha sem taxa nao pode gerar imposto: antes inventava 14%.');
        $this->assertSame(1000.0, $totais['total']);
    }

    public function test_linha_isenta_nao_gera_imposto(): void
    {
        $totais = InvoiceCalculationHelper::calculateTotals([
            $this->linha(1000, 1, ['tax_rate' => 0]),
        ]);

        $this->assertSame(0.0, $totais['tax_amount']);
    }

    public function test_a_taxa_declarada_continua_a_ser_aplicada(): void
    {
        // A correccao nao pode desligar o IVA de quem o cobra.
        $totais = InvoiceCalculationHelper::calculateTotals([
            $this->linha(1000, 1, ['tax_rate' => 14]),
        ]);

        $this->assertSame(140.0, $totais['tax_amount']);
        $this->assertSame(1140.0, $totais['total']);
    }

    public function test_o_caso_da_farmacia_reproduz_se_a_zero(): void
    {
        // Cinco linhas isentas cujo liquido bate com o ecra reportado.
        $linhas = [
            $this->linha(21833.736, 1, ['tax_rate' => 0]),
            $this->linha(21833.736, 1, ['tax_rate' => 0]),
            $this->linha(21833.736, 1, ['tax_rate' => 0]),
            $this->linha(21833.736, 1, ['tax_rate' => 0]),
            $this->linha(21833.736, 1, ['tax_rate' => 0]),
        ];

        $totais = InvoiceCalculationHelper::calculateTotals($linhas);

        // Cada linha monetária arredonda primeiro a 2 casas: 21.833,74 × 5.
        // Somar frações invisíveis produzia totais que não batiam com as linhas.
        $this->assertSame(109168.70, $totais['incidencia_iva']);
        $this->assertSame(0.0, $totais['tax_amount'],
            'O resumo cobrava 15.283,62 (14%) num documento todo isento.');
        $this->assertSame(109168.70, $totais['total']);
    }
    /**
     * Os atributos chegam numa ItemAttributeCollection, não num array.
     *
     * O código lia-os com (array)$item->attributes, que devolve as
     * propriedades internas da colecção — 'tax_rate' ficava SEMPRE ausente
     * e o cálculo caía no valor por omissão. Com o antigo `?? 14` isso
     * cobrava 14% a toda a gente, inclusive a linhas isentas; trocar o
     * fallback para 0 só inverteu o erro. A correcção é ler mesmo.
     */
    public function test_a_taxa_e_lida_de_uma_coleccao_de_atributos(): void
    {
        $linha = (object) [
            'price' => 1000.0,
            'quantity' => 1,
            'attributes' => new \Darryldecode\Cart\ItemAttributeCollection([
                'tax_rate' => 14,
                'discount_percent' => 0,
            ]),
        ];

        $totais = InvoiceCalculationHelper::calculateTotals([$linha]);

        $this->assertSame(140.0, $totais['tax_amount'],
            'a taxa da linha nao foi lida da coleccao de atributos');
    }

    /** E uma coleccao com taxa 0 tem mesmo de dar imposto 0. */
    public function test_coleccao_isenta_nao_gera_imposto(): void
    {
        $linha = (object) [
            'price' => 1000.0,
            'quantity' => 1,
            'attributes' => new \Darryldecode\Cart\ItemAttributeCollection([
                'tax_rate' => 0,
                'discount_percent' => 0,
            ]),
        ];

        $totais = InvoiceCalculationHelper::calculateTotals([$linha]);

        $this->assertSame(0.0, $totais['tax_amount']);
    }

    /** O desconto por linha tambem vinha da coleccao e era ignorado. */
    public function test_o_desconto_da_linha_e_lido_da_coleccao(): void
    {
        $linha = (object) [
            'price' => 1000.0,
            'quantity' => 1,
            'attributes' => new \Darryldecode\Cart\ItemAttributeCollection([
                'tax_rate' => 14,
                'discount_percent' => 10,
            ]),
        ];

        $totais = InvoiceCalculationHelper::calculateTotals([$linha]);

        $this->assertSame(900.0, $totais['subtotal'], 'o desconto de linha nao foi lido');
        $this->assertSame(126.0, $totais['tax_amount']);
    }
}
