<?php

namespace App\Livewire\Client;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ClientLogin extends Component
{
    public $email = '';
    public $password = '';
    public $remember = false;

    protected $rules = [
        'email' => 'required|email',
        'password' => 'required',
    ];

    public function login()
    {
        $this->validate();

        if (Auth::guard('client')->attempt([
            'email' => $this->email,
            'password' => $this->password,
            'portal_access' => true,
            'is_active' => true,
        ], $this->remember)) {
            
            // Atualizar último login
            Auth::guard('client')->user()->update([
                'last_login_at' => now()
            ]);

            session()->regenerate();
            
            return redirect()->intended(route('client.dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => 'As credenciais fornecidas não correspondem aos nossos registros ou você não tem acesso ao portal.',
        ]);
    }

    public function render()
    {
        return view('livewire.client.client-login')->layout('layouts.guest');
    }
}
