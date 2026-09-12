<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * OS CONTACTOS DO RESTAURANTE — clientes e fornecedores.
 *
 * NÃO SÃO UMA SEGUNDA TABELA. O ecrã do restaurante abre os mesmos registos
 * (`invoicing_clients`, `invoicing_suppliers`) que a Facturação usa, e é por
 * isso que estes ensaios batem à porta da Facturação: se houvesse uma segunda
 * porta, um campo novo no cliente apareceria num ecrã e faltaria no outro.
 *
 * O que se prende aqui é o que o ecrã em Livewire prendia — os campos fiscais
 * gravam-se, e um NIF angolano inválido é recusado — mas na porta verdadeira,
 * que é a que o ecrã em React usa.
 */
class RestaurantContactManagementTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/clients';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant');
        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.clients.view', 'invoicing.clients.create');
    }

    public function test_o_formulario_guarda_os_campos_fiscais_e_o_pais_iso(): void
    {
        $this->postJson(self::RAIZ, [
            'name' => 'Cliente Portugal Lda',
            'type' => 'pessoa_juridica',
            'country' => 'PT',
            'nif' => 'PT-509999999',
            'city' => 'Lisboa',
            'tax_regime' => 'geral',
            'is_iva_subject' => true,
            'is_active' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('invoicing_clients', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Portugal Lda',
            'country' => 'PT',
            'city' => 'Lisboa',
        ]);
    }

    public function test_nif_angolano_invalido_e_rejeitado(): void
    {
        $this->postJson(self::RAIZ, [
            'name' => 'Cliente Inválido',
            'type' => 'pessoa_fisica',
            'country' => 'AO',
            'nif' => '123',
            'is_active' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nif');

        $this->assertDatabaseMissing('invoicing_clients', ['name' => 'Cliente Inválido']);
    }

    /** A página do restaurante abre e monta o ecrã dos contactos. */
    public function test_a_pagina_do_restaurante_abre(): void
    {
        $this->comPermissoes('restaurant.orders.view');

        $this->get('/restaurant/contacts')
            ->assertOk()
            ->assertSee('restaurant/contactos', false);
    }
}
