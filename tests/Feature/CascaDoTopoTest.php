<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * O TOPO DE TODAS AS PÁGINAS — a empresa activa, o contador, o sino e as
 * mensagens da plataforma. Eram seis componentes Livewire no layout; são agora
 * pequenos ecrãs React que falam com `/api/v1/casca`.
 */
class CascaDoTopoTest extends TenantTestCase
{
    public function test_sem_sessao_nao_ha_topo(): void
    {
        auth()->logout();

        // 419 e não 401: a aplicação responde assim a um pedido JSON sem sessão,
        // para o ecrã saber que tem de voltar a entrar.
        $this->assertContains($this->getJson('/api/v1/casca/topo')->getStatusCode(), [401, 419]);
    }

    public function test_o_layout_monta_as_pecas_do_topo_em_react(): void
    {
        $html = $this->get('/home')->assertOk()->getContent();

        foreach (['casca/empresa', 'casca/subscricao', 'casca/notificacoes', 'casca/mensagens', 'casca/avisos'] as $peca) {
            $this->assertStringContainsString('data-peca="'.$peca.'"', $html);
        }

        // E já não há componentes Livewire no topo.
        foreach (['tenant-switcher', 'subscription-timer', 'notifications', 'mensagens-da-plataforma', 'send-welcome-email'] as $antigo) {
            $this->assertStringNotContainsString('&quot;name&quot;:&quot;'.$antigo.'&quot;', $html);
        }
    }

    /**
     * O topo tem de responder a quem já não tem subscrição — é quando o
     * contador interessa — e a troca tem de deixar SAIR para outra empresa.
     */
    public function test_sem_subscricao_o_topo_responde_e_a_troca_deixa_sair(): void
    {
        $semPlano = Tenant::create([
            'name' => 'Casa Sem Plano', 'slug' => 'sem-plano-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'sp'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);
        $this->user->tenants()->syncWithoutDetaching([$semPlano->id]);
        $this->user->switchTenant($semPlano->id);
        Subscription::where('tenant_id', $semPlano->id)->delete();

        $this->getJson('/api/v1/casca/topo')->assertOk()->assertJsonPath('empresa.activa.id', $semPlano->id);

        $this->postJson("/api/v1/casca/empresas/{$this->tenant->id}/entrar")->assertOk();
        $this->assertSame($this->tenant->id, (int) session('active_tenant_id'));
    }

    public function test_o_dono_da_plataforma_nao_tem_contador(): void
    {
        $this->user->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($this->user->fresh())->getJson('/api/v1/casca/topo')->assertOk()->assertJsonPath('prazo', null);
    }

    public function test_o_contador_diz_quanto_falta(): void
    {
        Subscription::where('tenant_id', $this->tenant->id)->update([
            'status' => 'active', 'current_period_end' => now()->addDays(10)->addHours(3),
        ]);

        $prazo = $this->getJson('/api/v1/casca/topo')->assertOk()->json('prazo');

        $this->assertFalse($prazo['expirado']);
        $this->assertSame(10, $prazo['dias']);
        $this->assertSame('yellow', $prazo['cor']);
        $this->assertStringStartsWith('10d', $prazo['resumo']);
    }

    /**
     * UM PEDIDO DE FUNDO NÃO DESFAZ A SESSÃO. As peças do topo pedem dados ao
     * abrir e de minuto a minuto; um GET que acabe depois de outro pedido ter
     * mudado a sessão escrevia por cima a sessão velha. E consumia o «flash»
     * que era para a página seguinte.
     */
    public function test_os_pedidos_do_topo_nao_gravam_a_sessao(): void
    {
        $escritas = new class extends \Illuminate\Session\NullSessionHandler {
            public int $vezes = 0;

            public function write($id, $data): bool
            {
                $this->vezes++;

                return true;
            }
        };

        // A MESMA sessão nos três pedidos, como acontece nos ensaios e num servidor
        // que não morre entre pedidos: a troca do GET não pode ficar para os seguintes.
        $sessao = new \Illuminate\Session\Store('ensaio', $escritas);

        $correr = function (string $metodo, string $morada = '/api/v1/casca/topo') use ($sessao) {
            $sessao->start();
            $pedido = \Illuminate\Http\Request::create($morada, $metodo);
            $pedido->setLaravelSession($sessao);

            (new \App\Http\Middleware\SessaoSoDeLeitura())->handle($pedido, fn () => response('ok'));

            // É o que o StartSession faz no fim de cada pedido.
            $sessao->save();
        };

        $correr('GET');
        $this->assertSame(0, $escritas->vezes, 'um GET da casca não escreve a sessão');

        $correr('POST');
        $this->assertSame(1, $escritas->vezes, 'um POST (trocar de empresa) grava como sempre');

        $correr('GET', '/home');
        $this->assertSame(2, $escritas->vezes, 'uma página grava como sempre (o flash e o endereço anterior)');
    }

    public function test_marcar_todas_como_lidas_zera_o_sino(): void
    {
        $this->user->notify(new class extends \Illuminate\Notifications\Notification {
            public function via($n): array { return ['database']; }
            public function toArray($n): array { return ['title' => 'Olá', 'message' => 'Teste', 'icon' => 'fa-bell', 'color' => 'blue']; }
        });

        $antes = $this->getJson('/api/v1/casca/notificacoes')->assertOk()->json('por_ler');
        $this->assertGreaterThanOrEqual(1, $antes);

        $this->postJson('/api/v1/casca/notificacoes/lidas')->assertOk();

        $this->assertSame(0, $this->getJson('/api/v1/casca/notificacoes')->json('por_ler'));
    }
}
