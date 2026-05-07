<?php

namespace App\Services\AGT;

use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use Illuminate\Database\Eloquent\Model;

/**
 * AGT v1.2 — Mapper Eloquent → array de payload AGT.
 *
 * Aceita SalesInvoice (FT/FR), CreditNote (NC), DebitNote (ND), Receipt (RC)
 * e devolve a estrutura esperada por AGTPayloadBuilder::buildDocument().
 */
class DocumentMapper
{
    /** Default EAC (CAE) se o tenant não tiver configurado. */
    private const DEFAULT_EAC = '00000';

    /**
     * Mapear um modelo de documento para o array AGT v1.2.
     */
    public function map(Model $document): array
    {
        $type = $this->resolveType($document);
        $client = $document->client ?? null;
        $tenantSettings = $this->getSettings($document);

        $eacCode = $document->eac_code
            ?? $tenantSettings?->agt_eac_code
            ?? self::DEFAULT_EAC;

        $documentNo  = $this->resolveDocumentNumber($document, $type);
        $documentDate = $this->resolveDocumentDate($document);
        $systemEntry  = $this->resolveSystemEntryDate($document);

        $companyName = $client?->name ?? $client?->company_name ?? 'Consumidor Final';
        $customerNif = $client?->nif ?? '999999990';
        $customerCountry = strtoupper($client?->country ?? 'AO');

        $items = $document->items ?? collect();
        $isCreditNote = ($type === 'NC');

        $lines = [];
        $lineNo = 0;
        foreach ($items as $item) {
            $lineNo++;
            $lines[] = $this->mapLine($item, $lineNo, $isCreditNote, $eacCode);
        }

        $totals = $this->mapTotals($document);

        $doc = [
            'documentNo'      => $documentNo,
            'documentType'    => $type,
            'documentStatus'  => $document->document_status_code ?? 'N',
            'documentDate'    => $documentDate,
            'systemEntryDate' => $systemEntry,
            'eacCode'         => $eacCode,
            'customerTaxID'   => $customerNif,
            'customerCountry' => $customerCountry,
            'companyName'     => $companyName,
            'lines'           => $lines,
            'documentTotals'  => $totals,
        ];

        // Withholding (IRT/IPU/IPC) se aplicável
        $withholding = $this->mapWithholdings($document);
        if (!empty($withholding)) {
            $doc['withholdingTaxList'] = $withholding;
        }

        return $doc;
    }

    /** Resolver tipo de documento AGT (FT/FR/NC/ND/RC). */
    private function resolveType(Model $document): string
    {
        if ($document instanceof SalesInvoice) {
            return strtoupper($document->invoice_type ?? 'FT');
        }
        if ($document instanceof CreditNote) return 'NC';
        if ($document instanceof DebitNote)  return 'ND';
        if ($document instanceof Receipt)    return 'RC';
        return 'FT';
    }

    private function resolveDocumentNumber(Model $document, string $type): string
    {
        return (string) (
            $document->invoice_number
            ?? $document->credit_note_number
            ?? $document->debit_note_number
            ?? $document->receipt_number
            ?? ''
        );
    }

    private function resolveDocumentDate(Model $document): string
    {
        $date = $document->invoice_date
            ?? $document->issue_date
            ?? $document->receipt_date
            ?? now();
        return $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : (string) $date;
    }

    private function resolveSystemEntryDate(Model $document): string
    {
        $dt = $document->system_entry_date ?? $document->created_at ?? now();
        return $dt instanceof \DateTimeInterface
            ? $dt->utc()->format('Y-m-d\TH:i:s\Z')
            : (string) $dt;
    }

    private function getSettings(Model $document)
    {
        try {
            return \App\Models\Invoicing\InvoicingSettings::forTenant($document->tenant_id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Mapear uma linha (item) para a estrutura AGT v1.2. */
    private function mapLine($item, int $lineNo, bool $isCreditNote, ?string $defaultEac): array
    {
        $unitPrice    = (float) ($item->unit_price ?? 0);
        $unitPriceBase = (float) ($item->unit_price_base ?? $item->unit_price ?? 0);
        $quantity     = (float) ($item->quantity ?? 1);
        $discount     = (float) ($item->discount_amount ?? 0);
        $netLine      = (float) ($item->subtotal ?? ($unitPrice * $quantity));
        $taxAmount    = (float) ($item->tax_amount ?? 0);
        $taxRate      = (float) ($item->tax_rate ?? 14);

        $line = [
            'lineNumber'         => $lineNo,
            'productCode'        => (string) ($item->product?->sku ?? $item->product_id ?? "ITEM{$lineNo}"),
            'productDescription' => (string) ($item->description ?? $item->product_name ?? $item->product?->name ?? 'Item'),
            'quantity'           => round($quantity, 4),
            'unitOfMeasure'      => (string) ($item->unit ?? 'UN'),
            'unitPriceBase'      => round($unitPriceBase, 2),
            'unitPrice'          => round($unitPrice, 2),
        ];

        // referenceInfo (NC obrigatório)
        if ($isCreditNote && !empty($item->reference_invoice_no)) {
            $line['referenceInfo'] = [
                'reference'           => (string) $item->reference_invoice_no,
                'referenceItemLineNo' => (int) ($item->reference_item_line_no ?? $lineNo),
                'reason'              => (string) ($item->reference_reason ?? 'Rectificação'),
            ];
        }

        // debitAmount / creditAmount (mutuamente exclusivos; só um pode estar > 0)
        if ($isCreditNote || (float) ($item->debit_amount ?? 0) > 0) {
            $line['debitAmount']  = round((float) ($item->debit_amount ?? $netLine), 2);
            $line['creditAmount'] = 0;
        } else {
            $line['debitAmount']  = 0;
            $line['creditAmount'] = round((float) ($item->credit_amount ?? $netLine), 2);
        }

        // taxes (array)
        $tax = [
            'taxType'          => 'IVA',
            'taxCountryRegion' => $item->tax_country_region ?? 'AO',
            'taxCode'          => $item->tax_code ?? ($taxRate > 0 ? 'NOR' : 'ISE'),
            'taxPercentage'    => round($taxRate, 2),
            'taxContribution'  => round($taxAmount, 2),
        ];
        if (!empty($item->tax_exemption_code)) {
            $tax['taxExemptionCode'] = $item->tax_exemption_code;
        }
        if (!empty($item->tax_exemption_reason)) {
            $tax['taxExemptionReason'] = $item->tax_exemption_reason;
        }
        $line['taxes'] = [$tax];

        $line['settlementAmount'] = round((float) ($item->settlement_amount ?? $discount ?? 0), 2);

        return $line;
    }

    private function mapTotals(Model $document): array
    {
        $taxPayable = (float) ($document->tax_payable ?? $document->tax_amount ?? 0);
        $netTotal   = (float) ($document->net_total ?? $document->subtotal ?? 0);
        $grossTotal = (float) ($document->gross_total ?? $document->total ?? ($netTotal + $taxPayable));

        return [
            'taxPayable' => round($taxPayable, 2),
            'netTotal'   => round($netTotal, 2),
            'grossTotal' => round($grossTotal, 2),
        ];
    }

    /** Mapear retenções na fonte (polimórfico). */
    private function mapWithholdings(Model $document): array
    {
        // 1) IRT a partir do campo legacy (irt_amount em SalesInvoice)
        $list = [];
        if (isset($document->irt_amount) && (float) $document->irt_amount > 0) {
            $list[] = [
                'withholdingTaxType'        => 'IRT',
                'withholdingTaxDescription' => 'Imposto sobre Rendimento do Trabalho',
                'withholdingTaxAmount'      => round((float) $document->irt_amount, 2),
            ];
        }

        // 2) Tabela polimórfica invoicing_withholding_taxes
        try {
            $rows = \DB::table('invoicing_withholding_taxes')
                ->where('document_type', get_class($document))
                ->where('document_id', $document->id)
                ->get();
            foreach ($rows as $r) {
                $list[] = [
                    'withholdingTaxType'        => $r->withholding_tax_type,
                    'withholdingTaxDescription' => $r->withholding_tax_description,
                    'withholdingTaxAmount'      => round((float) $r->withholding_tax_amount, 2),
                ];
            }
        } catch (\Throwable $e) {
            // ignora se a tabela não existir
        }

        return $list;
    }
}
