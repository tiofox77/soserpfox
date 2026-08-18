<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\EstadoDaSubscricao;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Em que pé está a subscrição de cada empresa.
 *
 * A lista dizia o plano e mais nada. Sem isto, um teste que expira passa
 * despercebido até alguém reparar por acaso — e uma empresa a usar de graça
 * há dois meses é receita perdida que ninguém contabilizou.
 */
class EstadoDaSubscricaoTest extends TestCase
{
    use DatabaseTransactions;

    private function empresaCom(?array $subscricao): Tenant
    {
        $t = Tenant::create([
            'name'  => 'Teste Sub ' . uniqid(),
            'email' => uniqid() . '@teste.local',
            'nif'   => '5' . random_int(10000000, 99999999),
        ]);

        if ($subscricao) {
            // A subscrição exige plano; qualquer um serve para o que se testa.
            $plano = \App\Models\Plan::first() ?? \App\Models\Plan::create([
                'name' => 'Teste', 'slug' => 'teste-' . uniqid(), 'price_monthly' => 0,
            ]);

            $t->subscriptions()->create(array_merge([
                'plan_id' => $plano->id,
                'status' => 'active',
                'amount' => 0,
            ], $subscricao));
        }

        return $t->fresh();
    }

    public function test_sem_subscricao_diz_que_nao_ha(): void
    {
        $e = EstadoDaSubscricao::para($this->empresaCom(null));

        $this->assertSame('Sem subscrição', $e['rotulo']);
        $this->assertNull($e['dias']);
    }

    public function test_em_teste_mostra_os_dias_que_faltam(): void
    {
        $e = EstadoDaSubscricao::para($this->empresaCom([
            'status' => 'trial', 'trial_ends_at' => now()->addDays(10),
        ]));

        $this->assertSame('Em teste', $e['rotulo']);
        $this->assertEqualsWithDelta(10, $e['dias'], 1);
    }

    public function test_teste_a_acabar_fica_a_amarelo(): void
    {
        // Três dias ou menos: é quando ainda dá para telefonar a tempo.
        $e = EstadoDaSubscricao::para($this->empresaCom([
            'status' => 'trial', 'trial_ends_at' => now()->addDays(2),
        ]));

        $this->assertSame('amber', $e['cor']);
    }

    public function test_teste_expirado_e_ainda_activo_e_alarme(): void
    {
        // Este é o caso que interessa apanhar: está a usar sem pagar.
        $e = EstadoDaSubscricao::para($this->empresaCom([
            'status' => 'trial', 'trial_ends_at' => now()->subDays(15),
        ]));

        $this->assertSame('Teste expirado', $e['rotulo']);
        $this->assertSame('red', $e['cor']);
        $this->assertLessThan(0, $e['dias'], 'os dias vêm negativos: já passaram');
    }

    public function test_activo_mostra_quando_renova(): void
    {
        $e = EstadoDaSubscricao::para($this->empresaCom([
            'status' => 'active', 'ends_at' => now()->addDays(20),
        ]));

        $this->assertSame('Activo', $e['rotulo']);
        $this->assertSame('emerald', $e['cor']);
    }

    public function test_activo_a_expirar_avisa(): void
    {
        $e = EstadoDaSubscricao::para($this->empresaCom([
            'status' => 'active', 'ends_at' => now()->addDays(5),
        ]));

        $this->assertSame('amber', $e['cor'], 'uma semana antes já se pode cobrar');
    }

    public function test_expirado_e_vermelho(): void
    {
        $e = EstadoDaSubscricao::para($this->empresaCom([
            'status' => 'active', 'ends_at' => now()->subDay(),
        ]));

        $this->assertSame('Expirado', $e['rotulo']);
        $this->assertSame('red', $e['cor']);
    }

    public function test_os_dias_saem_por_extenso(): void
    {
        $q = fn ($d) => \App\Support\EstadoDaSubscricao::quantoFalta($d);

        // "14d" e telegrafico: quem le a lista quer saber quanto falta sem
        // ter de decifrar nem passar o rato por cima.
        $this->assertSame('faltam 14 dias', $q(14));
        $this->assertSame('falta 1 dia', $q(1), 'singular');
        $this->assertSame('termina hoje', $q(0));
        $this->assertSame('terminou ontem', $q(-1));
        $this->assertSame('terminou ha 15 dias', $q(-15), 'um numero negativo nao se le');
        $this->assertSame('', $q(null));
    }

    public function test_a_data_de_fim_vem_no_resultado(): void
    {
        // Para o cartao a mostrar ao lado, em vez de a esconder no tooltip.
        $e = \App\Support\EstadoDaSubscricao::para($this->empresaCom([
            'status' => 'trial', 'trial_ends_at' => now()->addDays(14),
        ]));

        $this->assertSame(now()->addDays(14)->format('d/m/Y'), $e['ate']);
    }

    public function test_activo_sem_data_de_fim_diz_que_nao_expira(): void
    {
        // Acontece nos planos promocionais e nos activados a mao. O cartao
        // mostrava so 'Activo' e ficava-se sem saber se aquilo renova, quando,
        // ou se vai durar para sempre — que e o caso a vigiar, porque e
        // receita que nunca mais e cobrada.
        $e = \App\Support\EstadoDaSubscricao::para($this->empresaCom([
            'status' => 'active',
        ]));

        $this->assertSame('Activo', $e['rotulo']);
        $this->assertSame('sem prazo', $e['nota'] ?? null);
        $this->assertStringContainsString('nao expira', $e['detalhe']);
    }
}
