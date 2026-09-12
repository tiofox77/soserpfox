<?php

namespace Tests\Feature\Pwa;

use App\Http\Controllers\PwaController;
use App\Models\PwaDevice;
use Tests\TenantTestCase;

/**
 * O inventário dos aparelhos com PWA.
 *
 * PORQUE EXISTE. Descobriu-se, a testar num Android, que um deploy do motor
 * podia não chegar aos aparelhos — e, pior, que não havia forma nenhuma de o
 * saber. Produção tinha a correcção, o telemóvel corria a versão de antes, e
 * só se percebeu por acaso.
 *
 * A causa está corrigida. Isto trava a CEGUEIRA: se voltar a acontecer, tem de
 * aparecer numa lista em vez de ser descoberto por acaso.
 */
class AparelhosPwaTest extends TenantTestCase
{
    private const API = '/api/v1/plataforma/react/aparelhos-pwa';

    private function aparelho(array $dados = []): PwaDevice
    {
        return PwaDevice::create(array_merge([
            'tenant_id'     => $this->tenant->id,
            'device_uuid'   => 'dev-' . uniqid(),
            'app_version'   => 'versao-actual',
            'standalone'    => false,
            'syncs'         => 1,
            'first_seen_at' => now(),
            'last_seen_at'  => now(),
        ], $dados));
    }

    /** @test */
    public function a_sincronizacao_regista_o_aparelho_e_a_versao_que_ele_corre(): void
    {
        $this->comModulo('invoicing');

        $this->withHeaders([
            'X-Sos-Device'     => 'aparelho-do-balcao',
            'X-Sos-Version'    => 'abc1234567',
            'X-Sos-Standalone' => '1',
            'X-Sos-Platform'   => 'Android',
        ])->getJson('/api/v1/invoicing/sync')->assertOk();

        $this->assertDatabaseHas('pwa_devices', [
            'tenant_id'   => $this->tenant->id,
            'device_uuid' => 'aparelho-do-balcao',
            'app_version' => 'abc1234567',
            'standalone'  => 1,
        ]);
    }

    /** @test */
    public function sincronizar_outra_vez_nao_cria_um_segundo_aparelho(): void
    {
        $this->comModulo('invoicing');

        for ($i = 0; $i < 3; $i++) {
            $this->withHeaders(['X-Sos-Device' => 'o-mesmo', 'X-Sos-Version' => 'v1'])
                ->getJson('/api/v1/invoicing/sync')->assertOk();
        }

        $this->assertSame(1, PwaDevice::where('device_uuid', 'o-mesmo')->count());
        $this->assertSame(3, (int) PwaDevice::where('device_uuid', 'o-mesmo')->value('syncs'));
    }

    /**
     * A telemetria NUNCA pode partir a sincronização.
     *
     * Isto corre dentro da chamada que faz uma loja vender. Um cabeçalho
     * absurdo não pode impedir um catálogo de descer.
     *
     * @test
     */
    public function um_cabecalho_disparatado_nao_parte_a_sincronizacao(): void
    {
        $this->comModulo('invoicing');

        $this->withHeaders([
            'X-Sos-Device'  => str_repeat('x', 5000),
            'X-Sos-Version' => str_repeat('v', 900),
        ])->getJson('/api/v1/invoicing/sync')->assertOk();

        // Um identificador impossível é ignorado — não se guarda lixo.
        $this->assertSame(0, PwaDevice::count());
    }

    /** @test */
    public function um_aparelho_sem_identificador_nao_e_registado(): void
    {
        $this->comModulo('invoicing');

        $this->getJson('/api/v1/invoicing/sync')->assertOk();

        $this->assertSame(0, PwaDevice::count());
    }

    /**
     * O ENSAIO QUE IMPORTA: o aparelho atrasado aparece.
     *
     * @test
     */
    public function o_ecra_marca_quem_nao_tem_a_ultima_versao(): void
    {
        $this->comoSuperAdmin();

        $actual = app(PwaController::class)->buildVersion();

        $emDia = $this->aparelho(['app_version' => $actual]);
        $atrasado = $this->aparelho(['app_version' => 'versao-de-antes']);
        $mudo = $this->aparelho(['app_version' => null]);

        // Dois atrasados: o que corre versão antiga E o que não diz nada. Não
        // saber o que um aparelho corre é o mesmo problema com outro nome.
        $this->assertSame(2, $this->getJson(self::API)->assertOk()->json('resumo.atrasados'));

        $ids = collect($this->getJson(self::API . '?filtro=atrasados')->json('aparelhos'))->pluck('id');

        $this->assertTrue($ids->contains($atrasado->id));
        $this->assertTrue($ids->contains($mudo->id));
        $this->assertFalse($ids->contains($emDia->id), 'um aparelho actualizado não pode aparecer como atrasado');
    }

    /** @test */
    public function o_resumo_conta_tudo_e_nao_so_o_que_o_filtro_deixa_ver(): void
    {
        $this->comoSuperAdmin();

        $this->aparelho(['standalone' => true]);
        $this->aparelho(['standalone' => false]);
        $this->aparelho(['standalone' => false]);

        $r = $this->getJson(self::API . '?filtro=instalados')->assertOk();

        // Um resumo que encolhe com o filtro faz o problema parecer menor do
        // que é — quem lê fica com o número errado na cabeça.
        $this->assertSame(3, $r->json('resumo.aparelhos'));
        $this->assertSame(1, $r->json('resumo.instalados'));
        $this->assertCount(1, $r->json('aparelhos'));
    }

    /**
     * A procura não pode furar o filtro. O componente juntava-a com `orWhere`
     * soltos: procurar uma versão com «instalados» trazia os não instalados.
     *
     * @test
     */
    public function procurar_nao_fura_o_filtro(): void
    {
        $this->comoSuperAdmin();

        $this->aparelho(['standalone' => true, 'app_version' => 'v-procurada']);
        $browser = $this->aparelho(['standalone' => false, 'app_version' => 'v-procurada']);

        $ids = collect($this->getJson(self::API . '?filtro=instalados&procura=v-procurada')->json('aparelhos'))->pluck('id');

        $this->assertCount(1, $ids);
        $this->assertFalse($ids->contains($browser->id));
    }

    /** @test */
    public function os_adormecidos_sao_os_que_ha_muito_nao_falam(): void
    {
        $this->comoSuperAdmin();

        $this->aparelho(['last_seen_at' => now()->subDay()]);
        $adormecido = $this->aparelho(['last_seen_at' => now()->subDays(40)]);

        $this->assertSame(1, $this->getJson(self::API)->json('resumo.adormecidos'));

        $this->assertSame(
            [$adormecido->id],
            collect($this->getJson(self::API . '?filtro=adormecidos')->json('aparelhos'))->pluck('id')->all()
        );
    }

    /** @test */
    public function o_ecra_e_so_para_o_super_admin_da_plataforma(): void
    {
        // O utilizador normal do TenantTestCase não é super admin.
        $this->get('/superadmin/aparelhos-pwa')->assertForbidden();
        $this->getJson(self::API)->assertForbidden();
    }

    /** Dá ao utilizador do ensaio o acesso de super admin da plataforma. */
    private function comoSuperAdmin(): void
    {
        $this->user->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($this->user->fresh());
    }
}
