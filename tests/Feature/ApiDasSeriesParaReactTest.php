<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSeries;
use Tests\TenantTestCase;

/**
 * A API DAS SÉRIES, para o ecrã em React.
 *
 * O que ela promete e estes ensaios guardam: o prefixo é o da AGT e não o
 * que se manda; uma série registada na AGT quase não se mexe e não se
 * elimina; a lista filtra; e tudo exige a permissão das séries.
 */
class ApiDasSeriesParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/series';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function corpo(array $por = []): array
    {
        return array_merge([
            'document_type' => 'invoice',
            'series_code' => 'R' . strtoupper(substr(md5(uniqid('', true)), 0, 5)),
            'name' => 'Série de ensaio',
            'prefix' => 'FT',
            'include_year' => true,
            'next_number' => 1,
            'number_padding' => 6,
            'is_default' => false,
            'is_active' => true,
            'reset_yearly' => true,
            'description' => null,
            'series_year' => (int) now()->year,
            'establishment_number' => 'SEDE',
            'invoicing_method' => 'FEPC',
        ], $por);
    }

    /** @test */
    public function tudo_exige_a_permissao_das_series(): void
    {
        $this->getJson(self::RAIZ)->assertForbidden();
        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();

        $this->comPermissoes('invoicing.series.view', 'invoicing.series.edit');

        $opcoes = $this->getJson(self::RAIZ . '/opcoes')->assertOk();
        $this->assertSame('FT', collect($opcoes->json('tipos'))->firstWhere('valor', 'invoice')['prefixo']);
        $this->assertContains('FEPC', array_column($opcoes->json('metodos'), 'valor'));

        $this->getJson(self::RAIZ)->assertOk()->assertJsonStructure(['data', 'meta' => ['total']]);
    }

    /** O prefixo é fiscal: o que se manda não conta. @test */
    public function o_prefixo_e_o_da_agt_e_nao_o_que_se_manda(): void
    {
        $this->comPermissoes('invoicing.series.view', 'invoicing.series.edit');

        $r = $this->postJson(self::RAIZ, $this->corpo(['prefix' => 'ZZ']))->assertCreated();

        $this->assertSame('FT', $r->json('data.prefix'));
        $this->assertStringStartsWith('FT ', $r->json('data.exemplo'));
        $this->assertFalse($r->json('data.registada'));
        $this->assertSame(0, $r->json('data.emitidos'));
    }

    /** @test */
    public function uma_serie_registada_na_agt_quase_nao_se_mexe_e_nao_se_elimina(): void
    {
        $this->comPermissoes('invoicing.series.view', 'invoicing.series.edit');

        $id = $this->postJson(self::RAIZ, $this->corpo())->assertCreated()->json('data.id');
        InvoicingSeries::whereKey($id)->update(['agt_series_id' => 'AGT-ENSAIO-1']);

        $this->deleteJson(self::RAIZ . '/' . $id)->assertStatus(422);
        $this->assertNotNull(InvoicingSeries::find($id), 'registada na AGT, fica');

        $r = $this->putJson(self::RAIZ . '/' . $id, $this->corpo(['name' => 'Outro nome', 'next_number' => 500]))->assertOk();

        $this->assertSame('Outro nome', $r->json('data.name'));
        $this->assertSame(1, $r->json('data.next_number'), 'o número não se mexe numa série registada');
        $this->assertTrue($r->json('data.registada'));
    }

    /** @test */
    public function uma_serie_livre_edita_se_e_elimina_se(): void
    {
        $this->comPermissoes('invoicing.series.view', 'invoicing.series.edit');

        $id = $this->postJson(self::RAIZ, $this->corpo())->assertCreated()->json('data.id');

        $r = $this->putJson(self::RAIZ . '/' . $id, $this->corpo(['next_number' => 42, 'number_padding' => 4]))->assertOk();
        $this->assertSame(42, $r->json('data.next_number'));
        $this->assertStringEndsWith('/0042', $r->json('data.exemplo'));

        $this->deleteJson(self::RAIZ . '/' . $id)->assertOk();
        $this->assertNull(InvoicingSeries::find($id));
    }

    /** @test */
    public function a_lista_filtra_por_tipo_e_por_procura(): void
    {
        $this->comPermissoes('invoicing.series.view', 'invoicing.series.edit');

        $this->postJson(self::RAIZ, $this->corpo(['name' => 'Balcão Norte']))->assertCreated();
        $this->postJson(self::RAIZ, $this->corpo(['document_type' => 'proforma', 'prefix' => 'PP', 'name' => 'Proformas do ensaio']))->assertCreated();

        $porTipo = $this->getJson(self::RAIZ . '?tipo=proforma')->assertOk()->json('data');
        $this->assertNotEmpty($porTipo);
        $this->assertSame(['proforma'], array_values(array_unique(array_column($porTipo, 'document_type'))));

        $porProcura = $this->getJson(self::RAIZ . '?procura=Norte')->assertOk()->json('data');
        $this->assertCount(1, $porProcura);
        $this->assertSame('Balcão Norte', $porProcura[0]['name']);
    }
}
