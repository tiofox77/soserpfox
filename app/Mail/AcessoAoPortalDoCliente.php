<?php

namespace App\Mail;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Boas-vindas ao portal do cliente, com os dados de entrada.
 *
 * NÃO implementa ShouldQueue de propósito: neste sistema não há worker de
 * fila, e um email em fila é um email que nunca sai (mesma razão da
 * NotificacaoDeModelo).
 */
class AcessoAoPortalDoCliente extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Client $cliente,
        public string $senha,
    ) {
    }

    public function envelope(): Envelope
    {
        $empresa = $this->cliente->tenant?->name ?: config('app.name');

        return new Envelope(
            subject: 'Os seus dados de acesso — ' . $empresa,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.acesso-portal-cliente',
            with: [
                'cliente' => $this->cliente,
                'senha'   => $this->senha,
                'empresa' => $this->cliente->tenant?->name ?: config('app.name'),
                'url'     => route('client.login'),
            ],
        );
    }
}
