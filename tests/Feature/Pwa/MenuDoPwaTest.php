<?php

namespace Tests\Feature\Pwa;

use App\Models\Invoicing\InvoicingSettings;
use App\Support\MenuDoPwa;
use Spatie\Permission\Models\Permission;
use Tests\TenantTestCase;

/**
 * Quem vê o quê no PWA.
 *
 * Três regras, e a ordem entre elas é o que este ficheiro fixa: o módulo e a
 * permissão são do sistema e não se negoceiam; a escolha da empresa é uma
 * preferência e só pode TIRAR do que as outras duas já deixaram passar.
 *
 * E o que mais importa: o menu e a ROTA respondem o mesmo. Esconder um botão
 * nunca foi fechar uma porta, e enquanto as duas regras viveram em ficheiros
 * diferentes discordaram — escondia-se a entrada e o endereço continuava a
 * abrir a quem o escrevesse à mão.
 */
class MenuDoPwaTest extends TenantTestCase
{
    private const DEFINICOES = '/api/v1/invoicing/react/definicoes';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
        $this->comModulo('treasury');
    }

    /** Dá ao utilizador de teste exactamente estas permissões. */
    private function comAsPermissoes(string ...$nomes): void
    {
        foreach ($nomes as $nome) {
            Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
        }

        $this->user->syncPermissions($nomes);
        $this->user->forgetCachedPermissions();
    }

    private function chaves(): array
    {
        return array_keys(MenuDoPwa::visiveis($this->user->fresh(), $this->tenant->fresh()));
    }

    // ── O módulo ──────────────────────────────────────────────────────

    public function test_sem_o_modulo_de_restaurante_nao_ha_mesas(): void
    {
        $this->comAsPermissoes('restaurant.orders.view', 'invoicing.pos.access');

        $this->assertNotContains('restaurante', $this->chaves());
    }

    public function test_com_o_modulo_e_a_permissao_as_mesas_aparecem(): void
    {
        $this->comModulo('restaurant');
        $this->comAsPermissoes('restaurant.orders.view');

        $this->assertContains('restaurante', $this->chaves());
    }

    // ── A permissão ───────────────────────────────────────────────────

    /**
     * Quem não vê clientes no sistema também não os vê no telemóvel. Era o
     * furo: o PWA só pedia `auth` e mostrava tudo a toda a gente.
     */
    public function test_sem_permissao_a_entrada_nao_aparece(): void
    {
        $this->comAsPermissoes('invoicing.pos.access');

        $chaves = $this->chaves();

        $this->assertContains('pos', $chaves);
        $this->assertNotContains('clientes', $chaves);
        $this->assertNotContains('catalogo', $chaves);
        $this->assertNotContains('documentos', $chaves);
    }

    /** O Início não depende de nada: sem ele não há como voltar atrás. */
    public function test_o_inicio_aparece_sempre(): void
    {
        $this->comAsPermissoes();

        $this->assertContains('inicio', $this->chaves());
    }

    // ── A escolha da empresa ──────────────────────────────────────────

    public function test_sem_nada_configurado_aparece_tudo_a_que_se_tem_direito(): void
    {
        $this->comAsPermissoes(
            'invoicing.pos.access',
            'invoicing.clients.view',
            'invoicing.products.view',
            'invoicing.sales.invoices.view'
        );

        $chaves = $this->chaves();

        // Uma definição nova não pode apagar o menu de quem já usava o PWA.
        $this->assertEqualsCanonicalizing(
            ['inicio', 'catalogo', 'pos', 'clientes', 'documentos'],
            $chaves
        );
    }

    public function test_a_empresa_pode_desligar_uma_entrada(): void
    {
        $this->comAsPermissoes('invoicing.pos.access', 'invoicing.clients.view');

        InvoicingSettings::forTenant($this->tenant->id)->update([
            'pwa_menu' => ['inicio', 'pos'],
        ]);

        $chaves = $this->chaves();

        $this->assertContains('pos', $chaves);
        $this->assertNotContains('clientes', $chaves, 'a empresa desligou-a');
    }

    /**
     * A escolha da empresa só TIRA. Ligar uma entrada não dá a permissão nem o
     * módulo que faltam — senão qualquer empresa se auto-atribuía o que não
     * contratou pelas definições.
     */
    public function test_ligar_nas_definicoes_nao_substitui_a_permissao(): void
    {
        $this->comAsPermissoes('invoicing.pos.access');

        InvoicingSettings::forTenant($this->tenant->id)->update([
            'pwa_menu' => ['inicio', 'pos', 'clientes', 'catalogo', 'documentos', 'restaurante'],
        ]);

        $chaves = $this->chaves();

        $this->assertNotContains('clientes', $chaves, 'falta a permissão');
        $this->assertNotContains('restaurante', $chaves, 'falta o módulo');
    }

    /** Uma lista guardada vazia deixaria o tablet sem menu nenhum. */
    public function test_lista_vazia_deixa_ficar_o_que_nao_se_desliga(): void
    {
        $this->comAsPermissoes('invoicing.pos.access');

        InvoicingSettings::forTenant($this->tenant->id)->update(['pwa_menu' => []]);

        $this->assertSame(['inicio'], $this->chaves());
    }

    /** Uma entrada removida do produto não fica a assombrar as definições. */
    public function test_chave_desconhecida_e_ignorada(): void
    {
        $this->comAsPermissoes('invoicing.pos.access');

        InvoicingSettings::forTenant($this->tenant->id)->update([
            'pwa_menu' => ['inicio', 'pos', 'entrada-que-ja-nao-existe'],
        ]);

        $this->assertEqualsCanonicalizing(['inicio', 'pos'], $this->chaves());
    }

    // ── A porta, e não só o botão ─────────────────────────────────────

    /**
     * O ENSAIO QUE IMPORTA. O menu esconde e a ROTA fecha — as duas com a
     * mesma regra. Sem isto, bastava escrever o endereço.
     */
    public function test_a_rota_fecha_o_que_o_menu_esconde(): void
    {
        $this->comAsPermissoes('invoicing.pos.access');

        $this->actingAs($this->user)->get('/invoicing/offline/pos')->assertOk();
        $this->actingAs($this->user)->get('/invoicing/offline/clients')->assertForbidden();
        $this->actingAs($this->user)->get('/invoicing/offline/catalog')->assertForbidden();
        $this->actingAs($this->user)->get('/invoicing/offline/drafts')->assertForbidden();
    }

    /**
     * O 403 tem de EXPLICAR-SE.
     *
     * Um ecrã em branco lê-se como avaria, e a chamada ao suporte é a mesma
     * que a de uma avaria a sério — com a diferença de que esta se resolve em
     * dez segundos, se alguém souber que permissão pedir. Por isso o nome
     * exacto da permissão aparece na página.
     *
     * E continua a ser 403: o service worker só guarda respostas OK, portanto
     * esta página nunca fica no aparelho no lugar do ecrã verdadeiro.
     */
    public function test_o_ecra_recusado_diz_o_que_falta(): void
    {
        $this->comAsPermissoes('invoicing.pos.access');

        $this->actingAs($this->user)
            ->get('/invoicing/offline/clients')
            ->assertStatus(403)
            ->assertSee('invoicing.clients.view')
            ->assertSee(__('Permissão em falta'));
    }

    public function test_a_rota_fecha_o_que_a_empresa_desligou(): void
    {
        $this->comAsPermissoes('invoicing.pos.access', 'invoicing.clients.view');

        InvoicingSettings::forTenant($this->tenant->id)->update(['pwa_menu' => ['inicio', 'pos']]);

        $this->actingAs($this->user)->get('/invoicing/offline/clients')->assertForbidden();
        $this->actingAs($this->user)->get('/invoicing/offline/clients/new')->assertForbidden();
    }

    public function test_a_rota_do_restaurante_fecha_sem_o_modulo(): void
    {
        $this->comAsPermissoes('restaurant.orders.view');

        $this->actingAs($this->user)->get('/invoicing/offline/restaurant')->assertForbidden();
    }

    /** O Início nunca fecha, aconteça o que acontecer às definições. */
    public function test_o_inicio_abre_sempre(): void
    {
        $this->comAsPermissoes();

        InvoicingSettings::forTenant($this->tenant->id)->update(['pwa_menu' => []]);

        $this->actingAs($this->user)->get('/invoicing/offline')->assertOk();
    }

    // ── O ecrã de definições ──────────────────────────────────────────

    /**
     * Só se oferece o que a empresa pode mesmo ter. Oferecer o restaurante a
     * quem não o contratou é prometer o que não há.
     */
    public function test_as_definicoes_so_oferecem_o_que_a_empresa_tem(): void
    {
        $configuraveis = array_keys(MenuDoPwa::configuraveis($this->tenant->fresh()));

        $this->assertNotContains('inicio', $configuraveis, 'o que não se desliga não se oferece');
        $this->assertNotContains('restaurante', $configuraveis, 'sem o módulo não há nada a oferecer');
        $this->assertContains('pos', $configuraveis);
    }

    public function test_com_o_modulo_o_restaurante_passa_a_ser_configuravel(): void
    {
        $this->comModulo('restaurant');

        $this->assertContains('restaurante', array_keys(MenuDoPwa::configuraveis($this->tenant->fresh())));
    }

    /**
     * O ecrã de definições guarda mesmo a escolha — e o Início entra sempre,
     * escolha o utilizador o que escolher. Um PWA sem Início não tem saída.
     *
     * O ecrã passou a React; quem grava é a API das definições, pelo mesmo
     * serviço (`DefinicoesDaFacturacao`) que o Livewire chamava.
     */
    public function test_o_ecra_de_definicoes_guarda_a_escolha(): void
    {
        $this->comAsPermissoes('invoicing.settings.view', 'invoicing.settings.edit');

        $this->putJson(self::DEFINICOES, $this->ficha(['pwa_menu' => ['pos']]))->assertOk();

        $guardado = InvoicingSettings::forTenant($this->tenant->id)->fresh()->pwa_menu;

        $this->assertContains('pos', $guardado);
        $this->assertContains('inicio', $guardado, 'o que não se desliga entra sempre');
        $this->assertNotContains('clientes', $guardado);
    }

    /** Uma chave inventada pelo navegador não entra na coluna. */
    public function test_o_ecra_recusa_uma_entrada_que_nao_existe(): void
    {
        $this->comAsPermissoes('invoicing.settings.view', 'invoicing.settings.edit');

        $this->putJson(self::DEFINICOES, $this->ficha(['pwa_menu' => ['pos', 'entrada-inventada']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('pwa_menu.1');
    }

    /** A ficha inteira das definições, com o que o teste quer mudar por cima. */
    private function ficha(array $por = []): array
    {
        return array_merge($this->getJson(self::DEFINICOES)->assertOk()->json('definicoes'), $por);
    }
}
