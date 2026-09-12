<?php

namespace Tests\Feature\Restaurant;

use App\Models\Restaurant\RestaurantSettings;
use Tests\TenantTestCase;

/**
 * A impressão automática dos talões da cozinha.
 *
 * O talão já existia como página; o que faltava era ninguém ter de abrir a
 * página e carregar em imprimir a meio do serviço. A mecânica do iframe é do
 * browser e prova-se à mão — o que os ensaios prendem é o CONTRATO à volta: a
 * definição existe, chega ao ecrã, e o ecrã tem tudo o que o script precisa.
 *
 * A DEFINIÇÃO DA CASA É O VALOR DE ARRANQUE e não a decisão final: a escolha
 * verdadeira é POR APARELHO (`localStorage`), porque a impressora está num
 * posto só. Ligada em todos os ecrãs, cada um imprimia a sua cópia do mesmo
 * talão.
 */
class ImpressaoAutomaticaDaCozinhaTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant');
        $this->comPermissoes(
            'restaurant.kitchen.view', 'restaurant.kitchen.manage',
            'restaurant.settings.view', 'restaurant.settings.edit',
        );
    }

    /** Desligada por omissão: ligada em todo o lado, cada ecrã imprimia a sua cópia. */
    public function test_nasce_desligada(): void
    {
        $this->assertFalse((bool) RestaurantSettings::forTenant($this->tenant->id)->kitchen_auto_print);
    }

    public function test_a_definicao_grava_se_e_chega_ao_ecra_da_cozinha(): void
    {
        RestaurantSettings::forTenant($this->tenant->id);

        $this->putJson('/api/v1/invoicing/react/restaurant/definicoes', [
            'kitchen_auto_print' => true,
            'use_kitchen_workflow' => true,
            'reserve_stock_on_confirm' => true,
            'consume_stock_on_kitchen' => true,
            'service_charge_percent' => 0,
            'tips_enabled' => true,
        ])->assertOk();

        $this->assertTrue((bool) RestaurantSettings::forTenant($this->tenant->id)->fresh()->kitchen_auto_print);

        // E o ecrã da cozinha recebe-a como valor de arranque.
        $this->getJson('/api/v1/invoicing/react/restaurant/cozinha/opcoes')
            ->assertOk()
            ->assertJsonPath('impressao_automatica', true);
    }

    /**
     * As peças de que o script vive: o interruptor por aparelho, o endereço do
     * talão e o da segunda via. Sem qualquer uma, a impressão automática falha
     * em silêncio — nada rebenta, só não imprime.
     */
    public function test_o_ecra_tem_as_pecas_de_que_o_script_precisa(): void
    {
        $ecra = file_get_contents(resource_path('js/ecras/restaurant/Cozinha.tsx'));

        // A escolha é do aparelho e guarda-se nele.
        $this->assertStringContainsString('kds-imprimir-sozinho', $ecra);
        $this->assertStringContainsString('localStorage', $ecra);

        // O talão e a segunda via saem da rota de sempre.
        $this->assertStringContainsString('/restaurant/kitchen/tickets/', $ecra);
        $this->assertStringContainsString('copy=1', $ecra);

        // E imprime-se num iframe escondido, sem sair da cozinha.
        $this->assertStringContainsString('imprimirEmSilencio', $ecra);
    }

    /**
     * O PULSO CONTINUA A SER UM JSON.
     *
     * É a pergunta barata que o ecrã faz de três em três segundos. Apontá-la a
     * uma página em vez de a uma agregação era trocar 30 bytes por uma página
     * inteira, vinte vezes por minuto, em cada ecrã de cozinha aberto.
     */
    public function test_o_pulso_continua_a_responder_em_json(): void
    {
        $this->getJson('/restaurant/kitchen/pulso')
            ->assertOk()
            ->assertJsonStructure(['pulso', 'bilhetes']);
    }
}
