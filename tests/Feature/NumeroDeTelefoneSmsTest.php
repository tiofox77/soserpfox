<?php

namespace Tests\Feature;

use App\Services\SmsService;
use Tests\TenantTestCase;

/**
 * O numero de telefone com que o SMS sai.
 *
 * Isto limitava-se a tirar os caracteres estranhos e a por um '+' a frente. O
 * que saia era o que estava gravado: uns com indicativo e outros sem —
 * '+938023744' ao lado de '+244944111781' no mesmo envio, e ate '+83635622',
 * que nao e numero nenhum. Um numero sem indicativo nao chega ao destino, e o
 * envio e cobrado na mesma.
 */
class NumeroDeTelefoneSmsTest extends TenantTestCase
{
    private function formatar($numero): ?string
    {
        return (new SmsService())->formatPhoneNumber($numero);
    }

    /** O caso que motivou tudo: nove digitos sem indicativo. */
    public function test_um_numero_nacional_ganha_o_indicativo(): void
    {
        $this->assertSame('+244938023744', $this->formatar('938023744'));
        $this->assertSame('+244942705533', $this->formatar('942705533'));
    }

    /** Fixos tambem: comecam por 2 e tem os mesmos nove digitos. */
    public function test_um_fixo_tambem_ganha_o_indicativo(): void
    {
        $this->assertSame('+244222334455', $this->formatar('222334455'));
    }

    /** Com indicativo ja la, fica como esta — nao se poe 244 duas vezes. */
    public function test_com_indicativo_nao_se_repete(): void
    {
        $this->assertSame('+244944111781', $this->formatar('+244944111781'));
        $this->assertSame('+244944111781', $this->formatar('244944111781'));
        $this->assertSame('+244944111781', $this->formatar('00244944111781'));
    }

    /** Escrito como as pessoas escrevem. */
    public function test_aceita_espacos_tracos_e_parenteses(): void
    {
        $this->assertSame('+244923718903', $this->formatar('923 718 903'));
        $this->assertSame('+244923718903', $this->formatar('+244 923-718-903'));
        $this->assertSame('+244923718903', $this->formatar('(244) 923 718 903'));
    }

    /** O que nao e numero nenhum devolve null, e nao um palpite. */
    public function test_um_numero_impossivel_e_recusado(): void
    {
        $this->assertNull($this->formatar('83635622'), 'oito digitos nao e numero angolano');
        $this->assertNull($this->formatar('12345'));
        $this->assertNull($this->formatar(''));
        $this->assertNull($this->formatar(null));
        $this->assertNull($this->formatar('24499988'), 'com 244 mas curto de mais');
    }

    /** E o envio recusa-se em vez de pagar por um SMS que nao chega. */
    public function test_o_envio_recusa_um_numero_invalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SmsService())->send('83635622', 'Mensagem de teste');
    }
}
