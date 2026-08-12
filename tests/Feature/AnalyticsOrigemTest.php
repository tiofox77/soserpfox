<?php

namespace Tests\Feature;

use App\Services\Analytics\Origem;
use App\Services\Analytics\Regiao;
use Tests\TestCase;

/**
 * De onde veio a visita.
 *
 * O ecrã classificava a origem com um CASE dentro do SQL e cinco domínios
 * escritos à mão. Tudo o resto caía em "other" — incluindo o tráfego da
 * PRÓPRIA aplicação, que nesta base é mais de metade dos referrers. Ou seja: a
 * medida que devia dizer o que traz gente de fora estava a contar gente de
 * dentro.
 */
class AnalyticsOrigemTest extends TestCase
{
    public function test_sem_referrer_e_directo(): void
    {
        $this->assertSame('directo', Origem::classificar(null)['canal']);
        $this->assertSame('directo', Origem::classificar('')['canal']);
    }

    public function test_motor_de_busca_e_organico(): void
    {
        foreach (['https://www.google.com/search?q=erp', 'https://bing.com/', 'https://duckduckgo.com/'] as $url) {
            $this->assertSame('orgânico', Origem::classificar($url)['canal'], $url);
        }
    }

    public function test_rede_social_e_reconhecida_pelo_nome(): void
    {
        // O referrer real desta base é `http://m.facebook.com` — com o `m.` da
        // versão móvel, que uma comparação exacta não apanharia.
        $r = Origem::classificar('http://m.facebook.com');

        $this->assertSame('social', $r['canal']);
        $this->assertSame('Facebook', $r['fonte']);
    }

    public function test_o_trafego_da_propria_casa_nao_conta_como_referencia(): void
    {
        // Mais de metade dos referrers desta base são páginas da aplicação:
        // gente que estava no POS e foi ao site. Contar isso como tráfego
        // vindo de fora inflaciona tudo o que interessa medir.
        $r = Origem::classificar('https://soserp.vip/invoicing/pos', null, 'soserp.vip');

        $this->assertSame('interno', $r['canal']);
    }

    public function test_um_site_desconhecido_e_referencia_com_o_dominio_a_vista(): void
    {
        // O CASE antigo mandava isto para "other" e perdia-se qual era o site.
        $r = Origem::classificar('https://jornaldeangola.ao/noticia/123');

        $this->assertSame('referência', $r['canal']);
        $this->assertStringContainsString('jornaldeangola', strtolower($r['fonte']));
    }

    public function test_uma_campanha_marcada_manda_em_tudo(): void
    {
        $r = Origem::classificar('https://www.google.com/', 'newsletter_marco');

        $this->assertSame('campanha', $r['canal']);
        $this->assertSame('newsletter_marco', $r['fonte']);
    }

    public function test_o_dominio_sai_sem_www_e_em_minusculas(): void
    {
        $this->assertSame('exemplo.ao', Origem::dominio('https://WWW.Exemplo.AO/pagina?x=1'));
    }

    public function test_um_referrer_sem_esquema_ainda_da_dominio(): void
    {
        // Alguns browsers mandam o referrer sem "https://".
        $this->assertSame('facebook.com', Origem::dominio('facebook.com/algo'));
    }

    public function test_lixo_no_referrer_nao_rebenta(): void
    {
        foreach (['', null, 'nao-e-um-url', '://'] as $entrada) {
            $r = Origem::classificar($entrada);
            $this->assertIsString($r['canal']);
        }
    }

    public function test_a_bandeira_sai_do_codigo_do_pais(): void
    {
        $this->assertSame('🇦🇴', Regiao::bandeira('AO'));
        $this->assertSame('🇵🇹', Regiao::bandeira('pt'));
    }

    public function test_um_codigo_de_pais_invalido_da_um_simbolo_neutro(): void
    {
        foreach ([null, '', 'XYZ', '1', 'LO'] as $codigo) {
            $this->assertSame('🌐', Regiao::bandeira($codigo), var_export($codigo, true));
        }
    }

    public function test_o_pais_tem_nome_em_portugues(): void
    {
        $this->assertSame('Angola', Regiao::nomeDoPais('AO'));
        $this->assertSame('Moçambique', Regiao::nomeDoPais('MZ'));
        $this->assertSame('Rede local', Regiao::nomeDoPais('LO'));
    }
}
