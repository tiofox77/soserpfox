<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\DebitNotes\DebitNoteCreate;
use App\Models\Client;
use App\Models\Tenant;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Nota de Débito — conformidade fiscal.
 *
 * A ND é documento comunicável e entra na cadeia de hash. Estes casos vêm de
 * lacunas reais: o documento ficava acima da soma das suas linhas, a retenção
 * era copiada da factura inteira, e um artigo de outra empresa podia entrar
 * numa nota fiscal desta.
 */
class NotaDebitoTest extends TenantTestCase
{
    private function componente()
    {
        return Livewire::test(DebitNoteCreate::class)
            ->set('client_id', $this->cliente->id)
            ->set('issue_date', now()->toDateString())
            ->set('due_date', now()->addDays(30)->toDateString())
            ->set('reason', 'Rectificação de teste');
    }

    public function test_artigo_de_outra_empresa_nao_entra_na_nota(): void
    {
        $outra = Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => (string) random_int(700000000, 799999999),
            'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $alheio = \App\Models\Product::create([
            'tenant_id' => $outra->id,
            'name' => 'Artigo alheio', 'code' => 'AL' . strtoupper(substr(uniqid(), -6)),
            'type' => 'produto', 'price' => 50000, 'is_active' => true,
        ]);

        $c = $this->componente()->call('addProduct', $alheio->id);

        $itens = \Darryldecode\Cart\Facades\CartFacade::session($c->get('cartInstance'))->getContent();

        $this->assertFalse(
            $itens->contains(fn ($i) => (int) $i->id === (int) $alheio->id),
            'um artigo de outra empresa não pode entrar num documento fiscal desta'
        );
    }

    public function test_artigo_da_empresa_entra_com_o_imposto_do_regime(): void
    {
        $artigo = $this->produtoComStock(0, 10000);

        $c = $this->componente()->call('addProduct', $artigo->id);

        $item = \Darryldecode\Cart\Facades\CartFacade::session($c->get('cartInstance'))
            ->getContent()
            ->first();

        $this->assertNotNull($item);
        $this->assertEquals(14, (float) $item->attributes['tax_rate']);
        $this->assertSame('NOR', $item->attributes['tax_code'], 'o código SAFT acompanha a taxa');
        $this->assertContains($item->attributes['tax_country_region'], ['AO', 'AO-CAB']);
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
        \App\Services\Invoicing\TaxResolver::clearCache();

        $c = $this->componente()->call('addProduct', $artigo->fresh()->id);

        $item = \Darryldecode\Cart\Facades\CartFacade::session($c->get('cartInstance'))
            ->getContent()->first();

        $this->assertEquals(7, (float) $item->attributes['tax_rate']);
        $this->assertSame('RED', $item->attributes['tax_code'],
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

        $c = Livewire::test(DebitNoteCreate::class)
            ->set('client_id', $cabinda->id)
            ->call('addProduct', $artigo->id);

        $item = \Darryldecode\Cart\Facades\CartFacade::session($c->get('cartInstance'))
            ->getContent()->first();

        $this->assertSame('AO-CAB', $item->attributes['tax_country_region']);
    }

    public function test_o_codigo_calcula_a_base_liquida_de_descontos(): void
    {
        // Guarda contra regressão do defeito bloqueante: net_total usava o
        // subtotal BRUTO enquanto as linhas descontavam, e o cliente era
        // debitado a mais exactamente no valor do desconto.
        $codigo = file_get_contents(app_path('Livewire/Invoicing/DebitNotes/DebitNoteCreate.php'));

        $this->assertStringContainsString('$ndNetTotal', $codigo);
        $this->assertStringContainsString("\$debitNote->net_total   = round(\$ndNetTotal, 2)", $codigo);
        $this->assertStringNotContainsString("'net_total' => \$totals['subtotal_original']", $codigo);
    }

    public function test_a_retencao_e_proporcional_a_base_da_nota(): void
    {
        // Copiar o withholding_tax_amount da factura fazia uma ND de 10.000 Kz
        // sobre uma factura de 1.000.000 Kz declarar 65.000 Kz de retenção.
        $codigo = file_get_contents(app_path('Livewire/Invoicing/DebitNotes/DebitNoteCreate.php'));

        $this->assertStringContainsString('$ndNetTotal * $percentagem / 100', $codigo);
        $this->assertStringNotContainsString("'withholding_tax_amount'      => \$ret->withholding_tax_amount", $codigo);
    }
}
