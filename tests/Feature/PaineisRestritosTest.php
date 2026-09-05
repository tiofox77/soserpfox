<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\User;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Painéis: cada um vê os seus números, e só os cartões a que tem direito.
 *
 * O painel da facturação somava a empresa inteira para quem lá entrasse, e
 * a página inicial mostrava a toda a gente o plano, o valor e a data de
 * renovação — informação de quem paga, não de quem vende.
 */
class PaineisRestritosTest extends TenantTestCase
{
    private function colega(): User
    {
        $colega = User::create([
            'name' => 'Colega '.uniqid(),
            'email' => uniqid().'@exemplo.ao',
            'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id,
        ]);

        $colega->tenants()->syncWithoutDetaching([$this->tenant->id]);

        return $colega;
    }

    private function factura(int $autor, float $valor): SalesInvoice
    {
        $cliente = Client::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente '.uniqid(),
            'email' => uniqid().'@cliente.ao',
        ]);

        return SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $cliente->id,
            'invoice_number' => 'FT '.uniqid(),
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'pending',
            'subtotal' => $valor,
            'total' => $valor,
            'created_by' => $autor,
        ]);
    }

    /** @test */
    public function o_painel_da_facturacao_conta_so_as_do_proprio(): void
    {
        $colega = $this->colega();
        $this->factura($this->user->id, 1000);
        $this->factura($colega->id, 9000);
        $this->factura($colega->id, 5000);

        $this->comPermissoes('invoicing.dashboard.view');
        $this->actingAs($this->user);

        $ecra = Livewire::test(\App\Livewire\Invoicing\InvoicingDashboard::class);
        $stats = $ecra->viewData('stats');
        $documentos = $ecra->viewData('documents');

        $this->assertSame(1000.0, (float) $stats['total_invoiced']);
        $this->assertSame(1, (int) $documentos['invoices']);
    }

    /** @test */
    public function com_a_permissao_o_painel_soma_a_empresa_toda(): void
    {
        $colega = $this->colega();
        $this->factura($this->user->id, 1000);
        $this->factura($colega->id, 9000);

        $this->comPermissoes('invoicing.dashboard.view', 'invoicing.documents.all');
        $this->actingAs($this->user);

        $ecra = Livewire::test(\App\Livewire\Invoicing\InvoicingDashboard::class);
        $stats = $ecra->viewData('stats');
        $documentos = $ecra->viewData('documents');

        $this->assertSame(10000.0, (float) $stats['total_invoiced']);
        $this->assertSame(2, (int) $documentos['invoices']);
    }

    /**
     * As listas do painel — pendentes, melhores clientes, actividade — também.
     *
     * @test
     */
    public function as_listas_do_painel_seguem_a_mesma_regra(): void
    {
        $colega = $this->colega();
        $minha = $this->factura($this->user->id, 1000);
        $dela = $this->factura($colega->id, 9000);

        $this->comPermissoes('invoicing.dashboard.view');
        $this->actingAs($this->user);

        $ecra = Livewire::test(\App\Livewire\Invoicing\InvoicingDashboard::class);

        $pendentes = $ecra->viewData('pendingInvoices')->pluck('id')->all();
        $recentes = $ecra->viewData('recentActivities')->pluck('id')->all();

        $this->assertContains($minha->id, $pendentes);
        $this->assertNotContains($dela->id, $pendentes);
        $this->assertNotContains($dela->id, $recentes);
    }

    /** @test */
    public function quem_nao_trata_do_pacote_nao_ve_o_plano_na_pagina_inicial(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');
        $this->actingAs($this->user);

        $resposta = $this->get(route('home'));

        $resposta->assertOk();
        $resposta->assertDontSee('Subscrição');
        $resposta->assertDontSee('Renovação');
    }

    /** @test */
    public function quem_trata_do_pacote_continua_a_ver_o_plano(): void
    {
        $this->comPermissoes('billing.manage');
        $this->actingAs($this->user);

        $resposta = $this->get(route('home'));

        $resposta->assertOk();
        $resposta->assertSee('Subscrição');
    }

    /** O ajudante que protege um número: mostra-o a quem pode, esconde-o a quem não. */
    public function test_valor_protegido_esconde_a_quem_nao_pode(): void
    {
        $this->comPermissoes('workshop.reports.view');
        $this->actingAs($this->user);

        $this->assertSame('1.500,00', valorProtegido(1500, 'workshop.reports.view'));
        $this->assertSame('•••', valorProtegido(1500, 'hotel.reports.view'));
    }

    /** E o ajudante que diz se está preso ao próprio trabalho. */
    public function test_so_ve_o_seu_segue_a_permissao_dos_documentos(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');
        $this->actingAs($this->user);
        $this->assertTrue(soVeOSeu());

        $this->comPermissoes('invoicing.documents.all');
        $this->actingAs($this->user->fresh());
        $this->assertFalse(soVeOSeu());
    }
}
