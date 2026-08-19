<?php

namespace Tests\Feature;

use App\Livewire\POS\POSSystem;
use App\Models\Product;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Artigos de um módulo de negócio (salão) não se vendem no POS de faturação.
 *
 * Um "Corte de Cabelo" existe em invoicing_products só para a linha da
 * factura ter artigo de catálogo. Quem o marca e cobra é o POS do salão,
 * que sabe do profissional, da duração e da marcação. Ao balcão da
 * faturação era um artigo solto, sem nada disso.
 */
class PosNaoVendeArtigosDeModuloTest extends TenantTestCase
{
    private function servicoDeSalao(): Product
    {
        $p = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Corte de Cabelo',
            'code' => 'SVC' . uniqid(),
            'type' => 'servico',
            'price' => 3000,
            'manage_stock' => false,
            'is_active' => true,
            'tax_type' => 'isento',
        ]);

        // module NAO e fillable (so os modelos do modulo lhe tocam).
        $p->module = 'salon';
        $p->save();

        return $p;
    }

    private function artigoNormal(): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pão',
            'code' => 'P' . uniqid(),
            'type' => 'produto',
            'price' => 200,
            'manage_stock' => false,
            'is_active' => true,
            'tax_type' => 'isento',
        ]);
    }

    private function turnoAberto(): void
    {
        \App\Models\Invoicing\PosShift::createSafely([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_balance' => 0,
        ], $this->tenant->id);
    }

    public function test_o_pos_de_faturacao_nao_lista_artigos_do_salao(): void
    {
        $salao = $this->servicoDeSalao();
        $normal = $this->artigoNormal();

        $this->comPermissoes('invoicing.pos.access')->comModulo('invoicing');
        $this->turnoAberto();

        $ids = collect(
            Livewire::actingAs($this->user)->test(POSSystem::class)->viewData('products')
        )->pluck('id');

        $this->assertFalse($ids->contains($salao->id), 'o serviço do salão não pode aparecer no POS de faturação');
        $this->assertTrue($ids->contains($normal->id), 'um artigo normal tem de continuar a aparecer');
    }

    public function test_o_pos_recusa_por_id_mesmo_que_a_grelha_seja_contornada(): void
    {
        $salao = $this->servicoDeSalao();

        $this->comPermissoes('invoicing.pos.access')->comModulo('invoicing');
        $this->turnoAberto();

        Livewire::actingAs($this->user)->test(POSSystem::class)
            ->call('addToCart', $salao->id)
            ->assertDispatched('notify');

        // Não entrou no carrinho.
        $carrinho = \Darryldecode\Cart\Facades\CartFacade::session(
            $this->user->id . '_t' . $this->tenant->id
        )->getContent();

        $this->assertNull($carrinho->get($salao->id), 'o artigo do salão não podia entrar no carrinho');
    }

    public function test_o_sync_offline_tambem_nao_leva_artigos_do_salao(): void
    {
        $salao = $this->servicoDeSalao();
        $normal = $this->artigoNormal();

        $json = $this->actingAs($this->user)->getJson('/api/v1/invoicing/sync')->assertOk()->json();
        $ids = collect($json['data']['products'])->pluck('id');

        $this->assertFalse($ids->contains($salao->id));
        $this->assertTrue($ids->contains($normal->id));
    }
}
