<?php

namespace Tests\Feature\Restaurant;

use App\Livewire\Restaurant\KitchenDisplay;
use App\Livewire\Restaurant\SettingsManagement;
use App\Models\Restaurant\RestaurantSettings;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * A impressão automática dos talões da cozinha.
 *
 * O talão já existia como página; o que faltava era ninguém ter de abrir a
 * página e carregar em imprimir a meio do serviço. A mecânica do iframe é do
 * browser e prova-se à mão — o que os ensaios prendem é o CONTRATO à volta:
 * a definição existe, chega ao ecrã, e o ecrã tem tudo o que o script precisa.
 */
class ImpressaoAutomaticaDaCozinhaTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant');
        $this->comPermissoes('restaurant.kitchen.view', 'restaurant.settings.view');
    }

    /** Desligada por omissão: ligada em todo o lado, cada ecrã imprimia a sua cópia. */
    public function test_nasce_desligada(): void
    {
        $this->assertFalse((bool) RestaurantSettings::forTenant($this->tenant->id)->kitchen_auto_print);
    }

    /** @test */
    public function a_definicao_grava_se_e_chega_ao_ecra_da_cozinha(): void
    {
        Livewire::actingAs($this->user)->test(SettingsManagement::class)
            ->set('kitchenAutoPrint', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue((bool) RestaurantSettings::forTenant($this->tenant->id)->fresh()->kitchen_auto_print);

        // E o KDS recebe-a como valor de arranque.
        Livewire::actingAs($this->user)->test(KitchenDisplay::class)
            ->assertViewHas('autoPrint', true);
    }

    /**
     * O ecrã tem as três peças de que o script vive: o marcador do bilhete, o
     * endereço de impressão e o interruptor. Sem qualquer uma, a impressão
     * automática falha em silêncio — nada rebenta, só não imprime.
     *
     * @test
     */
    public function o_ecra_tem_as_pecas_de_que_o_script_precisa(): void
    {
        $html = Livewire::actingAs($this->user)->test(KitchenDisplay::class)->html();

        $this->assertStringContainsString('pulsoDaCozinha(', $html);
        $this->assertStringContainsString('imprimirSozinho', $html);

        // Sem bilhetes não há data-talao para conferir — mas o template que os
        // gera tem de o ter escrito.
        $vista = file_get_contents(resource_path('views/livewire/restaurant/kitchen-display.blade.php'));

        $this->assertStringContainsString('data-talao=', $vista);
        $this->assertStringContainsString('data-talao-url=', $vista);
        $this->assertStringContainsString("route('restaurant.kitchen.print'", $vista);
    }
}
