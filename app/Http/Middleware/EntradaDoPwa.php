<?php

namespace App\Http\Middleware;

use App\Support\MenuDoPwa;
use App\Support\PaginaDoPwa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A porta de cada ecrã do PWA, com a mesma regra que desenha o menu.
 *
 * Antes o menu escondia a entrada e a rota abria na mesma a quem escrevesse o
 * endereço — e um dos dois estava sempre desactualizado, porque viviam em
 * ficheiros diferentes. Aqui a pergunta é uma só: `MenuDoPwa::podeVer()`.
 *
 * Responde 403, e é de propósito: o service worker só guarda respostas OK,
 * portanto uma página a que o utilizador não tem direito nem sequer chega a
 * ficar guardada no aparelho.
 */
class EntradaDoPwa
{
    public function handle(Request $request, Closure $next, string $chave): Response
    {
        if (MenuDoPwa::podeVer($chave)) {
            return $next($request);
        }

        // UM 403 EM BRANCO LÊ-SE COMO AVARIA.
        //
        // Quem está ao balcão não distingue "não tem permissão" de "a
        // aplicação partiu-se" — e a chamada que faz ao suporte é a mesma. A
        // página diz o que falta e a quem pedir; o estado continua 403, para o
        // service worker não a guardar no lugar do ecrã verdadeiro.
        $definicao = MenuDoPwa::ENTRADAS[$chave] ?? null;

        return PaginaDoPwa::resposta('sem-acesso', [
            'semAcesso' => [
                'chave' => $chave,
                'etiqueta' => __($definicao['etiqueta'] ?? $chave),
                'permissao' => $definicao['permissao'] ?? null,
                'modulo' => $definicao['modulo'] ?? null,
            ],
        ], 403);
    }
}
