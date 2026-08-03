<?php

namespace App\Notifications\AGT;

use App\Models\AGT\AGTSubmission;
use App\Services\AGT\AGTErrorCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email enviado quando uma submissão à AGT é rejeitada ou contém erros.
 *
 * Disparado por:
 *  - PollAGTStatusJob (resultCode 1 ou 2)
 *  - AGTService::submitToAGT (falha imediata)
 */
class AGTSubmissionFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AGTSubmission $submission,
        public array $errorList = [],
        public ?string $context = null,
    ) {
        $this->onQueue('agt-notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = sprintf(
            '[AGT] Submissão %s rejeitada — %s',
            strtoupper($this->submission->status),
            $this->submission->document_number ?? $this->submission->agt_reference ?? '(sem ref)'
        );

        $mail = (new MailMessage)
            ->subject($subject)
            ->error()
            ->greeting('Submissão AGT com erros')
            ->line(sprintf(
                'A submissão **%s** (tipo `%s`) foi rejeitada ou contém erros pela AGT.',
                $this->submission->document_number ?? '—',
                $this->submission->document_type_code ?? '—'
            ));

        if ($this->context) {
            $mail->line('**Contexto:** ' . $this->context);
        }

        $mail->line('**Referência AGT:** ' . ($this->submission->agt_reference ?? '—'));
        $mail->line('**Estado:** ' . strtoupper($this->submission->status));

        if (!empty($this->errorList)) {
            $mail->line('**Erros reportados:**');
            $items = AGTErrorCode::toArray($this->errorList);
            foreach ($items as $err) {
                $mail->line(sprintf('• [%s] %s', $err['code'] ?: 'ERR', $err['message']));
            }
        } elseif ($this->submission->error_message) {
            $mail->line('**Erro:** ' . $this->submission->error_message);
        }

        $mail->action('Abrir painel AGT', url('/invoicing/agt-adquirente'))
             ->line('Por favor reveja o documento e corrija antes de resubmeter.')
             ->salutation('— SOSERP / AGT');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'submission_id'   => $this->submission->id,
            'document_number' => $this->submission->document_number,
            'agt_reference'   => $this->submission->agt_reference,
            'status'          => $this->submission->status,
            'error_message'   => $this->submission->error_message,
            'errors'          => AGTErrorCode::toArray($this->errorList),
            'context'         => $this->context,
        ];
    }
}
