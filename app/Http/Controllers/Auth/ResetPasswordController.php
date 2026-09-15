<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;

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

    public function __construct()
    {
        // Sem travão, com um token qualquer, perguntava-se sem fim se um email
        // tem conta (auditoria de segurança de 2026-09-15).
        $this->middleware('throttle:5,10')->only('reset');
    }

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
     * A MESMA RESPOSTA para «esse email não existe» e «esse link não serve».
     *
     * A resposta do Laravel distinguia as duas: com um token inventado, «Não
     * encontramos nenhum utilizador com esse email» dizia que o email não tem
     * conta, e «token inválido» dizia que tem.
     */
    protected function sendResetFailedResponse(Request $request, $response)
    {
        $mensagem = in_array($response, [Password::INVALID_USER, Password::INVALID_TOKEN], true)
            ? __('O link para redefinir a palavra-passe é inválido ou já expirou. Peça um novo.')
            : trans($response);

        if ($request->wantsJson()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['email' => [$mensagem]]);
        }

        return redirect()->back()->withInput($request->only('email'))->withErrors(['email' => $mensagem]);
    }

    /**
     * Senha nova: as outras portas fecham-se.
     *
     * Quem pede uma senha nova pode estar a fazê-lo porque alguém lha levou.
     * Deixar abertas as sessões dos outros aparelhos e os tokens da aplicação
     * móvel era deixar lá dentro precisamente quem se quer pôr fora. E uma conta
     * desactivada não entra por aqui — trocava de senha e ficava logo dentro.
     */
    protected function resetPassword($user, $password)
    {
        $this->setUserPassword($user, $password);
        $user->setRememberToken(\Illuminate\Support\Str::random(60));
        $user->save();

        event(new \Illuminate\Auth\Events\PasswordReset($user));

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        if (Schema::hasTable('api_tokens')) {
            DB::table('api_tokens')->where('user_id', $user->id)->delete();
        }

        if ($user->is_active) {
            $this->guard()->login($user);
        }
    }

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/home';
}
