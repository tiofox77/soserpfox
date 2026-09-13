<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Licensing\LicenseManager;
use App\Services\Setup\CriarPrimeiraEmpresa;
use App\Support\EcraReact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * O ASSISTENTE DE 1.ª UTILIZAÇÃO DO ON-PREMISE — a página e o envio.
 *
 * Só se regista com `licensing.enforce` ligado (a build offline). A página abre
 * enquanto não houver empresa nenhuma e a licença deixar; os dados que a
 * licença assina (empresa, NIF, plano) vêm pré-preenchidos.
 */
class AssistenteDeSetupController extends Controller
{
    public function __construct(private readonly LicenseManager $licencas, private readonly CriarPrimeiraEmpresa $criar)
    {
    }

    public function index()
    {
        if ($this->criar->jaHaEmpresa()) {
            return redirect('/login');
        }

        $estado = $this->licencas->estado();
        if ($estado->bloqueiaTudo()) {
            return redirect('/licenca');
        }

        $p = $estado->payload;

        return EcraReact::solta('setup/assistente', 'Bem-vindo ao SOSERP', [
            'empresa' => $p?->empresa() ?? '',
            'nif' => $p?->nif() ?? '',
            'plano' => $p?->plano(),
            'regimes' => collect(Tenant::REGIMES)->map(fn ($r, $chave) => ['valor' => $chave, 'rotulo' => __($r['label'])])->values(),
        ])();
    }

    public function finalizar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'empresa' => 'required|string|min:3|max:255',
            'nif' => 'nullable|string|max:20',
            'regime' => 'required|string|in:'.implode(',', array_keys(Tenant::REGIMES)),
            'endereco' => 'nullable|string|max:255',
            'telefone' => 'nullable|string|max:30',
            'email_empresa' => 'nullable|email|max:255',
            'admin_nome' => 'required|string|min:3|max:255',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8|confirmed',
        ], [], [
            'empresa' => __('Nome da empresa'),
            'regime' => __('Regime fiscal'),
            'email_empresa' => __('Email da empresa'),
            'admin_nome' => __('Nome'),
            'admin_email' => __('Email (login)'),
            'admin_password' => __('Password'),
        ]);

        // Dois separadores abertos no assistente: o segundo envio não cria outra.
        if ($this->criar->jaHaEmpresa()) {
            return response()->json(['message' => __('A empresa já foi criada.'), 'ir_para' => '/login']);
        }

        // Um 500 anónimo no último passo deixava o cliente sem saber se a
        // empresa ficou criada. A transacção garante que não; a mensagem diz
        // o que falhou.
        try {
            $this->criar->criar($dados, $this->licencas->estado()->payload?->plano());
        } catch (\Throwable $e) {
            Log::error('Setup on-premise falhou', ['erro' => $e->getMessage()]);

            return response()->json([
                'message' => __('Não foi possível criar a empresa: :erro', ['erro' => $e->getMessage()]),
            ], 500);
        }

        session()->flash('ok', __('Empresa criada. Já pode entrar com o administrador.'));

        return response()->json(['message' => __('Empresa criada. Já pode entrar com o administrador.'), 'ir_para' => '/login']);
    }
}
