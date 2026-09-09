<?php

namespace Tests\Feature\Workshop;

use App\Models\HR\Employee;
use App\Models\Workshop\Mechanic;
use App\Models\Workshop\Service;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use Tests\TenantTestCase;

/**
 * O PAINEL E OS CINCO MAPAS DA OFICINA.
 *
 * O que estes ensaios guardam, para além de «abre e responde»:
 *
 *  1. O MECÂNICO É O DA OFICINA. `mechanic_id` aponta para
 *     `workshop_mechanics` desde 2025-11-05, a caixa de escolha do ecrã sempre
 *     ofereceu mecânicos da oficina — e o painel lia `users`, o mapa lia
 *     `hr_employees` e as relações do modelo liam `hr_employees`. O nome que
 *     aparecia era o de OUTRA PESSOA com o mesmo número, sem erro nenhum a
 *     dizê-lo. É a defeito mais caro deste módulo e tem aqui a sua trave.
 *
 *  2. O INTERVALO INCLUI O ÚLTIMO DIA. As colunas são DATETIME e os mapas
 *     comparavam com datas secas: o trabalho de hoje não contava.
 *
 *  3. O DINHEIRO SÓ SAI A QUEM PODE VER RELATÓRIOS — e não sai mesmo: o Blade
 *     escondia-o com «•••» no HTML e mandava o número na mesma.
 *
 *  4. O PAPEL E O EXCEL EXISTEM. Os dois botões respondiam «Funcionalidade de
 *     exportação em desenvolvimento».
 */
class PainelEMapasDaOficinaTest extends TenantTestCase
{
    private const PAINEL = '/api/v1/invoicing/react/oficina/painel';
    private const MAPAS = '/api/v1/invoicing/react/oficina/relatorios';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    /* ─── O painel ────────────────────────────────────────────────────── */

    public function test_o_painel_conta_as_ordens_do_periodo_e_o_que_esta_na_bancada(): void
    {
        $this->comPermissoes('workshop.dashboard.view');

        $viatura = $this->viatura();

        $this->ordem($viatura, ['status' => 'pending', 'received_at' => now()->subDays(2)]);
        $this->ordem($viatura, ['status' => 'in_progress', 'received_at' => now()->subDays(1)]);
        $this->ordem($viatura, ['status' => 'completed', 'received_at' => now(), 'completed_at' => now()]);
        // Esta é de um mês antes: fica fora do período, mas continua na bancada.
        $this->ordem($viatura, ['status' => 'scheduled', 'received_at' => now()->subMonths(2)]);

        $r = $this->getJson(self::PAINEL . '?de=' . now()->startOfMonth()->toDateString() . '&ate=' . now()->toDateString())
            ->assertOk();

        $this->assertSame(3, $r->json('cartoes.ordens'), 'só as que entraram no período');
        // As pendentes/agendadas contam-se SEM período: estão na bancada hoje.
        $this->assertSame(2, $r->json('cartoes.pendentes'));
        $this->assertSame(1, $r->json('cartoes.em_curso'));
        $this->assertSame(1, $r->json('cartoes.concluidas'));
    }

    /**
     * O ÚLTIMO DIA CONTA.
     *
     * `received_at` é DATETIME; com a data seca no limite de cima, uma ordem
     * das 15h de hoje ficava de fora do «até hoje».
     */
    public function test_o_ultimo_dia_do_periodo_conta(): void
    {
        $this->comPermissoes('workshop.dashboard.view');

        $this->ordem($this->viatura(), ['received_at' => now()->startOfDay()->addHours(15)]);

        $r = $this->getJson(self::PAINEL . '?de=' . now()->toDateString() . '&ate=' . now()->toDateString())->assertOk();

        $this->assertSame(1, $r->json('cartoes.ordens'));
    }

    /**
     * A CARGA POR MECÂNICO É DOS MECÂNICOS DA OFICINA.
     *
     * O painel lia a tabela `users`. Este ensaio cria um funcionário do RH com
     * o MESMO número de um mecânico para que uma leitura à tabela errada não
     * possa passar por acaso.
     */
    public function test_a_carga_e_dos_mecanicos_da_oficina_e_nao_do_rh(): void
    {
        $this->comPermissoes('workshop.dashboard.view');

        $mecanico = Mechanic::create(['name' => 'Zeca da Oficina', 'phone' => '923000001', 'specialties' => ['Motor']]);
        $this->funcionarioComId($mecanico->id, 'Outra', 'Pessoa');

        $this->ordem($this->viatura(), ['status' => 'in_progress', 'mechanic_id' => $mecanico->id]);

        $serie = $this->getJson(self::PAINEL)->assertOk()->json('series.mecanicos');

        $this->assertSame(['Zeca da Oficina'], $serie['etiquetas']);
        $this->assertSame([1], $serie['valores']);
    }

    /** Sem mecânico, a ordem conta na mesma — e diz-se «Por atribuir». */
    public function test_uma_ordem_sem_mecanico_aparece_por_atribuir(): void
    {
        $this->comPermissoes('workshop.dashboard.view');

        $this->ordem($this->viatura(), ['status' => 'pending', 'mechanic_id' => null]);

        $serie = $this->getJson(self::PAINEL)->assertOk()->json('series.mecanicos');

        $this->assertSame([__('Por atribuir')], $serie['etiquetas']);
    }

    /**
     * QUEM NÃO PODE VER RELATÓRIOS NÃO RECEBE DINHEIRO NENHUM.
     *
     * O Blade trocava o número por «•••» no HTML — o valor ia na mesma para o
     * browser, a um `Ctrl+U` de distância.
     */
    public function test_sem_permissao_de_relatorios_o_dinheiro_nao_sai_do_servidor(): void
    {
        $this->comPermissoes('workshop.dashboard.view');

        $this->ordem($this->viatura(), ['status' => 'completed', 'payment_status' => 'paid', 'total' => 250000]);

        $r = $this->getJson(self::PAINEL)->assertOk();

        $this->assertFalse($r->json('ve_dinheiro'));
        $this->assertNull($r->json('dinheiro'));
        $this->assertStringNotContainsString('250000', $r->getContent());
    }

    public function test_com_permissao_de_relatorios_o_dinheiro_sai(): void
    {
        $this->comPermissoes('workshop.dashboard.view', 'workshop.reports.view');

        $this->ordem($this->viatura(), ['status' => 'completed', 'payment_status' => 'paid', 'total' => 250000]);
        $this->ordem($this->viatura(), ['status' => 'delivered', 'payment_status' => 'pending', 'total' => 90000]);

        $r = $this->getJson(self::PAINEL)->assertOk();

        $this->assertTrue($r->json('ve_dinheiro'));
        $this->assertEquals(250000, $r->json('dinheiro.facturado'));
        $this->assertEquals(90000, $r->json('dinheiro.a_receber'));
    }

    /**
     * OS DOCUMENTOS A CADUCAR DIZEM QUAL — e quantos dias faltam.
     *
     * O ecrã em Blade juntava as três datas num parágrafo corrido: o seguro
     * caducado lia-se igual à inspecção que caduca daqui a três semanas.
     */
    public function test_os_documentos_a_caducar_dizem_qual_e_quantos_dias(): void
    {
        $this->comPermissoes('workshop.dashboard.view');

        $this->viatura([
            'plate' => 'LD-99-99-XX',
            'insurance_expiry' => now()->subDays(5),
            'inspection_expiry' => now()->addDays(10),
            // Este está em dia e por isso NÃO deve aparecer na lista da viatura.
            'registration_expiry' => now()->addYear(),
        ]);

        $lista = $this->getJson(self::PAINEL)->assertOk()->json('documentos_a_caducar');

        $this->assertCount(1, $lista);
        $this->assertSame('LD-99-99-XX', $lista[0]['matricula']);

        $nomes = array_column($lista[0]['documentos'], 'nome');
        $this->assertContains(__('Seguro'), $nomes);
        $this->assertContains(__('Inspecção'), $nomes);
        $this->assertNotContains(__('Livrete'), $nomes, 'o que está em dia não é um aviso');

        $seguro = collect($lista[0]['documentos'])->firstWhere('nome', __('Seguro'));
        $this->assertLessThan(0, $seguro['dias'], 'o que já passou tem dias negativos');
    }

    public function test_o_painel_pede_a_sua_permissao(): void
    {
        $this->getJson(self::PAINEL)->assertForbidden();
    }

    /* ─── Os mapas ────────────────────────────────────────────────────── */

    public function test_os_cinco_mapas_respondem_com_colunas_e_linhas(): void
    {
        $this->comPermissoes('workshop.reports.view');

        $this->comAlgumTrabalho();

        foreach (['servicos', 'receita', 'viaturas', 'mecanicos', 'ordens'] as $qual) {
            $r = $this->getJson(self::MAPAS . '?mapa=' . $qual)->assertOk();

            $this->assertNotEmpty($r->json('colunas'), "{$qual}: sem colunas");
            $this->assertNotEmpty($r->json('linhas'), "{$qual}: sem linhas");
            $this->assertNotNull($r->json('totais'), "{$qual}: sem totais");

            // Toda a coluna declarada existe na linha — senão a tabela mostra
            // uma coluna vazia e ninguém sabe porquê.
            foreach ($r->json('colunas') as $c) {
                $this->assertArrayHasKey($c['chave'], $r->json('linhas.0'),
                    "{$qual}: a coluna {$c['chave']} não vem nas linhas");
            }
        }
    }

    public function test_um_mapa_desconhecido_e_recusado(): void
    {
        $this->comPermissoes('workshop.reports.view');

        $this->getJson(self::MAPAS . '?mapa=inventado')->assertStatus(422);
    }

    /**
     * O MAPA DE MECÂNICOS É DOS MECÂNICOS DA OFICINA — e mostra quem não teve
     * trabalho nenhum, que é justamente a linha que interessa a quem o abre.
     */
    public function test_o_mapa_de_mecanicos_le_a_oficina_e_mostra_quem_nao_teve_trabalho(): void
    {
        $this->comPermissoes('workshop.reports.view');

        $ocupado = Mechanic::create(['name' => 'Zeca Ocupado', 'phone' => '923000001', 'specialties' => ['Motor']]);
        $parado = Mechanic::create(['name' => 'Tó Parado', 'phone' => '923000002', 'specialties' => ['Chapa']]);

        // Um funcionário do RH com o mesmo número: se o mapa lesse a tabela
        // errada, era este nome que saía.
        $this->funcionarioComId($ocupado->id, 'Outra', 'Pessoa');

        $this->ordem($this->viatura(), ['mechanic_id' => $ocupado->id, 'status' => 'completed', 'total' => 50000]);

        $linhas = collect($this->getJson(self::MAPAS . '?mapa=mecanicos')->assertOk()->json('linhas'));

        $this->assertSame(['Zeca Ocupado', 'Tó Parado'], $linhas->pluck('nome')->all());
        $this->assertSame(1, $linhas->firstWhere('nome', 'Zeca Ocupado')['ordens']);
        $this->assertSame(0, $linhas->firstWhere('nome', 'Tó Parado')['ordens']);
        $this->assertNotContains('Outra Pessoa', $linhas->pluck('nome')->all());
    }

    /** E o mapa de ordens escreve o mecânico da oficina, não o do RH. */
    public function test_o_mapa_de_ordens_escreve_o_mecanico_da_oficina(): void
    {
        $this->comPermissoes('workshop.reports.view');

        $m = Mechanic::create(['name' => 'Zeca da Oficina', 'phone' => '923000001', 'specialties' => ['Motor']]);
        $this->funcionarioComId($m->id, 'Outra', 'Pessoa');

        $this->ordem($this->viatura(), ['mechanic_id' => $m->id]);

        $linha = $this->getJson(self::MAPAS . '?mapa=ordens')->assertOk()->json('linhas.0');

        $this->assertSame('Zeca da Oficina', $linha['mecanico']);
    }

    /** O filtro de estado só existe no mapa de ordens, e funciona. */
    public function test_o_mapa_de_ordens_filtra_por_estado(): void
    {
        $this->comPermissoes('workshop.reports.view');

        $v = $this->viatura();
        $this->ordem($v, ['status' => 'completed']);
        $this->ordem($v, ['status' => 'cancelled']);

        $todas = $this->getJson(self::MAPAS . '?mapa=ordens')->assertOk()->json('linhas');
        $so = $this->getJson(self::MAPAS . '?mapa=ordens&estado=completed')->assertOk()->json('linhas');

        $this->assertCount(2, $todas);
        $this->assertCount(1, $so);
        $this->assertSame('completed', $so[0]['estado']);
        // E o estado vem por extenso: `completed` numa tabela não é resposta.
        $this->assertSame(__('Concluída'), $so[0]['estado_rotulo']);
    }

    /** As opções dizem quais os mapas e qual deles aceita filtro de estado. */
    public function test_as_opcoes_dizem_quem_pede_estado(): void
    {
        $this->comPermissoes('workshop.reports.view');

        $mapas = collect($this->getJson(self::MAPAS . '/opcoes')->assertOk()->json('mapas'));

        $this->assertCount(5, $mapas);
        $this->assertTrue($mapas->firstWhere('valor', 'ordens')['pede_estado']);
        $this->assertFalse($mapas->firstWhere('valor', 'receita')['pede_estado']);
    }

    public function test_os_mapas_pedem_a_sua_permissao(): void
    {
        $this->getJson(self::MAPAS . '/opcoes')->assertForbidden();
        $this->getJson(self::MAPAS . '?mapa=servicos')->assertForbidden();
    }

    /* ─── O papel e o Excel ───────────────────────────────────────────── */

    public function test_o_papel_sai_com_os_filtros_escritos(): void
    {
        $this->comPermissoes('workshop.reports.view');

        $this->comAlgumTrabalho();

        $this->get(route('workshop.reports.imprimir', ['mapa' => 'ordens', 'estado' => 'completed']))
            ->assertOk()
            ->assertSee(__('Ordens de Serviço'), false)
            // O cabeçalho diz o que se pediu — sem isso ninguém confere o mapa
            // daqui a uma semana.
            ->assertSee(__('Estado: :estado', ['estado' => __('Concluída')]), false);
    }

    public function test_o_papel_escreve_o_estado_por_extenso(): void
    {
        $this->comPermissoes('workshop.reports.view');

        $this->ordem($this->viatura(), ['status' => 'in_progress']);

        $html = $this->get(route('workshop.reports.imprimir', ['mapa' => 'ordens']))->assertOk()->getContent();

        $this->assertStringContainsString(__('Em curso'), $html);
        $this->assertStringNotContainsString('in_progress', $html);
    }

    public function test_o_excel_descarrega(): void
    {
        $this->comPermissoes('workshop.reports.view');

        $this->comAlgumTrabalho();

        $r = $this->get(route('workshop.reports.excel', ['mapa' => 'servicos']))->assertOk();

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $r->headers->get('Content-Type')
        );
        $this->assertStringContainsString('.xlsx', $r->headers->get('Content-Disposition'));
    }

    public function test_o_papel_e_o_excel_pedem_permissao(): void
    {
        $this->get(route('workshop.reports.imprimir', ['mapa' => 'servicos']))->assertForbidden();
        $this->get(route('workshop.reports.excel', ['mapa' => 'servicos']))->assertForbidden();
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function viatura(array $campos = []): Vehicle
    {
        return Vehicle::create(array_merge([
            'plate' => 'LD-' . random_int(10, 99) . '-' . random_int(10, 99) . '-AA',
            'vehicle_number' => 'VEH-' . substr(uniqid(), -5),
            'owner_name' => 'Dono', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active',
        ], $campos));
    }

    private function ordem(Vehicle $v, array $campos = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'order_number' => 'OS-' . substr(uniqid(), -6),
            'vehicle_id' => $v->id,
            'received_at' => now(),
            'problem_description' => 'Não pega.',
            'status' => 'pending',
        ], $campos));
    }

    /**
     * UM FUNCIONÁRIO DO RH COM UM NÚMERO ESCOLHIDO.
     *
     * Serve para provar que a leitura é à tabela certa: se o painel ou o mapa
     * lessem `hr_employees`, era este nome que saía.
     */
    private function funcionarioComId(int $id, string $nome, string $apelido): Employee
    {
        $e = Employee::create([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'EMP-' . substr(uniqid(), -5),
            'first_name' => $nome, 'last_name' => $apelido,
            'hire_date' => now()->subYear(), 'status' => 'active',
        ]);

        if ($e->id !== $id) {
            \Illuminate\Support\Facades\DB::table('hr_employees')->where('id', $e->id)->update(['id' => $id]);
            $e = Employee::find($id);
        }

        return $e;
    }

    /** Uma ordem com um serviço e uma peça — o mínimo para os cinco mapas. */
    private function comAlgumTrabalho(): WorkOrder
    {
        $mecanico = Mechanic::create(['name' => 'Zeca Mota', 'phone' => '923000009', 'specialties' => ['Motor']]);
        $servico = Service::create(['service_code' => 'SRV-1', 'name' => 'Mudança de óleo', 'labor_cost' => 15000]);

        $ordem = $this->ordem($this->viatura(), [
            'mechanic_id' => $mecanico->id,
            'status' => 'completed',
            'completed_at' => now(),
            'labor_total' => 15000, 'parts_total' => 5000, 'total' => 20000,
            'payment_status' => 'paid',
        ]);

        WorkOrderItem::create([
            'work_order_id' => $ordem->id, 'service_id' => $servico->id, 'type' => 'service',
            'name' => 'Mudança de óleo', 'quantity' => 1, 'unit_price' => 15000, 'subtotal' => 15000,
        ]);

        WorkOrderItem::create([
            'work_order_id' => $ordem->id, 'type' => 'part',
            'name' => 'Filtro de óleo', 'quantity' => 1, 'unit_price' => 5000, 'subtotal' => 5000,
        ]);

        return $ordem;
    }
}
