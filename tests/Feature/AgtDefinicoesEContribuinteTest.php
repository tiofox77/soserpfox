<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSeries;
use App\Services\AGT\GestaoAgt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AgtDeEnsaio;
use Tests\TenantTestCase;

/**
 * AS DEFINIÇÕES E A FICHA DO CONTRIBUINTE — o que se grava sem querer.
 *
 *  · Um POST que só trouxesse o CAE desligava o envio automático (o `?? false`
 *    do guardar). Os dois interruptores passam a obrigatórios nas definições;
 *    na ficha só se mexem quando vêm.
 *  · Um CAE inventado gravava-se e só aparecia na primeira submissão recusada.
 *  · O NIF mudava-se pela ficha AGT com a permissão da AGT, por baixo da
 *    guarda do ecrã da empresa; sem validar que é NIF de empresa; e mesmo
 *    com séries registadas e documentos comunicados com o número antigo.
 */
class AgtDefinicoesEContribuinteTest extends TenantTestCase
{
    use AgtDeEnsaio;

    private const RAIZ = '/api/v1/invoicing/react/agt';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('local');
        Http::fake(['*' => Http::response(['requestID' => 'x', 'resultCode' => '0'], 200)]);

        $this->comModulo('invoicing')->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
    }

    public function test_um_post_parcial_nao_desliga_o_envio_automatico(): void
    {
        $this->definicoesAgt()->update(['agt_auto_submit' => true, 'agt_require_validation' => true]);

        $this->postJson(self::RAIZ . '/definicoes', ['agt_eac_code' => '47730'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['agt_auto_submit', 'agt_require_validation']);

        $this->assertTrue((bool) $this->definicoesAgt()->fresh()->agt_auto_submit, 'continua ligado');

        // Na ficha do contribuinte os interruptores são opcionais — e ausentes não mexem.
        $this->postJson(self::RAIZ . '/contribuinte', ['agt_establishment_number' => 'SEDE'])->assertOk();
        $this->assertTrue((bool) $this->definicoesAgt()->fresh()->agt_auto_submit);
    }

    public function test_o_cae_tem_de_existir_no_catalogo(): void
    {
        DB::table('agt_cae_codes')->insert([
            ['code' => '47730', 'level' => 'subclass', 'description' => 'Comércio a retalho de produtos farmacêuticos', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        Cache::forget(GestaoAgt::CHAVE_DO_CAE);

        $base = ['agt_auto_submit' => true, 'agt_require_validation' => true];

        $this->postJson(self::RAIZ . '/definicoes', $base + ['agt_eac_code' => '99999'])
            ->assertStatus(422)->assertJsonValidationErrors('agt_eac_code');
        $this->postJson(self::RAIZ . '/contribuinte', ['agt_establishment_number' => 'SEDE', 'agt_eac_code' => '99999'])
            ->assertStatus(422)->assertJsonValidationErrors('agt_eac_code');

        $this->postJson(self::RAIZ . '/definicoes', $base + ['agt_eac_code' => '47730'])->assertOk();
        $this->assertSame('47730', $this->definicoesAgt()->fresh()->agt_eac_code);

        // Vazio continua a ser «sem CAE».
        $this->postJson(self::RAIZ . '/definicoes', $base + ['agt_eac_code' => ''])->assertOk();
        $this->assertNull($this->definicoesAgt()->fresh()->agt_eac_code);
    }

    public function test_sem_catalogo_carregado_exige_a_forma_de_uma_classe(): void
    {
        DB::table('agt_cae_codes')->delete();
        Cache::forget(GestaoAgt::CHAVE_DO_CAE);

        $base = ['agt_auto_submit' => true, 'agt_require_validation' => true];

        $this->postJson(self::RAIZ . '/definicoes', $base + ['agt_eac_code' => '47A'])->assertStatus(422)->assertJsonValidationErrors('agt_eac_code');
        $this->postJson(self::RAIZ . '/definicoes', $base + ['agt_eac_code' => '47730'])->assertOk();
    }

    public function test_mudar_o_nif_exige_a_permissao_dos_dados_da_empresa(): void
    {
        $antigo = $this->tenant->nif;

        $this->postJson(self::RAIZ . '/contribuinte', ['agt_establishment_number' => 'SEDE', 'tax_registration_number' => '5417000001'])
            ->assertForbidden();
        $this->assertSame($antigo, $this->tenant->fresh()->nif);

        // Mandar o MESMO NIF (a ficha manda-o sempre) não pede nada.
        $this->postJson(self::RAIZ . '/contribuinte', ['agt_establishment_number' => 'SEDE', 'tax_registration_number' => $antigo])->assertOk();

        $this->comPermissoes('settings.edit');
        $this->postJson(self::RAIZ . '/contribuinte', ['agt_establishment_number' => 'SEDE', 'tax_registration_number' => '5417000001'])->assertOk();
        $this->assertSame('5417000001', $this->tenant->fresh()->nif);
    }

    public function test_o_nif_tem_de_ser_de_empresa(): void
    {
        $this->comPermissoes('settings.edit');
        $antigo = $this->tenant->nif;

        // O número do bilhete de identidade.
        $this->postJson(self::RAIZ . '/contribuinte', ['agt_establishment_number' => 'SEDE', 'tax_registration_number' => '004512345LA041'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tax_registration_number');

        $this->assertSame($antigo, $this->tenant->fresh()->nif);
    }

    public function test_com_series_registadas_ou_documentos_comunicados_o_nif_nao_muda_aqui(): void
    {
        $this->comPermissoes('settings.edit');
        $antigo = $this->tenant->nif;
        $pedido = ['agt_establishment_number' => 'LOJA 9', 'tax_registration_number' => '5417000002'];

        InvoicingSeries::where('tenant_id', $this->tenant->id)->where('document_type', 'invoice')->limit(1)
            ->update(['agt_series_id' => 'FT-REGISTADA', 'agt_environment' => 'production']);

        $r = $this->postJson(self::RAIZ . '/contribuinte', $pedido)->assertStatus(422);
        $this->assertStringContainsString('suporte', $r->json('message'));
        $this->assertSame($antigo, $this->tenant->fresh()->nif);
        $this->assertNotSame('LOJA 9', $this->definicoesAgt()->fresh()->agt_establishment_number, 'a recusa não deixa o resto gravado a meio');

        InvoicingSeries::where('tenant_id', $this->tenant->id)->update(['agt_series_id' => null]);
        $this->submissaoAgt();

        $this->postJson(self::RAIZ . '/contribuinte', $pedido)->assertStatus(422);
        $this->assertSame($antigo, $this->tenant->fresh()->nif);
    }
}
