<?php

namespace Tests\Feature;

use App\Models\Restaurant\VenueLimitRequest;
use Tests\TenantTestCase;

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
            ->assertSee('Pedidos de estabelecimentos')
            ->assertSee($this->tenant->name);
    }
}
