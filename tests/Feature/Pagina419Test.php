<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * A pagina de erro 419 — "a pagina esteve parada demasiado tempo".
 *
 * O botao principal era href="javascript:location.reload()". Numa pagina de
 * 419 o pedido que falhou foi quase sempre a submissao de um formulario, e
 * recarregar volta a manda-la — ou faz o browser perguntar se quer reenviar os
 * dados. E com a sessao caida o destino certo nem e a pagina: e a entrada.
 */
class Pagina419Test extends TenantTestCase
{
    private function pagina(): string
    {
        return view('errors.419')->render();
    }

    public function test_nao_ha_javascript_no_botao(): void
    {
        $this->assertStringNotContainsString('javascript:', $this->pagina(),
            'um href javascript: volta a submeter o formulario que falhou');
    }

    public function test_sem_sessao_manda_entrar_de_novo(): void
    {
        auth()->logout();
        session()->flush();

        $html = $this->pagina();

        $this->assertStringContainsString(route('login'), $html,
            'com a sessao caida o destino e a entrada, e nao uma recarga');
        $this->assertStringContainsString('Entrar de novo', $html);
    }

    public function test_com_sessao_leva_de_volta_a_pagina(): void
    {
        $this->actingAs($this->user);

        $html = $this->pagina();

        $this->assertStringContainsString('Voltar à página', $html);
        $this->assertStringNotContainsString('Entrar de novo', $html);
    }

    /** Os dois caminhos tem sempre uma saida para o inicio. */
    public function test_ha_sempre_como_voltar_ao_inicio(): void
    {
        $this->assertStringContainsString('Voltar ao início', $this->pagina());
    }
}
