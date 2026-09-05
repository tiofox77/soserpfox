<?php

namespace Tests\Feature;

use App\Exceptions\LimiteDeDocumentosAtingido;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Plataforma\TrocarDePlano;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * Tecto de documentos do plano.
 *
 * O FOX Friendly é uma oferta de três meses com tudo aberto; sem tecto, uma
 * empresa facturava o ano inteiro à borla. Passa a dar 500 documentos.
 *
 * O QUE ESTES ENSAIOS PRENDEM, por ordem de importância:
 *   1. quem já cá está NÃO é apanhado — o tecto viaja na subscrição, e as
 *      antigas ficam sem ele;
 *   2. o travão está na porta única da numeração, não em cada ecrã;
 *   3. proformas e orçamentos não gastam quota (não são fiscais);
 *   4. a ficha da empresa concede mais, nunca corta.
 */
class LimiteDeDocumentosTest extends TenantTestCase
{
    private function plano(?int $tecto): Plan
    {
        return Plan::create([
            'name' => 'Plano '.uniqid(), 'slug' => 'plano-'.uniqid(),
            'price_monthly' => 0, 'trial_days' => 0,
            'max_users' => 10, 'max_storage_mb' => 1000,
            'max_documents' => $tecto, 'is_active' => true,
        ]);
    }

    private function cliente(): int
    {
        return \App\Models\Client::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente '.uniqid(),
            'email' => uniqid().'@cliente.ao',
        ])->id;
    }

    /** Documentos fiscais já emitidos, sem passar pelos ecrãs. */
    private function facturas(int $quantas): void
    {
        $cliente = $this->cliente();

        for ($i = 0; $i < $quantas; $i++) {
            DB::table('invoicing_sales_invoices')->insert([
                'tenant_id' => $this->tenant->id,
                'client_id' => $cliente,
                'invoice_number' => 'FT X/'.uniqid(),
                'invoice_date' => now()->toDateString(),
                'status' => 'pending',
                'subtotal' => 100, 'total' => 100,
                'created_by' => $this->user->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function serie(): InvoicingSeries
    {
        return InvoicingSeries::getDefaultSeries($this->tenant->id, 'pos');
    }

    /** @test */
    public function o_plano_fox_friendly_da_500_documentos(): void
    {
        // A decisão vive na migração; prende-se aí para que ninguém a mude
        // sem reparar. A base de ensaio não tem os planos de produção.
        $migracao = file_get_contents(database_path('migrations/2026_09_03_090000_limite_de_documentos_do_plano.php'));
        $this->assertStringContainsString('TECTO_DO_FOX = 500', $migracao);

        $fox = Plan::where('slug', 'fox-friendly')->first();

        if ($fox) {
            $this->assertSame(500, (int) $fox->max_documents);
        }
    }

    /**
     * A REGRA QUE PROTEGE QUEM JÁ CÁ ESTÁ.
     *
     * Uma subscrição criada antes desta política tem `max_documentos` nulo, e
     * nulo é sem tecto — mesmo que o plano passe a ter um.
     *
     * @test
     */
    public function uma_subscricao_antiga_nao_apanha_o_tecto_novo(): void
    {
        $plano = $this->plano(500);

        $antiga = $this->tenant->subscriptions()->create([
            'plan_id' => $plano->id, 'billing_cycle' => 'monthly', 'amount' => 0,
            'status' => 'active', 'current_period_start' => now(), 'current_period_end' => now()->addYear(),
            // sem max_documentos: como as que já existiam
        ]);

        $this->assertNull($antiga->max_documentos);
        $this->assertNull($this->tenant->fresh()->limiteDeDocumentos());

        $this->facturas(3);
        $this->assertTrue($this->tenant->fresh()->podeEmitirDocumento());
    }

    /** Uma subscrição NOVA copia o tecto do plano. */
    public function test_uma_subscricao_nova_leva_o_tecto_do_plano(): void
    {
        $plano = $this->plano(500);

        $nova = app(TrocarDePlano::class)->aplicar($this->tenant, $plano, 'monthly');

        $this->assertSame(500, (int) $nova->max_documentos);
        $this->assertSame(500, $this->tenant->fresh()->limiteDeDocumentos());
    }

    /** Um plano sem tecto continua sem tecto. */
    public function test_plano_sem_tecto_nao_poe_tecto(): void
    {
        $nova = app(TrocarDePlano::class)->aplicar($this->tenant, $this->plano(null), 'monthly');

        $this->assertNull($nova->max_documentos);
        $this->assertNull($this->tenant->fresh()->limiteDeDocumentos());
    }

    /** @test */
    public function abaixo_do_tecto_numera_normalmente(): void
    {
        app(TrocarDePlano::class)->aplicar($this->tenant, $this->plano(5), 'monthly');
        $this->facturas(2);

        $numero = $this->serie()->getNextNumber();

        $this->assertNotEmpty($numero);
    }

    /**
     * O travão está na porta da numeração — a que todo o documento fiscal
     * usa, venha do POS, da faturação, das notas ou da sincronização.
     *
     * @test
     */
    public function chegado_ao_tecto_a_numeracao_recusa(): void
    {
        app(TrocarDePlano::class)->aplicar($this->tenant, $this->plano(5), 'monthly');
        $this->facturas(5);

        $serie = $this->serie();
        $antes = $serie->fresh()->next_number;

        try {
            $serie->getNextNumber();
            $this->fail('devia ter recusado');
        } catch (LimiteDeDocumentosAtingido $e) {
            $this->assertSame(5, $e->limite);
            $this->assertSame(5, $e->emitidos);
            $this->assertStringContainsString('Mude de plano', $e->getMessage());
        }

        $this->assertSame($antes, $serie->fresh()->next_number, 'recusar não pode gastar número da série');
    }

    /** Proformas e orçamentos não gastam quota: não são documentos fiscais. */
    public function test_proformas_nao_contam_para_o_tecto(): void
    {
        app(TrocarDePlano::class)->aplicar($this->tenant, $this->plano(3), 'monthly');

        DB::table('invoicing_sales_proformas')->insert([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente(),
            'proforma_number' => 'PF X/'.uniqid(),
            'proforma_date' => now()->toDateString(),
            'status' => 'draft', 'subtotal' => 10, 'total' => 10,
            'created_by' => $this->user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(0, $this->tenant->fresh()->documentosEmitidos());
        $this->assertTrue($this->tenant->fresh()->podeEmitirDocumento());
    }

    /** Notas e recibos contam — são fiscais e vão à AGT. */
    public function test_notas_e_recibos_contam(): void
    {
        app(TrocarDePlano::class)->aplicar($this->tenant, $this->plano(10), 'monthly');
        $this->facturas(1);

        DB::table('invoicing_credit_notes')->insert([
            'tenant_id' => $this->tenant->id, 'client_id' => $this->cliente(), 'credit_note_number' => 'NC X/'.uniqid(),
            'issue_date' => now()->toDateString(), 'status' => 'issued',
            'subtotal' => 10, 'total' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(2, $this->tenant->fresh()->documentosEmitidos());
    }

    /** A ficha da empresa concede mais, nunca corta. */
    public function test_a_ficha_da_empresa_pode_dar_mais(): void
    {
        app(TrocarDePlano::class)->aplicar($this->tenant, $this->plano(5), 'monthly');
        $this->facturas(5);

        $this->assertFalse($this->tenant->fresh()->podeEmitirDocumento());

        // Sem tecto na ficha (nulo) = sem tecto, ganha ao do plano.
        $this->tenant->update(['max_documents' => null]);
        $this->tenant->refresh();
        $this->assertFalse($this->tenant->podeEmitirDocumento(), 'nulo na ficha é ausência, não concessão');

        // Um número maior na ficha concede mais.
        $this->tenant->update(['max_documents' => 50]);
        $this->assertSame(50, $this->tenant->fresh()->limiteDeDocumentos());
        $this->assertTrue($this->tenant->fresh()->podeEmitirDocumento());
    }
}
