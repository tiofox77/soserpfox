<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /** A senha nova, a partir do link do e-mail — o ecrã `entrada/nova-senha`. */
    public function showResetForm(\Illuminate\Http\Request $request, $token = null)
    {
        return \App\Support\EcraReact::solta('entrada/nova-senha', 'Definir Palavra-passe', \App\Support\Entrada::comum() + [
            'acao' => route('password.update'),
            'token' => (string) $token,
            'email' => $request->query('email') ?: (old('email') ?: null),
            'login' => route('login'),
        ])();
    }

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/home';
}
