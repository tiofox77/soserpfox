<?php

namespace Tests\Feature;

use App\Livewire\Company\CompanyProfile;
use App\Models\Tenant;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Os dados da empresa são de quem a gere.
 *
 * `/empresa` estava só atrás do `auth`: qualquer utilizador com sessão abria
 * a página e podia GRAVAR — mudar o NIF, o nome e, sobretudo, o REGIME
 * FISCAL, que se propaga aos impostos, às definições de facturação e a todos
 * os produtos. Um caixa punha a empresa inteira no regime errado.
 *
 * Ver e mudar passam a ser direitos diferentes: um contabilista precisa do
 * NIF e não de mexer no regime.
 */
class DadosDaEmpresaPermissaoTest extends TenantTestCase
{
    /** @test */
    public function sem_permissao_nem_a_pagina_abre(): void
    {
        $this->actingAs($this->user);

        $this->get(route('company.profile'))->assertForbidden();
    }

    /** @test */
    public function sem_permissao_o_componente_recusa_se(): void
    {
        $this->actingAs($this->user);

        // A guarda da rota não chega: o Livewire fala com o seu próprio
        // endereço e não torna a passar pelo middleware.
        Livewire::test(CompanyProfile::class)->assertStatus(403);
    }

    /** @test */
    public function quem_pode_ver_abre_a_pagina(): void
    {
        $this->comPermissoes('settings.view');
        $this->actingAs($this->user);

        $this->get(route('company.profile'))->assertOk();
    }

    /**
     * VER NÃO É MUDAR — é a metade que interessa.
     *
     * @test
     */
    public function quem_so_ve_nao_grava(): void
    {
        $this->comPermissoes('settings.view');
        $this->actingAs($this->user);

        $nomeAntes = $this->tenant->name;

        Livewire::test(CompanyProfile::class)
            ->set('name', 'Nome Roubado')
            ->set('regime', Tenant::REGIME_NAO_SUJEICAO)
            ->call('save');

        $depois = $this->tenant->fresh();

        $this->assertSame($nomeAntes, $depois->name, 'o nome da empresa mudou sem direito');
        $this->assertNotSame(Tenant::REGIME_NAO_SUJEICAO, Tenant::canonicalRegime($depois->regime),
            'o REGIME FISCAL mudou sem direito — propaga-se a impostos e produtos');
    }

    /** Nem apaga o logótipo. */
    public function test_quem_so_ve_nao_apaga_o_logotipo(): void
    {
        $this->tenant->update(['logo' => 'logos/teste.png']);

        $this->comPermissoes('settings.view');
        $this->actingAs($this->user);

        Livewire::test(CompanyProfile::class)->call('removeLogo');

        $this->assertSame('logos/teste.png', $this->tenant->fresh()->logo);
    }

    /** @test */
    public function quem_gere_a_empresa_grava(): void
    {
        $this->comPermissoes('settings.view', 'settings.edit');
        $this->actingAs($this->user);

        Livewire::test(CompanyProfile::class)
            ->set('name', 'Nome Novo Lda')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Nome Novo Lda', $this->tenant->fresh()->name);
    }

    /** O menu não oferece o que a página recusa. */
    public function test_o_menu_esconde_o_link_a_quem_nao_pode(): void
    {
        $this->actingAs($this->user);
        $this->get(route('home'))->assertDontSee(route('company.profile'));

        $this->comPermissoes('settings.view');
        $this->actingAs($this->user->fresh());
        $this->get(route('home'))->assertSee(route('company.profile'));
    }
}
