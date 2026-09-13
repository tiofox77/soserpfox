<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/** As actualizações do sistema (`/changelog`) em React, com as versões do config nas props. */
class ActualizacoesDoSistemaTest extends TenantTestCase
{
    public function test_a_pagina_monta_o_ecra_com_as_versoes(): void
    {
        $r = $this->get('/changelog')->assertOk()->assertSee('data-ecra="conta/actualizacoes"', false);

        preg_match('/data-ecra="conta\/actualizacoes"\s+data-props="([^"]*)"/', $r->getContent(), $m);
        $props = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);

        $this->assertSame(config('changelog.current'), $props['atual']);
        $this->assertCount(count(config('changelog.releases')), $props['versoes']);
    }

    public function test_sem_sessao_nao_abre(): void
    {
        auth()->logout();

        $this->get('/changelog')->assertRedirect();
    }
}
