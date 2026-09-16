<?php

namespace Tests\Feature\Revenda;

use App\Mail\Revenda\AvisoDaRevenda;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Revenda\LigacaoAoRevendedor;
use App\Services\Revenda\RegraDeComissao;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TenantTestCase;

/**
 * O REVENDEDOR PAGA PELO CLIENTE E O SUPER ADMIN CONFIRMA (16/09/2026) — e os
 * planos gratuitos não passam pelo revendedor; o portal tem barra lateral.
 */
class PagamentosPeloRevendedorTest extends TenantTestCase
{
    private Plan $pago;

    private Plan $gratis;

    private Reseller $r;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->pago = Plan::create(['name' => 'Pago ' . uniqid(), 'slug' => 'pg-' . uniqid(), 'description' => 'x', 'price_monthly' => 20000, 'price_yearly' => 200000,
            'trial_days' => 0, 'max_users' => 5, 'max_companies' => 1, 'is_active' => true, 'is_public' => true, 'order' => 60]);
        $this->gratis = Plan::create(['name' => 'Gratis ' . uniqid(), 'slug' => 'gr-' . uniqid(), 'description' => 'x', 'price_monthly' => 0, 'price_yearly' => 0,
            'trial_days' => 0, 'max_users' => 1, 'max_companies' => 1, 'is_active' => true, 'is_public' => true, 'order' => 61]);

        $this->r = Reseller::create(['name' => 'Paulo Revende', 'email' => 'paulo' . uniqid() . '@exemplo.ao', 'password' => 'Senha-forte-3']);
        $this->r->forceFill(['status' => 'aprovado', 'code' => Reseller::novoCodigo('Paulo'), 'commission' => RegraDeComissao::de(['valor' => 10])->paraGuardar()])->save();
        LigacaoAoRevendedor::ligar($this->tenant, $this->r, 'codigo');
    }

    private function dono(): User
    {
        return User::create(['name' => 'Dono da plataforma', 'email' => 'dono' . uniqid() . '@exemplo.ao', 'password' => 'x', 'is_super_admin' => true, 'is_active' => true]);
    }

    private function factura(string $estado = 'pending'): Invoice
    {
        $sub = $this->tenant->subscriptions()->create(['plan_id' => $this->pago->id, 'status' => 'active', 'amount' => 20000, 'billing_cycle' => 'monthly', 'current_period_start' => now()->subMonth(), 'current_period_end' => now()->addDays(5), 'ends_at' => now()->addDays(5)]);

        return Invoice::create(['tenant_id' => $this->tenant->id, 'subscription_id' => $sub->id, 'invoice_date' => now(), 'due_date' => now()->addDays(5),
            'subtotal' => 20000, 'tax' => 0, 'total' => 20000, 'status' => $estado, 'description' => 'Renovação']);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->create('transferencia.pdf', 40, 'application/pdf');
    }

    public function test_os_planos_gratuitos_nao_passam_pelo_revendedor(): void
    {
        $this->actingAs($this->r, 'revendedor');

        $ids = array_column($this->getJson('/revendedor/api/opcoes')->assertOk()->json('planos'), 'id');
        $this->assertContains($this->pago->id, $ids);
        $this->assertNotContains($this->gratis->id, $ids);

        $this->postJson('/revendedor/api/empresas', [
            'company_name' => 'Loja Sempre Grátis', 'company_nif' => '5' . random_int(100000000, 999999999), 'company_regime' => Tenant::REGIME_GERAL,
            'name' => 'Ana Maria Lopes', 'email' => 'ana' . uniqid() . '@exemplo.ao', 'selected_plan_id' => $this->gratis->id,
        ])->assertStatus(422)->assertJsonValidationErrors('selected_plan_id');

        $this->postJson("/revendedor/api/empresas/{$this->tenant->id}/pedidos", ['plan_id' => $this->gratis->id, 'ciclo' => 'monthly'])
            ->assertStatus(422)->assertJsonValidationErrors('plan_id');
    }

    public function test_o_pedido_pago_pelo_revendedor_aparece_ao_super_admin_com_o_nome_dele_e_a_recusa_avisa_o(): void
    {
        $this->actingAs($this->r, 'revendedor');
        $this->post("/revendedor/api/empresas/{$this->tenant->id}/pedidos", ['plan_id' => $this->pago->id, 'ciclo' => 'monthly', 'referencia' => 'TRF-7', 'comprovativo' => $this->pdf()], ['Accept' => 'application/json'])
            ->assertCreated();
        $pedido = Order::where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
        $this->assertSame($this->r->id, $pedido->reseller_id);

        $this->actingAs($this->dono());
        $linha = collect($this->getJson('/api/v1/plataforma/react/facturacao')->assertOk()->json('pedidos'))->firstWhere('id', $pedido->id);
        $this->assertSame('Paulo Revende', $linha['revendedor']['nome']);

        $this->postJson("/api/v1/plataforma/react/facturacao/pedidos/{$pedido->id}/recusar", ['motivo' => 'O valor não entrou na conta.'])->assertOk();
        Mail::assertSent(AvisoDaRevenda::class, fn ($m) => $m->hasTo($this->r->email) && $m->assunto === 'Pagamento recusado');
    }

    public function test_pagar_a_factura_de_renovacao_e_o_super_admin_confirma_ou_recusa(): void
    {
        $admin = $this->dono();
        $factura = $this->factura();

        // Por pagar no portal.
        $this->actingAs($this->r, 'revendedor');
        $pagamentos = $this->getJson('/revendedor/api/pagamentos')->assertOk();
        $this->assertSame([$factura->id], array_column(array_filter($pagamentos->json('por_pagar'), fn ($p) => $p['tipo'] === 'factura'), 'id'));

        // Sem comprovativo não vai.
        $this->postJson("/revendedor/api/empresas/{$this->tenant->id}/facturas/{$factura->id}/pagamento", ['referencia' => 'X'])->assertStatus(422)->assertJsonValidationErrors('comprovativo');

        $this->post("/revendedor/api/empresas/{$this->tenant->id}/facturas/{$factura->id}/pagamento", ['referencia' => 'TRF-RENOVA', 'comprovativo' => $this->pdf()], ['Accept' => 'application/json'])->assertOk();
        $factura->refresh();
        $this->assertSame([$this->r->id, 'TRF-RENOVA', 'pending'], [$factura->payment_submitted_by_reseller_id, $factura->payment_reference, $factura->status]);
        $this->assertNotNull($factura->payment_proof);
        Mail::assertSent(AvisoDaRevenda::class, fn ($m) => $m->hasTo($admin->email) && $m->assunto === 'Pagamento enviado por um revendedor');
        $this->assertSame('por_confirmar', $this->getJson('/revendedor/api/pagamentos')->json('por_confirmar.0.estado'));

        // O super admin recusa: volta a «por pagar», com o motivo, e o revendedor é avisado.
        $this->actingAs($admin);
        $this->assertSame($factura->id, $this->getJson('/api/v1/plataforma/react/facturacao')->assertOk()->json('pagamentos.0.id'));
        $this->postJson("/api/v1/plataforma/react/facturacao/facturas/{$factura->id}/recusar-pagamento", ['motivo' => 'A transferência não entrou.'])->assertOk();
        $factura->refresh();
        $this->assertNull($factura->payment_submitted_at);
        $this->assertSame('A transferência não entrou.', $factura->payment_rejection_reason);
        Mail::assertSent(AvisoDaRevenda::class, fn ($m) => $m->hasTo($this->r->email) && $m->assunto === 'Pagamento recusado');

        // Envia outra vez e o super admin confirma: a factura fica paga e dá a comissão.
        $this->actingAs($this->r, 'revendedor');
        $this->post("/revendedor/api/empresas/{$this->tenant->id}/facturas/{$factura->id}/pagamento", ['comprovativo' => $this->pdf()], ['Accept' => 'application/json'])->assertOk();
        $this->assertNull($factura->fresh()->payment_rejection_reason);

        $this->actingAs($admin);
        $this->postJson("/api/v1/plataforma/react/facturacao/facturas/{$factura->id}/pagar")->assertOk();
        $this->assertSame('paid', $factura->fresh()->status);
        $this->assertEquals(2000, ResellerCommission::where('origin_type', 'invoice')->where('origin_id', $factura->id)->value('amount'));

        $this->actingAs($this->r, 'revendedor');
        $historico = $this->getJson('/revendedor/api/pagamentos')->json('historico');
        $this->assertSame('confirmado', collect($historico)->firstWhere('id', $factura->id)['estado']);
    }

    public function test_so_paga_as_facturas_das_empresas_dele_e_o_portal_tem_a_barra_lateral(): void
    {
        $outra = Tenant::create(['name' => 'Alheia ' . uniqid(), 'slug' => 'al-' . uniqid(), 'email' => 'al' . uniqid() . '@x.ao', 'is_active' => true]);
        $sub = $outra->subscriptions()->create(['plan_id' => $this->pago->id, 'status' => 'active', 'amount' => 1, 'billing_cycle' => 'monthly']);
        $alheia = Invoice::create(['tenant_id' => $outra->id, 'subscription_id' => $sub->id, 'invoice_date' => now(), 'due_date' => now(), 'subtotal' => 1, 'tax' => 0, 'total' => 1, 'status' => 'pending']);

        $this->actingAs($this->r, 'revendedor');
        $this->post("/revendedor/api/empresas/{$outra->id}/facturas/{$alheia->id}/pagamento", ['comprovativo' => $this->pdf()], ['Accept' => 'application/json'])->assertNotFound();
        $this->post("/revendedor/api/empresas/{$this->tenant->id}/facturas/{$alheia->id}/pagamento", ['comprovativo' => $this->pdf()], ['Accept' => 'application/json'])->assertNotFound();

        $this->factura();
        $pagina = $this->get('/revendedor/pagamentos')->assertOk();
        $pagina->assertSee('data-peca="revenda/moldura"', false)->assertSee('data-ecra="revenda/pagamentos"', false);
    }
}
