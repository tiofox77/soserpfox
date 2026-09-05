<?php

namespace Tests\Feature\Pwa;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

/**
 * O comando que repõe o PIN de turno de um funcionário (pwa:definir-pin).
 *
 * O que estes ensaios prendem é o que torna seguro correr isto por um URL de
 * manutenção: o PIN óbvio é recusado, o número só se grava com --aplicar, o
 * hash que fica confere mesmo, e ninguém define o PIN de um funcionário de
 * outra empresa.
 */
class DefinirPinPosCommandTest extends TenantTestCase
{
    /** @test */
    public function recusa_um_pin_obvio_e_nao_grava_nada(): void
    {
        $this->artisan('pwa:definir-pin', [
            '--tenant' => $this->tenant->id,
            '--email' => $this->user->email,
            '--pin' => '1234',
            '--aplicar' => true,
        ])
            ->expectsOutputToContain('demasiado obvio')
            ->assertExitCode(1);

        $this->assertFalse($this->user->fresh()->temPinPos(), 'um PIN recusado não podia ter ficado gravado');
    }

    /** @test */
    public function so_grava_com_aplicar(): void
    {
        // A seco: não grava.
        $this->artisan('pwa:definir-pin', [
            '--tenant' => $this->tenant->id,
            '--email' => $this->user->email,
            '--pin' => '4820',
        ])
            ->expectsOutputToContain('CONSULTA apenas')
            ->assertExitCode(0);

        $this->assertFalse($this->user->fresh()->temPinPos());
    }

    /** @test */
    public function um_pin_dado_grava_um_hash_que_confere(): void
    {
        $this->artisan('pwa:definir-pin', [
            '--tenant' => $this->tenant->id,
            '--email' => $this->user->email,
            '--pin' => '4820',
            '--aplicar' => true,
        ])->assertExitCode(0);

        $hash = $this->user->fresh()->pos_pin_hash;
        $this->assertNotEmpty($hash);
        $this->assertTrue(Hash::check('4820', $hash), 'o PIN escrito tem de conferir com o hash guardado');
    }

    /** @test */
    public function sem_pin_gera_um_valido_e_nao_obvio(): void
    {
        $this->artisan('pwa:definir-pin', [
            '--tenant' => $this->tenant->id,
            '--email' => $this->user->email,
            '--aplicar' => true,
        ])->assertExitCode(0);

        $this->assertTrue($this->user->fresh()->temPinPos(), 'um PIN gerado tinha de ficar gravado');
    }

    /** @test */
    public function nao_toca_num_funcionario_de_outra_empresa(): void
    {
        $estranho = User::create([
            'name' => 'De Outra Casa',
            'email' => 'estranho'.uniqid().'@exemplo.ao',
            'password' => bcrypt('secret'),
            'tenant_id' => $this->tenant->id,
        ]);
        // NÃO é ligado ao tenant (não faz tenants()->attach) — logo não é membro.

        $this->artisan('pwa:definir-pin', [
            '--tenant' => $this->tenant->id,
            '--email' => $estranho->email,
            '--pin' => '4820',
            '--aplicar' => true,
        ])
            ->expectsOutputToContain('nao pertence a este tenant')
            ->assertExitCode(1);

        $this->assertFalse($estranho->fresh()->temPinPos());
    }
}
