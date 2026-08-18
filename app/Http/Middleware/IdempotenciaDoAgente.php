<?php

namespace App\Http\Middleware;

use App\Models\AgentRequest;
use App\Support\AgenteAutenticado;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;

/**
 * Idempotência das escritas.
 *
 * Sem isto, um agente que repita uma chamada por timeout aprova o mesmo
 * pedido duas vezes. Verificar antes de escrever tem janela de corrida,
 * por isso apanha-se também a violação do índice único — o mesmo padrão
 * que o checkout do restaurante e o local_uuid do POS já usam.
 */
class IdempotenciaDoAgente
{
    public function handle(Request $request, Closure $next)
    {
        $chave = $request->header('Idempotency-Key');

        if (!$chave) {
            return response()->json([
                'erro'     => 'falta_idempotency_key',
                'mensagem' => 'Escritas exigem o cabeçalho Idempotency-Key (uuid).',
            ], 422);
        }

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $chave)) {
            return response()->json([
                'erro'     => 'idempotency_key_invalida',
                'mensagem' => 'Idempotency-Key tem de ser um uuid.',
            ], 422);
        }

        $agente = app(AgenteAutenticado::class);
        $rota   = $request->path();
        $hash   = hash('sha256', json_encode($request->all()));

        $anterior = AgentRequest::where('agent_token_id', $agente->id())
            ->where('idempotency_key', $chave)
            ->first();

        if ($anterior) {
            return $this->repetir($anterior, $hash);
        }

        try {
            $registo = AgentRequest::create([
                'agent_token_id'  => $agente->id(),
                'idempotency_key' => $chave,
                'rota'            => $rota,
                'corpo_hash'      => $hash,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Duas chamadas em paralelo com a mesma chave: ganha a primeira.
            $anterior = AgentRequest::where('agent_token_id', $agente->id())
                ->where('idempotency_key', $chave)
                ->first();

            return $anterior
                ? $this->repetir($anterior, $hash)
                : response()->json([
                    'erro'     => 'em_curso',
                    'mensagem' => 'Um pedido com esta chave está a ser processado.',
                ], 409);
        }

        $resposta = $next($request);

        $registo->update([
            'http_status' => $resposta->getStatusCode(),
            'resposta'    => json_decode($resposta->getContent(), true),
        ]);

        return $resposta;
    }

    /** Repetição da mesma chave: devolve-se a resposta original. */
    private function repetir(AgentRequest $anterior, string $hash)
    {
        // Mesma chave com corpo diferente é erro do chamador, não repetição.
        if ($anterior->corpo_hash !== $hash) {
            return response()->json([
                'erro'     => 'idempotency_key_reutilizada',
                'mensagem' => 'Esta Idempotency-Key já foi usada com outro corpo.',
            ], 422);
        }

        if ($anterior->http_status === null) {
            return response()->json([
                'erro'     => 'em_curso',
                'mensagem' => 'Um pedido com esta chave ainda está a ser processado.',
            ], 409);
        }

        return response()
            ->json($anterior->resposta, $anterior->http_status)
            ->header('Idempotent-Replay', 'true');
    }
}
