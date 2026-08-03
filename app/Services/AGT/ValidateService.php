<?php

namespace App\Services\AGT;

use App\Models\Invoicing\InvoicingSettings;

/**
 * AGT v1.2 — Validar Documento (DS.120 §4.7).
 *
 * Operação do ADQUIRENTE para Confirmar (C) ou Rejeitar (R) um documento
 * recebido (ex: factura de fornecedor).
 *
 * POST /api/fe/v1/factura/validar
 * Resposta: {actionResultCode, documentStatusCode, errorList}
 *   actionResultCode  : C_OK | R_OK | C_NOK | R_NOK
 *   documentStatusCode: S_A | S_C | S_I | S_RG | S_RJ | S_V
 */
class ValidateService
{
    private InvoicingSettings $settings;
    private AGTPayloadBuilder $builder;
    private AGTHttpClient $http;

    public function __construct(InvoicingSettings $settings)
    {
        $this->settings = $settings;
        $this->builder  = new AGTPayloadBuilder($settings);
        $this->http     = new AGTHttpClient($settings);
    }

    /**
     * Confirma um documento recebido.
     *
     * @param string $documentNo Identificador do documento (formato SAF-T(AO))
     * @param float|null $deductibleVATPercentage Percentagem de IVA dedutível (exclusivo com $nonDeductibleAmount)
     * @param float|null $nonDeductibleAmount     Valor de IVA não dedutível (exclusivo com $deductibleVATPercentage)
     */
    public function confirm(
        string $documentNo,
        ?float $deductibleVATPercentage = null,
        ?float $nonDeductibleAmount = null
    ): array {
        return $this->execute($documentNo, 'C', $deductibleVATPercentage, $nonDeductibleAmount);
    }

    /** Rejeita um documento recebido. */
    public function reject(string $documentNo): array
    {
        return $this->execute($documentNo, 'R');
    }

    /**
     * Executa a chamada efectiva /validarDocumento.
     */
    public function execute(
        string $documentNo,
        string $action,
        ?float $deductibleVATPercentage = null,
        ?float $nonDeductibleAmount = null
    ): array {
        $taxNumber = $this->resolveTenantNif();
        $payload   = $this->builder->buildValidarDocumento(
            $taxNumber,
            $documentNo,
            $action,
            $deductibleVATPercentage,
            $nonDeductibleAmount
        );

        $result = $this->http->post(
            AGTHttpClient::ENDPOINT_VALIDATE,
            $payload,
            'ValidarDocumento'
        );

        $body = $result['response'] ?? [];

        return [
            'ok'                  => $result['ok'],
            'actionResultCode'    => $body['actionResultCode']   ?? null,
            'documentStatusCode'  => $body['documentStatusCode'] ?? null,
            'errorList'           => $body['errorList']           ?? [],
            'response'            => $body,
            'error'               => $result['error'],
            'payload'             => $payload,
        ];
    }

    /** Apenas devolve o payload sem submeter (útil para certificação). */
    public function buildPayload(
        string $documentNo,
        string $action,
        ?float $deductibleVATPercentage = null,
        ?float $nonDeductibleAmount = null
    ): array {
        return $this->builder->buildValidarDocumento(
            $this->resolveTenantNif(),
            $documentNo,
            $action,
            $deductibleVATPercentage,
            $nonDeductibleAmount
        );
    }

    private function resolveTenantNif(): string
    {
        $tenant = $this->settings->tenant ?? null;
        return (string) (
            $tenant?->nif
            ?? $tenant?->tax_id
            ?? $this->settings->company_nif
            ?? ''
        );
    }
}
