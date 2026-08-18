<?php

namespace Tests\Unit;

use App\Support\NifAngolano;
use PHPUnit\Framework\TestCase;

/**
 * Classificação de NIF de empresa angolana, sem expor o número.
 */
class NifAngolanoTest extends TestCase
{
    public function test_nif_de_empresa_valido(): void
    {
        $this->assertSame(NifAngolano::VALIDO, NifAngolano::classificar('5417123456')['estado']);
        $this->assertSame(NifAngolano::VALIDO, NifAngolano::classificar('541712345')['estado']); // nove dígitos
    }

    public function test_ausente(): void
    {
        $this->assertSame(NifAngolano::AUSENTE, NifAngolano::classificar(null)['estado']);
        $this->assertSame(NifAngolano::AUSENTE, NifAngolano::classificar('   ')['estado']);
    }

    public function test_bi_com_letras_e_invalido(): void
    {
        $r = NifAngolano::classificar('004512345LA041');
        $this->assertSame(NifAngolano::INVALIDO, $r['estado']);
        $this->assertStringContainsString('BI', $r['motivo']);
    }

    public function test_pessoa_singular_e_invalido_para_empresa(): void
    {
        $r = NifAngolano::classificar('2417123456');
        $this->assertSame(NifAngolano::INVALIDO, $r['estado']);
        $this->assertStringContainsString('singular', $r['motivo']);
    }

    public function test_consumidor_final_e_invalido_para_empresa(): void
    {
        $this->assertSame(NifAngolano::INVALIDO, NifAngolano::classificar('999999999')['estado']);
    }

    public function test_comprimento_errado(): void
    {
        $this->assertSame(NifAngolano::INVALIDO, NifAngolano::classificar('12345')['estado']);
    }

    public function test_o_numero_sai_sempre_mascarado(): void
    {
        $r = NifAngolano::classificar('5417123456');
        // valido nao devolve mascarado no fluxo? devolve sim:
        $this->assertSame('54******56', $r['mascarado']);

        // e nunca aparece o numero completo em lado nenhum do retorno
        $inv = NifAngolano::classificar('2417123456');
        $this->assertStringNotContainsString('2417123456', json_encode($inv));
    }

    public function test_tolera_pontuacao(): void
    {
        $this->assertSame(NifAngolano::VALIDO, NifAngolano::classificar('5417.123.456')['estado']);
    }
}
