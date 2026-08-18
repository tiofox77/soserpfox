<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * O botão de suporte tem de se poder fechar.
 *
 * Fica por cima do canto inferior direito, que é onde as tabelas põem os
 * botões de acção. Quem estava a trabalhar numa lista tinha o último botão de
 * cada linha tapado e nada que pudesse fazer quanto a isso.
 */
class SuporteFechavelTest extends TestCase
{
    private function componente(): string
    {
        return file_get_contents(resource_path('views/components/support-button.blade.php'));
    }

    public function test_ha_forma_de_fechar(): void
    {
        $this->assertStringContainsString('esconder()', $this->componente());
    }

    public function test_ha_forma_de_trazer_de_volta(): void
    {
        // Esconder de vez deixava as pessoas sem suporte e sem saber porquê.
        $this->assertStringContainsString('mostrar()', $this->componente());
    }

    public function test_a_escolha_fica_guardada_no_aparelho(): void
    {
        // Sem isto voltava a aparecer a cada página, e fechá-lo não valia nada.
        $this->assertStringContainsString("localStorage.setItem('suporte-escondido'", $this->componente());
        $this->assertStringContainsString("localStorage.getItem('suporte-escondido'", $this->componente());
    }

    public function test_o_componente_compila(): void
    {
        $html = view('components.support-button')->render();

        $this->assertStringContainsString('suporte-escondido', $html);
        $this->assertStringContainsString('Centro de Suporte', $html);
    }
}
