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

        // DS.120 §4.5 — Validação da janela temporal de seriesYear:
        //   - Janeiro até 15 de Dezembro: apenas ano corrente
        //   - 16 a 31 de Dezembro: ano corrente ou seguinte
        $this->validateSeriesYearWindow($year);

        $documentType = $this->agtDocumentType((string) ($series->document_type ?? 'FT'));
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

        $body = $result['response'];
        $fe = is_array($body['seriesFEResult'] ?? null) ? $body['seriesFEResult'] : [];
        $seriesCode = $fe['seriesCode'] ?? null;
        $errors = collect($body['errorList'] ?? [])
            ->filter(function ($error) {
                if (is_array($error)) {
                    return filled($error['idError'] ?? null)
                        || filled($error['descriptionError'] ?? $error['errorDescription'] ?? null);
                }
                return trim((string) $error) !== '';
            })
            ->values()
            ->all();
        $semanticError = collect($errors)->map(function ($error) {
            if (is_array($error)) {
                $code = $error['idError'] ?? '';
                $description = $error['descriptionError'] ?? $error['errorDescription'] ?? '';
                return trim(($code ? "[{$code}] " : '') . $description);
            }
            return trim((string) $error);
        })->filter()->implode('; ');
        $accepted = $result['ok'] && filled($seriesCode) && $errors === [];

        if ($accepted) {
            $series->forceFill([
                'authorized_quantity' => $fe['authorizedQuantity'] ?? null,
                'first_document_no'   => $fe['firstDocumentNo'] ?? null,
                'last_document_no'    => $fe['lastDocumentNo'] ?? null,
                'agt_series_id'       => $seriesCode,
                'atcud_validation_code' => $seriesCode,
                'agt_status'          => 'active',
                // DS.120 §4.6 — série recém-registada começa como "Aberta"
                'agt_series_status'   => InvoicingSeries::AGT_STATUS_OPEN,
                'agt_series_start_ts' => now(),
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
                'error'     => $semanticError ?: $result['error'] ?: 'A AGT não devolveu o código da série.',
            ]);
        }

        $series->save();

        return [
            'ok'         => $accepted,
            'seriesCode' => $seriesCode,
            'requestID'  => $result['requestID'],
            'response'   => $result['response'],
            'error'      => $accepted
                ? null
                : ($semanticError ?: $result['error'] ?: 'A AGT não devolveu o código da série.'),
            'payload'    => $payload,
        ];
    }

    /** Devolve apenas o payload (sem enviar) — útil para certificação. */
    public function buildPayload(InvoicingSeries $series): array
    {
        return $this->builder->buildSolicitarSerie(
            $this->resolveTenantNif(),
            (int) ($series->series_year ?? date('Y')),
            $this->agtDocumentType((string) ($series->document_type ?? 'FT')),
            $series->establishment_number ?? $this->settings->agt_establishment_number ?? 'SEDE',
            $series->series_contingency_indicator ?? 'N'
        );
    }

    /**
     * Valida a janela temporal de `seriesYear` conforme DS.120 §4.5:
     *   - Jan a 15-Dez: apenas ano corrente
     *   - 16 a 31-Dez: ano corrente ou ano seguinte
     *
     * @throws \InvalidArgumentException quando o ano está fora da janela.
     */
    public function validateSeriesYearWindow(int $year, ?\DateTimeInterface $now = null): void
    {
        $now ??= now();
        $currentYear = (int) $now->format('Y');
        $month       = (int) $now->format('n');
        $day         = (int) $now->format('j');

        // Após 15-Dez (i.e. dia >= 16 em Dezembro): aceita corrente ou seguinte
        $afterCutoff = ($month === 12 && $day >= 16);

        $allowed = $afterCutoff
            ? [$currentYear, $currentYear + 1]
            : [$currentYear];

        if (!in_array($year, $allowed, true)) {
            throw new \InvalidArgumentException(sprintf(
                'seriesYear inválido: %d. DS.120 §4.5 — em %s só são aceites: %s.',
                $year,
                $afterCutoff ? 'período pós-15Dez' : 'período pré-16Dez',
                implode(', ', $allowed)
            ));
        }
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

    /** Traduz os tipos internos do ERP para o catálogo fechado da DS.120. */
    private function agtDocumentType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'invoice'     => 'FT',
            'pos'         => 'FR',
            'receipt'     => 'RC',
            'credit_note' => 'NC',
            'debit_note'  => 'ND',
            default       => strtoupper(trim($type)),
        };
    }
}
