<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sair do PWA: termina a sessão e devolve à entrada do PWA.
 *
 * NÃO SE APAGA NADA DO APARELHO. A base local pode ter vendas por enviar, e
 * limpá-la ao sair faria desaparecer facturas que o servidor ainda não viu —
 * dinheiro cobrado que deixava de existir em lado nenhum. Quem sai continua a
 * poder entrar sem rede e a fila sobe na mesma.
 */
class PwaSairController extends Controller
{
    public function __invoke(Request $request)
    {
        if (Auth::check()) {
            Auth::logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Quando a saída foi feita sem rede, quem chega aqui é a fila do
        // aparelho e não um clique. Responde-se em JSON: um 302 para o login
        // fazia o trabalho parecer falhado e ele ficava a repetir-se.
        if ($request->wantsJson() || $request->boolean('da_fila')) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('invoicing.offline.login');
    }
}
