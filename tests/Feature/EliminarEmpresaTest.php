<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\Plataforma\EliminarEmpresa;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * Apagar uma empresa a serio.
 *
 * A regra e fiscal e nao tecnica: so se apaga quem NUNCA comunicou nada a AGT.
 * Se comunicou, existe um registo do lado da autoridade tributaria que nao
 * desaparece por se apagar aqui — e quem vier pedir contas encontra o dono do
 * sistema sem nada para mostrar.
 */
class EliminarEmpresaTest extends TenantTestCase
{
    private function servico(): EliminarEmpresa
    {
        return app(EliminarEmpresa::class);
    }

    private function empresaDescartavel(): Tenant
    {
        return Tenant::create([
            'name' => 'Lixo ' . uniqid(), 'company_name' => 'Lixo', 'is_active' => true,
        ]);
    }

    public function test_uma_empresa_que_nunca_comunicou_pode_ser_apagada(): void
    {
        $e = $this->empresaDescartavel();

        $this->assertFalse($this->servico()->comunicouAAgt($e));

        $this->servico()->eliminar($e);

        $this->assertDatabaseMissing('tenants', ['id' => $e->id]);
    }

    /** E vai mesmo TUDO: apagar so a linha da empresa deixava os dados para tras. */
    public function test_apaga_tambem_o_que_e_dela(): void
    {
        $e = $this->empresaDescartavel();

        DB::table('invoicing_clients')->insert([
            'tenant_id' => $e->id, 'name' => 'Cliente da lixo', 'nif' => '999999999',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->servico()->eliminar($e);

        $this->assertSame(0, DB::table('invoicing_clients')->where('tenant_id', $e->id)->count(),
            'ficaram dados de uma empresa que ja nao existe');
    }

    /** O travao que importa: com comunicacao a AGT, recusa. */
    public function test_uma_empresa_que_comunicou_a_agt_nao_pode_ser_apagada(): void
    {
        $e = $this->empresaDescartavel();

        DB::table('agt_submissions')->insert([
            'tenant_id' => $e->id, 'status' => 'validated', 'document_type' => 'invoice',
            'document_id' => 1, 'document_number' => 'FT X/1', 'document_type_code' => 'FT',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertTrue($this->servico()->comunicouAAgt($e));

        $this->expectException(\DomainException::class);
        $this->servico()->eliminar($e);
    }

    /** E continua la depois da recusa. */
    public function test_depois_de_recusar_a_empresa_continua_la(): void
    {
        $e = $this->empresaDescartavel();

        DB::table('agt_submissions')->insert([
            'tenant_id' => $e->id, 'status' => 'validated', 'document_type' => 'invoice',
            'document_id' => 1, 'document_number' => 'FT X/1', 'document_type_code' => 'FT',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $this->servico()->eliminar($e);
        } catch (\DomainException) {
            // esperado
        }

        $this->assertDatabaseHas('tenants', ['id' => $e->id]);
    }

    /** Um documento com carimbo de submissao tambem conta como comunicacao. */
    public function test_um_documento_ja_submetido_tambem_trava(): void
    {
        $e = $this->empresaDescartavel();

        DB::table('invoicing_sales_invoices')->insert([
            'tenant_id' => $e->id, 'invoice_number' => 'FT X/000001',
            'client_id' => $this->cliente->id, 'invoice_date' => now()->toDateString(),
            'created_by' => $this->user->id, 'agt_submitted_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertTrue($this->servico()->comunicouAAgt($e));
    }

    /** Ter facturas por si so NAO trava: o que trava e ter comunicado. */
    public function test_facturas_por_comunicar_nao_travam(): void
    {
        $e = $this->empresaDescartavel();

        DB::table('invoicing_sales_invoices')->insert([
            'tenant_id' => $e->id, 'invoice_number' => 'FT X/000002',
            'client_id' => $this->cliente->id, 'invoice_date' => now()->toDateString(),
            'created_by' => $this->user->id, 'agt_submitted_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertFalse($this->servico()->comunicouAAgt($e));
    }

    /** Quem decide tem de poder ver o que se perde antes de decidir. */
    public function test_diz_o_que_se_perde(): void
    {
        $e = $this->empresaDescartavel();

        DB::table('invoicing_clients')->insert([
            'tenant_id' => $e->id, 'name' => 'Um cliente', 'nif' => '999999998',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $perdas = $this->servico()->oQueSePerde($e);

        $this->assertSame(1, $perdas['clientes']);
        $this->assertArrayHasKey('facturas', $perdas);
    }

    // ---- pelo ecra ------------------------------------------------------

    private function comoDonoDaPlataforma(): void
    {
        $this->user->update(["is_super_admin" => true]);
        $this->actingAs($this->user->fresh());
    }

    /** Sem escrever o nome, nao apaga. */
    public function test_o_ecra_exige_o_nome_escrito(): void
    {
        $this->comoDonoDaPlataforma();
        $e = $this->empresaDescartavel();

        \Livewire\Livewire::test(\App\Livewire\SuperAdmin\Tenants::class)
            ->call("abrirApagarDefinitivo", $e->id)
            ->set("apagarDefinitivoConfirmacao", "nome errado")
            ->call("confirmarApagarDefinitivo")
            ->assertHasErrors("apagarDefinitivoConfirmacao");

        $this->assertDatabaseHas("tenants", ["id" => $e->id]);
    }

    /** Com o nome certo, apaga. */
    public function test_o_ecra_apaga_com_o_nome_certo(): void
    {
        $this->comoDonoDaPlataforma();
        $e = $this->empresaDescartavel();

        \Livewire\Livewire::test(\App\Livewire\SuperAdmin\Tenants::class)
            ->call("abrirApagarDefinitivo", $e->id)
            ->set("apagarDefinitivoConfirmacao", $e->name)
            ->call("confirmarApagarDefinitivo");

        $this->assertDatabaseMissing("tenants", ["id" => $e->id]);
    }

    /** E o ecra impede quando houve comunicacao a AGT. */
    public function test_o_ecra_impede_quando_houve_comunicacao(): void
    {
        $this->comoDonoDaPlataforma();
        $e = $this->empresaDescartavel();

        \Illuminate\Support\Facades\DB::table("agt_submissions")->insert([
            "tenant_id" => $e->id, "status" => "validated", "document_type" => "invoice",
            "document_id" => 1, "document_number" => "FT X/1", "document_type_code" => "FT",
            "created_at" => now(), "updated_at" => now(),
        ]);

        \Livewire\Livewire::test(\App\Livewire\SuperAdmin\Tenants::class)
            ->call("abrirApagarDefinitivo", $e->id)
            ->assertSet("apagarDefinitivoImpedido", fn ($v) => $v !== null);
    }
}
