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
        $this->signatureService = new SignatureService();
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
     * Submeter documento à AGT
     */
    public function submitToAGT($document): array
    {
        $documentType = $this->getDocumentTypeCode($document);
        return $this->getClient()->registerInvoice($document, $documentType);
    }

    /**
     * Verificar estado na AGT
     */
    public function checkAGTStatus(string $agtReference): array
    {
        return $this->getClient()->getStatus($agtReference);
    }

    // =========================================
    // GESTÃO DE SÉRIES
    // =========================================

    /**
     * Registar série na AGT
     */
    public function registerSeries(InvoicingSeries $series): array
    {
        return $this->getClient()->requestSeries($series);
    }

    /**
     * Sincronizar todas as séries do tenant com a AGT
     */
    public function syncAllSeries(): array
    {
        $series = InvoicingSeries::where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->whereNull('agt_series_id')
            ->get();

        $results = [
            'total' => $series->count(),
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($series as $s) {
            $result = $this->registerSeries($s);
            
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
            
            $results['details'][] = [
                'series' => $s->prefix . ' ' . $s->series_code,
                'result' => $result,
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
    public function getComplianceReport(): array
    {
        $report = [
            'tenant_id' => $this->tenantId,
            'generated_at' => now()->toDateTimeString(),
            'keys_configured' => $this->signatureService->hasKeys(),
            'api_configured' => $this->getClient()->isConfigured(),
            'environment' => $this->getClient()->getEnvironment(),
        ];

        // Estatísticas de séries
        $series = InvoicingSeries::where('tenant_id', $this->tenantId)->get();
        $report['series'] = [
            'total' => $series->count(),
            'registered' => $series->whereNotNull('agt_series_id')->count(),
            'pending' => $series->whereNull('agt_series_id')->count(),
        ];

        // Estatísticas de submissões
        $submissions = AGTSubmission::where('tenant_id', $this->tenantId)->get();
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
