<?php

namespace Tests\Feature\Seguranca;

use App\Models\Client;
use App\Models\Invoicing\Tax;
use Tests\TenantTestCase;

/**
 * A API DE LISTAGENS DA APP MÓVEL — `/api/v1/invoicing/list|detail|dashboard-stats`.
 *
 * Só autenticava: um caixa lia todas as facturas e turnos, mudava a taxa do IVA
 * (de onde o TaxResolver tira o imposto das vendas seguintes) e o `detail` de um
 * cliente devolvia o hash da senha do portal (auditoria de 2026-09-13).
 */
class ApiDeListagensExigePermissaoTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing';

    public function test_sem_permissao_nao_le_nem_muda_nada(): void
    {
        $taxa = Tax::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'IVA ensaio'], ['rate' => 14, 'code' => 'IVAE', 'is_active' => true]);

        $this->getJson(self::RAIZ . '/list/sales-invoices')->assertForbidden();
        $this->getJson(self::RAIZ . '/list/shifts')->assertForbidden();
        $this->getJson(self::RAIZ . '/dashboard-stats')->assertForbidden();
        $this->putJson(self::RAIZ . '/list/taxes/' . $taxa->id, ['name' => 'IVA', 'rate' => 0])->assertForbidden();
        $this->deleteJson(self::RAIZ . '/list/taxes/' . $taxa->id)->assertForbidden();
        $this->postJson(self::RAIZ . '/list/clients', ['name' => 'Intruso'])->assertForbidden();

        $this->assertSame(14.0, (float) $taxa->fresh()->rate);
    }

    public function test_com_a_permissao_da_area_le_e_o_detalhe_nao_leva_credenciais(): void
    {
        $this->comPermissoes('invoicing.clients.view');

        $cliente = $this->clienteEmpresa();
        \Illuminate\Support\Facades\DB::table('invoicing_clients')->where('id', $cliente->id)
            ->update(['password' => bcrypt('segredo-do-portal'), 'remember_token' => 'token-secreto']);

        $this->getJson(self::RAIZ . '/list/clients')->assertOk();

        $r = $this->getJson(self::RAIZ . '/detail/clients/' . $cliente->id)->assertOk();
        $this->assertArrayNotHasKey('password', $r->json('record'));
        $this->assertArrayNotHasKey('remember_token', $r->json('record'));

        // Ver não é editar.
        $this->putJson(self::RAIZ . '/list/clients/' . $cliente->id, ['name' => 'Outro'])->assertForbidden();
    }
}
