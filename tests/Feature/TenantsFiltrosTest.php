<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * Os filtros da lista de empresas.
 *
 * A lista serve para responder a perguntas — "quem está adormecido?", "quem
 * está no Business?", "quem desactivámos?" — e sem filtros a única resposta
 * possível era ler as páginas todas à mão.
 *
 * O ecrã passou a React e os filtros vão para `/api/v1/plataforma/react/empresas`.
 * O que era estado do componente (o cartão aceso, voltar à primeira página ao
 * mudar um filtro) vive no browser e prova-se no ensaio `react.plataforma`.
 */
class TenantsFiltrosTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->user->update(['is_super_admin' => true]);
        $this->actingAs($this->user->fresh());
    }

    private function empresa(array $extra = []): Tenant
    {
        return Tenant::create(array_merge([
            'name'  => 'Empresa ' . uniqid(),
            'slug'  => 'emp-' . uniqid(),
            'nif'   => (string) random_int(500000000, 599999999),
            'email' => 'e' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ], $extra));
    }

    private function comFactura(Tenant $t): void
    {
        DB::table('invoicing_sales_invoices')->insert([
            'tenant_id'      => $t->id,
            'invoice_number' => 'FT-' . uniqid(),
            'client_id'      => $this->cliente->id,
            'invoice_date'   => now(),
            'created_by'     => $this->user->id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    /** Os nomes das empresas da resposta, por ordem. */
    private function nomes(array $filtros): array
    {
        return array_column(
            $this->getJson('/api/v1/plataforma/react/empresas?' . http_build_query($filtros + ['por_pagina' => '50']))
                ->assertOk()->json('empresas'),
            'nome'
        );
    }

    public function test_filtrar_por_estado_mostra_so_esse_estado(): void
    {
        $aFacturar = $this->empresa(['name' => 'Facturadora Lda']);
        $this->empresa(['name' => 'Fantasma Lda']);
        $this->comFactura($aFacturar);

        $nomes = $this->nomes(['estado' => 'activa']);

        $this->assertContains('Facturadora Lda', $nomes);
        $this->assertNotContains('Fantasma Lda', $nomes);
    }

    /** Um estado que não existe não devolve a lista toda, como se não houvesse filtro. */
    public function test_um_estado_inventado_e_recusado(): void
    {
        $this->getJson('/api/v1/plataforma/react/empresas?estado=inventado')
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado');
    }

    public function test_filtrar_por_desactivadas(): void
    {
        $this->empresa(['name' => 'Desligada Lda', 'is_active' => false]);
        $this->empresa(['name' => 'Ligada Lda']);

        $nomes = $this->nomes(['activa' => '0']);

        $this->assertContains('Desligada Lda', $nomes);
        $this->assertNotContains('Ligada Lda', $nomes);
    }

    public function test_filtrar_por_plano_segue_a_subscricao_em_vigor(): void
    {
        $plano = Plan::create([
            'name' => 'Filtro', 'slug' => 'filtro-' . uniqid(), 'description' => 'x',
            'price_monthly' => 1000, 'price_yearly' => 10000, 'trial_days' => 0,
            'max_users' => 5, 'max_companies' => 1, 'is_active' => true, 'order' => 9,
        ]);

        $dentro = $this->empresa(['name' => 'No Plano Lda']);
        $this->empresa(['name' => 'Fora do Plano Lda']);

        Subscription::create([
            'tenant_id' => $dentro->id, 'plan_id' => $plano->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'amount' => 1000,
            'current_period_end' => now()->addMonth(),
        ]);

        $nomes = $this->nomes(['plano' => (string) $plano->id]);

        $this->assertContains('No Plano Lda', $nomes);
        $this->assertNotContains('Fora do Plano Lda', $nomes);
    }

    /** Ordenar por facturas põe quem factura no topo. */
    public function test_ordenar_por_facturas(): void
    {
        $comMovimento = $this->empresa(['name' => 'Movimentada Lda']);
        $this->comFactura($comMovimento);
        $this->empresa(['name' => 'Parada Lda']);

        $nomes = $this->nomes(['ordenar' => 'facturas']);

        $this->assertLessThan(
            array_search('Parada Lda', $nomes, true),
            array_search('Movimentada Lda', $nomes, true),
            'Quem factura tem de aparecer antes de quem está parado.'
        );
    }

    /** Um por-página inventado no pedido é recusado, e não parte a lista. */
    public function test_um_por_pagina_invalido_e_recusado(): void
    {
        $this->getJson('/api/v1/plataforma/react/empresas?por_pagina=9999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('por_pagina');
    }

    /**
     * UMA PÁGINA PARA LÁ DA ÚLTIMA cai na última. Filtrar na página 3 mostrava
     * «nenhum resultado» com resultados a existir na primeira.
     */
    public function test_uma_pagina_para_la_da_ultima_cai_na_ultima(): void
    {
        $this->empresa(['name' => 'Unica Filtrada Lda']);

        $json = $this->getJson('/api/v1/plataforma/react/empresas?' . http_build_query([
            'procura' => 'Unica Filtrada Lda', 'pagina' => 3,
        ]))->assertOk()->json();

        $this->assertSame(1, $json['paginacao']['pagina']);
        $this->assertSame(['Unica Filtrada Lda'], array_column($json['empresas'], 'nome'));
    }

    /** As contagens dos cartões não mudam quando se filtra por um estado. */
    public function test_os_cartoes_contam_antes_do_filtro_de_estado(): void
    {
        $aFacturar = $this->empresa(['name' => 'Facturadora Lda']);
        $this->comFactura($aFacturar);
        $this->empresa(['name' => 'Fantasma Lda']);

        $contagens = $this->getJson('/api/v1/plataforma/react/empresas')->json('contagens');
        $filtradas = $this->getJson('/api/v1/plataforma/react/empresas?estado=activa')->json('contagens');

        $this->assertEquals(
            $contagens['vazia'] ?? 0,
            $filtradas['vazia'] ?? 0,
            'Clicar num cartão não pode zerar as contagens dos outros.'
        );
    }
}
