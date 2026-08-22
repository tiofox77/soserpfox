<?php

namespace App\Services\Restaurant;

use App\Models\Restaurant\Venue;
use App\Models\Restaurant\VenueLimitRequest;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestaurantVenueLimitService
{
    public function createVenue(int $tenantId, array $data): Venue
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenantId);
            $limit = max(1, (int) $tenant->restaurant_venue_limit);
            $used = Venue::withoutGlobalScopes()->where('tenant_id', $tenantId)->count();

            if ($used >= $limit) {
                throw ValidationException::withMessages([
                    'venueName' => "O limite de {$limit} estabelecimento(s) foi atingido. Solicite autorização ao administrador.",
                ]);
            }

            return Venue::withoutGlobalScopes()->create($data + ['tenant_id' => $tenantId]);
        }, 3);
    }

    public function requestIncrease(int $tenantId, int $userId, int $requestedLimit, ?string $reason): VenueLimitRequest
    {
        return DB::transaction(function () use ($tenantId, $userId, $requestedLimit, $reason) {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenantId);
            $current = max(1, (int) $tenant->restaurant_venue_limit);
            if ($requestedLimit <= $current) {
                throw ValidationException::withMessages(['requestedVenueLimit' => 'O novo limite deve ser superior ao limite atual.']);
            }
            if (VenueLimitRequest::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['requestedVenueLimit' => 'Já existe um pedido pendente para esta empresa.']);
            }

            return VenueLimitRequest::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId, 'requested_by' => $userId,
                'current_limit' => $current, 'requested_limit' => $requestedLimit,
                'status' => 'pending', 'reason' => trim((string) $reason) ?: null,
            ]);
        }, 3);
    }

    public function review(int $requestId, int $adminId, bool $approve, ?int $approvedLimit, ?string $notes): VenueLimitRequest
    {
        return DB::transaction(function () use ($requestId, $adminId, $approve, $approvedLimit, $notes) {
            $request = VenueLimitRequest::withoutGlobalScopes()->lockForUpdate()->findOrFail($requestId);
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['review' => 'Este pedido já foi analisado.']);
            }

            if ($approve) {
                $tenant = Tenant::withTrashed()->lockForUpdate()->findOrFail($request->tenant_id);
                $limit = max((int) $tenant->restaurant_venue_limit, (int) ($approvedLimit ?: $request->requested_limit));
                if ($limit <= (int) $tenant->restaurant_venue_limit) {
                    throw ValidationException::withMessages(['approvedLimit' => 'O limite aprovado deve aumentar a quota atual.']);
                }
                $tenant->update(['restaurant_venue_limit' => $limit]);
            }

            $request->update([
                'status' => $approve ? 'approved' : 'rejected', 'admin_notes' => trim((string) $notes) ?: null,
                'reviewed_by' => $adminId, 'reviewed_at' => now(),
            ]);
            return $request->fresh();
        }, 3);
    }
}
