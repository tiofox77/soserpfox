<?php

namespace Tests\Unit;

use App\Support\TextoEstragado;
use PHPUnit\Framework\TestCase;

/** Os acentos que uma importação leu na página de código da consola (CP437). */
class TextoEstragadoTest extends TestCase
{
    /** @dataProvider casos */
    public function test_repara_so_o_que_esta_estragado(string $gravado, string $esperado): void
    {
        $this->assertSame($esperado, TextoEstragado::reparar($gravado));
    }

    public static function casos(): array
    {
        return [
            'maiúscula acentuada' => ['├üLCOOL', 'ÁLCOOL'],
            'metade estragada, metade certa' => ['SOLU├ºÃO VAGINAL', 'SOLUçÃO VAGINAL'],
            'til e cedilha' => ['Pomada cicatriza├º├úo', 'Pomada cicatrização'],
            'o ordinal' => ['Álcool 70┬║', 'Álcool 70º'],
            'texto certo fica igual' => ['Xarope para a tosse', 'Xarope para a tosse'],
            'acentos certos ficam iguais' => ['Pasta de dentes Colgate Máxima Proteção', 'Pasta de dentes Colgate Máxima Proteção'],
            'um traço de caixa sozinho não é estrago' => ['A ├ B', 'A ├ B'],
        ];
    }

    public function test_nulo_fica_nulo(): void
    {
        $this->assertNull(TextoEstragado::reparar(null));
        $this->assertFalse(TextoEstragado::estaEstragado('ÁLCOOL'));
        $this->assertTrue(TextoEstragado::estaEstragado('├üLCOOL'));
    }
}
