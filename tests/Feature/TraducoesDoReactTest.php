<?php

namespace Tests\Feature;

use App\Support\DicionarioDoReact;
use Tests\TenantTestCase;

/**
 * OS ECRÃS EM REACT VOLTAM A FALAR AS TRÊS LÍNGUAS.
 *
 * A aplicação fala português, inglês e francês, e o dicionário é um só — os
 * `lang/en.json` e `lang/fr.json`, com a frase em português por chave. Os ecrãs
 * em Blade traduziam por `__()`; ao passarem para React, o `__()` ficou para
 * trás e a facturação passou a falar só português a quem tinha escolhido outra
 * língua.
 *
 * O QUE ISTO GUARDA: que o mecanismo existe e que **não custa nada a quem
 * trabalha em português** — nessa língua a chave já é a frase, e não se
 * descarrega dicionário nenhum. Os ~200 KB de cada ficheiro só viajam para
 * quem os pediu.
 */
class TraducoesDoReactTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.sales.invoices.view');
    }

    /** @test */
    public function em_portugues_nao_se_descarrega_dicionario_nenhum(): void
    {
        $this->user->update(['locale' => 'pt']);

        $this->actingAs($this->user)
            ->get('/invoicing/sales/invoices')
            ->assertOk()
            ->assertSee('window.__reactLingua = "pt"', false)
            ->assertDontSee('__reactDicionarioUrl', false);

        $this->assertFalse(DicionarioDoReact::precisa('pt'));
        $this->assertSame([], DicionarioDoReact::frases('pt'));
    }

    /** @test */
    public function em_ingles_a_pagina_anuncia_o_dicionario_e_ele_serve_se(): void
    {
        $this->user->update(['locale' => 'en']);

        $pagina = $this->actingAs($this->user)->get('/invoicing/sales/invoices')->assertOk();

        $pagina->assertSee('window.__reactLingua = "en"', false);
        $pagina->assertSee('__reactDicionarioUrl', false);

        $frases = DicionarioDoReact::frases('en');

        $this->assertNotEmpty($frases, 'o lang/en.json é o dicionário de sempre');
        $this->assertArrayHasKey('Clientes', $frases);

        // E a morada anunciada serve mesmo o dicionário.
        $this->actingAs($this->user)
            ->get(route('react.traducoes', ['marca' => DicionarioDoReact::marca('en')]))
            ->assertOk()
            ->assertJsonPath('Clientes', $frases['Clientes']);
    }

    /**
     * A MARCA DA VERSÃO VAI NA MORADA.
     *
     * É o que permite guardar o dicionário no aparelho e não voltar a pedi-lo:
     * um ficheiro novo tem morada nova, e um deploy que não lhe toque não
     * obriga ninguém a descarregar 200 KB outra vez.
     *
     * @test
     */
    public function a_marca_muda_quando_o_dicionario_muda(): void
    {
        $antes = DicionarioDoReact::marca('en');

        $this->assertStringStartsWith('en-', $antes);
        $this->assertSame($antes, DicionarioDoReact::marca('en'), 'sem mexer no ficheiro, a marca é a mesma');
        $this->assertNotSame($antes, DicionarioDoReact::marca('fr'));
    }

    /** Em português a porta nem existe: não há dicionário para servir. @test */
    public function em_portugues_a_porta_do_dicionario_fecha(): void
    {
        $this->user->update(['locale' => 'pt']);

        $this->actingAs($this->user)
            ->get(route('react.traducoes', ['marca' => 'pt']))
            ->assertNotFound();
    }
}
