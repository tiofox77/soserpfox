<?php

namespace Tests\Feature\Plataforma;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * A LISTA DE EMPRESAS DA PLATAFORMA: os cartões de estado e a linha de sinais.
 *
 * O cartão dizia «entrou há 4h» ao lado de «Criada em 14/09/2026» — lia-se como
 * a hora da inscrição — e os cartões de cima contavam as desactivadas como
 * «a montar». Cada número passa a vir com a sua data por extenso e o estado com
 * a frase que o explica.
 */
class ListaDeEmpresasTest extends TenantTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Dono da Plataforma',
            'email' => 'plataforma_' . uniqid() . '@exemplo.ao',
            'password' => bcrypt('x'),
        ]);
        $this->admin->forceFill(['is_super_admin' => true])->save();
    }

    public function test_as_desactivadas_tem_cartao_proprio_e_nao_contam_nos_outros(): void
    {
        $desligada = Tenant::create([
            'name' => 'Desligada ' . uniqid(), 'slug' => 'desligada-' . uniqid(),
            'email' => 'd' . uniqid() . '@exemplo.ao', 'is_active' => false,
        ]);

        $r = $this->actingAs($this->admin)
            ->getJson('/api/v1/plataforma/react/empresas?procura=' . urlencode($desligada->name))
            ->assertOk();

        $this->assertSame(1, $r->json('contagens.desactivada'));
        $this->assertSame(0, $r->json('contagens.a_montar'));
        $this->assertSame('desactivada', $r->json('empresas.0.vida.chave'));
    }

    public function test_cada_sinal_vem_com_a_data_por_extenso_e_o_estado_com_o_porque(): void
    {
        DB::table('tenant_user')->where('tenant_id', $this->tenant->id)->where('user_id', $this->user->id)
            ->update(['ultimo_acesso_em' => now()->subHours(4)]);

        $r = $this->actingAs($this->admin)
            ->getJson('/api/v1/plataforma/react/empresas?procura=' . urlencode($this->tenant->name))
            ->assertOk();

        $vida = $r->json('empresas.0.vida');

        $this->assertSame('a_montar', $vida['chave'], 'inscrita agora e sem facturas');
        $this->assertNotEmpty($vida['motivo']);
        $this->assertSame(now()->subHours(4)->format('d/m/Y H:i'), $vida['ultimo_acesso_em']);
        $this->assertTrue($vida['acesso_recente']);
        $this->assertArrayHasKey('operacoes_30d', $vida);
        $this->assertNotNull($r->json('empresas.0.criada_ha'));
    }

    public function test_filtrar_pelo_cartao_das_desactivadas(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/plataforma/react/empresas?estado=desactivada')
            ->assertOk();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/plataforma/react/empresas?ordenar=actividade')
            ->assertOk();
    }
}
