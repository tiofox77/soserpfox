<?php

namespace App\Services\AGT;

use App\Models\Invoicing\InvoicingSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AGT v1.2 — Registar Factura(s).
 *
 * POST /api/fe/v1/factura/registar
 * Resposta: {requestID, errorList}
 *
 * Modelo assíncrono: o servidor devolve o requestID; a resposta final
 * deve ser obtida via QueryService::status($requestID).
 */
class RegisterService
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
     * Submete um array de documentos já normalizados.
     *
     * @param array $documents Lista de documentos (formato esperado por AGTPayloadBuilder::buildDocument)
     * @param ?string $taxRegistrationNumber Override do NIF (ex: NIF de certificação)
     * @return array {ok, requestID, response, error, payload, submissionUUID}
     */
    public function register(array $documents, ?string $submissionUuid = null, ?string $taxRegistrationNumber = null): array
    {
        $submissionUuid ??= (string) Str::uuid();
        $taxNumber       = $taxRegistrationNumber ?? $this->resolveTenantNif();

        $built = array_map(
            fn($d) => $this->builder->buildDocument($d, $taxNumber),
            $documents
        );

        $payload = $this->builder->buildRegistarFactura(
            $taxNumber,
            $built,
            $submissionUuid
        );

        $result = $this->http->post(
            AGTHttpClient::ENDPOINT_REGISTER,
            $payload,
            'RegistarFactura'
        );

        if (!$result['ok']) {
            Log::warning('AGT RegistarFactura falhou', [
                'submissionUUID' => $submissionUuid,
                'error'          => $result['error'],
            ]);
        }

        return [
            'ok'             => $result['ok'],
            'requestID'      => $result['requestID'],
            'response'       => $result['response'],
            'error'          => $result['error'],
            'payload'        => $payload,
            'submissionUUID' => $submissionUuid,
        ];
    }

    /** Constrói payload sem enviar (útil para certificação/inspecção). */
    public function buildPayload(array $documents, ?string $submissionUuid = null, ?string $taxRegistrationNumber = null): array
    {
        $taxNumber = $taxRegistrationNumber ?? $this->resolveTenantNif();

        $built = array_map(
            fn($d) => $this->builder->buildDocument($d, $taxNumber),
            $documents
        );

        return $this->builder->buildRegistarFactura($taxNumber, $built, $submissionUuid);
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
