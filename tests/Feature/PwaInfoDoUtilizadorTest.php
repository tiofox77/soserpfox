<?php

namespace Tests\Feature;

use App\Models\Invoicing\PosShift;
use Tests\TenantTestCase;

/**
 * O que o PWA precisa de saber sobre quem o esta a usar.
 *
 * O ecra inicial do modo offline le tudo isto do cache local, por isso o que
 * nao vier na sincronizacao nunca chega ao aparelho — e deixa de se poder ver
 * justamente quando faz falta, que e sem internet.
 */
class PwaInfoDoUtilizadorTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A API do PWA pede permissão desde 2026-09-13 (AutorizaApiDoPwa): o
        // utilizador do ensaio é um caixa a sério, não um membro sem papel.
        $this->comPermissoesDoPwa();
    }

    private function sincronizar(): array
    {
        return $this->actingAs($this->user)
            ->getJson('/api/v1/invoicing/sync')
            ->assertOk()
            ->json();
    }

    public function test_a_sincronizacao_diz_quem_esta_autenticado(): void
    {
        $json = $this->sincronizar();

        $this->assertSame($this->user->id, $json['user']['id'] ?? null);
        $this->assertSame($this->user->name, $json['user']['name'] ?? null);
        $this->assertSame($this->user->email, $json['user']['email'] ?? null,
            'sem o email nao se distinguem dois operadores com o mesmo nome proprio');
    }

    public function test_a_sincronizacao_diz_a_empresa_e_o_armazem(): void
    {
        $json = $this->sincronizar();

        $this->assertNotEmpty($json['company']['name'] ?? null,
            'quem trabalha em varias empresas tem de saber em qual esta antes de vender');
        $this->assertArrayHasKey('warehouse', $json);
    }

    /** Sem turno aberto, o aparelho tem de saber que nao ha — e nao ficar sem resposta. */
    public function test_sem_turno_aberto_diz_que_nao_ha(): void
    {
        PosShift::where('tenant_id', $this->tenant->id)->update(['status' => 'closed']);

        $json = $this->sincronizar();

        $this->assertArrayHasKey('shift', $json);
        $this->assertFalse($json['shift']['open']);
    }

    /** Com turno aberto, vem o numero, a hora e os valores da caixa. */
    public function test_com_turno_aberto_vem_o_numero_a_hora_e_os_valores(): void
    {
        PosShift::create([
            'tenant_id'       => $this->tenant->id,
            'user_id'         => $this->user->id,
            'shift_number'    => 'T-TESTE-1',
            'status'          => 'open',
            'opened_at'       => now()->subHours(2),
            'opening_balance' => 5000,
        ]);

        $turno = $this->sincronizar()['shift'];

        $this->assertTrue($turno['open']);
        $this->assertSame('T-TESTE-1', $turno['number']);
        $this->assertNotEmpty($turno['opened_at'], 'sem a hora nao se sabe desde quando esta aberto');
        // assertEquals e nao assertSame: o JSON devolve 5000 sem casas decimais,
        // e o que interessa aqui e o valor, nao se veio inteiro ou decimal.
        $this->assertEquals(5000, $turno['opening_balance']);
        $this->assertArrayHasKey('cash_sales', $turno);
        $this->assertArrayHasKey('total_sales', $turno);
    }

    /** A hora do turno vai com fuso, para o telemovel a mostrar na hora certa. */
    public function test_a_hora_do_turno_leva_fuso(): void
    {
        PosShift::create([
            'tenant_id'    => $this->tenant->id,
            'user_id'      => $this->user->id,
            'shift_number' => 'T-TESTE-2',
            'status'       => 'open',
            'opened_at'    => now(),
        ]);

        $abertura = $this->sincronizar()['shift']['opened_at'];

        $this->assertMatchesRegularExpression('/[+-]\d{2}:\d{2}$|Z$/', $abertura,
            'sem fuso o telemovel le a hora como se fosse dele e mostra outra');
    }
}
