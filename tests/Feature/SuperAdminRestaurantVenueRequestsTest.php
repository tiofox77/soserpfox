<?php

namespace Tests\Feature;

use App\Models\Restaurant\VenueLimitRequest;
use Tests\TenantTestCase;

/**
 * O dono da plataforma abre os pedidos de estabelecimentos. O ecrã passou a
 * React: a página monta o ecrã e a API entrega os pedidos de todas as empresas.
 */
class SuperAdminRestaurantVenueRequestsTest extends TenantTestCase
{
    public function test_super_admin_abre_lista_de_pedidos(): void
    {
        $this->user->forceFill(['is_super_admin' => true])->save();
        VenueLimitRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'requested_by' => $this->user->id,
            'current_limit' => 1, 'requested_limit' => 2, 'status' => 'pending',
        ]);

        $this->get(route('superadmin.restaurant-venue-requests'))
            ->assertOk()
            ->assertSee('data-ecra="plataforma/estabelecimentos"', false);

        $this->getJson('/api/v1/plataforma/react/pedidos-de-estabelecimentos')
            ->assertOk()
            ->assertJsonFragment(['empresa' => $this->tenant->company_name ?: $this->tenant->name]);
    }
}
