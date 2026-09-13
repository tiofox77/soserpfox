<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * O botão de suporte tem de se poder fechar.
 *
 * Fica por cima do canto inferior direito, que é onde as tabelas põem os
 * botões de acção. Quem estava a trabalhar numa lista tinha o último botão de
 * cada linha tapado e nada que pudesse fazer quanto a isso.
 *
 * Era um componente Blade com Alpine; é a peça `casca/suporte`, em React.
 */
class SuporteFechavelTest extends TenantTestCase
{
    private function peca(): string
    {
        return file_get_contents(resource_path('js/ecras/casca/Suporte.tsx'));
    }

    public function test_ha_forma_de_fechar_e_de_trazer_de_volta(): void
    {
        $this->assertStringContainsString('const esconder', $this->peca());
        // Esconder de vez deixava as pessoas sem suporte e sem saber porquê.
        $this->assertStringContainsString('const mostrar', $this->peca());
        $this->assertStringContainsString("t('Mostrar o suporte')", $this->peca());
    }

    public function test_a_escolha_fica_guardada_no_aparelho(): void
    {
        // Sem isto voltava a aparecer a cada página, e fechá-lo não valia nada.
        $this->assertStringContainsString("const CHAVE = 'suporte-escondido'", $this->peca());
        $this->assertStringContainsString('localStorage.setItem(CHAVE', $this->peca());
        $this->assertStringContainsString('localStorage.getItem(CHAVE', $this->peca());
    }

    public function test_o_layout_monta_a_peca_com_as_duas_moradas(): void
    {
        $html = $this->get('/home')->assertOk()->getContent();

        $this->assertSame(1, preg_match('/data-peca="casca\/suporte"\s+data-props="([^"]*)"/', $html, $m), 'o layout tem de montar o suporte');
        $props = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);

        $this->assertSame(route('support.tickets'), $props['tickets']);
        $this->assertSame(route('support.features'), $props['melhorias']);
        $this->assertStringNotContainsString('x-data', $html, 'o layout já não tem Alpine');
    }
}
