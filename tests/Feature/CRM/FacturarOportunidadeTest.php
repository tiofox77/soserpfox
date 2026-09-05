<?php

namespace Tests\Feature\CRM;

use App\Livewire\CRM\Oportunidades;
use App\Models\Client;
use App\Models\CRM\Opportunity;
use App\Models\CRM\Stage;
use App\Services\CRM\FacturarOportunidade;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O laço do CRM: o negócio ganho vira documento, e o funil fica a saber.
 *
 * O CRM levava o negócio até «ganho» e parava. Quem ganhava lia «vá à
 * Facturação», procurava o cliente e escrevia o valor outra vez — duas
 * verdades sobre o mesmo negócio, que divergem à primeira correcção. E o
 * funil dizia quanto se GANHOU, nunca quanto se COBROU.
 *
 * O que estes ensaios prendem:
 *   1. a factura sai pela porta única e nasce em rascunho, com o valor do negócio;
 *   2. a oportunidade guarda o documento — e é essa marca que impede facturar duas vezes;
 *   3. as recusas dizem PORQUÊ (sem ganhar, sem cliente, sem valor).
 */
class FacturarOportunidadeTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('crm.view', 'crm.opportunities.view', 'crm.opportunities.manage',
            'invoicing.sales.invoices.create', 'invoicing.sales.invoices.view');
    }

    private function cliente(): Client
    {
        return Client::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente '.uniqid(),
            'email' => uniqid().'@cliente.ao',
        ]);
    }

    private function oportunidade(array $extra = []): Opportunity
    {
        $etapa = Stage::where('tenant_id', $this->tenant->id)->first()
            ?? Stage::create([
                'tenant_id' => $this->tenant->id, 'name' => 'Proposta',
                'sort_order' => 1, 'probability' => 50, 'is_active' => true,
            ]);

        return Opportunity::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'title' => 'Instalação de rede',
            'stage_id' => $etapa->id,
            'client_id' => $this->cliente()->id,
            'amount' => 250000,
            'probability' => 100,
            'status' => 'won',
            'closed_at' => now(),
            'created_by' => $this->user->id,
        ], $extra));
    }

    /** @test */
    public function o_negocio_ganho_vira_factura_em_rascunho(): void
    {
        $o = $this->oportunidade();

        $factura = app(FacturarOportunidade::class)->facturar($o, $this->tenant->id);

        $this->assertNotEmpty($factura->invoice_number);
        $this->assertSame('draft', $factura->status, 'nasce em rascunho, para conferir');
        $this->assertSame($o->client_id, $factura->client_id);
        $this->assertSame('crm', $factura->source_module);
        $this->assertSame('OPO-'.$o->id, $factura->source_reference);

        // Uma linha, com o título do negócio e o valor acordado.
        $linhas = $factura->items;
        $this->assertCount(1, $linhas);
        $this->assertSame('Instalação de rede', $linhas->first()->product_name);
        $this->assertSame(250000.0, (float) $linhas->first()->unit_price);
    }

    /** @test */
    public function a_oportunidade_guarda_o_documento(): void
    {
        $o = $this->oportunidade();

        $factura = app(FacturarOportunidade::class)->facturar($o, $this->tenant->id);
        $depois = $o->fresh();

        $this->assertSame($factura->id, $depois->sales_invoice_id);
        $this->assertNotNull($depois->facturada_em);
        $this->assertSame($factura->invoice_number, $depois->factura->invoice_number);
    }

    /**
     * A marca — e não o estado — é o que impede facturar duas vezes.
     *
     * @test
     */
    public function nao_se_factura_duas_vezes_o_mesmo_negocio(): void
    {
        $o = $this->oportunidade();

        app(FacturarOportunidade::class)->facturar($o, $this->tenant->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/já foi facturado/');

        app(FacturarOportunidade::class)->facturar($o->fresh(), $this->tenant->id);
    }

    /** Um negócio ainda aberto não dá documento. */
    public function test_so_se_factura_o_que_esta_ganho(): void
    {
        $o = $this->oportunidade(['status' => 'open', 'closed_at' => null]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/negócio ganho/');

        app(FacturarOportunidade::class)->facturar($o, $this->tenant->id);
    }

    /** Sem cliente não há a quem facturar — e diz-se porquê. */
    public function test_sem_cliente_recusa_e_explica(): void
    {
        $o = $this->oportunidade(['client_id' => null]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/não tem cliente/');

        app(FacturarOportunidade::class)->facturar($o, $this->tenant->id);
    }

    /** Um negócio sem valor não dá documento. */
    public function test_sem_valor_recusa(): void
    {
        $o = $this->oportunidade(['amount' => 0]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/sem valor/');

        app(FacturarOportunidade::class)->facturar($o, $this->tenant->id);
    }

    /** Uma oportunidade de OUTRA empresa é recusada. */
    public function test_oportunidade_de_outra_empresa_e_recusada(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Outra '.uniqid(), 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999), 'email' => uniqid().'@x.ao',
        ]);

        $o = $this->oportunidade();
        $o->forceFill(['tenant_id' => $outra->id])->save();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/não é desta empresa/');

        app(FacturarOportunidade::class)->facturar($o->fresh(), $this->tenant->id);
    }

    /** O ecrã factura e diz o número; sem permissão de emitir, não factura. */
    public function test_o_ecra_gera_a_factura(): void
    {
        $o = $this->oportunidade();

        Livewire::test(Oportunidades::class)
            ->set('filtroEstado', 'won')
            ->call('facturar', $o->id)
            ->assertHasNoErrors();

        $this->assertNotNull($o->fresh()->sales_invoice_id);
    }

    /** O painel separa o ganho do cobrado. */
    public function test_o_painel_diz_quanto_ja_foi_facturado(): void
    {
        $facturada = $this->oportunidade();
        $this->oportunidade(['title' => 'Por facturar', 'amount' => 90000]);

        app(FacturarOportunidade::class)->facturar($facturada, $this->tenant->id);

        $resumo = Livewire::test(\App\Livewire\CRM\Dashboard::class)->viewData('resumo');

        $this->assertSame(340000.0, $resumo['ganho_mes'], 'ganho é tudo o que fechou');
        $this->assertSame(250000.0, $resumo['facturado_mes'], 'facturado é só o que virou documento');
        $this->assertSame(1, $resumo['por_facturar_mes']);
    }
}
