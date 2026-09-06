<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\TransportGuide;
use App\Models\Invoicing\TransportGuideItem;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * EMITIR UMA GUIA DE TRANSPORTE OU DE REMESSA — num sítio só.
 *
 * Vivia dentro do `TransportGuides\TransportGuides`. Ao migrar o ecrã para
 * React, saiu para aqui; o Livewire e a API chamam o mesmo.
 *
 * O QUE ESTE CAMINHO GARANTE:
 *
 *  · O NÚMERO é por tipo e por ano (GT-2026-0001), contado também sobre as
 *    guias anuladas — um número não se reutiliza.
 *  · A SÉRIE é a do tipo `transport`, se a empresa a tiver registada: é ela
 *    que dá o código de validação do ATCUD.
 *  · O VALOR DA MERCADORIA vem da factura de origem, quando há: é o que se
 *    assina no SAFT-AO.
 *  · O hash e a assinatura JWS saem dos ganchos do modelo, antes do insert,
 *    para o encadeamento ficar certo.
 */
class EmissorDeGuias
{
    public function regras(): array
    {
        return [
            'type' => 'required|in:GT,GR',
            'client_id' => 'required|integer',
            'invoice_id' => 'nullable|integer',
            'issue_date' => 'required|date',
            'loading_datetime' => 'nullable|date',
            'vehicle_plate' => 'nullable|string|max:30',
            'driver_name' => 'nullable|string|max:120',
            'driver_document' => 'nullable|string|max:60',
            'load_address' => 'nullable|string|max:255',
            'unload_address' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    /**
     * @param  array $itens  [{product_id, product_name, description, quantity, unit}]
     *
     * @throws DomainException sem linhas com quantidade
     */
    public function emitir(array $d, array $itens, int $tenantId, ?int $autorId): TransportGuide
    {
        $itens = array_values(array_filter($itens, fn ($i) => (float) ($i['quantity'] ?? 0) > 0));

        if (empty($itens)) {
            throw new DomainException(__('Adicione pelo menos um item com quantidade.'));
        }

        return DB::transaction(function () use ($d, $itens, $tenantId, $autorId) {
            $facturaId = ! empty($d['invoice_id']) ? (int) $d['invoice_id'] : null;

            // O valor da mercadoria transportada, da factura de origem se houver.
            $valor = $facturaId
                ? (float) (SalesInvoice::where('tenant_id', $tenantId)->where('id', $facturaId)->value('total') ?? 0)
                : 0.0;

            $serie = InvoicingSeries::where('tenant_id', $tenantId)
                ->where('document_type', 'transport')
                ->where('is_active', true)
                ->orderByDesc('id')
                ->first();

            $guia = TransportGuide::create([
                'tenant_id' => $tenantId,
                'guide_number' => $this->proximoNumero($tenantId, $d['type']),
                'type' => $d['type'],
                'series_id' => $serie?->id,
                'invoice_id' => $facturaId,
                'client_id' => $d['client_id'],
                'gross_total' => $valor,
                'system_entry_date' => now(),
                'issue_date' => $d['issue_date'],
                'loading_datetime' => ($d['loading_datetime'] ?? null) ?: null,
                'vehicle_plate' => $d['vehicle_plate'] ?? null,
                'driver_name' => $d['driver_name'] ?? null,
                'driver_document' => $d['driver_document'] ?? null,
                'load_address' => $d['load_address'] ?? null,
                'unload_address' => $d['unload_address'] ?? null,
                'notes' => $d['notes'] ?? null,
                'status' => 'issued',
                'created_by' => $autorId,
            ]);

            foreach ($itens as $it) {
                TransportGuideItem::create([
                    'transport_guide_id' => $guia->id,
                    'product_id' => $it['product_id'] ?? null,
                    'product_name' => $it['product_name'] ?? null,
                    'description' => $it['description'] ?? ($it['product_name'] ?? ''),
                    'quantity' => (float) $it['quantity'],
                    'unit' => $it['unit'] ?? 'un',
                ]);
            }

            return $guia;
        });
    }

    /** Anular: fica cancelada e sai da lista, mas o número fica gasto. */
    public function anular(TransportGuide $guia): void
    {
        $guia->update(['status' => 'cancelled']);
        $guia->delete();
    }

    /**
     * Comunicar à AGT (DS.120), conforme o ambiente da empresa.
     *
     * @return array{ok: bool, mensagem: string}
     */
    public function comunicar(TransportGuide $guia): array
    {
        if (empty($guia->jws_signature)) {
            return ['ok' => false, 'mensagem' => __('A guia não está assinada (a empresa não tem chaves AGT).')];
        }

        try {
            $r = $guia->submitToAGT();
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensagem' => __('Erro ao comunicar à AGT: :detalhe', ['detalhe' => $e->getMessage()])];
        }

        return ($r['success'] ?? false)
            ? ['ok' => true, 'mensagem' => __('Guia comunicada à AGT. Referência: :ref', ['ref' => $r['requestID'] ?? '—'])]
            : ['ok' => false, 'mensagem' => 'AGT: ' . ($r['error'] ?? __('submissão rejeitada'))];
    }

    /** As linhas de uma factura de venda, prontas a copiar para a guia. */
    public function linhasDaFactura(SalesInvoice $factura): array
    {
        return $factura->items->map(fn ($it) => [
            'product_id' => $it->product_id,
            'product_name' => $it->product_name ?? $it->description,
            'description' => $it->description,
            'quantity' => (float) $it->quantity,
            'unit' => $it->unit ?? 'un',
        ])->values()->all();
    }

    private function proximoNumero(int $tenantId, string $tipo): string
    {
        $ultima = TransportGuide::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('type', $tipo)
            ->whereYear('created_at', date('Y'))
            ->orderByDesc('id')
            ->first();

        $n = $ultima ? ((int) substr($ultima->guide_number, -4)) + 1 : 1;

        return $tipo . '-' . date('Y') . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
