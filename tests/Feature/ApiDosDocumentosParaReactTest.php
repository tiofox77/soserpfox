<?php

namespace Tests\Feature;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\SalesProforma;
use App\Models\Invoicing\SalesQuote;
use App\Models\User;
use App\Services\Invoicing\TiposDeDocumento;
use Tests\TenantTestCase;

/**
 * A lista genérica que serve CINCO documentos.
 *
 * O risco de um controlador genérico é ser genérico também nas permissões: uma
 * rota só que serve cinco tabelas é uma rota só que as pode abrir todas. Estes
 * ensaios provam que não — cada tipo exige a SUA permissão, e o escopo por
 * autor continua a valer em todos.
 */
class ApiDosDocumentosParaReactTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function rota(string $tipo): string
    {
        return "/api/v1/invoicing/react/documentos/{$tipo}";
    }

    private function orcamento(array $por = []): SalesQuote
    {
        return SalesQuote::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'quote_number' => 'ORC/' . random_int(1000, 9999),
            'quote_date' => now()->toDateString(),
            'status' => 'draft',
            'total' => 100,
            'created_by' => $this->user->id,
        ], $por));
    }

    private function fornecedor(): \App\Models\Supplier
    {
        return \App\Models\Supplier::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Fornecedor de Ensaio'],
            ['nif' => (string) random_int(500000000, 599999999), 'is_active' => true]
        );
    }

    private function compra(array $por = []): PurchaseInvoice
    {
        return PurchaseInvoice::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->fornecedor()->id,
            'invoice_number' => 'FC/' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'status' => 'pending',
            'total' => 5000,
            'paid_amount' => 0,
            'created_by' => $this->user->id,
        ], $por));
    }

    /** @test */
    public function um_tipo_inventado_da_404(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $this->getJson($this->rota('facturas-secretas'))->assertNotFound();
    }

    /**
     * CADA TIPO EXIGE A SUA PERMISSÃO.
     *
     * Quem pode ver proformas de venda não passa a ver facturas de compra por
     * a rota ser a mesma.
     *
     * @test
     */
    public function a_permissao_e_por_tipo_e_nao_pela_rota(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $this->getJson($this->rota('proformas-venda'))->assertOk();

        foreach (['orcamentos', 'facturas-compra', 'proformas-compra', 'recibos'] as $outro) {
            $this->getJson($this->rota($outro))->assertForbidden();
            $this->getJson($this->rota($outro) . '/opcoes')->assertForbidden();
        }
    }

    /** Os cinco tipos do registo respondem, cada um com a sua permissão. @test */
    public function os_cinco_tipos_respondem(): void
    {
        foreach (TiposDeDocumento::todos() as $slug => $def) {
            $this->comPermissoes($def['permissao']);

            $this->getJson($this->rota($slug))
                ->assertOk()
                ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);

            $this->getJson($this->rota($slug) . '/opcoes')
                ->assertOk()
                ->assertJsonPath('parte', $def['parte'])
                ->assertJsonPath('tem_saldo', $def['tem_saldo']);
        }
    }

    /** O escopo por autor vale aqui também. @test */
    public function quem_so_ve_os_seus_nao_ve_os_do_colega(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $colega = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $meu = $this->orcamento();
        $dele = $this->orcamento(['created_by' => $colega->id]);

        $ids = collect($this->getJson($this->rota('orcamentos'))->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($meu->id));
        $this->assertFalse($ids->contains($dele->id), 'o orçamento do colega não pode aparecer');
    }

    /** A factura de compra é a única com saldo, e mostra o que FALTA. @test */
    public function so_a_factura_de_compra_traz_saldo(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.view', 'invoicing.sales.proformas.view');

        $f = $this->compra(['total' => 5000, 'paid_amount' => 2000]);

        $linha = collect($this->getJson($this->rota('facturas-compra'))->json('data'))
            ->firstWhere('id', $f->id);

        $this->assertEqualsWithDelta(3000, $linha['saldo'], 0.01, 'mostra o que falta, não o total');

        SalesProforma::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'proforma_number' => 'PRF/' . random_int(1000, 9999),
            'proforma_date' => now()->toDateString(),
            'status' => 'draft',
            'total' => 100,
            'created_by' => $this->user->id,
        ]);

        $daProforma = collect($this->getJson($this->rota('proformas-venda'))->json('data'))->first();

        $this->assertArrayNotHasKey('saldo', $daProforma, 'uma proforma não tem pagamentos');
    }

    /** Uma compra paga não fica a dever, seja qual for o `paid_amount`. @test */
    public function uma_compra_paga_nao_aparece_a_dever(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.view');

        $f = $this->compra(['status' => 'paid', 'total' => 5000, 'paid_amount' => 0]);

        $linha = collect($this->getJson($this->rota('facturas-compra'))->json('data'))
            ->firstWhere('id', $f->id);

        $this->assertSame(0, (int) $linha['saldo']);
    }

    /** Os estados oferecidos no filtro são os que a tabela TEM. @test */
    public function os_estados_do_filtro_saem_dos_dados(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $this->orcamento(['status' => 'accepted']);

        $estados = collect($this->getJson($this->rota('orcamentos') . '/opcoes')->json('estados'))
            ->pluck('valor');

        $this->assertTrue($estados->contains('accepted'));
        $this->assertFalse($estados->contains('overdue'), 'um orçamento não vence');
    }

    /** A procura encontra pelo número e pelo nome da outra parte. @test */
    public function a_procura_apanha_o_numero_e_a_outra_parte(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $o = $this->orcamento(['quote_number' => 'ORC PROCURA/0001']);

        $ids = collect($this->getJson($this->rota('orcamentos') . '?procura=PROCURA')->json('data'))
            ->pluck('id');

        $this->assertTrue($ids->contains($o->id));

        $porCliente = collect(
            $this->getJson($this->rota('orcamentos') . '?procura=' . urlencode($o->client->name))->json('data')
        )->pluck('id');

        $this->assertTrue($porCliente->contains($o->id));
    }

    /* ─── O que as listas em Blade tinham e faltava ───────────────────── */

    /**
     * A COLUNA DO PRAZO: validade nas propostas, vencimento nas compras.
     *
     * A lista em Blade tinha-a, e não é decoração — uma proposta caducada não
     * se converte, e uma compra vencida é dinheiro em atraso. Quem decide que
     * o prazo passou é o SERVIDOR, com o relógio dele.
     *
     * @test
     */
    public function o_prazo_vem_na_linha_e_diz_se_ja_passou(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view', 'invoicing.purchases.invoices.view');

        $caducado = $this->orcamento(['valid_until' => now()->subWeek()->toDateString()]);
        $valido = $this->orcamento(['valid_until' => now()->addWeek()->toDateString()]);

        $this->getJson($this->rota('orcamentos') . '/opcoes')->assertOk()
            ->assertJsonPath('prazo', 'Validade');

        $linhas = collect($this->getJson($this->rota('orcamentos'))->assertOk()->json('data'))
            ->keyBy('id');

        $this->assertTrue($linhas[$caducado->id]['expirado'], 'a validade passada tem de aparecer expirada');
        $this->assertFalse($linhas[$valido->id]['expirado']);

        // Na compra o mesmo campo chama-se «Vencimento» — é o esquema que sabe.
        $this->getJson($this->rota('facturas-compra') . '/opcoes')->assertOk()
            ->assertJsonPath('prazo', 'Vencimento');
    }

    /**
     * AS NOTAS TRAZEM A FACTURA DE ORIGEM E O MOTIVO.
     *
     * A lista em Blade tinha-os em coluna própria: uma nota de crédito sem
     * saber de que factura é não se lê, e uma de devolução conta uma história
     * diferente de uma de correcção.
     *
     * @test
     */
    public function as_notas_trazem_a_factura_de_origem_e_o_motivo(): void
    {
        $this->comPermissoes('invoicing.credit-notes.view');

        $factura = \App\Models\Invoicing\SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT ORIGEM/0001',
            'invoice_date' => now()->toDateString(),
            'status' => 'sent',
            'total' => 5000,
            'created_by' => $this->user->id,
        ]);

        $nota = \App\Models\Invoicing\CreditNote::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $factura->client_id,
            'invoice_id' => $factura->id,
            'credit_note_number' => 'NC/0001',
            'issue_date' => now()->toDateString(),
            'status' => 'issued',
            'reason' => 'return',
            'total' => 1000,
            'created_by' => $this->user->id,
        ]);

        $opcoes = $this->getJson($this->rota('notas-credito') . '/opcoes')->assertOk();

        $this->assertSame('Fatura Origem', $opcoes->json('origem'));
        $this->assertContains('return', collect($opcoes->json('motivos'))->pluck('valor')->all());

        $linha = collect($this->getJson($this->rota('notas-credito'))->assertOk()->json('data'))
            ->firstWhere('id', $nota->id);

        $this->assertSame($factura->id, $linha['origem']['id']);
        $this->assertSame('Devolução', $linha['motivo_rotulo']);

        // E o FILTRO do motivo separa-as.
        $ids = collect($this->getJson($this->rota('notas-credito') . '?motivo=return')->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($nota->id));

        $ids = collect($this->getJson($this->rota('notas-credito') . '?motivo=discount')->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($nota->id));
    }

    /**
     * O FILTRO DO MOTIVO SÓ EXISTE ONDE HÁ MOTIVOS.
     *
     * Num orçamento é recusado em vez de ignorado: ignorado em silêncio,
     * devolvia a lista toda e fazia acreditar que filtrava.
     *
     * @test
     */
    public function o_motivo_nao_se_aceita_onde_nao_existe(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $this->getJson($this->rota('orcamentos') . '?motivo=return')
            ->assertStatus(422)->assertJsonValidationErrors('motivo');

        $this->getJson($this->rota('orcamentos') . '/opcoes')->assertOk()
            ->assertJsonPath('motivos', [])
            ->assertJsonPath('origem', null);
    }

    /**
     * CONVERTER UMA PROPOSTA EM FACTURA — o botão que a lista em Blade tinha e
     * a migração não trouxe, nem no ecrã nem na API.
     *
     * A factura nasce em RASCUNHO: converter não é emitir.
     *
     * @test
     */
    public function converter_um_orcamento_faz_nascer_uma_factura_em_rascunho(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $o = $this->orcamento();

        // SEM A PERMISSÃO DE FACTURAR, não converte: nasce uma factura, e quem
        // não pode facturar não a faz nascer por este atalho.
        $this->postJson($this->rota('orcamentos') . '/' . $o->id . '/converter')->assertForbidden();

        $this->comPermissoes('invoicing.sales.invoices.create');

        $r = $this->postJson($this->rota('orcamentos') . '/' . $o->id . '/converter')->assertOk();

        $id = $r->json('factura.id');

        $this->assertDatabaseHas('invoicing_sales_invoices', [
            'id' => $id,
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
        ]);

        // E o histórico passa a mostrá-la.
        $h = $this->getJson($this->rota('orcamentos') . '/' . $o->id . '/historico')->assertOk();

        $this->assertCount(1, $h->json('facturas'));
        $this->assertSame($id, $h->json('facturas.0.id'));
    }

    /** Um documento que não se converte responde 404, e não uma factura vazia. @test */
    public function um_documento_que_nao_se_converte_recusa(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.view', 'invoicing.sales.invoices.create');

        $c = $this->compra();

        $this->postJson($this->rota('facturas-compra') . '/' . $c->id . '/converter')->assertNotFound();
        $this->getJson($this->rota('facturas-compra') . '/' . $c->id . '/historico')->assertNotFound();
    }

    /**
     * ELIMINAR — e não o que já foi convertido.
     *
     * Uma proposta convertida em factura não se elimina: a factura aponta para
     * ela e ficaria a referir um documento que já não existe.
     *
     * @test
     */
    public function eliminar_respeita_a_permissao_e_o_que_ja_foi_convertido(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $o = $this->orcamento();

        // Sem a permissão de apagar, o botão nem devia aparecer — e a porta
        // recusa na mesma, que é a guarda que conta.
        $this->getJson($this->rota('orcamentos') . '/opcoes')->assertOk()
            ->assertJsonPath('pode_apagar', false);

        $this->deleteJson($this->rota('orcamentos') . '/' . $o->id)->assertForbidden();

        $this->comPermissoes('invoicing.sales.quotes.delete');

        $this->getJson($this->rota('orcamentos') . '/opcoes')->assertOk()
            ->assertJsonPath('pode_apagar', true);

        // Um já convertido não se apaga, e diz porquê.
        $convertido = $this->orcamento(['status' => 'converted']);

        $this->deleteJson($this->rota('orcamentos') . '/' . $convertido->id)->assertStatus(422);
        $this->assertNotNull(SalesQuote::find($convertido->id));

        // O que ainda dá, apaga-se.
        $this->deleteJson($this->rota('orcamentos') . '/' . $o->id)->assertOk();
        $this->assertNull(SalesQuote::find($o->id));
    }

    /** Uma factura de compra não se elimina por aqui: anula-se. @test */
    public function a_factura_de_compra_nao_se_elimina_por_aqui(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.view', 'invoicing.purchases.invoices.delete');

        $c = $this->compra();

        $this->deleteJson($this->rota('facturas-compra') . '/' . $c->id)->assertNotFound();
        $this->assertNotNull(PurchaseInvoice::find($c->id));
    }
}
