<?php

namespace App\Jobs\AGT;

use App\Services\AGT\AGTClient;
use App\Models\AGT\AGTSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckAGTStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    private int $submissionId;

    public function __construct(int $submissionId)
    {
        $this->submissionId = $submissionId;
    }

    public function handle(): void
    {
        $submission = AGTSubmission::find($this->submissionId);

        if (!$submission) {
            Log::warning('CheckAGTStatus: Submissão não encontrada', ['id' => $this->submissionId]);
            return;
        }

        // Só verificar se está submetido
        if ($submission->status !== AGTSubmission::STATUS_SUBMITTED) {
            return;
        }

        if (empty($submission->agt_reference)) {
            Log::warning('CheckAGTStatus: Sem referência AGT', ['id' => $this->submissionId]);
            return;
        }

        try {
            $client = new AGTClient($submission->tenant_id);
            $result = $client->getStatus($submission->agt_reference);

            if ($result['success']) {
                $status = $result['data']['status'] ?? 'unknown';
                
                if ($status === 'validated') {
                    $submission->markAsValidated(
                        $submission->agt_reference,
                        $result['data']['atcud'] ?? $submission->atcud,
                        $result['data']
                    );
                    Log::info('CheckAGTStatus: Documento validado', ['id' => $this->submissionId]);
                    
                } elseif ($status === 'rejected') {
                    $submission->markAsRejected(
                        $result['data']['error_code'] ?? 'REJECTED',
                        $result['data']['error_message'] ?? 'Documento rejeitado pela AGT',
                        $result['data']
                    );
                    Log::info('CheckAGTStatus: Documento rejeitado', ['id' => $this->submissionId]);
                }
                // Se ainda pendente, será verificado novamente pelo scheduler
            }

        } catch (\Exception $e) {
            Log::error('CheckAGTStatus: Erro', [
                'id' => $this->submissionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
