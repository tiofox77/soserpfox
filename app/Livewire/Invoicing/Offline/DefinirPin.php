<?php

namespace App\Livewire\Invoicing\Offline;

use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O funcionário define ou muda o seu PIN de turno.
 *
 * O PIN abre turno offline no POS. É uma credencial de chão de loja,
 * separada da password da conta: confirma-se com a password (para ninguém
 * definir um PIN a partir de uma sessão deixada aberta), mas o que fica
 * guardado é só o bcrypt do PIN, que segue para os tablets na próxima
 * sincronização.
 */
#[Layout('layouts.app')]
#[Title('PIN de turno')]
class DefinirPin extends Component
{
    public string $pin = '';
    public string $pin_confirmation = '';
    public string $password = '';

    public bool $jaTemPin = false;

    protected function rules(): array
    {
        return [
            'pin' => ['required', 'digits_between:4,6', 'confirmed'],
            'password' => ['required', 'string'],
        ];
    }

    protected array $messages = [
        'pin.required'        => 'Escreva o PIN.',
        'pin.digits_between'  => 'O PIN tem de ter 4 a 6 dígitos.',
        'pin.confirmed'       => 'Os dois PIN não coincidem.',
        'password.required'   => 'Confirme com a sua palavra-passe.',
    ];

    public function mount(): void
    {
        $this->jaTemPin = auth()->user()->temPinPos();
    }

    public function guardar(): void
    {
        $this->validate();

        // A password confirma que é mesmo o dono da conta a definir o PIN.
        if (!Hash::check($this->password, auth()->user()->password)) {
            $this->addError('password', 'Palavra-passe incorrecta.');
            return;
        }

        // PIN óbvio é um PIN que não protege nada.
        if (in_array($this->pin, ['0000', '1111', '1234', '123456', '000000', '111111'], true)) {
            $this->addError('pin', 'Escolha um PIN menos óbvio.');
            return;
        }

        try {
            auth()->user()->definirPinPos($this->pin);
        } catch (\InvalidArgumentException $e) {
            $this->addError('pin', $e->getMessage());
            return;
        }

        $this->reset(['pin', 'pin_confirmation', 'password']);
        $this->jaTemPin = true;

        $this->dispatch('notify', type: 'success', message: __(
            'PIN definido. Vai para os tablets na próxima sincronização com internet.'
        ));
    }

    public function render()
    {
        return view('livewire.invoicing.offline.definir-pin');
    }
}
