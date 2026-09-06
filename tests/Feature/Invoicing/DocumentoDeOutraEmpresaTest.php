<?php

namespace Tests\Feature\Invoicing;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Supplier;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * O que aconteceu em produção: o utilizador trocou de empresa com a lista de
 * facturas de compra aberta. O ecrã continuou a mostrar a lista da empresa
 * ANTERIOR e ele carregou em "Registar Pagamento". O filtro por empresa fez o
 * seu trabalho e não encontrou o documento — mas o `findOrFail` transformou
 * isso em "No query results for model [App\Models\Invoicing\PurchaseInvoice]
 * 35" à cara do utilizador (e em 500 nas outras acções da lista).
 *
 * OS ECRÃS SÃO AGORA EM REACT e a forma do aviso mudou de sítio: o servidor
 * responde 404 em JSON e o ecrã trata-o como "este documento não é seu" —
 * nunca 500, nunca uma excepção com o nome da classe lá dentro.
 *
 * O QUE NÃO PODE MUDAR NUNCA, e é o que estes ensaios guardam: um documento de
 * OUTRA empresa dá 404 em todas as portas, e não 200. Nem para ler, nem para
 * alterar, nem para pagar — e o registo dele fica exactamente como estava.
 */
class DocumentoDeOutraEmpresaTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes(
            'invoicing.purchases.invoices.view',
            'invoicing.purchases.invoices.create',
            'invoicing.sales.invoices.view',
            'invoicing.receipts.create',
        );
    }

    private function outraEmpresa(): Tenant
    {
        return Tenant::create([
            'name'      => 'Empresa Vizinha',
            'slug'      => 'vizinha-' . uniqid(),
            'nif'       => (string) random_int(500000000, 599999999),
            'email'     => 'viz' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);
    }

    /** Uma factura de compra que pertence a OUTRA empresa. */
    private function compraDeOutraEmpresa(): PurchaseInvoice
    {
        $outra = $this->outraEmpresa();

        // withoutEvents: o global scope de empresa é de LEITURA, mas o
        // `creating` do BelongsToTenant carimbaria aqui a empresa activa do
        // teste — e era justamente isso que se quer evitar.
        return PurchaseInvoice::withoutEvents(function () use ($outra) {
            $fornecedor = Supplier::withoutEvents(fn () => Supplier::create([
                'tenant_id' => $outra->id, 'name' => 'Fornecedor Vizinho', 'is_active' => true,
            ]));

            return PurchaseInvoice::create([
                'tenant_id'      => $outra->id,
                'supplier_id'    => $fornecedor->id,
                'invoice_number' => 'FC-VIZ-' . uniqid(),
                'invoice_date'   => now(),
                'status'         => 'pending',
                'subtotal'       => 7000,
                'total'          => 7000,
                'paid_amount'    => 0,
            ]);
        });
    }

    /** @test */
    public function abrir_uma_compra_de_outra_empresa_da_404_e_nao_a_ficha(): void
    {
        $alheia = $this->compraDeOutraEmpresa();

        $this->getJson(self::RAIZ . '/compra/' . $alheia->id)->assertNotFound();
    }

    /** @test */
    public function alterar_uma_compra_de_outra_empresa_nao_lhe_toca(): void
    {
        $alheia = $this->compraDeOutraEmpresa();

        $this->putJson(self::RAIZ . '/compra/' . $alheia->id, [
            'supplier_id'  => $alheia->supplier_id,
            'warehouse_id' => $this->armazem->id,
            'invoice_date' => now()->toDateString(),
            'linhas'       => [['description' => 'Nada disto', 'quantity' => 1, 'price' => 1]],
        ])->assertNotFound();

        $alheia->refresh();

        $this->assertSame('pending', $alheia->status);
        $this->assertEqualsWithDelta(7000, (float) $alheia->total, 0.01);
    }

    /**
     * O CASO DE PRODUÇÃO: carregar em "Registar Pagamento" na lista da empresa
     * anterior. Nem o contexto do modal abre, nem o pagamento entra.
     *
     * @test
     */
    public function pagar_uma_compra_de_outra_empresa_nao_e_possivel(): void
    {
        $alheia = $this->compraDeOutraEmpresa();

        $this->getJson(self::RAIZ . '/pagamentos/purchase/' . $alheia->id)->assertNotFound();

        $this->postJson(self::RAIZ . '/pagamentos/purchase/' . $alheia->id, [
            'amount'         => 7000,
            'payment_method' => 'cash',
        ])->assertNotFound();

        $alheia->refresh();

        $this->assertSame('pending', $alheia->status);
        $this->assertEquals(0, (float) $alheia->paid_amount);
    }

    /** E o mesmo do lado das vendas: a factura do vizinho não se abre. @test */
    public function abrir_uma_factura_de_venda_de_outra_empresa_da_404(): void
    {
        $outra = $this->outraEmpresa();

        $alheia = SalesInvoice::withoutEvents(fn () => SalesInvoice::create([
            'tenant_id'      => $outra->id,
            'client_id'      => $this->cliente->id,
            'invoice_number' => 'FT VIZ/000001',
            'invoice_type'   => 'FT',
            'invoice_date'   => now(),
            'status'         => 'sent',
            'total'          => 1000,
            'created_by'     => $this->user->id,
        ]));

        $this->getJson(self::RAIZ . '/factura/' . $alheia->id)->assertNotFound();
        $this->getJson(self::RAIZ . '/pagamentos/sale/' . $alheia->id)->assertNotFound();
    }

    /** A porta continua aberta para os documentos da própria empresa. @test */
    public function documento_da_propria_empresa_continua_a_funcionar(): void
    {
        $fornecedor = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Fornecedor', 'is_active' => true]);

        $minha = PurchaseInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'supplier_id'    => $fornecedor->id,
            'invoice_number' => 'FC-' . uniqid(),
            'invoice_date'   => now(),
            'status'         => 'pending',
            'subtotal'       => 1000,
            'total'          => 1000,
            'paid_amount'    => 0,
            'created_by'     => $this->user->id,
        ]);

        $this->getJson(self::RAIZ . '/compra/' . $minha->id)
            ->assertOk()
            ->assertJsonPath('documento.id', $minha->id);

        $this->getJson(self::RAIZ . '/pagamentos/purchase/' . $minha->id)
            ->assertOk()
            ->assertJsonPath('factura.id', $minha->id);
    }
}
