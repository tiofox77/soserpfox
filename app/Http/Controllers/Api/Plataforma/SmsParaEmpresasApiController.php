<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SmsSetting;
use App\Models\SmsTemplate;
use App\Models\Tenant;
use App\Services\SmsService;
use App\Support\PartesDeSms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * ENVIAR UM SMS ÀS EMPRESAS — a todas, ou só às escolhidas.
 *
 * O SMS custa por mensagem e um engano aqui multiplica-se por todas as
 * empresas. As travas do componente ficam todas:
 *
 *  · REVER ANTES DE ENVIAR. A revisão devolve uma assinatura do que mostrou —
 *    quem recebe e o texto — e o envio só avança com essa assinatura. Se
 *    alguma coisa mudou entretanto (o público no ecrã, uma empresa que ganhou
 *    telefone, uma vírgula na mensagem), recusa e pede nova revisão: confirmar
 *    uma coisa e enviar outra era o defeito que o componente evitava desfazendo
 *    o «por confirmar». Contar só os destinatários não chegava — trocar três
 *    empresas por outras três passava.
 *  · QUEM NÃO TEM TELEFONE NÃO CONTA, e diz-se quem é.
 *  · UMA FALHA NÃO PARA AS OUTRAS, e o retorno do SmsService conta: ele apanha
 *    as falhas do fornecedor por dentro e devolve um array.
 *
 * E uma correcção: escolher um modelo enchia a caixa de texto, mas o envio
 * usava o MODELO e ignorava o que se tivesse corrigido na caixa. Agora o que
 * sai é o que está escrito, com as variáveis do modelo trocadas por empresa.
 */
class SmsParaEmpresasApiController extends Controller
{
    private const VARIAVEIS = ['app_name', 'tenant_name', 'app_url'];

    public function index(): JsonResponse
    {
        $config = SmsSetting::whereNull('tenant_id')->where('is_active', true)->first();

        return response()->json([
            'configurado' => (bool) $config,
            'gateway' => $config?->provider,
            'empresas' => Tenant::where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone'])
                ->map(fn (Tenant $t) => ['id' => $t->id, 'nome' => $t->name, 'telefone' => $t->phone ?: null]),
            'planos' => Plan::orderBy('name')->get(['id', 'name'])->map(fn (Plan $p) => ['id' => $p->id, 'nome' => $p->name]),
            'modelos' => SmsTemplate::whereNull('tenant_id')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'content'])
                ->map(fn (SmsTemplate $m) => ['id' => $m->id, 'nome' => $m->name, 'conteudo' => $m->content]),
            'variaveis' => self::VARIAVEIS,
        ]);
    }

    /** Passo 1: a quem vai, quem fica de fora, e quanto custa em partes. */
    public function rever(Request $request): JsonResponse
    {
        $dados = $this->validar($request);
        $alvo = $this->alvo($dados);
        $podem = $this->comTelefone($alvo);

        if ($podem->isEmpty()) {
            throw ValidationException::withMessages(['mensagem' => __('Nenhuma das empresas escolhidas tem telefone registado.')]);
        }

        $partes = PartesDeSms::contar($dados['mensagem']);

        return response()->json([
            'alvo' => $alvo->count(),
            'com_telefone' => $podem->count(),
            'sem_telefone' => $alvo->filter(fn ($e) => empty($e->phone))->pluck('name')->values(),
            'partes' => $partes,
            'total_de_partes' => $partes * $podem->count(),
            'assinatura' => $this->assinatura($podem, $dados['mensagem']),
        ]);
    }

    public function enviar(Request $request): JsonResponse
    {
        $dados = $this->validar($request, true);
        $destinatarios = $this->comTelefone($this->alvo($dados));

        if ($destinatarios->isEmpty()) {
            throw ValidationException::withMessages(['mensagem' => __('Nenhuma das empresas escolhidas tem telefone registado.')]);
        }

        if (! hash_equals($this->assinatura($destinatarios, $dados['mensagem']), (string) $dados['assinatura'])) {
            throw ValidationException::withMessages(['assinatura' => __('A lista de destinatários ou a mensagem mudaram desde a revisão. Reveja antes de enviar.')]);
        }

        $servico = new SmsService();
        $enviados = 0;
        $partes = 0;
        $falhados = [];

        foreach ($destinatarios as $empresa) {
            $texto = $this->textoPara($dados['mensagem'], $empresa);

            try {
                $resultado = $servico->send($empresa->phone, $texto, 'aviso_plataforma', null, $empresa->id);

                if (is_array($resultado) && ($resultado['success'] ?? true) === false) {
                    throw new \RuntimeException($resultado['error'] ?? 'o fornecedor recusou');
                }

                $enviados++;
                $partes += PartesDeSms::contar($texto);
            } catch (\Throwable $e) {
                $falhados[] = $empresa->name;

                Log::error('SMS às empresas: falhou num destinatário.', ['tenant_id' => $empresa->id, 'erro' => $e->getMessage()]);
            }
        }

        Log::info('SMS às empresas concluído.', ['enviados' => $enviados, 'falhados' => count($falhados), 'publico' => $dados['publico']]);

        return response()->json([
            'message' => __('SMS enviado a :n empresa(s).', ['n' => $enviados]),
            'resultado' => ['enviados' => $enviados, 'falhados' => $falhados, 'partes' => $partes],
        ]);
    }

    private function validar(Request $request, bool $aEnviar = false): array
    {
        $dados = $request->validate([
            // 640 = quatro partes GSM-7. Acima disto o custo por destinatário
            // começa a ser difícil de justificar sem alguém reparar.
            'mensagem' => ['required', 'string', 'min:3', 'max:640'],
            'publico' => ['required', 'in:todas,empresas,planos'],
            'empresa_ids' => ['array'],
            'empresa_ids.*' => ['integer'],
            'plano_ids' => ['array'],
            'plano_ids.*' => ['integer'],
            'assinatura' => [$aEnviar ? 'required' : 'nullable', 'string', 'size:64'],
        ], [
            'mensagem.required' => __('Escreva a mensagem.'),
            'mensagem.max' => __('A mensagem é demasiado longa (máximo 640 caracteres).'),
            'assinatura.required' => __('Reveja os destinatários antes de enviar.'),
        ]);

        if ($dados['publico'] === 'empresas' && empty($dados['empresa_ids'])) {
            throw ValidationException::withMessages(['empresa_ids' => __('Escolha pelo menos uma empresa.')]);
        }

        if ($dados['publico'] === 'planos' && empty($dados['plano_ids'])) {
            throw ValidationException::withMessages(['plano_ids' => __('Escolha pelo menos um plano.')]);
        }

        return $dados;
    }

    /** As empresas activas do público escolhido, tenham telefone ou não. */
    private function alvo(array $dados): Collection
    {
        return Tenant::where('is_active', true)
            ->when($dados['publico'] === 'empresas', fn ($q) => $q->whereIn('id', $dados['empresa_ids'] ?: [0]))
            // Pelo plano da subscrição activa, que é o que define em que plano
            // a empresa está hoje — e não pelo histórico dela.
            ->when($dados['publico'] === 'planos', fn ($q) => $q->whereHas('subscriptions', fn ($s) => $s
                ->whereIn('plan_id', $dados['plano_ids'] ?: [0])
                ->whereIn('status', ['active', 'trial'])))
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);
    }

    private function comTelefone(Collection $alvo): Collection
    {
        return $alvo->filter(fn ($e) => ! empty($e->phone))->values();
    }

    /** O que a revisão mostrou: estas empresas, este texto. */
    private function assinatura(Collection $destinatarios, string $mensagem): string
    {
        return hash_hmac('sha256', $destinatarios->pluck('id')->sort()->implode(',').'|'.trim($mensagem), (string) config('app.key'));
    }

    private function textoPara(string $mensagem, Tenant $empresa): string
    {
        return strtr($mensagem, [
            '{{app_name}}' => (string) config('app.name', 'SOS ERP'),
            '{{tenant_name}}' => (string) $empresa->name,
            '{{app_url}}' => (string) config('app.url'),
        ]);
    }
}
