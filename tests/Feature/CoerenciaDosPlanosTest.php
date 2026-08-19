<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Plan;
use Tests\TenantTestCase;

/**
 * A escada dos planos tem de ser coerente: quem paga mais recebe, no
 * mínimo, tudo o que recebe quem paga menos.
 *
 * O que se passava: o Pacote Salão (7.900 Kz) dava o módulo do salão que o
 * Enterprise (89.900 Kz) não dava, e o Professional prometia Contabilidade
 * na descrição sem a incluir.
 */
class CoerenciaDosPlanosTest extends TenantTestCase
{
    private const ESCADA = ['starter', 'professional', 'business', 'enterprise'];

    protected function setUp(): void
    {
        parent::setUp();

        // A base de testes é uma cópia do esquema, sem catálogo semeado.
        // Monta-se aqui o mínimo: os módulos e a escada.
        foreach (['invoicing', 'treasury', 'rh', 'inventario', 'contabilidade',
                  'salon', 'hotel', 'restaurant', 'eventos'] as $slug) {
            Module::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'is_core' => false]);
        }

        $degraus = [
            'starter'      => ['invoicing', 'treasury'],
            'professional' => ['invoicing', 'treasury', 'rh', 'inventario'],
            'business'     => ['invoicing', 'treasury', 'rh', 'inventario', 'contabilidade'],
            'enterprise'   => ['invoicing', 'treasury', 'rh', 'inventario', 'contabilidade'],
        ];

        foreach ($degraus as $slug => $mods) {
            $plano = Plan::firstOrCreate(['slug' => $slug], [
                'name' => ucfirst($slug), 'price_monthly' => 1000, 'is_active' => true,
            ]);
            $plano->modules()->sync(Module::whereIn('slug', $mods)->pluck('id')->all());
        }

        // Um pacote vertical que dá um módulo que os degraus de cima não têm —
        // exactamente o caso que o utilizador viu.
        $pacote = Plan::firstOrCreate(['slug' => 'pacote-salao'], [
            'name' => 'Pacote Salão', 'price_monthly' => 7900, 'is_active' => true,
        ]);
        $pacote->modules()->sync(Module::whereIn('slug', ['invoicing', 'treasury', 'salon'])->pluck('id')->all());
    }

    private function modulos(string $slug): array
    {
        $p = Plan::where('slug', $slug)->first();

        return $p ? $p->modules()->pluck('modules.slug')->sort()->values()->all() : [];
    }

    public function test_antes_da_correccao_a_escada_esta_partida(): void
    {
        // O pacote de 7.900 dá 'salon'; o enterprise não.
        $this->assertContains('salon', $this->modulos('pacote-salao'));
        $this->assertNotContains('salon', $this->modulos('enterprise'));
    }

    public function test_cada_degrau_inclui_tudo_o_do_degrau_abaixo(): void
    {
        $this->artisan('planos:coerencia')->assertSuccessful();

        $anterior = null;
        foreach (self::ESCADA as $slug) {
            $mods = $this->modulos($slug);

            if ($anterior !== null) {
                $this->assertEmpty(
                    array_diff($anterior, $mods),
                    "o plano {$slug} não inclui tudo o que o degrau abaixo inclui"
                );
            }

            $anterior = $mods;
        }
    }

    public function test_o_enterprise_tem_mesmo_todos_os_modulos(): void
    {
        $this->artisan('planos:coerencia')->assertSuccessful();

        $this->assertSame(
            Module::count(),
            Plan::where('slug', 'enterprise')->first()->modules()->count(),
            'o Enterprise anuncia todos os módulos — tem de os ter'
        );
    }

    /**
     * Business e Enterprise não podem dar o mesmo.
     *
     * Custam 44.900 e 89.900 Kz. Se derem os mesmos módulos, quem paga o
     * dobro recebe utilizadores e mais nada — e o degrau de cima deixa de ser
     * um degrau.
     */
    public function test_o_enterprise_da_mais_modulos_que_o_business(): void
    {
        $this->artisan('planos:coerencia')->assertSuccessful();

        $business = $this->modulos('business');
        $enterprise = $this->modulos('enterprise');

        $this->assertNotEmpty(
            array_diff($enterprise, $business),
            'quem paga o dobro tem de receber módulos a mais'
        );
    }

    /** Os módulos de sector são o que o Business não leva. */
    public function test_o_business_nao_leva_os_modulos_de_sector(): void
    {
        $this->artisan('planos:coerencia')->assertSuccessful();

        $business = $this->modulos('business');

        foreach (['hotel', 'restaurant', 'salon', 'eventos'] as $sector) {
            $this->assertNotContains($sector, $business,
                "o módulo de sector '{$sector}' é o que distingue o Enterprise");
            $this->assertContains($sector, $this->modulos('enterprise'));
        }
    }

    /** Mas leva todos os horizontais — é isso que justifica o preço dele. */
    public function test_o_business_leva_todos_os_modulos_de_gestao(): void
    {
        $this->artisan('planos:coerencia')->assertSuccessful();

        $business = $this->modulos('business');

        foreach (['invoicing', 'treasury', 'rh', 'inventario', 'contabilidade'] as $slug) {
            $this->assertContains($slug, $business);
        }
    }

    public function test_o_professional_inclui_a_contabilidade_que_promete(): void
    {
        $this->artisan('planos:coerencia')->assertSuccessful();

        $this->assertContains('contabilidade', $this->modulos('professional'));
    }

    public function test_nenhum_pacote_vertical_da_mais_que_o_enterprise(): void
    {
        $this->artisan('planos:coerencia')->assertSuccessful();

        $enterprise = $this->modulos('enterprise');

        foreach (Plan::where('slug', 'like', 'pacote-%')->get() as $pacote) {
            $emFalta = array_diff($pacote->modules()->pluck('modules.slug')->all(), $enterprise);

            $this->assertEmpty(
                $emFalta,
                "{$pacote->name} dá módulos que o Enterprise não dá: " . implode(', ', $emFalta)
            );
        }
    }

    public function test_so_ver_nao_grava(): void
    {
        $antes = Plan::where('slug', 'enterprise')->first()->modules()->count();

        $this->artisan('planos:coerencia', ['--so-ver' => true])->assertSuccessful();

        $this->assertSame($antes, Plan::where('slug', 'enterprise')->first()->modules()->count());
    }
}
