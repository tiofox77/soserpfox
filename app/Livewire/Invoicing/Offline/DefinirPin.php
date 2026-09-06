<?php

namespace App\Livewire\Invoicing\Offline;

use App\Services\POS\DefinicaoDePin;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O funcionário define ou muda o seu PIN de turno — o ecrã de sempre.
 * A regra (password a confirmar, PIN que proteja alguma coisa) vive na
 * `DefinicaoDePin`, partilhada com o ecrã em React.
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
        return DefinicaoDePin::regras();
    }

    protected function messages(): array
    {
        return DefinicaoDePin::mensagens();
    }

    public function mount(): void
    {
        $this->jaTemPin = auth()->user()->temPinPos();
    }

    public function guardar(): void
    {
        $this->validate();

        try {
            app(DefinicaoDePin::class)->definir(auth()->user(), $this->pin, $this->password);
        } catch (ValidationException $e) {
            // Limpar o PIN também na recusa: é propriedade pública e ficaria
            // no snapshot devolvido ao cliente até nova submissão.
            $this->reset(['pin', 'pin_confirmation']);

            throw $e;
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
