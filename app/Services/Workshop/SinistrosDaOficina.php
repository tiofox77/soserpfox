<?php

namespace App\Services\Workshop;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderClaim;
use App\Models\Workshop\WorkOrderHistory;
use App\Services\Invoicing\ModuleInvoiceService;
use App\Services\Invoicing\TaxResolver;
use Illuminate\Support\Facades\DB;

/**
 * OS SINISTROS COM SEGURADORA (15/09/2026, OF-15).
 *
 * A FACTURA DIVIDE-SE EM DUAS, e as duas somam a ordem ao cêntimo.
 *
 * A franquia é um valor COM imposto (é o que o cliente paga). Tira-se das
 * linhas mais caras: de cada linha passa para a factura do cliente o líquido
 * que, com o imposto DESSA linha, dá o que falta da franquia — e a linha da
 * seguradora fica com o resto. Assim cada parte leva o imposto da sua taxa,
 * sem percentagens de desconto com casas decimais que a AGT não aceitaria, e
 * sem linhas negativas.
 *
 * Tudo sai pela porta fiscal única (`ModuleInvoiceService::emitir`).
 */
class SinistrosDaOficina
{
    /**
     * @param  list<array{product_id: int, name: string, quantity: float, unit_price: float, discount_percent: float, is_service: bool}>  $linhas
     * @return array{seguradora: list<array>, cliente: list<array>, cabe: bool}
     */
    public static function repartir(array $linhas, float $franquia, int $tenantId, string $processo): array
    {
        $porValor = collect($linhas)->values()->map(function (array $l, int $i) use ($tenantId) {
            $liquido = round((float) $l['quantity'] * (float) $l['unit_price'] * (1 - (float) ($l['discount_percent'] ?? 0) / 100), 2);

            return $l + ['_i' => $i, '_liquido' => $liquido, '_taxa' => (float) TaxResolver::forProductId($l['product_id'], $tenantId)['rate']];
        })->sortByDesc('_liquido')->values();

        $nomeDaFranquia = fn (string $linha) => mb_substr(__('Franquia do sinistro :processo — :linha', ['processo' => $processo ?: '—', 'linha' => $linha]), 0, 255);
        $limpa = fn (array $l) => array_diff_key($l, ['_i' => 1, '_liquido' => 1, '_taxa' => 1]);

        $falta = round(max(0, $franquia), 2);
        $seguradora = [];
        $cliente = [];

        foreach ($porValor as $l) {
            if ($falta < 0.005 || $l['_liquido'] <= 0) {
                $seguradora[$l['_i']] = $limpa($l);

                continue;
            }

            $brutoDaLinha = round($l['_liquido'] * (1 + $l['_taxa'] / 100), 2);

            // A linha inteira não chega (ou chega à justa): passa toda para o cliente.
            if ($brutoDaLinha <= $falta + 0.005) {
                $cliente[] = ['name' => $nomeDaFranquia($l['name']), 'quantity' => 1.0, 'unit_price' => $l['_liquido'], 'discount_percent' => 0.0] + $limpa($l);
                $falta = round($falta - $brutoDaLinha, 2);

                continue;
            }

            $tirar = round($falta / (1 + $l['_taxa'] / 100), 2);
            $cliente[] = ['name' => $nomeDaFranquia($l['name']), 'quantity' => 1.0, 'unit_price' => $tirar, 'discount_percent' => 0.0] + $limpa($l);

            // A da seguradora fica com o resto, numa quantidade (a conta da linha vai no nome).
            $nome = (float) $l['quantity'] != 1.0
                ? mb_substr($l['name'] . ' (' . rtrim(rtrim(number_format((float) $l['quantity'], 2, ',', ''), '0'), ',') . ' × ' . number_format((float) $l['unit_price'], 2, ',', '.') . ')', 0, 255)
                : $l['name'];
            $seguradora[$l['_i']] = ['name' => $nome, 'quantity' => 1.0, 'unit_price' => round($l['_liquido'] - $tirar, 2), 'discount_percent' => 0.0] + $limpa($l);
            $falta = 0.0;
        }

        ksort($seguradora);

        return ['seguradora' => array_values($seguradora), 'cliente' => $cliente, 'cabe' => $falta < 0.005];
    }

    /**
     * FACTURAR O SINISTRO: a factura da seguradora e, havendo franquia, a do cliente.
     *
     * @return array{0: SalesInvoice, 1: ?SalesInvoice}
     */
    public static function facturar(WorkOrder $ordem): array
    {
        $sinistro = WorkOrderClaim::withoutGlobalScope('tenant')->where('work_order_id', $ordem->id)->firstOrFail();
        $seguradora = Client::where('tenant_id', $ordem->tenant_id)->find($sinistro->insurer_client_id);

        if (! $seguradora) {
            throw new \InvalidArgumentException(__('Escolha a seguradora no separador Sinistro antes de facturar.'));
        }
        if ($ordem->invoice_id) {
            throw new \InvalidArgumentException(__('Esta ordem de serviço já foi facturada.'));
        }
        if (! $ordem->vehicle) {
            throw new \InvalidArgumentException(__('Ordem de serviço sem veículo associado.'));
        }
        $aEspera = $ordem->pendingItems()->count();
        if ($aEspera > 0) {
            throw new \InvalidArgumentException(trans_choice('Há :n linha à espera da aprovação do cliente. Registe a decisão antes de facturar.|Há :n linhas à espera da aprovação do cliente. Registe a decisão antes de facturar.', $aEspera, ['n' => $aEspera]));
        }
        if (! $ordem->items()->where('approval', 'approved')->exists()) {
            throw new \InvalidArgumentException(__('A ordem não tem linhas aprovadas para facturar.'));
        }

        return DB::transaction(function () use ($ordem, $sinistro, $seguradora) {
            $bloqueada = WorkOrder::withoutGlobalScope('tenant')->whereKey($ordem->id)->lockForUpdate()->first();
            if ($bloqueada?->invoice_id) {
                throw new \InvalidArgumentException(__('Esta ordem de serviço já foi facturada.'));
            }

            $armazem = \App\Models\Invoicing\Warehouse::getDefault($ordem->tenant_id);
            if (! $armazem) {
                throw new \InvalidArgumentException(__('Nenhum armazém padrão configurado.'));
            }

            $franquia = round((float) $sinistro->excess_amount, 2);
            $r = self::repartir($ordem->linhasParaFacturar(), $franquia, (int) $ordem->tenant_id, (string) $sinistro->claim_number);

            if (! $r['cabe'] || $r['seguradora'] === []) {
                throw new \InvalidArgumentException(__('A franquia (:valor Kz) não cabe no total das linhas aprovadas: a seguradora ficaria sem nada a pagar.', ['valor' => number_format($franquia, 2, ',', '.')]));
            }

            $emissor = app(ModuleInvoiceService::class);
            $viatura = "{$ordem->vehicle->plate} - {$ordem->vehicle->brand} {$ordem->vehicle->model}";
            $processo = $sinistro->claim_number ?: '—';

            $daSeguradora = $emissor->emitir([
                'tenant_id' => $ordem->tenant_id,
                'client_id' => $seguradora->id,
                'warehouse_id' => $armazem->id,
                'lines' => $r['seguradora'],
                'discount_commercial' => (float) ($ordem->discount ?? 0),
                'retencao_irt' => $seguradora->type === 'pessoa_juridica',
                'origem_modulo' => 'oficina',
                'origem' => $ordem->order_number,
                'notes' => __('Sinistro nº :processo · Apólice :apolice · Viatura :viatura · OS :ordem', ['processo' => $processo, 'apolice' => $sinistro->policy_number ?: '—', 'viatura' => $viatura, 'ordem' => $ordem->order_number])
                    . ($franquia > 0 ? "\n" . __('Franquia a cargo do segurado: :valor Kz (facturada à parte).', ['valor' => number_format($franquia, 2, ',', '.')]) : ''),
            ]);

            $daFranquia = null;
            if ($r['cliente'] !== []) {
                $dono = $ordem->clienteDaFactura();
                $daFranquia = $emissor->emitir([
                    'tenant_id' => $ordem->tenant_id,
                    'client_id' => $dono->id,
                    'warehouse_id' => $armazem->id,
                    'lines' => $r['cliente'],
                    'retencao_irt' => $dono->type === 'pessoa_juridica',
                    'origem_modulo' => 'oficina',
                    'origem' => $ordem->order_number,
                    'notes' => __('Franquia do sinistro nº :processo (seguradora :seguradora) · Viatura :viatura · OS :ordem', ['processo' => $processo, 'seguradora' => $seguradora->name, 'viatura' => $viatura, 'ordem' => $ordem->order_number]),
                ]);
            }

            $ordem->update(['invoice_id' => $daSeguradora->id, 'invoiced_at' => now()]);
            $sinistro->update(['excess_invoice_id' => $daFranquia?->id]);

            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT, $daFranquia
                ? __('Sinistro facturado: :seguradora à seguradora e :franquia ao cliente (franquia).', ['seguradora' => $daSeguradora->invoice_number, 'franquia' => $daFranquia->invoice_number])
                : __('Sinistro facturado à seguradora: :seguradora.', ['seguradora' => $daSeguradora->invoice_number]));

            return [$daSeguradora, $daFranquia];
        });
    }

    public static function paraEcra(?WorkOrderClaim $s): ?array
    {
        return $s ? [
            'seguradora_id' => $s->insurer_client_id,
            'seguradora' => $s->insurer?->name,
            'processo' => $s->claim_number,
            'apolice' => $s->policy_number,
            'data_sinistro' => $s->accident_date?->toDateString(),
            'perito' => $s->adjuster_name,
            'perito_telefone' => $s->adjuster_phone,
            'perito_email' => $s->adjuster_email,
            'data_peritagem' => $s->inspection_date?->toDateString(),
            'valor_aprovado' => $s->approved_amount !== null ? (float) $s->approved_amount : null,
            'franquia' => (float) $s->excess_amount,
            'estado' => $s->status,
            'estado_rotulo' => __(WorkOrderClaim::ESTADOS[$s->status] ?? $s->status),
            'notas' => $s->notes,
            'factura_franquia' => $s->excessInvoice ? [
                'id' => $s->excessInvoice->id,
                'numero' => $s->excessInvoice->invoice_number,
                'cliente' => $s->excessInvoice->client?->name,
                'morada' => route('invoicing.sales.invoices.preview', $s->excessInvoice->id),
            ] : null,
        ] : null;
    }
}
