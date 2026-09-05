<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesProforma;
use App\Models\User;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Listas de documentos: cada um vê os que emitiu.
 *
 * Fecha a porta do lado do relatório do POS. As vendas do POS acabam em
 * facturas, e a lista de Facturas de Venda mostrava-as todas a qualquer
 * utilizador com acesso ao módulo — um vendedor via as vendas dos colegas
 * com dois cliques.
 *
 * A regra vive num sítio só (App\Traits\DocumentosPorAutor) e vale para as
 * nove listas: facturas e proformas de venda e de compra, orçamentos, notas
 * de crédito e de débito, recibos e adiantamentos.
 */
class DocumentosPorAutorTest extends TenantTestCase
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

    private function cliente(): Client
    {
        return Client::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente '.uniqid(),
            'email' => uniqid().'@cliente.ao',
        ]);
    }

    private function factura(int $autor, string $numero): SalesInvoice
    {
        return SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente()->id,
            'invoice_number' => $numero,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'pending',
            'subtotal' => 1000,
            'total' => 1000,
            'created_by' => $autor,
        ]);
    }

    private function proforma(int $autor, string $numero): SalesProforma
    {
        return SalesProforma::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente()->id,
            'proforma_number' => $numero,
            'proforma_date' => now(),
            'status' => 'draft',
            'subtotal' => 500,
            'total' => 500,
            'created_by' => $autor,
        ]);
    }

    /** @test */
    public function um_vendedor_so_ve_as_facturas_que_emitiu(): void
    {
        $colega = $this->colega();
        $minha = $this->factura($this->user->id, 'FT MINHA/001');
        $dele = $this->factura($colega->id, 'FT DELE/002');

        $this->comPermissoes('invoicing.sales.invoices.view');
        $this->actingAs($this->user);

        $ecra = Livewire::test(\App\Livewire\Invoicing\Sales\Invoices::class);
        $vistas = $ecra->viewData('invoices')->pluck('id')->all();

        $this->assertContains($minha->id, $vistas);
        $this->assertNotContains($dele->id, $vistas, 'a factura do colega apareceu na lista');
        $ecra->assertDontSee('FT DELE/002');
    }

    /**
     * Os contadores no topo contam o mesmo que a lista mostra — senão o
     * vendedor via uma lista de 1 e um total de 200.
     *
     * @test
     */
    public function os_totais_do_topo_seguem_a_mesma_regra(): void
    {
        $colega = $this->colega();
        $this->factura($this->user->id, 'FT MINHA/010');
        $this->factura($colega->id, 'FT DELE/011');
        $this->factura($colega->id, 'FT DELE/012');

        $this->comPermissoes('invoicing.sales.invoices.view');
        $this->actingAs($this->user);

        $stats = Livewire::test(\App\Livewire\Invoicing\Sales\Invoices::class)->viewData('stats');

        $this->assertSame(1, $stats['total']);
        $this->assertSame(1000.0, (float) $stats['total_amount']);
    }

    /** @test */
    public function com_a_permissao_ve_as_de_todos(): void
    {
        $colega = $this->colega();
        $minha = $this->factura($this->user->id, 'FT MINHA/020');
        $dele = $this->factura($colega->id, 'FT DELE/021');

        $this->comPermissoes('invoicing.sales.invoices.view', 'invoicing.documents.all');
        $this->actingAs($this->user);

        $vistas = Livewire::test(\App\Livewire\Invoicing\Sales\Invoices::class)
            ->viewData('invoices')->pluck('id')->all();

        $this->assertContains($minha->id, $vistas);
        $this->assertContains($dele->id, $vistas);
    }

    /**
     * O filtro por autor é para quem pode ver todos. A quem não pode, não lhe
     * serve de atalho.
     *
     * @test
     */
    public function o_filtro_por_autor_nao_da_a_volta_a_permissao(): void
    {
        $colega = $this->colega();
        $dele = $this->factura($colega->id, 'FT DELE/030');
        $this->factura($this->user->id, 'FT MINHA/031');

        $this->comPermissoes('invoicing.sales.invoices.view');
        $this->actingAs($this->user);

        $ecra = Livewire::test(\App\Livewire\Invoicing\Sales\Invoices::class)
            ->set('autorId', $colega->id);

        $this->assertNotContains($dele->id, $ecra->viewData('invoices')->pluck('id')->all());
        $this->assertTrue($ecra->instance()->autoresDosDocumentos->isEmpty(),
            'a lista de colegas não devia estar disponível');
    }

    /** Com a permissão, o filtro escolhe mesmo o autor. */
    public function test_com_a_permissao_o_filtro_funciona(): void
    {
        $colega = $this->colega();
        $dele = $this->factura($colega->id, 'FT DELE/040');
        $minha = $this->factura($this->user->id, 'FT MINHA/041');

        $this->comPermissoes('invoicing.sales.invoices.view', 'invoicing.documents.all');
        $this->actingAs($this->user);

        $vistas = Livewire::test(\App\Livewire\Invoicing\Sales\Invoices::class)
            ->set('autorId', $colega->id)
            ->viewData('invoices')->pluck('id')->all();

        $this->assertContains($dele->id, $vistas);
        $this->assertNotContains($minha->id, $vistas);
    }

    /**
     * Pelo id também não: os botões da linha recebem um número e abriam
     * qualquer documento da empresa.
     */
    public function test_abrir_a_factura_de_um_colega_pelo_id_nao_mostra_nada(): void
    {
        $colega = $this->colega();
        $dele = $this->factura($colega->id, 'FT DELE/050');

        $this->comPermissoes('invoicing.sales.invoices.view');
        $this->actingAs($this->user);

        Livewire::test(\App\Livewire\Invoicing\Sales\Invoices::class)
            ->call('viewInvoice', $dele->id)
            ->assertSet('selectedInvoice', null);
    }

    /** A regra não é só das facturas: as proformas seguem-na na mesma. */
    public function test_as_proformas_seguem_a_mesma_regra(): void
    {
        $colega = $this->colega();
        $minha = $this->proforma($this->user->id, 'PF MINHA/001');
        $dela = $this->proforma($colega->id, 'PF DELE/002');

        $this->comPermissoes('invoicing.sales.proformas.view');
        $this->actingAs($this->user);

        $vistas = Livewire::test(\App\Livewire\Invoicing\Sales\Proformas::class)
            ->viewData('proformas')->pluck('id')->all();

        $this->assertContains($minha->id, $vistas);
        $this->assertNotContains($dela->id, $vistas);
    }

    /**
     * Um documento SEM autor é de ninguém, não é de um colega.
     *
     * As facturas e proformas de venda têm autor obrigatório na base, mas as
     * compras, notas, recibos e adiantamentos aceitam nulo — importações,
     * sincronização offline e dados antigos entram por aí. Escondê-los de
     * toda a gente tornava-os inalcançáveis, que é pior do que mostrá-los.
     */
    public function test_um_documento_sem_autor_continua_a_ver_se(): void
    {
        $fornecedor = \App\Models\Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fornecedor '.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'is_active' => true,
        ]);

        $orfa = \App\Models\Invoicing\PurchaseInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $fornecedor->id,
            'invoice_number' => 'FC ORFA/060',
            'invoice_date' => now()->toDateString(),
            'status' => 'draft',
            'subtotal' => 700,
            'total' => 700,
            'created_by' => null,
        ]);

        $this->comPermissoes('invoicing.purchases.invoices.view');
        $this->actingAs($this->user);

        $vistas = Livewire::test(\App\Livewire\Invoicing\Purchases\Invoices::class)
            ->viewData('invoices')->pluck('id')->all();

        $this->assertContains($orfa->id, $vistas, 'um documento sem autor não pode ficar inalcançável');
    }

    /** As nove listas usam mesmo a regra — nenhuma ficou de fora. */
    public function test_todas_as_listas_de_documentos_usam_a_regra(): void
    {
        $ecras = [
            \App\Livewire\Invoicing\Sales\Invoices::class,
            \App\Livewire\Invoicing\Sales\Proformas::class,
            \App\Livewire\Invoicing\Sales\Quotes::class,
            \App\Livewire\Invoicing\CreditNotes\CreditNotes::class,
            \App\Livewire\Invoicing\DebitNotes\DebitNotes::class,
            \App\Livewire\Invoicing\Receipts\Receipts::class,
            \App\Livewire\Invoicing\Advances\Advances::class,
            \App\Livewire\Invoicing\Purchases\Invoices::class,
            \App\Livewire\Invoicing\Purchases\Proformas::class,
        ];

        foreach ($ecras as $ecra) {
            $this->assertContains(
                \App\Traits\DocumentosPorAutor::class,
                class_uses_recursive($ecra),
                "{$ecra} não segue a regra de quem vê que documentos"
            );
        }
    }
}
