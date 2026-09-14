<?php

namespace App\Services\AGT;

use App\Models\AGT\AGTSubmission;
use App\Models\AGT\AGTCommunicationLog;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\InvoicingSeries;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @deprecated desde 25/05/2026 — Sprint 1 consolidation.
 *
 * Esta classe monolítica (~740 linhas) está a ser substituída pela arquitectura
 * Services + AGTHttpClient + AGTPayloadBuilder + JwsSigner:
 *
 *   AGTClient::requestSeries(...)      ->  SeriesService::request(...)
 *   AGTClient::registerInvoice(...)    ->  RegisterService::register(...)
 *   AGTClient::getStatus(...)          ->  QueryService::consultByRequestId(...)
 *   AGTClient::getInvoice(...)         ->  QueryService::consultByNumber(...)
 *   AGTClient::listInvoices(...)       ->  (Sprint 2 — ListInvoicesService)
 *   AGTClient::listSeries(...)         ->  (Sprint 2 — ListSeriesService)
 *   AGTClient::validateDocument(...)   ->  ValidateService::confirm/reject
 *
 * Mantida temporariamente por dependências legacy. Não usar em código novo.
 *
 * Cliente API Facturação Electrónica AGT Angola
 * Decreto Presidencial n.º 71/25
 *
 * Autenticação: Basic Auth (username:password)
 * Assinatura: JWS RS256 (chave privada do contribuinte)
 * Modelo: Assíncrono (polling via obterEstado)
 */
class AGTClient
{
    private ?InvoicingSettings $settings = null;
    private int $tenantId;
    private string $environment;
    private string $baseUrl;
    private ?string $username = null;
    private ?string $password = null;

    // URLs e endpoints: uma única fonte, no AGTHttpClient.
    //
    // Estavam escritos aqui E lá com os mesmos valores. Duas listas de URLs da
    // administração fiscal é um problema à espera de acontecer: bastava a AGT
    // mudar um caminho para metade do sistema (os Services, que usam o
    // AGTHttpClient) ficar a apontar para um sítio e a outra metade (este
    // cliente) para outro, sem nada a assinalar.
    //
    // A unificação completa — reescrever os fluxos deste cliente sobre o
    // AGTHttpClient — é um refactor grande num caminho já certificado; fica para
    // quando houver razão para lhe mexer. O que interessava era eliminar a
    // divergência silenciosa dos endereços.
    const SANDBOX_URL = AGTHttpClient::SANDBOX_URL;
    const PRODUCTION_URL = AGTHttpClient::PRODUCTION_URL;

    const ENDPOINT_SOLICITAR_SERIE = AGTHttpClient::ENDPOINT_SERIES;
    const ENDPOINT_REGISTAR_FACTURA = AGTHttpClient::ENDPOINT_REGISTER;
    const ENDPOINT_OBTER_ESTADO = AGTHttpClient::ENDPOINT_STATUS;
    const ENDPOINT_CONSULTAR_FACTURA = AGTHttpClient::ENDPOINT_CONSULT;
    const ENDPOINT_LISTAR_FACTURAS = AGTHttpClient::ENDPOINT_LIST_INVOICES;
    const ENDPOINT_LISTAR_SERIES = AGTHttpClient::ENDPOINT_LIST_SERIES;
    const ENDPOINT_VALIDAR_DOCUMENTO = AGTHttpClient::ENDPOINT_VALIDATE;

    public function __construct(int $tenantId, ?string $environmentOverride = null)
    {
        $this->tenantId = $tenantId;
        $this->loadSettings();

        // O override é usado apenas pela consola de testes do produtor e não
        // altera a configuração persistida da empresa.
        if (in_array($environmentOverride, ['sandbox', 'production'], true)) {
            $this->environment = $environmentOverride;
            $this->baseUrl = $environmentOverride === 'production'
                ? self::PRODUCTION_URL
                : self::SANDBOX_URL;
        }
    }

    // =========================================
    // CONFIGURAÇÃO
    // =========================================

    private function loadSettings(): void
    {
        // Uma empresa nova nasce em PRODUÇÃO: quem se regista vai facturar a
        // sério, e nascer em homologação mandava os primeiros documentos para
        // o ambiente de testes sem ninguém dar por isso. Ver a migração
        // 2026_09_02_140000. Só afecta linhas NOVAS — quem já existe fica onde
        // está.
        $this->settings = InvoicingSettings::firstOrCreate(
            ['tenant_id' => $this->tenantId],
            [
                'default_currency' => 'AOA',
                'agt_environment' => 'production',
                'agt_establishment_number' => 'SEDE',
            ]
        );

        // O `?:` e não `??`: a coluna é NOT NULL, mas uma linha antiga pode ter
        // ficado com string vazia, e '' escolheria homologação em silêncio.
        $this->environment = $this->settings->agt_environment ?: 'production';
        $this->baseUrl = $this->environment === 'production'
            ? self::PRODUCTION_URL
            : self::SANDBOX_URL;
    }

    public function isConfigured(): bool
    {
        // Credenciais do produtor PARA ESTE AMBIENTE.
        return AGTProducerStore::temCredenciais($this->environment);
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    // =========================================
    // AUTENTICAÇÃO BASIC AUTH
    // =========================================

    private function loadCredentials(): void
    {
        if ($this->username && $this->password) return;

        $credenciais = AGTProducerStore::credenciais($this->environment);
        $this->username = $credenciais['username'];
        $this->password = $credenciais['password'];

        if (empty($this->username) || empty($this->password)) {
            $rotulo = $this->environment === 'production' ? 'Produção' : 'Homologação';
            throw new \Exception("Credenciais Basic Auth do produtor para {$rotulo} não configuradas. Contacte o administrador do sistema.");
        }
    }

    public function authenticate(): bool
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Credenciais AGT do produtor não configuradas');
        }
        $this->loadCredentials();
        return true;
    }

    private function getHttpClient()
    {
        $this->loadCredentials();

        return Http::withBasicAuth($this->username, $this->password)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->connectTimeout(15)
            ->retry(2, 750)
            ->timeout(30);
    }

    public function getContributorPrivateKey(): ?string
    {
        // Chave do ambiente DESTE cliente — que pode ser um override e não o
        // gravado. Sem o argumento, a consola de testes pedia produção e
        // assinava com a chave de homologação; a AGT recusava e a causa não
        // aparecia em lado nenhum.
        $keyPath = AGTKeyStore::privateKeyPath((int) $this->tenantId, $this->environment);
        if (Storage::disk('local')->exists($keyPath)) {
            return Storage::disk('local')->get($keyPath);
        }
        return null;
    }

    public function hasContributorKey(): bool
    {
        return $this->getContributorPrivateKey() !== null;
    }

    // =========================================
    // SOLICITAR SÉRIE (solicitarSerie)
    // =========================================

    public function requestSeries(InvoicingSeries $series): array
    {
        $this->loadCredentials();

        $startTime = microtime(true);
        $endpoint = self::ENDPOINT_SOLICITAR_SERIE;

        $tenant = \App\Models\Tenant::find($this->tenantId);
        $taxNumber = $tenant->nif ?? $tenant->tax_id ?? '';
        $establishment = $this->settings->agt_establishment_number ?? 'SEDE';

        $payload = [
            'schemaVersion' => $this->settings->agt_schema_version ?? AGTPayloadBuilder::SCHEMA_VERSION,
            'submissionUUID' => (string) Str::uuid(),
            'taxRegistrationNumber' => $taxNumber,
            'submissionTimeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'softwareInfo' => $this->buildSoftwareInfo(),
            'seriesYear' => (string) ($series->current_year ?? date('Y')),
            'documentType' => $series->prefix ?? $series->document_type,
            'establishmentNumber' => $establishment,
            'jwsSignature' => $this->signPayload([
                'taxRegistrationNumber' => $taxNumber,
                'seriesYear' => (string) ($series->current_year ?? date('Y')),
                'documentType' => $series->prefix ?? $series->document_type,
                'establishmentNumber' => $establishment,
                'seriesContingencyIndicator' => 'N',
            ]),
            'seriesContingencyIndicator' => 'N',
        ];

        try {
            $response = $this->getHttpClient()->post($this->baseUrl . $endpoint, $payload);
            $responseTime = (microtime(true) - $startTime) * 1000;

            AGTCommunicationLog::log(
                $this->tenantId,
                'SolicitarSerie',
                'POST',
                $endpoint,
                ['Authorization' => 'Basic ***'],
                $payload,
                $response->status(),
                $response->headers(),
                $response->json(),
                $responseTime,
                $response->successful(),
                // O ambiente deste cliente — que pode ser o override da consola.
                ambiente: $this->environment
            );

            if ($response->successful()) {
                $data = $response->json();
                $seriesResult = $data['seriesFEResult'] ?? [];
                $seriesCode = is_array($seriesResult)
                    ? ($seriesResult['seriesCode'] ?? null)
                    : null;
                $errors = collect($data['errorList'] ?? [])
                    ->filter(function ($error) {
                        if (is_array($error)) {
                            return filled($error['idError'] ?? null)
                                || filled($error['descriptionError'] ?? $error['errorDescription'] ?? null);
                        }

                        return trim((string) $error) !== '';
                    })
                    ->values()
                    ->all();

                if (!empty($errors) || empty($seriesCode)) {
                    $error = $this->formatErrorList($errors)
                        ?: 'A AGT não devolveu o código da série.';
                    $series->update([
                        'agt_series_id' => null,
                        'agt_status' => 'pending',
                        'agt_environment' => $this->environment,
                        'agt_response' => $data,
                    ]);

                    return [
                        'success' => false,
                        'error' => $error,
                        'status' => $response->status(),
                        'data' => $data,
                    ];
                }
                
                $series->update([
                    'agt_series_id' => $seriesCode,
                    'atcud_validation_code' => $seriesCode,
                    'agt_status' => 'active',
                    'agt_series_status' => InvoicingSeries::AGT_STATUS_OPEN,
                    'series_contingency_indicator' => 'N',
                    'authorized_quantity' => $seriesResult['authorizedQuantity'] ?? null,
                    'first_document_no' => $seriesResult['firstDocumentNo'] ?? null,
                    'last_document_no' => $seriesResult['lastDocumentNo'] ?? null,
                    'submission_uuid' => $payload['submissionUUID'],
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

            $errorData = $response->json();
            return [
                'success' => false,
                'error' => $this->formatErrorList($errorData['errorList'] ?? []) ?: 'Erro ao solicitar série',
                'status' => $response->status(),
                'data' => $errorData,
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
    // REGISTAR FACTURA (registarFactura)
    // =========================================

    public function registerInvoice($document, string $documentTypeCode): array
    {
        $this->loadCredentials();

        $startTime = microtime(true);
        $endpoint = self::ENDPOINT_REGISTAR_FACTURA;

        $submission = AGTSubmission::createForDocument($document, $documentTypeCode);
        $payload = $this->buildInvoicePayload($document, $documentTypeCode);

        try {
            $response = $this->getHttpClient()
                ->timeout(60)
                ->post($this->baseUrl . $endpoint, $payload);

            $responseTime = (microtime(true) - $startTime) * 1000;

            AGTCommunicationLog::log(
                $this->tenantId,
                'RegistarFactura',
                'POST',
                $endpoint,
                ['Authorization' => 'Basic ***'],
                $payload,
                $response->status(),
                $response->headers(),
                $response->json(),
                $responseTime,
                $response->successful(),
                $response->successful() ? null : ($response->json()['message'] ?? 'Erro'),
                $submission->id,
                ambiente: $this->environment
            );

            $submission->markAsSubmitted($payload);

            if ($response->successful()) {
                $data = $response->json();
                $requestID = $data['requestID'] ?? null;

                $submission->markAsValidated($requestID, null, $data);

                return [
                    'success' => true,
                    'data' => $data,
                    'submission_id' => $submission->id,
                    'request_id' => $requestID,
                    'message' => 'Documento aceite (processamento assíncrono). Consultar estado com requestID.',
                ];
            }

            $errorData = $response->json();
            $errorMsg = $this->formatErrorList($errorData['errorList'] ?? []);
            $submission->markAsRejected(
                $errorData['errorList'][0]['idError'] ?? (string)$response->status(),
                $errorMsg ?: 'Erro ao registar documento',
                $errorData
            );

            return [
                'success' => false,
                'error' => $errorMsg ?: 'Erro ao registar documento',
                'submission_id' => $submission->id,
                'status' => $response->status(),
                'data' => $errorData,
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
        $tenant = \App\Models\Tenant::find($this->tenantId);
        $taxNumber = $tenant->nif ?? $tenant->tax_id ?? '';
        $client = $document->client;
        $items = $document->items ?? collect();
        $docNo = $document->invoice_number ?? $document->credit_note_number ?? $document->debit_note_number;

        $documents = [[
            'documentNo' => $docNo,
            'documentStatus' => 'N',
            'jwsDocumentSignature' => $this->signPayload([
                'documentNo' => $docNo,
                'taxRegistrationNumber' => $taxNumber,
                'documentType' => $documentTypeCode,
                'documentDate' => ($document->invoice_date ?? $document->issue_date)?->format('Y-m-d'),
                'customerTaxID' => $client->nif ?? '999999999',
                'customerCountry' => \App\Support\Geografia::normalizarPais($client->country ?? null)
                    ?? \App\Support\Geografia::PAIS_PADRAO,
                'companyName' => $client->name ?? '',
                'documentTotals' => [
                    'taxPayable' => round($document->tax_amount ?? 0, 2),
                    'netTotal' => round($document->net_total ?? $document->subtotal ?? 0, 2),
                    'grossTotal' => round($document->gross_total ?? $document->total ?? 0, 2),
                ],
            ]),
            'documentDate' => ($document->invoice_date ?? $document->issue_date)?->format('Y-m-d'),
            'documentType' => $documentTypeCode,
            'eacCode' => $document->eac_code ?? $this->settings->agt_eac_code ?? '',
            'systemEntryDate' => ($document->system_entry_date ?? now())->format('Y-m-d\TH:i:s\Z'),
            'customerTaxID' => $client->nif ?? '999999999',
            'customerCountry' => \App\Support\Geografia::normalizarPais($client->country ?? null)
                ?? \App\Support\Geografia::PAIS_PADRAO,
            'companyName' => $client->name ?? '',
            'lines' => collect($items)->map(function ($item, $idx) {
                $credit = round(($item->unit_price ?? 0) * $item->quantity, 2);
                $rate   = (float) ($item->tax_rate ?? 0);
                // A AGT apura o IVA por CEIL ao cêntimo (DS.120 §4.1) sobre
                // base × taxa. Enviar o tax_amount gravado (já a 2 casas) dava
                // menos um cêntimo e a recusa E70. Recalcula-se a partir da base
                // que vai no payload.
                $taxContribution = $rate > 0
                    ? AGTPayloadBuilder::ceilCents(($credit * $rate) / 100)
                    : 0.0;
                return [
                    'lineNumber' => $idx + 1,
                    'productCode' => $item->product?->sku ?? $item->product_id ?? 'ITEM',
                    'productDescription' => $item->description ?? $item->product_name ?? '',
                    'quantity' => $item->quantity,
                    'unitOfMeasure' => $item->unit ?? 'UN',
                    'unitPrice' => round($item->unit_price ?? 0, 2),
                    'unitPriceBase' => round($item->unit_price ?? 0, 2),
                    'debitAmount' => 0,
                    'creditAmount' => $credit,
                    'taxes' => [[
                        'taxType' => 'IVA',
                        'taxCountryRegion' => 'AO',
                        // Uma linha sem taxa NAO se declara a 14% a AGT: isso
                        // inventa imposto num documento que nao o cobrou e parte
                        // a igualdade netTotal + taxPayable = grossTotal.
                        'taxCode' => $rate > 0 ? 'NOR' : 'ISE',
                        'taxPercentage' => $rate,
                        'taxContribution' => $taxContribution,
                    ]],
                    'settlementAmount' => 0,
                ];
            })->toArray(),
            'documentTotals' => [
                'taxPayable' => round($document->tax_amount ?? 0, 2),
                'netTotal' => round($document->net_total ?? $document->subtotal ?? 0, 2),
                'grossTotal' => round($document->gross_total ?? $document->total ?? 0, 2),
            ],
        ]];

        return [
            'schemaVersion' => $this->settings->agt_schema_version ?? AGTPayloadBuilder::SCHEMA_VERSION,
            'submissionUUID' => (string) Str::uuid(),
            'taxRegistrationNumber' => $taxNumber,
            'submissionTimeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'softwareInfo' => $this->buildSoftwareInfo(),
            'numberOfEntries' => 1,
            'documents' => $documents,
        ];
    }

    // =========================================
    // OBTER ESTADO (obterEstado)
    // =========================================

    public function getStatus(string $requestID): array
    {
        $this->loadCredentials();

        $startTime = microtime(true);
        $endpoint = self::ENDPOINT_OBTER_ESTADO;

        $tenant = \App\Models\Tenant::find($this->tenantId);
        $taxNumber = $tenant->nif ?? $tenant->tax_id ?? '';

        $payload = [
            'schemaVersion' => $this->settings->agt_schema_version ?? AGTPayloadBuilder::SCHEMA_VERSION,
            'submissionUUID' => (string) Str::uuid(),
            'taxRegistrationNumber' => $taxNumber,
            'submissionTimeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'softwareInfo' => $this->buildSoftwareInfo(),
            'jwsSignature' => $this->signPayload([
                'taxRegistrationNumber' => $taxNumber,
                'requestID' => $requestID,
            ]),
            'requestID' => $requestID,
        ];

        try {
            $response = $this->getHttpClient()->post($this->baseUrl . $endpoint, $payload);
            $responseTime = (microtime(true) - $startTime) * 1000;

            AGTCommunicationLog::log(
                $this->tenantId,
                'ObterEstado',
                'POST',
                $endpoint,
                ['Authorization' => 'Basic ***'],
                $payload,
                $response->status(),
                $response->headers(),
                $response->json(),
                $responseTime,
                $response->successful(),
                // O ambiente deste cliente — que pode ser o override da consola.
                ambiente: $this->environment
            );

            if ($response->successful()) {
                $body = $response->json() ?? [];
                $errors = $this->responseErrors($body);
                if ($errors !== []) {
                    return [
                        'success' => false,
                        'error' => $this->formatErrorList($errors),
                        'data' => $body,
                        'status' => $response->status(),
                    ];
                }

                $resultCode = data_get($body, 'resultCode')
                    ?? data_get($body, 'statusResult.resultCode');
                $documents = data_get($body, 'documentStatusList', []);

                return [
                    'success' => true,
                    'data' => $body,
                    'status' => $response->status(),
                    'message' => $documents
                        ? sprintf('Estado recebido para %d documento(s).', count($documents))
                        : ($resultCode !== null
                            ? "Estado do pedido recebido (código {$resultCode})."
                            : 'Pedido aceite, mas a AGT não devolveu estados de documentos.'),
                ];
            }

            return [
                'success' => false,
                'error' => $this->formatErrorList($response->json()['requestErrorList'] ?? []) ?: 'Erro ao obter estado',
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
    // CONSULTAR FACTURA (consultarFactura)
    // =========================================

    public function getInvoice(string $documentNo): array
    {
        $this->loadCredentials();

        $startTime = microtime(true);
        $endpoint = self::ENDPOINT_CONSULTAR_FACTURA;

        $tenant = \App\Models\Tenant::find($this->tenantId);
        $taxNumber = $tenant->nif ?? $tenant->tax_id ?? '';

        $payload = [
            'schemaVersion' => $this->settings->agt_schema_version ?? AGTPayloadBuilder::SCHEMA_VERSION,
            'submissionUUID' => (string) Str::uuid(),
            'taxRegistrationNumber' => $taxNumber,
            'submissionTimeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'softwareInfo' => $this->buildSoftwareInfo(),
            'jwsSignature' => $this->signPayload([
                'taxRegistrationNumber' => $taxNumber,
                'documentNo' => $documentNo,
            ]),
            'invoiceNo' => $documentNo,
        ];

        try {
            $response = $this->getHttpClient()->post($this->baseUrl . $endpoint, $payload);
            $responseTime = (microtime(true) - $startTime) * 1000;

            AGTCommunicationLog::log(
                $this->tenantId,
                'ConsultarFactura',
                'POST',
                $endpoint,
                ['Authorization' => 'Basic ***'],
                $payload,
                $response->status(),
                $response->headers(),
                $response->json(),
                $responseTime,
                $response->successful(),
                // O ambiente deste cliente — que pode ser o override da consola.
                ambiente: $this->environment
            );

            if ($response->successful()) {
                $body = $response->json() ?? [];
                $errors = $this->responseErrors($body);
                if ($errors !== []) {
                    return [
                        'success' => false,
                        'error' => $this->formatErrorList($errors),
                        'data' => $body,
                        'status' => $response->status(),
                    ];
                }

                $document = data_get($body, 'document')
                    ?? data_get($body, 'invoice')
                    ?? data_get($body, 'invoiceResult');

                return [
                    'success' => $document !== null,
                    'data' => $body,
                    'status' => $response->status(),
                    'message' => $document !== null
                        ? 'Documento encontrado na AGT.'
                        : null,
                    'error' => $document === null
                        ? 'A AGT processou a consulta, mas não encontrou o documento informado.'
                        : null,
                ];
            }

            return [
                'success' => false,
                'error' => $this->formatErrorList($response->json()['errorList'] ?? []) ?: 'Documento não encontrado',
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
    // LISTAR FACTURAS (listarFacturas)
    // =========================================

    public function listInvoices(array $filters = []): array
    {
        $this->loadCredentials();

        $startTime = microtime(true);
        $endpoint = self::ENDPOINT_LISTAR_FACTURAS;

        $tenant = \App\Models\Tenant::find($this->tenantId);
        $taxNumber = $tenant->nif ?? $tenant->tax_id ?? '';
        $startDate = $filters['date_from'] ?? now()->subDays(30)->format('Y-m-d');
        $endDate = $filters['date_to'] ?? now()->format('Y-m-d');

        $payload = [
            'schemaVersion' => $this->settings->agt_schema_version ?? AGTPayloadBuilder::SCHEMA_VERSION,
            'submissionUUID' => (string) Str::uuid(),
            'taxRegistrationNumber' => $taxNumber,
            'submissionTimeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'softwareInfo' => $this->buildSoftwareInfo(),
            'jwsSignature' => $this->signPayload([
                'taxRegistrationNumber' => $taxNumber,
                'queryStartDate' => $startDate,
                'queryEndDate' => $endDate,
            ]),
            'queryStartDate' => $startDate,
            'queryEndDate' => $endDate,
        ];

        try {
            $response = $this->getHttpClient()->post($this->baseUrl . $endpoint, $payload);
            $responseTime = (microtime(true) - $startTime) * 1000;

            AGTCommunicationLog::log(
                $this->tenantId,
                'ListarFacturas',
                'POST',
                $endpoint,
                ['Authorization' => 'Basic ***'],
                $payload,
                $response->status(),
                $response->headers(),
                $response->json(),
                $responseTime,
                $response->successful(),
                // O ambiente deste cliente — que pode ser o override da consola.
                ambiente: $this->environment
            );

            if ($response->successful()) {
                $body = $response->json() ?? [];
                $errors = $this->responseErrors($body);
                if ($errors !== []) {
                    return [
                        'success' => false,
                        'error' => $this->formatErrorList($errors),
                        'data' => $body,
                        'status' => $response->status(),
                    ];
                }

                $entries = data_get($body, 'resultEntryList', []);
                $count = (int) (
                    data_get($body, 'documentResultCount')
                    ?? data_get($body, 'statusResult.documentResultCount')
                    ?? count(is_array($entries) ? $entries : [])
                );

                return [
                    'success' => true,
                    'data' => $body,
                    'status' => $response->status(),
                    'count' => $count,
                    'entries' => is_array($entries) ? $entries : [],
                    // Zero é o resultado normal para quem só emite. O utilizador
                    // espera ver aqui os documentos que emitiu — que este
                    // endpoint nunca devolve — por isso a mensagem tem de o dizer.
                    'message' => $count > 0
                        ? "{$count} factura(s) recebida(s) encontrada(s) no período."
                        : 'Consulta executada com sucesso: a AGT não tem facturas em que esta empresa seja o ADQUIRENTE neste período. '
                          . 'Este endpoint lista apenas documentos recebidos — os documentos que a empresa emitiu não aparecem aqui '
                          . '(veja o separador Submissões, ou use ConsultarFactura com o número fiscal completo).',
                ];
            }

            return [
                'success' => false,
                'error' => $this->formatErrorList($response->json()['errorList'] ?? []) ?: 'Erro ao listar documentos',
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
    // HELPERS & ASSINATURA
    // =========================================

    /**
     * Os três campos assinados do produtor — TODOS por ambiente.
     *
     * ISTO JÁ ESTEVE ERRADO E CUSTOU CARO. O `productId` e o `productVersion`
     * liam a definição GLOBAL (`saft_product_id`, `saft_version`), ignorando o
     * ambiente, enquanto o número de certificação já era o do ambiente certo.
     * Em produção saía o par trocado — versão `1.0` de homologação com o
     * certificado `FE/324/AGT/2026`, que é `1.0.0` — e a AGT devolvia E39 no
     * registo de séries de TODAS as empresas.
     *
     * O mais traiçoeiro é que a submissão de DOCUMENTOS funcionava: essa passa
     * pelo `AGTPayloadBuilder`, que sempre resolveu os três por ambiente. Duas
     * implementações do mesmo bloco assinado, uma certa e outra errada — e só
     * a errada é que se via, porque o registo de séries é o primeiro passo de
     * cada cliente novo.
     *
     * Fonte única agora: `AGTProducerStore`, o mesmo que o payload builder usa.
     */
    private function buildSoftwareInfo(): array
    {
        $detail = [
            'productId' => AGTProducerStore::productId($this->environment)
                ?: ($this->settings->agt_product_id ?? 'SOS ERP - SOLUÇÕES EMPRESARIAIS'),
            'productVersion' => AGTProducerStore::productVersion($this->environment)
                ?: ($this->settings->agt_product_version ?? '1.0'),
            'softwareValidationNumber' => AGTProducerStore::numeroCertificacao($this->environment)
                ?: ($this->settings->agt_software_validation_number ?? 'C_PENDING'),
        ];

        return [
            'softwareInfoDetail' => $detail,
            'jwsSoftwareSignature' => $this->signSoftwareInfo($detail),
        ];
    }

    /**
     * Assina com a chave do produtor DESTE ambiente.
     *
     * O caminho estava fixo em `saft/private_key.pem`. Hoje resolve no mesmo
     * sítio (o legado é o par partilhado), mas no dia em que se instalar um par
     * só para produção, esta assinatura continuaria a sair com a chave errada
     * — e a AGT recusaria sem dizer porquê. O `AGTProducerStore` já trata do
     * recurso ao legado.
     */
    private function signSoftwareInfo(array $payload): string
    {
        $caminho = AGTProducerStore::privateKeyPath($this->environment);
        $disco = Storage::disk('local');

        if (!$disco->exists($caminho)) {
            Log::warning('AGT: chave privada do produtor não encontrada', [
                'ambiente' => $this->environment,
                'caminho'  => $caminho,
            ]);

            return '';
        }

        return $this->generateJWS($payload, $disco->get($caminho));
    }

    private function signPayload(array $payload): string
    {
        $privateKey = $this->getContributorPrivateKey();

        if (!$privateKey) {
            Log::warning("AGT: Chave privada do contribuinte não encontrada para tenant {$this->tenantId}");
            return '';
        }

        return $this->generateJWS($payload, $privateKey);
    }

    private function generateJWS(array $payload, string $privateKeyPem): string
    {
        try {
            $header = ['alg' => 'RS256', 'typ' => 'JWT'];

            $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $dataToSign = $headerEncoded . '.' . $payloadEncoded;

            $pkeyId = openssl_pkey_get_private($privateKeyPem);
            if (!$pkeyId) {
                Log::error('AGT JWS: Erro ao carregar chave privada: ' . openssl_error_string());
                return '';
            }

            $signature = '';
            $signed = openssl_sign($dataToSign, $signature, $pkeyId, OPENSSL_ALGO_SHA256);

            if (!$signed) {
                Log::error('AGT JWS: Erro ao assinar: ' . openssl_error_string());
                return '';
            }

            return $dataToSign . '.' . $this->base64UrlEncode($signature);

        } catch (\Exception $e) {
            Log::error('AGT JWS exception', ['error' => $e->getMessage()]);
            return '';
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function formatErrorList(array $errorList): string
    {
        if (empty($errorList)) return '';

        return collect($errorList)->map(function ($err) {
            if (is_string($err)) {
                return trim($err);
            }
            $code = $err['idError'] ?? $err['errorCode'] ?? '';
            $desc = $err['descriptionError'] ?? $err['errorDescription'] ?? '';
            return $code ? "[{$code}] {$desc}" : $desc;
        })->filter()->implode('; ');
    }

    private function responseErrors(array $body): array
    {
        $errors = $body['errorList']
            ?? $body['requestErrorList']
            ?? data_get($body, 'statusResult.requestErrorList', []);

        if (!is_array($errors)) {
            $errors = [$errors];
        }

        return array_values(array_filter($errors, function ($error): bool {
            if (is_string($error)) {
                return trim($error) !== '';
            }
            return is_array($error) && collect($error)
                ->filter(fn ($value) => trim((string) $value) !== '')
                ->isNotEmpty();
        }));
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
                    'error' => 'Credenciais Basic Auth do produtor não configuradas. Solicite ao administrador em Operação AGT → Configurações.',
                ];
            }

            $this->loadCredentials();

            $tenant = \App\Models\Tenant::find($this->tenantId);
            $taxNumber = $tenant->nif ?? $tenant->tax_id ?? '';

            if (empty($taxNumber)) {
                return [
                    'success' => false,
                    'error' => 'NIF do contribuinte não configurado',
                ];
            }

            $payload = [
                'schemaVersion' => $this->settings->agt_schema_version ?? AGTPayloadBuilder::SCHEMA_VERSION,
                'taxRegistrationNumber' => $taxNumber,
                'submissionTimeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
                'softwareInfo' => $this->buildSoftwareInfo(),
                'jwsSignature' => $this->signPayload(['taxRegistrationNumber' => $taxNumber]),
            ];

            $response = $this->getHttpClient()->post($this->baseUrl . self::ENDPOINT_LISTAR_SERIES, $payload);

            if ($response->status() === 401 || $response->status() === 403) {
                return [
                    'success' => false,
                    'error' => 'Credenciais rejeitadas pela AGT (HTTP ' . $response->status() . ')',
                    'environment' => $this->environment,
                    'base_url' => $this->baseUrl,
                    'http_status' => $response->status(),
                ];
            }

            /*
             * «CREDENCIAIS VÁLIDAS» SÓ QUANDO A AGT NÃO SE QUEIXOU.
             *
             * Qualquer resposta que não fosse 401/403 contava como sucesso —
             * um 200 com `errorList` a dizer E39 (assinatura do produtor fora
             * do certificado) ou E40 (assinatura do contribuinte inválida)
             * aparecia no ecrã a verde. A autenticação passou, mas nenhum
             * documento ia passar; é isso que o botão tem de mostrar.
             */
            $corpo = $response->json() ?? [];
            $erros = is_array($corpo) ? $this->responseErrors($corpo) : [];

            if (!$response->successful() || $erros !== []) {
                return [
                    'success' => false,
                    'error' => $erros !== []
                        ? 'A AGT respondeu com erros: ' . $this->formatErrorList($erros)
                        : 'A AGT respondeu com HTTP ' . $response->status() . '.',
                    'errors' => $erros,
                    'environment' => $this->environment,
                    'base_url' => $this->baseUrl,
                    'http_status' => $response->status(),
                    'has_contributor_key' => $this->hasContributorKey(),
                ];
            }

            return [
                'success' => true,
                'environment' => $this->environment,
                'base_url' => $this->baseUrl,
                'http_status' => $response->status(),
                'has_contributor_key' => $this->hasContributorKey(),
                'message' => 'Credenciais válidas. API AGT respondeu (HTTP ' . $response->status() . ')',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
