<?php

namespace App\Http\Controllers\Registo;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Registo\AssistenteDeRegisto;
use App\Services\Registo\RegistarEmpresa;
use App\Support\ContaDaPlataforma;
use App\Support\EcraReact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * O REGISTO DE UMA CONTA NOVA — a página e as acções do assistente.
 *
 * Cada acção recebe o que o ecrã tem escrito, faz o passo, e devolve o estado
 * novo inteiro: o servidor continua a ser quem decide em que passo se está, que
 * planos estão fechados e se há alguma coisa a pagar.
 */
class RegistoController extends Controller
{
    public function index(Request $request)
    {
        $a = AssistenteDeRegisto::abrir($request);

        return EcraReact::solta('registo/assistente', 'Criar conta', [
            'estado' => $a->paraEcra(),
            'regimes' => collect(Tenant::REGIMES)->map(fn ($r, $chave) => [
                'valor' => $chave,
                'rotulo' => __($r['label']),
                'descricao' => __($r['description']),
                'volume' => __($r['turnover']),
            ])->values(),
            'conta' => ContaDaPlataforma::dados(),
            'site' => route('landing.home'),
            'entrar' => route('login'),
        ], ['pixel' => true])();
    }

    public function seguinte(Request $request): JsonResponse
    {
        $a = AssistenteDeRegisto::doPedido($request);

        try {
            $a->seguinte();
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Muitos erros de uma vez é, quase sempre, um formulário ainda por
            // preencher — não se apaga nada; fica só o registo.
            if (count($e->errors()) >= 3) {
                Log::warning('Vários erros de validação de uma vez', ['errors_count' => count($e->errors()), 'current_step' => $a->passo]);
            }
            // O que já foi escrito guarda-se à mesma.
            $a->guardar();

            throw $e;
        }

        return response()->json($a->paraEcra());
    }

    public function anterior(Request $request): JsonResponse
    {
        $a = AssistenteDeRegisto::doPedido($request);
        $a->anterior();

        return response()->json($a->paraEcra());
    }

    public function outroPlano(Request $request): JsonResponse
    {
        $a = AssistenteDeRegisto::doPedido($request);
        $a->escolherOutroPlano();

        return response()->json($a->paraEcra());
    }

    /** Guarda o que está escrito — o `updated()` de sempre. */
    public function guardar(Request $request): JsonResponse
    {
        $a = AssistenteDeRegisto::doPedido($request);
        $a->guardar();

        return response()->json(['guardado_em' => session('wizard_progress.saved_at')]);
    }

    public function recomecar(Request $request): JsonResponse
    {
        $a = AssistenteDeRegisto::doPedido($request);
        $a->recomecar(__('Progresso reiniciado. Comece novamente.'));

        return response()->json($a->paraEcra());
    }

    public function registar(Request $request, RegistarEmpresa $registar): JsonResponse
    {
        $a = AssistenteDeRegisto::doPedido($request);
        $a->validarTudo();

        try {
            $r = $registar->registar($a);
        } catch (\Throwable $e) {
            Log::error('ERRO AO CRIAR CONTA', ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            return response()->json(['message' => __('Erro ao criar conta: :erro', ['erro' => $e->getMessage()])], 500);
        }

        AssistenteDeRegisto::esquecer();

        if (! $a->autenticado) {
            Auth::login($r['utilizador']);
            $request->session()->regenerate();
        }

        session()->flash('success', match ($r['estado']) {
            'pending' => __('Empresa criada com sucesso! Seu pagamento está aguardando aprovação. Você receberá acesso total assim que for aprovado.'),
            'trial' => __('Empresa criada com sucesso! Você tem :dias dias de teste grátis. Bem-vindo ao SOSERP!', ['dias' => $r['dias_de_teste']]),
            default => __('Empresa criada com sucesso! Bem-vindo ao SOSERP.'),
        });

        return response()->json(['ir_para' => route('home'), 'estado' => $r['estado']]);
    }
}
