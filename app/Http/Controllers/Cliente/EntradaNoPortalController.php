<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
    private const TENTATIVAS = 5;

    public function entrar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $chave = 'portal-cliente:'.Str::lower($dados['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($chave, self::TENTATIVAS)) {
            throw ValidationException::withMessages([
                'email' => __('Demasiadas tentativas. Tente de novo dentro de :s segundos.', ['s' => RateLimiter::availableIn($chave)]),
            ]);
        }

        $entrou = Auth::guard('client')->attempt([
            'email' => $dados['email'],
            'password' => $dados['password'],
            'portal_access' => true,
            'is_active' => true,
        ], (bool) ($dados['remember'] ?? false));

        if (! $entrou) {
            RateLimiter::hit($chave, 60);

            throw ValidationException::withMessages([
                'email' => __('As credenciais fornecidas não correspondem aos nossos registros ou você não tem acesso ao portal.'),
            ]);
        }

        RateLimiter::clear($chave);
        Auth::guard('client')->user()->update(['last_login_at' => now()]);
        $request->session()->regenerate();

        return response()->json([
            'message' => __('Bem-vindo!'),
            'ir_para' => redirect()->intended(route('client.dashboard'))->getTargetUrl(),
        ]);
    }
}
