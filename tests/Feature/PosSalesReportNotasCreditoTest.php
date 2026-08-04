<?php

namespace Tests\Feature;

use App\Livewire\POS\SalesReport;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\SalesInvoice;
use App\Services\POS\PosSalesReportQuery;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Notas de crédito no mapa de vendas do POS.
 *
 * O mapa lia só as facturas: as devoluções não apareciam como linha nem
 * desciam do total, e quem lia o mapa não tinha como notar a diferença. Numa
 * das empresas eram 513.570 Kz de documentos anulados ou creditados a contar
 * como receita.
 */
class PosSalesReportNotasCreditoTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.pos.reports', 'invoicing.pos.reports.all')
             ->comModulo('invoicing');
    }

    private function factura(float $total, string $estado = 'paid'): SalesInvoice
    {
        return SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->cliente->id,
            'invoice_number' => 'FR ' . strtoupper(substr(uniqid(), -8)),
            'invoice_date'   => now()->toDateString(),
            'status'         => $estado,
            'subtotal'       => $total,
            'tax_amount'     => 0,
            'total'          => $total,
            'payment_method' => 'cash',
            'created_by'     => $this->user->id,
        ]);
    }

    private function nota(SalesInvoice $factura, float $total): CreditNote
    {
        return CreditNote::create([
            'tenant_id'          => $this->tenant->id,
            'client_id'          => $this->cliente->id,
            'invoice_id'         => $factura->id,
            'credit_note_number' => 'NC ' . strtoupper(substr(uniqid(), -8)),
            'issue_date'         => now()->toDateString(),
            'status'             => 'issued',
            'reason'             => 'return',
            'subtotal'           => $total,
            'tax_amount'         => 0,
            'total'              => $total,
            'type'               => 'total',
            'created_by'         => $this->user->id,
        ]);
    }

    private function mapa()
    {
        return Livewire::test(SalesReport::class)
            ->set('startDate', now()->subYear()->toDateString())
            ->set('endDate', now()->addYear()->toDateString());
    }

    public function test_a_nota_de_credito_aparece_na_listagem(): void
    {
        $f = $this->factura(10000);
        $n = $this->nota($f, 4000);

        $this->mapa()
            ->assertSee($f->invoice_number)
            ->assertSee($n->credit_note_number);
    }

    public function test_o_filtro_separa_facturas_de_notas(): void
    {
        $f = $this->factura(10000);
        $n = $this->nota($f, 4000);

        // Asserção sobre os dados e não sobre o HTML: a linha de uma nota de
        // crédito mostra "sobre {factura}", portanto o número da factura aparece
        // no ecrã mesmo quando ela própria está filtrada fora.
        $tipos = fn ($c) => collect($c->viewData('documentos')->items())->pluck('doc_tipo')->unique()->values()->all();

        $so = $this->mapa()->set('documentType', 'FR');
        $this->assertSame(['FR'], $tipos($so));
        $this->assertTrue(collect($so->viewData('documentos')->items())
            ->contains(fn ($d) => $d->numero === $f->invoice_number));

        $so = $this->mapa()->set('documentType', 'NC');
        $this->assertSame(['NC'], $tipos($so));
        $this->assertTrue(collect($so->viewData('documentos')->items())
            ->contains(fn ($d) => $d->numero === $n->credit_note_number));

        $ambos = $this->mapa()->set('documentType', '');
        $this->assertEqualsCanonicalizing(['FR', 'NC'], $tipos($ambos));
    }

    public function test_a_devolucao_desce_do_liquido(): void
    {
        $f = $this->factura(10000);
        $this->nota($f, 4000);

        $t = $this->mapa()->viewData('totais');

        $this->assertEquals(10000, $t['bruto']);
        $this->assertEquals(4000, $t['devolvido']);
        $this->assertEquals(6000, $t['liquido'], 'líquido = bruto − devoluções');
    }

    public function test_uma_factura_creditada_continua_no_bruto(): void
    {
        // A subtileza que fazia falta acertar: se a factura creditada saísse do
        // bruto E a nota de crédito descontasse, a devolução contava duas vezes
        // e o líquido saía a zero em vez de ficar em zero por uma via só.
        $f = $this->factura(10000, 'credited');
        $this->nota($f, 10000);

        $t = $this->mapa()->viewData('totais');

        $this->assertEquals(10000, $t['bruto'], 'a creditada fica no bruto');
        $this->assertEquals(10000, $t['devolvido']);
        $this->assertEquals(0, $t['liquido']);
    }

    public function test_uma_factura_anulada_sai_do_bruto(): void
    {
        // Ao contrário da creditada: uma anulada não chegou a ser venda e não
        // tem nota de crédito a compensá-la.
        $this->factura(10000);
        $this->factura(3000, 'cancelled');

        $t = $this->mapa()->viewData('totais');

        $this->assertEquals(10000, $t['bruto']);
        $this->assertEquals(1, $t['anuladas_n']);
        $this->assertEquals(3000, $t['anulado']);
    }

    public function test_os_totais_nao_seguem_o_filtro_de_tipo(): void
    {
        // Escolher "NC" na lista não pode zerar o bruto: é justamente contra ele
        // que se quer ler a devolução.
        $f = $this->factura(10000);
        $this->nota($f, 4000);

        $t = $this->mapa()->set('documentType', 'NC')->viewData('totais');

        $this->assertEquals(10000, $t['bruto']);
        $this->assertEquals(4000, $t['devolvido']);
    }

    public function test_um_meio_de_pagamento_escolhido_esconde_as_notas(): void
    {
        // As notas de crédito não têm meio de pagamento — a coluna nem existe.
        // Sem isto, filtrar por "dinheiro" devolvia na mesma todas as notas e o
        // filtro parecia avariado.
        $f = $this->factura(10000);
        $n = $this->nota($f, 4000);

        $this->mapa()->set('paymentMethod', 'cash')
            ->assertSee($f->invoice_number)
            ->assertDontSee($n->credit_note_number);
    }

    public function test_uma_nota_anulada_nao_devolve_nada(): void
    {
        $f = $this->factura(10000);
        $n = $this->nota($f, 4000);
        $n->update(['status' => 'cancelled']);

        $t = $this->mapa()->viewData('totais');

        $this->assertEquals(0, $t['devolvido']);
        $this->assertEquals(10000, $t['liquido']);
    }

    public function test_o_mapa_de_uma_empresa_nao_mostra_o_da_outra(): void
    {
        $meu = $this->factura(10000);

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => (string) random_int(600000000, 699999999),
            'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $clienteAlheio = \App\Models\Client::create([
            'tenant_id' => $outra->id, 'name' => 'Alheio',
            'nif' => (string) random_int(300000000, 399999999),
            'type' => 'pessoa_fisica', 'is_active' => true,
        ]);

        $notaAlheia = CreditNote::create([
            'tenant_id' => $outra->id, 'client_id' => $clienteAlheio->id,
            'credit_note_number' => 'NC ALHEIA ' . uniqid(),
            'issue_date' => now()->toDateString(), 'status' => 'issued',
            'reason' => 'return', 'subtotal' => 9999, 'tax_amount' => 0,
            'total' => 9999, 'type' => 'total',
        ]);

        $this->mapa()
            ->assertSee($meu->invoice_number)
            ->assertDontSee($notaAlheia->credit_note_number);

        $t = $this->mapa()->viewData('totais');
        $this->assertEquals(0, $t['devolvido']);
    }

    public function test_um_operador_restrito_nao_ve_as_devolucoes_dos_colegas(): void
    {
        // A restrição por operador tem de valer também para as notas de crédito:
        // sem isso, o mapa restrito passava a mostrar devoluções de colegas.
        //
        // Um utilizador NOVO, só com `invoicing.pos.reports`, em vez de revogar a
        // permissão ao do setUp — revogar deixa o modelo já autenticado com a
        // relação em cache e o teste media o cache, não a guarda.
        $caixa = \App\Models\User::create([
            'name' => 'Caixa', 'email' => 'cx' . uniqid() . '@x.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $this->tenant->id,
        ]);
        $caixa->tenants()->syncWithoutDetaching([$this->tenant->id]);

        // Vendas do próprio (o `factura()` grava created_by = $this->user).
        $minha = $this->factura(10000);
        $minha->update(['created_by' => $caixa->id]);

        // Vendas e devoluções de um colega.
        $doColega = $this->factura(5000);
        $this->nota($doColega, 5000);

        // A asserção é sobre o serviço e não sobre o ecrã: o que interessa aqui
        // é que `only_user_id` restringe os DOIS ramos da união. Quem decide se
        // ele é preenchido é a permissão, e isso já está coberto pelo
        // `applyScope` que existia antes desta alteração.
        $t = (new PosSalesReportQuery($this->tenant->id, [
            'start_date'   => now()->subYear()->toDateString(),
            'end_date'     => now()->addYear()->toDateString(),
            'only_user_id' => $caixa->id,
        ]))->totais();

        $this->assertEquals(10000, $t['bruto'], 'só as próprias vendas');
        $this->assertEquals(0, $t['devolvido'], 'e só as próprias devoluções');
    }

    public function test_o_export_usa_a_mesma_consulta_do_ecra(): void
    {
        $f = $this->factura(10000);
        $this->nota($f, 4000);

        $filtros = [
            'start_date' => now()->subYear()->toDateString(),
            'end_date'   => now()->addYear()->toDateString(),
        ];

        $doServico = (new PosSalesReportQuery($this->tenant->id, $filtros))->totais();
        $doEcra    = $this->mapa()->viewData('totais');

        $this->assertEquals($doEcra['bruto'], $doServico['bruto']);
        $this->assertEquals($doEcra['devolvido'], $doServico['devolvido']);
        $this->assertEquals($doEcra['liquido'], $doServico['liquido']);
    }
}
