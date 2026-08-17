<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * A entrada e a saída do PWA.
 *
 * Sair tem de terminar a sessão do servidor, não só sair do ecrã: deixá-la
 * aberta é deixar a conta acessível a quem apanhe o aparelho. E a página de
 * entrada tem de abrir SEM sessão — senão pedia login para poder mostrar o
 * login.
 */
class PwaEntradaESaidaTest extends TenantTestCase
{
    public function test_a_entrada_do_pwa_abre_sem_sessao(): void
    {
        $this->get(route('invoicing.offline.login'))
            ->assertOk()
            ->assertSee('SOS ERP', false);
    }

    public function test_o_pos_continua_a_exigir_sessao(): void
    {
        // A entrada ser pública não pode ter aberto o resto.
        // O TenantTestCase já deixa alguém autenticado; aqui interessa o
        // visitante.
        auth()->logout();

        $this->get(route('invoicing.offline.pos'))->assertRedirect();
    }

    public function test_sair_termina_a_sessao_e_devolve_a_entrada_do_pwa(): void
    {
        $this->actingAs($this->user);
        $this->assertTrue(auth()->check());

        $this->post(route('invoicing.offline.sair'))
            ->assertRedirect(route('invoicing.offline.login'));

        $this->assertFalse(auth()->check(), 'a sessão do servidor tinha de fechar');
    }

    public function test_a_saida_vinda_da_fila_responde_ok_e_nao_um_redireccionamento(): void
    {
        $this->actingAs($this->user);

        // Quem chega aqui é a fila do aparelho, depois de uma saída sem rede.
        // Um 302 para o login fazia o trabalho parecer falhado e ele ficava a
        // repetir-se para sempre.
        $this->postJson(route('invoicing.offline.sair'), ['da_fila' => true])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertFalse(auth()->check());
    }

    public function test_sair_sem_sessao_nao_rebenta(): void
    {
        // A fila pode chegar tarde, com a sessão já caída. Tem de ser um
        // nada-a-fazer, senão o trabalho fica preso no aparelho.
        $this->postJson(route('invoicing.offline.sair'), ['da_fila' => true])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}
