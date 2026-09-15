<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Support\Seguranca\TravaoDeEntradas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * ENTRAR NO PORTAL DO CLIENTE.
 *
 * As mesmas regras do componente — só entra quem tem o portal ligado e a ficha
 * activa, e fica registada a hora da entrada — e uma que faltava: TENTATIVAS
 * LIMITADAS. O formulário aceitava tentativas sem fim; com a senha gerada pela
 * empresa e o email do cliente à vista numa factura, era uma porta aberta a
 * quem quisesse adivinhar.
 */
class EntradaNoPortalController extends Controller
{
    public function entrar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        // A regra das três portas: 5 falhas fecham 10 minutos, e 20 falhas do
        // mesmo IP em quaisquer emails também (TravaoDeEntradas).
        $travao = TravaoDeEntradas::para('portal-cliente');

        if ($segundos = $travao->bloqueadoPor($dados['email'], $request->ip())) {
            throw ValidationException::withMessages([
                'email' => TravaoDeEntradas::mensagemDeBloqueio($segundos),
            ])->status(429);
        }

        $entrou = Auth::guard('client')->attempt([
            'email' => $dados['email'],
            'password' => $dados['password'],
            'portal_access' => true,
            'is_active' => true,
        ], (bool) ($dados['remember'] ?? false));

        if (! $entrou) {
            $restam = $travao->falhou($dados['email'], $request->ip());

            throw ValidationException::withMessages([
                'email' => TravaoDeEntradas::mensagemDeFalha($restam, __('As credenciais fornecidas não correspondem aos nossos registros ou você não tem acesso ao portal.')),
            ])->status($restam === 0 ? 429 : 422);
        }

        $travao->entrou($dados['email'], $request->ip());
        Auth::guard('client')->user()->update(['last_login_at' => now()]);
        $request->session()->regenerate();

        return response()->json([
            'message' => __('Bem-vindo!'),
            'ir_para' => redirect()->intended(route('client.dashboard'))->getTargetUrl(),
        ]);
    }
}
