<?php

namespace App\Services\Workshop;

use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderHistory;
use Illuminate\Support\Facades\DB;

/**
 * O RETRABALHO EM GARANTIA (15/09/2026, OF-19).
 *
 * Abre-se a partir de uma ordem concluída ou entregue: a nova ordem é da mesma
 * viatura e do mesmo mecânico, com o motivo e a causa, e não se factura. Fora
 * do prazo da garantia só com confirmação — a oficina pode assumir na mesma.
 */
class GarantiasDaOficina
{
    public const CAUSAS = [
        'mao_de_obra' => 'Mão-de-obra',
        'peca' => 'Peça com defeito',
        'diagnostico' => 'Diagnóstico errado',
        'outro' => 'Outra causa',
    ];

    public static function abrir(WorkOrder $original, string $causa, string $motivo, bool $foraDoPrazo, int $tenantId): WorkOrder
    {
        if ($original->warranty_of_id) {
            throw new \InvalidArgumentException(__('Esta ordem já é um retrabalho em garantia: abra-o a partir da ordem original.'));
        }
        if (! in_array($original->status, ['completed', 'delivered'], true)) {
            throw new \InvalidArgumentException(__('A garantia abre-se a partir de uma ordem concluída ou entregue.'));
        }
        if (self::expirou($original) && ! $foraDoPrazo) {
            throw new \InvalidArgumentException(__('A garantia desta ordem acabou a :data. Confirme para abrir na mesma.', ['data' => $original->warranty_expires->format('d/m/Y')]));
        }

        return DB::transaction(function () use ($original, $causa, $motivo, $tenantId) {
            $nova = WorkOrder::createWithTenantNumber([
                'tenant_id' => $tenantId,
                'vehicle_id' => $original->vehicle_id,
                'mechanic_id' => $original->mechanic_id,
                'received_at' => now(),
                'mileage_in' => (int) ($original->vehicle?->mileage ?: $original->mileage_in),
                'problem_description' => mb_substr(__('Retrabalho em garantia da :ordem: :motivo', ['ordem' => $original->order_number, 'motivo' => $motivo]), 0, 2000),
                'status' => 'pending',
                'priority' => 'high',
                'warranty_of_id' => $original->id,
                'warranty_cause' => $causa,
                'warranty_reason' => mb_substr($motivo, 0, 500),
            ], 'order_number', 'OS-');

            WorkOrderHistory::logAction($original->id, WorkOrderHistory::ACTION_COMMENT,
                __('Retrabalho em garantia aberto: :ordem (:causa).', ['ordem' => $nova->order_number, 'causa' => __(self::CAUSAS[$causa])]));
            WorkOrderHistory::logAction($nova->id, WorkOrderHistory::ACTION_COMMENT,
                __('Ordem de garantia da :ordem — não se factura ao cliente.', ['ordem' => $original->order_number]));

            return $nova;
        });
    }

    public static function expirou(WorkOrder $o): bool
    {
        return $o->warranty_expires && $o->warranty_expires->lt(today());
    }

    /** O que a ficha mostra: se é garantia, de quê; e os retrabalhos que a ordem já teve. */
    public static function paraEcra(WorkOrder $o): array
    {
        $original = $o->warranty_of_id ? WorkOrder::withoutGlobalScope('tenant')->withTrashed()->find($o->warranty_of_id) : null;

        return [
            'e_garantia' => (bool) $o->warranty_of_id,
            'de_ordem' => $original ? ['id' => $original->id, 'numero' => $original->order_number] : null,
            'causa' => $o->warranty_cause,
            'causa_rotulo' => $o->warranty_cause ? __(self::CAUSAS[$o->warranty_cause] ?? $o->warranty_cause) : null,
            'motivo' => $o->warranty_reason,
            'garantia_ate' => $o->warranty_expires?->toDateString(),
            'expirou' => self::expirou($o),
            'pode_abrir' => ! $o->warranty_of_id && in_array($o->status, ['completed', 'delivered'], true),
            'retrabalhos' => WorkOrder::where('tenant_id', $o->tenant_id)->where('warranty_of_id', $o->id)->orderBy('received_at')->get()
                ->map(fn (WorkOrder $r) => [
                    'id' => $r->id,
                    'numero' => $r->order_number,
                    'estado_rotulo' => __(OrdensDeServico::ESTADOS[$r->status] ?? $r->status),
                    'causa_rotulo' => __(self::CAUSAS[$r->warranty_cause] ?? '—'),
                    'em' => $r->received_at?->toIso8601String(),
                ])->values(),
            'causas' => collect(self::CAUSAS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
        ];
    }
}
