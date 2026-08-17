<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\Tenants;
use App\Models\Tenant;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O NIF no cartao de cada empresa, na lista do super admin.
 *
 * E por ele que a AGT identifica a empresa, e e o primeiro numero que se pede
 * ao telefone quando um cliente liga — estava so dentro da ficha de edicao.
 */
class CartaoDaEmpresaNifTest extends TenantTestCase
{
    private function comoDonoDaPlataforma(): void
    {
        $this->user->update(['is_super_admin' => true]);
        $this->actingAs($this->user->fresh());
    }

    private function empresa(string $nome, ?string $nif): Tenant
    {
        return Tenant::create([
            'name' => $nome, 'company_name' => $nome, 'nif' => $nif, 'is_active' => true,
        ]);
    }

    public function test_o_cartao_mostra_o_nif(): void
    {
        $this->comoDonoDaPlataforma();
        $this->empresa('Farmacia Com NIF', '5417289442');

        Livewire::test(Tenants::class)
            ->set('search', 'Farmacia Com NIF')
            ->assertSee('5417289442');
    }

    /** Um NIF que nao comeca por 5 fica assinalado onde se ve. */
    public function test_um_nif_que_nao_e_de_empresa_e_assinalado(): void
    {
        $this->comoDonoDaPlataforma();
        $this->empresa('Empresa Com NIF Pessoal', '004512345');

        Livewire::test(Tenants::class)
            ->set('search', 'Empresa Com NIF Pessoal')
            ->assertSee('Não é NIF de empresa');
    }

    /** E um NIF de empresa nao leva aviso nenhum. */
    public function test_um_nif_de_empresa_nao_leva_aviso(): void
    {
        $this->comoDonoDaPlataforma();
        $this->empresa('Empresa Correcta', '5417289442');

        Livewire::test(Tenants::class)
            ->set('search', 'Empresa Correcta')
            ->assertDontSee('Não é NIF de empresa');
    }

    /** Sem NIF diz que falta, em vez de deixar o espaco em branco. */
    public function test_sem_nif_diz_que_falta(): void
    {
        $this->comoDonoDaPlataforma();
        $this->empresa('Empresa Sem NIF', null);

        Livewire::test(Tenants::class)
            ->set('search', 'Empresa Sem NIF')
            ->assertSee('por preencher');
    }

    /** A pesquisa encontra pelo NIF, como o proprio campo promete. */
    public function test_a_pesquisa_encontra_pelo_nif(): void
    {
        $this->comoDonoDaPlataforma();
        $this->empresa('Procurada Pelo Numero', '5999888777');

        Livewire::test(Tenants::class)
            ->set('search', '5999888777')
            ->assertSee('Procurada Pelo Numero');
    }
}
