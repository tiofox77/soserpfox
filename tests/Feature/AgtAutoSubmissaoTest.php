<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Services\AGT\AutoSubmissao;
use Tests\TenantTestCase;

/**
 * O envio automático vale para TODOS os documentos fiscais.
 *
 * Estava copiado por seis sítios — POS, sincronização do PWA, facturas, notas
 * de crédito, notas de débito e os módulos — cada um com a sua versão. Os
 * recibos não tinham nenhuma: são documento fiscal (RC/RG) e simplesmente
 * nunca eram enviados.
 *
 * E dos seis, quatro submetiam o objecto original em vez de uma instância
 * fresca — o que fazia o documento seguir sem linhas (ver
 * AgtLinhasDoDocumentoTest).
 */
class AgtAutoSubmissaoTest extends TenantTestCase
{
    private function definicoes(bool $automatico): InvoicingSettings
    {
        $d = InvoicingSettings::forTenant($this->tenant->id);
        $d->update(['agt_auto_submit' => $automatico]);

        return $d->fresh();
    }

    private function factura(): SalesInvoice
    {
        $f = SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->cliente->id,
            'created_by'     => $this->user->id,
            'invoice_number' => 'FT S/000001',
            'invoice_type'   => 'FT',
            'invoice_date'   => now()->toDateString(),
            'due_date'       => now()->toDateString(),
            'status'         => 'sent',
            'subtotal'       => 1000,
            'net_total'      => 1000,
            'tax_payable'    => 140,
            'total'          => 1140,
            'gross_total'    => 1140,
        ]);

        SalesInvoiceItem::create([
            'sales_invoice_id' => $f->id,
            'product_name'     => 'Artigo',
            'description'      => 'Artigo',
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

    public function test_com_o_envio_desligado_nao_tenta_nada(): void
    {
        $this->definicoes(false);

        $r = AutoSubmissao::submeter($this->factura());

        $this->assertFalse($r['enviado']);
        $this->assertNull($r['erro'], 'desligado não é erro');
    }

    public function test_uma_falha_nunca_rebenta_para_quem_chamou(): void
    {
        // O documento já está gravado e é válido. Rebentar aqui deixava o
        // operador sem saber se a venda se fez.
        $this->definicoes(true);

        $r = AutoSubmissao::submeter($this->factura());

        // Sem chaves nem rede no teste, tem de falhar — mas devolvendo o erro,
        // não lançando-o.
        $this->assertFalse($r['enviado']);
        $this->assertNotNull($r['erro']);
    }

    public function test_sem_empresa_nao_tenta(): void
    {
        $factura = $this->factura();
        $factura->tenant_id = null;

        $r = AutoSubmissao::submeter($factura, 0);

        $this->assertFalse($r['enviado']);
    }

    public function test_serve_qualquer_documento_fiscal(): void
    {
        // O ponto da centralização: não é preciso um ramo por tipo. Os recibos
        // não tinham nenhum e por isso nunca eram enviados.
        $this->definicoes(false);

        foreach ([
            \App\Models\Invoicing\CreditNote::class,
            \App\Models\Invoicing\DebitNote::class,
            \App\Models\Invoicing\Receipt::class,
        ] as $classe) {
            $doc = new $classe(['tenant_id' => $this->tenant->id]);
            $doc->tenant_id = $this->tenant->id;

            $r = AutoSubmissao::submeter($doc);

            $this->assertFalse($r['enviado'], class_basename($classe) . ' com envio desligado');
            $this->assertNull($r['erro']);
        }
    }
}
