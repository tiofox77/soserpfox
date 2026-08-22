<?php

namespace Tests\Feature;

use App\Livewire\Restaurant\CategoryManagement;
use App\Models\Category;
use Livewire\Livewire;
use Tests\TenantTestCase;

class RestaurantCategoryManagementTest extends TenantTestCase
{
    public function test_novo_tenant_recebe_categorias_exemplo_e_geral(): void
    {
        $names = Category::where('tenant_id', $this->tenant->id)->pluck('name');
        $this->assertContains('Geral', $names);
        $this->assertContains('Pratos Principais', $names);
        $this->assertContains('Bebidas', $names);
    }

    public function test_restaurante_cria_categoria_no_catalogo_partilhado(): void
    {
        Livewire::actingAs($this->user)->test(CategoryManagement::class)
            ->call('create')->set('name', 'Sumos Naturais')->set('icon', 'fa-martini-glass-citrus')
            ->set('color', '#0891B2')->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('invoicing_categories', ['tenant_id'=>$this->tenant->id,'name'=>'Sumos Naturais','is_active'=>1]);
    }

    public function test_categoria_geral_nao_pode_ser_eliminada(): void
    {
        $general = Category::where('tenant_id',$this->tenant->id)->where('slug','geral')->firstOrFail();
        Livewire::actingAs($this->user)->test(CategoryManagement::class)->call('delete',$general->id)->assertDispatched('notify');
        $this->assertDatabaseHas('invoicing_categories',['id'=>$general->id,'deleted_at'=>null]);
    }
}
