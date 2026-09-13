<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ConfirmsPasswords;

class ConfirmPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Confirm Password Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password confirmations and
    | uses a simple trait to include the behavior. You're free to explore
    | this trait and override any functions that require customization.
    |
    */

    use ConfirmsPasswords;

    /** Confirmar a senha antes de uma zona sensível — o ecrã `entrada/confirmar-senha`. */
    public function showConfirmForm()
    {
        return \App\Support\EcraReact::solta('entrada/confirmar-senha', 'Confirmar Palavra-passe', \App\Support\Entrada::comum() + [
            'acao' => route('password.confirm'),
            'recuperar' => \Illuminate\Support\Facades\Route::has('password.request') ? route('password.request') : null,
        ])();
    }

    /**
     * Where to redirect users when the intended url fails.
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
        $this->middleware('auth');
    }
}
