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

    public function test_o_bi_bem_escrito_e_nif_de_pessoa_singular_valido(): void
    {
        $this->assertSame(NifAngolano::VALIDO, NifAngolano::classificar('004512345LA041')['estado']);
        $this->assertTrue(NifAngolano::formatoDeBI('006378572BA048'));

        $r = NifAngolano::classificar('0063785572BA048');
        $this->assertSame(NifAngolano::INVALIDO, $r['estado'], 'com letras mas um dígito a mais');
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

    /**
     * O classificar devolve as duas formas.
     *
     * `nif` é o número por inteiro — é o que a API do agente usa, desde que
     * a máscara foi retirada a pedido de quem gere a plataforma. `mascarado`
     * continua a existir para quem o queira pôr num ecrã público.
     */
    public function test_devolve_o_numero_inteiro_e_a_forma_mascarada(): void
    {
        $r = NifAngolano::classificar('5417123456');

        $this->assertSame('5417123456', $r['nif']);
        $this->assertSame('54******56', $r['mascarado']);
    }

    public function test_o_numero_vem_limpo_de_pontuacao(): void
    {
        $this->assertSame('5417123456', NifAngolano::classificar('5417.123.456')['nif']);
    }

    public function test_sem_nif_nao_ha_numero(): void
    {
        $r = NifAngolano::classificar('');

        $this->assertNull($r['nif']);
        $this->assertSame(NifAngolano::AUSENTE, $r['estado']);
    }

    public function test_tolera_pontuacao(): void
    {
        $this->assertSame(NifAngolano::VALIDO, NifAngolano::classificar('5417.123.456')['estado']);
    }
}
