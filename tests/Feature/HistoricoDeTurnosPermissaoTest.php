<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Pos\ShiftHistory;
use App\Models\Invoicing\PosShift;
use App\Models\User;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Histórico de Turnos: cada caixa vê os SEUS turnos.
 *
 * O relatório do POS protegia as vendas de cada operador atrás de
 * `invoicing.pos.reports.all`, mas este ecrã mostrava a mesma informação —
 * quanto cada um vendeu, o que abriu e fechou de caixa, e a diferença — a
 * qualquer utilizador que lá entrasse. Era a porta das traseiras do
 * relatório.
 */
class HistoricoDeTurnosPermissaoTest extends TenantTestCase
{
    private function colega(): User
    {
        $colega = User::create([
            'name' => 'Colega '.uniqid(),
            'email' => uniqid().'@exemplo.ao',
            'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id,
        ]);

        $colega->tenants()->syncWithoutDetaching([$this->tenant->id]);

        return $colega;
    }

    private function turno(int $userId, float $vendas = 50000): PosShift
    {
        return PosShift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $userId,
            'shift_number' => 'T'.random_int(100000, 999999),
            'status' => 'closed',
            'opened_at' => now()->subHours(8),
            'closed_at' => now(),
            'opening_balance' => 10000,
            'total_sales' => $vendas,
            'cash_difference' => -2500,
        ]);
    }

    /** @test */
    public function um_caixa_so_ve_os_seus_turnos(): void
    {
        $colega = $this->colega();
        $meu = $this->turno($this->user->id, 11111);
        $dele = $this->turno($colega->id, 99999);

        $this->comPermissoes('invoicing.pos.reports');
        $this->actingAs($this->user);

        $ecra = Livewire::test(ShiftHistory::class);

        $vistos = $ecra->viewData('shifts')->pluck('id')->all();

        $this->assertContains($meu->id, $vistos);
        $this->assertNotContains($dele->id, $vistos, 'o turno do colega apareceu na lista');
        $ecra->assertDontSee($colega->name);
    }

    /** @test */
    public function com_o_direito_de_ver_todos_ve_os_dois(): void
    {
        $colega = $this->colega();
        $meu = $this->turno($this->user->id);
        $dele = $this->turno($colega->id);

        $this->comPermissoes('invoicing.pos.reports', 'invoicing.pos.reports.all');
        $this->actingAs($this->user);

        $vistos = Livewire::test(ShiftHistory::class)->viewData('shifts')->pluck('id')->all();

        $this->assertContains($meu->id, $vistos);
        $this->assertContains($dele->id, $vistos);
    }

    /**
     * O filtro não dá a volta à permissão: escolher o nome de um colega na
     * lista era o caminho mais curto para ver os turnos dele.
     *
     * @test
     */
    public function escolher_outro_operador_no_filtro_nao_abre_nada(): void
    {
        $colega = $this->colega();
        $dele = $this->turno($colega->id);
        $this->turno($this->user->id);

        $this->comPermissoes('invoicing.pos.reports');
        $this->actingAs($this->user);

        $vistos = Livewire::test(ShiftHistory::class)
            ->set('userId', $colega->id)
            ->viewData('shifts')->pluck('id')->all();

        $this->assertNotContains($dele->id, $vistos);
    }

    /** O detalhe pelo id também não: era ali que se viam os movimentos de caixa. */
    public function test_o_detalhe_de_um_turno_alheio_da_404(): void
    {
        $colega = $this->colega();
        $dele = $this->turno($colega->id);

        $this->comPermissoes('invoicing.pos.reports');
        $this->actingAs($this->user);

        Livewire::test(ShiftHistory::class)
            ->call('viewDetails', $dele->id)
            ->assertStatus(404)
            ->assertSet('showDetailModal', false);
    }

    /** Quem pode ver todos abre o detalhe de qualquer turno. */
    public function test_quem_ve_todos_abre_o_detalhe(): void
    {
        $colega = $this->colega();
        $dele = $this->turno($colega->id);

        $this->comPermissoes('invoicing.pos.reports', 'invoicing.pos.reports.all');
        $this->actingAs($this->user);

        Livewire::test(ShiftHistory::class)
            ->call('viewDetails', $dele->id)
            ->assertSet('showDetailModal', true);
    }

    /** A lista de colegas some para quem só vê os seus. */
    public function test_sem_o_direito_nao_ha_lista_de_operadores(): void
    {
        $this->colega();

        $this->comPermissoes('invoicing.pos.reports');
        $this->actingAs($this->user);

        $ecra = Livewire::test(ShiftHistory::class);

        $this->assertTrue($ecra->viewData('users')->isEmpty());
        $ecra->assertSee('A mostrar apenas os seus turnos.');
    }
}
