<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * A rede de seguranca do ecra cinzento.
 *
 * O start_url do PWA e a pagina do POS, que tem vinte e dois x-cloak. O
 * x-cloak e `display: none !important` ate o Alpine arrancar, e o Alpine vem
 * de um CDN. Numa instalacao acabada de fazer, com a rede a falhar, ele nao
 * chega: fica tudo escondido e o utilizador ve um ecra cinzento sem uma
 * palavra que o explique.
 */
class PwaEcraCinzentoTest extends TenantTestCase
{
    private function pos(): string
    {
        return $this->actingAs($this->user)->get('/invoicing/offline/pos')->assertOk()->getContent();
    }

    public function test_a_pagina_traz_a_rede_de_seguranca(): void
    {
        $html = $this->pos();

        $this->assertStringContainsString('alpine-falhou', $html);
        $this->assertStringContainsString('pwa-aviso-arranque', $html);
    }

    /** O aviso diz o que fazer, e nao so que correu mal. */
    public function test_o_aviso_diz_o_que_fazer(): void
    {
        $html = $this->pos();

        $this->assertStringContainsString('Ligue-se à rede e recarregue', $html);
        $this->assertStringContainsString('Recarregar', $html);
    }

    /** A regra que desfaz o x-cloak tem de existir, senao revelar nao revela nada. */
    public function test_ha_regra_css_que_desfaz_o_x_cloak(): void
    {
        $html = $this->pos();

        $this->assertStringContainsString('html.alpine-falhou [x-cloak]', $html);
        $this->assertStringContainsString('display: revert !important', $html);
    }

    /** E o x-cloak continua a esconder no caso normal. */
    public function test_o_x_cloak_continua_a_esconder_quando_tudo_corre_bem(): void
    {
        $html = $this->pos();

        $this->assertStringContainsString('[x-cloak] { display: none !important; }', $html);
    }

    /** O ecra de carregamento aparece DE IMEDIATO, sem depender de nada. */
    public function test_ha_ecra_de_carregamento_desde_o_primeiro_instante(): void
    {
        $html = $this->pos();

        $this->assertStringContainsString("pwa-a-carregar", $html);
        $this->assertStringContainsString("A preparar o ponto de venda", $html);
    }

    /** E sai quando o Alpine arranca, que e quando ha algo por baixo para ver. */
    public function test_o_ecra_de_carregamento_sai_quando_o_alpine_arranca(): void
    {
        $this->assertStringContainsString("alpine:initialized", $this->pos());
    }

    /** Passado algum tempo diz porque esta a demorar, em vez de so rodar. */
    public function test_ao_fim_de_algum_tempo_explica_a_demora(): void
    {
        $this->assertStringContainsString("A carregar pela primeira vez", $this->pos());
    }
}
