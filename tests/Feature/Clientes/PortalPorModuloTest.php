<?php

namespace Tests\Feature\Clientes;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\VehiclePhoto;
use App\Models\Workshop\WorkOrder;
use App\Support\PortalDoCliente;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

/**
 * O PORTAL POR MÓDULO — cada cliente vê as áreas que a empresa lhe deu (15/09/2026).
 *
 * E a oficina no portal, o PDF da factura, a empresa desactivada e o mesmo
 * email em duas empresas.
 */
class PortalPorModuloTest extends TenantTestCase
{
    private const SENHA = 'SenhaDoPortal1';

    private Client $doPortal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comModulo('oficina')->comModulo('eventos');
        PortalDoCliente::esquecer();

        $this->doPortal = $this->clienteEmpresa();
        $this->doPortal->forceFill([
            'email' => 'dono' . uniqid() . '@exemplo.ao',
            'password' => Hash::make(self::SENHA),
            'portal_access' => true,
            'is_active' => true,
            'portal_modulos' => ['oficina'],
        ])->save();
    }

    private function entrar(?Client $c = null): void
    {
        auth()->logout();
        $this->postJson('/client/login', ['email' => ($c ?? $this->doPortal)->email, 'password' => self::SENHA])->assertOk();
    }

    private function factura(array $troca = []): SalesInvoice
    {
        return SalesInvoice::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->doPortal->id,
            'invoice_number' => 'FT P/' . random_int(10000, 99999),
            'invoice_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
            'status' => 'sent',
            'total' => 57000,
            'paid_amount' => 7000,
            'created_by' => $this->user->id,
        ], $troca));
    }

    public function test_so_da_oficina_ve_a_oficina_e_as_facturas_da_oficina(): void
    {
        $daOficina = $this->factura(['source_module' => 'oficina']);
        $doBalcao = $this->factura(['source_module' => null]);

        $this->entrar();

        $this->get('/client/oficina')->assertOk()->assertSee('data-ecra="cliente/oficina"', false);
        $this->get('/client/events')->assertForbidden();
        $this->getJson('/client/api/proformas')->assertForbidden();
        $this->getJson('/client/api/eventos')->assertForbidden();

        $numeros = array_column($this->getJson('/client/api/facturas')->assertOk()->json('facturas'), 'numero');
        $this->assertContains($daOficina->invoice_number, $numeros);
        $this->assertNotContains($doBalcao->invoice_number, $numeros, 'a factura do balcão não é da área da oficina');

        $painel = $this->getJson('/client/api/painel')->assertOk();
        $this->assertSame(['oficina'], $painel->json('seccoes'));
        $this->assertNotNull($painel->json('oficina'));
    }

    public function test_sem_o_modulo_na_empresa_a_area_desaparece_mesmo_marcada(): void
    {
        $this->doPortal->forceFill(['portal_modulos' => ['facturacao', 'hotel']])->save();

        $this->entrar();

        $this->assertSame(['facturacao'], $this->getJson('/client/api/painel')->assertOk()->json('seccoes'), 'a empresa não tem hotel');
    }

    public function test_o_portal_de_sempre_para_quem_nao_tem_escolha(): void
    {
        $this->doPortal->forceFill(['portal_modulos' => null])->save();

        $this->entrar();

        $this->assertSame(['facturacao', 'eventos'], $this->getJson('/client/api/painel')->json('seccoes'));
        $this->get('/client/oficina')->assertForbidden();
    }

    public function test_a_oficina_mostra_o_carro_o_estado_a_folha_e_a_factura_a_pagar(): void
    {
        $v = Vehicle::create(['plate' => 'LD-11-22-AB', 'vehicle_number' => 'VEH-P1', 'owner_name' => 'Dono', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active', 'client_id' => $this->doPortal->id, 'insurance_expiry' => now()->subDay()]);
        $f = $this->factura(['source_module' => 'oficina']);

        $os1 = WorkOrder::create(['order_number' => 'OS-P-1', 'vehicle_id' => $v->id, 'received_at' => now()->subDays(3), 'started_at' => now()->subDays(2), 'problem_description' => 'Travões a chiar.', 'work_performed' => 'Pastilhas novas.', 'status' => 'completed', 'completed_at' => now()->subDay(), 'priority' => 'normal', 'total' => 57000, 'invoice_id' => $f->id]);
        WorkOrder::create(['order_number' => 'OS-P-2', 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'Revisão.', 'status' => 'in_progress', 'priority' => 'normal']);

        // A fotografia do DEPOIS posta na folha de obra aparece ao cliente; a solta (sem folha) não.
        VehiclePhoto::create(['tenant_id' => $this->tenant->id, 'vehicle_id' => $v->id, 'work_order_id' => $os1->id, 'phase' => 'depois', 'service' => 'pintura', 'zone' => 'capo', 'file_path' => 'workshop/vehicles/x/depois.jpg']);
        VehiclePhoto::create(['tenant_id' => $this->tenant->id, 'vehicle_id' => $v->id, 'phase' => 'antes', 'service' => 'pintura', 'file_path' => 'workshop/vehicles/x/solta.jpg']);

        // A inspecção concluída aparece ao cliente; a que está a meio não.
        \App\Models\Workshop\WorkOrderInspection::create(['tenant_id' => $this->tenant->id, 'work_order_id' => $os1->id, 'name' => 'Revisão geral', 'completed_at' => now(), 'results' => [['seccao' => 'Travões', 'ponto' => 'Pastilhas', 'estado' => 'urgente', 'nota' => '2 mm', 'foto' => null]]]);
        \App\Models\Workshop\WorkOrderInspection::create(['tenant_id' => $this->tenant->id, 'work_order_id' => $os1->id, 'name' => 'A meio', 'results' => [['seccao' => 'A', 'ponto' => 'b', 'estado' => null, 'nota' => null, 'foto' => null]]]);

        // Uma ordem desta viatura facturada a OUTRA pessoa (o dono anterior) não aparece.
        $outro = $this->clienteEmpresa();
        $alheia = $this->factura(['client_id' => $outro->id, 'source_module' => 'oficina']);
        WorkOrder::create(['order_number' => 'OS-P-VELHA', 'vehicle_id' => $v->id, 'received_at' => now()->subYear(), 'problem_description' => 'x', 'status' => 'delivered', 'priority' => 'normal', 'invoice_id' => $alheia->id]);

        $this->entrar();
        $r = $this->getJson('/client/api/oficina')->assertOk();

        $this->assertSame('LD-11-22-AB', $r->json('viaturas.0.matricula'));
        $this->assertTrue($r->json('viaturas.0.na_oficina'));
        $this->assertLessThan(0, $r->json('viaturas.0.documentos.0.dias'), 'o seguro caducado vem com dias negativos');

        $numeros = array_column($r->json('ordens'), 'numero');
        $this->assertSame(['OS-P-2', 'OS-P-1'], $numeros, 'a do dono anterior fica de fora');

        $concluida = collect($r->json('ordens'))->firstWhere('numero', 'OS-P-1');
        $this->assertSame('Pastilhas novas.', $concluida['trabalho']);
        $this->assertTrue(collect($concluida['etapas'])->firstWhere('chave', 'completed')['actual']);
        $this->assertFalse(collect($concluida['etapas'])->firstWhere('chave', 'delivered')['feita']);
        $this->assertEqualsWithDelta(50000, $concluida['factura']['falta'], 0.01);
        $this->assertTrue($concluida['factura']['vencida']);
        $this->assertStringEndsWith("/client/facturas/{$f->id}/pdf", $concluida['factura']['pdf']);

        $this->assertEqualsWithDelta(50000, $r->json('resumo.por_pagar'), 0.01);

        $fotos = $concluida['fotos'];
        $this->assertCount(1, $fotos);
        $this->assertSame(['photo_after', 'Pintura', 'Capô'], [$fotos[0]['tipo'], $fotos[0]['servico'], $fotos[0]['zona']]);

        $this->assertCount(1, $concluida['inspeccoes']);
        $this->assertNull($concluida['aprovar'], 'sem linhas à espera não há link');
        $this->assertSame('approved', $concluida['linhas'][0]['aprovacao'] ?? 'approved');
        $this->assertSame(['Revisão geral', 1, '2 mm'], [$concluida['inspeccoes'][0]['nome'], $concluida['inspeccoes'][0]['contas']['urgente'], $concluida['inspeccoes'][0]['pontos'][0]['nota']]);
    }

    public function test_o_pdf_da_factura_so_das_que_o_cliente_ve(): void
    {
        $daOficina = $this->factura(['source_module' => 'oficina']);
        $doBalcao = $this->factura(['source_module' => null]);
        $rascunho = $this->factura(['source_module' => 'oficina', 'status' => 'draft']);
        $deOutro = $this->factura(['client_id' => $this->clienteEmpresa()->id, 'source_module' => 'oficina']);

        $this->entrar();

        $pdf = $this->get("/client/facturas/{$daOficina->id}/pdf")->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));

        $this->get("/client/facturas/{$doBalcao->id}/pdf")->assertNotFound();
        $this->get("/client/facturas/{$rascunho->id}/pdf")->assertNotFound();
        $this->get("/client/facturas/{$deOutro->id}/pdf")->assertNotFound();
    }

    public function test_empresa_desactivada_nao_entra_e_quem_ja_estava_sai(): void
    {
        $this->entrar();
        $this->getJson('/client/api/painel')->assertOk();

        Tenant::whereKey($this->tenant->id)->update(['is_active' => false]);

        $this->getJson('/client/api/painel')->assertForbidden();

        $this->postJson('/client/login', ['email' => $this->doPortal->email, 'password' => self::SENHA])->assertStatus(422);
    }

    public function test_o_mesmo_email_em_duas_empresas_escolhe_se_a_empresa(): void
    {
        $outra = Tenant::create(['name' => 'Outra Casa ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $la = Client::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'O mesmo, na outra', 'type' => 'pessoa_fisica', 'nif' => '999999999',
            'email' => $this->doPortal->email, 'password' => Hash::make(self::SENHA), 'portal_access' => true, 'is_active' => true,
        ]);

        auth()->logout();
        $r = $this->postJson('/client/login', ['email' => $this->doPortal->email, 'password' => self::SENHA])->assertOk();

        $this->assertCount(2, $r->json('escolher'));
        $this->assertNull($r->json('ir_para'));
        $this->assertNull(auth('client')->user(), 'ainda não entrou');

        $this->postJson('/client/login/empresa', ['id' => $la->id])->assertOk()->assertJsonStructure(['ir_para']);
        $this->assertSame($la->id, auth('client')->id());

        // Um id que não estava na escolha não abre nada.
        auth('client')->logout();
        $this->postJson('/client/login/empresa', ['id' => $this->doPortal->id])->assertStatus(422);
    }

    public function test_a_senha_que_so_bate_numa_das_empresas_entra_directo_nessa(): void
    {
        $outra = Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $la = Client::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Na outra', 'type' => 'pessoa_fisica', 'nif' => '999999999',
            'email' => $this->doPortal->email, 'password' => Hash::make('OutraSenha99'), 'portal_access' => true, 'is_active' => true,
        ]);

        auth()->logout();
        $this->postJson('/client/login', ['email' => $this->doPortal->email, 'password' => 'OutraSenha99'])->assertOk()->assertJsonStructure(['ir_para']);
        $this->assertSame($la->id, auth('client')->id(), 'antes, a senha era conferida só contra a primeira ficha e nunca entrava');
    }

    public function test_a_ficha_do_cliente_escolhe_as_areas_entre_as_da_empresa(): void
    {
        $this->comPermissoes('invoicing.clients.view', 'invoicing.clients.edit');

        $areas = array_column($this->getJson('/api/v1/invoicing/react/clients/opcoes')->assertOk()->json('portal_seccoes'), 'chave');
        $this->assertSame(['facturacao', 'oficina', 'eventos'], $areas);

        $base = ['type' => $this->doPortal->type, 'name' => $this->doPortal->name, 'nif' => $this->doPortal->nif, 'country' => 'AO', 'email' => $this->doPortal->email, 'portal_access' => true];

        $this->putJson('/api/v1/invoicing/react/clients/' . $this->doPortal->id, $base + ['portal_modulos' => ['hotel']])
            ->assertStatus(422)->assertJsonValidationErrors(['portal_modulos.0']);
        $this->putJson('/api/v1/invoicing/react/clients/' . $this->doPortal->id, $base + ['portal_modulos' => []])
            ->assertStatus(422)->assertJsonValidationErrors(['portal_modulos']);

        $this->putJson('/api/v1/invoicing/react/clients/' . $this->doPortal->id, $base + ['portal_modulos' => ['oficina', 'eventos', 'oficina']])->assertOk();
        $this->assertSame(['oficina', 'eventos'], $this->doPortal->fresh()->portal_modulos);
    }
}
