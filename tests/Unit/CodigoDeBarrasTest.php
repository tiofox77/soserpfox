<?php

namespace Tests\Unit;

use App\Support\CodigoDeBarras;
use PHPUnit\Framework\TestCase;

class CodigoDeBarrasTest extends TestCase
{
    public function test_o_lido_vem_sempre_primeiro(): void
    {
        $this->assertSame('8902292003269', CodigoDeBarras::formas('8902292003269')[0]);
        $this->assertSame('0108902292003269', CodigoDeBarras::formas('0108902292003269')[0]);
    }

    public function test_do_envelope_gs1_chega_se_ao_ean13(): void
    {
        $f = CodigoDeBarras::formas('0108902292003269');

        $this->assertContains('08902292003269', $f, 'o GTIN-14 de dentro');
        $this->assertContains('8902292003269', $f, 'o EAN-13 que o leitor normal manda');
    }

    public function test_do_ean13_chega_se_ao_envelope(): void
    {
        $f = CodigoDeBarras::formas('8902292003269');

        // O caminho inverso: o leitor manda o EAN-13 mas o catálogo veio de
        // um sistema que guardava a linha completa.
        $this->assertContains('0108902292003269', $f);
        $this->assertContains('08902292003269', $f);
    }

    public function test_os_glifos_do_leitor_nao_contam(): void
    {
        $this->assertContains('8902292003269', CodigoDeBarras::formas(')01=08902292003269'));
    }

    public function test_gs1_com_validade_atras_ainda_da_o_produto(): void
    {
        // AI(01) + GTIN-14 + o princípio de um AI(17), como sai truncado
        // nalgumas listagens.
        $this->assertContains('3400935955838', CodigoDeBarras::formas('0103400935955838172'));
    }

    public function test_um_codigo_interno_fica_como_esta(): void
    {
        // Os códigos internos da farmácia não são EAN e não se lhes inventa
        // envelope nenhum — os zeros à frente são deles.
        $this->assertSame(['0000548'], CodigoDeBarras::formas('0000548'));
        $this->assertSame(['000456'], CodigoDeBarras::formas('000456'));
    }

    public function test_nunca_devolve_repetidos_nem_vazios(): void
    {
        foreach (['8902292003269', '0108902292003269', '', '   ', 'ABC'] as $lido) {
            $f = CodigoDeBarras::formas($lido);
            $this->assertSame(array_values(array_unique($f)), $f);
            $this->assertNotContains('', $f);
        }
    }
}
