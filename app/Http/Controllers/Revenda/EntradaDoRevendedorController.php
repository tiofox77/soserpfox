<?php

namespace App\Http\Controllers\Revenda;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Support\Seguranca\RegraDaSenha;
use App\Support\Seguranca\TravaoDeEntradas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ENTRAR NO PORTAL DO REVENDEDOR (RV-07).
 *
 * As regras das outras portas: 5 falhas fecham 10 minutos (TravaoDeEntradas),
 * e só entra quem está APROVADO. Um pedido por aprovar ou recusado ouve a
 * razão, depois de acertar a senha — antes disso não se diz nada sobre a conta.
 */
class EntradaDoRevendedorController extends Controller
{
    public function entrar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);
        $email = mb_strtolower(trim($dados['email']));
        $travao = TravaoDeEntradas::para('revendedor');

        if ($segundos = $travao->bloqueadoPor($email, $request->ip())) {
            throw ValidationException::withMessages(['email' => TravaoDeEntradas::mensagemDeBloqueio($segundos)])->status(429);
        }

        $revendedor = Reseller::where('email', $email)->first();

        if (! $revendedor || ! Hash::check($dados['password'], $revendedor->password)) {
            $restam = $travao->falhou($email, $request->ip());

            throw ValidationException::withMessages([
                'email' => TravaoDeEntradas::mensagemDeFalha($restam, __('O email ou a senha não estão certos.')),
            ])->status($restam === 0 ? 429 : 422);
        }

        $travao->entrou($email, $request->ip());

        if (! $revendedor->aprovado()) {
            throw ValidationException::withMessages(['email' => self::porQueNaoEntra($revendedor)]);
        }

        Auth::guard('revendedor')->login($revendedor, (bool) ($dados['remember'] ?? false));
        $revendedor->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();

        return response()->json([
            'message' => __('Bem-vindo, :nome!', ['nome' => $revendedor->name]),
            'ir_para' => redirect()->intended(route('revendedor.painel'))->getTargetUrl(),
        ]);
    }

    public static function porQueNaoEntra(Reseller $r): string
    {
        return match ($r->status) {
            'pendente' => __('O seu pedido ainda está a ser analisado. Avisamos por email quando for aprovado.'),
            'recusado' => __('O seu pedido de revendedor não foi aceite.'),
            'suspenso' => __('A sua conta de revendedor está suspensa. Fale connosco para saber mais.'),
            default => __('Esta conta não pode entrar.'),
        };
    }

    public function sair(Request $request): RedirectResponse
    {
        Auth::guard('revendedor')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('revendedor.login');
    }

    /** O link para uma senha nova. A resposta é a mesma quer o email exista quer não. */
    public function pedirNovaSenha(Request $request): JsonResponse
    {
        $dados = $request->validate(['email' => ['required', 'email']]);

        Password::broker('revendedores')->sendResetLink(['email' => mb_strtolower(trim($dados['email']))]);

        return response()->json(['message' => __('Se o email tiver conta de revendedor, enviámos um link para escolher uma senha nova.')]);
    }

    public function novaSenha(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', RegraDaSenha::regra()],
        ]);

        $estado = Password::broker('revendedores')->reset(
            ['email' => mb_strtolower(trim($dados['email'])), 'token' => $dados['token'], 'password' => $dados['password'], 'password_confirmation' => $request->input('password_confirmation')],
            function (Reseller $r, string $senha) {
                $r->forceFill(['password' => $senha, 'remember_token' => Str::random(60)])->save();
            },
        );

        if ($estado !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('O link já não é válido. Peça outro.')]);
        }

        return response()->json(['message' => __('Senha mudada. Já pode entrar.'), 'ir_para' => route('revendedor.login')]);
    }
}
