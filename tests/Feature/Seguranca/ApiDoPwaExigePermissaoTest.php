<?php

namespace Tests\Feature\Seguranca;

use App\Models\Invoicing\SalesInvoice;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * A API DO PWA E DA APP MÓVEL PEDE PERMISSÃO — à sessão e ao operador.
 *
 * Só autenticava: qualquer membro da empresa emitia uma Fatura-Recibo assinada
 * ao preço que quisesse em nome de um colega, fechava o turno dele com a
 * contagem que escrevesse, e a API antiga do restaurante anulava artigos já
 * produzidos sem `restaurant.orders.cancel` (auditoria de 2026-09-13).
 */
class ApiDoPwaExigePermissaoTest extends TenantTestCase
{
    private function venda(array $por = []): array
    {
        $p = $this->produtoComStock(10, 1000);

        return array_merge([
            'local_uuid' => 'seg-' . uniqid(),
            'payment_method' => 'cash',
            'items' => [['product_id' => $p->id, 'product_name' => $p->name, 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]],
        ], $por);
    }

    private function colega(bool $comPin, bool $vende): User
    {
        $u = User::create([
            'name' => 'Colega ' . uniqid(), 'email' => 'colega' . uniqid() . '@empresa.ao',
            'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
        $u->tenants()->syncWithoutDetaching([$this->tenant->id => ['is_active' => true]]);

        if ($vende) {
            $this->operadorDoPwa($u);
        }

        if (! $comPin) {
            $u->forceFill(['pos_pin_hash' => null])->save();
        } elseif (! $u->temPinPos()) {
            $u->definirPinPos('5827');
        }

        return $u;
    }

    public function test_um_membro_sem_papel_nao_vende_nem_sincroniza_nem_mexe_em_turnos(): void
    {
        $this->getJson('/api/v1/invoicing/sync')->assertForbidden();
        $this->postJson('/api/v1/invoicing/pos/sale', $this->venda())->assertForbidden();
        $this->postJson('/api/v1/invoicing/drafts', ['doc_type' => 'FT', 'items' => [['product_name' => 'x', 'quantity' => 1, 'unit_price' => 1]]])->assertForbidden();
        $this->postJson('/api/v1/invoicing/pos/shift/open', ['local_uuid' => 't-' . uniqid(), 'opening_balance' => 0])->assertForbidden();
        $this->postJson('/api/v1/invoicing/pos/shift/close', ['local_uuid' => 't-' . uniqid(), 'actual_cash' => 0])->assertForbidden();
        $this->postJson('/api/v1/invoicing/clients', ['name' => 'Intruso', 'local_uuid' => 'c-' . uniqid()])->assertForbidden();

        $this->assertSame(0, SalesInvoice::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_o_caixa_nao_vende_em_nome_de_um_colega_sem_permissao_ou_sem_pin(): void
    {
        $this->comPermissoesDoPwa();

        $semPermissao = $this->colega(comPin: true, vende: false);
        $this->postJson('/api/v1/invoicing/pos/sale', $this->venda(['operator_id' => $semPermissao->id]))
            ->assertForbidden()->assertJsonFragment(['message' => __('O operador :nome não tem permissão para esta operação.', ['nome' => $semPermissao->name])]);

        $semPin = $this->colega(comPin: false, vende: true);
        $this->postJson('/api/v1/invoicing/pos/sale', $this->venda(['operator_id' => $semPin->id]))->assertForbidden();

        $colega = $this->colega(comPin: true, vende: true);
        $this->postJson('/api/v1/invoicing/pos/sale', $this->venda(['operator_id' => $colega->id]))->assertSuccessful();
    }

    public function test_o_empregado_de_mesa_sincroniza_mas_nao_vende_ao_balcao(): void
    {
        $this->comPermissoes('restaurant.orders.view');

        $this->getJson('/api/v1/invoicing/sync')->assertOk();
        $this->postJson('/api/v1/invoicing/pos/sale', $this->venda())->assertForbidden();
    }

    public function test_a_api_antiga_do_restaurante_pede_as_permissoes_das_comandas(): void
    {
        $this->comModulo('restaurant');
        $this->comPermissoes('restaurant.orders.view', 'restaurant.orders.edit');

        $this->postJson('/api/v1/restaurant/orders/1/items/1/void', ['reason' => 'xxx'])->assertForbidden();
        $this->postJson('/api/v1/restaurant/orders/1/checkout', [])->assertForbidden();
        $this->postJson('/api/v1/restaurant/orders/1/merge', [])->assertForbidden();
    }

    public function test_uma_data_do_aparelho_no_futuro_vale_o_agora_do_servidor(): void
    {
        $this->comPermissoesDoPwa();
        $this->abrirTurnoDoPwa();

        $this->postJson('/api/v1/invoicing/pos/sale', $this->venda([
            'local_uuid' => 'futuro-' . uniqid(),
            'created_at_local' => now()->addYear()->toIso8601String(),
        ]))->assertSuccessful();

        $factura = SalesInvoice::where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
        $this->assertTrue($factura->invoice_date->lessThanOrEqualTo(now()->endOfDay()), 'nenhuma factura com data futura');
    }

    private function abrirTurnoDoPwa(): void
    {
        $this->postJson('/api/v1/invoicing/pos/shift/open', ['local_uuid' => 'turno-' . uniqid(), 'opening_balance' => 0])->assertSuccessful();
    }
}
