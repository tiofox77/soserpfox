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
    /** Menos do que isto, do abrir da página ao «Criar conta», não é uma pessoa. */
    private const SEGUNDOS_MINIMOS = 5;

    public function index(Request $request)
    {
        $a = AssistenteDeRegisto::abrir($request);
        if (! session()->has('registo_aberto_em')) {
            session(['registo_aberto_em' => time()]);
        }

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
        $this->travarRobos($request);
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
        $this->travarRobos($request, final: true);
        $a = AssistenteDeRegisto::doPedido($request);
        $a->validarTudo();

        try {
            $r = $registar->registar($a);
        } catch (\Throwable $e) {
            // A mensagem da excepção FICA NO LOG. Ia para o browser: uma
            // QueryException traz o SQL com os valores — email, a impressão da
            // senha, o NIF (auditoria de segurança de 2026-09-15).
            $referencia = strtoupper(\Illuminate\Support\Str::random(8));
            Log::error('ERRO AO CRIAR CONTA', ['referencia' => $referencia, 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            return response()->json(['message' => __('Não foi possível criar a conta. Tente de novo; se voltar a acontecer, contacte o suporte com a referência :ref.', ['ref' => $referencia])], 500);
        }

        // A PROVA DE QUE ACEITOU. A caixa dos Termos e da Política validava-se e
        // não ficava em lado nenhum — o RGPD pede para o poder demonstrar.
        foreach (['termos', 'privacidade'] as $tipo) {
            \App\Services\Privacidade\Consentimentos::registar($tipo, true, 'registo', $r['utilizador'], null, $request);
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

    /**
     * DUAS ARMADILHAS PARA ROBÔS, sem captcha a incomodar pessoas.
     *
     *  · o campo `website` está escondido de quem vê a página e dos leitores de
     *    ecrã; um robô que preenche tudo preenche-o;
     *  · ninguém escreve os dados, a empresa, escolhe o plano e aceita os termos
     *    em menos de cinco segundos.
     *
     * A resposta é a de um erro vulgar: dizer «apanhámos-te» ensinava o robô.
     */
    private function travarRobos(Request $request, bool $final = false): void
    {
        $aberto = (int) session('registo_aberto_em', 0);
        $depressa = $final && $aberto > 0 && (time() - $aberto) < self::SEGUNDOS_MINIMOS;

        if (filled($request->input('website')) || $depressa) {
            Log::warning('Registo recusado pelas armadilhas de robôs', ['campo' => filled($request->input('website')), 'depressa' => $depressa]);

            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => [__('Não foi possível continuar. Recarregue a página e tente de novo.')],
            ]);
        }
    }
}
