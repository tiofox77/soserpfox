<?php

namespace App\Http\Controllers\Api\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderAttachment;
use App\Services\Invoicing\SomasDasFacturas;
use App\Services\Workshop\OrdensDeServico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A OFICINA NO PORTAL DO CLIENTE — o carro, o estado, a folha de obra e a factura.
 *
 * Pedido de 15/09/2026: «na oficina o cliente deve ver o estado do carro, o
 * status, a folha de obra, a factura para pagamento e outros dados relevantes».
 *
 * O QUE É DO CLIENTE:
 *  · as viaturas com `client_id` dele, nesta empresa;
 *  · as ordens dessas viaturas — MENOS as que foram facturadas a outra pessoa.
 *    Um carro que mudou de dono não mostra ao dono novo as reparações (e as
 *    facturas) do antigo.
 *
 * O QUE FICA DE FORA, de propósito: as notas internas, o histórico de quem mexeu
 * em quê, o mecânico, o custo das peças. O cliente vê o que o papel da ordem
 * lhe diria.
 */
class PortalDaOficinaApiController extends Controller
{
    /** O caminho de uma ordem, pela ordem em que acontece. */
    private const ETAPAS = ['pending', 'in_progress', 'waiting_parts', 'completed', 'delivered'];

    public function index(Request $request): JsonResponse
    {
        $cliente = $request->user('client');
        $viaturas = $this->viaturasDe($cliente);
        $ordens = $this->ordensDe($cliente, $viaturas->pluck('id')->all());

        $contas = \App\Models\Treasury\Account::withoutGlobalScopes()->with('bank')
            ->where('tenant_id', $cliente->tenant_id)
            ->where('is_active', true)->where('show_on_invoice', true)
            ->orderBy('invoice_display_order')->limit(4)->get();

        return response()->json([
            'resumo' => self::resumo($cliente),
            'viaturas' => $viaturas->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'matricula' => $v->plate,
                'viatura' => trim("{$v->brand} {$v->model}"),
                'ano' => $v->year,
                'cor' => $v->color,
                'km' => (int) $v->mileage,
                'documentos' => array_values(array_filter([
                    $this->documento(__('Livrete'), $v->registration_expiry),
                    $this->documento(__('Seguro'), $v->insurance_expiry),
                    $this->documento(__('Inspecção'), $v->inspection_expiry),
                ])),
                'na_oficina' => $ordens->where('vehicle_id', $v->id)->whereNotIn('status', ['delivered', 'cancelled'])->count() > 0,
            ])->values(),
            'ordens' => $ordens->map(fn (WorkOrder $o) => $this->ordem($o, $cliente))->values(),
            'contas' => $contas->map(fn ($c) => [
                'banco' => $c->bank?->name ?? $c->account_name,
                'conta' => $c->account_number,
                'iban' => $c->iban,
            ])->values(),
        ]);
    }

    /** Os números do cartão da oficina no início do portal. */
    public static function resumo(Client $cliente): array
    {
        $eu = new self();
        $viaturas = $eu->viaturasDe($cliente);
        $ordens = $eu->ordensDe($cliente, $viaturas->pluck('id')->all());
        $facturas = $ordens->pluck('invoice')->filter(fn ($f) => $f && $f->status !== 'draft');

        return [
            'viaturas' => $viaturas->count(),
            'na_oficina' => $ordens->whereNotIn('status', ['delivered', 'cancelled'])->count(),
            'prontas' => $ordens->where('status', 'completed')->count(),
            'por_pagar' => round((float) $facturas->sum(fn ($f) => $eu->falta($f)), 2),
        ];
    }

    private function viaturasDe(Client $cliente)
    {
        return Vehicle::withoutGlobalScopes()
            ->where('tenant_id', $cliente->tenant_id)
            ->where('client_id', $cliente->id)
            ->orderBy('plate')
            ->get();
    }

    private function ordensDe(Client $cliente, array $viaturas)
    {
        if (! $viaturas) {
            return collect();
        }

        return WorkOrder::withoutGlobalScopes()
            ->with(['invoice' => fn ($q) => $q->withoutGlobalScopes(), 'vehicle' => fn ($q) => $q->withoutGlobalScopes(), 'items', 'attachments', 'vehiclePhotos' => fn ($q) => $q->withoutGlobalScopes()->orderBy('created_at'),
                // As inspecções CONCLUÍDAS (OF-02): uma a meio não se mostra ao cliente.
                'inspections' => fn ($q) => $q->withoutGlobalScopes()->whereNotNull('completed_at')->orderByDesc('completed_at')])
            ->where('tenant_id', $cliente->tenant_id)
            ->whereIn('vehicle_id', $viaturas)
            ->orderByDesc('received_at')->orderByDesc('id')
            ->limit(60)
            ->get()
            // A ordem facturada a OUTRA pessoa não é deste cliente (carro que mudou de dono).
            ->filter(fn (WorkOrder $o) => ! $o->invoice || (int) $o->invoice->client_id === (int) $cliente->id)
            ->values();
    }

    private function ordem(WorkOrder $o, Client $cliente): array
    {
        $f = $o->invoice && $o->invoice->status !== 'draft' ? $o->invoice : null;
        $falta = $f ? $this->falta($f) : 0.0;
        $datas = [
            'pending' => $o->received_at,
            'in_progress' => $o->started_at,
            'waiting_parts' => null,
            'completed' => $o->completed_at,
            'delivered' => $o->delivered_at,
        ];
        $aqui = array_search($o->status, self::ETAPAS, true);

        return [
            'id' => $o->id,
            'numero' => $o->order_number,
            'matricula' => $o->vehicle?->plate,
            'viatura' => $o->vehicle ? trim("{$o->vehicle->brand} {$o->vehicle->model}") : null,
            'estado' => $o->status,
            'estado_rotulo' => __(OrdensDeServico::ESTADOS[$o->status] ?? $o->status),
            'cancelada' => $o->status === 'cancelled',
            // A LINHA DO TEMPO: cada etapa, feita ou não, com a data quando a há.
            // «À espera de peças» só aparece se a ordem lá estiver ou lá tiver passado.
            'etapas' => collect(self::ETAPAS)
                ->reject(fn ($e) => $e === 'waiting_parts' && $o->status !== 'waiting_parts')
                ->map(fn ($e) => [
                    'chave' => $e,
                    'rotulo' => __(OrdensDeServico::ESTADOS[$e]),
                    'feita' => $aqui !== false && array_search($e, self::ETAPAS, true) <= $aqui,
                    'actual' => $e === $o->status,
                    'quando' => $datas[$e]?->toIso8601String(),
                ])->values(),
            'entrada' => $o->received_at?->toIso8601String(),
            'agendada' => $o->scheduled_for?->toIso8601String(),
            'km' => (int) $o->mileage_in,
            'problema' => $o->problem_description,
            'diagnostico' => $o->diagnosis,
            'trabalho' => $o->work_performed,
            'recomendacoes' => $o->recommendations,
            'linhas' => $o->items->map(fn ($l) => [
                'tipo' => $l->type,
                'nome' => $l->name,
                'quantidade' => (float) $l->quantity,
                'total' => round((float) $l->subtotal, 2),
            ])->values(),
            'total' => round((float) $o->total, 2),
            'garantia_ate' => $o->warranty_expires?->toDateString(),
            'inspeccoes' => $o->inspections->map(fn ($i) => \App\Http\Controllers\Api\Workshop\InspeccoesDaOrdemApiController::paraEcra($i))->values(),
            'fotos' => $o->attachments
                ->filter(fn (WorkOrderAttachment $a) => in_array($a->category, ['photo_before', 'photo_after', 'photo_damage'], true))
                ->map(fn (WorkOrderAttachment $a) => [
                    'url' => $a->file_url,
                    'tipo' => $a->category,
                    'descricao' => $a->description,
                    'servico' => null,
                    'zona' => null,
                ])
                /*
                 * E AS FOTOGRAFIAS DA VIATURA POSTAS NESTA FOLHA DE OBRA — o antes
                 * e o depois do bate-chapa e da pintura. Só as ligadas a uma
                 * folha: as outras são registo interno da oficina.
                 */
                ->concat($o->vehiclePhotos->map(fn (\App\Models\Workshop\VehiclePhoto $v) => [
                    'url' => $v->url,
                    'tipo' => ['antes' => 'photo_before', 'depois' => 'photo_after', 'dano' => 'photo_damage'][$v->phase] ?? 'photo_during',
                    'descricao' => $v->description,
                    'servico' => __(\App\Models\Workshop\VehiclePhoto::SERVICOS[$v->service] ?? 'Outros'),
                    'zona' => $v->zone ? __(\App\Models\Workshop\VehiclePhoto::ZONAS[$v->zone] ?? $v->zone) : null,
                ]))->values(),
            'factura' => $f ? [
                'id' => $f->id,
                'numero' => $f->invoice_number,
                'data' => $f->invoice_date?->toDateString(),
                'vencimento' => $f->due_date?->toDateString(),
                'estado_rotulo' => $f->status_label,
                'total' => round((float) $f->total, 2),
                'falta' => $falta,
                'vencida' => $falta > 0 && $f->due_date && $f->due_date->lt(today()),
                'pdf' => route('client.invoices.pdf', $f->id),
            ] : null,
        ];
    }

    /** O que falta receber de uma factura — a regra única das facturas. */
    private function falta($f): float
    {
        if (($f->invoice_type ?? 'FT') === 'FR' || in_array($f->status, SomasDasFacturas::SEM_NADA_A_RECEBER, true)) {
            return 0.0;
        }

        return max(0.0, round((float) $f->total - (float) $f->paid_amount, 2));
    }

    /** Uma validade da viatura, com os dias que faltam (negativos = caducada). */
    private function documento(string $nome, $data): ?array
    {
        if (! $data) {
            return null;
        }

        return ['nome' => $nome, 'ate' => $data->toDateString(), 'dias' => (int) today()->diffInDays($data, false)];
    }
}
