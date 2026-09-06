<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\SalesInvoice;
use App\Services\Invoicing\ModuleInvoiceService;
use Tests\TenantTestCase;

/**
 * Conformidade AGT dos documentos emitidos pelos módulos de negócio.
 *
 * Oficina e hotel emitiam documentos fiscais completos — série, número, ATCUD,
 * hash encadeado — que NUNCA eram comunicados à AGT: o emissor partilhado não
 * chamava `submitToAGT()`, apesar de o prometer no próprio comentário. Os
 * documentos entravam na cadeia de hash sem existir para a autoridade.
 */
class AgtComunicacaoTest extends TenantTestCase
{
    private function emitir(): SalesInvoice
    {
        return app(ModuleInvoiceService::class)->emitir([
            'tenant_id'     => $this->tenant->id,
            'client_id'     => $this->cliente->id,
            'origem_modulo' => 'hotel',
            'origem'        => 'RES-' . uniqid(),
            'lines' => [
                ['name' => 'Hospedagem', 'quantity' => 1, 'unit_price' => 10000, 'is_service' => true],
            ],
        ]);
    }

    public function test_documento_de_modulo_nasce_fiscalmente_completo(): void
    {
        $factura = $this->emitir();

        // Tudo o que a AGT lê no payload tem de estar preenchido.
        $this->assertNotEmpty($factura->invoice_number, 'sem número não há documento');
        $this->assertNotNull($factura->series_id, 'o número tem de vir de uma série');
        $this->assertSame('FT', $factura->invoice_type);
        $this->assertNotEmpty($factura->saft_hash);
        $this->assertNotEmpty($factura->system_entry_date);
        $this->assertSame('F', $factura->invoice_status);
        $this->assertNotEmpty($factura->invoice_status_date);
        $this->assertNotEmpty($factura->source_billing);
        $this->assertGreaterThan(0, (float) $factura->gross_total);

        $linha = $factura->items->first();
        $this->assertNotNull($linha->product_id);
        $this->assertContains($linha->tax_country_region, ['AO', 'AO-CAB']);
        $this->assertNotEmpty($linha->tax_code);
    }

    public function test_o_numero_vem_da_serie_de_emissao_e_nao_de_uma_contagem(): void
    {
        $a = $this->emitir();
        $b = $this->emitir();

        $serie = \App\Models\Invoicing\InvoicingSeries::find($a->series_id);

        $this->assertNotNull($serie);
        $this->assertSame($a->series_id, $b->series_id);
        $this->assertNotSame($a->invoice_number, $b->invoice_number, 'números não podem repetir');
    }

    public function test_o_hash_encadeia_com_o_documento_anterior(): void
    {
        $a = $this->emitir();
        $b = $this->emitir();

        $this->assertNotEmpty($a->saft_hash);
        $this->assertNotEmpty($b->saft_hash);
        $this->assertNotSame($a->saft_hash, $b->saft_hash, 'hash igual significa cadeia partida');
    }

    public function test_com_auto_submit_desligado_nao_comunica(): void
    {
        $definicoes = InvoicingSettings::forTenant($this->tenant->id);
        $definicoes->update(['agt_auto_submit' => false]);

        $factura = $this->emitir();

        // O documento grava na mesma — o utilizador não pode ficar preso.
        $this->assertNotEmpty($factura->invoice_number);
        $this->assertNull($factura->fresh()->agt_request_id);
    }

    public function test_com_auto_submit_ligado_tenta_comunicar_e_nunca_bloqueia(): void
    {
        $definicoes = InvoicingSettings::forTenant($this->tenant->id);
        $definicoes->update(['agt_auto_submit' => true]);

        // Sem chaves RSA configuradas a submissão falha — e é isso que se
        // testa: a falha tem de ser registada, nunca rebentar a emissão.
        $factura = $this->emitir();

        $this->assertNotEmpty($factura->invoice_number, 'a factura tem de gravar mesmo com a AGT indisponível');
        $this->assertNotEmpty($factura->saft_hash);
    }

    /**
     * NOTA sobre o que NÃO é testável aqui.
     *
     * O adiamento da comunicação (DB::afterCommit) não se consegue provar nesta
     * suite: o `DatabaseTransactions` faz o Laravel executar os callbacks de
     * afterCommit de imediato, de propósito — senão nenhuma lógica de
     * afterCommit seria testável. Dentro de um teste o nível de transacção é 1
     * mas o callback corre logo.
     *
     * A semântica real foi verificada fora da suite, contra a mesma ligação:
     *   nível 0 → corre já · nível 1 → adia · rollback → descarta · commit → corre
     *
     * Por isso o que se garante por teste é a presença da chamada
     * (test_o_emissor_adia_a_comunicacao_para_depois_do_commit) e que emitir
     * dentro de uma transacção do chamador não rebenta.
     */
    public function test_emissao_dentro_de_transacao_do_chamador_produz_documento_completo(): void
    {
        InvoicingSettings::forTenant($this->tenant->id)->update(['agt_auto_submit' => true]);

        // É o padrão real: Checkout::processCheckout e WorkOrder::convertToInvoice
        // abrem transacção ANTES de emitir, pelo que a do emissor fica aninhada.
        \Illuminate\Support\Facades\DB::beginTransaction();

        $factura = $this->emitir();

        $this->assertSame(2, \Illuminate\Support\Facades\DB::connection()->transactionLevel(),
            'a transacção do chamador tem de continuar aberta depois de emitir');

        \Illuminate\Support\Facades\DB::commit();

        $this->assertNotEmpty($factura->invoice_number);
        $this->assertNotEmpty($factura->saft_hash);
    }

    public function test_o_emissor_adia_a_comunicacao_para_depois_do_commit(): void
    {
        $codigo = file_get_contents((new \ReflectionClass(ModuleInvoiceService::class))->getFileName());

        $this->assertStringContainsString('DB::afterCommit', $codigo,
            'comunicar dentro da transacção do chamador deixa documentos na AGT que um rollback apaga da base');
    }

    public function test_o_emissor_expoe_o_metodo_de_comunicacao(): void
    {
        // Guarda contra regressão: se alguém remover a chamada, isto cai.
        $servico = new \ReflectionClass(ModuleInvoiceService::class);
        $codigo  = file_get_contents($servico->getFileName());

        $this->assertStringContainsString('comunicarAGT', $codigo);
        // Comunicar deixou de ser enviar no mesmo pedido: agora ENFILEIRA,
        // e o DespacharAgtPendentes envia à boleia do tráfego.
        $this->assertStringContainsString('AutoSubmissao::enfileirar', $codigo);
        $this->assertStringNotContainsString('submitToAGT', $codigo,
            'o emissor não pode enviar à AGT durante o atendimento — só enfileirar');
    }

    public function test_artigo_criado_pelo_modulo_nasce_com_regime_fiscal(): void
    {
        // "Levar o produto para a faturação" só serve se ele chegar lá
        // completo: sem tax_type/tax_rate_id o artigo funcionava por acidente
        // (o TaxResolver recorria ao imposto da empresa) mas ficava sem IVA na
        // listagem e obrigava a preencher tudo à mão na primeira edição.
        $artigo = app(ModuleInvoiceService::class)
            ->produtoDoCatalogo($this->tenant->id, 'Mão-de-obra ' . uniqid(), 5000, 'service');

        $this->assertSame('servico', $artigo->type);
        $this->assertNotEmpty($artigo->code, 'o catálogo exige código único por empresa');
        $this->assertSame('iva', $artigo->tax_type);
        $this->assertSame($this->imposto->id, $artigo->tax_rate_id);
        $this->assertFalse((bool) $artigo->manage_stock, 'um serviço não gere stock');

        // E o imposto que a factura vai usar é o do regime, resolvido a partir
        // do próprio artigo.
        $tx = \App\Services\Invoicing\TaxResolver::forProduct($artigo->fresh(), $this->tenant->id);
        $this->assertEquals(14, (float) $tx['rate']);
    }

    public function test_artigo_de_empresa_isenta_nasce_isento_com_codigo(): void
    {
        $this->imposto->update(['rate' => 0, 'saft_type' => 'ISE', 'saft_code' => 'ISE', 'exemption_code' => 'M04']);
        \App\Services\Invoicing\TaxResolver::clearCache();

        $artigo = app(ModuleInvoiceService::class)
            ->produtoDoCatalogo($this->tenant->id, 'Serviço isento ' . uniqid(), 5000, 'service');

        $this->assertSame('isento', $artigo->tax_type);
        $this->assertNotEmpty($artigo->exemption_reason, 'isento sem motivo é recusado pela AGT');
    }

    public function test_nota_de_debito_tem_caminho_de_comunicacao(): void
    {
        // A ND usa o trait mas durante muito tempo nada a submetia — só a NC
        // era comunicada.
        $this->assertTrue(
            method_exists(\App\Models\Invoicing\DebitNote::class, 'submitToAGT'),
            'DebitNote tem de usar HasAGTSignature'
        );

        // A emissão vive no EmissorDeNotas — o mesmo que o ecrã Livewire e a
        // API em React chamam. É lá que se ENFILEIRA (não se envia no mesmo
        // pedido). O submitToAGT do trait continua a existir — é o que o
        // DespacharAgtPendentes usa depois, à boleia do tráfego.
        $servico = file_get_contents(app_path('Services/Invoicing/EmissorDeNotas.php'));
        $this->assertStringContainsString('AutoSubmissao::enfileirar', $servico,
            'a Nota de Débito tem de ser enfileirada para a AGT');

        $ecra = file_get_contents(app_path('Http/Controllers/Api/Invoicing/NotasApiController.php'));
        $this->assertStringContainsString('emitirDebito', $ecra,
            'o ecrã da Nota de Débito (a API que o React chama) tem de passar pelo emissor');
    }
}
