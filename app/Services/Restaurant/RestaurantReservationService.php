<?php

namespace App\Services\Restaurant;

use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Reservation;
use App\Models\Restaurant\Venue;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RestaurantReservationService
{
    public function save(array $data, int $tenantId, ?int $userId, ?Reservation $reservation = null): Reservation
    {
        return DB::transaction(function () use ($data, $tenantId, $userId, $reservation) {
            $venue = Venue::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($data['venue_id']);
            if (!$venue) throw new InvalidArgumentException('Estabelecimento inválido.');
            $table = null;
            if (!empty($data['table_id'])) {
                $table = DiningTable::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('venue_id', $venue->id)->find($data['table_id']);
                if (!$table || $table->capacity < (int) $data['guest_count']) throw new InvalidArgumentException('Mesa inválida ou com capacidade insuficiente.');
                $start = \Carbon\Carbon::parse($data['reserved_at']);
                $end = $start->copy()->addMinutes((int) $data['duration_minutes']);
                $conflict = Reservation::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('table_id', $table->id)
                    ->whereIn('status', ['pending', 'confirmed', 'seated'])->when($reservation, fn ($q) => $q->whereKeyNot($reservation->id))
                    ->where('reserved_at', '<', $end)->whereRaw('DATE_ADD(reserved_at, INTERVAL duration_minutes MINUTE) > ?', [$start])->exists();
                if ($conflict) throw new InvalidArgumentException('A mesa já possui uma reserva neste horário.');
            }
            if ($reservation && $reservation->tenant_id !== $tenantId) throw new InvalidArgumentException('Reserva inválida.');
            $payload = $data + ['tenant_id' => $tenantId, 'created_by' => $userId];
            if (!$reservation) {
                $next = Reservation::withoutGlobalScopes()->where('tenant_id', $tenantId)->lockForUpdate()->count() + 1;
                $payload['reservation_number'] = sprintf('RES-%s-%05d', now()->format('Y'), $next);
                $payload['status'] = 'pending';
                return Reservation::withoutGlobalScopes()->create($payload);
            }
            $reservation->update($payload);
            return $reservation->fresh(['table', 'venue']);
        });
    }

    public function changeStatus(Reservation $reservation, string $status, int $tenantId): void
    {
        $allowed = ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['seated', 'cancelled', 'no_show'], 'seated' => ['completed']];
        if ($reservation->tenant_id !== $tenantId || !in_array($status, $allowed[$reservation->status] ?? [], true)) throw new InvalidArgumentException('Transição de reserva inválida.');
        DB::transaction(function () use ($reservation, $status, $tenantId) {
            $reservation->update(['status' => $status]);
            if ($reservation->table_id) {
                $table = DiningTable::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($reservation->table_id);
                if ($status === 'confirmed' && $table?->status === 'available') $table->update(['status' => 'reserved']);
                if (in_array($status, ['cancelled', 'no_show', 'completed'], true) && $table?->status === 'reserved') $table->update(['status' => 'available']);
            }
        });
    }
}
