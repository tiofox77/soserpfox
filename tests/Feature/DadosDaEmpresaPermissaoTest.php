<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * Os dados da empresa são de quem a gere.
 *
 * `/empresa` esteve só atrás do `auth`: qualquer utilizador com sessão abria a
 * página e podia GRAVAR — mudar o NIF, o nome e, sobretudo, o REGIME FISCAL,
 * que se propaga aos impostos, às definições de facturação e a todos os
 * produtos. Um caixa punha a empresa inteira no regime errado.
 *
 * Ver e mudar são direitos diferentes: um contabilista precisa do NIF e não de
 * mexer no regime. O ecrã é hoje React, e a regra continua a ser verificada em
 * cada pedido — a guarda da rota abre a página, não autoriza a escrita.
 */
class DadosDaEmpresaPermissaoTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/empresa';

    /** @test */
    public function sem_permissao_nem_a_pagina_abre(): void
    {
        $this->actingAs($this->user);

        $this->get(route('company.profile'))->assertForbidden();
    }

    /** @test */
    public function sem_permissao_a_api_recusa_se(): void
    {
        $this->actingAs($this->user);

        // A guarda da rota da PÁGINA não chega: a API tem o seu próprio
        // endereço e não torna a passar por ela.
        $this->getJson(self::RAIZ)->assertForbidden();
    }

    /** @test */
    public function quem_pode_ver_abre_a_pagina(): void
    {
        $this->comPermissoes('settings.view');
        $this->actingAs($this->user);

        $this->get(route('company.profile'))->assertOk()->assertSee('empresa/dados', false);
        $this->getJson(self::RAIZ)->assertOk()->assertJsonPath('permissoes.editar', false);
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

        $this->putJson(self::RAIZ, [
            'name' => 'Nome Roubado',
            'nif' => $this->tenant->nif,
            'country' => 'AO',
            'regime' => Tenant::REGIME_NAO_SUJEICAO,
            'confirmar_regime' => true,
        ])->assertForbidden();

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

        $this->deleteJson(self::RAIZ.'/logotipo')->assertForbidden();

        $this->assertSame('logos/teste.png', $this->tenant->fresh()->logo);
    }

    /** @test */
    public function quem_gere_a_empresa_grava(): void
    {
        $this->comPermissoes('settings.view', 'settings.edit');
        $this->actingAs($this->user);

        $this->putJson(self::RAIZ, [
            'name' => 'Nome Novo Lda',
            'nif' => $this->tenant->nif,
            'country' => 'AO',
            'regime' => Tenant::canonicalRegime($this->tenant->regime),
        ])->assertOk();

        $this->assertSame('Nome Novo Lda', $this->tenant->fresh()->name);
    }

    /**
     * MUDAR DE REGIME PEDE UM SIM ESCRITO.
     *
     * Não é um campo como os outros: ao guardar, o imposto por omissão e o
     * regime de TODOS os produtos mudam de uma vez. Um pedido que lá ponha o
     * regime novo sem a confirmação não passa.
     */
    public function test_mudar_de_regime_sem_confirmar_nao_passa(): void
    {
        $this->comPermissoes('settings.view', 'settings.edit');
        $this->actingAs($this->user);

        $antes = Tenant::canonicalRegime($this->tenant->regime);

        $this->putJson(self::RAIZ, [
            'name' => $this->tenant->name,
            'nif' => $this->tenant->nif,
            'country' => 'AO',
            'regime' => Tenant::REGIME_NAO_SUJEICAO,
        ])->assertStatus(422)->assertJsonValidationErrors('regime');

        $this->assertSame($antes, Tenant::canonicalRegime($this->tenant->fresh()->regime));
    }

    /** Com a confirmação, muda — e propaga-se. */
    public function test_com_a_confirmacao_o_regime_muda(): void
    {
        $this->comPermissoes('settings.view', 'settings.edit');
        $this->actingAs($this->user);

        $this->putJson(self::RAIZ, [
            'name' => $this->tenant->name,
            'nif' => $this->tenant->nif,
            'country' => 'AO',
            'regime' => Tenant::REGIME_NAO_SUJEICAO,
            'confirmar_regime' => true,
        ])->assertOk();

        $this->assertSame(
            Tenant::REGIME_NAO_SUJEICAO,
            Tenant::canonicalRegime($this->tenant->fresh()->regime),
        );
    }

    /**
     * GUARDAR OS CONTACTOS NÃO REESCREVE O REGIME.
     *
     * Empresas antigas têm valores legados («regime_isencao»). Reescrevê-los em
     * cada gravação era mudar-lhes o regime só por se ter corrigido o telefone.
     */
    public function test_guardar_sem_mexer_no_regime_nao_toca_no_valor_legado(): void
    {
        $this->comPermissoes('settings.view', 'settings.edit');
        $this->actingAs($this->user);

        $this->tenant->update(['regime' => 'regime_isencao']);

        $this->putJson(self::RAIZ, [
            'name' => $this->tenant->name,
            'nif' => $this->tenant->nif,
            'phone' => '923000111',
            'country' => 'AO',
            // O canónico do legado — não é uma mudança de regime.
            'regime' => Tenant::REGIME_NAO_SUJEICAO,
        ])->assertOk();

        $this->assertSame('regime_isencao', $this->tenant->fresh()->regime,
            'o valor legado foi reescrito sem ninguém o pedir');
        $this->assertSame('923000111', $this->tenant->fresh()->phone);
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
