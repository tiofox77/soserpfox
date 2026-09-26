<?php

namespace App\Services\Casca;

use App\Models\AnalyticsEvent;
use App\Models\Support\Ticket;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Privacidade\Consentimentos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * «PRECISO DE AJUDA PARA COMEÇAR» (26/09/2026).
 *
 * Quem acabou de se registar e não sabe por onde pegar carrega no botão da
 * página inicial. Daqui sai:
 *
 *  · um PEDIDO DE SUPORTE, sempre — é o que garante que uma pessoa da equipa
 *    vê o pedido, com ou sem WhatsApp. Dois cliques seguidos não abrem dois:
 *    o pedido aberto nas últimas 24 horas é devolvido;
 *  · a ESCOLHA DO WHATSAPP, sempre gravada — o «sim» com o número, a versão do
 *    texto lido e a finalidade; o «não» também, porque a recusa é o que impede
 *    o agente de escrever a quem não quer;
 *  · um EVENTO `pedido_de_ajuda` na analítica, para medir quantos pedem ajuda.
 *
 * NÃO ENVIA MENSAGEM NENHUMA. A mensagem de WhatsApp é do agente (OpenClaw),
 * que lê o consentimento pelo `contactos` e tem as suas próprias regras.
 */
final class PedidoDeAjuda
{
    public const ASSUNTO = 'Ajuda para começar';

    public const EVENTO = 'pedido_de_ajuda';

    /**
     * @param  array{mensagem?: string|null, whatsapp: bool, telefone?: string|null, modulo?: string|null}  $dados
     * @return array{ticket: Ticket, novo: bool}
     */
    public function pedir(User $user, Tenant $empresa, array $dados, Request $request): array
    {
        $whatsapp = (bool) $dados['whatsapp'];
        $contacto = $whatsapp ? ($dados['telefone'] ?? null) : null;

        Consentimentos::registar(Consentimentos::WHATSAPP, $whatsapp, 'pedido_de_ajuda', $user, null, $request, [
            'tenant_id' => $empresa->id,
            'contacto' => $contacto,
        ]);

        $existente = Ticket::where('tenant_id', $empresa->id)
            ->where('user_id', $user->id)
            ->where('subject', 'like', self::ASSUNTO . '%')
            ->whereIn('status', ['open', 'in_progress'])
            ->where('created_at', '>=', now()->subDay())
            ->latest('id')
            ->first();

        if ($existente) {
            return ['ticket' => $existente, 'novo' => false];
        }

        $ticket = DB::transaction(fn () => Ticket::create([
            'tenant_id' => $empresa->id,
            'user_id' => $user->id,
            'ticket_number' => Ticket::proximoNumero($empresa->id),
            'subject' => self::ASSUNTO . (! empty($dados['modulo']) ? ' — ' . $dados['modulo'] : ''),
            'description' => $this->descricao($dados, $contacto),
            'priority' => 'medium',
            'category' => 'other',
            'status' => 'open',
        ]));

        $this->medir($user, $empresa, $dados, $whatsapp);

        return ['ticket' => $ticket, 'novo' => true];
    }

    private function descricao(array $dados, ?string $contacto): string
    {
        $texto = trim((string) ($dados['mensagem'] ?? ''));

        return ($texto !== '' ? $texto : 'Pedido feito no botão «Preciso de ajuda para começar» da página inicial.')
            . "\n\n"
            . ($contacto
                ? 'Aceitou ser contactado por WhatsApp para este fim, no número ' . $contacto . '.'
                : 'Não aceitou contacto por WhatsApp: responder por aqui ou por email.');
    }

    /** Melhor esforço: a analítica nunca impede o pedido. */
    private function medir(User $user, Tenant $empresa, array $dados, bool $whatsapp): void
    {
        try {
            AnalyticsEvent::create([
                'visitor_id' => (string) Str::uuid(),
                'session_id' => (string) Str::uuid(),
                'type' => 'conversion',
                'event_name' => self::EVENTO,
                'path' => '/home',
                'user_id' => $user->id,
                'tenant_id' => $empresa->id,
                'anonimo' => ! Consentimentos::permite('estatisticas'),
                'meta' => ['modulo' => $dados['modulo'] ?? null, 'whatsapp' => $whatsapp],
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
