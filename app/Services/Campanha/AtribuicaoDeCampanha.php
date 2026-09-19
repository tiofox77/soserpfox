<?php

namespace App\Services\Campanha;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A ATRIBUIÇÃO DA CAMPANHA — desde a aterragem até ao cadastro.
 *
 * O anúncio aterra em `/`, `/modulos` ou `/modulos/{slug}`, muitas vezes com
 * `?utm_*` e `?fbclid`. Se a atribuição só fosse lida em `/register`, quem
 * clicasse no «Começar» perdia-a (o link não a leva, e ainda bem — não se
 * põem dados nos URLs). Por isso guarda-se na SESSÃO logo na aterragem, e o
 * registo consome-a lá.
 *
 * Guarda também o PLANO do módulo por onde a pessoa entrou, para o assistente
 * pré-seleccionar o plano certo mesmo quando o botão aponta o `/register` seco.
 */
class AtribuicaoDeCampanha
{
    /**
     * Lê o pedido e guarda o que houver. Idempotente: só acrescenta.
     *
     * @param  string|null  $moduloSlug  o slug da página do módulo, quando é uma.
     */
    public static function capturar(Request $request, ?string $moduloSlug = null): void
    {
        $chaves = (array) config('campanha.atribuicao', []);
        $chegou = array_filter(
            $request->only($chaves),
            fn ($v) => is_string($v) && $v !== '' && mb_strlen($v) <= 400,
        );

        if ($chegou !== []) {
            $request->session()->put(
                'registration_acquisition',
                array_merge($request->session()->get('registration_acquisition', []), $chegou),
            );
        }

        if (! $request->session()->has('registration_visitor_id')) {
            $request->session()->put('registration_visitor_id', (string) Str::uuid());
        }

        // O plano do módulo fica lembrado para o registo — a última página de
        // módulo visitada manda, que é a intenção mais recente.
        if ($moduloSlug !== null) {
            $plano = self::planoDoModulo($moduloSlug);
            if ($plano !== null) {
                $request->session()->put('registration_plan', $plano);
            }
        }
    }

    /** O slug do plano público a pré-seleccionar para uma página de módulo, ou null. */
    public static function planoDoModulo(?string $moduloSlug): ?string
    {
        if ($moduloSlug === null) {
            return null;
        }

        return config('campanha.modulos.' . $moduloSlug);
    }
}
