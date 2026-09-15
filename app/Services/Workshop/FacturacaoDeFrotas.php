<?php

namespace App\Services\Workshop;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\StockMovement;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderClaim;
use App\Models\Workshop\WorkOrderHistory;
use App\Services\Invoicing\ModuleInvoiceService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A FACTURAÇÃO DE FROTAS (15/09/2026, OF-16).
 *
 * Uma empresa com várias viaturas recebe UMA factura com as ordens do período:
 * as linhas aprovadas de cada ordem, com a matrícula e o nº da OS à frente.
 *
 * SÓ ENTRAM ORDENS PRONTAS A FACTURAR: concluídas ou entregues, sem factura,
 * sem linhas à espera do cliente, que não sejam sinistro com seguradora (esse
 * tem a sua factura dividida) e cujas peças já saíram do stock. É esta última
 * que deixa a factura dizer ao `SalesInvoiceObserver` que não desconte outra
 * vez (origem `FROTA-…`).
 */
class FacturacaoDeFrotas
{
    public const PREFIXO = 'FROTA-';

    /** Os estados em que uma ordem se pode facturar em frota. */
    private const PRONTAS = ['completed', 'delivered'];

    /** As empresas com ordens por facturar — o que cada uma tem à espera. */
    public static function clientes(int $tenantId): array
    {
        return DB::table('workshop_work_orders as o')
            ->join('workshop_vehicles as v', 'v.id', '=', 'o.vehicle_id')
            ->join((new Client)->getTable() . ' as c', 'c.id', '=', 'v.client_id')
            ->leftJoin('workshop_claims as s', function ($j) {
                $j->on('s.work_order_id', '=', 'o.id')->whereNotNull('s.insurer_client_id');
            })
            ->where('o.tenant_id', $tenantId)->whereNull('o.deleted_at')->whereNull('o.invoice_id')
            ->whereIn('o.status', self::PRONTAS)->whereNull('s.id')
            ->groupBy('c.id', 'c.name', 'c.nif', 'c.type')
            ->selectRaw('c.id, c.name, c.nif, c.type, COUNT(o.id) as ordens, COUNT(DISTINCT v.id) as viaturas, SUM(o.total) as valor, MIN(COALESCE(o.completed_at, o.received_at)) as desde')
            ->orderByRaw("c.type = 'pessoa_juridica' DESC")->orderByDesc('valor')
            ->limit(200)->get()
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'nome' => $c->name,
                'nif' => $c->nif,
                'empresa' => $c->type === 'pessoa_juridica',
                'ordens' => (int) $c->ordens,
                'viaturas' => (int) $c->viaturas,
                'valor' => round((float) $c->valor, 2),
                'desde' => $c->desde ? substr((string) $c->desde, 0, 10) : null,
            ])->all();
    }

    /** As ordens do cliente no período, cada uma com a razão se não pode entrar. */
    public static function ordens(int $tenantId, int $clienteId, ?CarbonInterface $de, ?CarbonInterface $ate): Collection
    {
        $ordens = WorkOrder::where('tenant_id', $tenantId)
            ->whereHas('vehicle', fn ($q) => $q->where('client_id', $clienteId))
            ->whereNull('invoice_id')->whereIn('status', self::PRONTAS)
            ->when($de, fn ($q) => $q->whereRaw('COALESCE(completed_at, received_at) >= ?', [$de->copy()->startOfDay()]))
            ->when($ate, fn ($q) => $q->whereRaw('COALESCE(completed_at, received_at) <= ?', [$ate->copy()->endOfDay()]))
            ->with(['vehicle:id,plate,brand,model', 'items'])
            ->orderByRaw('COALESCE(completed_at, received_at)')->get();

        $sinistros = WorkOrderClaim::whereIn('work_order_id', $ordens->pluck('id'))->whereNotNull('insurer_client_id')->pluck('work_order_id')->flip();
        $comSaida = StockMovement::where('reference_type', 'WorkOrder')->whereIn('reference_id', $ordens->pluck('id'))
            ->where('type', StockMovement::TYPE_OUT)->distinct()->pluck('reference_id')->flip();

        return $ordens->map(function (WorkOrder $o) use ($sinistros, $comSaida) {
            $aprovadas = $o->items->where('approval', 'approved');
            $motivo = match (true) {
                isset($sinistros[$o->id]) => __('É um sinistro: factura-se no separador Facturação da ordem.'),
                $o->items->contains('approval', 'pending') => __('Tem linhas à espera da aprovação do cliente.'),
                $aprovadas->isEmpty() => __('Não tem linhas aprovadas.'),
                $aprovadas->where('type', 'part')->whereNotNull('product_id')->isNotEmpty() && ! isset($comSaida[$o->id]) => __('As peças ainda não saíram do stock.'),
                default => null,
            };

            return [
                'id' => $o->id,
                'numero' => $o->order_number,
                'matricula' => $o->vehicle?->plate,
                'viatura' => trim(($o->vehicle?->brand ?? '') . ' ' . ($o->vehicle?->model ?? '')),
                'entrada' => $o->received_at?->toIso8601String(),
                'concluida' => $o->completed_at?->toIso8601String(),
                'estado' => $o->status,
                'estado_rotulo' => __(OrdensDeServico::ESTADOS[$o->status] ?? $o->status),
                'linhas' => $aprovadas->count(),
                'total' => round((float) $o->total, 2),
                'pode' => $motivo === null,
                'motivo' => $motivo,
            ];
        })->values();
    }

    /**
     * UMA FACTURA PARA AS ORDENS ESCOLHIDAS.
     *
     * @param  list<int>  $ids
     */
    public static function facturar(int $tenantId, int $clienteId, array $ids): SalesInvoice
    {
        $cliente = Client::where('tenant_id', $tenantId)->findOrFail($clienteId);

        return DB::transaction(function () use ($tenantId, $cliente, $ids) {
            // Trancar as ordens e voltar a ver: dois cliques não facturam duas vezes.
            WorkOrder::where('tenant_id', $tenantId)->whereIn('id', $ids)->lockForUpdate()->get();
            $elegiveis = self::ordens($tenantId, $cliente->id, null, null)->whereIn('id', $ids);

            $recusadas = $elegiveis->where('pode', false);
            if ($elegiveis->count() !== count(array_unique($ids)) || $recusadas->isNotEmpty()) {
                throw new \InvalidArgumentException($recusadas->isNotEmpty()
                    ? __(':ordem: :motivo', ['ordem' => $recusadas->first()['numero'], 'motivo' => $recusadas->first()['motivo']])
                    : __('Há ordens escolhidas que já não estão por facturar a este cliente. Recarregue a lista.'));
            }
            if ($elegiveis->isEmpty()) {
                throw new \InvalidArgumentException(__('Escolha pelo menos uma ordem.'));
            }

            $armazem = \App\Models\Invoicing\Warehouse::getDefault($tenantId);
            if (! $armazem) {
                throw new \InvalidArgumentException(__('Nenhum armazém padrão configurado.'));
            }

            $ordens = WorkOrder::where('tenant_id', $tenantId)->whereIn('id', $elegiveis->pluck('id'))->with(['vehicle', 'items'])
                ->orderByRaw('COALESCE(completed_at, received_at)')->get();

            $linhas = [];
            foreach ($ordens as $o) {
                $prefixo = '[' . ($o->vehicle?->plate ?? '—') . ' · ' . $o->order_number . '] ';
                foreach ($o->linhasParaFacturar() as $l) {
                    $linhas[] = ['name' => mb_substr($prefixo . $l['name'], 0, 255)] + $l;
                }
            }

            $factura = app(ModuleInvoiceService::class)->emitir([
                'tenant_id' => $tenantId,
                'client_id' => $cliente->id,
                'warehouse_id' => $armazem->id,
                'lines' => $linhas,
                'discount_commercial' => round((float) $ordens->sum('discount'), 2),
                'retencao_irt' => $cliente->type === 'pessoa_juridica',
                'origem_modulo' => 'oficina',
                'origem' => self::PREFIXO . now()->format('YmdHis'),
                'notes' => __('Factura de frota — :n ordens de serviço:', ['n' => $ordens->count()]) . "\n"
                    . $ordens->map(fn (WorkOrder $o) => "{$o->order_number} · {$o->vehicle?->plate} {$o->vehicle?->brand} {$o->vehicle?->model}")->implode("\n"),
            ]);

            foreach ($ordens as $o) {
                $o->update(['invoice_id' => $factura->id, 'invoiced_at' => now()]);
                WorkOrderHistory::logAction($o->id, WorkOrderHistory::ACTION_COMMENT,
                    __('Facturada na factura de frota :numero (:n ordens).', ['numero' => $factura->invoice_number, 'n' => $ordens->count()]));
            }

            return $factura;
        });
    }
}
