<?php

namespace Tests\Feature\Seguranca;

use Tests\TenantTestCase;

/**
 * VER NÃO É EDITAR — séries fiscais e guias de transporte.
 *
 * Criar uma série, mudar-lhe o próximo número (números repetidos) ou apagá-la,
 * e emitir, comunicar à AGT ou anular uma guia pediam só a permissão de VER
 * (auditoria de segurança de 2026-09-13).
 */
class VerNaoEEditarTest extends TenantTestCase
{
    public function test_quem_so_ve_series_nao_as_cria_nem_apaga(): void
    {
        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.series.view');

        $this->getJson('/api/v1/invoicing/react/series')->assertOk();
        $this->postJson('/api/v1/invoicing/react/series', ['document_type' => 'FT', 'name' => 'X'])->assertForbidden();
        $this->deleteJson('/api/v1/invoicing/react/series/1')->assertForbidden();
    }

    public function test_quem_so_ve_guias_nao_emite_nem_anula(): void
    {
        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.transport-guides.view');

        $this->postJson('/api/v1/invoicing/react/guias', [])->assertForbidden();
        $this->postJson('/api/v1/invoicing/react/guias/1/agt')->assertForbidden();
        $this->deleteJson('/api/v1/invoicing/react/guias/1', ['motivo' => 'x'])->assertForbidden();
    }
}
