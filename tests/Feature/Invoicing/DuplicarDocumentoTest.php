<?php

namespace Tests\Feature\Invoicing;

use App\Livewire\Invoicing\Sales\InvoiceCreate;
use App\Livewire\Invoicing\Sales\ProformaCreate;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\SalesProforma;
use App\Models\Product;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Duplicar um documento: aproveitar o trabalho, não a identidade.
 *
 * O ganho é óbvio — quem factura o mesmo cliente todos os meses deixa de
 * reescrever doze linhas. O RISCO é que não é óbvio, e é caro: se o duplicado
 * herdar o número, a série ou o hash do original, nascem dois documentos com a
 * mesma identidade fiscal. Duas realidades para a mesma venda.
 *
 * Por isso o ensaio central aqui não é "os dados vieram" — é "estes campos
 * NÃO vieram", campo a campo. Um `loadX()` que um dia passe a ler o
 * `invoice_number` partiria isto imediatamente, que é exactamente o que se
 * quer.
 */
class DuplicarDocumentoTest extends TenantTestCase
{
    private Product $artigo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        $this->artigo = Product::create([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Artigo a duplicar',
            'code'         => 'ART-DUP',
            'price'        => 2500,
            'type'         => 'produto',
            'manage_stock' => false,
            'is_active'    => true,
        ]);
    }

    private function facturaEmitida(): SalesInvoice
    {
        $factura = SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->cliente->id,
            'warehouse_id'   => $this->armazem->id,
            'created_by'     => $this->user->id,
            'invoice_number' => 'FT A/000042',
            'invoice_type'   => 'FT',
            'invoice_date'   => now()->subMonths(3),
            'due_date'       => now()->subMonths(3)->addDays(30),
            'status'         => 'paid',
            'notes'          => 'Avença mensal',
            'terms'          => 'Pagamento a 30 dias',
            'subtotal'       => 5000,
            'tax_amount'     => 700,
            'total'          => 5700,
            // A identidade fiscal, que é o que não pode viajar.
            'saft_hash'      => 'HASH-DO-ORIGINAL',
            'hash'           => 'HASH-DO-ORIGINAL',
            'hash_previous'  => 'HASH-ANTERIOR',
            'atcud'          => 'ABC123-42',
            'hash_control'   => '1',
        ]);

        SalesInvoiceItem::create([
            'tenant_id'         => $this->tenant->id,
            'sales_invoice_id'  => $factura->id,
            'product_id'        => $this->artigo->id,
            'product_name'      => $this->artigo->name,
            'quantity'          => 2,
            'unit_price'        => 2500,
            'tax_rate'          => 14,
            'discount_percent'  => 0,
            'subtotal'          => 5000,
            'tax_amount'        => 700,
            'total'             => 5700,
        ]);

        return $factura->fresh();
    }

    /** @test */
    public function a_factura_duplicada_traz_o_conteudo_comercial(): void
    {
        $origem = $this->facturaEmitida();

        Livewire::withQueryParams(['duplicar' => $origem->id])
            ->test(InvoiceCreate::class)
            ->assertSet('client_id', $origem->client_id)
            ->assertSet('warehouse_id', $origem->warehouse_id)
            ->assertSet('notes', 'Avença mensal')
            ->assertSet('terms', 'Pagamento a 30 dias')
            ->assertSet('invoice_type', 'FT');
    }

    /** @test */
    public function a_factura_duplicada_nasce_nova_e_nao_como_edicao(): void
    {
        $origem = $this->facturaEmitida();

        Livewire::withQueryParams(['duplicar' => $origem->id])
            ->test(InvoiceCreate::class)
            // Se `isEdit` viesse ligado, gravar escrevia POR CIMA do original
            // — e o original desaparecia sem uma palavra.
            ->assertSet('isEdit', false)
            ->assertSet('invoiceId', null)
            ->assertSet('duplicadoDe', 'FT A/000042');
    }

    /** @test */
    public function o_duplicado_e_de_hoje_e_nao_do_periodo_do_original(): void
    {
        $origem = $this->facturaEmitida();

        Livewire::withQueryParams(['duplicar' => $origem->id])
            ->test(InvoiceCreate::class)
            // A data do original é de há três meses: um período fiscal que já
            // fechou. Herdá-la punha o documento novo no sítio errado.
            ->assertSet('invoice_date', now()->format('Y-m-d'));
    }

    /**
     * O ENSAIO QUE IMPORTA.
     *
     * Nenhum campo que dê identidade fiscal ao original pode aparecer no
     * formulário do duplicado. Não é uma questão de arrumação: dois documentos
     * com o mesmo número são duas verdades para a mesma venda, e a AGT vê as
     * duas.
     *
     * @test
     */
    public function o_duplicado_nao_herda_nada_da_identidade_fiscal(): void
    {
        $origem = $this->facturaEmitida();

        $componente = Livewire::withQueryParams(['duplicar' => $origem->id])
            ->test(InvoiceCreate::class);

        $doOriginal = [
            'FT A/000042',      // número
            'HASH-DO-ORIGINAL', // hash / saft_hash
            'HASH-ANTERIOR',    // elo da cadeia
            'ABC123-42',        // ATCUD
        ];

        // Varre-se TODA a propriedade pública do componente, e não uma lista
        // escolhida a dedo: uma propriedade nova que um dia passe a trazer o
        // número do original tem de fazer isto falhar sem ninguém se lembrar
        // de a acrescentar aqui.
        $instancia = $componente->instance();
        $publicas = (new \ReflectionObject($instancia))->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($publicas as $reflexao) {
            $propriedade = $reflexao->getName();
            $valor = $reflexao->isInitialized($instancia) ? $reflexao->getValue($instancia) : null;

            if (!is_scalar($valor) || $propriedade === 'duplicadoDe') {
                continue;
            }

            foreach ($doOriginal as $marca) {
                $this->assertNotSame(
                    $marca,
                    (string) $valor,
                    "A propriedade `{$propriedade}` trouxe `{$marca}` do documento original."
                );
            }
        }
    }

    /** @test */
    public function duplicar_um_documento_de_outra_empresa_nao_e_possivel(): void
    {
        $outra = \App\Models\Tenant::create([
            'name'      => 'Empresa Vizinha',
            'slug'      => 'vizinha-' . uniqid(),
            'nif'       => (string) random_int(500000000, 599999999),
            'email'     => 'v' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);

        $alheia = SalesInvoice::create([
            'tenant_id'      => $outra->id,
            'client_id'      => $this->cliente->id,
            'warehouse_id'   => $this->armazem->id,
            'created_by'     => $this->user->id,
            'invoice_number' => 'FT X/000001',
            'invoice_type'   => 'FT',
            'invoice_date'   => now(),
            'status'         => 'sent',
            'total'          => 1000,
        ]);

        // O `duplicar` vem da barra de endereço: qualquer id se escreve à mão.
        // Sem o escopo da empresa, isto copiava a factura do vizinho — cliente,
        // preços e condições incluídos.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::withQueryParams(['duplicar' => $alheia->id])->test(InvoiceCreate::class);
    }

    /** @test */
    public function a_proforma_de_venda_tambem_duplica(): void
    {
        $origem = SalesProforma::create([
            'tenant_id'       => $this->tenant->id,
            'client_id'       => $this->cliente->id,
            'warehouse_id'    => $this->armazem->id,
            'created_by'      => $this->user->id,
            'proforma_number' => 'PP A/000007',
            'proforma_date'   => now()->subMonths(2),
            'valid_until'     => now()->subMonth(),
            'status'          => 'sent',
            'notes'           => 'Proposta anual',
            'subtotal'        => 3000,
            'tax_amount'      => 420,
            'total'           => 3420,
        ]);

        Livewire::withQueryParams(['duplicar' => $origem->id])
            ->test(ProformaCreate::class)
            ->assertSet('client_id', $origem->client_id)
            ->assertSet('notes', 'Proposta anual')
            ->assertSet('isEdit', false)
            ->assertSet('duplicadoDe', 'PP A/000007')
            // O prazo de validade do original já expirou. Um duplicado que o
            // herdasse nascia inválido.
            ->assertSet('proforma_date', now()->format('Y-m-d'));
    }
}
