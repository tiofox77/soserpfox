<?php

namespace Tests\Feature;

use App\Models\AGT\AGTSubmission;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Services\AGT\AutoSubmissao;
use App\Services\AGT\DespachoPendentes;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

/**
 * Gravar a factura e comunicá-la à AGT são DOIS passos, não um.
 *
 * Emitir uma FT chamava submitToAGT() no mesmo pedido: a factura gravava e o
 * utilizador ficava à espera da AGT — que num dia mau responde a 8 segundos,
 * ou não responde. A venda já estava feita; não há razão para prender quem a
 * fez enquanto o fisco pensa.
 *
 * Agora emitir só ENFILEIRA (marca a submissão como pendente, sem tocar na
 * rede). Quem envia é o DespachoPendentes, à boleia do tráfego, depois de a
 * resposta já ter seguido para o browser.
 */
class AgtEnfileirarTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A rede fica trancada: enfileirar NÃO pode fazer chamada nenhuma.
        // Se fizer, o teste falha com ConnectionException em vez de passar sem
        // se ver o erro.
        Http::preventStrayRequests();
    }

    private function definicoes(bool $automatico): InvoicingSettings
    {
        $d = InvoicingSettings::forTenant($this->tenant->id);
        $d->update(['agt_auto_submit' => $automatico]);

        return $d->fresh();
    }

    private function factura(string $numero = 'FT S/000001'): SalesInvoice
    {
        $f = SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->cliente->id,
            'created_by'     => $this->user->id,
            'invoice_number' => $numero,
            'invoice_type'   => 'FT',
            'invoice_date'   => now()->toDateString(),
            'due_date'       => now()->toDateString(),
            'status'         => 'sent',
            'subtotal'       => 1000, 'net_total' => 1000,
            'tax_payable'    => 140, 'total' => 1140, 'gross_total' => 1140,
        ]);

        SalesInvoiceItem::create([
            'sales_invoice_id' => $f->id,
            'product_name' => 'Artigo', 'description' => 'Artigo',
            'quantity' => 1, 'unit' => 'UN', 'unit_price' => 1000,
            'unit_price_base' => 1000, 'subtotal' => 1000,
            'tax_rate' => 14, 'tax_amount' => 140, 'total' => 1140,
            'credit_amount' => 1000, 'order' => 1,
            'tax_country_region' => 'AO', 'tax_code' => 'NOR',
        ]);

        return $f->fresh();
    }

    private function submissoesDe(SalesInvoice $f)
    {
        return AGTSubmission::withoutGlobalScopes()
            ->where('document_type', SalesInvoice::class)
            ->where('document_id', $f->id);
    }

    // ── enfileirar não envia ───────────────────────────────────────────────

    public function test_enfileirar_cria_a_submissao_pendente_sem_enviar(): void
    {
        $this->definicoes(true);
        $f = $this->factura();

        $r = AutoSubmissao::enfileirar($f);

        $this->assertTrue($r['enfileirado']);
        $this->assertFalse($r['jaEnviado']);

        $sub = $this->submissoesDe($f)->first();
        $this->assertNotNull($sub, 'tinha de ficar uma submissão para o tráfego apanhar');
        $this->assertSame(AGTSubmission::STATUS_PENDING, $sub->status);
        // Não tocou na AGT: sem requestID, sem data de envio.
        $this->assertNull($f->fresh()->agt_request_id);
    }

    public function test_enfileirar_nao_faz_chamada_nenhuma_a_rede(): void
    {
        // preventStrayRequests() no setUp faz isto rebentar se houver HTTP.
        // Se este teste passa, é porque enfileirar não fala com a AGT.
        $this->definicoes(true);

        AutoSubmissao::enfileirar($this->factura());

        $this->assertTrue(true);
    }

    public function test_com_o_envio_desligado_nao_enfileira(): void
    {
        $this->definicoes(false);
        $f = $this->factura();

        $r = AutoSubmissao::enfileirar($f);

        $this->assertFalse($r['enfileirado']);
        $this->assertNull($r['erro'], 'desligado não é erro');
        $this->assertSame(0, $this->submissoesDe($f)->count());
    }

    // ── idempotência: uma factura não se comunica duas vezes ───────────────

    public function test_enfileirar_duas_vezes_nao_duplica(): void
    {
        $this->definicoes(true);
        $f = $this->factura();

        AutoSubmissao::enfileirar($f);
        $segunda = AutoSubmissao::enfileirar($f);

        $this->assertFalse($segunda['enfileirado'], 'a segunda vez não cria nada');
        $this->assertSame(1, $this->submissoesDe($f)->count());
    }

    public function test_nao_enfileira_o_que_ja_foi_validado(): void
    {
        $this->definicoes(true);
        $f = $this->factura();

        AGTSubmission::createForDocument($f, 'FT')
            ->update(['status' => AGTSubmission::STATUS_VALIDATED]);

        $r = AutoSubmissao::enfileirar($f);

        $this->assertFalse($r['enfileirado']);
        $this->assertTrue($r['jaEnviado']);
        $this->assertSame(1, $this->submissoesDe($f)->count());
    }

    public function test_sem_empresa_nao_enfileira(): void
    {
        $f = $this->factura();
        $f->tenant_id = null;

        $r = AutoSubmissao::enfileirar($f, 0);

        $this->assertFalse($r['enfileirado']);
    }

    // ── o tráfego é que envia ──────────────────────────────────────────────

    public function test_o_que_foi_enfileirado_fica_visivel_ao_despacho(): void
    {
        $this->definicoes(true);
        $f = $this->factura();

        AutoSubmissao::enfileirar($f);

        // É exactamente o que o DespacharAgtPendentes pergunta antes de agir.
        $this->assertTrue(
            DespachoPendentes::temTrabalho($this->tenant->id),
            'o despacho pelo tráfego tem de ver a submissão pendente'
        );
    }
}
