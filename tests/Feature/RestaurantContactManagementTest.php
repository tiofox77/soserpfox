<?php

namespace Tests\Feature;

use App\Livewire\Restaurant\ContactManagement;
use Livewire\Livewire;
use Tests\TenantTestCase;

class RestaurantContactManagementTest extends TenantTestCase
{
    public function test_formulario_mostra_campos_fiscais_e_guarda_pais_iso(): void
    {
        Livewire::actingAs($this->user)->test(ContactManagement::class)
            ->call('create', 'clients')
            ->assertSee('País')->assertSee('Regime fiscal')->assertSee('Sujeito a IVA')
            ->set('name', 'Cliente Portugal Lda')->set('type', 'pessoa_juridica')
            ->set('country', 'PT')->set('nif', 'PT-509999999')->set('city', 'Lisboa')
            ->set('taxRegime', 'geral')->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('invoicing_clients', [
            'tenant_id' => $this->tenant->id, 'name' => 'Cliente Portugal Lda',
            'country' => 'PT', 'city' => 'Lisboa',
        ]);
    }

    public function test_nif_angolano_invalido_e_rejeitado(): void
    {
        Livewire::actingAs($this->user)->test(ContactManagement::class)
            ->call('create', 'clients')->set('name', 'Cliente Inválido')
            ->set('country', 'AO')->set('nif', '123')->call('save')
            ->assertHasErrors(['nif']);
    }
}
