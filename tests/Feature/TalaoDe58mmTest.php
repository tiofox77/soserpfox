<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\PosShift;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/**
 * O TALÃO DE 58 mm — as máquinas portáteis com impressora embutida.
 *
 * PORQUE EXISTE (28/09/2026). Todos os talões eram de 80 mm, fixos. A Sunmi
 * V2s (e as semelhantes) usa rolo de 58 mm e imprime 48 mm: o talão de 80 mm
 * saía encolhido a ~60 % ou cortado à direita. Agora a largura escolhe-se na
 * empresa (Definições › Impressão) e, por cima, em cada aparelho (fica
 * guardada nele e vai no endereço como ?largura=58).
 *
 * A 58 mm não se encolhe o talão de 80: a folha tem 58 mm, o cabeçalho
 * empilha e cada artigo ocupa duas linhas. O conteúdo fiscal é o mesmo.
 */
class TalaoDe58mmTest extends TenantTestCase
{
    private function factura(): SalesInvoice
    {
        $f = SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->clienteEmpresa()->id,
            'invoice_number' => 'FR TESTE/' . random_int(1000, 9999),
            'invoice_date'   => now(),
            'status'         => 'paid',
            'total'          => 1140,
            'created_by'     => $this->user->id,
        ]);

        SalesInvoiceItem::create([
            'sales_invoice_id' => $f->id,
            'product_name'     => 'Água Mineral 1,5L',
            'description'      => 'Água Mineral 1,5L',
            'quantity'         => 2,
            'unit'             => 'UN',
            'unit_price'       => 500,
            'unit_price_base'  => 500,
            'subtotal'         => 1000,
            'tax_rate'         => 14,
            'tax_amount'       => 140,
            'total'            => 1140,
            'credit_amount'    => 1000,
            'order'            => 1,
            'tax_country_region' => 'AO',
        ]);

        return $f;
    }

    /** @test */
    public function a_definicao_existe_e_por_omissao_e_80(): void
    {
        $this->assertTrue(Schema::hasColumn('invoicing_settings', 'pos_largura_talao'));

        $this->assertSame('80', InvoicingSettings::forTenant($this->tenant->id)->pos_largura_talao);
        $this->assertSame(80, (new InvoicingSettings())->larguraDoTalao(), 'sem nada gravado, 80 mm');
        $this->assertSame(80, (new InvoicingSettings(['pos_largura_talao' => '70']))->larguraDoTalao(),
            'um valor estranho não inventa papel: cai nos 80');
        $this->assertSame(58, (new InvoicingSettings(['pos_largura_talao' => '58']))->larguraDoTalao());
    }

    /** @test */
    public function as_definicoes_gravam_58_e_recusam_o_resto(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.settings.view', 'invoicing.settings.edit');

        $raiz = '/api/v1/invoicing/react/definicoes';
        $corpo = $this->getJson($raiz)->assertOk()->json('definicoes');
        $this->assertSame('80', $corpo['pos_largura_talao']);

        $this->putJson($raiz, array_merge($corpo, ['pos_largura_talao' => '70']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('pos_largura_talao');

        $this->putJson($raiz, array_merge($corpo, ['pos_largura_talao' => '58']))->assertOk();

        $this->assertSame(58, InvoicingSettings::forTenant($this->tenant->id)->fresh()->larguraDoTalao());
    }

    /** @test */
    public function o_talao_do_servidor_sai_em_58_quando_se_pede(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.sales.invoices.view');
        $f = $this->factura();

        $html = $this->get("/invoicing/sales/invoices/{$f->id}/talao?largura=58")->assertOk()->getContent();

        $this->assertStringContainsString('size: 58mm auto', $html, 'a folha é de 58 mm');
        $this->assertStringContainsString('ticket-thermal papel-58', $html);
        // Duas colunas e não quatro: o nome numa linha, «qtd × preço» e o total na de baixo.
        $this->assertStringContainsString('2 × 500', $html);
        $this->assertStringNotContainsString('>QTD<', $html);
        $this->assertStringContainsString('Água Mineral 1,5L', $html);

        // E sem pedir nada continua o de sempre.
        $html80 = $this->get("/invoicing/sales/invoices/{$f->id}/talao")->assertOk()->getContent();
        $this->assertStringContainsString('size: 80mm auto', $html80);
        $this->assertStringContainsString('ticket-thermal papel-80', $html80);
    }

    /** @test */
    public function sem_pedido_vale_a_largura_da_empresa_e_o_pedido_ganha(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.sales.invoices.view');
        InvoicingSettings::forTenant($this->tenant->id)->update(['pos_largura_talao' => '58']);
        $f = $this->factura();

        $this->assertStringContainsString('size: 58mm auto',
            $this->get("/invoicing/sales/invoices/{$f->id}/talao")->assertOk()->getContent());

        // O aparelho do balcão, com impressora de 80, pede a sua.
        $this->assertStringContainsString('size: 80mm auto',
            $this->get("/invoicing/sales/invoices/{$f->id}/talao?largura=80")->assertOk()->getContent());

        // Um pedido inventado não passa: vale a da empresa.
        $this->assertStringContainsString('size: 58mm auto',
            $this->get("/invoicing/sales/invoices/{$f->id}/talao?largura=120")->assertOk()->getContent());
    }

    /** @test */
    public function o_talao_do_turno_tambem_tem_58(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.pos.access');

        $turno = PosShift::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'shift_number' => 'T' . random_int(10000, 99999),
            'opened_at' => now()->subHours(3),
            'closed_at' => now(),
            'opening_amount' => 0,
            'status' => 'closed',
        ]);

        $html = $this->get("/invoicing/pos/export/shift/{$turno->id}/ticket?largura=58&print=0")->assertOk()->getContent();
        $this->assertStringContainsString('size: 58mm auto', $html);

        $html = $this->get("/invoicing/pos/export/shift/{$turno->id}/ticket?print=0")->assertOk()->getContent();
        $this->assertStringContainsString('size: 80mm auto', $html);
    }

    /** O PDV e o PWA recebem a largura da empresa; o aparelho pode sobrepor-lhe a sua. @test */
    public function o_pdv_e_o_pwa_sabem_a_largura_da_empresa(): void
    {
        InvoicingSettings::forTenant($this->tenant->id)->update(['pos_largura_talao' => '58']);

        $this->comModulo('invoicing')->comPermissoes('invoicing.pos.reports');

        $mapa = $this->getJson('/api/v1/invoicing/react/pos/relatorio?'.http_build_query([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]))->assertOk()->json();

        $this->assertSame(58, $mapa['meta']['largura']);

        $sync = file_get_contents(app_path('Http/Controllers/Api/Invoicing/SyncController.php'));
        $this->assertStringContainsString("'talao_largura' =>", $sync, 'a empresa que vai para o PWA leva a largura');

        $pos = file_get_contents(app_path('Http/Controllers/Api/Invoicing/PosApiController.php'));
        $this->assertStringContainsString("'largura' => \$definicoes->larguraDoTalao()", $pos, 'a venda fechada leva a largura');
    }
}
