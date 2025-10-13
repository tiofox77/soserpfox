<?php

namespace App\Livewire\Client;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

#[Layout('layouts.client')]
#[Title('Meu Perfil')]
class ClientProfile extends Component
{
    public $name;
    public $email;
    public $phone;
    public $current_password = '';
    public $new_password = '';
    public $new_password_confirmation = '';

    public function mount()
    {
        $client = Auth::guard('client')->user();
        $this->name = $client->name;
        $this->email = $client->email;
        $this->phone = $client->phone;
    }

    public function updateProfile()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $client = Auth::guard('client')->user();
        $client->update([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
        ]);

        session()->flash('success', 'Perfil atualizado com sucesso!');
    }

    public function updatePassword()
    {
        $this->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $client = Auth::guard('client')->user();

        if (!Hash::check($this->current_password, $client->password)) {
            $this->addError('current_password', 'Senha atual incorreta');
            return;
        }

        $client->update([
            'password' => Hash::make($this->new_password),
            'password_changed_at' => now(),
        ]);

        $this->current_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';

        session()->flash('success', 'Senha alterada com sucesso!');
    }

    public function render()
    {
        return view('livewire.client.client-profile');
    }
}
