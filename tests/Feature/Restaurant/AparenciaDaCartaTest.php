<?php

namespace Tests\Feature\Restaurant;

use App\Livewire\Restaurant\AparenciaDaCarta;
use App\Livewire\Restaurant\MenuOnline;
use App\Models\Product;
use App\Models\Restaurant\MenuDestaque;
use App\Models\Restaurant\RestaurantSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * A aparência da carta pública: capa, cores, tema e destaques.
 *
 * O que estes ensaios prendem: o que se escolhe aqui chega mesmo à carta que o
 * cliente abre, e um destaque que deixou de se vender não fica lá pendurado.
 */
class AparenciaDaCartaTest extends TenantTestCase
{
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

    /** @test */
    public function guardar_a_aparencia_chega_a_carta_publica(): void
    {
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');
        $d = $this->cartaPublicada();

        Livewire::actingAs($this->user)->test(AparenciaDaCarta::class)
            ->set('titulo', 'Cantinho do Sabor')
            ->set('descricao', 'Cozinha angolana à lenha')
            ->set('cor', '#7c2d12')
            ->set('corAcento', '#0f766e')
            ->set('tema', 'escuro')
            ->set('tituloDestaques', 'O que o chefe recomenda')
            ->call('guardar')
            ->assertHasNoErrors();

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

        Livewire::actingAs($this->user)->test(AparenciaDaCarta::class)
            ->set('cor', 'laranja')
            ->call('guardar')
            ->assertHasErrors('cor');
    }

    /** @test */
    public function os_destaques_aparecem_primeiro_na_carta(): void
    {
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');
        $d = $this->cartaPublicada();
        $prato = $this->prato('Calulu de peixe');

        Livewire::actingAs($this->user)->test(AparenciaDaCarta::class)
            ->set('tituloDestaques', 'Sugestões da casa')
            ->call('guardar')
            ->call('destacar', $prato->id);

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
     *
     * @test
     */
    public function destaque_escondido_ou_a_zero_sai_da_carta(): void
    {
        $d = $this->cartaPublicada();

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

        $comp = Livewire::actingAs($this->user)->test(AparenciaDaCarta::class);

        for ($i = 0; $i < MenuDestaque::MAXIMO; $i++) {
            $comp->call('destacar', $this->prato('Prato '.$i)->id);
        }

        $aMais = $this->prato('Um a mais');
        $comp->call('destacar', $aMais->id);

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

        $comp = Livewire::actingAs($this->user)->test(AparenciaDaCarta::class)
            ->call('destacar', $primeiro->id)
            ->call('destacar', $segundo->id);

        $idDoSegundo = MenuDestaque::where('product_id', $segundo->id)->value('id');
        $comp->call('mover', $idDoSegundo, 'cima');

        $ordem = MenuDestaque::where('tenant_id', $this->tenant->id)
            ->orderBy('ordem')->pluck('product_id')->all();

        $this->assertSame([$segundo->id, $primeiro->id], $ordem);
    }

    /** Sem permissão de editar, nada se guarda nem se destaca. */
    public function test_sem_autoridade_nao_se_muda_a_carta(): void
    {
        $this->comPermissoes('restaurant.settings.view');
        $d = $this->cartaPublicada();
        $prato = $this->prato();

        Livewire::actingAs($this->user)->test(AparenciaDaCarta::class)
            ->set('titulo', 'Não devia gravar')
            ->call('guardar')
            ->call('destacar', $prato->id);

        $this->assertNotSame('Não devia gravar', $d->fresh()->menu_title);
        $this->assertSame(0, MenuDestaque::where('tenant_id', $this->tenant->id)->count());
    }

    /** A capa sobe, fica guardada, e a carta passa a mostrá-la. */
    public function test_a_capa_sobe_e_aparece(): void
    {
        Storage::fake('public');
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');
        $d = $this->cartaPublicada();

        Livewire::actingAs($this->user)->test(AparenciaDaCarta::class)
            ->set('capaNova', UploadedFile::fake()->image('sala.jpg', 1200, 600))
            ->call('guardar')
            ->assertHasNoErrors();

        $capa = $d->fresh()->menu_cover;

        $this->assertNotNull($capa);
        Storage::disk('public')->assertExists($capa);

        $this->get('/menu/'.$d->fresh()->menu_slug)
            ->assertOk()
            ->assertSee(basename($capa), false);
    }

    /**
     * A carta desligada continua a recusar-se a servir — a aparência nova não
     * pode ter aberto uma porta que estava fechada.
     *
     * @test
     */
    public function carta_desligada_continua_fechada(): void
    {
        $d = $this->cartaPublicada();

        RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)
            ->update(['online_menu_enabled' => false]);

        $this->get('/menu/'.$d->menu_slug)->assertNotFound();
    }
}
