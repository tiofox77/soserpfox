<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Services\AGT\DocumentMapper;
use Tests\TenantTestCase;

/**
 * O documento nunca pode seguir para a AGT sem linhas.
 *
 * A factura é gravada ANTES das suas linhas, e quem corre no evento `created`
 * — o observer que desconta stock — lê `$factura->items` nesse momento. O
 * Eloquent guarda essa colecção VAZIA e nunca mais a consulta. A submissão,
 * feita a seguir com o MESMO objecto, enviava um documento com os totais
 * preenchidos e zero linhas.
 *
 * A AGT recusava-o com uma mensagem que apontava para outro sítio:
 *
 *   E27 — Utilização incorrecta do campo "paymentReceipt" para o tipo de
 *   factura (FR).
 *
 * Foram precisas três hipóteses erradas até a comparação campo a campo com um
 * documento aceite mostrar que a diferença era esta. Depois de corrigido, a
 * mesma FR passou: resultCode 0, documentStatus V.
 */
class AgtLinhasDoDocumentoTest extends TenantTestCase
{
    private function facturaComLinha(): SalesInvoice
    {
        $f = SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->cliente->id,
            'created_by'     => $this->user->id,
            'invoice_number' => 'FR S/000001',
            'invoice_type'   => 'FR',
            'invoice_date'   => now()->toDateString(),
            'due_date'       => now()->toDateString(),
            'status'         => 'paid',
            'subtotal'       => 1000,
            'net_total'      => 1000,
            'tax_payable'    => 140,
            'total'          => 1140,
            'gross_total'    => 1140,
        ]);

        // O ponto crítico: ler a colecção ANTES de existirem linhas.
        $this->assertSame(0, $f->items->count(), 'a colecção fica em cache vazia');

        SalesInvoiceItem::create([
            'sales_invoice_id' => $f->id,
            'product_name'     => 'Bife da Casa',
            'description'      => 'Bife da Casa',
            'quantity'         => 1,
            'unit'             => 'UN',
            'unit_price'       => 1000,
            'unit_price_base'  => 1000,
            'subtotal'         => 1000,
            'tax_rate'         => 14,
            'tax_amount'       => 140,
            'total'            => 1140,
            'credit_amount'    => 1000,
            'order'            => 1,
            'tax_country_region' => 'AO',
            'tax_code'         => 'NOR',
        ]);

        return $f;
    }

    public function test_a_coleccao_em_cache_vazia_nao_esvazia_o_payload(): void
    {
        $factura = $this->facturaComLinha();

        // O objecto continua com a colecção em cache a zero...
        $this->assertSame(0, $factura->items->count());

        // ...mas o documento enviado tem de levar a linha.
        $doc = (new DocumentMapper())->map($factura);

        $this->assertCount(1, $doc['lines'], 'o documento não pode seguir sem linhas');
        $this->assertSame('Bife da Casa', $doc['lines'][0]['productDescription']);
    }

    public function test_totais_sem_linhas_e_o_que_a_agt_recusa(): void
    {
        // O sinal que denuncia o problema: totais preenchidos e nenhuma linha.
        // Um documento assim nunca deve chegar a ser construído.
        $factura = $this->facturaComLinha();

        $doc = (new DocumentMapper())->map($factura);

        $this->assertGreaterThan(0, $doc['documentTotals']['grossTotal']);
        $this->assertNotEmpty(
            $doc['lines'],
            'totais acima de zero com zero linhas é exactamente o que foi recusado'
        );
    }

    public function test_uma_coleccao_ja_carregada_com_linhas_nao_e_reconsultada(): void
    {
        // A correcção só actua no caso suspeito — carregada E vazia. Carregada
        // com conteúdo tem de continuar a servir, sem consulta extra.
        $factura = $this->facturaComLinha();
        $factura->load('items');

        $this->assertSame(1, $factura->items->count());
        $this->assertCount(1, (new DocumentMapper())->map($factura)['lines']);
    }
}
