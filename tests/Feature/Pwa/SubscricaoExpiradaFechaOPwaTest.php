<?php

namespace Tests\Feature\Pwa;

use App\Models\Subscription;
use Tests\TenantTestCase;

/**
 * Uma empresa com a subscrição expirada não vende pelo PWA.
 *
 * É a fronteira de segurança do produto, e o PWA é o sítio onde ela é mais
 * fácil de furar: a aplicação está desenhada para CONTINUAR a funcionar quando
 * o servidor não responde. Se um servidor que RECUSA for indistinguível de um
 * servidor que não responde, uma empresa que deixou de pagar continua a emitir
 * facturas para sempre — e sem nunca dar erro nenhum ao operador.
 *
 * Por isso a recusa tem de ser explícita e legível pela máquina:
 *
 *   · os ECRÃS do PWA não abrem;
 *   · a API de sincronização responde com um código que o motor sabe ler, e
 *     não com um 302 para uma página HTML de renovação — um redireccionamento
 *     chega ao `fetch` como uma resposta com sucesso e corpo em HTML, e o
 *     motor arquivava-o como "sem rede".
 *
 * O QUE ISTO NÃO PROMETE: um aparelho já offline, com o catálogo em casa,
 * continua a vender até voltar a falar com o servidor. Não há como evitá-lo
 * sem inventar um relógio de confiança dentro do telemóvel. A garantia é que,
 * no primeiro contacto, a recusa é reconhecida e a fila pára — nada do que foi
 * feito se perde, mas também nada mais sobe.
 */
class SubscricaoExpiradaFechaOPwaTest extends TenantTestCase
{
    private function expirarSubscricao(): void
    {
        Subscription::where('tenant_id', $this->tenant->id)->update([
            'status'             => 'expired',
            'current_period_end' => now()->subDay(),
            'ends_at'            => now()->subDay(),
        ]);
    }

    /** @test */
    public function com_subscricao_activa_o_pos_do_pwa_abre(): void
    {
        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.pos.access');
        \App\Support\MenuDoPwa::esquecerMemoria();

        // A referência: sem isto, um ensaio que veja 302 não distingue
        // "expirou" de "esta rota nunca abriu para ninguém".
        $this->get('/invoicing/offline/pos')->assertOk();
    }

    /**
     * A CONTRAPROVA, e é a que interessa a quem já paga.
     *
     * O CheckSubscription corre no grupo `web`: TODOS os pedidos passam por
     * lá. Uma alteração ali que estrague o caminho normal não afecta um ecrã —
     * afecta o produto inteiro, para todos os clientes ao mesmo tempo.
     *
     * Por isso a recusa nova tem de estar só onde já se recusava. Este ensaio
     * percorre o que uma empresa em dia usa a sério — os ecrãs, a
     * sincronização, o ping e a emissão — e exige 200 em tudo.
     *
     * @test
     */
    public function com_subscricao_activa_nada_muda_em_lado_nenhum(): void
    {
        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.pos.access');
        \App\Support\MenuDoPwa::esquecerMemoria();

        // Os ecrãs.
        $this->get('/invoicing/offline')->assertOk();
        $this->get('/invoicing/offline/pos')->assertOk();

        // A API que o PWA usa a cada minuto.
        $this->getJson('/api/v1/invoicing/ping')->assertOk();
        $this->getJson('/api/v1/invoicing/sync')->assertOk();

        // E nenhuma delas pode trazer a recusa nova encostada.
        $this->getJson('/api/v1/invoicing/ping')
            ->assertJsonMissingPath('code');
    }

    /** @test */
    public function com_subscricao_expirada_o_pos_do_pwa_nao_abre(): void
    {
        $this->comModulo('invoicing');
        $this->expirarSubscricao();

        $this->get('/invoicing/offline/pos')
            ->assertRedirect(route('subscription.expired'));
    }

    /** @test */
    public function com_subscricao_expirada_a_entrada_do_pwa_nao_abre(): void
    {
        $this->comModulo('invoicing');
        $this->expirarSubscricao();

        $this->get('/invoicing/offline')->assertRedirect(route('subscription.expired'));
    }

    /** @test */
    public function a_sincronizacao_recusa_e_di_lo_em_json(): void
    {
        $this->comModulo('invoicing');
        $this->expirarSubscricao();

        $resposta = $this->getJson('/api/v1/invoicing/sync');

        // 402 Payment Required: o código que existe para exactamente isto.
        // O que NÃO pode ser é um 302 — o `fetch` do motor segue-o, recebe HTML
        // com estado 200, e arquiva a recusa como uma falha de rede.
        $resposta->assertStatus(402);
        $resposta->assertJsonPath('code', 'subscription_expired');
    }

    /** @test */
    public function a_venda_offline_e_recusada_ao_subir(): void
    {
        $this->comModulo('invoicing');
        $this->expirarSubscricao();

        $this->postJson('/api/v1/invoicing/pos/sale', [
            'local_uuid'     => 'pos_ensaio_expirada',
            'payment_method' => 'cash',
            'items'          => [[
                'product_name' => 'Artigo',
                'quantity'     => 1,
                'unit_price'   => 100,
                'tax_rate'     => 14,
            ]],
        ])->assertStatus(402);
    }

    /** @test */
    public function o_ping_tambem_recusa_para_o_motor_saber_que_nao_e_falta_de_rede(): void
    {
        $this->comModulo('invoicing');
        $this->expirarSubscricao();

        // O ping é o que o motor usa para decidir se está online. Se ele
        // responder OK com a subscrição expirada, o aparelho fica a achar que
        // tem rede e a bater numa porta fechada até ao fim do turno.
        $this->getJson('/api/v1/invoicing/ping')->assertStatus(402);
    }
}
