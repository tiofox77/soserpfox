<?php

namespace App\Services\Invoicing;

use App\Helpers\InvoiceCalculationHelper;
use App\Helpers\SAFTHelper;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\PurchaseInvoiceItem;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * REGISTAR UMA FACTURA DE COMPRA — num sítio só.
 *
 * Vivia dentro do `Purchases\InvoiceCreate`. Ao migrar o ecrã para React,
 * saiu para aqui; o Livewire e a API chamam o mesmo.
 *
 * O QUE ESTE CAMINHO GARANTE, e a ordem importa:
 *
 *  · O STATUS FINAL É POSTO NO FIM. A factura nasce em `draft`, recebem-se as
 *    linhas e os totais, e SÓ DEPOIS se muda o estado. É a mudança
 *    draft→estado que o `PurchaseInvoiceObserver` vê para dar entrada do
 *    stock e dos lotes — com as linhas já lá. Pôr o estado logo no início
 *    fazia o observer correr sem linhas e não entrava nada.
 *
 *  · NUMA EDIÇÃO O ESTADO NÃO MUDA, para o stock não entrar duas vezes. O
 *    observer também tem o `stock_ja_entrou` como segunda guarda.
 *
 *  · O PREÇO DE COMPRA NOVO PASSA A SER O CUSTO DO ARTIGO. Numa factura nova
 *    o observer já o faz; numa edição é aqui o único caminho — corrigir o
 *    preço de uma compra recebida tem de corrigir o custo, senão a margem
 *    fica presa ao valor errado.
 *
 *  · O HASH assina o gross_total, por isso só depois dos totais.
 */
class EmissorDeCompras
{
    /**
     * @param  array $dados  supplier_id, warehouse_id, invoice_date, due_date, is_service,
     *                       discount_amount, discount_commercial, discount_financial, notes,
     *                       terms, tax_country_region, status
     * @param  Collection $linhas  objectos com id, name, price, quantity, attributes
     *                       (unit, discount_percent, tax_rate, batch_number,
     *                       manufacturing_date, expiry_date, alert_days)
     * @return PurchaseInvoice
     *
     * @throws DomainException
     */
    public function emitir(array $dados, Collection $linhas, ?PurchaseInvoice $existente = null): PurchaseInvoice
    {
        $tenantId = activeTenantId();
        $status = $dados['status'] ?? 'draft';
        $eEdicao = $existente !== null;

        if ($linhas->isEmpty()) {
            throw new DomainException(__('Adicione pelo menos um produto à fatura.'));
        }

        if ($eEdicao && $existente->status === 'converted') {
            throw new DomainException(__('Não é possível editar uma fatura já convertida.'));
        }

        return DB::transaction(function () use ($dados, $linhas, $existente, $eEdicao, $status, $tenantId) {
            if ($eEdicao) {
                $factura = $existente;
                $factura->items()->delete();
            } else {
                $factura = new PurchaseInvoice();
                $factura->tenant_id = $tenantId;
                $factura->created_by = auth()->id();
                // Nasce em rascunho: o estado final vai no fim, com as linhas já lá.
                $factura->status = 'draft';
            }

            $factura->supplier_id = $dados['supplier_id'];
            $factura->warehouse_id = $dados['warehouse_id'] ?? null;
            $factura->invoice_date = $dados['invoice_date'];
            $factura->due_date = $dados['due_date'] ?? null;
            $factura->is_service = (bool) ($dados['is_service'] ?? false);
            $factura->discount_amount = (float) ($dados['discount_amount'] ?? 0);
            $factura->discount_commercial = (float) ($dados['discount_commercial'] ?? 0);
            $factura->discount_financial = (float) ($dados['discount_financial'] ?? 0);
            $factura->notes = $dados['notes'] ?? null;
            $factura->terms = $dados['terms'] ?? null;
            $factura->save();

            $regiao = in_array($dados['tax_country_region'] ?? null, ['AO', 'AO-CAB'], true)
                ? $dados['tax_country_region']
                : 'AO';

            $ordem = 0;
            $subtotal = 0.0;
            $iva = 0.0;

            foreach ($linhas as $item) {
                $a = InvoiceCalculationHelper::atributos($item);

                $bruto = (float) $item->price * (float) $item->quantity;
                $pct = (float) ($a['discount_percent'] ?? 0);
                $desconto = $bruto * ($pct / 100);
                $aposDesconto = $bruto - $desconto;
                $taxa = (float) ($a['tax_rate'] ?? 0);
                $ivaLinha = $aposDesconto * ($taxa / 100);

                $subtotal += $bruto;
                $iva += $ivaLinha;

                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $factura->id,
                    'product_id' => is_numeric($item->id) ? (int) $item->id : null,
                    'product_name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit' => $a['unit'] ?? 'UN',
                    'unit_price' => $item->price,
                    'discount_percent' => $pct,
                    'discount_amount' => $desconto,
                    'subtotal' => $bruto,
                    'tax_rate' => $taxa,
                    'tax_country_region' => $regiao,
                    'tax_amount' => $ivaLinha,
                    'total' => $aposDesconto + $ivaLinha,
                    'order' => ++$ordem,
                    // Lotes e validades: é a compra que os cria.
                    'batch_number' => $a['batch_number'] ?? null,
                    'manufacturing_date' => $a['manufacturing_date'] ?? null,
                    'expiry_date' => $a['expiry_date'] ?? null,
                    'alert_days' => $a['alert_days'] ?? 30,
                ]);
            }

            $descontoComercial = (float) $factura->discount_commercial + (float) $factura->discount_amount;
            $aposComercial = $subtotal - $descontoComercial;
            $incidencia = $aposComercial - (float) $factura->discount_financial;
            $irt = $factura->is_service ? $incidencia * 0.065 : 0;

            $factura->subtotal = $subtotal;
            $factura->tax_amount = $iva;
            $factura->irt_amount = $irt;
            $factura->total = $incidencia + $iva - $irt;
            $factura->net_total = $subtotal - $descontoComercial - (float) ($factura->discount_financial ?? 0);
            $factura->tax_payable = $iva;
            $factura->gross_total = $factura->net_total + $iva;
            $factura->system_entry_date = $factura->system_entry_date ?? now();
            $factura->save();

            $anterior = PurchaseInvoice::where('tenant_id', $tenantId)
                ->where('id', '<', $factura->id)
                ->whereNotNull('saft_hash')
                ->orderByDesc('id')
                ->first();

            $hash = SAFTHelper::generateHash(
                $factura->invoice_date->format('Y-m-d'),
                ($factura->system_entry_date ?? $factura->created_at)->format('Y-m-d H:i:s'),
                $factura->invoice_number,
                $factura->gross_total,
                $anterior->saft_hash ?? null
            );

            if ($hash) {
                $factura->saft_hash = $hash;
                $factura->hash = $hash;
                $factura->hash_previous = $anterior->saft_hash ?? '';
                $factura->hash_control = '1';
            }

            // O ESTADO FINAL, AGORA: linhas e totais já existem, e é esta mudança
            // que o observer vê para dar entrada do stock e dos lotes.
            $factura->status = $status;
            $factura->save();

            if (in_array($status, PurchaseInvoice::ESTADOS_COM_STOCK, true)) {
                app(ActualizarCustoDeCompra::class)->aplicar($factura->fresh(['items.product']));
            }

            return $factura;
        });
    }
}
