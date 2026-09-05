<?php

namespace Tests\Feature\Projetos;

use App\Models\Projetos\HoraLancada;
use App\Models\Projetos\Projeto;
use App\Models\Projetos\Tarefa;
use App\Services\Invoicing\ModuleInvoiceService;
use App\Services\Projetos\FluxoDoProjeto;
use App\Services\Projetos\RegistoDeHoras;
use Tests\TenantTestCase;

/**
 * Projetos: horas, orçamento e facturação.
 *
 * O que estes ensaios prendem: o preço da hora CONGELA no lançamento, e uma
 * hora facturada não se mexe nem se factura duas vezes.
 */
class ProjetosTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->comModulo('projetos');
    }

    private function projeto(array $extra = []): Projeto
    {
        $p = app(FluxoDoProjeto::class)->criar($this->tenant->id, $this->user->id, array_merge([
            'nome' => 'Portal do cliente',
            'client_id' => $this->clienteEmpresa()->id,
            'orcamento' => 100000,
            'valor_hora' => 5000,
        ], $extra));

        return app(FluxoDoProjeto::class)->mudarEstado($p, $this->tenant->id, 'activo');
    }

    private function lancar(Projeto $p, float $horas, array $extra = []): HoraLancada
    {
        return app(RegistoDeHoras::class)->lancar($this->tenant->id, $this->user->id, array_merge([
            'projeto_id' => $p->id,
            'horas' => $horas,
            'data' => now()->toDateString(),
        ], $extra));
    }

    /** @test */
    public function o_codigo_e_por_empresa(): void
    {
        $p = $this->projeto();

        $this->assertStringStartsWith('PRJ-'.now()->year.'-', $p->codigo);
        $this->assertSame('activo', $p->estado);
    }

    /**
     * O PREÇO CONGELA NO LANÇAMENTO.
     *
     * Mudar a tabela de preços do projeto não pode reescrever o que já foi
     * trabalhado — senão uma actualização mexia em facturas por emitir.
     *
     * @test
     */
    public function mudar_o_preco_do_projeto_nao_reescreve_as_horas_ja_lancadas(): void
    {
        $p = $this->projeto();
        $antiga = $this->lancar($p, 10);

        $this->assertSame(5000.0, (float) $antiga->valor_hora);

        app(FluxoDoProjeto::class)->actualizar($p, $this->tenant->id, [
            'nome' => $p->nome,
            'client_id' => $p->client_id,
            'orcamento' => $p->orcamento,
            'valor_hora' => 9000,
        ]);

        $nova = $this->lancar($p->fresh(), 5);

        $this->assertSame(5000.0, (float) $antiga->fresh()->valor_hora, 'A hora antiga tem de manter o preço do dia.');
        $this->assertSame(9000.0, (float) $nova->valor_hora, 'A hora nova segue o preço novo.');

        // Consumido = 10×5000 + 5×9000
        $this->assertSame(95000.0, $p->fresh()->consumido());
    }

    /** @test */
    public function o_consumido_e_a_percentagem_saem_das_horas(): void
    {
        $p = $this->projeto(['orcamento' => 50000]);
        $this->lancar($p, 4);   // 4 × 5000 = 20 000

        $p = $p->fresh();

        $this->assertSame(20000.0, $p->consumido());
        $this->assertSame(40.0, $p->percentagemDoOrcamento());
        $this->assertFalse($p->acimaDoOrcamento());

        $this->lancar($p, 8);   // +40 000 => 60 000 de 50 000

        $this->assertTrue($p->fresh()->acimaDoOrcamento());
    }

    /** Sem orçamento não há percentagem — «0%» seria mentira. */
    public function test_projeto_sem_orcamento_nao_tem_percentagem(): void
    {
        $p = $this->projeto(['orcamento' => null]);
        $this->lancar($p, 3);

        $this->assertNull($p->fresh()->percentagemDoOrcamento());
    }

    /** Um projeto concluído não recebe horas novas. */
    public function test_projeto_fechado_nao_aceita_horas(): void
    {
        $p = $this->projeto();
        app(FluxoDoProjeto::class)->mudarEstado($p, $this->tenant->id, 'concluido');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Concluído/');

        $this->lancar($p->fresh(), 2);
    }

    /** Um dia não tem 25 horas — um engano de tecla custa dinheiro. */
    public function test_lancamento_absurdo_e_recusado(): void
    {
        $p = $this->projeto();

        $this->expectException(\InvalidArgumentException::class);
        $this->lancar($p, 30);
    }

    /** Uma tarefa de outro projeto não recebe estas horas. */
    public function test_tarefa_de_outro_projeto_e_recusada(): void
    {
        $a = $this->projeto();
        $b = $this->projeto(['nome' => 'Outro projeto']);

        $tarefaDeB = Tarefa::create([
            'tenant_id' => $this->tenant->id, 'projeto_id' => $b->id,
            'titulo' => 'Tarefa do B', 'estado' => 'por_fazer', 'created_by' => $this->user->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/não é deste projeto/');

        $this->lancar($a, 2, ['tarefa_id' => $tarefaDeB->id]);
    }

    /**
     * O ENSAIO QUE MAIS IMPORTA.
     *
     * Facturar leva as horas por facturar e marca-as. Uma segunda tentativa
     * não encontra nada — é a marca, e não um estado do projeto, que torna a
     * dupla facturação impossível.
     *
     * @test
     */
    public function facturar_horas_marca_as_linhas_e_nao_factura_duas_vezes(): void
    {
        $p = $this->projeto();
        $this->lancar($p, 6);
        $this->lancar($p, 4);

        $fluxo = app(FluxoDoProjeto::class);
        $this->assertSame(50000.0, $fluxo->porFacturar($p->fresh())['valor']);

        $factura = $fluxo->facturarHoras($p->fresh(), $this->tenant->id, app(ModuleInvoiceService::class));

        $this->assertNotNull($factura->invoice_number);
        $this->assertSame('projetos', $factura->source_module);
        $this->assertSame($p->codigo, $factura->source_reference);

        // Todas as horas ficaram marcadas com esta factura.
        $this->assertSame(0, HoraLancada::where('projeto_id', $p->id)->whereNull('facturado_em')->count());
        $this->assertSame(2, HoraLancada::where('projeto_id', $p->id)
            ->where('sales_invoice_id', $factura->id)->count());

        // E já não há nada por facturar.
        $this->assertSame(0.0, $fluxo->porFacturar($p->fresh())['valor']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Não há horas por facturar/');
        $fluxo->facturarHoras($p->fresh(), $this->tenant->id, app(ModuleInvoiceService::class));
    }

    /** Uma hora facturada é história: não se edita nem se apaga. */
    public function test_hora_facturada_nao_se_mexe(): void
    {
        $p = $this->projeto();
        $linha = $this->lancar($p, 3);

        app(FluxoDoProjeto::class)->facturarHoras($p->fresh(), $this->tenant->id, app(ModuleInvoiceService::class));

        $registo = app(RegistoDeHoras::class);
        $linha = $linha->fresh();

        $this->assertTrue($linha->jaFacturada());

        try {
            $registo->actualizar($linha, $this->tenant->id, ['horas' => 8]);
            $this->fail('Devia ter recusado a edição.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('já foi facturada', $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $registo->apagar($linha, $this->tenant->id);
    }

    /** As horas NÃO facturáveis ficam de fora da factura. */
    public function test_horas_nao_facturaveis_nao_vao_a_factura(): void
    {
        $p = $this->projeto();
        $this->lancar($p, 5);
        $naoFacturavel = $this->lancar($p, 3, ['facturavel' => false]);

        $fluxo = app(FluxoDoProjeto::class);

        $this->assertSame(25000.0, $fluxo->porFacturar($p->fresh())['valor']);

        $fluxo->facturarHoras($p->fresh(), $this->tenant->id, app(ModuleInvoiceService::class));

        $this->assertNull($naoFacturavel->fresh()->facturado_em, 'A hora não facturável fica por marcar.');
    }

    /** Sem cliente não há a quem facturar — e diz-se porquê. */
    public function test_projeto_interno_nao_se_factura(): void
    {
        $p = $this->projeto(['client_id' => null]);
        $this->lancar($p, 4);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/não tem cliente/');

        app(FluxoDoProjeto::class)->facturarHoras($p->fresh(), $this->tenant->id, app(ModuleInvoiceService::class));
    }

    /** Um projeto de outra empresa não se toca. */
    public function test_projeto_de_outra_empresa_e_recusado(): void
    {
        $p = $this->projeto();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/outra empresa/');

        app(FluxoDoProjeto::class)->mudarEstado($p, $this->tenant->id + 99999, 'em_pausa');
    }

    /** Reabrir um projeto limpa a data de fecho. */
    public function test_reabrir_limpa_a_data_de_fecho(): void
    {
        $fluxo = app(FluxoDoProjeto::class);
        $p = $this->projeto();

        $p = $fluxo->mudarEstado($p, $this->tenant->id, 'concluido');
        $this->assertNotNull($p->data_fim_real);

        $p = $fluxo->mudarEstado($p, $this->tenant->id, 'activo');
        $this->assertNull($p->data_fim_real);
    }

    /** A data de fim não pode ser anterior à de início. */
    public function test_datas_incoerentes_sao_recusadas(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(FluxoDoProjeto::class)->criar($this->tenant->id, $this->user->id, [
            'nome' => 'Impossível',
            'data_inicio' => '2026-06-01',
            'data_fim_prevista' => '2026-05-01',
        ]);
    }
    /**
     * O LAÇO FECHADO: depois de facturar, o projeto sabe dizer o que cobrou.
     *
     * Sem isto o módulo perdia o rasto — facturava-se e a pergunta «o que já
     * cobrei deste projeto?» não tinha resposta em lado nenhum.
     */
    public function test_o_projeto_mostra_o_que_ja_facturou(): void
    {
        $p = $this->projeto();
        $this->lancar($p, 6);
        $this->lancar($p, 4);

        // Antes de facturar não há nada cobrado.
        $this->assertSame(0.0, $p->fresh()->facturado());
        $this->assertSame(0.0, $p->fresh()->horasFacturadas());
        $this->assertTrue($p->fresh()->facturas()->isEmpty());

        $factura = app(FluxoDoProjeto::class)
            ->facturarHoras($p->fresh(), $this->tenant->id, app(ModuleInvoiceService::class));

        $depois = $p->fresh();

        $this->assertSame(50000.0, $depois->facturado());
        $this->assertSame(10.0, $depois->horasFacturadas());

        $facturas = $depois->facturas();
        $this->assertCount(1, $facturas);
        $this->assertSame($factura->id, $facturas->first()->id);
        $this->assertSame($factura->invoice_number, $facturas->first()->invoice_number);
    }

    /** Horas por facturar não contam como facturadas. */
    public function test_o_facturado_e_o_por_facturar_nao_se_misturam(): void
    {
        $p = $this->projeto();
        $this->lancar($p, 6);

        app(FluxoDoProjeto::class)->facturarHoras($p->fresh(), $this->tenant->id, app(ModuleInvoiceService::class));

        // Mais trabalho depois da factura: fica por facturar, não facturado.
        $this->lancar($p->fresh(), 2);

        $depois = $p->fresh();

        $this->assertSame(30000.0, $depois->facturado());
        $this->assertSame(10000.0, app(FluxoDoProjeto::class)->porFacturar($depois)['valor']);
        $this->assertSame(40000.0, $depois->consumido(), 'o consumido é tudo o que se trabalhou');
    }
}
