<?php

namespace App\Services\AGT;

use App\Models\Invoicing\InvoicingSettings;
use Illuminate\Support\Str;

/**
 * AGT v1.2 — Construtor de Payloads
 *
 * Constrói envelopes JSON conforme spec oficial:
 *  - SolicitarSerie
 *  - RegistarFactura
 *  - ConsultarFactura
 *
 * Todos os campos seguem camelCase, datas em ISO 8601, decimais com ponto.
 */
class AGTPayloadBuilder
{
    public const SCHEMA_VERSION = '1.2';

    private InvoicingSettings $settings;
    private JwsSigner $signer;

    public function __construct(InvoicingSettings $settings, ?JwsSigner $signer = null)
    {
        $this->settings = $settings;
        $this->signer   = $signer ?? new JwsSigner();
    }

    // ============================================================
    // BLOCOS COMUNS
    // ============================================================

    /** softwareInfo + jwsSoftwareSignature */
    public function softwareInfo(): array
    {
        $detail = [
            'productId'                => $this->settings->agt_product_id ?? 'SOS ERP',
            'productVersion'           => $this->settings->agt_product_version ?? '1.0',
            'softwareValidationNumber' => $this->settings->agt_software_validation_number ?? 'C_000',
        ];

        return [
            'softwareInfoDetail'    => $detail,
            'jwsSoftwareSignature'  => $this->signer->signSoftware($detail),
        ];
    }

    /** Envelope-base comum (schemaVersion, UUID, NIF, timestamp, softwareInfo) */
    private function envelopeBase(string $taxRegistrationNumber, ?string $submissionUuid = null): array
    {
        return [
            'schemaVersion'         => self::SCHEMA_VERSION,
            'submissionUUID'        => $submissionUuid ?? (string) Str::uuid(),
            'taxRegistrationNumber' => $taxRegistrationNumber,
            'submissionTimeStamp'   => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'softwareInfo'          => $this->softwareInfo(),
        ];
    }

    // ============================================================
    // 1) SOLICITAR SÉRIE
    // ============================================================

    /**
     * Constrói payload para POST /SolicitarSerie.
     *
     * @param string $taxRegistrationNumber NIF do contribuinte (10 dig)
     * @param int|string $seriesYear        Ano (ex: 2025)
     * @param string $documentType          FT, FR, NC, ND, RC, LD, etc.
     * @param string $establishmentNumber   Código do estabelecimento (ex: SEDE)
     * @param string $contingency           N (normal) ou S (contingência)
     */
    public function buildSolicitarSerie(
        string $taxRegistrationNumber,
        int|string $seriesYear,
        string $documentType,
        string $establishmentNumber = 'SEDE',
        string $contingency = 'N',
        ?string $submissionUuid = null
    ): array {
        $envelope = $this->envelopeBase($taxRegistrationNumber, $submissionUuid);

        $envelope['seriesYear']                 = (string) $seriesYear;
        $envelope['documentType']               = $documentType;
        $envelope['establishmentNumber']        = $establishmentNumber;
        $envelope['seriesContingencyIndicator'] = $contingency;

        // jwsSignature do request (assina campos canónicos)
        $envelope['jwsSignature'] = $this->signer->signRequest([
            'taxRegistrationNumber'      => $taxRegistrationNumber,
            'seriesYear'                 => (string) $seriesYear,
            'documentType'               => $documentType,
            'establishmentNumber'        => $establishmentNumber,
            'seriesContingencyIndicator' => $contingency,
        ]);

        return $envelope;
    }

    // ============================================================
    // 2) REGISTAR FACTURA(S)
    // ============================================================

    /**
     * Constrói payload para POST /RegistarFactura.
     *
     * @param string $taxRegistrationNumber NIF emissor
     * @param array  $documents Lista de documentos (cada um já formatado por buildDocument)
     */
    public function buildRegistarFactura(
        string $taxRegistrationNumber,
        array $documents,
        ?string $submissionUuid = null
    ): array {
        $envelope = $this->envelopeBase($taxRegistrationNumber, $submissionUuid);

        $envelope['numberOfEntries'] = count($documents);
        $envelope['documents']       = $documents;

        // jwsSignature do request
        $envelope['jwsSignature'] = $this->signer->signRequest([
            'taxRegistrationNumber' => $taxRegistrationNumber,
            'numberOfEntries'       => (string) count($documents),
            'submissionTimeStamp'   => $envelope['submissionTimeStamp'],
        ]);

        return $envelope;
    }

    /**
     * Constrói um único documento (factura/factura-recibo/NC/ND) conforme AGT v1.2.
     *
     * @param array $data Dados do documento (já normalizados — ver shape abaixo)
     * @param string $issuerNif NIF do emissor (para o jwsDocumentSignature)
     *
     * Shape esperado em $data:
     *   documentNo, documentType, documentDate (Y-m-d), systemEntryDate (ISO),
     *   eacCode, customerTaxID, customerCountry, companyName,
     *   lines[] => {lineNumber, productCode, productDescription, quantity, unitOfMeasure,
     *               unitPrice, unitPriceBase, debitAmount, creditAmount, settlementAmount,
     *               taxes[] => {taxType, taxCountryRegion, taxCode, taxPercentage, taxContribution,
     *                           taxExemptionCode?, taxExemptionReason?}},
     *   documentTotals => {taxPayable, netTotal, grossTotal},
     *   withholdingTaxList[] => {withholdingTaxType, withholdingTaxDescription, withholdingTaxAmount}
     */
    public function buildDocument(array $data, string $issuerNif): array
    {
        $doc = [
            'documentNo'              => $data['documentNo'],
            'documentStatus'          => $data['documentStatus'] ?? 'N',
            'documentDate'            => $data['documentDate'],
            'documentType'            => $data['documentType'],
            'eacCode'                 => $data['eacCode'] ?? null,
            'systemEntryDate'         => $data['systemEntryDate'],
            'customerTaxID'           => $data['customerTaxID'],
            'customerCountry'         => $data['customerCountry'] ?? 'AO',
            'companyName'             => $data['companyName'],
            'lines'                   => array_map([$this, 'normalizeLine'], $data['lines'] ?? []),
            'documentTotals'          => $this->normalizeTotals($data['documentTotals'] ?? []),
        ];

        if (!empty($data['withholdingTaxList'])) {
            $doc['withholdingTaxList'] = array_map(
                fn($w) => [
                    'withholdingTaxType'        => $w['withholdingTaxType'],
                    'withholdingTaxDescription' => $w['withholdingTaxDescription'] ?? '',
                    'withholdingTaxAmount'      => $this->money($w['withholdingTaxAmount'] ?? 0),
                ],
                $data['withholdingTaxList']
            );
        }

        // jwsDocumentSignature
        $doc['jwsDocumentSignature'] = $this->signer->signDocument($doc, $issuerNif);

        return $doc;
    }

    private function normalizeLine(array $line): array
    {
        $taxes = array_map(function ($t) {
            $tax = [
                'taxType'          => $t['taxType'] ?? 'IVA',
                'taxCountryRegion' => $t['taxCountryRegion'] ?? 'AO',
                'taxCode'          => $t['taxCode'] ?? 'NOR',
                'taxPercentage'    => $this->money($t['taxPercentage'] ?? 0, 2),
                'taxContribution'  => $this->money($t['taxContribution'] ?? 0),
            ];
            if (!empty($t['taxExemptionCode'])) {
                $tax['taxExemptionCode'] = $t['taxExemptionCode'];
            }
            if (!empty($t['taxExemptionReason'])) {
                $tax['taxExemptionReason'] = $t['taxExemptionReason'];
            }
            return $tax;
        }, $line['taxes'] ?? []);

        $out = [
            'lineNumber'         => (int) ($line['lineNumber'] ?? 1),
            'productCode'        => (string) ($line['productCode'] ?? ''),
            'productDescription' => (string) ($line['productDescription'] ?? ''),
            'quantity'           => $this->money($line['quantity'] ?? 0, 4),
            'unitOfMeasure'      => (string) ($line['unitOfMeasure'] ?? 'UN'),
            'unitPriceBase'      => $this->money($line['unitPriceBase'] ?? $line['unitPrice'] ?? 0),
            'unitPrice'          => $this->money($line['unitPrice'] ?? 0),
        ];

        // referenceInfo (object, obrigatório em NC) — nível linha, entre unitPrice e debitAmount.
        // Schema AGT exige: reference (str), reason (str opc), referenceItemLineNo (int, linha do doc original)
        if (!empty($line['referenceInfo']) && is_array($line['referenceInfo'])) {
            $r = $line['referenceInfo'];
            $ref = [
                'reference'           => (string) ($r['reference'] ?? ''),
                'referenceItemLineNo' => (int) ($r['referenceItemLineNo'] ?? $line['lineNumber'] ?? 1),
            ];
            if (!empty($r['reason'])) {
                $ref['reason'] = (string) $r['reason'];
            }
            $out['referenceInfo'] = $ref;
        }

        $out['debitAmount']      = $this->money($line['debitAmount'] ?? 0);
        $out['creditAmount']     = $this->money($line['creditAmount'] ?? 0);
        $out['taxes']            = $taxes;
        $out['settlementAmount'] = $this->money($line['settlementAmount'] ?? 0);

        return $out;
    }

    private function normalizeTotals(array $totals): array
    {
        return [
            'taxPayable' => $this->money($totals['taxPayable'] ?? 0),
            'netTotal'   => $this->money($totals['netTotal'] ?? 0),
            'grossTotal' => $this->money($totals['grossTotal'] ?? 0),
        ];
    }

    // ============================================================
    // 3) CONSULTAR FACTURA
    // ============================================================

    public function buildConsultarFactura(
        string $taxRegistrationNumber,
        string $documentNo,
        ?string $submissionUuid = null
    ): array {
        $envelope = $this->envelopeBase($taxRegistrationNumber, $submissionUuid);

        $envelope['invoiceNo']    = $documentNo;
        $envelope['jwsSignature'] = $this->signer->signRequest([
            'taxRegistrationNumber' => $taxRegistrationNumber,
            'documentNo'            => $documentNo,
        ]);

        return $envelope;
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /** Arredondamento financeiro consistente. */
    private function money(float|int|string $v, int $decimals = 2): float
    {
        return round((float) $v, $decimals);
    }

    public function getSigner(): JwsSigner
    {
        return $this->signer;
    }
}
