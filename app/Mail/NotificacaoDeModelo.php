<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * O email de uma notificação de modelo.
 *
 * O envio era feito com `Mail::send([], [], $callback)` — mensagem em bruto.
 * Funciona, mas fica invisível a quem tenta verificá-lo: o `Mail::fake()` dos
 * testes só regista Mailables, e uma mensagem bruta passa sem deixar rasto.
 * Com uma classe própria o envio é observável, e o assunto e o corpo podem ser
 * conferidos.
 *
 * NÃO implementa ShouldQueue de propósito: neste sistema não há worker de fila,
 * e um email em fila é um email que nunca sai. O envio corre à boleia do
 * pedido, em `terminate`, depois de a resposta seguir para o browser.
 */
class NotificacaoDeModelo extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $assuntoDaNotificacao,
        public string $corpoHtml,
        public ?string $remetente = null,
        public ?string $nomeRemetente = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            subject: $this->assuntoDaNotificacao ?: 'Notificação',
        );

        if ($this->remetente) {
            $envelope->from($this->remetente, $this->nomeRemetente ?: config('app.name'));
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->corpoHtml);
    }
}
