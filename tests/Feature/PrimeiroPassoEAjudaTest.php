<?php

namespace Tests\Feature;

use App\Models\Hotel\RoomType;
use App\Models\Product;
use App\Models\Support\Ticket;
use App\Services\Agent\EmissaoDeTokens;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * A PRIMEIRA UTILIZAÇÃO (26/09/2026): o próximo passo pelo módulo do plano, e
 * «Preciso de ajuda para começar» com o consentimento do WhatsApp — opcional,
 * com finalidade, versão do texto, número e recusa gravados.
 */
class PrimeiroPassoEAjudaTest extends TenantTestCase
{
    private const INICIO = '/api/v1/casca/inicio';

    private const AJUDA = '/api/v1/casca/ajuda';

    // ══════════════ O próximo passo ══════════════

    public function test_o_hotel_sem_quartos_comeca_pelo_primeiro_tipo_de_quarto(): void
    {
        $this->comModulo('hotel')->comPermissoes('hotel.room-types.view');

        $passo = $this->getJson(self::INICIO)->assertOk()->json('primeiro_passo');

        $this->assertSame('hotel', $passo['modulo']);
        $this->assertSame(route('hotel.room-types'), $passo['url']);
    }

    public function test_feito_o_passo_segue_para_o_seguinte_e_no_fim_desaparece(): void
    {
        $this->comModulo('hotel')->comModulo('invoicing')
            ->comPermissoes('hotel.room-types.view', 'invoicing.products.view');

        RoomType::create(['tenant_id' => $this->tenant->id, 'name' => 'Duplo', 'base_price' => 25000]);
        $this->assertSame('invoicing', $this->getJson(self::INICIO)->json('primeiro_passo.modulo'));

        Product::create(['tenant_id' => $this->tenant->id, 'code' => 'P1', 'name' => 'Pequeno-almoço']);
        $this->assertNull($this->getJson(self::INICIO)->json('primeiro_passo'));
    }

    /** Só aponta para ecrãs que a pessoa pode abrir. */
    public function test_sem_permissao_o_modulo_nao_da_passo(): void
    {
        $this->comModulo('hotel')->comModulo('invoicing')->comPermissoes('invoicing.products.view');

        $this->assertSame('invoicing', $this->getJson(self::INICIO)->json('primeiro_passo.modulo'));
    }

    /** Os serviços do salão vivem em invoicing_products: um produto qualquer não é um serviço do salão. */
    public function test_um_produto_da_facturacao_nao_conta_como_servico_do_salao(): void
    {
        $this->comModulo('salon')->comPermissoes('salon.services.view');
        Product::create(['tenant_id' => $this->tenant->id, 'code' => 'P1', 'name' => 'Champô']);

        $this->assertSame('salon', $this->getJson(self::INICIO)->json('primeiro_passo.modulo'));
    }

    public function test_o_passo_nunca_cria_dados(): void
    {
        $this->comModulo('hotel')->comModulo('invoicing')
            ->comPermissoes('hotel.room-types.view', 'invoicing.products.view');

        $this->getJson(self::INICIO)->assertOk();

        $this->assertSame(0, RoomType::where('tenant_id', $this->tenant->id)->count());
        $this->assertSame(0, Product::where('tenant_id', $this->tenant->id)->count());
    }

    /** Numa empresa com anos de casa e tudo feito, o cartão da ajuda seria ruído. */
    public function test_a_ajuda_e_para_quem_esta_a_comecar(): void
    {
        $this->assertNotNull($this->getJson(self::INICIO)->json('ajuda'), 'empresa nova');

        $this->tenant->forceFill(['created_at' => now()->subDays(60)])->save();
        // O utilizador guarda a empresa activa em memória: um novo, lido da base.
        $this->actingAs($this->user->fresh());
        $this->assertNull($this->getJson(self::INICIO)->json('ajuda'), 'empresa antiga sem passo por fazer');
    }

    // ══════════════ Preciso de ajuda para começar ══════════════

    public function test_sem_whatsapp_abre_o_pedido_e_grava_a_recusa(): void
    {
        $this->postJson(self::AJUDA, ['whatsapp' => false, 'mensagem' => 'Quero emitir a primeira factura'])
            ->assertCreated()
            ->assertJsonPath('novo', true);

        $ticket = Ticket::where('tenant_id', $this->tenant->id)->sole();
        $this->assertStringStartsWith('Ajuda para começar', $ticket->subject);
        $this->assertStringContainsString('Quero emitir a primeira factura', $ticket->description);
        $this->assertStringContainsString('Não aceitou contacto por WhatsApp', $ticket->description);

        $c = DB::table('consentimentos')->where('user_id', $this->user->id)->where('tipo', 'whatsapp_ajuda')->sole();
        $this->assertSame(0, (int) $c->aceite, 'a recusa também fica: é ela que impede o agente de escrever');
        $this->assertNull($c->contacto);
        $this->assertSame($this->tenant->id, (int) $c->tenant_id);
        $this->assertSame(config('privacidade.whatsapp_ajuda.versao'), $c->versao);
        $this->assertSame(config('privacidade.whatsapp_ajuda.finalidade'), $c->finalidade);
    }

    /** A caixa não é pré-marcada: sem a escolha dita, o pedido não passa. */
    public function test_a_escolha_do_whatsapp_tem_de_vir_dita(): void
    {
        $this->postJson(self::AJUDA, ['mensagem' => 'Olá'])->assertStatus(422)->assertJsonValidationErrors('whatsapp');
        $this->assertSame(0, Ticket::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_o_sim_exige_um_telemovel_angolano_e_grava_o_numero(): void
    {
        $this->postJson(self::AJUDA, ['whatsapp' => true])->assertStatus(422)->assertJsonValidationErrors('telefone');
        $this->postJson(self::AJUDA, ['whatsapp' => true, 'telefone' => '222 123 456'])
            ->assertStatus(422)->assertJsonValidationErrors('telefone');

        $this->postJson(self::AJUDA, ['whatsapp' => true, 'telefone' => '923 456 789'])->assertCreated();

        $c = DB::table('consentimentos')->where('user_id', $this->user->id)->where('tipo', 'whatsapp_ajuda')->sole();
        $this->assertSame(1, (int) $c->aceite);
        $this->assertSame('+244923456789', $c->contacto);
        $this->assertStringContainsString('+244923456789', Ticket::where('tenant_id', $this->tenant->id)->sole()->description);
    }

    public function test_dois_cliques_nao_abrem_dois_pedidos(): void
    {
        $this->postJson(self::AJUDA, ['whatsapp' => false])->assertCreated();
        $this->postJson(self::AJUDA, ['whatsapp' => false])->assertOk()->assertJsonPath('novo', false);

        $this->assertSame(1, Ticket::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_retirar_na_minha_conta_desliga_o_contacto(): void
    {
        $this->postJson(self::AJUDA, ['whatsapp' => true, 'telefone' => '923456789'])->assertCreated();

        $this->postJson('/api/v1/invoicing/react/conta/privacidade/whatsapp/retirar')
            ->assertOk()
            ->assertJsonPath('consentimentos.whatsapp_ajuda.aceite', false);

        $estado = \App\Services\Privacidade\Consentimentos::whatsappDe([$this->user->id])[$this->user->id];
        $this->assertFalse($estado['aceite']);
        $this->assertNull($estado['contacto']);
    }

    /** O agente lê o consentimento pelos contactos: sim com número, não, ou nunca perguntado. */
    public function test_o_agente_ve_o_consentimento_de_cada_pessoa(): void
    {
        config(['agent.token.exigir_ips' => false]);
        $r = app(EmissaoDeTokens::class)->emitir('openclaw-teste', $this->user, ['contacts:read'], [], 30);
        $cabecalho = ['Authorization' => 'Bearer ' . $r['em_claro']];
        $url = "/api/agent/v1/tenants/{$this->tenant->id}/contacts";

        $this->getJson($url, $cabecalho)->assertOk()->assertJsonPath('utilizadores.0.whatsapp', null);

        $this->postJson(self::AJUDA, ['whatsapp' => true, 'telefone' => '923456789'])->assertCreated();

        $this->getJson($url, $cabecalho)
            ->assertOk()
            ->assertJsonPath('utilizadores.0.whatsapp.aceite', true)
            ->assertJsonPath('utilizadores.0.whatsapp.contacto', '+244923456789')
            ->assertJsonPath('whatsapp_regras.versao_do_texto', config('privacidade.whatsapp_ajuda.versao'));
    }
}
