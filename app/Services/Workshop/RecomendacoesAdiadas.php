<?php

namespace App\Services\Workshop;

use App\Models\Workshop\DeferredItem;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderHistory;
use App\Models\Workshop\WorkOrderInspection;
use App\Models\Workshop\WorkOrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AS RECOMENDAÇÕES ADIADAS (15/09/2026, OF-12).
 *
 * TRÊS PORTAS DE ENTRADA, UMA DE SAÍDA:
 *
 *   · a linha RECUSADA (pelo cliente no link, ou marcada pela oficina) guarda-se
 *     sozinha — no `updated` da linha, venha a decisão de onde vier; e sai da
 *     lista se a decisão voltar atrás;
 *   · a linha ADIADA pela oficina sai da ordem e fica guardada;
 *   · os pontos amarelos e vermelhos da INSPECÇÃO guardam-se quando se passam às
 *     recomendações.
 *
 * Sai-se por JUNTAR a uma ordem (pela porta das linhas, com ou sem aprovação do
 * cliente) ou por DESCARTAR. Nada disto mexe em stock nem em totais.
 */
class RecomendacoesAdiadas
{
    /** Dias até se voltar a propor, quando ninguém diz outra coisa. */
    public const DIAS_POR_OMISSAO = 30;

    /** A linha mudou de decisão — chamado do `updated` da linha. */
    public static function decisaoMudou(WorkOrderItem $linha): void
    {
        try {
            if ($linha->approval === 'declined') {
                self::daLinha($linha, 'recusada');
            } elseif ($linha->getOriginal('approval') === 'declined') {
                DeferredItem::withoutGlobalScope('tenant')->where('work_order_item_id', $linha->id)
                    ->where('origin', 'recusada')->where('status', 'pendente')->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('Recomendação adiada não guardada', ['linha' => $linha->id, 'erro' => $e->getMessage()]);
        }
    }

    public static function daLinha(WorkOrderItem $linha, string $origem, ?Carbon $voltar = null, ?string $nota = null, ?int $userId = null): ?DeferredItem
    {
        $ordem = WorkOrder::withoutGlobalScope('tenant')->find($linha->work_order_id);
        if (! $ordem?->vehicle_id) {
            return null;
        }

        $existe = DeferredItem::withoutGlobalScope('tenant')->where('work_order_item_id', $linha->id)->where('status', 'pendente')->first();
        if ($existe) {
            return $existe;
        }

        return DeferredItem::create([
            'tenant_id' => $ordem->tenant_id,
            'vehicle_id' => $ordem->vehicle_id,
            'work_order_id' => $ordem->id,
            'work_order_item_id' => $linha->id,
            'type' => $linha->type === 'part' ? 'part' : 'service',
            'service_id' => $linha->service_id,
            'product_id' => $linha->product_id,
            'code' => $linha->code,
            'name' => $linha->name,
            'description' => $linha->description,
            'quantity' => $linha->quantity ?: 1,
            'unit_price' => $linha->unit_price ?: 0,
            'hours' => $linha->hours ?: 0,
            'origin' => $origem,
            'follow_up_on' => $voltar ?? today()->addDays(self::DIAS_POR_OMISSAO),
            'note' => $nota,
            'user_id' => $userId,
        ]);
    }

    /** A oficina adia uma linha: sai da ordem e fica guardada para outra visita. */
    public static function adiarLinha(OrdensDeServico $ordens, WorkOrder $ordem, WorkOrderItem $linha, ?Carbon $voltar, ?string $nota, ?int $userId): DeferredItem
    {
        return DB::transaction(function () use ($ordens, $ordem, $linha, $voltar, $nota, $userId) {
            $r = self::daLinha($linha, 'adiada', $voltar, $nota, $userId);
            // A linha vai-se; a recomendação não fica presa a um id que deixa de existir.
            $r->update(['work_order_item_id' => null]);
            $ordens->tirarLinha($ordem, $linha);

            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                __('«:nome» adiado para outra visita (voltar a propor a :data).', ['nome' => $r->name, 'data' => $r->follow_up_on?->format('d/m/Y')]));

            return $r;
        });
    }

    /**
     * Os pontos urgentes e de atenção de uma inspecção.
     *
     * @return int quantos ficaram guardados (os que já lá estavam não se repetem)
     */
    public static function daInspeccao(WorkOrder $ordem, WorkOrderInspection $i, ?int $userId): int
    {
        if (! $ordem->vehicle_id) {
            return 0;
        }

        $n = 0;
        foreach ($i->results ?? [] as $r) {
            if (! in_array($r['estado'] ?? null, ['urgente', 'atencao'], true)) {
                continue;
            }

            $nome = mb_substr("{$r['seccao']}: {$r['ponto']}", 0, 255);
            $jaHa = DeferredItem::withoutGlobalScope('tenant')->where('vehicle_id', $ordem->vehicle_id)
                ->where('status', 'pendente')->where('name', $nome)->exists();
            if ($jaHa) {
                continue;
            }

            DeferredItem::create([
                'tenant_id' => $ordem->tenant_id,
                'vehicle_id' => $ordem->vehicle_id,
                'work_order_id' => $ordem->id,
                'type' => 'service',
                'name' => $nome,
                'description' => $r['nota'] ?? null,
                'quantity' => 1,
                'unit_price' => 0,
                'origin' => 'inspeccao',
                'severity' => $r['estado'],
                // O urgente volta-se a propor já; o de atenção, daqui a um mês.
                'follow_up_on' => $r['estado'] === 'urgente' ? today() : today()->addDays(self::DIAS_POR_OMISSAO),
                'user_id' => $userId,
            ]);
            $n++;
        }

        return $n;
    }

    /** As que esperam nesta viatura. */
    public static function pendentes(int $tenantId, int $vehicleId): Collection
    {
        return DeferredItem::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->where('vehicle_id', $vehicleId)
            ->where('status', 'pendente')->with('workOrder:id,order_number')
            ->orderByRaw("CASE severity WHEN 'urgente' THEN 0 WHEN 'atencao' THEN 1 ELSE 2 END")->orderBy('follow_up_on')->get();
    }

    /**
     * JUNTAR À ORDEM: cada uma entra pela porta das linhas.
     *
     * @param  list<int>  $ids
     * @return int quantas entraram
     */
    public static function juntar(OrdensDeServico $ordens, WorkOrder $ordem, array $ids, bool $precisaAprovacao, ?int $userId): int
    {
        return DB::transaction(function () use ($ordens, $ordem, $ids, $precisaAprovacao, $userId) {
            $itens = DeferredItem::withoutGlobalScope('tenant')->where('tenant_id', $ordem->tenant_id)->where('vehicle_id', $ordem->vehicle_id)
                ->where('status', 'pendente')->whereIn('id', $ids)->lockForUpdate()->get();

            foreach ($itens as $r) {
                $ordens->juntarLinha($ordem, [
                    'type' => $r->type,
                    'service_id' => $r->service_id,
                    'product_id' => $r->product_id,
                    'code' => $r->code,
                    'name' => $r->name,
                    'description' => $r->description,
                    'quantity' => (float) $r->quantity,
                    'unit_price' => (float) $r->unit_price,
                    'hours' => (float) $r->hours,
                    'precisa_aprovacao' => $precisaAprovacao,
                ]);

                $r->update(['status' => 'aceite', 'resolved_work_order_id' => $ordem->id, 'resolved_at' => now(), 'resolved_by' => $userId]);
            }

            if ($itens->isNotEmpty()) {
                WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                    trans_choice(':n recomendação adiada voltou a esta ordem.|:n recomendações adiadas voltaram a esta ordem.', $itens->count(), ['n' => $itens->count()]));
            }

            return $itens->count();
        });
    }

    public static function paraEcra(DeferredItem $r): array
    {
        return [
            'id' => $r->id,
            'viatura_id' => $r->vehicle_id,
            'tipo' => $r->type,
            'nome' => $r->name,
            'descricao' => $r->description,
            'codigo' => $r->code,
            'quantidade' => (float) $r->quantity,
            'preco' => (float) $r->unit_price,
            'valor' => $r->valor(),
            'origem' => $r->origin,
            'origem_rotulo' => __(DeferredItem::ORIGENS[$r->origin] ?? $r->origin),
            'gravidade' => $r->severity,
            'voltar_em' => $r->follow_up_on?->toDateString(),
            'estado' => $r->status,
            'estado_rotulo' => __(DeferredItem::ESTADOS[$r->status] ?? $r->status),
            'ordem_id' => $r->work_order_id,
            'ordem' => $r->workOrder?->order_number,
            'resolvida_na_ordem_id' => $r->resolved_work_order_id,
            'resolvida_na_ordem' => $r->resolvedWorkOrder?->order_number,
            'resolvida_em' => $r->resolved_at?->toIso8601String(),
            'nota' => $r->note,
            'criada_em' => $r->created_at?->toIso8601String(),
        ];
    }
}
