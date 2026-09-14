<?php

namespace Tests\Unit;

use App\Support\AcentosEstragados;
use PHPUnit\Framework\TestCase;

class AcentosEstragadosTest extends TestCase
{
    /** Os nomes da lista de produtos da farmácia, tal como estão na base. @test */
    public function repara_os_nomes_estragados_pela_pagina_do_dos(): void
    {
        $casos = [
            '├ücido F├│lico Afopic teuto comprimido' => 'Ácido Fólico Afopic teuto comprimido',
            '├üCIDO F├ôLICO 5MG' => 'ÁCIDO FÓLICO 5MG',
            '├ügua Oxigenada 20V 250ml Velvet' => 'Água Oxigenada 20V 250ml Velvet',
            '├üLCOOL ET├ìLICO 70%' => 'ÁLCOOL ETÍLICO 70%',
            'DYALCIP CIPROFLOXACINA INFUS├âO 200MG/100ML' => 'DYALCIP CIPROFLOXACINA INFUSÃO 200MG/100ML',
            'Solu├º├úo oral' => 'Solução oral',
            'Pomada anti-inflamat├│ria' => 'Pomada anti-inflamatória',
            'Nº 5 ┬║' => 'Nº 5 º',
        ];

        foreach ($casos as $estragado => $certo) {
            $this->assertSame($certo, AcentosEstragados::reparar($estragado), $estragado);
        }
    }

    /** O CP850 põe «õ» e «é» noutros caracteres do que o CP437. @test */
    public function repara_tambem_a_pagina_850_e_o_latin1(): void
    {
        $this->assertSame('Emulsões', AcentosEstragados::reparar('Emuls├Áes'));
        $this->assertSame('Emulsões', AcentosEstragados::reparar('Emuls├╡es'));
        $this->assertSame('Café', AcentosEstragados::reparar('Caf├⌐'));
        $this->assertSame('Â fica', AcentosEstragados::reparar('Ã‚ fica'));
        $this->assertSame('Café com leite – açúcar', AcentosEstragados::reparar('CafÃ© com leite â€“ aÃ§Ãºcar'));
    }

    /** O que já está certo não se mexe — nem as letras certas ao lado das estragadas. @test */
    public function o_que_esta_certo_fica_igual(): void
    {
        foreach (['Ácido Fólico', 'ÂNCORA MARÍTIMA', 'Pão de Ló', 'Õ Ô Ã Â', 'Diclofenac 10mg/g', '1 MILLION paco rabanne', 'ÔNIBUS'] as $certo) {
            $this->assertSame($certo, AcentosEstragados::reparar($certo), $certo);
        }

        $this->assertSame('Água já certa e Ácido', AcentosEstragados::reparar('Água já certa e ├ücido'));
        $this->assertNull(AcentosEstragados::reparar(null));
    }

    /** Estragado duas vezes também volta. @test */
    public function estragado_duas_vezes_tambem_volta(): void
    {
        // «Á» → «├ü» (CP437) → e esse UTF-8 lido como CP1252.
        $duasVezes = mb_convert_encoding('├ü', 'UTF-8', 'Windows-1252');
        $this->assertSame('Água', AcentosEstragados::reparar($duasVezes . 'gua'));
    }

    /** O que não se sabe reparar fica marcado para alguém olhar. @test */
    public function o_que_sobra_fica_marcado(): void
    {
        $this->assertTrue(AcentosEstragados::precisaDeOlhos('ÁLCOOL ├ 96'));
        $this->assertTrue(AcentosEstragados::precisaDeOlhos("Nome com \u{FFFD}"));
        $this->assertFalse(AcentosEstragados::precisaDeOlhos('ÁLCOOL ETÍLICO 96%'));
    }
}
