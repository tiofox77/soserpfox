<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\DebitNoteItem;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Invoicing\TaxResolver;
use Tests\TenantTestCase;

/**
 * Nota de Débito — conformidade fiscal.
 *
 * A ND é documento comunicável e entra na cadeia de hash. Estes casos vêm de
 * lacunas reais: o documento ficava acima da soma das suas linhas, a retenção
 * era copiada da factura inteira, e um artigo de outra empresa podia entrar
 * numa nota fiscal desta.
 *
 * O ECRÃ MUDOU, A REGRA NÃO. O carrinho Livewire desapareceu com a migração
 * para React; hoje é `POST /api/v1/invoicing/react/notas/debito` que monta as
 * linhas e chama o `EmissorDeNotas`. É por aí que se prova — o que o carrinho
 * recusava, a API tem de recusar, e o que ele resolvia (a taxa, o código SAFT,
 * a região do adquirente) tem de continuar resolvido do lado do servidor.
 */
class NotaDebitoTest extends TenantTestCase
{
    private const ROTA = '/api/v1/invoicing/react/notas/debito';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes('invoicing.debit-notes.create');

        // Sem série de notas de débito não se emite nota nenhuma — e o
        // TenantTestCase só semeia as de factura e de POS.
        InvoicingSeries::create([
            'tenant_id' => $this->tenant->id,
            'series_code' => 'ND',
            'name' => 'ND (teste)',
            'document_type' => 'debit_note',
            'agt_environment' => 'sandbox',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    /** Emite uma ND com estas linhas e devolve-a já gravada. */
    private function emitir(array $linhas, ?int $clienteId = null): DebitNote
    {
        $resposta = $this->postJson(self::ROTA, [
            'client_id' => $clienteId ?? $this->cliente->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'reason' => 'correction',
            'linhas' => $linhas,
        ])->assertCreated();

        return DebitNote::with('items')->findOrFail($resposta->json('id'));
    }

    public function test_artigo_de_outra_empresa_nao_entra_na_nota(): void
    {
        $outra = Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => (string) random_int(700000000, 799999999),
            'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $alheio = Product::create([
            'tenant_id' => $outra->id,
            'name' => 'Artigo alheio', 'code' => 'AL' . strtoupper(substr(uniqid(), -6)),
            'type' => 'produto', 'price' => 50000, 'is_active' => true,
        ]);

        // O id vem do browser: um pedido forjado punha o nome, o preço e o
        // imposto de um artigo de OUTRA empresa dentro de um documento fiscal
        // desta — assinado, encadeado e comunicado à AGT.
        $nota = $this->emitir([[
            'product_id' => $alheio->id,
            'description' => 'Linha escrita à mão',
            'quantity' => 1,
            'price' => 1000,
        ]]);

        $this->assertSame(0,
            DebitNoteItem::where('debit_note_id', $nota->id)->where('product_id', $alheio->id)->count(),
            'um artigo de outra empresa não pode entrar num documento fiscal desta'
        );

        $linha = $nota->items->first();

        $this->assertNull($linha->product_id);
        $this->assertNotSame('Artigo alheio', $linha->description,
            'nem o nome dele pode viajar para cá');
    }

    public function test_artigo_da_empresa_entra_com_o_imposto_do_regime(): void
    {
        $artigo = $this->produtoComStock(0, 10000);

        // Sem preço no pedido: o preço é o do catálogo, não o que o browser
        // resolver mandar.
        $linha = $this->emitir([['product_id' => $artigo->id, 'quantity' => 1]])->items->first();

        $this->assertSame($artigo->id, (int) $linha->product_id);
        $this->assertEqualsWithDelta(10000, (float) $linha->unit_price, 0.01);
        $this->assertEquals(14, (float) $linha->tax_rate);
        $this->assertSame('NOR', $linha->tax_code, 'o código SAFT acompanha a taxa');
        $this->assertContains($linha->tax_country_region, ['AO', 'AO-CAB']);
    }

    public function test_codigo_saft_acompanha_taxa_reduzida(): void
    {
        $reduzida = \App\Models\Invoicing\Tax::create([
            'tenant_id' => $this->tenant->id, 'code' => 'IVA7',
            'name' => 'IVA 7%', 'rate' => 7, 'type' => 'iva',
            'saft_code' => 'RED', 'saft_type' => 'RED', 'is_active' => true,
        ]);

        $artigo = $this->produtoComStock(0, 10000);
        $artigo->update(['tax_rate_id' => $reduzida->id]);
        TaxResolver::clearCache();

        $linha = $this->emitir([['product_id' => $artigo->id, 'quantity' => 1]])->items->first();

        $this->assertEquals(7, (float) $linha->tax_rate);
        $this->assertSame('RED', $linha->tax_code,
            'com NOR fixo, 7% era declarado à AGT como taxa normal');
    }

    public function test_cliente_de_cabinda_leva_regiao_propria(): void
    {
        $cabinda = Client::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Cabinda',
            'nif' => (string) random_int(800000000, 899999999),
            'province' => 'Cabinda',
            'type' => 'pessoa_fisica', 'is_active' => true,
        ]);

        $artigo = $this->produtoComStock(0, 10000);

        $linha = $this->emitir([['product_id' => $artigo->id, 'quantity' => 1]], $cabinda->id)
            ->items->first();

        // Cabinda tem regime próprio e a AGT identifica-o por AO-CAB. A API
        // fixava 'AO' e declarava como continental uma nota de Cabinda — o
        // ecrã Livewire resolvia-o pelo cliente e a API não.
        $this->assertSame('AO-CAB', $linha->tax_country_region);
    }

    /**
     * A BASE É LÍQUIDA DE DESCONTOS.
     *
     * Guarda contra a regressão do defeito bloqueante: net_total usava o
     * subtotal BRUTO enquanto as linhas descontavam, e o cliente era debitado
     * a mais exactamente no valor do desconto.
     */
    public function test_o_documento_fecha_com_a_base_liquida_de_descontos(): void
    {
        $artigo = $this->produtoComStock(0, 1000);

        $nota = $this->emitir([[
            'product_id' => $artigo->id,
            'quantity' => 1,
            'price' => 1000,
            'discount_percent' => 20,
        ]]);

        $this->assertEqualsWithDelta(800, (float) $nota->net_total, 0.01,
            'a base é a soma das linhas JÁ descontadas');
        $this->assertEqualsWithDelta(112, (float) $nota->tax_amount, 0.01, '14% sobre 800');
        $this->assertEqualsWithDelta(912, (float) $nota->total, 0.01,
            'e o cliente é debitado do que a nota diz, não do bruto');
    }

    /** E a conta vive num sítio só: o emissor, que a API chama. */
    public function test_a_base_liquida_fecha_se_no_emissor_e_nao_no_controlador(): void
    {
        $servico = file_get_contents(app_path('Services/Invoicing/EmissorDeNotas.php'));

        $this->assertStringContainsString('$netLiquido', $servico);
        $this->assertStringContainsString("\$nota->net_total = round(\$netLiquido, 2)", $servico);

        $api = file_get_contents(app_path('Http/Controllers/Api/Invoicing/NotasApiController.php'));

        $this->assertStringContainsString('emitirDebito', $api, 'a API tem de passar pelo emissor');
        $this->assertStringNotContainsString("'net_total'", $api,
            'os totais fecham-se no emissor — um segundo sítio a decidi-los diverge à primeira alteração');
    }

    public function test_a_retencao_e_proporcional_a_base_da_nota(): void
    {
        // Copiar o withholding_tax_amount da factura fazia uma ND de 10.000 Kz
        // sobre uma factura de 1.000.000 Kz declarar 65.000 Kz de retenção.
        $servico = file_get_contents(app_path('Services/Invoicing/EmissorDeNotas.php'));

        $this->assertStringContainsString('$proporcionalA * $percentagem / 100', $servico);

        $api = file_get_contents(app_path('Http/Controllers/Api/Invoicing/NotasApiController.php'));

        $this->assertStringNotContainsString('withholding_tax_amount', $api,
            'a retenção não se copia do lado do controlador: é o emissor que a calcula');
    }
}
