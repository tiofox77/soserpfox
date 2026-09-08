<?php

namespace Tests\Feature\HR;

use App\Models\HR\Employee;
use App\Models\HR\Leave;
use App\Models\HR\Overtime;
use App\Models\HR\SalaryAdvance;
use App\Models\HR\SalaryDiscount;
use App\Models\HR\Vacation;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * OS SEIS PEDIDOS DO RH, na API que os seis ecrãs partilham.
 *
 * O que estes ensaios guardam:
 *
 * 1. QUE APROVAR É UMA PERMISSÃO À PARTE. Quem pede as suas férias não é quem
 *    as autoriza — e antes desta migração nenhuma das rotas tinha permissão
 *    nenhuma.
 *
 * 2. QUE A DECISÃO FICA ESCRITA: quem aprovou, quando, e — quando recusou —
 *    porquê. Um pedido decidido sem se saber por quem não serve de prova.
 *
 * 3. QUE UM PEDIDO SÓ SE DECIDE UMA VEZ. Aprovar duas vezes reescrevia a data
 *    e apagava quem tinha decidido antes.
 *
 * 4. QUE AS CONTAS SÃO DOS SERVIÇOS. O direito a férias, o multiplicador da
 *    hora extra e o tecto do adiantamento vivem no `VacationService`, no
 *    `OvertimeService` e no `SalaryAdvanceService` — a API chama, não copia.
 */
class ApiDosPedidosDeRhTest extends TenantTestCase
{
    private function raiz(string $tipo, string $cauda = ''): string
    {
        return "/api/v1/invoicing/react/rh/pedidos/{$tipo}{$cauda}";
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('rh');
    }

    private function funcionario(array $por = []): Employee
    {
        return Employee::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'EMP-' . substr((string) (microtime(true) * 10000), -7),
            'first_name' => 'Ana',
            'last_name' => 'Bento',
            'hire_date' => now()->subYears(2)->toDateString(),
            'status' => 'active',
            'salary' => 200000,
            'base_salary' => 200000,
        ], $por));
    }

    /** Um pedido já criado, para os ensaios da decisão. */
    private function licenca(array $por = []): Leave
    {
        return Leave::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->funcionario()->id,
            'leave_number' => 'LIC-' . substr((string) (microtime(true) * 10000), -7),
            'leave_type' => 'sick',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'total_days' => 3,
            'working_days' => 3,
            'reason' => 'Atestado médico de três dias.',
            'status' => 'pending',
        ], $por));
    }

    /* ─── A guarda que não existia ─────────────────────────────────────── */

    /** @test */
    public function sem_permissao_nao_se_ve_nem_se_pede(): void
    {
        foreach (['ferias', 'licencas', 'horas-extras', 'turno-nocturno', 'adiantamentos', 'descontos'] as $tipo) {
            $this->getJson($this->raiz($tipo, '/opcoes'))->assertForbidden();
            $this->getJson($this->raiz($tipo))->assertForbidden();
            $this->postJson($this->raiz($tipo), [])->assertForbidden();
        }
    }

    /**
     * QUEM PEDE NÃO APROVA.
     *
     * É a permissão que separa as duas pessoas, e a razão de `approve` ser um
     * verbo próprio e não «editar».
     *
     * @test
     */
    public function quem_pede_nao_aprova(): void
    {
        $this->comPermissoes('hr.leaves.view', 'hr.leaves.create');

        $l = $this->licenca();

        $r = $this->getJson($this->raiz('licencas', '/opcoes'))->assertOk();

        $this->assertTrue($r->json('permissoes.pode_criar'));
        $this->assertFalse($r->json('permissoes.pode_aprovar'));

        $this->postJson($this->raiz('licencas', "/{$l->id}/aprovar"))->assertForbidden();
        $this->postJson($this->raiz('licencas', "/{$l->id}/rejeitar"), ['rejection_reason' => 'Não pode ser assim.'])->assertForbidden();
    }

    /** Um tipo que não existe é 404 — não se inventa um pedido. @test */
    public function um_tipo_desconhecido_e_404(): void
    {
        $this->comPermissoes('hr.leaves.view');

        $this->getJson($this->raiz('xpto', '/opcoes'))->assertNotFound();
    }

    /* ─── Criar ────────────────────────────────────────────────────────── */

    /** @test */
    public function uma_licenca_grava_com_os_dias_uteis_contados_pelo_servico(): void
    {
        $this->comPermissoes('hr.leaves.view', 'hr.leaves.create');

        $e = $this->funcionario();

        $r = $this->postJson($this->raiz('licencas'), [
            'employee_id' => $e->id,
            'leave_type' => 'bereavement',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'reason' => 'Falecimento de familiar directo.',
        ])->assertCreated();

        $this->assertSame('pending', $r->json('documento.estado'));
        $this->assertStringStartsWith('LIC-', $r->json('documento.numero'));

        $l = Leave::findOrFail($r->json('documento.id'));

        $this->assertSame($e->id, (int) $l->employee_id);
        $this->assertSame(3, (int) $l->total_days, 'o LeaveService conta a diferença, não os dias inclusive');
        $this->assertGreaterThan(0, $l->working_days, 'os dias úteis vêm do LeaveService');
    }

    /** O motivo tem de explicar alguma coisa: dez caracteres. @test */
    public function um_motivo_curto_nao_passa(): void
    {
        $this->comPermissoes('hr.leaves.view', 'hr.leaves.create');

        $this->postJson($this->raiz('licencas'), [
            'employee_id' => $this->funcionario()->id,
            'leave_type' => 'sick',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'reason' => 'gripe',
        ])->assertStatus(422)->assertJsonValidationErrors('reason');
    }

    /** O fim não pode ser antes do início. @test */
    public function o_fim_nao_e_antes_do_inicio(): void
    {
        $this->comPermissoes('hr.leaves.view', 'hr.leaves.create');

        $this->postJson($this->raiz('licencas'), [
            'employee_id' => $this->funcionario()->id,
            'leave_type' => 'sick',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'reason' => 'Uma coisa qualquer bem escrita.',
        ])->assertStatus(422)->assertJsonValidationErrors('end_date');
    }

    /**
     * O FUNCIONÁRIO DO PEDIDO É DESTA EMPRESA.
     *
     * Um `exists:hr_employees,id` aceitava o de outra, e o adiantamento saía
     * calculado sobre o salário de uma empresa vizinha.
     *
     * @test
     */
    public function um_funcionario_de_outra_empresa_nao_entra(): void
    {
        $this->comPermissoes('hr.leaves.view', 'hr.leaves.create');

        $outra = Tenant::create(['name' => 'Vizinha', 'slug' => 'viz-' . uniqid(), 'is_active' => true]);
        $deFora = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'employee_number' => 'X1',
            'first_name' => 'Fora', 'last_name' => 'Daqui', 'status' => 'active',
        ]);

        $this->postJson($this->raiz('licencas'), [
            'employee_id' => $deFora->id,
            'leave_type' => 'sick',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'reason' => 'Uma coisa qualquer bem escrita.',
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');
    }

    /**
     * A RECUSA DO SERVIÇO É 422 COM A FRASE DELE, e não um 500.
     *
     * «Funcionário não tem dias de férias suficientes» é uma regra de negócio:
     * o ecrã tem de a mostrar tal e qual a quem está a marcar as férias.
     *
     * @test
     */
    public function a_regra_do_servico_chega_ao_ecra_como_esta_escrita(): void
    {
        $this->comPermissoes('hr.vacations.view', 'hr.vacations.create');

        $e = $this->funcionario(['hire_date' => now()->subMonth()->toDateString()]);

        // Um mês de casa não dá vinte e dois dias de férias.
        $r = $this->postJson($this->raiz('ferias'), [
            'employee_id' => $e->id,
            'reference_year' => (int) now()->format('Y'),
            'vacation_type' => 'normal',
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->addDays(30)->toDateString(),
        ])->assertStatus(422);

        $this->assertStringContainsString('férias', mb_strtolower($r->json('errors.regra.0') ?? ''));
    }

    /* ─── Decidir ──────────────────────────────────────────────────────── */

    /**
     * APROVAR ESCREVE QUEM E QUANDO.
     *
     * Um pedido aprovado sem se saber por quem não serve de prova a ninguém.
     *
     * @test
     */
    public function aprovar_grava_quem_decidiu_e_quando(): void
    {
        $this->comPermissoes('hr.leaves.view', 'hr.leaves.approve');

        $l = $this->licenca();

        $r = $this->postJson($this->raiz('licencas', "/{$l->id}/aprovar"))->assertOk();

        $this->assertSame('approved', $r->json('documento.estado'));
        $this->assertSame($this->user->name, $r->json('documento.decisao.aprovado_por'));
        $this->assertNotNull($r->json('documento.decisao.aprovado_em'));

        $l->refresh();

        $this->assertSame($this->user->id, (int) $l->approved_by);
        $this->assertNotNull($l->approved_at);
    }

    /** Um pedido só se decide UMA vez. @test */
    public function um_pedido_ja_decidido_nao_se_decide_outra_vez(): void
    {
        $this->comPermissoes('hr.leaves.view', 'hr.leaves.approve');

        $l = $this->licenca();

        $this->postJson($this->raiz('licencas', "/{$l->id}/aprovar"))->assertOk();

        $this->postJson($this->raiz('licencas', "/{$l->id}/aprovar"))
            ->assertStatus(422)->assertJsonValidationErrors('status');

        $this->postJson($this->raiz('licencas', "/{$l->id}/rejeitar"), ['rejection_reason' => 'Mudei de ideias agora.'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    /**
     * RECUSAR SEM MOTIVO NÃO PASSA.
     *
     * Uma recusa sem motivo é uma pessoa a perguntar porquê a quem já não se
     * lembra.
     *
     * @test
     */
    public function recusar_sem_motivo_nao_passa(): void
    {
        $this->comPermissoes('hr.leaves.view', 'hr.leaves.approve');

        $l = $this->licenca();

        $this->postJson($this->raiz('licencas', "/{$l->id}/rejeitar"), [])
            ->assertStatus(422)->assertJsonValidationErrors('rejection_reason');

        $this->postJson($this->raiz('licencas', "/{$l->id}/rejeitar"), ['rejection_reason' => 'não'])
            ->assertStatus(422)->assertJsonValidationErrors('rejection_reason');

        $r = $this->postJson($this->raiz('licencas', "/{$l->id}/rejeitar"), [
            'rejection_reason' => 'Já gozou os dias a que tinha direito este ano.',
        ])->assertOk();

        $this->assertSame('rejected', $r->json('documento.estado'));
        $this->assertSame('Já gozou os dias a que tinha direito este ano.', $r->json('documento.decisao.motivo_da_recusa'));
        $this->assertSame($this->user->name, $r->json('documento.decisao.recusado_por'));
    }

    /**
     * PAGAR SÓ DEPOIS DE APROVADO.
     *
     * Pagar um pedido que ninguém autorizou é dinheiro a sair sem decisão por
     * trás.
     *
     * @test
     */
    public function pagar_exige_aprovacao_primeiro(): void
    {
        $this->comPermissoes('hr.overtime.view', 'hr.overtime.approve');

        $h = Overtime::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->funcionario()->id,
            'overtime_number' => 'HE-' . substr((string) (microtime(true) * 10000), -7),
            'date' => now()->toDateString(),
            'total_hours' => 3,
            'hourly_rate' => 1000,
            'overtime_rate' => 1250,
            'total_amount' => 3750,
            'status' => 'pending',
        ]);

        $this->postJson($this->raiz('horas-extras', "/{$h->id}/pagar"))
            ->assertStatus(422)->assertJsonValidationErrors('status');

        $this->postJson($this->raiz('horas-extras', "/{$h->id}/aprovar"))->assertOk();
        $this->postJson($this->raiz('horas-extras', "/{$h->id}/pagar"))->assertOk();

        $this->assertSame('paid', $h->fresh()->status);
    }

    /**
     * O ADIANTAMENTO APROVA-SE POR UM VALOR — que pode ser menor do que o
     * pedido. É dele que sai o saldo e a prestação.
     *
     * @test
     */
    public function o_adiantamento_aprova_se_por_um_valor_menor(): void
    {
        $this->comPermissoes('hr.advances.view', 'hr.advances.approve');

        $a = SalaryAdvance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->funcionario()->id,
            'advance_number' => 'ADI-' . substr((string) (microtime(true) * 10000), -7),
            'requested_amount' => 200000,
            'base_salary' => 200000,
            'max_allowed' => 100000,
            'installments' => 4,
            'request_date' => now()->toDateString(),
            'reason' => 'Despesa de saúde da família.',
            'status' => 'pending',
        ]);

        // Sem o valor, não se aprova: aprovar isto É decidir quanto.
        $this->postJson($this->raiz('adiantamentos', "/{$a->id}/aprovar"), [])
            ->assertStatus(422)->assertJsonValidationErrors(['approved_amount', 'installment_amount']);

        $this->postJson($this->raiz('adiantamentos', "/{$a->id}/aprovar"), [
            'approved_amount' => 120000,
            'installment_amount' => 30000,
        ])->assertOk();

        $a->refresh();

        $this->assertSame('approved', $a->status);
        $this->assertEqualsWithDelta(120000, (float) $a->approved_amount, 0.01);
        $this->assertEqualsWithDelta(120000, (float) $a->balance, 0.01, 'o saldo nasce do valor APROVADO');
        $this->assertEqualsWithDelta(30000, (float) $a->installment_amount, 0.01);
    }

    /* ─── Eliminar e anular ────────────────────────────────────────────── */

    /**
     * UM PEDIDO JÁ DECIDIDO NÃO SE ELIMINA — anula-se.
     *
     * Apagá-lo deixava o histórico do funcionário com um buraco onde esteve
     * uma decisão.
     *
     * @test
     */
    public function um_pedido_decidido_nao_se_elimina(): void
    {
        $this->comPermissoes('hr.leaves.view', 'hr.leaves.approve', 'hr.leaves.delete');

        $l = $this->licenca();

        $this->postJson($this->raiz('licencas', "/{$l->id}/aprovar"))->assertOk();

        $this->deleteJson($this->raiz('licencas', "/{$l->id}"))
            ->assertStatus(422)->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('hr_leaves', ['id' => $l->id, 'deleted_at' => null]);

        // Mas anula-se, e fica no histórico.
        $this->postJson($this->raiz('licencas', "/{$l->id}/cancelar"), ['cancellation_reason' => 'Já não vai.'])->assertOk();

        $this->assertSame('cancelled', $l->fresh()->status);
    }

    /** Um pendente elimina-se. @test */
    public function um_pedido_pendente_elimina_se(): void
    {
        $this->comPermissoes('hr.leaves.view', 'hr.leaves.delete');

        $l = $this->licenca();

        $this->deleteJson($this->raiz('licencas', "/{$l->id}"))->assertOk();

        $this->assertSoftDeleted('hr_leaves', ['id' => $l->id]);
    }

    /* ─── As duas metades da tabela das horas extras ───────────────────── */

    /**
     * AS HORAS EXTRAS E O TURNO NOCTURNO PARTILHAM A TABELA — e cada ecrã só
     * vê a sua metade.
     *
     * Sem o filtro, o ecrã das horas extras mostrava as noites e o das noites
     * mostrava as horas: os mesmos registos contados duas vezes.
     *
     * @test
     */
    public function as_horas_extras_e_as_noites_nao_se_misturam(): void
    {
        $this->comPermissoes('hr.overtime.view');

        $e = $this->funcionario();

        $comuns = ['tenant_id' => $this->tenant->id, 'employee_id' => $e->id, 'date' => now()->toDateString(),
            'total_hours' => 2, 'hourly_rate' => 1000, 'overtime_rate' => 1250, 'total_amount' => 2500, 'status' => 'pending'];

        Overtime::create($comuns + ['overtime_number' => 'HE-1', 'is_night_shift' => false]);
        Overtime::create($comuns + ['overtime_number' => 'HE-2', 'is_night_shift' => true]);

        $extras = collect($this->getJson($this->raiz('horas-extras'))->assertOk()->json('data'));
        $noites = collect($this->getJson($this->raiz('turno-nocturno'))->assertOk()->json('data'));

        $this->assertCount(1, $extras);
        $this->assertSame('HE-1', $extras->first()['numero']);

        $this->assertCount(1, $noites);
        $this->assertSame('HE-2', $noites->first()['numero']);
    }

    /* ─── A lista ──────────────────────────────────────────────────────── */

    /**
     * AS CONTAGENS SÃO DE TODA A EMPRESA — «3 pendentes» é o número que diz a
     * quem aprova que tem trabalho à espera.
     *
     * @test
     */
    public function o_resumo_conta_por_estado(): void
    {
        $this->comPermissoes('hr.leaves.view');

        $this->licenca(['status' => 'pending']);
        $this->licenca(['status' => 'pending']);
        $this->licenca(['status' => 'approved']);

        $r = $this->getJson($this->raiz('licencas', '?por_pagina=5'))->assertOk();

        $porEstado = collect($r->json('resumo.por_estado'))->keyBy('valor');

        $this->assertSame(3, $r->json('resumo.total'));
        $this->assertSame(2, $porEstado['pending']['quantos']);
        $this->assertSame(1, $porEstado['approved']['quantos']);
        $this->assertSame(0, $porEstado['cancelled']['quantos']);
    }

    /** Filtrar por estado e por funcionário. @test */
    public function os_filtros_estreitam_a_lista(): void
    {
        $this->comPermissoes('hr.leaves.view');

        $quem = $this->funcionario();

        $this->licenca(['employee_id' => $quem->id]);
        $this->licenca(['status' => 'approved']);

        $this->assertCount(1, $this->getJson($this->raiz('licencas', '?estado=approved'))->assertOk()->json('data'));
        $this->assertCount(1, $this->getJson($this->raiz('licencas', '?funcionario=' . $quem->id))->assertOk()->json('data'));
        $this->assertCount(2, $this->getJson($this->raiz('licencas'))->assertOk()->json('data'));
    }

    /** Um pedido de outra empresa não aparece nem se toca. @test */
    public function um_pedido_de_outra_empresa_nao_se_toca(): void
    {
        $this->comPermissoes('hr.leaves.view', 'hr.leaves.approve', 'hr.leaves.delete');

        $outra = Tenant::create(['name' => 'Vizinha', 'slug' => 'viz-' . uniqid(), 'is_active' => true]);
        $quemDeFora = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'employee_number' => 'X-FORA',
            'first_name' => 'Fora', 'last_name' => 'Daqui', 'status' => 'active',
        ]);
        $deFora = Leave::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'employee_id' => $quemDeFora->id, 'leave_number' => 'LIC-FORA',
            'leave_type' => 'sick', 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
            'total_days' => 1, 'working_days' => 1, 'reason' => 'Uma coisa qualquer.', 'status' => 'pending',
        ]);

        $this->getJson($this->raiz('licencas', "/{$deFora->id}"))->assertNotFound();
        $this->postJson($this->raiz('licencas', "/{$deFora->id}/aprovar"))->assertNotFound();
        $this->deleteJson($this->raiz('licencas', "/{$deFora->id}"))->assertNotFound();

        $ids = collect($this->getJson($this->raiz('licencas'))->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($deFora->id));
    }

    /* ─── O desconto salarial ──────────────────────────────────────────── */

    /** A prestação sai da divisão, e o contador nasce cheio. @test */
    public function o_desconto_reparte_se_pelas_prestacoes(): void
    {
        $this->comPermissoes('hr.discounts.view', 'hr.discounts.create');

        $id = $this->postJson($this->raiz('descontos'), [
            'employee_id' => $this->funcionario()->id,
            'discount_type' => 'dano',
            'request_date' => now()->toDateString(),
            'amount' => 90000,
            'installments' => 3,
            'reason' => 'Dano em equipamento da empresa.',
        ])->assertCreated()->json('documento.id');

        $d = SalaryDiscount::findOrFail($id);

        $this->assertEqualsWithDelta(30000, (float) $d->installment_amount, 0.01);
        $this->assertSame(3, (int) $d->remaining_installments);
        $this->assertSame('pending', $d->status);
    }

    /** O desconto não se paga por aqui: desconta-se na folha. @test */
    public function o_desconto_nao_se_paga_por_aqui(): void
    {
        $this->comPermissoes('hr.discounts.view', 'hr.discounts.approve');

        $d = SalaryDiscount::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->funcionario()->id,
            'discount_type' => 'dano',
            'request_date' => now()->toDateString(),
            'amount' => 10000,
            'installments' => 1,
            'installment_amount' => 10000,
            'remaining_installments' => 1,
            'reason' => 'Uma coisa qualquer.',
            'status' => 'approved',
        ]);

        $this->postJson($this->raiz('descontos', "/{$d->id}/pagar"))->assertNotFound();
    }

    /* ─── As férias, de ponta a ponta ──────────────────────────────────── */

    /** @test */
    public function umas_ferias_pedem_se_aprovam_se_e_pagam_se(): void
    {
        $this->comPermissoes('hr.vacations.view', 'hr.vacations.create', 'hr.vacations.approve');

        $e = $this->funcionario(['hire_date' => now()->subYears(3)->toDateString()]);

        $id = $this->postJson($this->raiz('ferias'), [
            'employee_id' => $e->id,
            'reference_year' => (int) now()->format('Y'),
            'vacation_type' => 'normal',
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->addDays(6)->toDateString(),
            'notes' => 'Uma semana em Agosto.',
        ])->assertCreated()->json('documento.id');

        $v = Vacation::findOrFail($id);

        $this->assertSame('pending', $v->status);
        $this->assertGreaterThan(0, (float) $v->total_amount, 'o subsídio vem do VacationService');

        $this->postJson($this->raiz('ferias', "/{$id}/aprovar"))->assertOk();
        $this->postJson($this->raiz('ferias', "/{$id}/pagar"))->assertOk();

        $v->refresh();

        /*
         * NAS FÉRIAS O ESTADO NÃO PASSA A `paid` — o `enum` da coluna nem tem
         * esse valor. O que marca o pagamento é a bandeira `paid`, e é o
         * `Vacation::markAsPaid()` que sabe disso; escrever o estado à mão
         * dava um 1265 do MySQL em cima do utilizador.
         */
        $this->assertSame('approved', $v->status);
        $this->assertTrue((bool) $v->paid);
        $this->assertNotNull($v->paid_date);
        $this->assertSame($this->user->id, (int) $v->paid_by);
    }
}
