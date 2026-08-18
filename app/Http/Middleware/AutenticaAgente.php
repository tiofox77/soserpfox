<?php

namespace App\Http\Middleware;

use App\Models\AgentToken;
use App\Support\AgenteAutenticado;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Porta de entrada da API do agente.
 *
 * NÃO chama Auth::login(). O ResolveApiToken, que serve a app móvel, faz
 * isso — e converter o token num utilizador com sessão entregaria ao
 * agente externo a superfície web inteira desse utilizador. Aqui o token
 * dá acesso a esta API e a mais nada.
 *
 * A linha do token é lida a cada pedido, sem cache, para que revogar
 * tenha efeito imediato.
 */
class AutenticaAgente
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('agent.activa', true)) {
            return $this->erro($request, 503, 'api_desligada',
                'A API do agente está desligada.');
        }

        $bearer = $request->bearerToken();
        if (!$bearer) {
            return $this->erro($request, 401, 'sem_credencial',
                'Falta o cabeçalho Authorization: Bearer.');
        }

        // Formato: oclaw_<prefixo>_<segredo>
        $partes = explode('_', $bearer);
        if (count($partes) !== 3 || $partes[0] !== config('agent.token.prefixo', 'oclaw')) {
            return $this->erro($request, 401, 'credencial_invalida',
                'Credencial mal formada.');
        }

        [$_, $prefixo, $segredo] = $partes;

        $token = AgentToken::where('prefix', $prefixo)->first();

        // Comparação em tempo constante mesmo quando não há linha, para o
        // tempo de resposta não denunciar prefixos válidos.
        $esperado = $token->token_hash ?? str_repeat('0', 64);
        $confere  = hash_equals($esperado, AgentToken::hashSegredo($segredo));

        if (!$token || !$confere) {
            Log::warning('agente: credencial recusada', [
                'prefixo' => $prefixo,
                'ip'      => $request->ip(),
            ]);

            return $this->erro($request, 401, 'credencial_invalida',
                'Credencial inválida.');
        }

        if ($token->estaRevogado()) {
            return $this->erro($request, 401, 'credencial_revogada',
                'Credencial revogada em ' . $token->revoked_at->toDateString() . '.');
        }

        if ($token->estaExpirado()) {
            return $this->erro($request, 401, 'credencial_expirada',
                'Credencial expirada em ' . $token->expires_at->toDateString() . '.');
        }

        if (!$token->aceitaIp($request->ip())) {
            Log::warning('agente: IP fora da lista', [
                'prefixo' => $prefixo,
                'ip'      => $request->ip(),
            ]);

            return $this->erro($request, 403, 'ip_nao_autorizado',
                'Este endereço não está autorizado para esta credencial.');
        }

        $token->forceFill([
            'last_used_at' => now(),
            'last_ip'      => $request->ip(),
        ])->saveQuietly();

        app()->instance(AgenteAutenticado::class, new AgenteAutenticado($token));

        return $next($request);
    }

    private function erro(Request $request, int $status, string $codigo, string $mensagem)
    {
        return response()->json([
            'erro'     => $codigo,
            'mensagem' => $mensagem,
        ], $status);
    }
}
