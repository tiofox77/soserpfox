<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

class AgtSubmissoesPorAmbienteTest extends TenantTestCase
{
    /** O diagnóstico corre sem submissões e não escreve nada. @test */
    public function corre_e_so_le(): void
    {
        $this->artisan('agt:submissoes-por-ambiente', ['--empresa' => $this->tenant->id])
            ->expectsOutputToContain('Nenhuma submissão por concluir fica parada')
            ->assertSuccessful();
    }
}
