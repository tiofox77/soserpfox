<?php

namespace Tests\Feature;

use App\Services\Tenants\SinaisDeVida;
use Tests\TenantTestCase;

/**
 * O registo da ultima entrada de cada utilizador.
 *
 * O middleware que grava isto esteve dez meses pendurado no stack GLOBAL, que
 * corre antes de a sessao arrancar: ali o auth()->check() e sempre falso e nao
 * se gravava nada. A lista de empresas dizia "nunca entrou" de toda a gente.
 */
class RegistoDaUltimaEntradaTest extends TenantTestCase
{
    public function test_uma_pagina_aberta_por_quem_entrou_fica_registada(): void
    {
        $this->user->update(['last_login_at' => null]);

        $this->actingAs($this->user)->get('/home')->assertOk();

        $this->assertNotNull($this->user->fresh()->last_login_at,
            'o middleware nao gravou: provavelmente voltou ao stack global');
    }

    /** E a lista de empresas passa a ve-lo. */
    public function test_a_lista_de_empresas_deixa_de_dizer_nunca_entrou(): void
    {
        $this->user->update(['last_login_at' => null]);
        $this->user->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $this->assertNull(SinaisDeVida::para([$this->tenant->id])[$this->tenant->id]->ultima_entrada);

        $this->actingAs($this->user)->get('/home')->assertOk();

        $this->assertNotNull(
            SinaisDeVida::para([$this->tenant->id])[$this->tenant->id]->ultima_entrada,
            'entrou, e a lista continua a dizer que nunca entrou'
        );
    }

    /** Quem nao esta autenticado nao deixa rasto nenhum. */
    public function test_uma_visita_anonima_nao_grava_nada(): void
    {
        $this->user->update(['last_login_at' => null]);

        // A classe base ja deixa alguem autenticado; sem isto o teste media
        // uma visita autenticada e passava por engano.
        auth()->logout();
        session()->flush();

        $this->get('/login');

        $this->assertNull($this->user->fresh()->last_login_at);
    }
}
