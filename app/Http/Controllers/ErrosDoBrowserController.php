<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * OS ERROS QUE ACONTECEM NO BROWSER DE QUEM USA O SISTEMA.
 *
 * O React manda-os por `resources/js/casca/relatarErro.ts`. Aqui só se
 * escrevem no log como ERROR: o tap da captura (App\Logging\LigarCapturaDeErros)
 * agrupa-os em `erros_do_sistema` como qualquer outro, com contador, e o
 * `erros:ver` mostra-os ao lado dos do servidor.
 *
 * A mensagem leva o ecrã à frente, para dois ecrãs com o mesmo erro serem
 * dois problemas. Aceita-se de quem não entrou (a entrada e o registo também
 * são React), com limite por minuto.
 */
class ErrosDoBrowserController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'mensagem' => ['required', 'string', 'max:500'],
            'pilha' => ['nullable', 'string', 'max:4000'],
            'pilha_do_componente' => ['nullable', 'string', 'max:2000'],
            'ecra' => ['nullable', 'string', 'max:120'],
            'origem' => ['required', 'in:ecra,janela,promessa'],
            'endereco' => ['nullable', 'string', 'max:500'],
        ]);

        $onde = $dados['ecra'] ?: parse_url((string) ($dados['endereco'] ?? ''), PHP_URL_PATH) ?: '?';

        Log::error("Browser [{$onde}]: {$dados['mensagem']}", [
            'origem' => $dados['origem'],
            'endereco' => $dados['endereco'] ?? null,
            'utilizador' => $request->user()?->id,
            'empresa' => function_exists('activeTenantId') ? activeTenantId() : null,
            'navegador' => substr((string) $request->userAgent(), 0, 200),
            'pilha' => $dados['pilha'] ?? null,
            'pilha_do_componente' => $dados['pilha_do_componente'] ?? null,
        ]);

        return response()->json(['recebido' => true], 202);
    }
}
