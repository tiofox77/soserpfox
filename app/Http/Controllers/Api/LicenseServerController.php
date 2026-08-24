<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Licensing\LicenseIssuer;
use App\Services\Licensing\LicenseVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * SERVIDOR de licenças (lado do vendor, corre na cloud): recebe o check-in de
 * uma instalação offline e decide.
 *
 * Confia no token pela ASSINATURA (só o vendor a produz) para saber QUEM é o
 * tenant; decide o resto pela subscrição na base — não pelo que o cliente diz.
 *
 * Respostas:
 *   { "licenca": "<token renovado>" , "notificacoes": [] }   → activa: renova
 *   { "acao": "bloquear", "motivo": "..." }                  → suspensa/desconhecida
 *   { "erro": "renovacao_indisponivel" }                     → sem chave privada configurada
 *
 * Segurança do cliente: ele só reinicia o contador com a licença renovada
 * ASSINADA. Um "acao: continuar" à solta não o safa.
 */
class LicenseServerController extends Controller
{
    public function checkin(Request $request)
    {
        $dados = $request->validate([
            'token'       => 'required|string',
            'fingerprint' => 'nullable|string|max:64',
            'versao'      => 'nullable|string|max:40',
        ]);

        $cfg = config('licensing', []);
        $pub = $cfg['public_key'] ?? '';
        if (!$pub) {
            return response()->json(['erro' => 'servidor_sem_chave_publica'], 500);
        }

        // Quem é? (só a assinatura importa aqui — vigência/máquina decide o cliente)
        $payload = (new LicenseVerifier($pub, $cfg))->payloadAssinado($dados['token']);
        if (!$payload || !$payload->tenantId()) {
            return response()->json(['acao' => 'bloquear', 'motivo' => 'assinatura_invalida'], 200);
        }

        $tenant = Tenant::find($payload->tenantId());
        if (!$tenant) {
            return response()->json(['acao' => 'bloquear', 'motivo' => 'empresa_desconhecida'], 200);
        }

        // Estado na base manda. is_active é o que o painel/openclaw já mexe ao
        // suspender — logo, "suspender na cloud" bloqueia no próximo check-in.
        if (!$tenant->is_active) {
            return response()->json(['acao' => 'bloquear', 'motivo' => 'subscricao_suspensa'], 200);
        }

        $secret = $cfg['signing_key'] ?? null;
        if (!$secret) {
            // Servidor não configurado para renovar: não bloqueia, mas também
            // não renova — o cliente não reinicia o contador.
            return response()->json(['erro' => 'renovacao_indisponivel'], 200);
        }

        try {
            $novo = (new LicenseIssuer())->emitir([
                'tenant_id' => $tenant->id,
                'empresa'   => $tenant->name,
                'nif'       => $tenant->nif,
                'plano'     => $tenant->activeSubscription?->plan?->name,
                'modulos'   => ['*'], // v1: todos; refinar por tenant_module depois
                'fp'        => $payload->fingerprint(),   // mantém o binding original
                'graca'     => $payload->gracaDias() ?? ($cfg['offline_grace_days'] ?? 15),
                'exp'       => CarbonImmutable::now()->addDays((int) ($cfg['renew_days'] ?? 30))->getTimestamp(),
            ], $secret);
        } catch (\Throwable $e) {
            return response()->json(['erro' => 'falha_ao_renovar'], 500);
        }

        // Regista o contacto desta instalação: é o que alimenta a lista de
        // clientes offline no painel ("quem falou connosco, quando, e com que
        // versão"). Nunca pode partir a renovação — daí o try.
        try {
            \App\Models\LicencaEmitida::updateOrCreate(
                ['tenant_id' => $tenant->id, 'fingerprint' => $payload->fingerprint()],
                [
                    'plano'            => $tenant->activeSubscription?->plan?->name,
                    'max_users'        => $payload->maxUtilizadores() ?: null,
                    'modulos'          => $payload->modulos(),
                    'emitida_em'       => now(),
                    'expira_em'        => CarbonImmutable::now()->addDays((int) ($cfg['renew_days'] ?? 30)),
                    'ultimo_checkin'   => now(),
                    'versao_instalada' => $dados['versao'] ?? null,
                    'ultimo_ip'        => $request->ip(),
                ]
            );
        } catch (\Throwable $e) {
            // registo é secundário; a licença renovada é que interessa
        }

        return response()->json([
            'licenca'      => $novo,
            'notificacoes' => [],
        ]);
    }
}
