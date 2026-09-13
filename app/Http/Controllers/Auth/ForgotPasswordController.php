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

    /** Pedir o link — o ecrã `entrada/recuperar-senha`; o `status` chega nos recados. */
    public function showLinkRequestForm()
    {
        return \App\Support\EcraReact::solta('entrada/recuperar-senha', 'Recuperar Palavra-passe', \App\Support\Entrada::comum() + [
            'acao' => route('password.email'),
            'login' => route('login'),
        ])();
    }
}
