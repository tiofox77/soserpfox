<?php

namespace Tests\Feature;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\SalesProforma;
use App\Models\Invoicing\SalesQuote;
use App\Models\User;
use App\Services\Invoicing\TiposDeDocumento;
use Tests\TenantTestCase;

/**
 * A lista genérica que serve CINCO documentos.
 *
 * O risco de um controlador genérico é ser genérico também nas permissões: uma
 * rota só que serve cinco tabelas é uma rota só que as pode abrir todas. Estes
 * ensaios provam que não — cada tipo exige a SUA permissão, e o escopo por
 * autor continua a valer em todos.
 */
class ApiDosDocumentosParaReactTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function rota(string $tipo): string
    {
        return "/api/v1/invoicing/react/documentos/{$tipo}";
    }

    private function orcamento(array $por = []): SalesQuote
    {
        return SalesQuote::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'quote_number' => 'ORC/' . random_int(1000, 9999),
            'quote_date' => now()->toDateString(),
            'status' => 'draft',
            'total' => 100,
            'created_by' => $this->user->id,
        ], $por));
    }

    private function fornecedor(): \App\Models\Supplier
    {
        return \App\Models\Supplier::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Fornecedor de Ensaio'],
            ['nif' => (string) random_int(500000000, 599999999), 'is_active' => true]
        );
    }

    private function compra(array $por = []): PurchaseInvoice
    {
        return PurchaseInvoice::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->fornecedor()->id,
            'invoice_number' => 'FC/' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'status' => 'pending',
            'total' => 5000,
            'paid_amount' => 0,
            'created_by' => $this->user->id,
        ], $por));
    }

    /** @test */
    public function um_tipo_inventado_da_404(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $this->getJson($this->rota('facturas-secretas'))->assertNotFound();
    }

    /**
     * CADA TIPO EXIGE A SUA PERMISSÃO.
     *
     * Quem pode ver proformas de venda não passa a ver facturas de compra por
     * a rota ser a mesma.
     *
     * @test
     */
    public function a_permissao_e_por_tipo_e_nao_pela_rota(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $this->getJson($this->rota('proformas-venda'))->assertOk();

        foreach (['orcamentos', 'facturas-compra', 'proformas-compra', 'recibos'] as $outro) {
            $this->getJson($this->rota($outro))->assertForbidden();
            $this->getJson($this->rota($outro) . '/opcoes')->assertForbidden();
        }
    }

    /** Os cinco tipos do registo respondem, cada um com a sua permissão. @test */
    public function os_cinco_tipos_respondem(): void
    {
        foreach (TiposDeDocumento::todos() as $slug => $def) {
            $this->comPermissoes($def['permissao']);

            $this->getJson($this->rota($slug))
                ->assertOk()
                ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);

            $this->getJson($this->rota($slug) . '/opcoes')
                ->assertOk()
                ->assertJsonPath('parte', $def['parte'])
                ->assertJsonPath('tem_saldo', $def['tem_saldo']);
        }
    }

    /** O escopo por autor vale aqui também. @test */
    public function quem_so_ve_os_seus_nao_ve_os_do_colega(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $colega = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $meu = $this->orcamento();
        $dele = $this->orcamento(['created_by' => $colega->id]);

        $ids = collect($this->getJson($this->rota('orcamentos'))->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($meu->id));
        $this->assertFalse($ids->contains($dele->id), 'o orçamento do colega não pode aparecer');
    }

    /** A factura de compra é a única com saldo, e mostra o que FALTA. @test */
    public function so_a_factura_de_compra_traz_saldo(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.view', 'invoicing.sales.proformas.view');

        $f = $this->compra(['total' => 5000, 'paid_amount' => 2000]);

        $linha = collect($this->getJson($this->rota('facturas-compra'))->json('data'))
            ->firstWhere('id', $f->id);

        $this->assertEqualsWithDelta(3000, $linha['saldo'], 0.01, 'mostra o que falta, não o total');

        SalesProforma::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'proforma_number' => 'PRF/' . random_int(1000, 9999),
            'proforma_date' => now()->toDateString(),
            'status' => 'draft',
            'total' => 100,
            'created_by' => $this->user->id,
        ]);

        $daProforma = collect($this->getJson($this->rota('proformas-venda'))->json('data'))->first();

        $this->assertArrayNotHasKey('saldo', $daProforma, 'uma proforma não tem pagamentos');
    }

    /** Uma compra paga não fica a dever, seja qual for o `paid_amount`. @test */
    public function uma_compra_paga_nao_aparece_a_dever(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.view');

        $f = $this->compra(['status' => 'paid', 'total' => 5000, 'paid_amount' => 0]);

        $linha = collect($this->getJson($this->rota('facturas-compra'))->json('data'))
            ->firstWhere('id', $f->id);

        $this->assertSame(0, (int) $linha['saldo']);
    }

    /** Os estados oferecidos no filtro são os que a tabela TEM. @test */
    public function os_estados_do_filtro_saem_dos_dados(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $this->orcamento(['status' => 'accepted']);

        $estados = collect($this->getJson($this->rota('orcamentos') . '/opcoes')->json('estados'))
            ->pluck('valor');

        $this->assertTrue($estados->contains('accepted'));
        $this->assertFalse($estados->contains('overdue'), 'um orçamento não vence');
    }

    /** A procura encontra pelo número e pelo nome da outra parte. @test */
    public function a_procura_apanha_o_numero_e_a_outra_parte(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $o = $this->orcamento(['quote_number' => 'ORC PROCURA/0001']);

        $ids = collect($this->getJson($this->rota('orcamentos') . '?procura=PROCURA')->json('data'))
            ->pluck('id');

        $this->assertTrue($ids->contains($o->id));

        $porCliente = collect(
            $this->getJson($this->rota('orcamentos') . '?procura=' . urlencode($o->client->name))->json('data')
        )->pluck('id');

        $this->assertTrue($porCliente->contains($o->id));
    }
}
