<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TenantTestCase;

/**
 * PIN de turno para login offline no POS.
 *
 * O que se garante: o sync leva os funcionários ACTIVOS com PIN para o
 * tablet poder autenticar qualquer um offline, o verificador é bcrypt
 * (o que o bcryptjs entende), e o PIN em claro nunca sai do servidor.
 */
class PosPinOfflineTest extends TenantTestCase
{
    private function sincronizar(): array
    {
        return $this->actingAs($this->user)
            ->getJson('/api/v1/invoicing/sync')
            ->assertOk()
            ->json();
    }

    /** Cria um funcionário activo do tenant, com ou sem PIN. */
    private function funcionario(string $email, ?string $pin): User
    {
        $u = User::create([
            'name' => 'Func ' . $email,
            'email' => $email,
            'password' => bcrypt('segredo-forte'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $u->tenants()->syncWithoutDetaching([$this->tenant->id]);

        if ($pin !== null) {
            $u->definirPinPos($pin);
        }

        return $u;
    }

    public function test_o_sync_leva_os_funcionarios_com_pin(): void
    {
        $this->funcionario('caixa1@empresa.ao', '4827');
        $this->funcionario('caixa2@empresa.ao', '9310');

        $json = $this->sincronizar();

        $this->assertArrayHasKey('employees', $json);
        $emails = collect($json['employees'])->pluck('email');

        $this->assertTrue($emails->contains('caixa1@empresa.ao'));
        $this->assertTrue($emails->contains('caixa2@empresa.ao'));
    }

    public function test_funcionario_sem_pin_nao_vai_no_sync(): void
    {
        $this->funcionario('sempin@empresa.ao', null);

        $emails = collect($this->sincronizar()['employees'])->pluck('email');

        $this->assertFalse($emails->contains('sempin@empresa.ao'),
            'quem não definiu PIN não pode entrar offline, logo não vai no sync');
    }

    public function test_funcionario_inactivo_nao_vai_no_sync(): void
    {
        $u = $this->funcionario('saiu@empresa.ao', '4827');
        $u->update(['is_active' => false]);

        $emails = collect($this->sincronizar()['employees'])->pluck('email');

        $this->assertFalse($emails->contains('saiu@empresa.ao'),
            'um funcionário desactivado deixa de poder entrar offline');
    }

    public function test_o_verificador_e_bcrypt_e_nunca_o_pin(): void
    {
        $this->funcionario('caixa@empresa.ao', '4827');

        $emp = collect($this->sincronizar()['employees'])
            ->firstWhere('email', 'caixa@empresa.ao');

        // bcrypt normalizado para o bcryptjs
        $this->assertStringStartsWith('$2a$', $emp['pin_hash']);
        // e não o PIN em claro
        $this->assertStringNotContainsString('4827', $emp['pin_hash']);
        // o hash confere mesmo com o PIN (prova de que é utilizável)
        $this->assertTrue(password_verify('4827', str_replace('$2a$', '$2y$', $emp['pin_hash'])));
    }

    public function test_o_sync_diz_ate_quando_o_offline_vale(): void
    {
        $json = $this->sincronizar();

        $this->assertArrayHasKey('offline_valid_until', $json);
        $ate = \Carbon\Carbon::parse($json['offline_valid_until']);

        // ~14 dias a partir de agora
        $this->assertEqualsWithDelta(14, now()->diffInDays($ate), 1);
    }

    public function test_pin_fraco_e_recusado_pelo_modelo(): void
    {
        $u = $this->funcionario('x@empresa.ao', null);

        $this->expectException(\InvalidArgumentException::class);
        $u->definirPinPos('12'); // curto demais
    }

    public function test_o_hash_do_pin_nunca_aparece_ao_serializar_o_utilizador(): void
    {
        $u = $this->funcionario('y@empresa.ao', '4827');

        $this->assertArrayNotHasKey('pos_pin_hash', $u->toArray());
    }
}
