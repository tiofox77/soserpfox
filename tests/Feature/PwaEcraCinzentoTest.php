<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * A rede de seguranca do ecra cinzento.
 *
 * O start_url do PWA e a pagina do POS. Com Alpine, tudo estava atras de
 * x-cloak ate o Alpine arrancar — e numa instalacao acabada de fazer, com a
 * rede a falhar, ficava um ecra cinzento sem uma palavra que o explicasse.
 *
 * Com o PWA em React o risco muda de forma mas nao desaparece: se o pacote
 * nao chegar, o ecra de carregamento nunca sai. A casca (pwa/ecra.blade.php)
 * mostra-o DE IMEDIATO, sem depender de nada, explica a demora e, se o pacote
 * nao montar, troca-o por um aviso que diz o que fazer.
 */
class PwaEcraCinzentoTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // O POS do PWA exige a permissão que o menu usa para o mostrar (ver
        // App\Support\MenuDoPwa): sem ela, a rota responde 403 e o ensaio
        // falhava por falta de acesso, não por falta de rede de segurança.
        $this->comModulo('invoicing')->comPermissoes('invoicing.pos.access');
    }

    private function pos(): string
    {
        return $this->actingAs($this->user)->get('/invoicing/offline/pos')->assertOk()->getContent();
    }

    public function test_a_pagina_traz_a_rede_de_seguranca(): void
    {
        $html = $this->pos();

        $this->assertStringContainsString('pwa-aviso-arranque', $html);
        $this->assertStringContainsString('window.__pwaMontado', $html, 'o aviso só aparece se o pacote não montou');
    }

    /** O aviso diz o que fazer, e nao so que correu mal. */
    public function test_o_aviso_diz_o_que_fazer(): void
    {
        $html = $this->pos();

        $this->assertStringContainsString('Ligue-se à rede e recarregue', $html);
        $this->assertStringContainsString('Recarregar', $html);
    }

    /** Nada fica escondido à espera de uma biblioteca: o Alpine saiu, e o x-cloak com ele. */
    public function test_nao_ha_conteudo_escondido_a_espera_de_uma_biblioteca(): void
    {
        $html = $this->pos();

        $this->assertStringNotContainsString('x-cloak', $html);
        $this->assertStringNotContainsString('alpine', strtolower($html));
    }

    /** O ecra de carregamento aparece DE IMEDIATO, sem depender de nada. */
    public function test_ha_ecra_de_carregamento_desde_o_primeiro_instante(): void
    {
        $html = $this->pos();

        $this->assertStringContainsString('pwa-a-carregar', $html);
        $this->assertStringContainsString('A preparar o ponto de venda', $html);
    }

    /** E sai quando o pacote monta, que e quando ha algo por baixo para ver. */
    public function test_o_ecra_de_carregamento_sai_quando_o_pacote_monta(): void
    {
        $entrada = file_get_contents(resource_path('js/pwa.tsx'));

        $this->assertStringContainsString("getElementById('pwa-a-carregar')", $entrada);
        $this->assertStringContainsString('window.__pwaMontado = true', $entrada);
    }

    /** Passado algum tempo diz porque esta a demorar, em vez de so rodar. */
    public function test_ao_fim_de_algum_tempo_explica_a_demora(): void
    {
        $this->assertStringContainsString('A carregar pela primeira vez', $this->pos());
    }
}
