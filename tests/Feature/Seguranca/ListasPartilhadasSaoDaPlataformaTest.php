<?php

namespace Tests\Feature\Seguranca;

use Tests\TenantTestCase;

/**
 * OS BANCOS, AS MOEDAS E OS CÂMBIOS SÃO DE TODAS AS EMPRESAS.
 *
 * Não têm `tenant_id`; com uma permissão da sua empresa, qualquer administrador
 * renomeava ou apagava um banco, uma moeda ou um câmbio para toda a gente
 * (auditoria de segurança de 2026-09-13).
 */
class ListasPartilhadasSaoDaPlataformaTest extends TenantTestCase
{
    public function test_o_administrador_de_uma_empresa_nao_escreve_nas_listas_partilhadas(): void
    {
        $this->comModulo('contabilidade');
        $this->comPermissoes('treasury.banks.view', 'treasury.banks.delete', 'accounting.currencies.view', 'accounting.currencies.manage');

        $this->postJson('/api/v1/invoicing/react/catalogos/bancos', ['name' => 'Banco Falso', 'code' => 'BF' . uniqid(), 'is_active' => true])->assertForbidden();
        $this->postJson('/api/v1/invoicing/react/contabilidade/moedas', ['code' => 'XYZ', 'name' => 'Falsa', 'symbol' => 'X', 'decimal_places' => 2])->assertForbidden();
    }
}
