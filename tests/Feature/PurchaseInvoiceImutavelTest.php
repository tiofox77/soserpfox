<?php

namespace Tests\Feature;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Product;
use Illuminate\Support\Facades\Route;
use Tests\TenantTestCase;

/**
 * Uma factura de compra não se apaga — anula-se.
 *
 * O documento é do FORNECEDOR: deu entrada de stock, criou dívida a pagar e vai
 * para o SAFT-AO. Apagar a linha não desfaz nada disso; desfaz só a prova de
 * que aconteceu, e deixa o stock e as contas a apontar para um documento que já
 * não existe.
 *
 * O ECRÃ É AGORA EM REACT, e a garantia mudou de forma: onde o Livewire tinha
 * um `confirmDelete` que recusava e explicava, a API simplesmente NÃO TEM porta
 * por onde apagar. Uma porta que não existe é mais forte do que uma porta com
 * guarda — e é isso que o primeiro ensaio vigia, para que ninguém a abra por
 * distracção ao acrescentar um `Route::delete` ao grupo.
 *
 * E o que está registado também não se altera: a compra já deu entrada do
 * stock e o que se gravar por cima não a desfaz.
 */
class PurchaseInvoiceImutavelTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/compra';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes(
            'invoicing.purchases.invoices.view',
            'invoicing.purchases.invoices.create',
            'invoicing.purchases.invoices.delete',
        )->comModulo('invoicing');
    }

    private function artigo(): Product
    {
        return Product::create([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Artigo ' . uniqid(),
            'sku'          => 'SKU' . strtoupper(substr(uniqid(), -8)),
            'type'         => 'produto',
            'price'        => 1500,
            'cost'         => 0,
            'unit'         => 'UN',
            'tax_type'     => 'iva',
            'tax_rate_id'  => $this->imposto->id,
            'manage_stock' => true,
            'is_active'    => true,
        ]);
    }

    /** Regista uma compra pela API e devolve-a. */
    private function compra(string $estado, ?Product $artigo = null): PurchaseInvoice
    {
        $artigo = $artigo ?: $this->artigo();

        $id = $this->postJson(self::RAIZ, [
            'supplier_id'  => \App\Models\Supplier::create([
                'tenant_id' => $this->tenant->id,
                'name'      => 'Fornecedor ' . uniqid(),
                'nif'       => (string) random_int(500000000, 599999999),
                'is_active' => true,
            ])->id,
            'warehouse_id' => $this->armazem->id,
            'invoice_date' => now()->toDateString(),
            'status'       => $estado,
            'linhas'       => [['product_id' => $artigo->id, 'quantity' => 4, 'price' => 250]],
        ])->assertCreated()->json('id');

        return PurchaseInvoice::findOrFail($id);
    }

    /**
     * NÃO HÁ POR ONDE APAGAR — nem por engano.
     *
     * @test
     */
    public function a_api_dos_ecras_em_react_nao_apaga_facturas_de_compra(): void
    {
        $f = $this->compra('draft');

        // A porta não existe: o URI conhece-se (GET e PUT), o verbo não.
        $this->deleteJson(self::RAIZ . '/' . $f->id)->assertStatus(405);

        $this->assertDatabaseHas('invoicing_purchase_invoices', ['id' => $f->id]);

        // E nenhuma rota do grupo dos ecrãs novos apaga uma factura de compra,
        // seja qual for o endereço por onde alguém a acrescente.
        $apagadoras = collect(Route::getRoutes())->filter(function ($rota) {
            $accao = (string) ($rota->getAction('controller') ?? '');

            return in_array('DELETE', $rota->methods(), true)
                && str_starts_with($rota->uri(), 'api/v1/invoicing/react')
                && (str_contains($accao, 'CompraApiController') || str_contains($accao, 'DocumentosApiController'));
        });

        $this->assertCount(0, $apagadoras,
            'apareceu uma rota que apaga facturas de compra: ' . $apagadoras->map(fn ($r) => $r->uri())->implode(', '));
    }

    /**
     * UMA COMPRA REGISTADA NÃO SE ALTERA: o stock já entrou.
     *
     * Não basta a resposta ser 422 — o documento tem de ficar exactamente como
     * estava. Gravar por cima com metade das quantidades deixaria o stock a
     * apontar para números que o documento já não confirma.
     *
     * @test
     */
    public function uma_compra_registada_nao_se_altera(): void
    {
        $artigo = $this->artigo();
        $f = $this->compra('pending', $artigo);

        $antes = $f->only(['invoice_number', 'total', 'status']);
        $stockAntes = (float) $artigo->fresh()->stock_quantity;

        $this->putJson(self::RAIZ . '/' . $f->id, [
            'supplier_id'  => $f->supplier_id,
            'warehouse_id' => $this->armazem->id,
            'invoice_date' => now()->toDateString(),
            'linhas'       => [['product_id' => $artigo->id, 'quantity' => 1, 'price' => 1]],
        ])->assertStatus(422);

        $depois = $f->fresh();

        $this->assertSame($antes['invoice_number'], $depois->invoice_number);
        $this->assertEqualsWithDelta((float) $antes['total'], (float) $depois->total, 0.01);
        $this->assertSame(1, $depois->items()->count(), 'as linhas do documento registado ficam');
        $this->assertEqualsWithDelta($stockAntes, (float) $artigo->fresh()->stock_quantity, 0.001,
            'e o stock não se mexe por causa de uma alteração recusada');
    }

    /** O rascunho ainda se corrige — é essa a diferença. @test */
    public function um_rascunho_ainda_se_corrige(): void
    {
        $artigo = $this->artigo();
        $f = $this->compra('draft', $artigo);

        $this->putJson(self::RAIZ . '/' . $f->id, [
            'supplier_id'  => $f->supplier_id,
            'warehouse_id' => $this->armazem->id,
            'invoice_date' => now()->toDateString(),
            'status'       => 'draft',
            'linhas'       => [['product_id' => $artigo->id, 'quantity' => 7, 'price' => 250]],
        ])->assertOk();

        $this->assertEqualsWithDelta(7, (float) $f->fresh()->items()->first()->quantity, 0.001);
    }

    /* ─── ANULAR: o caminho certo, agora que apagar não existe ─────────── */

    /**
     * ANULAR PÕE O ESTADO EM `cancelled` E DEVOLVE O STOCK QUE TINHA ENTRADO.
     *
     * É a diferença entre anular e apagar, e é toda: o documento fica lá, com
     * a data e o valor que sempre teve, e o armazém volta ao que era. Apagar a
     * linha deixava o stock alto e o documento sem existir.
     *
     * @test
     */
    public function anular_reverte_o_stock_e_deixa_o_documento_de_pe(): void
    {
        $artigo = $this->artigo();
        $f = $this->compra('pending', $artigo);

        // A compra deu entrada das 4 unidades.
        $this->assertEqualsWithDelta(4, (float) $artigo->fresh()->stock_quantity, 0.001);

        $this->postJson(self::RAIZ . '/' . $f->id . '/anular')
            ->assertOk()
            ->assertJsonPath('estado', 'cancelled');

        $depois = $f->fresh();

        $this->assertSame('cancelled', $depois->status);
        $this->assertSame($f->invoice_number, $depois->invoice_number, 'o documento continua a ser o mesmo');
        $this->assertEqualsWithDelta((float) $f->total, (float) $depois->total, 0.01);
        $this->assertSame(1, $depois->items()->count(), 'as linhas ficam — é o registo do que aconteceu');

        $this->assertEqualsWithDelta(0, (float) $artigo->fresh()->stock_quantity, 0.001,
            'o stock que entrou com a compra saiu com a anulação');
    }

    /**
     * NÃO É UM `DELETE` DISFARÇADO: a factura continua na base e na lista.
     *
     * @test
     */
    public function anular_nao_apaga_a_factura(): void
    {
        $f = $this->compra('pending');

        $this->postJson(self::RAIZ . '/' . $f->id . '/anular')->assertOk();

        $this->assertDatabaseHas('invoicing_purchase_invoices', ['id' => $f->id, 'status' => 'cancelled']);
    }

    /** Anulada, não se reanula: gravar de novo era uma mudança de estado que não existe. @test */
    public function uma_compra_anulada_nao_se_anula_outra_vez(): void
    {
        $f = $this->compra('pending');

        $this->postJson(self::RAIZ . '/' . $f->id . '/anular')->assertOk();
        $this->postJson(self::RAIZ . '/' . $f->id . '/anular')->assertStatus(422);
    }

    /** Um rascunho não chegou a entrar: não há o que reverter. Corrige-se. @test */
    public function um_rascunho_nao_se_anula(): void
    {
        $f = $this->compra('draft');

        $this->postJson(self::RAIZ . '/' . $f->id . '/anular')->assertStatus(422);

        $this->assertSame('draft', $f->fresh()->status);
    }

    /**
     * UMA COMPRA COM DINHEIRO PAGO NÃO SE ANULA SEM MAIS NADA.
     *
     * O pagamento lançou recibo e movimento de tesouraria. Anular a factura
     * por baixo deles deixava o recibo a apontar para um documento anulado e o
     * dinheiro sem contrapartida — e o stock a ser revertido por cima de uma
     * mercadoria que já foi paga. Desfaz-se primeiro o pagamento.
     *
     * @test
     */
    public function uma_compra_ja_paga_nao_se_anula(): void
    {
        $artigo = $this->artigo();
        $f = $this->compra('pending', $artigo);

        // Pagar é emitir um recibo: a permissão é essa.
        $this->comPermissoes('invoicing.receipts.create');

        $this->postJson('/api/v1/invoicing/react/pagamentos/purchase/' . $f->id, [
            'amount' => round((float) $f->total / 2, 2),
            'payment_method' => 'multicaixa',
        ])->assertCreated();

        $this->assertGreaterThan(0, (float) $f->fresh()->paid_amount);

        $recusa = $this->postJson(self::RAIZ . '/' . $f->id . '/anular')->assertStatus(422);

        $this->assertStringContainsString('pagamento', mb_strtolower((string) $recusa->json('message')));
        $this->assertNotSame('cancelled', $f->fresh()->status);
        $this->assertEqualsWithDelta(4, (float) $artigo->fresh()->stock_quantity, 0.001,
            'uma anulação recusada não mexe no stock');
    }

    /** A compra de OUTRA EMPRESA não se anula: nem se sabe que existe. @test */
    public function a_compra_de_outra_empresa_da_404(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Vizinha', 'slug' => 'viz-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'v' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $alheia = PurchaseInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id,
            'supplier_id' => \App\Models\Supplier::create([
                'tenant_id' => $outra->id, 'name' => 'Fornecedor alheio', 'is_active' => true,
            ])->id,
            'created_by' => $this->user->id,
            'invoice_number' => 'FC-ALHEIA-' . uniqid(),
            'invoice_date' => now(),
            'status' => 'pending',
            'subtotal' => 100, 'total' => 100,
        ]);

        $this->postJson(self::RAIZ . '/' . $alheia->id . '/anular')->assertNotFound();

        $this->assertSame('pending', $alheia->fresh()->status);
    }

    /**
     * ANULAR É O ELIMINAR DE UMA COMPRA — e é a permissão de eliminar que manda.
     *
     * @test
     */
    public function sem_a_permissao_de_eliminar_nao_se_anula(): void
    {
        $f = $this->compra('pending');

        $this->semPermissao('invoicing.purchases.invoices.delete');

        $this->postJson(self::RAIZ . '/' . $f->id . '/anular')->assertForbidden();

        $this->assertSame('pending', $f->fresh()->status);
    }

    /** Tira um direito ao utilizador do ensaio, venha ele de onde vier. */
    private function semPermissao(string $nome): void
    {
        setPermissionsTeamId($this->tenant->id);

        $p = \Spatie\Permission\Models\Permission::findOrCreate($nome, 'web');

        $this->user->revokePermissionTo($p);

        foreach ($this->user->roles as $papel) {
            $papel->revokePermissionTo($p);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->forgetCachedPermissions();
    }
}
