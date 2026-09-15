<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Seguranca\TravaoDeEntradas;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * A página de entrada — o ecrã `entrada/login`, em React.
     *
     * O formulário continua a vir aqui (POST /login): a autenticação, a
     * limitação de tentativas e o «para onde ia» são deste controlador. E os
     * recados de quem manda para cá (a licença instalada, o limite de
     * utilizadores de um convite) passam a ver-se.
     */
    public function showLoginForm()
    {
        return \App\Support\EcraReact::solta('entrada/login', 'Entrar', \App\Support\Entrada::comum() + [
            'acao' => route('login'),
            'recuperar' => \Illuminate\Support\Facades\Route::has('password.request') ? route('password.request') : null,
            'registo' => route('register'),
        ])();
    }

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /* ─── O travão: 5 falhas, 10 minutos de bloqueio ──────────────────────
     *
     * O ThrottlesLogins do Laravel deixava 5 tentativas por MINUTO e voltava a
     * abrir ao fim de 60 segundos. As contas passam a ser as do
     * App\Support\Seguranca\TravaoDeEntradas, iguais às da API e do portal.
     */

    /** Quantas tentativas restam depois da última falha (para a mensagem). */
    private int $restam = TravaoDeEntradas::TENTATIVAS;

    private function travao(): TravaoDeEntradas
    {
        return TravaoDeEntradas::para('web');
    }

    protected function hasTooManyLoginAttempts(Request $request)
    {
        return $this->travao()->bloqueadoPor($request->input($this->username()), $request->ip()) > 0;
    }

    protected function incrementLoginAttempts(Request $request)
    {
        $this->restam = $this->travao()->falhou($request->input($this->username()), $request->ip());
    }

    protected function clearLoginAttempts(Request $request)
    {
        $this->travao()->entrou($request->input($this->username()), $request->ip());
    }

    protected function sendLockoutResponse(Request $request)
    {
        $segundos = $this->travao()->bloqueadoPor($request->input($this->username()), $request->ip());

        throw ValidationException::withMessages([
            $this->username() => [TravaoDeEntradas::mensagemDeBloqueio($segundos)],
        ])->status(Response::HTTP_TOO_MANY_REQUESTS);
    }

    /** A mesma frase exista ou não a conta — com o aviso quando o bloqueio está perto. */
    protected function sendFailedLoginResponse(Request $request)
    {
        throw ValidationException::withMessages([
            $this->username() => [TravaoDeEntradas::mensagemDeFalha($this->restam, trans('auth.failed'))],
        ]);
    }

    /**
     * Quem entra pelo PWA volta para o PWA.
     *
     * O redireccionamento normal é para o URL que a pessoa tentou abrir, e
     * quando não há nenhum cai em `/home`. A entrada do PWA abre-se
     * directamente — ninguém foi barrado a caminho de lado nenhum —, por isso
     * não há URL pretendido e o operador da caixa ia parar ao painel da
     * aplicação web. Num telemóvel instalado, isso é sair do sítio onde se
     * vende para um sítio onde não se vende.
     *
     * O sinal vem do formulário da entrada do PWA e não do navegador: uma
     * aplicação instalada não se distingue de um separador normal do lado do
     * servidor.
     */
    protected function authenticated(\Illuminate\Http\Request $request, $user)
    {
        // Uma conta desactivada entrava na mesma: o `is_active` não estava em
        // lado nenhum da autenticação (auditoria de segurança de 2026-09-13).
        if (! $user->is_active) {
            $this->guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw \Illuminate\Validation\ValidationException::withMessages(['email' => [__('A sua conta está desactivada.')]]);
        }

        if ($request->boolean('pwa')) {
            return redirect()->route('invoicing.offline.pos');
        }

        return null;
    }
}
