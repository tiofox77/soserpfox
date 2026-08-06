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
    public const SANDBOX_URL    = 'https://sifphml.minfin.gov.ao/sigt/fe/v1';
    public const PRODUCTION_URL = 'https://sifp.minfin.gov.ao/sigt/fe/v1';

    /** DS.120 §4.8 — Tamanho máximo de mensagem (750 KB). */
    public const MAX_PAYLOAD_BYTES = 750 * 1024;

    public const ENDPOINT_SERIES        = '/solicitarSerie';
    public const ENDPOINT_REGISTER      = '/registarFactura';
    public const ENDPOINT_CONSULT       = '/consultarFactura';
    public const ENDPOINT_STATUS        = '/obterEstado';
    public const ENDPOINT_LIST_INVOICES = '/listarFacturas';
    public const ENDPOINT_LIST_SERIES   = '/listarSeries';
    public const ENDPOINT_VALIDATE      = '/validarDocumento';

    private InvoicingSettings $settings;
    private int $tenantId;
    private string $baseUrl;
    private string $environment;

    public function __construct(InvoicingSettings $settings)
    {
        $this->settings    = $settings;
        $this->tenantId    = $settings->tenant_id;
        $this->environment = $settings->agt_environment ?? 'sandbox';
        $this->baseUrl = $this->environment === 'production'
            ? self::PRODUCTION_URL
            : self::SANDBOX_URL;
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

        // DS.120 §4.8: validação prévia de tamanho máx da mensagem.
        $jsonSize = strlen((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($jsonSize > self::MAX_PAYLOAD_BYTES) {
            return [
                'ok'        => false,
                'status'    => 0,
                'response'  => [],
                'requestID' => null,
                'error'     => sprintf('Payload excede 750KB (%d bytes)', $jsonSize),
                'elapsed'   => 0,
                'log_id'    => null,
            ];
        }

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
        // Credenciais do PRODUTOR para ESTE ambiente. A AGT entrega conjuntos
        // diferentes para homologação e produção; usar o de testes contra a API
        // real dá 401 e nenhum documento passa.
        $credenciais = AGTProducerStore::credenciais($this->environment);

        return Http::timeout(60)
            // O DNS da AGT é intermitente: medido, resolve em 7 ms quase sempre
            // e de vez em quando leva 11 s. Com o limite de 10 s do Laravel,
            // isso dava `cURL error 28: Resolving timed out` e o documento
            // ficava por comunicar.
            ->connectTimeout(30)
            // Duas tentativas extra, só em falha de LIGAÇÃO. Uma resposta da
            // AGT — mesmo a recusar — nunca se repete: seria reenviar um
            // documento que ela já viu.
            ->retry(3, 1000, function ($excepcao) {
                return $excepcao instanceof \Illuminate\Http\Client\ConnectionException;
            }, throw: false)
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($credenciais['username'], $credenciais['password'])
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
