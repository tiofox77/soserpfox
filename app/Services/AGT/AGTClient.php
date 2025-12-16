<?php

namespace App\Services\AGT;

use App\Models\AGT\AGTSubmission;
use App\Models\AGT\AGTCommunicationLog;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\InvoicingSeries;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Cliente API AGT Angola
 * Decreto Presidencial n.º 71/25
 * 
 * Serviços implementados:
 * - SolicitarSerie: Criar nova série na AGT
 * - RegistarFactura: Submeter documento para validação
 * - ObterEstado: Verificar estado de documento
 * - ConsultarFactura: Obter documento validado
 * - ListarFacturas: Listar documentos do período
 */
class AGTClient
{
    private ?InvoicingSettings $settings = null;
    private int $tenantId;
    private string $environment;
    private string $baseUrl;
    private ?string $accessToken = null;

    // URLs base por ambiente
    const SANDBOX_URL = 'https://api-sandbox.agt.minfin.gov.ao/v1';
    const PRODUCTION_URL = 'https://api.agt.minfin.gov.ao/v1';

    // Endpoints
    const ENDPOINT_TOKEN = '/oauth/token';
    const ENDPOINT_SERIES = '/series';
    const ENDPOINT_INVOICES = '/invoices';
    const ENDPOINT_STATUS = '/invoices/{reference}/status';

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->loadSettings();
    }

    // =========================================
    // CONFIGURAÇÃO
    // =========================================

    private function loadSettings(): void
    {
        $this->settings = InvoicingSettings::where('tenant_id', $this->tenantId)->first();
        
        if (!$this->settings) {
            throw new \Exception('Configurações de faturação não encontradas para o tenant');
        }

        $this->environment = $this->settings->agt_environment ?? 'sandbox';
        $this->baseUrl = $this->settings->agt_api_base_url 
            ?? ($this->environment === 'production' ? self::PRODUCTION_URL : self::SANDBOX_URL);
    }

    public function isConfigured(): bool
    {
        return $this->settings 
            && !empty($this->settings->agt_client_id) 
            && !empty($this->settings->agt_client_secret);
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    // =========================================
    // AUTENTICAÇÃO OAUTH 2.0
    // =========================================

    public function authenticate(): bool
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Credenciais AGT não configuradas');
        }

        // Verificar se token ainda é válido
        if ($this->hasValidToken()) {
            $this->accessToken = $this->decryptField($this->settings->agt_access_token);
            return true;
        }

        $startTime = microtime(true);
        
        try {
            $clientId = $this->decryptField($this->settings->agt_client_id);
            $clientSecret = $this->decryptField($this->settings->agt_client_secret);

            $response = Http::asForm()
                ->timeout(30)
                ->post($this->baseUrl . self::ENDPOINT_TOKEN, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => 'invoicing',
                ]);

            $responseTime = (microtime(true) - $startTime) * 1000;

            // Log da comunicação
            AGTCommunicationLog::log(
                $this->tenantId,
                'OAuth',
                'POST',
                self::ENDPOINT_TOKEN,
                ['Content-Type' => 'application/x-www-form-urlencoded'],
                ['grant_type' => 'client_credentials', 'client_id' => '***'],
                $response->status(),
                $response->headers(),
                $response->json(),
                $responseTime,
                $response->successful()
            );

            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['access_token'];
                
                // Guardar token encriptado
                $this->settings->update([
                    'agt_access_token' => Crypt::encryptString($this->accessToken),
                    'agt_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
                ]);

                return true;
            }

            Log::error('AGT OAuth falhou', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('AGT OAuth exception', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function hasValidToken(): bool
    {
        return $this->settings->agt_access_token 
            && $this->settings->agt_token_expires_at 
            && now()->lt($this->settings->agt_token_expires_at);
    }

    private function decryptField(?string $value): ?string
    {
        if (empty($value)) return null;
        
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value; // Retorna valor original se não estiver encriptado
        }
    }

    private function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Software-Certificate' => $this->settings->agt_software_certificate ?? $this->settings->saft_software_cert ?? '',
        ];
    }

    // =========================================
    // SOLICITAR SÉRIE
    // =========================================

    public function requestSeries(InvoicingSeries $series): array
    {
        $this->ensureAuthenticated();

        $startTime = microtime(true);
        $endpoint = self::ENDPOINT_SERIES;

        $payload = [
            'document_type' => $series->prefix,
            'series_code' => $series->series_code,
            'year' => $series->current_year ?? date('Y'),
            'establishment_id' => '1', // TODO: Multi-estabelecimento
            'description' => $series->name,
        ];

        try {
            $response = Http::withHeaders($this->getAuthHeaders())
                ->timeout(30)
                ->post($this->baseUrl . $endpoint, $payload);

            $responseTime = (microtime(true) - $startTime) * 1000;

            // Log
            AGTCommunicationLog::log(
                $this->tenantId,
                'SolicitarSerie',
                'POST',
                $endpoint,
                $this->getAuthHeaders(),
                $payload,
                $response->status(),
                $response->headers(),
                $response->json(),
                $responseTime,
                $response->successful()
            );

            if ($response->successful()) {
                $data = $response->json();
                
                // Atualizar série com dados da AGT
                $series->update([
                    'agt_series_id' => $data['series_id'] ?? $data['id'] ?? null,
                    'atcud_validation_code' => $data['atcud_validation_code'] ?? $data['validation_code'] ?? null,
                    'agt_status' => 'active',
                    'agt_environment' => $this->environment,
                    'agt_registered_at' => now(),
                    'agt_response' => $data,
                ]);

                return [
                    'success' => true,
                    'data' => $data,
                    'message' => 'Série registada na AGT com sucesso',
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Erro ao solicitar série',
                'status' => $response->status(),
            ];

        } catch (\Exception $e) {
            Log::error('AGT SolicitarSerie exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // =========================================
    // REGISTAR FACTURA
    // =========================================

    public function registerInvoice($document, string $documentTypeCode): array
    {
        $this->ensureAuthenticated();

        $startTime = microtime(true);
        $endpoint = self::ENDPOINT_INVOICES;

        // Criar submissão
        $submission = AGTSubmission::createForDocument($document, $documentTypeCode);

        // Preparar payload conforme especificação AGT
        $payload = $this->buildInvoicePayload($document, $documentTypeCode);

        try {
            $response = Http::withHeaders($this->getAuthHeaders())
                ->timeout(60)
                ->post($this->baseUrl . $endpoint, $payload);

            $responseTime = (microtime(true) - $startTime) * 1000;

            // Log
            AGTCommunicationLog::log(
                $this->tenantId,
                'RegistarFactura',
                'POST',
                $endpoint,
                $this->getAuthHeaders(),
                $payload,
                $response->status(),
                $response->headers(),
                $response->json(),
                $responseTime,
                $response->successful(),
                $response->successful() ? null : ($response->json()['message'] ?? 'Erro'),
                $submission->id
            );

            $submission->markAsSubmitted($payload);

            if ($response->successful()) {
                $data = $response->json();
                
                $agtReference = $data['reference'] ?? $data['agt_reference'] ?? $data['id'] ?? null;
                $atcud = $data['atcud'] ?? $this->generateATCUD($document, $documentTypeCode);

                $submission->markAsValidated($agtReference, $atcud, $data);

                return [
                    'success' => true,
                    'data' => $data,
                    'submission_id' => $submission->id,
                    'agt_reference' => $agtReference,
                    'atcud' => $atcud,
                    'message' => 'Documento registado na AGT com sucesso',
                ];
            }

            $errorData = $response->json();
            $submission->markAsRejected(
                $errorData['error_code'] ?? (string)$response->status(),
                $errorData['message'] ?? 'Erro ao registar documento',
                $errorData
            );

            return [
                'success' => false,
                'error' => $errorData['message'] ?? 'Erro ao registar documento',
                'error_code' => $errorData['error_code'] ?? null,
                'submission_id' => $submission->id,
                'status' => $response->status(),
            ];

        } catch (\Exception $e) {
            Log::error('AGT RegistarFactura exception', ['error' => $e->getMessage()]);
            
            $submission->markAsRejected('EXCEPTION', $e->getMessage(), []);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'submission_id' => $submission->id,
            ];
        }
    }

    private function buildInvoicePayload($document, string $documentTypeCode): array
    {
        $client = $document->client;
        $items = $document->items ?? [];

        return [
            'software_certificate' => $this->settings->agt_software_certificate ?? $this->settings->saft_software_cert ?? '',
            'document_type' => $documentTypeCode,
            'document_number' => $document->invoice_number ?? $document->credit_note_number ?? $document->debit_note_number,
            'series_id' => $document->series->agt_series_id ?? null,
            'issue_date' => $document->invoice_date?->format('Y-m-d') ?? $document->issue_date?->format('Y-m-d'),
            'system_entry_date' => $document->system_entry_date?->format('Y-m-d\TH:i:s') ?? now()->format('Y-m-d\TH:i:s'),
            'customer' => [
                'tax_id' => $client->nif ?? '999999999',
                'name' => $client->name,
                'address' => $client->address,
                'city' => $client->city,
                'country' => $client->country ?? 'AO',
            ],
            'lines' => collect($items)->map(function ($item) {
                return [
                    'line_number' => $item->order ?? $item->id,
                    'product_code' => $item->product?->sku ?? $item->product_id,
                    'product_description' => $item->description ?? $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_of_measure' => $item->unit ?? 'UN',
                    'unit_price' => round($item->unit_price, 2),
                    'tax_rate' => $item->tax_rate ?? 14,
                    'tax_amount' => round($item->tax_amount, 2),
                    'net_total' => round($item->subtotal, 2),
                    'gross_total' => round($item->total, 2),
                ];
            })->toArray(),
            'totals' => [
                'net_total' => round($document->net_total ?? $document->subtotal, 2),
                'tax_amount' => round($document->tax_amount, 2),
                'gross_total' => round($document->gross_total ?? $document->total, 2),
            ],
            'hash' => $document->hash ?? $document->saft_hash,
            'hash_control' => $document->hash_control ?? '1',
            'jws_signature' => $document->jws_signature,
        ];
    }

    // =========================================
    // OBTER ESTADO
    // =========================================

    public function getStatus(string $agtReference): array
    {
        $this->ensureAuthenticated();

        $startTime = microtime(true);
        $endpoint = str_replace('{reference}', $agtReference, self::ENDPOINT_STATUS);

        try {
            $response = Http::withHeaders($this->getAuthHeaders())
                ->timeout(30)
                ->get($this->baseUrl . $endpoint);

            $responseTime = (microtime(true) - $startTime) * 1000;

            AGTCommunicationLog::log(
                $this->tenantId,
                'ObterEstado',
                'GET',
                $endpoint,
                $this->getAuthHeaders(),
                null,
                $response->status(),
                $response->headers(),
                $response->json(),
                $responseTime,
                $response->successful()
            );

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Erro ao obter estado',
                'status' => $response->status(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // =========================================
    // CONSULTAR FACTURA
    // =========================================

    public function getInvoice(string $agtReference): array
    {
        $this->ensureAuthenticated();

        $startTime = microtime(true);
        $endpoint = self::ENDPOINT_INVOICES . '/' . $agtReference;

        try {
            $response = Http::withHeaders($this->getAuthHeaders())
                ->timeout(30)
                ->get($this->baseUrl . $endpoint);

            $responseTime = (microtime(true) - $startTime) * 1000;

            AGTCommunicationLog::log(
                $this->tenantId,
                'ConsultarFactura',
                'GET',
                $endpoint,
                $this->getAuthHeaders(),
                null,
                $response->status(),
                $response->headers(),
                $response->json(),
                $responseTime,
                $response->successful()
            );

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Documento não encontrado',
                'status' => $response->status(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // =========================================
    // LISTAR FACTURAS
    // =========================================

    public function listInvoices(array $filters = []): array
    {
        $this->ensureAuthenticated();

        $startTime = microtime(true);
        $endpoint = self::ENDPOINT_INVOICES;

        $queryParams = array_filter([
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'document_type' => $filters['document_type'] ?? null,
            'status' => $filters['status'] ?? null,
            'page' => $filters['page'] ?? 1,
            'per_page' => $filters['per_page'] ?? 50,
        ]);

        try {
            $response = Http::withHeaders($this->getAuthHeaders())
                ->timeout(30)
                ->get($this->baseUrl . $endpoint, $queryParams);

            $responseTime = (microtime(true) - $startTime) * 1000;

            AGTCommunicationLog::log(
                $this->tenantId,
                'ListarFacturas',
                'GET',
                $endpoint,
                $this->getAuthHeaders(),
                $queryParams,
                $response->status(),
                $response->headers(),
                $response->json(),
                $responseTime,
                $response->successful()
            );

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Erro ao listar documentos',
                'status' => $response->status(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // =========================================
    // HELPERS
    // =========================================

    private function ensureAuthenticated(): void
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }
    }

    private function generateATCUD($document, string $documentTypeCode): string
    {
        $series = $document->series;
        $validationCode = $series->atcud_validation_code ?? 'XXXXXXXX';
        $sequentialNumber = $document->id;
        
        return $validationCode . '-' . $sequentialNumber;
    }

    // =========================================
    // TESTE DE CONECTIVIDADE
    // =========================================

    public function testConnection(): array
    {
        try {
            if (!$this->isConfigured()) {
                return [
                    'success' => false,
                    'error' => 'Credenciais AGT não configuradas',
                ];
            }

            $authenticated = $this->authenticate();

            return [
                'success' => $authenticated,
                'environment' => $this->environment,
                'base_url' => $this->baseUrl,
                'message' => $authenticated ? 'Conexão estabelecida com sucesso' : 'Falha na autenticação',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
