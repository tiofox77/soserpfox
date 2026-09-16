<?php

namespace App\Mail\Revenda;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * UM AVISO DO PROGRAMA DE REVENDEDORES (16/09/2026).
 *
 * Um molde só para todos: pedido recebido, aprovado, recusado, pagamento de
 * comissões, empresa criada pelo revendedor. Sem fila (não há worker neste
 * sistema — um email em fila nunca sai).
 *
 * @param  list<string>  $linhas  parágrafos
 * @param  array<string, string>  $dados  a tabela de dados (rótulo → valor)
 * @param  array{texto:string, url:string}|null  $botao
 */
class AvisoDaRevenda extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $assunto,
        public string $saudacao,
        public array $linhas,
        public array $dados = [],
        public ?array $botao = null,
        public ?string $alerta = null,
        public string $cor = '#7c3aed',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->assunto . ' — ' . app_name());
    }

    public function content(): Content
    {
        return new Content(view: 'emails.revenda', with: [
            'plataforma' => app_name(),
            'saudacao' => $this->saudacao,
            'linhas' => $this->linhas,
            'dados' => $this->dados,
            'botao' => $this->botao,
            'alerta' => $this->alerta,
            'cor' => $this->cor,
        ]);
    }
}
