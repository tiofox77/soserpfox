<?php

namespace App\Services\AGT;

use App\Models\AGT\AGTSubmission;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\InvoicingSeries;
use Illuminate\Support\Facades\Log;

/**
 * Serviço Principal AGT Angola
 * Decreto Presidencial n.º 71/25
 * 
 * Coordena todos os serviços AGT:
 * - Assinatura Digital
 * - Comunicação API
 * - QR Code
 * - Validações
 */
class AGTService
{
    private SignatureService $signatureService;
    private QRCodeService $qrCodeService;
    private ?AGTClient $agtClient = null;
    private int $tenantId;

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->signatureService = new SignatureService($tenantId);
        $this->qrCodeService = new QRCodeService();
    }

    private function getClient(): AGTClient
    {
        if (!$this->agtClient) {
            $this->agtClient = new AGTClient($this->tenantId);
        }
        return $this->agtClient;
    }

    // =========================================
    // FLUXO COMPLETO DE DOCUMENTO
    // =========================================

    /**
     * Processar documento para conformidade AGT
     * 1. Gerar hash SAFT
     * 2. Assinar com JWS
     * 3. Gerar QR Code
     * 4. Submeter à AGT (se auto_submit ativo)
     */
    public function processDocument($document, bool $autoSubmit = false): array
    {
        $results = [
            'success' => true,
            'hash' => null,
            'jws_signature' => null,
            'qr_code' => null,
            'qr_data' => null,
            'agt_submission' => null,
            'errors' => [],
        ];

        try {
            if ((int) ($document->tenant_id ?? 0) !== $this->tenantId) {
                throw new \DomainException('O documento nao pertence ao tenant da operacao AGT.');
            }
            // 1. Verificar se tem chaves
            if (!$this->signatureService->hasKeys()) {
                $results['errors'][] = 'Chaves SAFT não configuradas. Configure em SuperAdmin > SAFT.';
                $results['success'] = false;
                return $results;
            }

            // 2. Gerar hash e assinatura
            $signResult = $this->signatureService->signComplete($document);
            $results['hash'] = $signResult['hash'];
            $results['jws_signature'] = $signResult['jws_signature'];

            if (!$signResult['success']) {
                $results['errors'][] = 'Erro ao gerar hash/assinatura';
            }

            // 3. Gerar QR Code
            $results['qr_data'] = $this->qrCodeService->generateQRData($document);
            $results['qr_code'] = $this->qrCodeService->generateQRImage($document);

            // 4. Submeter à AGT se solicitado
            if ($autoSubmit && $this->getClient()->isConfigured()) {
                $documentType = $this->getDocumentTypeCode($document);
                $submitResult = $this->getClient()->registerInvoice($document, $documentType);
                
                $results['agt_submission'] = $submitResult;
                
                if (!$submitResult['success']) {
                    $results['errors'][] = 'Erro ao submeter à AGT: ' . ($submitResult['error'] ?? 'Erro desconhecido');
                }
            }

            $results['success'] = empty($results['errors']);

        } catch (\Exception $e) {
            Log::error('AGTService: Erro ao processar documento', [
                'error' => $e->getMessage(),
                'document_id' => $document->id ?? null,
            ]);
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    // =========================================
    // OPERAÇÕES INDIVIDUAIS
    // =========================================

    /**
     * Apenas assinar documento (hash + JWS)
     */
    public function signDocument($document): array
    {
        return $this->signatureService->signComplete($document);
    }

    /**
     * Apenas gerar QR Code
     */
    public function generateQRCode($document): array
    {
        return [
            'qr_data' => $this->qrCodeService->generateQRData($document),
            'qr_image' => $this->qrCodeService->generateQRImage($document),
            'qr_svg' => $this->qrCodeService->generateQRSvg($document),
        ];
    }

    /**
     * Submeter documento à AGT (v1.2 — usa RegisterService).
     *
     * Faz mapping Eloquent → payload v1.2, submete e persiste:
     *   - jws_document_signature
     *   - agt_request_id
     *   - agt_submission_uuid
     *   - agt_status, agt_submitted_at
     */
    public function submitToAGT($document): array
    {
        try {
            if ((int) ($document->tenant_id ?? 0) !== $this->tenantId) {
                throw new \DomainException('O documento nao pertence ao tenant da operacao AGT.');
            }
            if ($document->relationLoaded('series') || method_exists($document, 'series')) {
                $series = $document->series;
                if ($series && (int) $series->tenant_id !== $this->tenantId) {
                    throw new \DomainException('A serie fiscal nao pertence ao tenant do documento.');
                }
            }
            $settings = \App\Models\Invoicing\InvoicingSettings::forTenant($this->tenantId);

            $submission = AGTSubmission::where('tenant_id', $this->tenantId)
                ->where('document_type', get_class($document))
                ->where('document_id', $document->id)
                ->orderByRaw("CASE status
                    WHEN 'validated' THEN 1
                    WHEN 'submitted' THEN 2
                    WHEN 'pending' THEN 3
                    ELSE 4 END")
                ->latest('id')
                ->first();

            /*
             * UMA SUBMISSÃO DE OUTRO AMBIENTE FICA QUIETA.
             *
             * O cliente HTTP fala sempre com o ambiente ACTIVO. Voltar a
             * homologação com documentos de produção por enviar mandava-os
             * para a AGT de testes, que os aceitava — e ficavam marcados
             * «validados» sem nunca terem existido para o fisco. O contrário
             * (testes a ir para a AGT real) é igualmente grave. Recusa-se aqui,
             * na porta por onde todos os envios passam; quando a empresa voltar
             * ao ambiente dela, a submissão segue como estava.
             */
            $ambienteActivo = GestaoAgt::normalizar($settings->agt_environment);

            if ($submission && !$submission->eDoAmbiente($ambienteActivo)) {
                $erro = sprintf(
                    'Este documento pertence a %s e a empresa emite agora em %s. Não foi enviado: fica à espera até a empresa voltar a %s.',
                    GestaoAgt::rotulo($submission->agt_environment),
                    GestaoAgt::rotulo($ambienteActivo),
                    GestaoAgt::rotulo($submission->agt_environment)
                );

                Log::warning('AGTService::submitToAGT: submissão de outro ambiente não enviada', [
                    'tenant_id'     => $this->tenantId,
                    'submission_id' => $submission->id,
                    'da_submissao'  => $submission->agt_environment,
                    'activo'        => $ambienteActivo,
                ]);

                return [
                    'success'         => false,
                    'requestID'       => null,
                    'submissionUUID'  => null,
                    'error'           => $erro,
                    'ambiente_errado' => true,
                ];
            }

            // Uma tentativa técnica não pode criar várias "submissões" para o
            // mesmo documento. Se a AGT já validou, nunca reenviar.
            if ($submission?->status === AGTSubmission::STATUS_VALIDATED) {
                $validatedFields = [
                    'agt_status' => 'validated',
                    'agt_reference' => $submission->agt_reference,
                    'agt_validated_at' => $submission->validated_at,
                ];
                $documentColumns = \Illuminate\Support\Facades\Schema::getColumnListing($document->getTable());
                $document->forceFill(array_intersect_key($validatedFields, array_flip($documentColumns)));
                if ($document->isDirty()) {
                    $document->save();
                }

                return [
                    'success' => true,
                    'requestID' => $submission->agt_reference,
                    'submissionUUID' => null,
                    'error' => null,
                    'payload' => $submission->request_payload ?? [],
                    'response' => $submission->response_payload ?? [],
                    'alreadyValidated' => true,
                ];
            }

            // Montar e assinar só depois de saber que vai mesmo seguir: a
            // recusa por ambiente, acima, não pode ficar escondida atrás de
            // uma chave em falta do ambiente errado.
            $register = new RegisterService($settings);
            $mapper   = new DocumentMapper();

            $docPayload = $mapper->map($document);

            if (!$submission) {
                $submission = AGTSubmission::createForDocument(
                    $document,
                    (string) ($docPayload['documentType'] ?? '')
                );
            } else {
                $submission->forceFill([
                    'status' => AGTSubmission::STATUS_PENDING,
                    'error_code' => null,
                    'error_message' => null,
                    'rejected_at' => null,
                ])->save();
            }
            $result     = $register->register([$docPayload]);

            // Persistir resposta no documento
            $sentDoc = $result['payload']['documents'][0] ?? null;

            $documentFields = [
                'jws_document_signature' => $sentDoc['jwsDocumentSignature'] ?? null,
                'agt_submission_uuid'    => $result['submissionUUID'],
                'agt_request_id'         => $result['requestID'],
                'agt_status'             => $result['ok'] ? 'submitted' : 'rejected',
                'agt_submitted_at'       => now(),
                'agt_reference'          => $result['requestID'] ?? $document->agt_reference,
            ];
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing($document->getTable());
            $document->forceFill(array_intersect_key($documentFields, array_flip($columns)));
            $document->save();

            // Sprint 3: enfileira polling de obterEstado (DS.120 §4.2).
            if ($result['ok'] && !empty($result['requestID'])) {
                $submission->markAsSubmitted($result['payload']);
                $submission->update([
                    'agt_reference' => $result['requestID'],
                    'response_payload' => $result['response'],
                ]);
                try {
                    \App\Jobs\AGT\PollAGTStatusJob::start(
                        $this->tenantId,
                        $submission->id,
                        $result['requestID']
                    );
                } catch (\Throwable $e) {
                    Log::warning('AGTService::submitToAGT: polling não enfileirado', [
                        'error' => $e->getMessage(),
                    ]);
                }
            } elseif (!$this->aAgtRecusouODocumento($result)) {
                // Não houve resposta, ou a AGT mandou abrandar (429), ou
                // respondeu sem dizer nada do documento (5xx, 401 sem errorList).
                //
                // Nenhum destes é uma recusa do documento. Marcá-los como
                // rejeitado tirava-o da lista de pendentes e nunca mais era
                // enviado.
                //
                // O 429 é o mais traiçoeiro: mediu-se um caso em que o registo
                // passou (requestID emitido, documento validado) e a chamada
                // seguinte, a um segundo de distância, levou 429 — e era essa
                // que o código tomava como resposta final. Um documento aceite
                // pela AGT ficava marcado como recusado.
                $submission->markAsCommunicationFailure(
                    (string) ($result['error'] ?: 'Falha de comunicação com a AGT')
                );

                Log::warning('AGTService: falha de comunicação, submissão fica pendente', [
                    'tenant_id'     => $this->tenantId,
                    'submission_id' => $submission->id,
                    'erro'          => $result['error'] ?? null,
                ]);
            } else {
                // A AGT respondeu e recusou. Isso sim é uma recusa — e é um
                // envio, que conta tentativa: sem isto uma recusa na hora não
                // gastava nenhuma e o botão «Reenviar» ficava para sempre
                // disponível para mandar o mesmo documento, igual.
                //
                // O código gravado é o da AGT (E43, E70…) quando ela o dá; o
                // AGT_REGISTER fica só para a recusa sem código.
                $submission->markAsRejected(
                    (string) (($result['error_code'] ?? null) ?: 'AGT_REGISTER'),
                    (string) ($result['error'] ?: 'Submissao rejeitada pela AGT'),
                    $result['response'] ?? [],
                    contarTentativa: true
                );
            }

            return [
                'success'        => $result['ok'],
                'requestID'      => $result['requestID'],
                'submissionUUID' => $result['submissionUUID'],
                'error'          => $result['error'],
                'payload'        => $result['payload'],
                'response'       => $result['response'],
            ];
        } catch (\Throwable $e) {
            Log::error('AGTService::submitToAGT (v1.2) falhou', [
                'document_id' => $document->id ?? null,
                'error'       => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Verificar estado na AGT
     */
    public function checkAGTStatus(string $agtReference): array
    {
        return $this->getClient()->getStatus($agtReference);
    }

    // =========================================
    // VALIDAR DOCUMENTO (Adquirente — DS.120 §4.7)
    // =========================================

    /**
     * Confirmar documento recebido (Adquirente).
     *
     * @param string $documentNo Identificador AGT do documento
     * @param float|null $deductibleVATPercentage % IVA dedutível (exclusivo com $nonDeductibleAmount)
     * @param float|null $nonDeductibleAmount     Valor IVA não dedutível (exclusivo)
     */
    public function confirmReceivedDocument(
        string $documentNo,
        ?float $deductibleVATPercentage = null,
        ?float $nonDeductibleAmount = null
    ): array {
        try {
            $settings = \App\Models\Invoicing\InvoicingSettings::forTenant($this->tenantId);
            $service  = new ValidateService($settings);
            return $service->confirm($documentNo, $deductibleVATPercentage, $nonDeductibleAmount);
        } catch (\Throwable $e) {
            Log::error('AGTService::confirmReceivedDocument falhou', [
                'documentNo' => $documentNo,
                'error'      => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Rejeitar documento recebido (Adquirente). */
    public function rejectReceivedDocument(string $documentNo): array
    {
        try {
            $settings = \App\Models\Invoicing\InvoicingSettings::forTenant($this->tenantId);
            $service  = new ValidateService($settings);
            return $service->reject($documentNo);
        } catch (\Throwable $e) {
            Log::error('AGTService::rejectReceivedDocument falhou', [
                'documentNo' => $documentNo,
                'error'      => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================
    // GESTÃO DE SÉRIES
    // =========================================

    /**
     * Registar série na AGT
     */
    public function registerSeries(InvoicingSeries $series): array
    {
        if ((int) $series->tenant_id !== $this->tenantId) {
            return [
                'success' => false,
                'error' => 'A série não pertence à empresa activa.',
            ];
        }
        if (!$series->isAGTEligible()) {
            return [
                'success' => false,
                'error' => 'Este tipo documental não utiliza série fiscal AGT.',
            ];
        }

        return $this->getClient()->requestSeries($series);
    }

    /**
     * Sincronizar todas as séries do tenant com a AGT
     */
    /**
     * Regista na AGT as séries fiscais que ainda não estão registadas NO
     * AMBIENTE ACTIVO.
     *
     * Pegava só nas que não tinham código nenhum. Uma série registada em
     * homologação tem código — o de homologação —, e por isso a empresa que
     * passava a produção carregava em «Sincronizar», via «0 pendentes» e
     * ficava sem série nenhuma para emitir: o getIssuanceSeries() recusa, e
     * bem, um código que a AGT de produção desconhece. Entram também as do
     * outro ambiente, e o código novo substitui o antigo.
     *
     * @return array{total:int, success:int, failed:int, details: array<int, array{serie_id:int, codigo:string, ok:bool, erro:?string, codigo_erro:?string, ambiente_anterior:?string, recuperada:bool}>}
     */
    public function syncAllSeries(): array
    {
        // O ambiente em que o registo vai de facto: o do cliente que o envia.
        $ambiente = GestaoAgt::normalizar($this->getClient()->getEnvironment());

        InvoicingSeries::where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->whereIn('prefix', InvoicingSeries::AGT_DOCUMENT_TYPES)
            ->whereNotNull('agt_series_id')
            ->where('agt_environment', $ambiente)
            ->whereNull('atcud_validation_code')
            ->get()
            ->each(function (InvoicingSeries $series): void {
                $series->forceFill([
                    'atcud_validation_code' => $series->agt_series_id,
                    'agt_status' => 'active',
                    'agt_series_status' => $series->agt_series_status ?: InvoicingSeries::AGT_STATUS_OPEN,
                ])->save();
            });

        $series = InvoicingSeries::where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->whereIn('prefix', InvoicingSeries::AGT_DOCUMENT_TYPES)
            ->where(fn ($q) => $q->whereNull('agt_series_id')
                ->orWhereNull('agt_environment')
                ->orWhere('agt_environment', '!=', $ambiente))
            ->get();

        $results = [
            'total' => $series->count(),
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($series as $s) {
            $codigo = trim($s->prefix . ' ' . $s->series_code);
            $doOutroAmbiente = filled($s->agt_series_id) && $s->agt_environment !== $ambiente;
            $anterior = $doOutroAmbiente ? $s->agt_environment : null;

            // Recuperar o código de uma resposta já recebida só vale se essa
            // resposta for DESTE ambiente. A de homologação guardada numa série
            // que agora vai para produção devolvia-lhe o código de testes.
            $storedSeriesCode = !$doOutroAmbiente && $s->agt_environment === $ambiente
                ? data_get($s->agt_response, 'seriesFEResult.seriesCode')
                : null;

            if (filled($storedSeriesCode)) {
                $s->forceFill([
                    'agt_series_id' => $storedSeriesCode,
                    'atcud_validation_code' => $storedSeriesCode,
                    'agt_status' => 'active',
                    'agt_series_status' => InvoicingSeries::AGT_STATUS_OPEN,
                    'agt_registered_at' => $s->agt_registered_at ?: now(),
                ])->save();

                $results['success']++;
                $results['details'][] = [
                    'serie_id' => $s->id,
                    'codigo' => $codigo,
                    'ok' => true,
                    'erro' => null,
                    'codigo_erro' => null,
                    'ambiente_anterior' => null,
                    'recuperada' => true,
                ];
                continue;
            }

            $result = $this->registerSeries($s);
            $ok = (bool) ($result['success'] ?? false);

            if ($ok) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = [
                'serie_id' => $s->id,
                'codigo' => $codigo,
                'ok' => $ok,
                'erro' => $ok ? null : (string) ($result['error'] ?? 'A AGT não registou a série.'),
                'codigo_erro' => $ok ? null : AGTErrorCode::primeiroCodigo($result['data'] ?? []),
                'ambiente_anterior' => $anterior,
                'recuperada' => false,
            ];
        }

        return $results;
    }

    // =========================================
    // VALIDAÇÕES
    // =========================================

    /**
     * Validar se documento pode ser anulado (regra 45 dias)
     */
    public function canCancel($document): array
    {
        $issueDate = $document->invoice_date ?? $document->issue_date ?? now();
        $daysSinceIssue = $issueDate->diffInDays(now());
        $maxDays = 45;

        $canCancel = $daysSinceIssue <= $maxDays;
        
        return [
            'can_cancel' => $canCancel,
            'days_since_issue' => $daysSinceIssue,
            'max_days' => $maxDays,
            'reason' => $canCancel 
                ? 'Documento pode ser anulado' 
                : "Prazo de {$maxDays} dias excedido. Use Nota de Crédito.",
            'requires_credit_note' => !$canCancel,
        ];
    }

    /**
     * Validar documento para conformidade AGT
     */
    public function validateDocument($document): array
    {
        $errors = [];
        $warnings = [];

        // Verificar campos obrigatórios
        if (empty($document->invoice_number ?? $document->credit_note_number ?? $document->debit_note_number)) {
            $errors[] = 'Número do documento não definido';
        }

        if (empty($document->client_id)) {
            $errors[] = 'Cliente não definido';
        }

        if (empty($document->hash) && empty($document->saft_hash)) {
            $warnings[] = 'Hash não gerado';
        }

        if (empty($document->jws_signature)) {
            $warnings[] = 'Assinatura JWS não gerada';
        }

        if (empty($document->atcud)) {
            $warnings[] = 'ATCUD não definido';
        }

        // Verificar série
        if ($document->series && empty($document->series->agt_series_id)) {
            $warnings[] = 'Série não registada na AGT';
        }

        // Verificar NIF cliente
        $clientNif = $document->client?->nif;
        if (empty($clientNif)) {
            $warnings[] = 'Cliente sem NIF (será usado 999999999)';
        }

        // Verificar valores
        $total = $document->gross_total ?? $document->total ?? 0;
        if ($total <= 0) {
            $errors[] = 'Total do documento inválido';
        }

        return [
            'valid' => empty($errors),
            'compliant' => empty($errors) && empty($warnings),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    // =========================================
    // RELATÓRIO DE CONFORMIDADE
    // =========================================

    /**
     * Gerar relatório de conformidade AGT para o tenant
     */
    /**
     * @param string|null $ambienteOverride Ambiente a reportar. Serve para o
     *   ecrã reflectir o seletor antes de este ser guardado.
     */
    public function getComplianceReport(?string $ambienteOverride = null): array
    {
        $report = [
            'tenant_id' => $this->tenantId,
            'generated_at' => now()->toDateTimeString(),
            'keys_configured' => $this->signatureService->hasKeys(),
            'api_configured' => $this->getClient()->isConfigured(),
            'environment' => $this->getClient()->getEnvironment(),
        ];

        // Tudo o que segue é POR AMBIENTE: uma série registada em homologação
        // não conta como registada em produção, e os cartões do topo diziam
        // "8/8 registadas" mesmo com o ecrã em Produção.
        $ambiente = in_array($ambienteOverride, AGTKeyStore::AMBIENTES, true)
            ? $ambienteOverride
            : AGTKeyStore::ambiente((int) $this->tenantId);

        // Estatísticas de séries
        $series = InvoicingSeries::where('tenant_id', $this->tenantId)
            ->whereIn('prefix', InvoicingSeries::AGT_DOCUMENT_TYPES)
            ->get();
        $registadas = $series->filter(
            fn ($s) => !empty($s->agt_series_id) && $s->agt_environment === $ambiente
        );
        $report['series'] = [
            'total' => $series->count(),
            'registered' => $registadas->count(),
            'pending' => $series->count() - $registadas->count(),
        ];

        // Estatísticas de submissões
        $submissions = AGTSubmission::where('tenant_id', $this->tenantId)
            ->where('agt_environment', $ambiente)
            ->get();
        $report['submissions'] = [
            'total' => $submissions->count(),
            'pending' => $submissions->where('status', 'pending')->count(),
            'submitted' => $submissions->where('status', 'submitted')->count(),
            'validated' => $submissions->where('status', 'validated')->count(),
            'rejected' => $submissions->where('status', 'rejected')->count(),
        ];

        // Estatísticas de faturas (últimos 30 dias)
        $thirtyDaysAgo = now()->subDays(30);
        $invoices = SalesInvoice::where('tenant_id', $this->tenantId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->get();

        $report['invoices_30_days'] = [
            'total' => $invoices->count(),
            'with_hash' => $invoices->filter(fn($i) => !empty($i->hash) || !empty($i->saft_hash))->count(),
            'with_jws' => $invoices->filter(fn($i) => !empty($i->jws_signature))->count(),
            'with_atcud' => $invoices->filter(fn($i) => !empty($i->atcud))->count(),
            'agt_validated' => $invoices->where('agt_status', 'validated')->count(),
        ];

        return $report;
    }

    // =========================================
    // HELPERS
    // =========================================

    /**
     * É um veredicto da AGT sobre o documento, ou só uma conversa que falhou?
     *
     * Recusa é a AGT dizer o que está mal: vem com `errorList`. Sem resposta
     * (0), abrandar (429), um erro dela (5xx) ou credenciais recusadas sem
     * lista não dizem nada do documento — ficam por repetir, não recusados.
     */
    private function aAgtRecusouODocumento(array $result): bool
    {
        $estado = (int) ($result['status'] ?? 0);

        if ($estado === 0 || $estado === 429) {
            return false;
        }

        return AGTErrorCode::lista($result['response']['errorList'] ?? []) !== []
            || filled($result['error_code'] ?? null);
    }

    private function getDocumentTypeCode($document): string
    {
        if ($document instanceof SalesInvoice) {
            return $document->invoice_type ?? 'FT';
        }
        
        if ($document instanceof CreditNote) {
            return 'NC';
        }
        
        if ($document instanceof DebitNote) {
            return 'ND';
        }

        $class = get_class($document);
        
        return match (true) {
            str_contains($class, 'Receipt') => 'RC',
            str_contains($class, 'Proforma') => 'FP',
            default => 'FT',
        };
    }

    /**
     * Testar conectividade com a AGT
     */
    public function testConnection(): array
    {
        return $this->getClient()->testConnection();
    }
}
