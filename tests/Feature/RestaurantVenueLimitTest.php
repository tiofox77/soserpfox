<?php

namespace Tests\Feature;

use App\Models\Restaurant\Venue;
use App\Models\Restaurant\VenueLimitRequest;
use App\Services\Restaurant\RestaurantVenueLimitService;
use Illuminate\Validation\ValidationException;
use Tests\TenantTestCase;

class RestaurantVenueLimitTest extends TenantTestCase
{
    public function test_default_quota_allows_one_venue_and_blocks_the_second(): void
    {
        $service = app(RestaurantVenueLimitService::class);
        $service->createVenue($this->tenant->id, ['code' => 'PRINCIPAL', 'name' => 'Principal', 'is_active' => true]);

        $this->expectException(ValidationException::class);
        $service->createVenue($this->tenant->id, ['code' => 'FILIAL', 'name' => 'Filial', 'is_active' => true]);
    }

    public function test_admin_approval_increases_quota_and_allows_second_venue(): void
    {
        $service = app(RestaurantVenueLimitService::class);
        $service->createVenue($this->tenant->id, ['code' => 'PRINCIPAL', 'name' => 'Principal', 'is_active' => true]);
        $request = $service->requestIncrease($this->tenant->id, $this->user->id, 2, 'Nova filial');
        $service->review($request->id, $this->user->id, true, 2, 'Autorizado para teste');
        $service->createVenue($this->tenant->id, ['code' => 'FILIAL', 'name' => 'Filial', 'is_active' => true]);

        $this->assertSame(2, Venue::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
        $this->assertSame(2, $this->tenant->fresh()->restaurant_venue_limit);
        $this->assertSame('approved', VenueLimitRequest::withoutGlobalScopes()->find($request->id)->status);
    }

    public function test_tenant_cannot_create_two_pending_requests(): void
    {
        $service = app(RestaurantVenueLimitService::class);
        $service->requestIncrease($this->tenant->id, $this->user->id, 2, null);

        $this->expectException(ValidationException::class);
        $service->requestIncrease($this->tenant->id, $this->user->id, 3, null);
    }
}
