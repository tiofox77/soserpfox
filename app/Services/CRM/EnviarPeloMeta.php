<?php

namespace App\Services\CRM;

use App\Models\CRM\MetaIntegration;
use Illuminate\Support\Facades\Http;

/**
 * Envia mensagens PELO Meta (por agora, WhatsApp Cloud API).
 *
 * O WhatsApp só deixa mandar texto livre dentro da janela de 24h desde a última
 * mensagem do cliente; fora disso é preciso um template aprovado. Aqui manda-se
 * o texto e devolve-se o que o Meta responder — quem chama mostra o erro se ele
 * recusar (ex.: janela fechada).
 */
class EnviarPeloMeta
{
    /**
     * @return array{ok: bool, id?: string, erro?: string}
     */
    public function whatsappTexto(MetaIntegration $mi, string $para, string $texto): array
    {
        if (! $mi->whatsappActivo()) {
            return ['ok' => false, 'erro' => 'O WhatsApp não está ligado nas definições.'];
        }

        $para = preg_replace('/\D+/', '', $para); // só dígitos
        if ($para === '') {
            return ['ok' => false, 'erro' => 'Sem número de WhatsApp para este lead.'];
        }

        try {
            $r = Http::withToken($mi->whatsapp_token)->timeout(20)->post(
                "https://graph.facebook.com/v21.0/{$mi->whatsapp_phone_number_id}/messages",
                [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $para,
                    'type' => 'text',
                    'text' => ['preview_url' => false, 'body' => $texto],
                ]
            );

            if ($r->successful()) {
                return ['ok' => true, 'id' => $r->json('messages.0.id')];
            }

            return ['ok' => false, 'erro' => $r->json('error.message') ?? ('O Meta recusou (HTTP '.$r->status().').')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => 'Não foi possível contactar o Meta — '.$e->getMessage()];
        }
    }
}
