<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * Encontrar a factura a partir do talao provisorio do POS offline.
 *
 * Uma venda feita sem internet sai com um talao a dizer PEND-20260817-A3F9C1,
 * e e esse papel que o cliente leva. Ao sincronizar, a venda recebe o numero
 * fiscal a serio e o provisorio deixa de aparecer em lado nenhum: quem
 * voltasse com o talao a pedir a factura nao era encontrado.
 *
 * Nao foi preciso guardar nada de novo — o provisorio TERMINA nos ultimos seis
 * caracteres do local_uuid, que ja fica gravado.
 *
 * A lista das facturas passou a React: quem procura e a API
 * `/api/v1/invoicing/react/sales-invoices`. A regra e a mesma e e la que ela
 * agora vive — foi preciso reimplementa-la, porque a API tinha ficado sem ela.
 */
class RastrearTalaoOfflineTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/sales-invoices';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes('invoicing.sales.invoices.view');
    }

    private function facturaVindaDoOffline(string $uuid, string $numero): void
    {
        // Inserida directamente: o que se testa e a PESQUISA, e montar uma
        // venda inteira pelo servico traria serie, stock e impostos para o meio
        // sem acrescentar nada ao que esta a ser provado.
        DB::table('invoicing_sales_invoices')->insert([
            'tenant_id'      => $this->tenant->id,
            'local_uuid'     => $uuid,
            'invoice_number' => $numero,
            'client_id'      => $this->cliente->id,
            'invoice_date'   => now()->toDateString(),
            'created_by'     => $this->user->id,
            'total'          => 200,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    /** Os numeros que a procura devolve. */
    private function procurar(string $termo): array
    {
        return collect(
            $this->getJson(self::RAIZ . '?' . http_build_query(['procura' => $termo]))
                ->assertOk()
                ->json('data')
        )->pluck('numero')->all();
    }

    public function test_o_numero_do_talao_encontra_a_factura(): void
    {
        $this->facturaVindaDoOffline('9c1e7b40-2f88-4a11-b0d3-77aa3f9c1', 'FR A/002064');

        $this->assertContains('FR A/002064', $this->procurar('PEND-20260817-A3F9C1'));
    }

    /** So a cauda tambem chega: quem le ao telefone dita o que consegue. */
    public function test_so_os_seis_ultimos_tambem_encontram(): void
    {
        $this->facturaVindaDoOffline('9c1e7b40-2f88-4a11-b0d3-77aa3f9c1', 'FR A/002064');

        $this->assertContains('FR A/002064', $this->procurar('a3f9c1'));
    }

    /**
     * E uma pesquisa normal NAO pode passar a bater contra os identificadores:
     * era trazer facturas que nao tem nada que ver com o que se procurou.
     */
    public function test_uma_pesquisa_normal_nao_bate_nos_identificadores(): void
    {
        $this->facturaVindaDoOffline('9c1e7b40-2f88-4a11-b0d3-77aa3f9c1', 'FR A/002064');

        $this->assertNotContains('FR A/002064', $this->procurar('texto que nao existe em lado nenhum'));
    }
}
