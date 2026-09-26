<?php

namespace Tests\Feature\Campanha;

use App\Models\Plan;
use App\Support\DiasDeTeste;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * AS PÁGINAS DOS ANÚNCIOS, NO TELEMÓVEL (26/09/2026).
 *
 * O botão tapava o logótipo, o Restaurante não estava na navegação dos
 * módulos, a demonstração do produto só aparecia no computador e a frase
 * «14 dias grátis» contradizia o Hotel, que dá 30.
 */
class PaginasComerciaisNoTelemovelTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DiasDeTeste::esquecer();
    }

    public function test_a_frase_dos_dias_sai_dos_planos_e_nomeia_as_excepcoes(): void
    {
        Plan::query()->update(['is_public' => false]);
        foreach ([['📊 Pacote Vendas', 14], ['👥 Pacote RH', 14], ['🏨 Pacote Hotel', 30]] as $i => [$nome, $dias]) {
            Plan::create([
                'name' => $nome, 'slug' => 'ensaio-' . $i . '-' . uniqid(), 'price_monthly' => 5000 + $i, 'trial_days' => $dias,
                'is_active' => true, 'is_public' => true, 'max_users' => 3,
            ]);
        }
        // O gratuito não é um teste: não entra na frase.
        Plan::create(['name' => 'Amigo', 'slug' => 'amigo-' . uniqid(), 'price_monthly' => 0, 'trial_days' => 90, 'is_active' => true, 'is_public' => true, 'max_users' => 3]);

        $this->assertSame('14 dias grátis (30 no Hotel)', DiasDeTeste::frase());
    }

    public function test_a_pagina_do_modulo_no_telemovel(): void
    {
        $html = $this->get('/modulos/vendas')->assertOk()->getContent();

        // O logótipo com altura por ecrã, e não os 80px fixos que o botão tapava.
        // Em CSS na própria página: classes do Tailwind que não estejam no
        // publico.css compilado não fazem nada em produção.
        $this->assertStringNotContainsString('style="height: 80px; max-height: 80px;"', $html);
        $this->assertStringContainsString('class="sos-logo-modulos object-contain"', $html);
        $this->assertStringContainsString('.sos-logo-modulos { height: 2.75rem; }', $html);

        // No telemóvel o «Começar Grátis» sai do topo para a barra de baixo,
        // com o WhatsApp ao lado — sem tapar o logótipo.
        $this->assertBarraDeBaixo($html, '244939729902');

        // O Restaurante na navegação dos módulos.
        $this->assertStringContainsString('href="/modulos/restaurant"', $html);

        // A demonstração do produto também no telemóvel (já não fica escondida).
        $this->assertStringNotContainsString('<div class="hidden md:block">', $html);
        $this->assertStringContainsString('app.soserp.vip/vendas', $html);
    }

    public function test_a_pagina_inicial_nao_promete_14_dias_a_todos(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(DiasDeTeste::frase(), $html);
        $this->assertStringNotContainsString('✨ 14 dias grátis •', $html);

        // O mesmo topo apertado, a mesma barra de baixo; o WhatsApp é o
        // contacto da plataforma, das definições.
        $this->assertBarraDeBaixo($html, preg_replace('/\D/', '', \App\Support\Entrada::contacto()['telefone']));
    }

    private function assertBarraDeBaixo(string $html, string $whatsapp): void
    {
        $this->assertStringContainsString('class="sos-cta-topo ', $html, 'o botão do topo esconde-se no telemóvel');
        $this->assertStringContainsString('.sos-cta-topo { display: none !important; }', $html);
        $this->assertMatchesRegularExpression('~<div class="sos-cta-fundo">\s*<a href="[^"]*/register" class="sos-cta-principal">~', $html);
        $this->assertStringContainsString('href="https://wa.me/' . $whatsapp . '"', $html);
    }
}
