<?php

namespace Tests\Feature;

use App\Models\HR\HRSetting;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * Gestão de empresas no super-admin.
 *
 * A área que mais dano pode fazer no sistema inteiro: cria, desactiva e apaga
 * empresas. E era onde a guarda estava mais fraca.
 */
class SuperAdminTenantsTest extends TenantTestCase
{
    private int $donoId = 0;

    /**
     * O dono da plataforma. Os ensaios chamavam o componente em Livewire
     * directamente, sem a guarda; a API está atrás do `superadmin`.
     */
    private function superAdmin(): void
    {
        $dono = User::create([
            'name' => 'Dono', 'email' => 'dono_' . uniqid() . '@exemplo.ao', 'password' => bcrypt('x'),
        ]);
        $dono->forceFill(['is_super_admin' => true])->save();

        $this->donoId = $dono->id;
        $this->actingAs($dono);
    }

    private function empresaNova(array $troca = []): array
    {
        return array_merge([
            'name' => 'Empresa Nova',
            'slug' => 'nova-' . uniqid(),
            'email' => 'nova' . uniqid() . '@exemplo.ao',
            'country' => 'AO',
            'max_users' => 5,
            'max_storage_mb' => 1000,
            'is_active' => true,
        ], $troca);
    }

    private function empresaVazia(): Tenant
    {
        return Tenant::create([
            'name'      => 'Empresa Vazia',
            'slug'      => 'vazia-' . uniqid(),
            'nif'       => (string) random_int(500000000, 599999999),
            'email'     => 'vazia' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);
    }

    /**
     * Um movimento de stock a sério para a empresa dada.
     *
     * A tabela tem chaves estrangeiras para armazém e artigo — ids inventados
     * não passam.
     */
    private function movimentoDeStock(Tenant $empresa, int $quantos = 1): void
    {
        $armazem = \App\Models\Invoicing\Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $empresa->id,
            'name'      => 'Armazém',
            'code'      => 'ARM-' . uniqid(),
            'is_active' => true,
        ]);

        $artigo = \App\Models\Product::withoutGlobalScopes()->create([
            'tenant_id'      => $empresa->id,
            'name'           => 'Artigo',
            'sku'            => 'SKU-' . uniqid(),
            'price'          => 100,
            'type'           => 'produto',
            'manage_stock'   => true,
            'stock_quantity' => 0,
        ]);

        for ($i = 0; $i < $quantos; $i++) {
            DB::table('invoicing_stock_movements')->insert([
                'tenant_id'    => $empresa->id,
                'warehouse_id' => $armazem->id,
                'product_id'   => $artigo->id,
                'type'         => 'in',
                'quantity'     => 1,
                'user_id'      => $this->user->id,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function test_uma_empresa_sem_actividade_pode_ser_apagada(): void
    {
        // O caso que justifica existir a função: uma empresa criada por engano.
        $vazia = $this->empresaVazia();

        $this->assertTrue($vazia->canBeDeleted()['can_delete']);
    }

    public function test_uma_empresa_com_movimentos_de_stock_nao_pode_ser_apagada(): void
    {
        // A guarda anterior olhava para as facturas e mais nada. Uma empresa
        // com anos de movimentos de stock, mas sem uma factura de venda, era
        // apagável — e ia-se abaixo com tudo isso atrás.
        $empresa = $this->empresaVazia();

        $this->movimentoDeStock($empresa);

        $r = $empresa->canBeDeleted();

        $this->assertFalse($r['can_delete']);
        $this->assertStringContainsString('movimentos de stock', $r['reason']);
        $this->assertStringContainsString('Desactive-a', $r['reason'], 'tem de dizer o que fazer em vez disso');
    }

    public function test_a_trilha_de_auditoria_nao_serve_de_guarda(): void
    {
        // Parecia o melhor sinal de todos — append-only, regista tudo. Mas uma
        // empresa acabada de criar já nasce com linhas lá: o provisionamento
        // cria o armazém, os métodos de pagamento e a série FR, e esses modelos
        // são auditados.
        //
        // Usá-la como guarda bloqueava TODAS as empresas, incluindo a criada
        // por engano há cinco minutos — o único caso em que apagar faz sentido.
        // Este teste fixa essa decisão para ela não ser desfeita por parecer
        // "mais seguro".
        $empresa = $this->empresaVazia();

        $this->assertGreaterThan(
            0,
            DB::table('audit_trail')->where('tenant_id', $empresa->id)->count(),
            'uma empresa nova já nasce com registos de auditoria'
        );

        $this->assertTrue(
            $empresa->canBeDeleted()['can_delete'],
            'a auditoria do próprio provisionamento não pode impedir de apagar'
        );
    }

    public function test_a_razao_diz_o_que_foi_encontrado_e_quanto(): void
    {
        // Um "não pode apagar" sem dizer porquê obriga a adivinhar.
        $empresa = $this->empresaVazia();

        $this->movimentoDeStock($empresa, 2);

        $r = $empresa->canBeDeleted();

        $this->assertSame(2, $r['encontrado']['movimentos de stock']);
        $this->assertStringContainsString('2 movimentos de stock', $r['reason']);
    }

    public function test_o_ecra_recusa_apagar_e_explica(): void
    {
        $this->superAdmin();

        $empresa = $this->empresaVazia();

        $this->movimentoDeStock($empresa);

        $this->postJson("/api/v1/plataforma/react/empresas/{$empresa->id}/suspender")
            ->assertStatus(422)
            ->assertJsonFragment(['id' => [$empresa->canBeDeleted()['reason']]]);

        $this->assertNotNull($empresa->fresh(), 'a empresa tem de continuar lá');
    }

    public function test_apagar_em_soft_nao_destroi_os_utilizadores(): void
    {
        // O defeito mais perigoso desta área: a cascata corria no `deleting`,
        // que também dispara no soft delete. A empresa ficava recuperável — e
        // os utilizadores, papéis e subscrições dela levavam `forceDelete` e
        // desapareciam para sempre. Restaurar a empresa dava uma casa sem
        // ninguém lá dentro.
        $empresa = $this->empresaVazia();

        $pessoa = User::create([
            'name'      => 'Pessoa da Empresa',
            'email'     => 'pessoa' . uniqid() . '@exemplo.ao',
            'password'  => bcrypt('secret'),
            'tenant_id' => $empresa->id,
        ]);
        $pessoa->tenants()->syncWithoutDetaching([$empresa->id]);

        $empresa->delete();   // soft

        $this->assertNotNull(
            User::find($pessoa->id),
            'um soft delete da empresa não pode apagar as pessoas dela'
        );

        $this->assertSoftDeleted('tenants', ['id' => $empresa->id]);
    }

    public function test_restaurar_uma_empresa_devolve_a_com_a_gente_dentro(): void
    {
        $empresa = $this->empresaVazia();

        $pessoa = User::create([
            'name'      => 'Pessoa',
            'email'     => 'p' . uniqid() . '@exemplo.ao',
            'password'  => bcrypt('secret'),
            'tenant_id' => $empresa->id,
        ]);
        $pessoa->tenants()->syncWithoutDetaching([$empresa->id]);

        $empresa->delete();
        Tenant::withTrashed()->find($empresa->id)->restore();

        $this->assertSame(
            1,
            Tenant::find($empresa->id)->users()->count(),
            'a empresa restaurada tem de voltar com os utilizadores'
        );
    }

    public function test_criar_uma_empresa_deixa_a_pronta_a_usar(): void
    {
        // As definições de RH e os modelos de notificação existiam agarrados à
        // empresa 1: uma empresa nova abria esses ecrãs em branco.
        $this->superAdmin();

        $slug = 'nova-' . uniqid();

        $this->postJson('/api/v1/plataforma/react/empresas', $this->empresaNova(['slug' => $slug]))
            ->assertCreated();

        $nova = Tenant::where('slug', $slug)->first();

        $this->assertNotNull($nova, 'a empresa tem de ser criada');

        $this->assertGreaterThan(
            0,
            HRSetting::withoutGlobalScopes()->where('tenant_id', $nova->id)->count(),
            'nasce com as definições de RH'
        );

        $this->assertGreaterThan(
            0,
            NotificationTemplate::withoutGlobalScopes()->where('tenant_id', $nova->id)->count(),
            'nasce com os modelos de notificação'
        );
    }

    public function test_uma_criacao_que_falha_a_meio_nao_deixa_empresa_meia_feita(): void
    {
        // Sem transacção, uma falha depois do `Tenant::create` deixava a
        // empresa gravada e meio montada: aparecia na lista, e quem lá entrasse
        // não tinha permissões nem contabilidade.
        $this->superAdmin();

        $antes = Tenant::count();

        // Uma falha DEPOIS de a empresa estar gravada, dentro da transacção: o
        // país já é validado à entrada, por isso a falha provoca-se no passo
        // seguinte ao `create`.
        Tenant::created(function () {
            throw new \RuntimeException('falha a meio do provisionamento');
        });

        $this->withoutExceptionHandling([\RuntimeException::class]);

        try {
            $this->postJson('/api/v1/plataforma/react/empresas', $this->empresaNova(['name' => 'Empresa Que Falha']));
        } catch (\RuntimeException) {
            // o que interessa é o que ficou na base
        }

        $this->assertSame($antes, Tenant::count(), 'não pode sobrar uma empresa meia feita');
    }

    public function test_o_slug_tem_de_ser_unico(): void
    {
        $this->superAdmin();

        $existente = $this->empresaVazia();

        $this->postJson('/api/v1/plataforma/react/empresas', $this->empresaNova(['name' => 'Outra', 'slug' => $existente->slug]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_editar_nao_se_queixa_do_proprio_slug(): void
    {
        $this->superAdmin();

        $empresa = $this->empresaVazia();

        $this->putJson("/api/v1/plataforma/react/empresas/{$empresa->id}", $this->empresaNova([
            'name' => 'Nome Alterado', 'slug' => $empresa->slug, 'email' => $empresa->email,
        ]))->assertOk();

        $this->assertSame('Nome Alterado', $empresa->fresh()->name);
    }

    public function test_desactivar_exige_motivo(): void
    {
        $this->superAdmin();

        $empresa = $this->empresaVazia();

        $this->postJson("/api/v1/plataforma/react/empresas/{$empresa->id}/desactivar", ['motivo' => 'curto'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');

        $this->assertTrue((bool) $empresa->fresh()->is_active, 'sem motivo válido não se desactiva');
    }

    public function test_desactivar_regista_quem_e_quando(): void
    {
        $this->superAdmin();

        $empresa = $this->empresaVazia();

        $this->postJson("/api/v1/plataforma/react/empresas/{$empresa->id}/desactivar", [
            'motivo' => 'Falta de pagamento das últimas três mensalidades.',
        ])->assertOk();

        $empresa->refresh();

        $this->assertFalse((bool) $empresa->is_active);
        $this->assertNotNull($empresa->deactivated_at);
        $this->assertSame($this->donoId, (int) $empresa->deactivated_by);
        $this->assertStringContainsString('mensalidades', $empresa->deactivation_reason);
    }
}
