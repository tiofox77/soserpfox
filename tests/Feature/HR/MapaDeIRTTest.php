<?php

namespace Tests\Feature\HR;

use App\Livewire\HR\MapaDeIRT as EcraMapaDeIRT;
use App\Models\HR\Employee;
use App\Models\HR\Payroll;
use App\Models\HR\PayrollItem;
use App\Services\HR\MapaDeIRT;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O mapa de IRT: o imposto retido aos trabalhadores num mês, na forma em que
 * se declara e se paga à AGT.
 */
class MapaDeIRTTest extends TenantTestCase
{
    private function ligarModuloRh(): void
    {
        $modulo = \App\Models\Module::firstOrCreate(
            ['slug' => 'rh'],
            ['name' => 'Recursos Humanos', 'is_active' => true]
        );

        $this->tenant->modules()->syncWithoutDetaching([
            $modulo->id => ['is_active' => true, 'trial_ends_at' => null],
        ]);
    }

    private function trabalhador(string $nome, ?string $nif = null): Employee
    {
        // hr_employees tem indice unico (tenant_id, nif): dois trabalhadores
        // nao podem partilhar NIF, tal como na vida real.
        $nif = $nif ?? (string) random_int(5000000000, 5999999999);

        [$proprio, $apelido] = array_pad(explode(' ', $nome, 2), 2, '');

        return Employee::create([
            'tenant_id'       => $this->tenant->id,
            'employee_number' => 'F' . random_int(1000, 9999) . uniqid(),
            'first_name'      => $proprio,
            'last_name'       => $apelido,
            'full_name'       => $nome,
            'nif'             => $nif,
            'status'          => 'active',
        ]);
    }

    private function folha(int $ano, int $mes, string $estado = 'approved'): Payroll
    {
        return Payroll::create([
            'tenant_id'      => $this->tenant->id,
            'payroll_number' => 'FP-' . uniqid(),
            'year'           => $ano,
            'month'          => $mes,
            'period_start'   => \Carbon\Carbon::create($ano, $mes, 1)->startOfMonth(),
            'period_end'     => \Carbon\Carbon::create($ano, $mes, 1)->endOfMonth(),
            'status'         => $estado,
        ]);
    }

    private function linha(Payroll $folha, Employee $t, float $bruto, float $inss, float $base, float $irt): PayrollItem
    {
        return PayrollItem::create([
            'payroll_id'    => $folha->id,
            'employee_id'   => $t->id,
            'base_salary'   => $bruto,
            'gross_salary'  => $bruto,
            'inss_employee' => $inss,
            'irt_base'      => $base,
            'irt_amount'    => $irt,
            'irt_rate'      => $base > 0 ? round($irt / $base * 100, 2) : 0,
            'net_salary'    => $bruto - $inss - $irt,
        ]);
    }

    // ── O mapa ───────────────────────────────────────────────────────────

    public function test_soma_o_irt_retido_no_mes(): void
    {
        $folha = $this->folha(2026, 5);
        $this->linha($folha, $this->trabalhador('Ana Silva'), 300000, 9000, 291000, 25000);
        $this->linha($folha, $this->trabalhador('Bruno Costa'), 200000, 6000, 194000, 8000);

        $mapa = app(MapaDeIRT::class)->paraMes($this->tenant->id, 2026, 5);

        $this->assertCount(2, $mapa['linhas']);
        $this->assertEqualsWithDelta(33000, $mapa['totais']['irt'], 0.01);
        $this->assertEqualsWithDelta(500000, $mapa['totais']['bruto'], 0.01);
        $this->assertEqualsWithDelta(485000, $mapa['totais']['base'], 0.01);
        $this->assertSame('05/2026', $mapa['periodo']);
    }

    /**
     * Um rascunho ainda vai mudar e uma folha anulada não reteve nada.
     * Declarar qualquer uma seria declarar imposto que não foi retido.
     */
    public function test_rascunho_e_anulada_nao_entram(): void
    {
        $rascunho = $this->folha(2026, 5, 'draft');
        $this->linha($rascunho, $this->trabalhador('Ana Silva'), 300000, 9000, 291000, 25000);

        $mapa = app(MapaDeIRT::class)->paraMes($this->tenant->id, 2026, 5);

        $this->assertCount(0, $mapa['linhas']);
        $this->assertEqualsWithDelta(0, $mapa['totais']['irt'], 0.01);
        // Mas é MOSTRADA: um rascunho por aprovar é a razão nº1 de o mapa não
        // bater certo, e descobrir isso depois de entregar é tarde.
        $this->assertCount(1, $mapa['ignoradas']);
    }

    public function test_folha_paga_conta_tal_como_a_aprovada(): void
    {
        $paga = $this->folha(2026, 5, 'paid');
        $this->linha($paga, $this->trabalhador('Ana Silva'), 300000, 9000, 291000, 25000);

        $mapa = app(MapaDeIRT::class)->paraMes($this->tenant->id, 2026, 5);

        $this->assertCount(1, $mapa['linhas']);
        $this->assertEqualsWithDelta(25000, $mapa['totais']['irt'], 0.01);
    }

    /**
     * Cada trabalhador aparece UMA vez, com os seus números — nunca duas
     * entradas com dois IRT para a mesma pessoa.
     *
     * O esquema já ajuda (hr_payrolls é único por empresa/ano/mês e
     * hr_payroll_items é único por folha/trabalhador), mas o mapa é o que se
     * entrega: vale a pena fixar que sai uma linha por pessoa e que o nome sai
     * por ordem alfabética, que é como se confere um mapa a olho.
     */
    public function test_uma_linha_por_trabalhador_e_por_ordem_de_nome(): void
    {
        $folha = $this->folha(2026, 12);
        $this->linha($folha, $this->trabalhador('Carlos Mendes'), 400000, 12000, 388000, 40000);
        $this->linha($folha, $this->trabalhador('Ana Silva'), 300000, 9000, 291000, 25000);
        $this->linha($folha, $this->trabalhador('Bruno Costa'), 200000, 6000, 194000, 8000);

        $mapa = app(MapaDeIRT::class)->paraMes($this->tenant->id, 2026, 12);

        $this->assertSame(
            ['Ana Silva', 'Bruno Costa', 'Carlos Mendes'],
            $mapa['linhas']->pluck('nome')->all()
        );
        $this->assertSame(3, $mapa['linhas']->pluck('employee_id')->unique()->count());
        $this->assertEqualsWithDelta(73000, $mapa['totais']['irt'], 0.01);
    }

    /** A taxa mostrada é a EFECTIVA — duas taxas de escalão não se somam. */
    public function test_taxa_e_a_efectiva_sobre_a_materia_colectavel(): void
    {
        $folha = $this->folha(2026, 5);
        $this->linha($folha, $this->trabalhador('Ana Silva'), 300000, 9000, 291000, 29100);

        $linha = app(MapaDeIRT::class)->paraMes($this->tenant->id, 2026, 5)['linhas']->first();

        $this->assertEqualsWithDelta(10.0, $linha['taxa'], 0.01);
    }

    public function test_isento_e_marcado_e_nao_conta_como_tributado(): void
    {
        $folha = $this->folha(2026, 5);
        $this->linha($folha, $this->trabalhador('Ana Silva'), 120000, 3600, 116400, 0);
        $this->linha($folha, $this->trabalhador('Bruno Costa'), 300000, 9000, 291000, 25000);

        $mapa = app(MapaDeIRT::class)->paraMes($this->tenant->id, 2026, 5);

        $this->assertSame(1, $mapa['totais']['isentos']);
        $this->assertSame(1, $mapa['totais']['tributados']);
        $this->assertTrue($mapa['linhas']->firstWhere('nome', 'Ana Silva')['isento']);
    }

    /** Outro mês não entra: o mapa é do período, não da empresa toda. */
    public function test_outro_mes_nao_entra(): void
    {
        $abril = $this->folha(2026, 4);
        $this->linha($abril, $this->trabalhador('Ana Silva'), 300000, 9000, 291000, 25000);

        $mapa = app(MapaDeIRT::class)->paraMes($this->tenant->id, 2026, 5);

        $this->assertCount(0, $mapa['linhas']);
    }

    /** Uma folha de outra empresa nunca pode entrar neste mapa. */
    public function test_folha_de_outra_empresa_nao_entra(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Vizinha', 'slug' => 'viz-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'v' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        Payroll::withoutEvents(function () use ($outra) {
            $folha = Payroll::create([
                'tenant_id' => $outra->id, 'payroll_number' => 'FP-VIZ-' . uniqid(),
                'year' => 2026, 'month' => 5,
                'period_start' => '2026-05-01', 'period_end' => '2026-05-31',
                'status' => 'approved',
            ]);

            $t = Employee::withoutEvents(fn () => Employee::create([
                'tenant_id' => $outra->id, 'employee_number' => 'V' . uniqid(),
                'first_name' => 'Alheio', 'last_name' => 'Vizinho', 'full_name' => 'Alheio Vizinho',
                'nif' => '5999999999', 'status' => 'active',
            ]));

            PayrollItem::create([
                'payroll_id' => $folha->id, 'employee_id' => $t->id,
                'base_salary' => 900000, 'gross_salary' => 900000, 'inss_employee' => 27000,
                'irt_base' => 873000, 'irt_amount' => 99999, 'irt_rate' => 11, 'net_salary' => 773001,
            ]);
        });

        $mapa = app(MapaDeIRT::class)->paraMes($this->tenant->id, 2026, 5);

        $this->assertCount(0, $mapa['linhas']);
        $this->assertEqualsWithDelta(0, $mapa['totais']['irt'], 0.01);
    }

    // ── CSV ──────────────────────────────────────────────────────────────

    /** Sem BOM, o Excel em Windows lê os acentos como lixo. */
    public function test_csv_leva_bom_e_os_numeros_todos(): void
    {
        $folha = $this->folha(2026, 5);
        $this->linha($folha, $this->trabalhador('Ana Conceição'), 300000, 9000, 291000, 25000);

        $mapa = app(MapaDeIRT::class)->paraMes($this->tenant->id, 2026, 5);
        $csv = app(MapaDeIRT::class)->csv($mapa, 'Empresa Teste', '5000000000');

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Ana Conceição', $csv);
        $this->assertStringContainsString('25000,00', $csv);
        $this->assertStringContainsString('05/2026', $csv);
    }

    // ── Ecrã ─────────────────────────────────────────────────────────────

    /** Abre no mês passado: é esse o que se está a preparar para entregar. */
    public function test_ecra_abre_no_mes_anterior(): void
    {
        $anterior = now()->subMonthNoOverflow();

        Livewire::test(EcraMapaDeIRT::class)
            ->assertSet('ano', (int) $anterior->year)
            ->assertSet('mes', (int) $anterior->month);
    }

    public function test_navegar_entre_meses_atravessa_o_ano(): void
    {
        Livewire::test(EcraMapaDeIRT::class)
            ->set('ano', 2026)->set('mes', 1)
            ->call('mesAnterior')
            ->assertSet('ano', 2025)->assertSet('mes', 12)
            ->call('mesSeguinte')
            ->assertSet('ano', 2026)->assertSet('mes', 1);
    }

    public function test_o_ecra_abre_por_http(): void
    {
        $this->ligarModuloRh();

        $this->get(route('hr.irt-map'))->assertOk();
    }

    public function test_a_impressao_abre_por_http(): void
    {
        $this->ligarModuloRh();
        $folha = $this->folha(2026, 5);
        $this->linha($folha, $this->trabalhador('Ana Silva'), 300000, 9000, 291000, 25000);

        $this->get(route('hr.irt-map.pdf', ['ano' => 2026, 'mes' => 5]))
            ->assertOk()
            ->assertSee('MAPA DE IRT', false)
            ->assertSee('Ana Silva', false);
    }

    public function test_o_csv_descarrega(): void
    {
        $this->ligarModuloRh();
        $folha = $this->folha(2026, 5);
        $this->linha($folha, $this->trabalhador('Ana Silva'), 300000, 9000, 291000, 25000);

        $this->get(route('hr.irt-map.csv', ['ano' => 2026, 'mes' => 5]))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="mapa_irt_2026_05.csv"');
    }

    /** Um mês 13 no URL faria o Carbon saltar de período sem se queixar. */
    public function test_periodo_invalido_e_recusado(): void
    {
        $this->ligarModuloRh();

        $this->get(route('hr.irt-map.csv', ['ano' => 2026, 'mes' => 13]))
            ->assertSessionHasErrors('mes');
    }
}
