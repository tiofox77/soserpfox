<?php

namespace App\Services\POS;

use App\Models\User;
use App\Support\PinDeTurno;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * O funcionário define ou muda o seu PIN de turno.
 *
 * O PIN abre turno offline no POS. É uma credencial de chão de loja,
 * separada da password da conta: confirma-se com a password (para ninguém
 * definir um PIN a partir de uma sessão deixada aberta), mas o que fica
 * guardado é só o bcrypt do PIN, que segue para os tablets na próxima
 * sincronização. Os dois ecrãs — Livewire e React — passam por aqui.
 */
class DefinicaoDePin
{
    public static function regras(): array
    {
        return [
            'pin' => ['required', 'digits_between:4,6', 'confirmed'],
            'password' => ['required', 'string'],
        ];
    }

    public static function mensagens(): array
    {
        return [
            'pin.required' => 'Escreva o PIN.',
            'pin.digits_between' => 'O PIN tem de ter 4 a 6 dígitos.',
            'pin.confirmed' => 'Os dois PIN não coincidem.',
            'password.required' => 'Confirme com a sua palavra-passe.',
        ];
    }

    /** Recusa com o campo certo: a password errada, ou um PIN que não protege nada. */
    public function definir(User $utilizador, string $pin, string $password): void
    {
        // A password confirma que é mesmo o dono da conta a definir o PIN.
        if (!Hash::check($password, $utilizador->password)) {
            throw ValidationException::withMessages(['password' => 'Palavra-passe incorrecta.']);
        }

        if ($recusa = PinDeTurno::recusa($pin)) {
            throw ValidationException::withMessages(['pin' => $recusa]);
        }

        try {
            $utilizador->definirPinPos($pin);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['pin' => $e->getMessage()]);
        }
    }
}
