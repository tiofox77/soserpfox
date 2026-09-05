<?php

namespace Tests\Feature\Invoicing;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Product;
use App\Services\AGT\SignatureService;
use App\Services\Invoicing\ModuleInvoiceService;
use Tests\TenantTestCase;

/**
 * A cadeia de assinaturas SAFT-AO: cada documento leva o hash do anterior.
 *
 * O defeito que estes ensaios travam era invisível até ao pior momento
 * possível. As facturas dos módulos — restaurante, hotel, salão, oficina, e as
 * vendas repostas pelo PWA offline — eram assinadas COM o hash do documento
 * anterior, mas gravavam só o `saft_hash`: ficavam com a assinatura certa e
 * sem registo de a que documento se ligavam.
 *
 * Parecia inofensivo. Não era: ao transmitir à AGT, o SignatureService
 * RECALCULAVA o hash a partir do `hash_previous` guardado — vazio — e
 * sobrepunha o correcto. A cadeia chegava intacta à véspera da declaração e
 * partia-se ao ser declarada, em todas as facturas a seguir de uma vez.
 */
class CadeiaDeAssinaturasTest extends TenantTestCase
{
    private Product $artigo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        $this->artigo = Product::create([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Artigo',
            'code'         => 'ART-CAD',
            'sale_price'   => 1000,
            'type'         => 'produto',
            'manage_stock' => false,
            'is_active'    => true,
        ]);
    }

    private function emitir(float $preco = 1000): SalesInvoice
    {
        return app(ModuleInvoiceService::class)->emitir([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'lines'     => [[
                'product_id' => $this->artigo->id,
                'name'       => $this->artigo->name,
                'quantity'   => 1,
                'unit_price' => $preco,
            ]],
            'invoice_type' => 'FR',
            'status'       => 'paid',
        ]);
    }

    /** @test */
    public function a_factura_de_um_modulo_guarda_o_elo_e_nao_so_o_hash(): void
    {
        $primeira = $this->emitir(1000);
        $segunda  = $this->emitir(2000);

        $this->assertNotEmpty($segunda->saft_hash, 'a segunda factura saiu sem assinatura');

        // O que faltava: o elo registado, não só calculado.
        $this->assertSame(
            $primeira->saft_hash,
            $segunda->fresh()->hash_previous,
            'a factura não registou a que documento se liga'
        );

        // E o `hash`, que é o campo que a AGT e o SAFT lêem primeiro.
        $this->assertSame($segunda->saft_hash, $segunda->fresh()->hash);
        $this->assertSame('1', $segunda->fresh()->hash_control);
    }

    /** @test */
    public function a_primeira_factura_da_empresa_liga_se_a_nada_e_isso_e_valido(): void
    {
        $primeira = $this->emitir();

        $this->assertNotEmpty($primeira->saft_hash);
        $this->assertSame('', (string) $primeira->fresh()->hash_previous);
    }

    /** @test */
    public function transmitir_a_agt_nao_reescreve_o_hash_de_um_documento_emitido(): void
    {
        $primeira = $this->emitir(1000);
        $segunda  = $this->emitir(2000);

        $hashAntes = $segunda->fresh()->saft_hash;
        $eloAntes  = $segunda->fresh()->hash_previous;

        // Este é o caminho da transmissão: o AGTService chama signComplete.
        app(SignatureService::class, ['tenantId' => $this->tenant->id])
            ->signComplete($segunda->fresh());

        $depois = $segunda->fresh();

        $this->assertSame($hashAntes, $depois->saft_hash, 'a transmissão reescreveu o hash do documento');
        $this->assertSame($hashAntes, $depois->hash);
        $this->assertSame($eloAntes, $depois->hash_previous, 'a transmissão apagou o elo');

        // E a cadeia continua a ligar depois de transmitida.
        $this->assertSame($primeira->fresh()->saft_hash, $depois->hash_previous);
    }

    /** @test */
    public function a_cadeia_atravessa_series_e_tipos_de_documento(): void
    {
        // A cadeia é UMA por empresa, por ordem de emissão: uma FR liga-se à FT
        // que veio antes dela. Conferi-la por série dá dezenas de erros que não
        // existem — foi o que a primeira versão do verificador fez.
        $ft = app(ModuleInvoiceService::class)->emitir([
            'tenant_id'    => $this->tenant->id,
            'client_id'    => $this->cliente->id,
            'lines'        => [['product_id' => $this->artigo->id, 'name' => 'A', 'quantity' => 1, 'unit_price' => 500]],
            'invoice_type' => 'FT',
            'status'       => 'sent',
        ]);

        $fr = $this->emitir(700);

        $this->assertSame($ft->saft_hash, $fr->fresh()->hash_previous);
    }

    /** @test */
    public function o_verificador_da_cadeia_aprova_o_que_a_emissao_produz(): void
    {
        $this->emitir(1000);
        $this->emitir(2000);
        $this->emitir(3000);

        $this->artisan('agt:verificar-cadeia', ['--tenant' => $this->tenant->id])
            ->assertExitCode(0);
    }
}
