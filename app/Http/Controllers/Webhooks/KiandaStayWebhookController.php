<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Hotel\LigacaoKiandaStay;
use App\Services\Hotel\ReceberDaKiandaStay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * A porta por onde o KiandaStay entrega as reservas a UMA empresa:
 * `/webhooks/kiandastay/{tenant}`.
 *
 * Sem sessão nem CSRF — é o site que chama, máquina a máquina. O que a fecha é
 * a ASSINATURA: cada evento vem com `X-Webhook-Signature: sha256=<hmac>` feito
 * sobre o corpo cru com o segredo que o site nos deu ao registar o webhook.
 * Sem segredo configurado, nada entra: uma porta aberta a quem soubesse o
 * número da empresa deixava qualquer um inventar reservas.
 *
 * RESPONDE SEMPRE 200 depois de validada a assinatura, mesmo que o tratamento
 * falhe. O KiandaStay conta as falhas e desliga o webhook ao fim de dez — um
 * erro nosso não pode ser o que corta o canal de reservas da casa.
 */
class KiandaStayWebhookController extends Controller
{
    public function receive(Request $request, int $tenant)
    {
        $ligacao = LigacaoKiandaStay::withoutGlobalScopes()->where('tenant_id', $tenant)->first();

        if (! $ligacao) {
            return response()->json(['ok' => false], 404);
        }

        $corpo = $request->getContent();

        if (empty($ligacao->webhook_secret)) {
            Log::warning('[KiandaStay] evento recusado: ligação sem segredo', ['tenant' => $tenant]);

            return response()->json(['ok' => false, 'erro' => 'Ligação por configurar.'], 403);
        }

        $esperada = 'sha256=' . hash_hmac('sha256', $corpo, $ligacao->webhook_secret);
        $recebida = (string) $request->header('X-Webhook-Signature', '');

        if (! hash_equals($esperada, $recebida)) {
            Log::warning('[KiandaStay] assinatura inválida', ['tenant' => $tenant]);

            return response()->json(['ok' => false, 'erro' => 'Assinatura inválida.'], 403);
        }

        if (! $ligacao->activa) {
            // Aceita-se (200) para o site não contar falha e desligar o
            // webhook, mas não se cria nada enquanto a casa não ligar isto.
            return response()->json(['ok' => true, 'ignorado' => 'ligação desactivada']);
        }

        $evento = json_decode($corpo, true);

        if (! is_array($evento)) {
            return response()->json(['ok' => true]);
        }

        try {
            $reserva = app(ReceberDaKiandaStay::class)->processar($ligacao, $evento);

            return response()->json([
                'ok'      => true,
                'reserva' => $reserva?->reservation_number,
            ]);
        } catch (\Throwable $e) {
            Log::error('[KiandaStay] falha a tratar o evento', [
                'tenant' => $tenant,
                'erro'   => $e->getMessage(),
            ]);

            $ligacao->forceFill(['ultimo_erro' => mb_substr($e->getMessage(), 0, 500)])->save();

            // 200 de propósito: ver a nota no topo.
            return response()->json(['ok' => true]);
        }
    }
}
