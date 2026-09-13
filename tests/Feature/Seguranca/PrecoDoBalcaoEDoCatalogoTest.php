<?php

namespace Tests\Feature\Seguranca;

use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\SalesInvoice;
use Tests\TenantTestCase;

/**
 * O PREÇO AO BALCÃO É O DO CATÁLOGO — salvo para quem pode mudar preços.
 *
 * A porta aceitava qualquer `unit_price`: um caixa emitia uma Fatura-Recibo
 * assinada a 1 Kz e o stock saía inteiro (auditoria de 2026-09-13).
 */
class PrecoDoBalcaoEDoCatalogoTest extends TenantTestCase
{
    private function turno(): void
    {
        PosShift::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'shift_number' => 'T' . random_int(10000, 99999), 'opened_at' => now(),
            'opening_amount' => 0, 'status' => 'open',
        ]);
    }

    private function venda(int $produto, string $nome, float $preco): array
    {
        return [
            'local_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'payment_method' => 'cash',
            'amount_received' => 100000,
            'items' => [['product_id' => $produto, 'product_name' => $nome, 'quantity' => 1, 'unit_price' => $preco, 'is_service' => false, 'unit' => 'UN']],
        ];
    }

    public function test_o_caixa_nao_vende_abaixo_do_catalogo_nem_linhas_inventadas(): void
    {
        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.pos.access', 'invoicing.pos.sell');
        $this->turno();
        $p = $this->produtoComStock(10, 1000);

        $this->postJson('/api/v1/invoicing/react/pos/vender', $this->venda($p->id, $p->name, 1))->assertStatus(422);

        $livre = $this->venda($p->id, 'Linha inventada', 1000);
        $livre['items'][0]['product_id'] = null;
        $this->postJson('/api/v1/invoicing/react/pos/vender', $livre)->assertStatus(422);

        $this->assertSame(0, SalesInvoice::where('tenant_id', $this->tenant->id)->count());

        $this->postJson('/api/v1/invoicing/react/pos/vender', $this->venda($p->id, $p->name, 1000))->assertSuccessful();
    }

    public function test_quem_pode_mudar_precos_muda(): void
    {
        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.pos.access', 'invoicing.pos.sell', 'invoicing.products.edit');
        $this->turno();
        $p = $this->produtoComStock(10, 1000);

        $this->postJson('/api/v1/invoicing/react/pos/vender', $this->venda($p->id, $p->name, 800))->assertSuccessful();
    }
}
