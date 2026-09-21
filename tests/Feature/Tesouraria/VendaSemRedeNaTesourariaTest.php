<?php

namespace Tests\Feature\Tesouraria;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Treasury\Transaction;
use Tests\TenantTestCase;

/**
 * A VENDA FEITA SEM REDE E A TESOURARIA (19/09/2026).
 *
 * «O PWA tem um bug nos recibos e facturas: as informações não estão a cruzar
 * bem com a tesouraria.»
 *
 * Cruzavam mal por uma razão simples: a FACTURA ficava com a data em que foi
 * vendida no aparelho (`created_at_local`) e o MOVIMENTO DE TESOURARIA ficava
 * com a data em que a fila subiu. Uma loja três dias sem internet emitia as
 * facturas nos dias 15, 16 e 17 e punha todo o dinheiro na tesouraria no dia
 * 18: o mapa de caixa de cada dia não batia com as facturas desse dia, e o
 * dia da descarga aparecia com o triplo do que vendeu.
 */
class VendaSemRedeNaTesourariaTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoesDoPwa();
        $this->comPermissoes('invoicing.pos.access', 'invoicing.pos.view')->comModulo('invoicing');
    }

    private function enviar(string $uuid, float $preco, string $quando)
    {
        return $this->postJson('/api/v1/invoicing/pos/sale', [
            'local_uuid' => $uuid,
            'client_id' => null,
            'payment_method' => 'cash',
            'amount_received' => $preco,
            'created_at_local' => $quando,
            'items' => [[
                'product_id' => $this->produtoComStock(500)->id,
                'product_name' => 'Artigo de teste',
                'quantity' => 1,
                'unit_price' => $preco,
                'tax_rate' => 0,
                'is_service' => false,
                'unit' => 'UN',
            ]],
        ]);
    }

    public function test_o_movimento_fica_com_a_data_da_venda_e_nao_com_a_da_descarga(): void
    {
        $ha3dias = now()->subDays(3)->format('Y-m-d\TH:i:s');

        $this->enviar('uuid-sem-rede-1', 7500, $ha3dias)->assertSuccessful();

        $factura = SalesInvoice::withoutGlobalScopes()->where('local_uuid', 'uuid-sem-rede-1')->firstOrFail();
        $movimento = Transaction::withoutGlobalScopes()->where('invoice_id', $factura->id)->firstOrFail();

        $this->assertSame(
            $factura->invoice_date->toDateString(),
            $movimento->transaction_date->toDateString(),
            'o dinheiro entra na tesouraria no dia em que foi cobrado, não no dia em que a fila subiu',
        );
        $this->assertSame(now()->subDays(3)->toDateString(), $movimento->transaction_date->toDateString());
    }

    public function test_o_movimento_aponta_para_a_factura_que_o_gerou(): void
    {
        $this->enviar('uuid-sem-rede-2', 2500, now()->format('Y-m-d\TH:i:s'))->assertSuccessful();

        $factura = SalesInvoice::withoutGlobalScopes()->where('local_uuid', 'uuid-sem-rede-2')->firstOrFail();
        $movimento = Transaction::withoutGlobalScopes()->where('invoice_id', $factura->id)->firstOrFail();

        // Sem isto o movimento só se liga à factura pela coluna `invoice_id`,
        // que as notas e os recibos também usam: não havia como saber que
        // documento é a origem daquele dinheiro.
        $this->assertSame(SalesInvoice::class, $movimento->related_type);
        $this->assertSame($factura->id, (int) $movimento->related_id);
    }

    /** Uma FR emitida no «Novo Documento» do PWA — a outra porta da fila. */
    private function emitir(string $uuid, string $tipo, float $preco, ?string $forma = null)
    {
        return $this->postJson('/api/v1/invoicing/drafts', array_filter([
            'doc_type' => $tipo,
            'local_uuid' => $uuid,
            'payment_method' => $forma,
            'items' => [[
                'product_id' => $this->produtoComStock(500)->id,
                'product_name' => 'Artigo de teste',
                'quantity' => 1,
                'unit_price' => $preco,
                'tax_rate' => 0,
            ]],
        ]));
    }

    public function test_a_fatura_recibo_do_pwa_entra_na_tesouraria_e_fica_paga(): void
    {
        $this->emitir('uuid-fr-pwa', 'FR', 9000)->assertSuccessful();

        $factura = SalesInvoice::withoutGlobalScopes()->where('local_uuid', 'uuid-fr-pwa')->firstOrFail();

        // Uma FR é paga no acto: sem isto ficava com saldo por receber igual ao
        // total e aparecia na dívida do cliente para sempre.
        $this->assertEqualsWithDelta(
            (float) $factura->total, (float) $factura->paid_amount, 0.01,
            'uma fatura-recibo nasce paga',
        );

        $movimento = Transaction::withoutGlobalScopes()->where('invoice_id', $factura->id)->first();

        $this->assertNotNull($movimento, 'o dinheiro da FR tem de aparecer na tesouraria');
        $this->assertSame('income', $movimento->type);
        $this->assertEqualsWithDelta((float) $factura->total, (float) $movimento->amount, 0.01);
    }

    public function test_a_fatura_a_prazo_do_pwa_nao_inventa_dinheiro(): void
    {
        $this->emitir('uuid-ft-pwa', 'FT', 9000)->assertSuccessful();

        $factura = SalesInvoice::withoutGlobalScopes()->where('local_uuid', 'uuid-ft-pwa')->firstOrFail();

        // Uma FT fica a aguardar pagamento: nada entra na tesouraria até haver
        // recibo.
        $this->assertEqualsWithDelta(0, (float) $factura->paid_amount, 0.01);
        $this->assertSame(0, Transaction::withoutGlobalScopes()->where('invoice_id', $factura->id)->count());
    }

    public function test_reenviar_a_fatura_recibo_nao_lanca_o_dinheiro_duas_vezes(): void
    {
        $this->emitir('uuid-fr-repetida', 'FR', 4500)->assertSuccessful();
        $this->emitir('uuid-fr-repetida', 'FR', 4500)->assertSuccessful();

        $factura = SalesInvoice::withoutGlobalScopes()->where('local_uuid', 'uuid-fr-repetida')->firstOrFail();

        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('invoice_id', $factura->id)->count());
        $this->assertEqualsWithDelta((float) $factura->total, (float) $factura->paid_amount, 0.01);
    }

    public function test_reenviar_a_mesma_venda_nao_duplica_o_dinheiro(): void
    {
        $quando = now()->format('Y-m-d\TH:i:s');

        $this->enviar('uuid-sem-rede-3', 4000, $quando)->assertSuccessful();
        $this->enviar('uuid-sem-rede-3', 4000, $quando)->assertSuccessful();

        $factura = SalesInvoice::withoutGlobalScopes()->where('local_uuid', 'uuid-sem-rede-3')->firstOrFail();

        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('invoice_id', $factura->id)->count());
        $this->assertSame(1, SalesInvoice::withoutGlobalScopes()->where('local_uuid', 'uuid-sem-rede-3')->count());
    }
}
