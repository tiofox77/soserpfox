<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\CRM\MetaIntegration;
use App\Services\CRM\ReceberDoMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * A porta por onde o Meta entrega tudo a UMA empresa: /webhooks/meta/{tenant}.
 *
 * SEM sessão nem CSRF (é o Meta que chama, máquina-a-máquina). Fica seguro por
 * duas coisas: o `verify_token` no arranque (GET) e a ASSINATURA de cada evento
 * (POST, HMAC com o app_secret). O {tenant} no endereço diz de quem é.
 */
class MetaWebhookController extends Controller
{
    /** GET — o aperto de mão do Meta ao configurar o webhook. */
    public function verify(Request $request, int $tenant)
    {
        $mi = MetaIntegration::where('tenant_id', $tenant)->first();

        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));
        $mode = $request->query('hub_mode', $request->query('hub.mode'));

        if ($mi && $mode === 'subscribe' && $token && hash_equals((string) $mi->webhook_verify_token, (string) $token)) {
            // O Meta espera o challenge cru, em texto, com 200.
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Verificação falhou.', 403);
    }

    /** POST — os eventos (mensagens, leads). */
    public function receive(Request $request, int $tenant)
    {
        $mi = MetaIntegration::where('tenant_id', $tenant)->first();
        if (! $mi) {
            return response()->json(['ok' => false], 404);
        }

        $raw = $request->getContent();

        // ASSINATURA. Se a empresa configurou o app_secret, exige-se — é o que
        // garante que o evento veio MESMO do Meta e não de um forjador.
        if (! empty($mi->app_secret)) {
            $assinatura = (string) $request->header('X-Hub-Signature-256', '');
            $esperada = 'sha256='.hash_hmac('sha256', $raw, $mi->app_secret);
            if (! hash_equals($esperada, $assinatura)) {
                Log::warning('Meta webhook: assinatura inválida', ['tenant' => $tenant]);

                return response('Assinatura inválida.', 403);
            }
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response()->json(['ok' => true]); // nada a fazer, mas não fazer o Meta repetir
        }

        // Processa-se aqui, mas NUNCA se devolve erro ao Meta por uma falha
        // interna — senão ele repete o evento em ciclo. Regista-se e segue-se.
        try {
            $n = app(ReceberDoMeta::class)->processar($mi, $payload);

            return response()->json(['ok' => true, 'tratados' => $n]);
        } catch (\Throwable $e) {
            Log::error('Meta webhook: falha ao processar', ['tenant' => $tenant, 'erro' => $e->getMessage()]);

            return response()->json(['ok' => true]); // 200 de propósito
        }
    }
}
