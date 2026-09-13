<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

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
        if ($request->boolean('pwa')) {
            return redirect()->route('invoicing.offline.pos');
        }

        return null;
    }
}
