<?php

namespace Tests\Feature;

use App\Livewire\Users\UserManagement;
use App\Models\User;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O admin define/repõe o PIN de turno de um funcionário.
 */
class AdminDefinePinTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->comPermissoes('users.manage');
    }

    private function funcionario(): User
    {
        $u = User::create([
            'name' => 'Caixa', 'email' => 'caixa@empresa.ao',
            'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
        $u->tenants()->syncWithoutDetaching([$this->tenant->id]);
        return $u;
    }

    public function test_o_admin_define_o_pin_de_um_funcionario(): void
    {
        $u = $this->funcionario();

        Livewire::actingAs($this->user)->test(UserManagement::class)
            ->call('openPinModal', $u->id)
            ->set('posPin', '4827')
            ->set('posPinConfirmation', '4827')
            ->call('savePin')
            ->assertHasNoErrors();

        $this->assertTrue($u->fresh()->temPinPos());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('4827', $u->fresh()->pos_pin_hash));
    }

    public function test_recusa_pin_curto(): void
    {
        $u = $this->funcionario();

        Livewire::actingAs($this->user)->test(UserManagement::class)
            ->call('openPinModal', $u->id)
            ->set('posPin', '12')
            ->set('posPinConfirmation', '12')
            ->call('savePin')
            ->assertHasErrors('posPin');

        $this->assertFalse($u->fresh()->temPinPos());
    }

    public function test_recusa_pin_obvio(): void
    {
        $u = $this->funcionario();

        Livewire::actingAs($this->user)->test(UserManagement::class)
            ->call('openPinModal', $u->id)
            ->set('posPin', '1234')
            ->set('posPinConfirmation', '1234')
            ->call('savePin')
            ->assertHasErrors('posPin');
    }

    public function test_nao_define_pin_de_funcionario_de_outra_empresa(): void
    {
        $intruso = User::create([
            'name' => 'Fora', 'email' => 'fora@outra.ao',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);

        Livewire::actingAs($this->user)->test(UserManagement::class)
            ->call('openPinModal', $intruso->id);

        // Não abre o modal para quem não é da empresa.
        $this->assertFalse($intruso->fresh()->temPinPos());
    }
}
