<?php

namespace Tests\Feature\Seguranca;

use Tests\TenantTestCase;

/**
 * A API DE UM MÓDULO SÓ SERVE A EMPRESA QUE O TEM.
 *
 * As páginas passavam pelo `tenant.module`; a API React não: acabado o teste do
 * hotel ou do RH, a empresa continuava a usá-los pela API (auditoria de
 * segurança de 2026-09-13).
 */
class ApiDeModuloExigeOModuloTest extends TenantTestCase
{
    public function test_sem_o_modulo_a_api_dele_recusa_mesmo_com_permissao(): void
    {
        $this->comPermissoes('hotel.dashboard.view', 'hotel.reservations.view');

        $this->getJson('/api/v1/invoicing/react/hotel/reservas')->assertForbidden();

        $this->comModulo('hotel');

        $this->getJson('/api/v1/invoicing/react/hotel/reservas')->assertOk();
    }
}
