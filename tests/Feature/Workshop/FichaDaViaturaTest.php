<?php

namespace Tests\Feature\Workshop;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use Tests\TenantTestCase;

/**
 * A FICHA DA VIATURA — as folhas de obra e as facturas ligadas (pedido de 15/09/2026).
 *
 * E OS CLIENTES DENTRO DA OFICINA: o ecrã da facturação, com o menu da oficina.
 */
class FichaDaViaturaTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina/ordens';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina')->comModulo('invoicing');
    }

    private function viatura(array $campos = []): Vehicle
    {
        return Vehicle::create(array_merge([
            'plate' => 'LD-' . random_int(10, 99) . '-' . random_int(10, 99) . '-AA',
            'vehicle_number' => 'VEH-' . substr(uniqid(), -5),
            'owner_name' => 'Dono', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active', 'mileage' => 120500,
        ], $campos));
    }

    private function ordem(Vehicle $v, array $campos = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'order_number' => 'OS-' . substr(uniqid(), -6),
            'vehicle_id' => $v->id,
            'received_at' => now(),
            'problem_description' => 'Não pega.',
            'status' => 'completed',
            'priority' => 'normal',
            'total' => 57000,
        ], $campos));
    }

    /** Uma factura da oficina, já emitida e com parte paga. */
    private function factura(float $total, float $pago, ?string $vencimento): SalesInvoice
    {
        $this->comPermissoes('invoicing.sales.invoices.create');
        $produto = $this->produtoComStock(50, 1000);

        $id = $this->postJson('/api/v1/invoicing/react/factura', [
            'client_id' => $this->clienteEmpresa()->id, 'warehouse_id' => $this->armazem->id, 'invoice_type' => 'FT',
            'invoice_date' => now()->toDateString(), 'status' => 'draft',
            'linhas' => [['product_id' => $produto->id, 'quantity' => 1, 'price' => 1000]],
        ])->assertCreated()->json('id');

        $f = SalesInvoice::findOrFail($id);
        $f->forceFill(['status' => 'partially_paid', 'total' => $total, 'paid_amount' => $pago, 'due_date' => $vencimento])->saveQuietly();

        return $f->fresh();
    }

    public function test_a_ficha_mostra_as_folhas_de_obra_e_a_factura_de_cada_uma(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'invoicing.sales.invoices.view');

        $v = $this->viatura();
        $antiga = $this->ordem($v, ['received_at' => now()->subDays(40)]);
        $recente = $this->ordem($v, ['received_at' => now()->subDay(), 'status' => 'in_progress', 'total' => 0]);
        $this->ordem($this->viatura(), ['received_at' => now()]); // de outra viatura: não entra

        $f = $this->factura(57000, 20000, now()->subDays(5)->toDateString());
        $antiga->forceFill(['invoice_id' => $f->id])->saveQuietly();

        $r = $this->getJson(self::API . '/viatura/' . $v->id)->assertOk();

        $this->assertSame([$recente->order_number, $antiga->order_number], array_column($r->json('ordens'), 'numero'), 'a mais recente primeiro, e só as desta viatura');
        $this->assertNull($r->json('ordens.0.factura'), 'a ordem em curso ainda não tem factura');

        $ligada = $r->json('ordens.1.factura');
        $this->assertSame($f->invoice_number, $ligada['numero']);
        $this->assertEqualsWithDelta(37000, $ligada['falta'], 0.01);
        $this->assertTrue($ligada['vencida']);
        $this->assertSame("/invoicing/sales/invoices/{$f->id}/preview", $ligada['preview']);

        $this->assertSame(2, $r->json('resumo.ordens'));
        $this->assertSame(1, $r->json('resumo.abertas'));
        $this->assertSame(1, $r->json('resumo.facturas'));
        $this->assertEqualsWithDelta(37000, $r->json('resumo.por_receber'), 0.01);
    }

    public function test_sem_permissao_de_facturas_nao_leva_as_moradas_e_um_rascunho_nao_deve_nada(): void
    {
        $this->comPermissoes('workshop.vehicles.view');

        $v = $this->viatura();
        $o = $this->ordem($v);
        $f = $this->factura(57000, 0, now()->subDays(5)->toDateString());
        $f->forceFill(['status' => 'draft'])->saveQuietly();
        $o->forceFill(['invoice_id' => $f->id])->saveQuietly();

        $r = $this->getJson(self::API . '/viatura/' . $v->id)->assertOk();

        $this->assertNull($r->json('ordens.0.factura.preview'));
        $this->assertSame(0, (int) $r->json('ordens.0.factura.falta'), 'um rascunho não é dívida');
        $this->assertFalse($r->json('ordens.0.factura.vencida'));
    }

    public function test_a_viatura_de_outra_empresa_nao_se_abre(): void
    {
        $this->comPermissoes('workshop.vehicles.view');

        $outra = Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $alheia = Vehicle::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'plate' => 'LD-00-00-ZZ', 'vehicle_number' => 'VEH-X', 'owner_name' => 'X', 'brand' => 'X', 'model' => 'X', 'status' => 'active',
        ]);

        $this->getJson(self::API . '/viatura/' . $alheia->id)->assertNotFound();
    }

    public function test_sem_a_permissao_das_viaturas_e_recusado(): void
    {
        $this->comPermissoes('workshop.work-orders.view');

        $this->getJson(self::API . '/viatura/' . $this->viatura()->id)->assertForbidden();
    }

    public function test_o_catalogo_das_viaturas_anuncia_a_ficha(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.mechanics.view');

        $this->getJson('/api/v1/invoicing/react/catalogos/viaturas/opcoes')->assertOk()->assertJsonPath('ficha', 'viatura');
        $this->getJson('/api/v1/invoicing/react/catalogos/mecanicos/opcoes')->assertOk()->assertJsonPath('ficha', null);
    }

    public function test_os_clientes_abrem_dentro_da_oficina_com_a_permissao_dos_clientes(): void
    {
        $this->comPermissoes('invoicing.clients.view');
        $this->get('/workshop/clients')->assertOk()->assertSee('data-ecra="facturacao/clientes"', false);
    }
}
