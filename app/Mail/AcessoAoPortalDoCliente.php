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

    /** @param  string|null  $senha  nula quando só mudaram os dados de entrada (a senha é a de antes) */
    public function __construct(
        public Client $cliente,
        public ?string $senha,
    ) {
    }

    public function envelope(): Envelope
    {
        $empresa = $this->cliente->tenant?->name ?: config('app.name');

        return new Envelope(
            subject: ($this->senha !== null ? __('Os seus dados de acesso') : __('Os seus dados de acesso foram actualizados')) . ' — ' . $empresa,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.acesso-portal-cliente',
            with: [
                'cliente' => $this->cliente,
                'senha'   => $this->senha,
                // As três maneiras de entrar: email, telefone e nome de utilizador.
                'telefone' => $this->cliente->portal_phone ? ($this->cliente->mobile ?: $this->cliente->phone) : null,
                'utilizador' => $this->cliente->portal_username,
                'empresa' => $this->cliente->tenant?->name ?: config('app.name'),
                'url'     => route('client.login'),
            ],
        );
    }
}
