<?php

namespace App\Services\AGT;

use App\Models\AGT\AGTCommunicationLog;
use App\Models\Invoicing\InvoicingSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * AGT v1.2 — HTTP client.
 *
 * - Sem OAuth: todas as chamadas são autenticadas via jwsSignature no body.
 * - Modelo assíncrono: o servidor devolve {requestID, errorList}.
 * - Endpoints (oficial): https://quiosqueagt.minfin.gov.ao/...
 *
 * Override do baseUrl/endpoints possível via InvoicingSettings::agt_api_base_url.
 */
class AGTHttpClient
{
    public const SANDBOX_URL    = 'https://quiosqueagt.hml.minfin.gov.ao';
    public const PRODUCTION_URL = 'https://quiosqueagt.minfin.gov.ao';

    public const ENDPOINT_SERIES   = '/api/fe/v1/series/solicitar';
    public const ENDPOINT_REGISTER = '/api/fe/v1/factura/registar';
    public const ENDPOINT_CONSULT  = '/api/fe/v1/factura/consultar';
    public const ENDPOINT_STATUS   = '/api/fe/v1/factura/estado';

    private InvoicingSettings $settings;
    private int $tenantId;
    private string $baseUrl;
    private string $environment;

    public function __construct(InvoicingSettings $settings)
    {
        $this->settings    = $settings;
        $this->tenantId    = $settings->tenant_id;
        $this->environment = $settings->agt_environment ?? 'sandbox';
        $this->baseUrl     = rtrim(
            $settings->agt_api_base_url
                ?? ($this->environment === 'production' ? self::PRODUCTION_URL : self::SANDBOX_URL),
            '/'
        );
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Envia POST para um endpoint AGT, registando log e devolvendo array com:
     *  ['ok' => bool, 'status' => int, 'response' => array, 'error' => ?string, 'requestID' => ?string]
     */
    public function post(string $endpoint, array $payload, string $serviceName): array
    {
        $url       = $this->baseUrl . $endpoint;
        $startTime = microtime(true);
        $log = null;

        try {
            $response = $this->client()->post($url, $payload);
            $body     = $response->json() ?? [];
            $status   = $response->status();
            $elapsed  = (int) ((microtime(true) - $startTime) * 1000);

            $errorList = $body['errorList'] ?? [];
            $hasErrors = !empty($errorList) && $errorList !== [''];
            $success   = $response->successful() && !$hasErrors;
            $errorMsg  = $this->extractError($body);

            $log = AGTCommunicationLog::log(
                $this->tenantId,
                $serviceName,
                'POST',
                $endpoint,
                ['Content-Type' => 'application/json'],
                $payload,
                $status,
                $response->headers(),
                $body,
                $elapsed,
                $success,
                $errorMsg
            );

            return [
                'ok'        => $success,
                'status'    => $status,
                'response'  => $body,
                'requestID' => $body['requestID'] ?? null,
                'error'     => $errorMsg,
                'elapsed'   => $elapsed,
                'log_id'    => $log?->id,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'        => false,
                'status'    => 0,
                'response'  => [],
                'requestID' => null,
                'error'     => $e->getMessage(),
                'elapsed'   => (int) ((microtime(true) - $startTime) * 1000),
                'log_id'    => null,
            ];
        }
    }

    private function client(): PendingRequest
    {
        return Http::timeout(60)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-Tenant-Id' => (string) $this->tenantId,
            ]);
    }

    private function extractError(array $body): ?string
    {
        $errors = $body['errorList'] ?? null;
        if (empty($errors) || $errors === ['']) {
            return null;
        }
        if (is_array($errors) && isset($errors[0]['descriptionError'])) {
            return collect($errors)->pluck('descriptionError')->implode('; ');
        }
        return is_array($errors) ? implode('; ', array_filter($errors)) : (string) $errors;
    }
}
