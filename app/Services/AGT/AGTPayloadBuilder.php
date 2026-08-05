<?php

namespace App\Services\AGT;

use App\Models\Invoicing\InvoicingSettings;
use Illuminate\Support\Facades\Storage;
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
    /**
     * Default schema version. Pode ser sobrescrita via InvoicingSettings::agt_schema_version.
     * NB: DS.120 v1.1 (Nov 2025) refere "1.0" — confirmar com AGT a versão activa.
     */
    public const SCHEMA_VERSION = '1.2';

    /** Limite máximo de documentos por submissão (DS.120 §4.1). */
    public const MAX_ENTRIES_PER_SUBMISSION = 30;

    /** Moeda por defeito (DS.120 — Angola). */
    public const DEFAULT_CURRENCY = 'AOA';

    private InvoicingSettings $settings;
    private JwsSigner $signer;
    private JwsSigner $softwareSigner;

    public function __construct(InvoicingSettings $settings, ?JwsSigner $signer = null)
    {
        $this->settings = $settings;

        // Assinatura do PRODUTOR com a chave do ambiente activo da empresa.
        // Antes era sempre `saft/private_key.pem`: uma empresa em produção
        // assinava com a chave de produtor de homologação e a AGT recusava.
        $ambienteProdutor = AGTProducerStore::normalizar($settings->agt_environment ?? null);

        $this->softwareSigner = new JwsSigner(
            Storage::disk('local')->exists(AGTProducerStore::privateKeyPath($ambienteProdutor))
                ? Storage::disk('local')->get(AGTProducerStore::privateKeyPath($ambienteProdutor))
                : null,
            Storage::disk('local')->exists(AGTProducerStore::publicKeyPath($ambienteProdutor))
                ? Storage::disk('local')->get(AGTProducerStore::publicKeyPath($ambienteProdutor))
                : null
        );
        if ($signer) {
            $this->signer = $signer;
            return;
        }

        // Chaves do AMBIENTE activo — sandbox e produção têm pares diferentes.
        $priv = AGTKeyStore::privateKeyPath((int) $settings->tenant_id);
        $pub  = AGTKeyStore::publicKeyPath((int) $settings->tenant_id);
        $private = Storage::disk('local')->get($priv);
        $public = Storage::disk('local')->exists($pub)
            ? Storage::disk('local')->get($pub)
            : null;
        $this->signer = new JwsSigner($private, $public);
    }

    /** Resolve schemaVersion (settings override > constante). */
    private function schemaVersion(): string
    {
        return (string) ($this->settings->agt_schema_version ?? self::SCHEMA_VERSION);
    }

    // ============================================================
    // BLOCOS COMUNS
    // ============================================================

    /** softwareInfo + jwsSoftwareSignature */
    public function softwareInfo(): array
    {
        $detail = [
            'productId'                => softwareSetting('invoicing', 'saft_product_id', $this->settings->agt_product_id ?? 'SOS ERP'),
            'productVersion'           => softwareSetting('invoicing', 'saft_version', $this->settings->agt_product_version ?? '1.0'),
            'softwareValidationNumber' => softwareSetting('invoicing', 'saft_software_cert', $this->settings->agt_software_validation_number ?? 'C_000'),
            'signatureVersion'         => 1,
        ];

        return [
            'softwareInfoDetail'    => $detail,
            'jwsSoftwareSignature'  => $this->softwareSigner->signSoftware($detail),
        ];
    }

    /** Envelope-base comum (schemaVersion, UUID, NIF, timestamp, softwareInfo) */
    private function envelopeBase(string $taxRegistrationNumber, ?string $submissionUuid = null): array
    {
        return [
            'schemaVersion'         => $this->schemaVersion(),
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
        $count = count($documents);
        if ($count < 1 || $count > self::MAX_ENTRIES_PER_SUBMISSION) {
            throw new \InvalidArgumentException(sprintf(
                'numberOfEntries inválido: %d (DS.120 §4.1 exige 1..%d).',
                $count,
                self::MAX_ENTRIES_PER_SUBMISSION
            ));
        }

        $envelope = $this->envelopeBase($taxRegistrationNumber, $submissionUuid);

        // DS.120 §4.1: numberOfEntries é String.
        $envelope['numberOfEntries'] = (string) $count;
        $envelope['documents']       = $documents;

        // jwsSignature do request
        $envelope['jwsSignature'] = $this->signer->signRequest([
            'taxRegistrationNumber' => $taxRegistrationNumber,
            'numberOfEntries'       => (string) $count,
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
        $status = $data['documentStatus'] ?? 'N';
        $doc = [
            'documentNo'              => $data['documentNo'],
            'documentStatus'          => $status,
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

        // DS.120 §4.1: documentCancelReason obrigatório se status=A (I=Ident.incorrecta, N=Não enviado).
        if ($status === 'A') {
            $doc['documentCancelReason'] = $data['documentCancelReason'] ?? 'I';
        }

        // DS.120 §4.1: rejectedDocumentNo obrigatório se status=C (correcção).
        if ($status === 'C' && !empty($data['rejectedDocumentNo'])) {
            $doc['rejectedDocumentNo'] = (string) $data['rejectedDocumentNo'];
        }

        // DS.120 §4.1: paymentReceipt obrigatório para AR, RC, RG.
        if (in_array($data['documentType'] ?? '', ['AR', 'RC', 'RG'], true) && !empty($data['paymentReceipt'])) {
            $doc['paymentReceipt'] = $this->normalizePaymentReceipt($data['paymentReceipt']);
        }

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
                // DS.120 §4.1: taxContribution arredondado por EXCESSO ao cêntimo.
                'taxContribution'  => self::ceilCents($t['taxContribution'] ?? 0),
            ];

            // taxPercentage só quando o imposto o declara. Forçá-lo aqui a 0
            // reintroduzia-o no Imposto de Selo, que se declara por montante —
            // e a AGT recusava a combinação. Impostos ad valorem (IVA, IEC)
            // continuam a levá-lo.
            if (array_key_exists('taxPercentage', $t)) {
                $tax['taxPercentage'] = $this->money($t['taxPercentage'], 2);
            }
            if (!empty($t['taxExemptionCode'])) {
                $tax['taxExemptionCode'] = $t['taxExemptionCode'];
            }
            if (!empty($t['taxExemptionReason'])) {
                $tax['taxExemptionReason'] = $t['taxExemptionReason'];
            }

            // O Imposto de Selo exige taxAmount: a AGT recusava a combinação
            // (taxType IS, taxCode NOR, taxAmount null, taxPercentage 1). Este
            // remapeamento descartava o campo que o LineTax já produzia.
            if (isset($t['taxAmount'])) {
                $tax['taxAmount'] = $this->money($t['taxAmount'], 2);
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
        $out = [
            'taxPayable' => $this->money($totals['taxPayable'] ?? 0),
            'netTotal'   => $this->money($totals['netTotal'] ?? 0),
            'grossTotal' => $this->money($totals['grossTotal'] ?? 0),
        ];
        $currency = strtoupper((string) ($totals['currency'] ?? self::DEFAULT_CURRENCY));
        if ($currency !== self::DEFAULT_CURRENCY) {
            $out['currency'] = [
                'currencyCode' => $currency,
                'currencyAmount' => $this->money($totals['currencyAmount'] ?? $totals['grossTotal'] ?? 0),
                'exchangeRate' => $this->money($totals['exchangeRate'] ?? 1, 4),
            ];
        }
        return $out;
    }

    /** Normaliza o objecto paymentReceipt (AR/RC/RG). */
    private function normalizePaymentReceipt(array $pr): array
    {
        $out = [
            'paymentMechanism' => (string) ($pr['paymentMechanism'] ?? 'NU'),
            'paymentAmount'    => $this->money($pr['paymentAmount'] ?? 0),
            'paymentDate'      => (string) ($pr['paymentDate'] ?? now()->format('Y-m-d')),
        ];
        if (!empty($pr['sourceDocuments']) && is_array($pr['sourceDocuments'])) {
            $out['sourceDocuments'] = array_map(function ($d, $index) {
                $source = is_array($d['sourceDocumentID'] ?? null)
                    ? $d['sourceDocumentID']
                    : ['originatingON' => (string) ($d['sourceDocumentID'] ?? ''),
                       'documentDate' => (string) ($d['invoiceDate'] ?? '')];
                $row = [
                    'lineNo' => (int) ($d['lineNo'] ?? ($index + 1)),
                    'sourceDocumentID' => [
                        'originatingON' => (string) ($source['originatingON'] ?? $source['OriginatingON'] ?? ''),
                        'documentDate' => (string) ($source['documentDate'] ?? ''),
                    ],
                ];
                if (isset($d['debitAmount'])) {
                    $row['debitAmount'] = $this->money($d['debitAmount']);
                } else {
                    $row['creditAmount'] = $this->money($d['creditAmount'] ?? 0);
                }
                return $row;
            }, $pr['sourceDocuments'], array_keys($pr['sourceDocuments']));
        }
        return $out;
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
    // 4) OBTER ESTADO  (DS.120 §4.2)
    // ============================================================

    public function buildObterEstado(
        string $taxRegistrationNumber,
        string $requestID,
        ?string $submissionUuid = null
    ): array {
        $envelope = $this->envelopeBase($taxRegistrationNumber, $submissionUuid);
        $envelope['requestID']    = $requestID;
        $envelope['jwsSignature'] = $this->signer->signRequest([
            'taxRegistrationNumber' => $taxRegistrationNumber,
            'requestID'             => $requestID,
        ]);
        return $envelope;
    }

    // ============================================================
    // 5) LISTAR FACTURAS  (DS.120 §4.3)
    // ============================================================

    public function buildListarFacturas(
        string $taxRegistrationNumber,
        string $queryStartDate,
        string $queryEndDate,
        ?string $submissionUuid = null
    ): array {
        $envelope = $this->envelopeBase($taxRegistrationNumber, $submissionUuid);
        $envelope['queryStartDate'] = $queryStartDate;
        $envelope['queryEndDate']   = $queryEndDate;
        $envelope['jwsSignature']   = $this->signer->signRequest([
            'taxRegistrationNumber' => $taxRegistrationNumber,
            'queryStartDate'        => $queryStartDate,
            'queryEndDate'          => $queryEndDate,
        ]);
        return $envelope;
    }

    // ============================================================
    // 6) LISTAR SÉRIES  (DS.120 §4.6)
    // ============================================================

    public function buildListarSeries(
        string $taxRegistrationNumber,
        string $establishmentNumber,
        array $filters = [],
        ?string $submissionUuid = null
    ): array {
        $envelope = $this->envelopeBase($taxRegistrationNumber, $submissionUuid);
        $envelope['establishmentNumber'] = $establishmentNumber;

        foreach (['seriesCode', 'seriesYear', 'seriesStatus', 'documentType'] as $f) {
            if (!empty($filters[$f])) {
                $envelope[$f] = (string) $filters[$f];
            }
        }

        $envelope['jwsSignature'] = $this->signer->signRequest([
            'taxRegistrationNumber' => $taxRegistrationNumber,
            'submissionTimeStamp'   => $envelope['submissionTimeStamp'],
            'establishmentNumber'   => $establishmentNumber,
        ]);
        return $envelope;
    }

    // ============================================================
    // 7) VALIDAR DOCUMENTO  (DS.120 §4.7) — Adquirente
    // ============================================================

    /**
     * @param string $action 'C' = Confirmação, 'R' = Rejeição
     * @param float|null $deductibleVATPercentage Exclusivo com $nonDeductibleAmount
     * @param float|null $nonDeductibleAmount     Exclusivo com $deductibleVATPercentage
     */
    public function buildValidarDocumento(
        string $taxRegistrationNumber,
        string $documentNo,
        string $action,
        ?float $deductibleVATPercentage = null,
        ?float $nonDeductibleAmount = null,
        ?string $submissionUuid = null
    ): array {
        $action = strtoupper($action);
        if (!in_array($action, ['C', 'R'], true)) {
            throw new \InvalidArgumentException("action deve ser 'C' (Confirmação) ou 'R' (Rejeição)");
        }
        if ($deductibleVATPercentage !== null && $nonDeductibleAmount !== null) {
            throw new \InvalidArgumentException('deductibleVATPercentage e nonDeductibleAmount são mutuamente exclusivos');
        }

        $envelope = $this->envelopeBase($taxRegistrationNumber, $submissionUuid);
        $envelope['documentNo'] = $documentNo;
        $envelope['action']     = $action;

        if ($deductibleVATPercentage !== null) {
            $envelope['deductibleVATPercentage'] = $this->money($deductibleVATPercentage, 2);
        }
        if ($nonDeductibleAmount !== null) {
            $envelope['nonDeductibleAmount'] = $this->money($nonDeductibleAmount);
        }

        $envelope['jwsSignature'] = $this->signer->signRequest([
            'taxRegistrationNumber' => $taxRegistrationNumber,
            'documentNo'            => $documentNo,
            'action'                => $action,
        ]);

        return $envelope;
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * Arredondamento financeiro consistente.
     * Para taxContribution o DS.120 §4.1 exige arredondamento por excesso (CEIL) ao cêntimo.
     */
    private function money(float|int|string $v, int $decimals = 2): float
    {
        return round((float) $v, $decimals);
    }

    /**
     * Arredondamento por EXCESSO ao cêntimo (DS.120 §4.1 — taxContribution).
     * Ex: 12.341 → 12.35
     */
    public static function ceilCents(float|int|string $v): float
    {
        $factor = 100.0;
        return ceil(((float) $v) * $factor) / $factor;
    }

    public function getSigner(): JwsSigner
    {
        return $this->signer;
    }
}
