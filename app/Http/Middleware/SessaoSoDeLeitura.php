<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Symfony\Component\HttpFoundation\Response;

/**
 * UM PEDIDO À API QUE SÓ LÊ NÃO REESCREVE A SESSÃO.
 *
 * O Laravel grava a sessão INTEIRA no fim de cada pedido, com o que leu ao
 * começar. Os ecrãs React pedem dados em paralelo ao abrir (e as peças do topo
 * de minuto a minuto): um desses pedidos que acabe DEPOIS de outro ter mudado
 * a sessão escreve por cima a sessão velha. Foi assim que, logo a seguir a
 * ligar a casca nova, a página voltava à antiga — os pedidos da página
 * anterior acabavam depois e repunham a sessão de antes. O mesmo podia desfazer
 * uma troca de empresa.
 *
 * E um segundo efeito, silencioso: gravar a sessão envelhece o «flash». Um
 * pedido de fundo consumia o «Guardado com sucesso» que era para a página
 * seguinte.
 *
 * Por isso, num GET à API, a gravação DESTE pedido não se faz. RENOVA-SE só a
 * hora da última actividade: quem passa uma hora a filtrar uma lista em React
 * está a trabalhar, e não pode ser posto fora como se tivesse saído.
 *
 * A troca de manipulador vale UMA gravação e desfaz-se sozinha: o objecto da
 * sessão é reaproveitado entre pedidos no mesmo processo (nos ensaios, e num
 * servidor que não morre entre pedidos), e o pedido seguinte tem de voltar a
 * gravar normalmente.
 */
class SessaoSoDeLeitura
{
    /**
     * A EXCEPÇÃO: um GET que MUDOU a identidade da sessão tem de gravar.
     *
     * A personificação que acaba sozinha (o prazo passa enquanto o topo
     * pergunta) acaba num GET. Sem gravar, a sessão regenerada ficava sem nada
     * — o admin era posto fora — ou o pedido seguinte voltava a ser a pessoa
     * personificada. Quem muda a identidade marca o pedido com este atributo.
     */
    public const GRAVAR = 'sessao.gravar_mesmo_numa_leitura';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->attributes->get(self::GRAVAR)) {
            return $response;
        }

        if ($request->isMethodSafe() && $request->is('api/*', 'client/api/*') && $request->hasSession()) {
            $sessao = $request->session();

            if ($sessao instanceof Store) {
                $sessao->setHandler(new SoRenovaUmaVez($sessao->getHandler(), $sessao));
            }
        }

        return $response;
    }
}
