<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Products;
use App\Models\Category;
use App\Models\Product;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Criar produto com os campos numéricos opcionais em branco.
 *
 * O formulário devolve '' num campo de número que se limpa. A validação
 * deixava passar (são nullable) mas o MySQL recusa '' numa coluna inteira:
 * o INSERT rebentava, o Livewire devolvia 500 e o ecrã não dizia nada —
 * o utilizador carregava em "Criar" e não acontecia nada.
 */
class CriarProdutoComCamposVaziosTest extends TenantTestCase
{
    private function categoria(): Category
    {
        return Category::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bebidas', 'is_active' => true,
        ]);
    }

    private function formulario(): \Livewire\Features\SupportTesting\Testable
    {
        $this->comPermissoes('invoicing.products.create', 'invoicing.products.edit')
            ->comModulo('invoicing');

        return Livewire::actingAs($this->user)->test(Products::class)
            ->call('create')
            ->set('name', 'pao')
            ->set('type', 'produto')
            ->set('price', 0)
            ->set('unit', 'Unidade')
            ->set('category_id', $this->categoria()->id)
            ->set('tax_type', 'isento')
            ->set('exemption_reason', 'I13');
    }

    public function test_grava_com_stock_minimo_e_maximo_em_branco(): void
    {
        $this->formulario()
            ->set('stock_min', '')
            ->set('stock_max', '')
            ->set('stock_quantity', '')
            ->call('save')
            ->assertHasNoErrors();

        $p = Product::where('tenant_id', $this->tenant->id)->where('name', 'pao')->first();

        $this->assertNotNull($p, 'o produto tinha de ser gravado');
        $this->assertSame(0, (int) $p->stock_min, 'mínimo em branco é 0');
        $this->assertNull($p->stock_max, 'máximo em branco é "sem máximo"');
    }

    public function test_grava_com_meses_apos_abertura_em_branco(): void
    {
        $this->formulario()
            ->set('pao_months', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull(
            Product::where('tenant_id', $this->tenant->id)->where('name', 'pao')->first()
        );
    }

    public function test_os_valores_preenchidos_continuam_a_ser_gravados(): void
    {
        $this->formulario()
            ->set('stock_min', '5')
            ->set('stock_max', '50')
            ->call('save')
            ->assertHasNoErrors();

        $p = Product::where('tenant_id', $this->tenant->id)->where('name', 'pao')->first();

        $this->assertSame(5, (int) $p->stock_min);
        $this->assertSame(50, (int) $p->stock_max);
    }
}
