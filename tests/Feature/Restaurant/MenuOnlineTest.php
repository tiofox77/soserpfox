<?php

namespace Tests\Feature\Restaurant;

use App\Models\Product;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * A carta pública do restaurante.
 *
 * É a primeira página deste sistema que se abre SEM SESSÃO: quem a vê é um
 * cliente sentado a uma mesa, sem conta, sem empresa activa e sem permissões.
 * Isso muda o que pode correr mal.
 *
 * Os escopos automáticos por empresa não funcionam sem sessão. Uma leitura
 * que se esqueça de dizer de que empresa é serve a carta de outra casa — com
 * os preços de outra casa. É o defeito central que estes ensaios travam.
 *
 * Os outros dois: uma carta que ainda não foi publicada não pode estar
 * visível, e o QR de uma mesa tem de identificar a mesa certa.
 */
class MenuOnlineTest extends TenantTestCase
{
    private RestaurantSettings $definicoes;

    private DiningTable $mesa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant');
        $this->comModulo('invoicing');

        $this->definicoes = RestaurantSettings::forTenant($this->tenant->id);
        $this->definicoes->update([
            'menu_slug' => 'casa-de-teste',
            'online_menu_enabled' => true,
            'menu_whatsapp_enabled' => true,
            'menu_whatsapp_number' => '+244 900 111 222',
            'menu_title' => 'Casa de Teste',
            'menu_show_prices' => true,
        ]);

        $sala = Venue::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'S1',
            'name' => 'Salão',
            'is_active' => true,
        ]);

        $zona = Area::create([
            'tenant_id' => $this->tenant->id,
            'venue_id' => $sala->id,
            'name' => 'Esplanada',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->mesa = DiningTable::create([
            'tenant_id' => $this->tenant->id,
            'venue_id' => $sala->id,
            'area_id' => $zona->id,
            'code' => 'M12',
            'name' => 'Mesa 12',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Muamba de Galinha',
            'code' => 'MUAMBA',
            'price' => 5500,
            'type' => 'produto',
            'manage_stock' => false,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function a_carta_publicada_abre_sem_sessao(): void
    {
        auth()->logout();

        $this->get('/menu/casa-de-teste')
            ->assertOk()
            ->assertSee('Casa de Teste')
            ->assertSee('Muamba de Galinha');
    }

    /** @test */
    public function uma_carta_por_publicar_nao_existe(): void
    {
        $this->definicoes->update(['online_menu_enabled' => false]);

        auth()->logout();

        // 404 e não 403: a diferença entre "não há" e "está desligado" contava
        // a estranhos que aquele restaurante é cliente deste sistema.
        $this->get('/menu/casa-de-teste')->assertNotFound();
    }

    /** @test */
    public function um_endereco_que_nao_existe_da_404(): void
    {
        auth()->logout();

        $this->get('/menu/casa-que-nao-existe')->assertNotFound();
    }

    /**
     * O ENSAIO QUE IMPORTA: a carta é da casa certa.
     *
     * Sem sessão não há empresa activa, e os escopos automáticos não filtram
     * nada. Uma consulta sem `where('tenant_id')` explícito mostrava, na
     * carta desta casa, os pratos e os preços da casa do lado.
     *
     * @test
     */
    public function a_carta_nunca_mostra_pratos_de_outra_empresa(): void
    {
        $vizinha = Tenant::create([
            'name' => 'Restaurante Vizinho',
            'slug' => 'vizinho-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'v'.uniqid().'@exemplo.ao',
            'is_active' => true,
        ]);

        Product::withoutGlobalScopes()->create([
            'tenant_id' => $vizinha->id,
            'name' => 'Prato Secreto do Vizinho',
            'code' => 'SEGREDO',
            'price' => 9999,
            'type' => 'produto',
            'manage_stock' => false,
            'is_active' => true,
        ]);

        auth()->logout();

        $this->get('/menu/casa-de-teste')
            ->assertOk()
            ->assertSee('Muamba de Galinha')
            ->assertDontSee('Prato Secreto do Vizinho');
    }

    /** @test */
    public function o_qr_da_mesa_identifica_a_mesa(): void
    {
        auth()->logout();

        $this->get('/menu/casa-de-teste/M12')
            ->assertOk()
            ->assertSee('Mesa 12');
    }

    /** @test */
    public function um_codigo_de_mesa_desconhecido_avisa_em_vez_de_inventar(): void
    {
        auth()->logout();

        // Um QR antigo, de uma mesa que já foi removida. Fingir que se sabe
        // onde a pessoa está sentada é pior do que perguntar.
        // O ecrã diz «Mesa não reconhecida» quando a página lho conta.
        $mesa = $this->props($this->get('/menu/casa-de-teste/MESA-QUE-NAO-EXISTE'))['mesa'];

        $this->assertFalse($mesa['reconhecida']);
        $this->assertSame('MESA-QUE-NAO-EXISTE', $mesa['codigo']);
    }

    /** @test */
    public function a_mensagem_de_whatsapp_leva_a_mesa_e_os_artigos(): void
    {
        auth()->logout();

        // A mensagem monta-se no telemóvel (mensagemDoWhatsapp.test.ts guarda o
        // texto: a mesa à cabeça, «2x Muamba de Galinha»). Daqui sai o que ela
        // precisa: o número só com dígitos e o rótulo da mesa.
        $props = $this->props($this->get('/menu/casa-de-teste/M12'));

        $this->assertSame('244900111222', $props['casa']['whatsapp']);
        $this->assertSame('Mesa 12', $props['mesa']['rotulo']);
        $this->assertTrue($props['mesa']['reconhecida']);
    }

    /** @test */
    public function sem_numero_de_whatsapp_nao_ha_botao_que_nao_leva_a_lado_nenhum(): void
    {
        $this->definicoes->update(['menu_whatsapp_number' => null]);

        auth()->logout();

        $this->assertNull($this->props($this->get('/menu/casa-de-teste'))['casa']['whatsapp']);

        // E com o WhatsApp desligado, mesmo com número, também não.
        $this->definicoes->update(['menu_whatsapp_number' => '+244 900 111 222', 'menu_whatsapp_enabled' => false]);
        $this->assertNull($this->props($this->get('/menu/casa-de-teste'))['casa']['whatsapp']);
    }

    /** @test */
    public function os_artigos_sem_preco_ficam_fora_da_carta(): void
    {
        Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Farinha a granel',
            'code' => 'FARINHA',
            'price' => 0,
            'type' => 'produto',
            'manage_stock' => true,
            'is_active' => true,
        ]);

        auth()->logout();

        // Um artigo a zero é matéria-prima ou um registo por acabar. Numa
        // carta pública lê-se como oferta — e alguém vai pedi-lo.
        $this->get('/menu/casa-de-teste')
            ->assertOk()
            ->assertDontSee('Farinha a granel');
    }

    /** @test */
    public function a_folha_de_qr_traz_um_cartao_por_mesa_mais_o_geral(): void
    {
        $this->comPermissoes('restaurant.settings.view');

        $resposta = $this->get('/restaurant/menu/qr');

        $resposta->assertOk()
            ->assertSee('Mesa 12')
            // O cartão GERAL, sem mesa: vale para o balcão, a montra e a porta.
            ->assertSee('Casa de Teste')
            ->assertSee(url('/menu/casa-de-teste/M12'));

        // As imagens vão EMBUTIDAS. Uma folha que peça vinte ficheiros ao
        // servidor imprime metade das vezes — o diálogo de impressão abre
        // antes de as imagens chegarem, e sai papel com quadrados vazios.
        $resposta->assertSee('data:image/png;base64,', false);
    }

    /** @test */
    public function sem_endereco_nao_se_geram_qr_que_apontam_para_lado_nenhum(): void
    {
        $this->definicoes->update(['menu_slug' => null, 'online_menu_enabled' => false]);
        $this->comPermissoes('restaurant.settings.view');

        // Um QR que aponta para /menu/ vazio dá 404 a quem o apontar — depois
        // de impresso e colado em vinte mesas.
        $this->get('/restaurant/menu/qr')
            ->assertRedirect(route('restaurant.settings'));
    }

    /** @test */
    public function o_qr_de_uma_mesa_de_outra_casa_nao_se_gera(): void
    {
        $this->comPermissoes('restaurant.settings.view');

        // Sem esta guarda, escrever um código qualquer gerava um QR desta
        // empresa com a mesa de outra — um código que nunca ia reconhecer nada.
        $this->get('/restaurant/menu/qr/imagem/MESA-DE-OUTRA-CASA')->assertNotFound();
    }

    /** @test */
    public function o_qr_de_uma_mesa_desta_casa_descarrega_se(): void
    {
        $this->comPermissoes('restaurant.settings.view');

        $this->get('/restaurant/menu/qr/imagem/M12')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    /** @test */
    public function pedir_pela_pagina_esta_desligado_por_omissao(): void
    {
        $prato = Product::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->pedir(['mesa' => 'M12', 'escolhas' => [['id' => $prato->id, 'quantidade' => 1]]])
            ->assertStatus(422)->assertJsonValidationErrors('pedido');

        $this->assertSame(0, \App\Models\Restaurant\MenuOrder::withoutGlobalScopes()->count());
    }

    /** @test */
    public function o_pedido_da_pagina_fica_a_espera_e_nao_vira_comanda(): void
    {
        $this->definicoes->update(['menu_orders_enabled' => true]);

        $prato = Product::where('tenant_id', $this->tenant->id)->firstOrFail();

        \Illuminate\Support\Facades\RateLimiter::clear('menu-pedido:'.$this->tenant->id.':'.$this->mesa->id);

        $this->pedir(['mesa' => 'M12', 'escolhas' => [['id' => $prato->id, 'quantidade' => 2]], 'nome' => 'Cliente da Esplanada'])
            ->assertCreated();

        $pedido = \App\Models\Restaurant\MenuOrder::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('pending', $pedido->status);
        $this->assertSame($this->mesa->id, $pedido->table_id);
        $this->assertSame(2, $pedido->items[0]['quantity']);
        $this->assertEqualsWithDelta(11000, (float) $pedido->estimated_total, 0.01);

        // O ESSENCIAL: não nasceu comanda nenhuma.
        //
        // Uma comanda exige turno aberto — é a regra que impede vender com a
        // caixa fechada. O cliente à mesa não tem turno; se esta página abrisse
        // comandas, qualquer pessoa com o endereço lançava vendas numa caixa
        // que ninguém abriu.
        $this->assertSame(0, \App\Models\Restaurant\Order::withoutGlobalScopes()->count());
    }

    /** @test */
    public function um_pedido_sem_mesa_nao_se_envia(): void
    {
        $this->definicoes->update(['menu_orders_enabled' => true]);

        $prato = Product::where('tenant_id', $this->tenant->id)->firstOrFail();

        // Sem mesa o pedido não tem destino, e a mesa é a única coisa que
        // amarra quem pede a alguém que está mesmo lá dentro.
        $this->pedir(['escolhas' => [['id' => $prato->id, 'quantidade' => 1]]])
            ->assertStatus(422)->assertJsonValidationErrors('pedido');

        $this->assertSame(0, \App\Models\Restaurant\MenuOrder::withoutGlobalScopes()->count());
    }

    /** @test */
    public function a_mesma_mesa_nao_pode_enviar_pedidos_em_catadupa(): void
    {
        $this->definicoes->update(['menu_orders_enabled' => true]);

        $prato = Product::where('tenant_id', $this->tenant->id)->firstOrFail();

        \Illuminate\Support\Facades\RateLimiter::clear('menu-pedido:'.$this->tenant->id.':'.$this->mesa->id);
        $this->pedir(['mesa' => 'M12', 'escolhas' => [['id' => $prato->id, 'quantidade' => 1]]])->assertCreated();

        // Um endereço público sem travão é um convite a encher a sala de
        // pedidos falsos.
        $this->pedir(['mesa' => 'M12', 'escolhas' => [['id' => $prato->id, 'quantidade' => 1]]])
            ->assertStatus(422)->assertJsonValidationErrors('pedido');

        $this->assertSame(1, \App\Models\Restaurant\MenuOrder::withoutGlobalScopes()->count());
    }

    /** @test */
    public function esconder_precos_esconde_os_precos(): void
    {
        $this->definicoes->update(['menu_show_prices' => false]);

        auth()->logout();

        $r = $this->get('/menu/casa-de-teste')
            ->assertOk()
            ->assertSee('Muamba de Galinha')
            ->assertDontSee('5.500,00');

        // O preço nem chega ao browser: escondido não é só não desenhado.
        $this->assertNull($this->props($r)['pratos'][0]['preco']);
        $this->assertStringNotContainsString('5500', $r->getContent());
    }

    /** @test */
    public function um_artigo_de_outra_casa_nao_entra_no_pedido(): void
    {
        $this->definicoes->update(['menu_orders_enabled' => true]);
        \Illuminate\Support\Facades\RateLimiter::clear('menu-pedido:'.$this->tenant->id.':'.$this->mesa->id);

        $vizinha = Tenant::create([
            'name' => 'Vizinho', 'slug' => 'vz-'.uniqid(), 'nif' => (string) random_int(500000000, 599999999),
            'email' => 'vz'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);
        $alheio = Product::withoutGlobalScopes()->create([
            'tenant_id' => $vizinha->id, 'name' => 'Prato Alheio', 'code' => 'ALHEIO', 'price' => 100,
            'type' => 'produto', 'manage_stock' => false, 'is_active' => true,
        ]);

        // Um id escrito à mão, de outra empresa, não faz um pedido.
        $this->pedir(['mesa' => 'M12', 'escolhas' => [['id' => $alheio->id, 'quantidade' => 3]]])
            ->assertStatus(422)->assertJsonValidationErrors('pedido');

        $this->assertSame(0, \App\Models\Restaurant\MenuOrder::withoutGlobalScopes()->count());
    }

    /** @test */
    public function a_pagina_monta_a_carta_em_react_com_a_mesa(): void
    {
        auth()->logout();

        $props = $this->props($this->get('/menu/casa-de-teste/M12'));

        $this->assertSame('Casa de Teste', $props['casa']['titulo']);
        $this->assertSame(['Muamba de Galinha'], array_column($props['pratos'], 'nome'));
        $this->assertSame(5500.0, (float) $props['pratos'][0]['preco']);
    }

    private function props(\Illuminate\Testing\TestResponse $r): array
    {
        $r->assertOk()->assertSee('data-ecra="restaurant/carta-online"', false);
        preg_match('/data-props="([^"]*)"/', $r->getContent(), $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
    }

    private function pedir(array $dados): \Illuminate\Testing\TestResponse
    {
        auth()->logout();

        return $this->postJson('/api/publico/restaurante/casa-de-teste/pedido', $dados);
    }
}
