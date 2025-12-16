<?php

namespace App\Jobs\AGT;

use App\Services\AGT\AGTService;
use App\Models\AGT\AGTSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SubmitDocumentToAGT implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 120;

    private string $documentClass;
    private int $documentId;
    private int $tenantId;

    public function __construct(string $documentClass, int $documentId, int $tenantId)
    {
        $this->documentClass = $documentClass;
        $this->documentId = $documentId;
        $this->tenantId = $tenantId;
    }

    public function handle(): void
    {
        Log::info('SubmitDocumentToAGT: Iniciando', [
            'document_class' => $this->documentClass,
            'document_id' => $this->documentId,
        ]);

        try {
            $document = $this->documentClass::find($this->documentId);

            if (!$document) {
                Log::error('SubmitDocumentToAGT: Documento não encontrado');
                return;
            }

            // Verificar se já foi validado
            if ($document->agt_status === 'validated') {
                Log::info('SubmitDocumentToAGT: Documento já validado');
                return;
            }

            $agtService = new AGTService($this->tenantId);
            $result = $agtService->submitToAGT($document);

            if ($result['success']) {
                Log::info('SubmitDocumentToAGT: Sucesso', [
                    'agt_reference' => $result['agt_reference'] ?? null,
                ]);
            } else {
                Log::warning('SubmitDocumentToAGT: Falha', [
                    'error' => $result['error'] ?? 'Erro desconhecido',
                ]);
                
                // Re-lançar para retry se não for erro definitivo
                if ($this->shouldRetry($result)) {
                    throw new \Exception($result['error'] ?? 'Erro ao submeter');
                }
            }

        } catch (\Exception $e) {
            Log::error('SubmitDocumentToAGT: Exception', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function shouldRetry(array $result): bool
    {
        $permanentErrors = ['INVALID_DOCUMENT', 'DUPLICATE', 'INVALID_SIGNATURE'];
        $errorCode = $result['error_code'] ?? '';
        
        return !in_array($errorCode, $permanentErrors);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SubmitDocumentToAGT: Job falhou permanentemente', [
            'document_class' => $this->documentClass,
            'document_id' => $this->documentId,
            'error' => $exception->getMessage(),
        ]);

        // Marcar documento como falha
        $document = $this->documentClass::find($this->documentId);
        if ($document) {
            $document->update(['agt_status' => 'failed']);
        }
    }
}
