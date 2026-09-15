<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    public function __construct()
    {
        // Sem travão pedia-se o email de recuperação sem fim (auditoria de 2026-09-13).
        $this->middleware('throttle:5,1')->only('sendResetLinkEmail');
    }

    /*
     * A MESMA RESPOSTA EXISTA OU NÃO A CONTA. «Não encontramos esse email»
     * dizia a quem perguntasse que emails têm conta no sistema.
     *
     * E o «Aguarde antes de tentar novamente» também: só aparecia a quem pedia
     * duas vezes o link de um email QUE EXISTE (um inexistente nunca fica em
     * espera) — era a mesma fuga por outra porta (auditoria de 2026-09-15).
     */
    protected function sendResetLinkFailedResponse(\Illuminate\Http\Request $request, $response)
    {
        if (in_array($response, [\Illuminate\Support\Facades\Password::INVALID_USER, \Illuminate\Support\Facades\Password::RESET_THROTTLED], true)) {
            return $this->sendResetLinkResponse($request, \Illuminate\Support\Facades\Password::RESET_LINK_SENT);
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => trans($response)]);
    }

    /** Pedir o link — o ecrã `entrada/recuperar-senha`; o `status` chega nos recados. */
    public function showLinkRequestForm()
    {
        return \App\Support\EcraReact::solta('entrada/recuperar-senha', 'Recuperar Palavra-passe', \App\Support\Entrada::comum() + [
            'acao' => route('password.email'),
            'login' => route('login'),
        ])();
    }
}
