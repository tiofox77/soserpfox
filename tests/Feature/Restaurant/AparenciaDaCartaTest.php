<?php

namespace Tests\Feature\Restaurant;

use App\Models\Product;
use App\Models\Restaurant\MenuDestaque;
use App\Models\Restaurant\RestaurantSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * A aparência da carta pública: capa, cores, tema e destaques.
 *
 * O que estes ensaios prendem: o que se escolhe aqui chega mesmo à carta que o
 * cliente abre, e um destaque que deixou de se vender não fica lá pendurado.
 */
class AparenciaDaCartaTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/restaurant/aparencia';

    protected function setUp(): void
    {
        parent::setUp();
        $this->comModulo('restaurant');
    }

    private function cartaPublicada(): RestaurantSettings
    {
        $d = RestaurantSettings::forTenant($this->tenant->id);

        RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update([
            'menu_slug' => 'casa-'.uniqid(),
            'online_menu_enabled' => true,
            'menu_orders_enabled' => true,
            'menu_show_prices' => true,
        ]);

        return $d->fresh();
    }

    private function prato(string $nome = 'Muamba de galinha', float $preco = 4500): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => $nome,
            'code' => 'P'.strtoupper(substr(uniqid(), -8)),
            'type' => 'produto',
            'price' => $preco,
            'unit' => 'UN',
            'tax_type' => 'iva',
            'tax_rate_id' => $this->imposto->id,
            'is_active' => true,
        ]);
    }

    /** A forma completa que o ecrã grava. */
    private function forma(array $extra = []): array
    {
        return array_merge([
            'menu_title' => 'Cantinho do Sabor',
            'menu_description' => 'Cozinha angolana à lenha',
            'menu_primary_color' => '#7c2d12',
            'menu_accent_color' => '#0f766e',
            'menu_theme' => 'escuro',
            'menu_destaques_titulo' => 'O que o chefe recomenda',
            'menu_show_prices' => true,
        ], $extra);
    }

    public function test_guardar_a_aparencia_chega_a_carta_publica(): void
    {
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');
        $d = $this->cartaPublicada();

        $this->putJson(self::RAIZ, $this->forma())->assertOk();

        $d = $d->fresh();

        $this->assertSame('Cantinho do Sabor', $d->menu_title);
        $this->assertSame('#7c2d12', $d->menu_primary_color);
        $this->assertSame('#0f766e', $d->menu_accent_color);
        $this->assertSame('escuro', $d->menu_theme);

        // E aparece mesmo na carta que o cliente abre.
        $this->get('/menu/'.$d->menu_slug)
            ->assertOk()
            ->assertSee('Cantinho do Sabor')
            ->assertSee('Cozinha angolana à lenha')
            ->assertSee('#0f766e', false);
    }

    /** Uma cor que não é uma cor é recusada. */
    public function test_cor_invalida_e_recusada(): void
    {
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');
        $this->cartaPublicada();

        $this->putJson(self::RAIZ, $this->forma(['menu_primary_color' => 'laranja']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('menu_primary_color');
    }

    public function test_os_destaques_aparecem_primeiro_na_carta(): void
    {
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');
        $d = $this->cartaPublicada();
        $prato = $this->prato('Calulu de peixe');

        $this->putJson(self::RAIZ, $this->forma(['menu_destaques_titulo' => 'Sugestões da casa']))->assertOk();
        $this->postJson(self::RAIZ . '/destaques', ['product_id' => $prato->id])->assertOk();

        $this->assertDatabaseHas('restaurant_menu_destaques', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $prato->id,
        ]);

        $this->get('/menu/'.$d->fresh()->menu_slug)
            ->assertOk()
            ->assertSee('Sugestões da casa')
            ->assertSee('Calulu de peixe');
    }

    /**
     * Um destaque que deixou de se vender NÃO fica pendurado na carta.
     *
     * Vale a mesma regra dos outros pratos: escondido ou a zero, sai. Um
     * destaque que já não se vende é pior do que destaque nenhum.
     */
    public function test_destaque_escondido_ou_a_zero_sai_da_carta(): void
    {
        $this->cartaPublicada();

        $escondido = $this->prato('Prato retirado');
        $semPreco = $this->prato('Prato sem preço');
        $bom = $this->prato('Funge com feijão');

        foreach ([$escondido, $semPreco, $bom] as $i => $p) {
            MenuDestaque::create([
                'tenant_id' => $this->tenant->id, 'product_id' => $p->id, 'ordem' => $i,
            ]);
        }

        $escondido->update(['is_active' => false]);
        $semPreco->update(['price' => 0]);

        $paraCarta = MenuDestaque::paraCarta($this->tenant->id);

        $this->assertCount(1, $paraCarta);
        $this->assertSame($bom->id, $paraCarta->first()->product_id);
    }

    /** Uma fila de destaques longa deixa de destacar seja o que for. */
    public function test_ha_um_tecto_de_destaques(): void
    {
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');
        $this->cartaPublicada();

        for ($i = 0; $i < MenuDestaque::MAXIMO; $i++) {
            $this->postJson(self::RAIZ . '/destaques', ['product_id' => $this->prato('Prato '.$i)->id])
                ->assertOk();
        }

        $aMais = $this->prato('Um a mais');

        $this->postJson(self::RAIZ . '/destaques', ['product_id' => $aMais->id])->assertStatus(422);

        $this->assertSame(MenuDestaque::MAXIMO, MenuDestaque::where('tenant_id', $this->tenant->id)->count());
        $this->assertDatabaseMissing('restaurant_menu_destaques', ['product_id' => $aMais->id]);
    }

    /** A ordem escolhida é a ordem que a carta mostra. */
    public function test_mover_um_destaque_muda_a_ordem(): void
    {
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');
        $this->cartaPublicada();

        $primeiro = $this->prato('Primeiro');
        $segundo = $this->prato('Segundo');

        $this->postJson(self::RAIZ . '/destaques', ['product_id' => $primeiro->id])->assertOk();
        $this->postJson(self::RAIZ . '/destaques', ['product_id' => $segundo->id])->assertOk();

        $idDoSegundo = MenuDestaque::where('product_id', $segundo->id)->value('id');

        $this->postJson(self::RAIZ . "/destaques/{$idDoSegundo}/mover", ['sentido' => 'cima'])->assertOk();

        $ordem = MenuDestaque::where('tenant_id', $this->tenant->id)
            ->orderBy('ordem')->pluck('product_id')->all();

        $this->assertSame([$segundo->id, $primeiro->id], $ordem);
    }

    /** O artigo de outra empresa não se destaca aqui. */
    public function test_o_prato_de_outra_empresa_nao_se_destaca(): void
    {
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');
        $this->cartaPublicada();

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $alheio = Product::create([
            'tenant_id' => $outra->id, 'name' => 'Alheio', 'type' => 'produto',
            'price' => 1000, 'unit' => 'UN', 'is_active' => true,
        ]);

        $this->postJson(self::RAIZ . '/destaques', ['product_id' => $alheio->id])->assertStatus(422);

        $this->assertDatabaseMissing('restaurant_menu_destaques', ['product_id' => $alheio->id]);
    }

    /** Sem permissão de editar, nada se guarda nem se destaca. */
    public function test_sem_autoridade_nao_se_muda_a_carta(): void
    {
        $this->comPermissoes('restaurant.settings.view');
        $d = $this->cartaPublicada();
        $prato = $this->prato();

        $this->putJson(self::RAIZ, $this->forma(['menu_title' => 'Não devia gravar']))->assertForbidden();
        $this->postJson(self::RAIZ . '/destaques', ['product_id' => $prato->id])->assertForbidden();

        $this->assertNotSame('Não devia gravar', $d->fresh()->menu_title);
        $this->assertSame(0, MenuDestaque::where('tenant_id', $this->tenant->id)->count());
    }

    /** A capa sobe, fica guardada, e a carta passa a mostrá-la. */
    public function test_a_capa_sobe_e_aparece(): void
    {
        Storage::fake('public');
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');
        $d = $this->cartaPublicada();

        $this->post(self::RAIZ . '/imagem', [
            'qual' => 'capa',
            'ficheiro' => UploadedFile::fake()->image('sala.jpg', 1200, 600),
        ])->assertOk();

        $capa = $d->fresh()->menu_cover;

        $this->assertNotNull($capa);
        Storage::disk('public')->assertExists($capa);

        $this->get('/menu/'.$d->fresh()->menu_slug)
            ->assertOk()
            ->assertSee(basename($capa), false);
    }

    /** A capa trocada apaga a anterior: dez trocas não deixam dez ficheiros. */
    public function test_a_capa_trocada_apaga_a_anterior(): void
    {
        Storage::fake('public');
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');
        $d = $this->cartaPublicada();

        $this->post(self::RAIZ . '/imagem', [
            'qual' => 'capa', 'ficheiro' => UploadedFile::fake()->image('antiga.jpg', 800, 400),
        ])->assertOk();

        $antiga = $d->fresh()->menu_cover;

        $this->post(self::RAIZ . '/imagem', [
            'qual' => 'capa', 'ficheiro' => UploadedFile::fake()->image('nova.jpg', 800, 400),
        ])->assertOk();

        $this->assertNotSame($antiga, $d->fresh()->menu_cover);
        Storage::disk('public')->assertMissing($antiga);
    }

    /**
     * A carta desligada continua a recusar-se a servir — a aparência nova não
     * pode ter aberto uma porta que estava fechada.
     */
    public function test_carta_desligada_continua_fechada(): void
    {
        $d = $this->cartaPublicada();

        RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)
            ->update(['online_menu_enabled' => false]);

        $this->get('/menu/'.$d->menu_slug)->assertNotFound();
    }

    /** Sem carta publicada não há pré-visualização — nem endereço para ela. */
    public function test_sem_carta_publicada_nao_ha_previsualizacao(): void
    {
        $this->comPermissoes('restaurant.settings.view');

        RestaurantSettings::forTenant($this->tenant->id);

        $this->getJson(self::RAIZ)->assertOk()->assertJsonPath('url_da_carta', null);
    }
}
