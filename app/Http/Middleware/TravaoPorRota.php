<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * UM TRAVÃO POR ROTA — o `throttle:N,M` de sempre, mas com o contador DA ROTA.
 *
 * O `throttle:N,M` do Laravel conta pela pessoa (ou, sem sessão, pelo IP) e
 * NÃO pela rota: todas as rotas com travão sem nome partilham UM contador.
 * Cada uma só muda o tecto que lhe aplica. No registo (16/09/2026, «Too Many
 * Attempts» no passo 3) o guardar automático do assistente (120/min), o
 * «Seguinte» (20 em 10 min) e o registo das visitas do site (120/min) enchiam
 * esse contador — e o botão final, com tecto 10, encontrava-o já acima de 10
 * e recusava a conta nova sem ela ter tentado nada.
 *
 * Com a rota na chave cada travão conta só os seus pedidos, que é o que quem
 * os escreveu queria dizer. Os limitadores com nome (`throttle:agent-read`)
 * não passam por aqui: têm a sua própria chave.
 */
class TravaoPorRota extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $rota = $request->route();
        $nome = $rota ? ($rota->getName() ?: implode('|', $rota->methods()) . ' ' . $rota->uri()) : '';

        return sha1($nome . '|' . parent::resolveRequestSignature($request));
    }
}
