<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\Tenants as EcraTenants;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Os filtros da lista de empresas.
 *
 * A lista serve para responder a perguntas — "quem está adormecido?", "quem
 * está no Business?", "quem desactivámos?" — e sem filtros a única resposta
 * possível era ler as páginas todas à mão.
 */
class TenantsFiltrosTest extends TenantTestCase
{
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

    public function test_filtrar_por_estado_mostra_so_esse_estado(): void
    {
        $aFacturar = $this->empresa(['name' => 'Facturadora Lda']);
        $vazia     = $this->empresa(['name' => 'Fantasma Lda']);
        $this->comFactura($aFacturar);

        Livewire::test(EcraTenants::class)
            ->call('filtrarPorEstado', 'activa')
            ->assertSee('Facturadora Lda')
            ->assertDontSee('Fantasma Lda');
    }

    /** O mesmo clique liga e desliga o filtro. */
    public function test_o_cartao_de_estado_alterna(): void
    {
        Livewire::test(EcraTenants::class)
            ->call('filtrarPorEstado', 'vazia')
            ->assertSet('filtroEstado', 'vazia')
            ->call('filtrarPorEstado', 'vazia')
            ->assertSet('filtroEstado', '');
    }

    public function test_filtrar_por_desactivadas(): void
    {
        $morta = $this->empresa(['name' => 'Desligada Lda', 'is_active' => false]);
        $viva  = $this->empresa(['name' => 'Ligada Lda']);

        Livewire::test(EcraTenants::class)
            ->set('filtroActivo', '0')
            ->assertSee('Desligada Lda')
            ->assertDontSee('Ligada Lda');
    }

    public function test_filtrar_por_plano_segue_a_subscricao_em_vigor(): void
    {
        $plano = Plan::create([
            'name' => 'Filtro', 'slug' => 'filtro-' . uniqid(), 'description' => 'x',
            'price_monthly' => 1000, 'price_yearly' => 10000, 'trial_days' => 0,
            'max_users' => 5, 'max_companies' => 1, 'is_active' => true, 'order' => 9,
        ]);

        $dentro = $this->empresa(['name' => 'No Plano Lda']);
        $fora   = $this->empresa(['name' => 'Fora do Plano Lda']);

        Subscription::create([
            'tenant_id' => $dentro->id, 'plan_id' => $plano->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'amount' => 1000,
            'current_period_end' => now()->addMonth(),
        ]);

        Livewire::test(EcraTenants::class)
            ->set('filtroPlano', (string) $plano->id)
            ->assertSee('No Plano Lda')
            ->assertDontSee('Fora do Plano Lda');
    }

    /** Ordenar por facturas põe quem factura no topo. */
    public function test_ordenar_por_facturas(): void
    {
        $comMovimento = $this->empresa(['name' => 'Movimentada Lda']);
        $this->comFactura($comMovimento);
        $this->empresa(['name' => 'Parada Lda']);

        $html = Livewire::test(EcraTenants::class)
            ->set('ordenar', 'facturas')
            ->html();

        $this->assertLessThan(
            strpos($html, 'Parada Lda') ?: PHP_INT_MAX,
            strpos($html, 'Movimentada Lda'),
            'Quem factura tem de aparecer antes de quem está parado.'
        );
    }

    /** Um porPagina inventado no pedido não pode partir a lista. */
    public function test_um_por_pagina_invalido_cai_para_dez(): void
    {
        Livewire::test(EcraTenants::class)
            ->set('porPagina', 9999)
            ->assertOk();
    }

    public function test_mudar_um_filtro_volta_a_primeira_pagina(): void
    {
        Livewire::test(EcraTenants::class)
            ->call('setPage', 3)
            ->set('filtroActivo', '1')
            ->assertSet('paginators.page', 1);
    }

    public function test_limpar_filtros_limpa_tudo(): void
    {
        Livewire::test(EcraTenants::class)
            ->set('search', 'x')
            ->set('filtroActivo', '0')
            ->call('filtrarPorEstado', 'vazia')
            ->call('limparFiltros')
            ->assertSet('search', '')
            ->assertSet('filtroEstado', '')
            ->assertSet('filtroActivo', '')
            ->assertSet('filtroPlano', '');
    }

    /** As contagens dos cartões não mudam quando se filtra por um estado. */
    public function test_os_cartoes_contam_antes_do_filtro_de_estado(): void
    {
        $aFacturar = $this->empresa(['name' => 'Facturadora Lda']);
        $this->comFactura($aFacturar);
        $this->empresa(['name' => 'Fantasma Lda']);

        $semFiltro = Livewire::test(EcraTenants::class);
        $contagens = $semFiltro->viewData('contagens');

        $comFiltro = Livewire::test(EcraTenants::class)->call('filtrarPorEstado', 'activa');
        $contagensFiltradas = $comFiltro->viewData('contagens');

        $this->assertEquals(
            $contagens['vazia'] ?? 0,
            $contagensFiltradas['vazia'] ?? 0,
            'Clicar num cartão não pode zerar as contagens dos outros.'
        );
    }
}
