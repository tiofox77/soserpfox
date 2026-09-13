<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Um separador aberto antes do deploy do React pede ao Livewire um componente
 * que já não existe. Recebe 409, que o layout antigo trata recarregando.
 */
class SeparadorDeAntesDoReactTest extends TestCase
{
    public function test_o_pedido_do_livewire_recebe_409_e_nao_500(): void
    {
        $this->postJson('/livewire/update', ['components' => [['snapshot' => '{}', 'updates' => [], 'calls' => []]]], ['X-Livewire' => '1'])
            ->assertStatus(409);
    }

    public function test_sem_o_cabecalho_do_livewire_nada_muda(): void
    {
        $this->get('/login')->assertOk();
    }
}
