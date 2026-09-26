<?php

namespace App\Http\Middleware;

use App\Rules\NifDeEmpresa;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uma empresa com NIF de pessoa singular tem de o corrigir, e já.
 *
 * O NIF vai em cada documento fiscal comunicado à AGT, e é por ele que a
 * empresa é identificada. Com o número do bilhete de identidade no lugar do
 * NIF da empresa, as facturas são recusadas — e isso só se descobre no dia em
 * que alguém tenta comunicar, quando já há documentos emitidos com o número
 * errado. Corrigir depois deixa de ser mudar um campo.
 *
 * A validação no registo, escrita hoje, só trava os NOVOS. Quem se registou
 * antes ficou com o que escreveu, e nada no sistema o obrigava a reparar.
 *
 * O QUE ESTE MEIO-CAMINHO EVITA, e é deliberado: NÃO se tranca o ponto de
 * venda. Uma farmácia com o NIF errado continua a ter clientes ao balcão, e
 * pará-la seria um estrago maior do que o que se está a corrigir. O POS, o
 * modo offline e a saída ficam livres; o resto do sistema leva a pessoa ao
 * ecrã onde se arranja.
 */
class ExigirNifDeEmpresa
{
    /** Onde se pode continuar a ir com o NIF por corrigir. */
    private const LIVRES = [
        'empresa',            // é aqui que se corrige
        'logout',
        'login',
        'invoicing/pos',      // não se para a caixa
        'invoicing/offline',
        'api',
        'maintenance',
        'sw.js',
        'pwa',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $empresa = $this->empresaActiva();

        if (!$empresa || $this->nifEstaBem($empresa->nif) || $this->podePassar($request)) {
            return $next($request);
        }

        // SÓ SE MANDA LÁ QUEM PODE CORRIGIR.
        //
        // O ecrã da empresa passou a exigir `settings.view` — mudar o NIF e o
        // regime fiscal é de quem gere a empresa. Mandar para lá um caixa era
        // atirá-lo contra um 403 e trancá-lo fora do sistema: redirecção para
        // uma página que ele não abre, a cada pedido. Quem não pode corrigir
        // continua a trabalhar; a pressão fica em quem tem as mãos no volante.
        if (!auth()->user()?->can('settings.edit')) {
            return $next($request);
        }

        // Uma mensagem que diz o que está mal, porque importa, e o que fazer.
        // "NIF inválido" não move ninguém; perder as facturas move.
        return redirect()
            ->route('company.profile')
            // O NIF de pessoa singular (o número do BI) é válido desde
            // 26/09/2026: aqui só chega um número mal escrito.
            ->with('warning', 'O NIF da empresa (' . $empresa->nif . ') não está bem escrito: o de uma empresa tem nove ou dez dígitos '
                . '(começado por 5), e o de um empresário em nome individual é o número do BI (nove dígitos, duas letras e três dígitos). '
                . 'Os documentos comunicados à AGT com um NIF errado são recusados. Corrija-o aqui para continuar a usar o sistema.');
    }

    private function empresaActiva()
    {
        if (!auth()->check() || !function_exists('activeTenant')) {
            return null;
        }

        try {
            return activeTenant();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Vazio não conta como errado: é outro problema, e não é este que o resolve. */
    private function nifEstaBem(?string $nif): bool
    {
        if (empty($nif)) {
            return true;
        }

        return !Validator::make(['nif' => $nif], ['nif' => [new NifDeEmpresa()]])->fails();
    }

    private function podePassar(Request $request): bool
    {
        $caminho = ltrim($request->path(), '/');

        foreach (self::LIVRES as $livre) {
            if ($caminho === $livre || str_starts_with($caminho, $livre . '/')) {
                return true;
            }
        }

        // Um pedido que não é uma página — o Livewire a actualizar um campo, um
        // ficheiro — não se redirecciona: partiria o próprio ecrã de correcção.
        return !$request->isMethod('GET') || $request->ajax() || $request->wantsJson();
    }
}
