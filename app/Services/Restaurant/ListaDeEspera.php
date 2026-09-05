<?php

namespace App\Services\Restaurant;

use App\Models\Restaurant\Order;
use App\Models\Restaurant\Waitlist;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A fila de quem chega sem reserva.
 *
 * Três verbos e mais nenhum: chegar, sentar, desistir. O sentar é o único com
 * miolo — abre a comanda na mesa escolhida pelo MESMO caminho de sempre
 * (`RestaurantOrderService::open`), para a fila nunca ser uma porta ao lado
 * das regras: o turno obrigatório e a mesa ocupada valem aqui como valem no
 * POS.
 */
class ListaDeEspera
{
    public function chegar(array $dados, int $tenantId, ?int $userId): Waitlist
    {
        $nome = trim((string) ($dados['guest_name'] ?? ''));

        if ($nome === '') {
            throw new InvalidArgumentException('A fila precisa do nome de quem espera.');
        }

        return Waitlist::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'venue_id' => (int) $dados['venue_id'],
            'guest_name' => $nome,
            'phone' => trim((string) ($dados['phone'] ?? '')) ?: null,
            'guest_count' => max(1, (int) ($dados['guest_count'] ?? 2)),
            'notes' => trim((string) ($dados['notes'] ?? '')) ?: null,
            'status' => 'waiting',
            'arrived_at' => now(),
            'created_by' => $userId,
        ]);
    }

    /**
     * Sentar: sai da fila e a comanda abre na mesa, num passo só.
     *
     * Se a comanda não puder abrir (turno fechado, mesa ocupada entretanto),
     * a entrada FICA na fila — sentar meio cliente era perder o registo dele
     * sem lhe dar mesa nenhuma.
     */
    public function sentar(Waitlist $entrada, int $mesaId, int $tenantId, ?int $userId): Order
    {
        if ($entrada->tenant_id !== $tenantId || $entrada->status !== 'waiting') {
            throw new InvalidArgumentException('Esta entrada já saiu da fila.');
        }

        return DB::transaction(function () use ($entrada, $mesaId, $tenantId, $userId) {
            $order = app(RestaurantOrderService::class)->open([
                'venue_id' => $entrada->venue_id,
                'table_id' => $mesaId,
                'guest_count' => $entrada->guest_count,
                'notes' => 'Da lista de espera: '.$entrada->guest_name,
            ], $tenantId, $userId);

            $entrada->update([
                'status' => 'seated',
                'table_id' => $mesaId,
                'order_id' => $order->id,
                'resolved_at' => now(),
            ]);

            return $order;
        });
    }

    /** Desistiu — e fica registado, porque é o número que mede as mesas em falta. */
    public function desistir(Waitlist $entrada, int $tenantId): Waitlist
    {
        if ($entrada->tenant_id !== $tenantId || $entrada->status !== 'waiting') {
            throw new InvalidArgumentException('Esta entrada já saiu da fila.');
        }

        $entrada->update(['status' => 'left', 'resolved_at' => now()]);

        return $entrada;
    }

    /** A fila desta casa, por ordem de chegada. */
    public function fila(int $tenantId, int $venueId)
    {
        return Waitlist::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('venue_id', $venueId)
            ->where('status', 'waiting')
            ->orderBy('arrived_at')
            ->get();
    }
}
