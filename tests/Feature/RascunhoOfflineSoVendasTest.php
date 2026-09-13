<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * O rascunho offline so cria documentos de VENDA.
 *
 * A Nota de Credito estava na lista, e o servidor mandava tudo o que nao fosse
 * proforma para o createInvoice(): o que nascia era uma linha na tabela das
 * VENDAS com invoice_type=NC. Nao estornava nada, nao apontava para o
 * documento original, e contava como receita nos totais. As notas de credito a
 * serio vivem em invoicing_credit_notes, com serie e numeracao proprias.
 */
class RascunhoOfflineSoVendasTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A API do PWA pede permissão desde 2026-09-13 (AutorizaApiDoPwa): o
        // utilizador do ensaio é um caixa a sério, não um membro sem papel.
        $this->comPermissoesDoPwa();
    }

    private function enviar(string $tipo)
    {
        return $this->actingAs($this->user)->postJson('/api/v1/invoicing/drafts', [
            'local_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'doc_type'   => $tipo,
            'client_id'  => $this->cliente->id,
            'items'      => [[
                'product_name' => 'Artigo',
                'quantity'     => 1,
                'unit_price'   => 100,
            ]],
        ]);
    }

    public function test_a_nota_de_credito_e_recusada(): void
    {
        $this->enviar('NC')->assertStatus(422);

        $this->assertSame(0,
            \Illuminate\Support\Facades\DB::table('invoicing_sales_invoices')
                ->where('tenant_id', $this->tenant->id)
                ->where('invoice_type', 'NC')
                ->count(),
            'uma nota de credito nao pode nascer na tabela das vendas'
        );
    }

    /** E os de venda continuam a passar, que e o que o POS faz. */
    public function test_os_documentos_de_venda_continuam_a_ser_aceites(): void
    {
        foreach (['FT', 'FR', 'proforma'] as $tipo) {
            $resposta = $this->enviar($tipo);

            $this->assertNotSame(422, $resposta->getStatusCode(),
                "o tipo {$tipo} e uma venda e tem de continuar a passar");
        }
    }

    /** O ecra offline tambem nao a oferece — senao pedia-se e levava-se erro. */
    public function test_o_ecra_offline_nao_oferece_nota_de_credito(): void
    {
        preg_match('/export const TIPOS_DE_DOCUMENTO[^=]*= \[(.*?)\];/s',
            file_get_contents(resource_path('js/pwa/ecras/novo-documento/formulario.ts')), $m);

        $this->assertNotEmpty($m, 'a lista dos tipos oferecidos tem de existir');
        $this->assertStringNotContainsString("code: 'NC'", $m[1],
            'a opcao continua na lista e so falha ao gravar');
    }
}
