<?php

namespace App\Jobs\AGT;

use App\Models\AGT\AGTSubmission;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\User;
use App\Notifications\AGT\AGTSubmissionFailedNotification;
use App\Services\AGT\AGTErrorCode;
use App\Services\AGT\QueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Job de polling do estado de uma submissão AGT (DS.120 §4.2).
 *
 * Modelo assíncrono:
 *   1. RegistarFactura → devolve requestID
 *   2. Job é enfileirado com delay (15s default)
 *   3. ObterEstado → resultCode:
 *        0 = Sem inválidas (sucesso)
 *        1 = Misto (algumas inválidas)
 *        2 = Sem válidas (todas inválidas)
 *        7 = Prematuro (re-enfileirar)
 *        8 = Em curso (re-enfileirar)
 *        9 = Cancelado
 *   4. Persiste estado em AGTSubmission e marca documento.
 */
class PollAGTStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Atraso inicial (segundos) antes da primeira chamada. */
    public const INITIAL_DELAY_SECONDS = 15;

    /** Atraso entre re-tentativas (resultCode 7/8). */
    public const RETRY_DELAY_SECONDS = 30;

    /** Máximo de tentativas (≈ 30 * 30s = 15 min). */
    public int $tries = 30;

    /** Timeout em segundos. */
    public int $timeout = 60;

    public function __construct(
        public int $tenantId,
        public int $submissionId,
        public string $requestID,
        public int $attemptNo = 1,
    ) {
        $this->onQueue('agt-polling');
    }

    public function handle(): void
    {
        $submission = AGTSubmission::find($this->submissionId);
        if (!$submission) {
            Log::warning('PollAGTStatusJob: submission não encontrada', [
                'submission_id' => $this->submissionId,
            ]);
            return;
        }
        if ((int) $submission->tenant_id !== $this->tenantId) {
            Log::error('PollAGTStatusJob: tenant da submissao nao corresponde ao job', [
                'submission_id' => $this->submissionId,
                'job_tenant' => $this->tenantId,
                'submission_tenant' => $submission->tenant_id,
            ]);
            return;
        }

        // Já concluída?
        if (in_array($submission->status, [
            AGTSubmission::STATUS_VALIDATED,
            AGTSubmission::STATUS_REJECTED,
            AGTSubmission::STATUS_CANCELLED,
        ], true)) {
            return;
        }

        $settings = InvoicingSettings::forTenant($this->tenantId);
        if (!$settings) {
            Log::error('PollAGTStatusJob: settings não encontradas', ['tenant' => $this->tenantId]);
            return;
        }

        // A consulta vai à AGT do ambiente ACTIVO. Um requestID de produção
        // perguntado à de testes (ou o contrário) não tem resposta verdadeira —
        // e um «0» de lá dava por validado um documento que o fisco nunca viu.
        // Fica quieta; o despacho retoma-a quando a empresa voltar ao ambiente.
        if (!$submission->eDoAmbiente(\App\Services\AGT\GestaoAgt::normalizar($settings->agt_environment))) {
            Log::info('PollAGTStatusJob: submissão de outro ambiente, não consultada', [
                'submission_id' => $this->submissionId,
                'da_submissao'  => $submission->agt_environment,
                'activo'        => $settings->agt_environment,
            ]);
            return;
        }

        $service = new QueryService($settings);
        $result  = $service->consultByRequestId($this->requestID);

        $body       = $result['response'] ?? [];
        $resultCode = $body['resultCode'] ?? null;
        // Os erros do PEDIDO e os de CADA DOCUMENTO (documentStatusList[].errorList).
        // Lia-se só os primeiros, e é nos segundos que vêm o E43 e o E70: a
        // recusa ficava gravada sem código nem descrição.
        $errorList  = collect(AGTErrorCode::lista($body))
            ->map(fn (array $e) => array_filter([
                'idError'          => $e['codigo'],
                'descriptionError' => $e['descricao'],
                'documentNo'       => $e['documento'],
            ], fn ($v) => $v !== null))
            ->all();

        Log::info('PollAGTStatusJob: estado', [
            'requestID'  => $this->requestID,
            'attempt'    => $this->attemptNo,
            'resultCode' => $resultCode,
        ]);

        switch ((string) $resultCode) {
            case '0': // Sem inválidas
                $submission->markAsValidated($this->requestID, null, $body);
                break;

            case '1': // Misto
                $submission->update([
                    'status'           => AGTSubmission::STATUS_VALIDATED,
                    'validated_at'     => now(),
                    'response_payload' => $body,
                    'error_message'    => AGTErrorCode::formatList($errorList),
                ]);
                $this->notifyTenantAdmins($submission, $errorList, 'Submissão com erros parciais (resultCode 1)');
                break;

            case '2': // Sem válidas
                $submission->markAsRejected(
                    AGTErrorCode::primeiroCodigo($body) ?? 'RESULT_CODE_2',
                    AGTErrorCode::formatList($errorList) ?: 'Todos os documentos foram considerados inválidos.',
                    $body
                );
                $this->notifyTenantAdmins($submission, $errorList, 'Submissão totalmente rejeitada (resultCode 2)');
                break;

            case '7': // Prematuro
            case '8': // Em curso
                if ($this->attemptNo >= $this->tries) {
                    Log::warning('PollAGTStatusJob: limite de tentativas atingido', [
                        'requestID' => $this->requestID,
                    ]);
                    return;
                }
                self::dispatch(
                    $this->tenantId,
                    $this->submissionId,
                    $this->requestID,
                    $this->attemptNo + 1
                )->delay(now()->addSeconds(self::RETRY_DELAY_SECONDS));
                break;

            case '9': // Cancelado
                $submission->update([
                    'status'           => AGTSubmission::STATUS_CANCELLED,
                    'response_payload' => $body,
                ]);
                break;

            default:
                // Resposta inesperada — re-tentar uma vez
                if ($this->attemptNo < 3) {
                    self::dispatch(
                        $this->tenantId,
                        $this->submissionId,
                        $this->requestID,
                        $this->attemptNo + 1
                    )->delay(now()->addSeconds(self::RETRY_DELAY_SECONDS));
                } else {
                    Log::warning('PollAGTStatusJob: resultCode desconhecido', [
                        'requestID'  => $this->requestID,
                        'resultCode' => $resultCode,
                    ]);
                }
                break;
        }
    }

    /**
     * Atalho: enfileira primeiro polling com delay inicial.
     */
    public static function start(int $tenantId, int $submissionId, string $requestID): void
    {
        self::dispatch($tenantId, $submissionId, $requestID, 1)
            ->delay(now()->addSeconds(self::INITIAL_DELAY_SECONDS));
    }

    /**
     * Notifica admins/responsáveis financeiros do tenant por email.
     * Lê emails configurados em `agt_notification_emails` (CSV) ou usa
     * todos os utilizadores com permissão `invoicing.agt.view`.
     */
    private function notifyTenantAdmins(AGTSubmission $submission, array $errorList, string $context): void
    {
        try {
            $settings = InvoicingSettings::forTenant($this->tenantId);
            $emails   = $this->resolveNotificationEmails($settings);
            if (empty($emails)) {
                return;
            }

            Notification::route('mail', $emails)
                ->notify(new AGTSubmissionFailedNotification($submission, $errorList, $context));
        } catch (\Throwable $e) {
            Log::warning('PollAGTStatusJob: falha ao notificar admins', [
                'submission_id' => $submission->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /** Resolve a lista de emails para notificar (settings ou utilizadores). */
    private function resolveNotificationEmails(?InvoicingSettings $settings): array
    {
        // 1) Override explícito em settings (CSV)
        $csv = $settings?->agt_notification_emails ?? null;
        if (!empty($csv)) {
            return array_filter(array_map('trim', explode(',', $csv)));
        }

        // 2) Fallback: utilizadores do tenant com permissão AGT
        try {
            return User::where('tenant_id', $this->tenantId)
                ->whereNotNull('email')
                ->get()
                ->filter(fn ($u) => method_exists($u, 'can') && $u->can('invoicing.agt.view'))
                ->pluck('email')
                ->unique()
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
