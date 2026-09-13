<?php

namespace Tests\Feature;

use App\Models\PlatformMessage;
use App\Models\PlatformMessageRead;
use Tests\TenantTestCase;

/**
 * O painel de avisos no ecra de entrada, e o fuso horario do agendamento.
 *
 * O fuso e o que fez a mensagem nao aparecer: o campo datetime-local nao tem
 * fuso nenhum e devolve os digitos escritos. Quem escreve esta em Angola
 * (UTC+1) e a aplicacao comparava em UTC, pelo que uma mensagem marcada para
 * as 10:20 so entrava no ar as 11:20 para quem a marcou.
 *
 * A aplicacao passou entretanto a correr em hora de Angola, o que resolve isto
 * pela raiz: os digitos escritos ja sao os que a base guarda. Estes testes
 * ficam porque continuam a guardar a mesma promessa — a hora escrita e a hora
 * a que a mensagem entra no ar — e e essa promessa que interessa, nao a
 * mecanica com que se cumpre.
 */
class AvisosDaPlataformaTest extends TenantTestCase
{
    private function mensagem(array $campos = []): PlatformMessage
    {
        return PlatformMessage::create(array_merge([
            'title'       => 'Suporte',
            'body'        => 'Podem abrir ticket ou contactar 942705533.',
            'level'       => 'info',
            'display'     => 'barra',
            'audience'    => 'todas',
            'dismissible' => true,
            'is_active'   => true,
            'created_by'  => $this->user->id,
        ], $campos));
    }

    // ---- o fuso do agendamento ------------------------------------------

    /** A hora escrita e de Angola, e o instante que ela representa e esse. */
    public function test_a_hora_escrita_representa_o_instante_de_angola(): void
    {
        $guardada = PlatformMessage::doRelogioDeParede('2026-08-14T10:20');

        $this->assertSame('2026-08-14 09:20', $guardada->utc()->format('Y-m-d H:i'),
            'as 10:20 de Angola sao as 09:20 em UTC');
    }

    /** E o caminho de volta devolve exactamente o que foi escrito. */
    public function test_o_formulario_mostra_a_hora_que_foi_escrita(): void
    {
        $m = $this->mensagem(['starts_at' => PlatformMessage::doRelogioDeParede('2026-08-14T10:20')]);

        $this->assertSame('2026-08-14 10:20', $m->noRelogioDeParede('starts_at')->format('Y-m-d H:i'));
    }

    /** Vazio continua a ser "desde ja" e nao uma data inventada. */
    public function test_sem_hora_escrita_nao_ha_limite(): void
    {
        $this->assertNull(PlatformMessage::doRelogioDeParede(null));
        $this->assertNull(PlatformMessage::doRelogioDeParede(''));
    }

    /** Uma data que nao se consegue ler nao pode calar a mensagem. */
    public function test_uma_data_ilegivel_fica_sem_limite_em_vez_de_esconder(): void
    {
        $this->assertNull(PlatformMessage::doRelogioDeParede('isto nao e uma data'));
    }

    /**
     * O caso do ecra: marcada para as 10:20 de Angola, esta no ar as 10:20 de
     * Angola. Antes so aparecia as 11:20.
     */
    public function test_uma_mensagem_marcada_para_agora_esta_no_ar_agora(): void
    {
        // O relogio da aplicacao ja e o de Angola, por isso o travelTo marca
        // hora de Angola: as 10:30, a mensagem das 10:20 ja passou a hora.
        $this->travelTo('2026-08-14 10:30:00');

        $m = $this->mensagem(['starts_at' => PlatformMessage::doRelogioDeParede('2026-08-14T10:20')]);

        $this->assertTrue(
            PlatformMessage::noAr()->whereKey($m->id)->exists(),
            'a mensagem marcada para as 10:20 de Angola devia estar no ar as 10:30 de Angola'
        );

        $this->travelBack();
    }

    /** E antes da hora continua a nao aparecer. */
    public function test_antes_da_hora_marcada_continua_fora(): void
    {
        $this->travelTo('2026-08-14 10:10:00');   // hora de Angola, dez minutos antes

        $m = $this->mensagem(['starts_at' => PlatformMessage::doRelogioDeParede('2026-08-14T10:20')]);

        $this->assertFalse(PlatformMessage::noAr()->whereKey($m->id)->exists());

        $this->travelBack();
    }

    /** O que se escreve no ecra do super admin chega a base como o mesmo instante. */
    public function test_publicar_pelo_ecra_guarda_o_instante_certo(): void
    {
        $this->comoDonoDaPlataforma();

        $this->postJson('/api/v1/plataforma/react/avisos', [
            'title' => 'Suporte',
            'body' => 'Contactar 942705533.',
            'level' => 'info',
            'display' => 'barra',
            'audience' => 'todas',
            'starts_at' => '2026-08-14T10:20',
        ])->assertOk();

        $m = PlatformMessage::where('title', 'Suporte')->firstOrFail();

        $this->assertSame('2026-08-14 09:20', $m->starts_at->utc()->format('Y-m-d H:i'));
    }

    // ---- o painel no ecra de entrada ------------------------------------

    /** O aviso no ar aparece no painel. */
    public function test_o_painel_mostra_o_aviso_no_ar(): void
    {
        $this->mensagem();

        $this->assertStringContainsString('Suporte', $this->painel());
        $this->assertStringContainsString('942705533', $this->painel());
    }

    /**
     * O que a barra perdia: dispensado ali, o aviso continua a poder ser lido
     * aqui. Era esta a razao de ser do painel.
     */
    public function test_um_aviso_dispensado_na_barra_continua_no_painel(): void
    {
        $m = $this->mensagem();

        $this->postJson("/api/v1/casca/mensagens/{$m->id}/dispensar")->assertOk();

        $this->assertStringNotContainsString('942705533', $this->barra());

        $this->assertStringContainsString('942705533', $this->painel());
        $this->assertTrue($this->getJson('/api/v1/casca/avisos')->json('avisos.0.dispensada'), 'marcado como lido');
    }

    /** Sem avisos o painel nao se desenha. */
    public function test_sem_avisos_o_painel_nao_ocupa_o_ecra(): void
    {
        $this->assertSame([], $this->getJson('/api/v1/casca/avisos')->assertOk()->json('avisos'));
    }

    /** Uma mensagem retirada do ar sai do painel. */
    public function test_um_aviso_retirado_sai_do_painel(): void
    {
        $this->mensagem(['is_active' => false]);

        $this->assertStringNotContainsString('942705533', $this->painel());
    }

    /** Um aviso de outra empresa nao entra aqui. */
    public function test_um_aviso_de_outra_empresa_nao_aparece(): void
    {
        $this->mensagem(['audience' => 'empresas', 'tenant_ids' => [$this->tenant->id + 9999]]);

        $this->assertStringNotContainsString('942705533', $this->painel());
    }

    /** O painel nao pode derrubar o ecra de entrada. */
    public function test_uma_falha_no_painel_nao_derruba_o_ecra(): void
    {
        \Schema::rename('platform_messages', 'platform_messages_escondida');

        try {
            $this->painel();
        } finally {
            \Schema::rename('platform_messages_escondida', 'platform_messages');
        }
    }

    /** O painel entra mesmo no ecra de entrada, e nao so existe. */
    public function test_o_ecra_de_entrada_carrega_o_painel(): void
    {
        $this->mensagem();

        $this->actingAs($this->user)
            ->get('/home')
            ->assertOk()
            ->assertSee('data-peca="casca/avisos"', false);
    }

    /**
     * O que a barra do topo recebe, como texto. A barra e o pop-up passaram a
     * React e pedem as mensagens a `/api/v1/casca/mensagens`.
     */
    private function barra(): string
    {
        return json_encode($this->getJson('/api/v1/casca/mensagens')->assertOk()->json(), JSON_UNESCAPED_UNICODE);
    }

    /** O que o painel do ecrã de entrada recebe, como texto. */
    private function painel(): string
    {
        return json_encode($this->getJson('/api/v1/casca/avisos')->assertOk()->json(), JSON_UNESCAPED_UNICODE);
    }

    private function comoDonoDaPlataforma(): void
    {
        $this->user->update(['is_super_admin' => true]);
        $this->actingAs($this->user->fresh());
    }
}
