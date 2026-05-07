<?php

namespace App\Services\AGT;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AGT v1.2 — Solicitar Criação de Série.
 *
 * POST /api/fe/v1/series/solicitar
 * Resposta: {resultCode, errorList, seriesFEResult: {seriesCode, authorizedQuantity,
 *            firstDocumentNo, lastDocumentNo}}
 */
class SeriesService
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
     * Solicita uma nova série à AGT e (em sucesso) actualiza o registo local.
     *
     * @return array {ok, requestID?, seriesCode?, response, error}
     */
    public function request(
        InvoicingSeries $series,
        ?int $year = null,
        ?string $establishmentNumber = null,
        string $contingency = 'N'
    ): array {
        $year ??= (int) ($series->series_year ?? date('Y'));
        $establishmentNumber ??= $series->establishment_number
            ?? $this->settings->agt_establishment_number ?? 'SEDE';

        $documentType = $series->document_type ?? 'FT';
        $taxNumber    = $this->resolveTenantNif();
        $uuid         = (string) Str::uuid();

        $payload = $this->builder->buildSolicitarSerie(
            $taxNumber,
            $year,
            $documentType,
            $establishmentNumber,
            $contingency,
            $uuid
        );

        $result = $this->http->post(
            AGTHttpClient::ENDPOINT_SERIES,
            $payload,
            'SolicitarSerie'
        );

        // Persist UUID always
        $series->forceFill([
            'submission_uuid' => $uuid,
            'series_year' => $year,
            'establishment_number' => $establishmentNumber,
            'series_contingency_indicator' => $contingency,
        ]);

        if ($result['ok']) {
            $body = $result['response'];
            $fe   = $body['seriesFEResult'] ?? [];
            $series->forceFill([
                'series_code'         => $fe['seriesCode'] ?? null,
                'authorized_quantity' => $fe['authorizedQuantity'] ?? null,
                'first_document_no'   => $fe['firstDocumentNo'] ?? null,
                'last_document_no'    => $fe['lastDocumentNo'] ?? null,
                'agt_series_id'       => $fe['seriesCode'] ?? null,
                'agt_status'          => 'active',
                'agt_registered_at'   => now(),
                'agt_response'        => $body,
            ]);
        } else {
            $series->forceFill([
                'agt_status'   => 'pending',
                'agt_response' => $result['response'] ?: ['error' => $result['error']],
            ]);
            Log::warning('AGT SolicitarSerie falhou', [
                'series_id' => $series->id,
                'error'     => $result['error'],
            ]);
        }

        $series->save();

        return [
            'ok'         => $result['ok'],
            'seriesCode' => $series->series_code,
            'requestID'  => $result['requestID'],
            'response'   => $result['response'],
            'error'      => $result['error'],
            'payload'    => $payload,
        ];
    }

    /** Devolve apenas o payload (sem enviar) — útil para certificação. */
    public function buildPayload(InvoicingSeries $series): array
    {
        return $this->builder->buildSolicitarSerie(
            $this->resolveTenantNif(),
            (int) ($series->series_year ?? date('Y')),
            $series->document_type ?? 'FT',
            $series->establishment_number ?? $this->settings->agt_establishment_number ?? 'SEDE',
            $series->series_contingency_indicator ?? 'N'
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
