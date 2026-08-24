<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Tests\TenantTestCase;

/**
 * O limite de utilizadores por plano. Havia três defeitos, e cada um destes
 * testes fixa a correcção de um:
 *   1. duas regras diferentes respondiam à mesma pergunta;
 *   2. contavam-se contas desactivadas, pelo que desactivar não libertava vaga;
 *   3. o convite passava ao lado do limite — convidar N pessoas metia N dentro.
 */
class LimiteDeUtilizadoresTest extends TenantTestCase
{
    private function juntarUtilizador(bool $activo = true): User
    {
        $u = User::create([
            'name' => 'U' . uniqid(),
            'email' => uniqid() . '@x.com',
            'password' => bcrypt('x'),
            'is_active' => $activo,
        ]);
        $u->tenants()->attach($this->tenant->id, ['is_active' => $activo, 'joined_at' => now()]);

        return $u;
    }

    public function test_as_duas_regras_respondem_o_mesmo(): void
    {
        $this->tenant->update(['max_users' => 7]);
        $t = $this->tenant->fresh();

        // getMaxUsers/canAddUser eram independentes de limiteDeUtilizadores/
        // cabeMaisUmUtilizador e discordavam. Agora são a mesma coisa.
        $this->assertSame($t->limiteDeUtilizadores(), $t->getMaxUsers());
        $this->assertSame($t->cabeMaisUmUtilizador(), $t->canAddUser());
    }

    public function test_limite_zero_e_ilimitado(): void
    {
        // Um campo por preencher não pode trancar a empresa inteira.
        $this->tenant->update(['max_users' => 0]);
        $this->tenant->activeSubscription?->plan?->update(['max_users' => 0]);
        $t = $this->tenant->fresh()->load('activeSubscription.plan');

        $this->assertSame(0, $t->limiteDeUtilizadores());
        $this->assertTrue($t->cabeMaisUmUtilizador());
    }

    public function test_contam_so_utilizadores_activos(): void
    {
        $this->tenant->update(['max_users' => 2]);
        $this->tenant->activeSubscription?->plan?->update(['max_users' => 2]);

        $antes = $this->tenant->fresh()->utilizadoresQueContam();
        $desactivado = $this->juntarUtilizador(false);

        // Uma conta desactivada não ocupa vaga.
        $this->assertSame($antes, $this->tenant->fresh()->utilizadoresQueContam());

        // Reactivar pelo lado do TENANT, de propósito: `User::tenants()` tem
        // `wherePivot('is_active', true)` embutido, por isso um
        // updateExistingPivot por esse lado nunca alcança uma linha inactiva —
        // a própria condição da relação exclui-a, e a escrita passa em
        // silêncio. Armadilha a lembrar sempre que se reactivar alguém.
        $desactivado->update(['is_active' => true]);
        $this->tenant->users()->updateExistingPivot($desactivado->id, ['is_active' => true]);
        $this->assertSame($antes + 1, $this->tenant->fresh()->utilizadoresQueContam());
    }

    public function test_convite_aceite_respeita_o_limite(): void
    {
        // Enche a empresa até ao tecto.
        $this->tenant->update(['max_users' => 1]);
        $this->tenant->activeSubscription?->plan?->update(['max_users' => 1]);
        $t = $this->tenant->fresh()->load('activeSubscription.plan');
        $this->assertFalse($t->cabeMaisUmUtilizador(), 'a empresa devia estar cheia');

        $convite = \App\Models\UserInvitation::create([
            'tenant_id'  => $this->tenant->id,
            'invited_by' => $this->user->id,
            'email'      => 'novo' . uniqid() . '@x.com',
            'name'       => 'Novo',
            'role'       => 'user',
        ]);

        $antes = User::count();

        $this->post('/invitation/' . $convite->token . '/accept', [
            'password' => 'segredo123',
            'password_confirmation' => 'segredo123',
        ]);

        // O furo: antes disto, a conta era criada na mesma.
        $this->assertSame($antes, User::count(), 'o convite furou o limite de utilizadores');
    }
}
