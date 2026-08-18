<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Plataforma\TrocarDePlano;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Trocar de plano: o antigo morre, o novo nasce limpo.
 *
 * O ecrã reaproveitava a subscrição e só lhe trocava o plano — ficava lá o
 * fim do teste e as datas do anterior, e a empresa acabava com um plano novo
 * a correr com as contas do velho.
 */
class TrocarDePlanoTest extends TestCase
{
    use DatabaseTransactions;

    private function plano(string $nome, float $preco, int $teste = 0): Plan
    {
        return Plan::create([
            'name' => $nome, 'slug' => strtolower($nome) . '-' . uniqid(),
            'price_monthly' => $preco, 'trial_days' => $teste,
            'max_users' => 10, 'max_storage_mb' => 5000, 'is_active' => true,
        ]);
    }

    private function empresa(): Tenant
    {
        return Tenant::create([
            'name' => 'Teste Troca ' . uniqid(), 'email' => uniqid() . '@teste.local',
            'nif' => '5' . random_int(10000000, 99999999),
        ]);
    }

    private function trocar(Tenant $t, Plan $p): \App\Models\Subscription
    {
        return app(TrocarDePlano::class)->aplicar($t, $p);
    }

    public function test_primeira_vez_com_plano_que_tem_teste_comeca_em_teste(): void
    {
        $s = $this->trocar($this->empresa(), $this->plano('Com teste', 5900, 14));

        $this->assertSame('trial', $s->status);
        $this->assertNotNull($s->trial_ends_at);
        $this->assertEqualsWithDelta(14, now()->diffInDays($s->trial_ends_at), 1);
    }

    public function test_quem_ja_usou_um_plano_nao_ganha_teste_outra_vez(): void
    {
        $t = $this->empresa();

        $this->trocar($t, $this->plano('Primeiro', 4900, 14));
        $s = $this->trocar($t, $this->plano('Segundo', 17900, 14));

        // Sem isto, bastava trocar de plano para ganhar outro teste, e outro.
        $this->assertSame('active', $s->status);
        $this->assertNull($s->trial_ends_at);
    }

    public function test_o_plano_antigo_fica_cancelado_e_a_zero(): void
    {
        $t = $this->empresa();

        $velha = $this->trocar($t, $this->plano('Velho', 4900, 14));
        $this->trocar($t, $this->plano('Novo', 17900));

        $velha->refresh();

        $this->assertSame('cancelled', $velha->status);
        $this->assertNull($velha->trial_ends_at, 'uma cancelada com teste no futuro aparece nos relatórios como em teste');
    }

    public function test_fica_apenas_uma_subscricao_viva(): void
    {
        $t = $this->empresa();

        $this->trocar($t, $this->plano('A', 4900));
        $this->trocar($t, $this->plano('B', 5900));
        $novo = $this->trocar($t, $this->plano('C', 17900));

        $vivas = $t->subscriptions()->whereNotIn('status', ['cancelled', 'expired'])->get();

        $this->assertCount(1, $vivas);
        $this->assertSame($novo->id, $vivas->first()->id);
    }

    public function test_os_limites_da_empresa_acompanham_o_plano(): void
    {
        $t = $this->empresa();
        $p = $this->plano('Grande', 44900);

        $this->trocar($t, $p);

        // Senão a empresa fica com o plano novo e os tectos do antigo.
        $this->assertSame(10, (int) $t->fresh()->max_users);
        $this->assertSame(5000, (int) $t->fresh()->max_storage_mb);
    }

    public function test_um_plano_sem_teste_entra_activo_mesmo_a_primeira_vez(): void
    {
        $s = $this->trocar($this->empresa(), $this->plano('Sem teste', 5900, 0));

        $this->assertSame('active', $s->status);
        $this->assertNull($s->trial_ends_at);
    }
}
