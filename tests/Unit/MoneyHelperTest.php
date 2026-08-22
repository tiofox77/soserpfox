<?php

namespace Tests\Unit;

use App\Helpers\MoneyHelper;
use Tests\TestCase;

/**
 * O parse do dinheiro é crítico: um preço mal lido é dinheiro a mais ou a menos
 * num documento. Tem de aceitar qualquer combinação de separadores e devolver
 * sempre o número certo — mesmo que o JS da máscara não tenha corrido.
 */
class MoneyHelperTest extends TestCase
{
    /** @dataProvider casosParse */
    public function test_parse_le_qualquer_formato($entrada, float $esperado): void
    {
        $this->assertSame($esperado, MoneyHelper::parse($entrada), "parse de: " . var_export($entrada, true));
    }

    public static function casosParse(): array
    {
        return [
            'angola completo'      => ['10.000,23', 10000.23],
            'angola milhoes'       => ['1.234.567,89', 1234567.89],
            'só vírgula'           => ['10000,23', 10000.23],
            'só ponto'             => ['10000.23', 10000.23],
            'inteiro'              => ['10000', 10000.0],
            'internacional'        => ['1,234,567.89', 1234567.89],
            'frança com espaço'    => ['10 000,50', 10000.50],
            'com moeda'            => ['Kz 1.500,00', 1500.0],
            'suíça apóstrofo'      => ["20'000.00", 20000.0],
            'vazio'                => ['', 0.0],
            'nulo'                 => [null, 0.0],
            'float passthrough'    => [1234.56, 1234.56],
            'int passthrough'      => [5000, 5000.0],
            'negativo'             => ['-50,5', -50.5],
            'zero decimal'         => ['0,00', 0.0],
            'só decimais'          => [',5', 0.5],
        ];
    }

    public function test_separadores_por_formato(): void
    {
        $this->assertSame(['.', ','], MoneyHelper::separadores('angola'));
        $this->assertSame(['.', ','], MoneyHelper::separadores('portugal'));
        $this->assertSame([',', '.'], MoneyHelper::separadores('international'));
        $this->assertSame([' ', ','], MoneyHelper::separadores('france'));
        $this->assertSame(["'", '.'], MoneyHelper::separadores('switzerland'));
        $this->assertSame(['.', ','], MoneyHelper::separadores(null));
    }

    public function test_format_usa_a_config(): void
    {
        $ao = ['on' => true, 'milhar' => '.', 'decimal' => ',', 'casas' => 2];
        $this->assertSame('10.000,23', MoneyHelper::format(10000.23, $ao));
        $this->assertSame('1.234.567,89', MoneyHelper::format(1234567.89, $ao));

        $intl = ['on' => true, 'milhar' => ',', 'decimal' => '.', 'casas' => 2];
        $this->assertSame('10,000.23', MoneyHelper::format(10000.23, $intl));

        $zero = ['on' => true, 'milhar' => '.', 'decimal' => ',', 'casas' => 0];
        $this->assertSame('10.000', MoneyHelper::format(10000.23, $zero));
    }

    public function test_ida_e_volta_nao_perde_valor(): void
    {
        $cfg = ['on' => true, 'milhar' => '.', 'decimal' => ',', 'casas' => 2];
        foreach ([0.0, 1.5, 10000.23, 1234567.89, 999999.99] as $v) {
            $this->assertSame($v, MoneyHelper::parse(MoneyHelper::format($v, $cfg)), "ida-e-volta de $v");
        }
    }
}
