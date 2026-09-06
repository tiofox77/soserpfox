<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use Tests\TenantTestCase;

/**
 * O SAFT-AO, para o ecrã em React.
 *
 * O que estes ensaios guardam: ver as contagens e gerar o ficheiro são
 * permissões diferentes; o período tem de fazer sentido; e a descarga é um
 * SAFT a sério que deixa rasto na auditoria.
 */
class ApiDoSaftParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/saft';

    private const DESCARGA = '/invoicing/saft-generator/novo-ecra/descarregar';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function periodo(array $por = []): array
    {
        return array_merge([
            'startDate' => now()->startOfMonth()->toDateString(),
            'endDate' => now()->endOfMonth()->toDateString(),
            'documentType' => 'all',
        ], $por);
    }

    /** @test */
    public function ver_e_gerar_sao_permissoes_diferentes(): void
    {
        $this->getJson(self::RAIZ . '/opcoes')->assertForbidden();

        $this->comPermissoes('invoicing.saft.view');

        $this->getJson(self::RAIZ . '/opcoes')->assertOk()
            ->assertJsonPath('permissoes.pode_gerar', false)
            ->assertJsonPath('descarga', url(self::DESCARGA));

        $this->getJson(self::RAIZ . '/estatisticas?' . http_build_query($this->periodo()))->assertOk()
            ->assertJsonStructure(['data' => ['totalInvoices', 'totalValue', 'totalCreditNotes', 'totalDebitNotes', 'totalReceipts', 'totalMovements', 'totalCustomers', 'totalSuppliers', 'totalProducts']]);

        $this->get(self::DESCARGA . '?' . http_build_query($this->periodo()))->assertForbidden();
    }

    /** @test */
    public function o_periodo_tem_de_fazer_sentido(): void
    {
        $this->comPermissoes('invoicing.saft.view');

        $this->getJson(self::RAIZ . '/estatisticas?' . http_build_query($this->periodo(['startDate' => '2026-02-01', 'endDate' => '2026-01-01'])))
            ->assertStatus(422)
            ->assertJsonValidationErrors('endDate');

        $this->getJson(self::RAIZ . '/estatisticas?' . http_build_query($this->periodo(['documentType' => 'tudo'])))
            ->assertStatus(422)
            ->assertJsonValidationErrors('documentType');
    }

    /** @test */
    public function a_descarga_e_um_saft_a_serio_e_deixa_rasto(): void
    {
        $this->comPermissoes('invoicing.saft.view', 'invoicing.saft.generate');

        $r = $this->get(self::DESCARGA . '?' . http_build_query($this->periodo() + ['includeStockMovements' => 0]));

        $r->assertOk();
        $this->assertStringContainsString('attachment', (string) $r->headers->get('content-disposition'));
        $this->assertStringContainsString('SAFT_AO_' . $this->tenant->id, (string) $r->headers->get('content-disposition'));

        $xml = $r->streamedContent();
        $this->assertStringContainsString('<AuditFile', $xml);
        $this->assertStringContainsString('urn:OECD:StandardAuditFile-Tax:AO_1.01_01', $xml);
        $this->assertStringContainsString('<CompanyName>', $xml);
        $this->assertStringContainsString('<StartDate>' . now()->startOfMonth()->toDateString() . '</StartDate>', $xml);

        $this->assertTrue(AuditTrail::forTenant()->where('event', 'exportacao')->exists(), 'o SAFT deixa rasto');
    }
}
