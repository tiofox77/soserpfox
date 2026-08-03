<?php

namespace App\Services\AGT;

use App\Models\Invoicing\InvoicingSettings;

/**
 * AGT v1.2 — Consultar Factura.
 *
 * POST /api/fe/v1/factura/consultar
 * Resposta: {documentNo, documentStatus, document, documentStatusList, errorList}
 *
 * Também serve para polling de requestID após RegistarFactura.
 */
class QueryService
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

    /** Consulta uma factura pelo seu número. */
    public function consultByNumber(string $documentNo): array
    {
        $taxNumber = $this->resolveTenantNif();
        $payload   = $this->builder->buildConsultarFactura($taxNumber, $documentNo);

        $result = $this->http->post(
            AGTHttpClient::ENDPOINT_CONSULT,
            $payload,
            'ConsultarFactura'
        );

        $body = $result['response'];

        return [
            'ok'              => $result['ok'],
            'documentNo'      => $body['documentNo'] ?? null,
            'documentStatus'  => $body['documentStatus'] ?? null,
            'statusList'      => $body['documentStatusList'] ?? [],
            'document'        => $body['document'] ?? null,
            'response'        => $body,
            'error'           => $result['error'],
            'payload'         => $payload,
        ];
    }

    /**
     * Consulta o estado de um requestID (polling) — DS.120 §4.2.
     *
     * Usa o builder canónico `buildObterEstado` que assina correctamente
     * `{taxRegistrationNumber, requestID}` conforme spec.
     */
    public function consultByRequestId(string $requestID): array
    {
        $taxNumber = $this->resolveTenantNif();
        $envelope  = $this->builder->buildObterEstado($taxNumber, $requestID);

        $result = $this->http->post(
            AGTHttpClient::ENDPOINT_STATUS,
            $envelope,
            'ObterEstado'
        );

        $body = $result['response'] ?? [];

        return [
            'ok'                  => $result['ok'],
            'resultCode'          => $body['resultCode']         ?? null,
            'documentStatusList'  => $body['documentStatusList'] ?? [],
            'requestErrorList'    => $body['requestErrorList']   ?? [],
            'response'            => $body,
            'error'               => $result['error'],
            'payload'             => $envelope,
        ];
    }

    /** Apenas o payload (sem submeter). */
    public function buildPayload(string $documentNo): array
    {
        return $this->builder->buildConsultarFactura(
            $this->resolveTenantNif(),
            $documentNo
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
