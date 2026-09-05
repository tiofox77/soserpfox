<?php

namespace Tests\Unit;

use App\Rules\ValidateNIF;
use PHPUnit\Framework\TestCase;

class ValidateNIFTest extends TestCase
{
    public function test_pessoa_fisica_aceita_bi_angolano(): void
    {
        $rule = new ValidateNIF('pessoa_fisica');

        $this->assertTrue($rule->passes('nif', '025824504LA054'));
        $this->assertTrue($rule->passes('nif', '025824504la054'));
        $this->assertTrue($rule->passes('nif', '025824504 LA 054'));
    }

    public function test_bi_nao_e_aceite_sem_contexto_de_pessoa_fisica(): void
    {
        $this->assertFalse((new ValidateNIF())->passes('nif', '025824504LA054'));
        $this->assertFalse((new ValidateNIF('pessoa_juridica'))->passes('nif', '025824504LA054'));
    }

    public function test_pessoa_fisica_continua_a_aceitar_nif_numerico(): void
    {
        $this->assertTrue((new ValidateNIF('pessoa_fisica'))->passes('nif', '2417123456'));
    }

    public function test_normaliza_bi_para_formato_canonico(): void
    {
        $this->assertSame('025824504LA054', ValidateNIF::normalize(' 025824504-la-054 '));
    }
}
