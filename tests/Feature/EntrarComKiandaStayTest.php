<?php

namespace Tests\Feature;

use App\Models\Hotel\LigacaoKiandaStay;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

/**
 * «Entrar com o KiandaStay» — ligar sem copiar chave nenhuma.
 *
 * O hoteleiro carrega no botão, entra no site com a conta dele, escolhe a casa
 * e volta ligado. O que fica guardado é um token daquela casa, e não a
 * `api_key` do site — que é uma só e abre os 172 hotéis.
 *
 * O `state` é o que amarra a volta à ida: sem ele, bastaria mandar a alguém um
 * endereço com o código de outra autorização para lhe ligar uma casa alheia.
 */
class EntrarComKiandaStayTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // LIGAR A CASA A UM SITE DE RESERVAS é decisão de quem gere o hotel: a
        // volta do «Entrar com o KiandaStay» passou a pedir a permissão de
        // alterar as definições, como o ecrã de onde se parte.
        $this->comModulo('hotel')->comPermissoes('hotel.settings.edit');
    }

    private function ligacao(): LigacaoKiandaStay
    {
        return LigacaoKiandaStay::paraTenant($this->tenant->id);
    }

    /** @test */
    public function o_botao_manda_autorizar_no_site_com_um_state_proprio(): void
    {
        $destino = $this->postJson('/api/v1/invoicing/react/hotel/kiandastay/autorizar', [
            'base_url' => 'https://kiandastay.exemplo',
        ])->assertOk()->json('url');

        $this->assertStringContainsString('/ligar/soserp', $destino);
        $this->assertStringContainsString(urlencode(route('hotel.kiandastay.retorno')), $destino);

        $estado = session('kiandastay_state');

        $this->assertNotEmpty($estado, 'sem state não há como amarrar a volta à ida');
        $this->assertStringContainsString('state=' . $estado, $destino);

        // E o endereço fica guardado, para a volta saber com quem falar.
        $this->assertSame('https://kiandastay.exemplo', $this->ligacao()->fresh()->base_url);
    }

    /**
     * A VOLTA TROCA O BILHETE PELO TOKEN E FICA LIGADO.
     *
     * @test
     */
    public function a_volta_deixa_a_ligacao_feita(): void
    {
        $this->ligacao()->forceFill(['base_url' => 'https://kiandastay.exemplo'])->save();

        Http::fake([
            '*/api/v1/ligacoes/token' => Http::response([
                'token'          => 'kshc_token_de_ensaio',
                'hotel'          => ['id' => 51, 'name' => 'Mussulo Bay', 'slug' => 'mussulo'],
                'webhook_secret' => 'segredo-do-site',
            ], 200),
        ]);

        session(['kiandastay_state' => 'abc123']);

        $this->get(route('hotel.kiandastay.retorno', ['code' => 'bilhete', 'state' => 'abc123']))
            ->assertRedirect(route('hotel.kiandastay'));

        $l = $this->ligacao()->fresh();

        $this->assertSame('kshc_token_de_ensaio', $l->api_key, 'o token da casa fica guardado');
        $this->assertSame('segredo-do-site', $l->webhook_secret);
        $this->assertSame(51, (int) $l->property_id);
        $this->assertSame('Mussulo Bay', $l->property_name);
        $this->assertTrue($l->activa, 'e a ligação fica logo a receber');

        // O endereço onde queremos receber viaja na mesma volta.
        Http::assertSent(fn ($p) => $p['webhook_url'] === $l->urlDoWebhook());
    }

    /**
     * UM CÓDIGO QUE NÃO É DESTA SESSÃO NÃO LIGA NADA.
     *
     * @test
     */
    public function sem_o_state_certo_nao_se_liga(): void
    {
        $this->ligacao()->forceFill(['base_url' => 'https://kiandastay.exemplo'])->save();

        Http::fake();

        session(['kiandastay_state' => 'o-certo']);

        $this->get(route('hotel.kiandastay.retorno', ['code' => 'bilhete', 'state' => 'outro']))
            ->assertRedirect(route('hotel.kiandastay'));

        $this->assertNull($this->ligacao()->fresh()->api_key, 'não se guarda token nenhum');

        Http::assertNothingSent();
    }

    /** Sem código não se tenta nada. @test */
    public function sem_codigo_nao_se_tenta_nada(): void
    {
        Http::fake();

        session(['kiandastay_state' => 'abc']);

        $this->get(route('hotel.kiandastay.retorno', ['state' => 'abc']))
            ->assertRedirect(route('hotel.kiandastay'));

        Http::assertNothingSent();
    }

    /**
     * O ECRÃ NÃO CONVIDA O BROWSER A PREENCHER O ENDEREÇO COM UM EMAIL.
     *
     * Aconteceu num cliente: o gestor de palavras-passe viu «endereço» ao lado
     * de uma «palavra-passe» e encheu os dois com o email e a senha guardados.
     *
     * @test
     */
    public function os_campos_estao_fora_do_alcance_da_autofill(): void
    {
        $ecra = file_get_contents(resource_path('js/ecras/hotel/KiandaStay.tsx'));

        $this->assertStringContainsString('autoComplete="off"', $ecra,
            'o endereço do site não se preenche sozinho');
        $this->assertStringContainsString('autoComplete="new-password"', $ecra,
            'e a chave da API muito menos');
        $this->assertStringNotContainsString('name="base_url"', $ecra,
            'um campo com o nome óbvio é o que a autofill procura');
    }
}
