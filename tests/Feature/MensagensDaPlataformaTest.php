<?php

namespace Tests\Feature;

use App\Livewire\MensagensDaPlataforma;
use App\Livewire\SuperAdmin\MensagensPlataforma;
use App\Models\Plan;
use App\Models\PlatformMessage;
use App\Models\PlatformMessageRead;
use App\Models\Subscription;
use App\Models\Tenant;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O dono da plataforma a falar com as empresas.
 *
 * Antes disto não havia forma nenhuma: uma paragem para manutenção, uma
 * mudança de preços, uma obrigação nova da AGT, um aviso a um cliente em
 * particular — tudo saía por WhatsApp, à mão, empresa a empresa, e ninguém
 * sabia quem tinha lido.
 */
class MensagensDaPlataformaTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->user->update(['is_super_admin' => true]);
        \Cache::forget('mensagens-plataforma-no-ar');
    }

    private function mensagem(array $extra = []): PlatformMessage
    {
        \Cache::forget('mensagens-plataforma-no-ar');

        return PlatformMessage::create(array_merge([
            'title'    => 'Manutenção no domingo',
            'body'     => 'O sistema estará indisponível das 22h à 1h.',
            'level'    => 'aviso',
            'display'  => 'barra',
            'audience' => 'todas',
        ], $extra));
    }

    // ==================== quem a vê ====================

    public function test_uma_mensagem_para_todas_aparece(): void
    {
        $this->mensagem();

        Livewire::test(MensagensDaPlataforma::class)
            ->assertSee('Manutenção no domingo')
            ->assertSee('das 22h à 1h');
    }

    public function test_uma_mensagem_retirada_nao_aparece(): void
    {
        $this->mensagem(['is_active' => false]);

        Livewire::test(MensagensDaPlataforma::class)->assertDontSee('Manutenção no domingo');
    }

    public function test_uma_mensagem_agendada_para_amanha_ainda_nao_aparece(): void
    {
        $this->mensagem(['starts_at' => now()->addDay()]);

        Livewire::test(MensagensDaPlataforma::class)->assertDontSee('Manutenção no domingo');
    }

    public function test_uma_mensagem_terminada_nao_aparece(): void
    {
        $this->mensagem(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);

        Livewire::test(MensagensDaPlataforma::class)->assertDontSee('Manutenção no domingo');
    }

    /** Dirigida a outra empresa, não é para esta. */
    public function test_uma_mensagem_para_outra_empresa_nao_aparece(): void
    {
        $outra = Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);

        $this->mensagem(['audience' => 'empresas', 'tenant_ids' => [$outra->id]]);

        Livewire::test(MensagensDaPlataforma::class)->assertDontSee('Manutenção no domingo');
    }

    public function test_uma_mensagem_para_esta_empresa_aparece(): void
    {
        $this->mensagem(['audience' => 'empresas', 'tenant_ids' => [$this->tenant->id]]);

        Livewire::test(MensagensDaPlataforma::class)->assertSee('Manutenção no domingo');
    }

    /** Dirigida a um plano: só quem lá está a vê. */
    public function test_uma_mensagem_por_plano_segue_o_plano_da_empresa(): void
    {
        $plano = Plan::create([
            'name' => 'Business', 'slug' => 'business-' . uniqid(), 'description' => 'x',
            'price_monthly' => 44900, 'price_yearly' => 449000, 'trial_days' => 0,
            'max_users' => 20, 'max_companies' => 3, 'is_active' => true, 'order' => 1,
        ]);

        $this->mensagem(['audience' => 'planos', 'plan_ids' => [$plano->id]]);

        // A empresa está noutro plano.
        Livewire::test(MensagensDaPlataforma::class)->assertDontSee('Manutenção no domingo');

        // Passa a estar neste.
        Subscription::where('tenant_id', $this->tenant->id)->delete();
        Subscription::create([
            'tenant_id' => $this->tenant->id, 'plan_id' => $plano->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'amount' => 44900,
            'current_period_end' => now()->addMonth(),
        ]);

        Livewire::test(MensagensDaPlataforma::class)->assertSee('Manutenção no domingo');
    }

    // ==================== ler e dispensar ====================

    /** Ver uma mensagem deixa rasto — é como se sabe quem foi avisado. */
    public function test_ver_uma_mensagem_fica_registado(): void
    {
        $m = $this->mensagem();

        Livewire::test(MensagensDaPlataforma::class)->assertSee('Manutenção no domingo');

        $leitura = PlatformMessageRead::where('platform_message_id', $m->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($leitura);
        $this->assertNotNull($leitura->seen_at);
        $this->assertNull($leitura->dismissed_at);
    }

    public function test_dispensar_esconde_a_mensagem_para_sempre(): void
    {
        $m = $this->mensagem();

        Livewire::test(MensagensDaPlataforma::class)
            ->call('dispensar', $m->id)
            ->assertDontSee('Manutenção no domingo');

        // E continua escondida numa visita nova.
        Livewire::test(MensagensDaPlataforma::class)->assertDontSee('Manutenção no domingo');

        $this->assertNotNull(
            PlatformMessageRead::where('platform_message_id', $m->id)
                ->where('user_id', $this->user->id)
                ->value('dismissed_at')
        );
    }

    /** Uma mensagem que não se dispensa não tem botão para isso. */
    public function test_uma_mensagem_nao_dispensavel_nao_oferece_como_fechar(): void
    {
        $this->mensagem(['dismissible' => false, 'display' => 'popup']);

        Livewire::test(MensagensDaPlataforma::class)
            ->assertSee('Manutenção no domingo')
            ->assertSee('não pode ser dispensada');
    }

    /** O ecrã não pode cair por causa de um aviso. */
    public function test_uma_falha_nas_mensagens_nao_derruba_a_pagina(): void
    {
        \Cache::forget('mensagens-plataforma-no-ar');
        \Schema::rename('platform_messages', 'platform_messages_escondida');

        try {
            Livewire::test(MensagensDaPlataforma::class)->assertOk();
        } finally {
            \Schema::rename('platform_messages_escondida', 'platform_messages');
        }
    }

    // ==================== o ecrã de quem escreve ====================

    public function test_publicar_uma_mensagem(): void
    {
        Livewire::test(MensagensPlataforma::class)
            ->call('nova')
            ->set('title', 'Preços novos a partir de Setembro')
            ->set('body', 'Os planos multi-empresa passam a ter novo preço.')
            ->set('level', 'info')
            ->set('display', 'barra')
            ->set('audience', 'todas')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('platform_messages', [
            'title' => 'Preços novos a partir de Setembro',
        ]);
    }

    /** Escolher "empresas escolhidas" sem escolher nenhuma manda para ninguém. */
    public function test_publico_por_empresas_sem_empresas_e_recusado(): void
    {
        Livewire::test(MensagensPlataforma::class)
            ->call('nova')
            ->set('title', 'Aviso')
            ->set('body', 'Um aviso qualquer.')
            ->set('audience', 'empresas')
            ->set('tenant_ids', [])
            ->call('guardar')
            ->assertHasErrors('tenant_ids');

        $this->assertDatabaseMissing('platform_messages', ['title' => 'Aviso']);
    }

    public function test_o_fim_nao_pode_ser_antes_do_inicio(): void
    {
        Livewire::test(MensagensPlataforma::class)
            ->call('nova')
            ->set('title', 'Aviso')
            ->set('body', 'Um aviso qualquer.')
            ->set('starts_at', now()->addDays(3)->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('guardar')
            ->assertHasErrors('ends_at');
    }

    /** Publicar tem de aparecer já, e não daí a um minuto. */
    public function test_publicar_limpa_a_cache_para_a_mensagem_aparecer_ja(): void
    {
        // Aquecer a cache com "não há nada".
        Livewire::test(MensagensDaPlataforma::class)->assertDontSee('Urgente');

        Livewire::test(MensagensPlataforma::class)
            ->call('nova')
            ->set('title', 'Urgente: paragem agora')
            ->set('body', 'Vamos parar em cinco minutos.')
            ->set('level', 'urgente')
            ->call('guardar');

        Livewire::test(MensagensDaPlataforma::class)->assertSee('Urgente: paragem agora');
    }

    public function test_quem_nao_e_dono_da_plataforma_nao_entra(): void
    {
        $this->user->update(['is_super_admin' => false]);

        Livewire::test(MensagensPlataforma::class)->assertForbidden();
    }

    /** Quantas empresas isto alcança — para se saber antes de publicar. */
    public function test_o_alcance_e_contado(): void
    {
        $m = $this->mensagem(['audience' => 'empresas', 'tenant_ids' => [$this->tenant->id]]);

        $this->assertSame(1, $m->quantasEmpresas());
    }
}
