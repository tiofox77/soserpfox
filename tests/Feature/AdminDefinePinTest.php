<?php

namespace Tests\Feature;

use App\Models\User;
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

    private function definir(User $u, string $pin)
    {
        return $this->actingAs($this->user)->postJson(
            "/api/v1/invoicing/react/utilizadores/{$u->id}/pin",
            ['pin' => $pin, 'pin_confirmation' => $pin],
        );
    }

    public function test_o_admin_define_o_pin_de_um_funcionario(): void
    {
        $u = $this->funcionario();

        $this->definir($u, '4827')->assertOk();

        $this->assertTrue($u->fresh()->temPinPos());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('4827', $u->fresh()->pos_pin_hash));
    }

    public function test_recusa_pin_curto(): void
    {
        $u = $this->funcionario();

        $this->definir($u, '12')->assertStatus(422)->assertJsonValidationErrors('pin');

        $this->assertFalse($u->fresh()->temPinPos());
    }

    public function test_recusa_pin_obvio(): void
    {
        $u = $this->funcionario();

        $this->definir($u, '1234')->assertStatus(422)->assertJsonValidationErrors('pin');

        $this->assertFalse($u->fresh()->temPinPos());
    }

    /**
     * NUNCA SE CONFIA NO ID DO BROWSER.
     *
     * Sem a verificação de empresa, um número escrito à mão punha um PIN na
     * conta de alguém de outra casa — e esse PIN abre turno no POS.
     */
    public function test_nao_define_pin_de_funcionario_de_outra_empresa(): void
    {
        $intruso = User::create([
            'name' => 'Fora', 'email' => 'fora@outra.ao',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);

        $this->definir($intruso, '7391')->assertNotFound();

        $this->assertFalse($intruso->fresh()->temPinPos());
    }
}
