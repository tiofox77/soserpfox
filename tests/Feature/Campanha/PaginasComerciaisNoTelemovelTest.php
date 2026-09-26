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

        // O logótipo com altura responsiva, e não os 80px fixos que o botão tapava.
        $this->assertStringNotContainsString('style="height: 80px; max-height: 80px;"', $html);
        $this->assertStringContainsString('h-12 sm:h-16 lg:h-20', $html);

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
    }
}
