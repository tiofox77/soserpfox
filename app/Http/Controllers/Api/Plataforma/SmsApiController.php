<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Models\SmsSetting;
use App\Models\SmsTemplate;
use App\Services\SmsService;
use App\Services\TelcoSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * O SMS DA PLATAFORMA — o fornecedor, os modelos de mensagem e o histórico.
 *
 * As regras vêm do componente, onde foram aprendidas:
 *
 *  · AS CHAVES NUNCA VOLTAM AO BROWSER. Diz-se só se estão guardadas; o campo
 *    vazio é «manter a que está».
 *  · COM A TELCOSMS o remetente é SOSERP e não se escolhe, e o relatório de
 *    entrega não se configura.
 *  · A CHAVE QAS é obrigatória só quando a aplicação escolhida é a de testes.
 *  · O TESTE USA O QUE ESTÁ NO FORMULÁRIO: grava antes de enviar. Escolher a
 *    TelcoSMS e testar sem ter carregado em «Guardar» ainda usava a D7.
 *  · O SALDO INDISPONÍVEL NÃO É UMA CHAVE ERRADA: a TelcoSMS às vezes não o dá,
 *    e o ecrã dizia «erro» a quem tinha tudo bem.
 */
class SmsApiController extends Controller
{
    private const FORNECEDORES = ['d7networks', 'telcosms'];

    public function index(): JsonResponse
    {
        $s = SmsSetting::whereNull('tenant_id')->first();

        return response()->json([
            'configuracao' => [
                'provider' => $s->provider ?? 'd7networks',
                'api_url' => $s->api_url ?? 'https://api.d7networks.com/messages/v1/send',
                'sender_id' => $s->sender_id ?? 'SOS ERP',
                'telco_application' => data_get($s?->config, 'telco_application', 'soserp_prd'),
                'report_url' => $s->report_url ?? '',
                'is_active' => (bool) ($s->is_active ?? true),
                'token_guardado' => filled($s?->api_token),
                'chave_qas_guardada' => filled($s?->telco_api_key_qas),
            ],
            'numeros' => [
                'total' => SmsLog::count(),
                'enviados' => SmsLog::where('status', 'sent')->count(),
                'falhados' => SmsLog::where('status', 'failed')->count(),
                'hoje' => SmsLog::whereDate('created_at', today())->count(),
            ],
            'modelos' => SmsTemplate::whereNull('tenant_id')->orderBy('name')->get()
                ->map(fn (SmsTemplate $m) => [
                    'id' => $m->id,
                    'nome' => $m->name,
                    'slug' => $m->slug,
                    'conteudo' => $m->content,
                    'descricao' => $m->description,
                    // As variáveis vêm como {nome => descrição}: a lista perdia o nome.
                    'variaveis' => collect($m->variables ?? [])->map(fn ($desc, $nome) => [
                        'nome' => is_int($nome) ? (string) $desc : (string) $nome,
                        'descricao' => is_int($nome) ? null : $desc,
                    ])->values(),
                    'activo' => (bool) $m->is_active,
                    'caracteres' => mb_strlen((string) $m->content),
                ])->values(),
            'tipos' => SmsLog::whereNotNull('type')->where('type', '!=', '')->distinct()->orderBy('type')->pluck('type')->values(),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->gravar($request);

        return response()->json(['message' => __('Configuração do SMS guardada.')]);
    }

    public function saldo(Request $request): JsonResponse
    {
        $d = $request->validate([
            'telco_application' => ['nullable', 'in:soserp_prd,soserp_qas'],
        ]);

        $s = SmsSetting::whereNull('tenant_id')->first();

        if (($s?->provider) !== 'telcosms') {
            throw ValidationException::withMessages(['provider' => __('O saldo só se consulta com a TelcoSMS.')]);
        }

        $qas = ($d['telco_application'] ?? data_get($s->config, 'telco_application')) === 'soserp_qas';
        $chave = (string) ($qas ? $s->telco_api_key_qas : $s->api_token);

        $r = (new TelcoSmsService($chave))->checkBalance();

        if (($r['balance_unavailable'] ?? false) === true) {
            $ultimo = SmsLog::where('sender_id', 'SOSERP')->where('status', 'sent')->latest('sent_at')->first();

            return response()->json([
                'aviso' => true,
                'message' => $ultimo
                    ? __('A TelcoSMS está a funcionar, mas não deu o saldo agora (HTTP :http). O último SMS aceite foi a :dia.', [
                        'http' => $r['status'] ?? 500, 'dia' => $ultimo->sent_at?->format('d/m/Y H:i'),
                    ])
                    : __('A TelcoSMS não deu o saldo agora (HTTP :http). Isto não quer dizer que a chave esteja errada: envie um SMS de teste para confirmar.', [
                        'http' => $r['status'] ?? 500,
                    ]),
            ]);
        }

        if (! $r['success']) {
            throw ValidationException::withMessages(['provider' => $r['message']]);
        }

        return response()->json([
            'message' => trim($r['message'].(($r['balance'] ?? null) !== null ? ' '.__('Saldo: :s', ['s' => $r['balance']]) : '')),
            'saldo' => $r['balance'] ?? null,
        ]);
    }

    /** O texto de um modelo, com dados de exemplo — para o teste. */
    public function previsualizar(int $modelo): JsonResponse
    {
        $m = SmsTemplate::whereNull('tenant_id')->where('is_active', true)->findOrFail($modelo);

        return response()->json([
            'mensagem' => $m->render([
                'app_name' => config('app.name', 'SOS ERP'),
                'test_message' => now()->format('d/m/Y H:i:s'),
                'tenant_name' => __('Empresa de Teste'),
                'plan_name' => __('Plano de Teste'),
                'days_remaining' => 5,
                'app_url' => config('app.url'),
                'user_name' => __('Utilizador de Teste'),
                'user_email' => 'teste@exemplo.ao',
                'user_password' => '********',
            ]),
        ]);
    }

    public function testar(Request $request, SmsService $sms): JsonResponse
    {
        $d = $request->validate([
            'test_phone' => ['required', 'string', 'max:30'],
            'test_message' => ['required', 'string', 'max:918'],
        ], [], [
            'test_phone' => __('telefone'),
            'test_message' => __('mensagem'),
        ]);

        // O TESTE USA O QUE ESTÁ NO FORMULÁRIO — grava primeiro.
        $this->gravar($request);

        $r = $sms->send($d['test_phone'], $d['test_message'], 'test', auth()->id(), null);

        if (! ($r['success'] ?? false)) {
            throw ValidationException::withMessages([
                'test_phone' => __('O SMS não saiu: :erro', ['erro' => $r['error'] ?? __('erro desconhecido')]),
            ]);
        }

        return response()->json(['message' => __('SMS de teste enviado para :n.', ['n' => $d['test_phone']])]);
    }

    public function guardarModelo(Request $request, int $id): JsonResponse
    {
        $m = SmsTemplate::whereNull('tenant_id')->findOrFail($id);

        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:918'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ], [], [
            'name' => __('nome'),
            'content' => __('conteúdo'),
        ]);

        // O SLUG NÃO SE MUDA: é por ele que o código pede o modelo. Mudá-lo
        // calava o aviso que o usava, sem erro nenhum.
        $m->update([
            'name' => $d['name'],
            'content' => $d['content'],
            'description' => $d['description'] ?? null,
            'is_active' => (bool) ($d['is_active'] ?? true),
        ]);

        return response()->json(['message' => __('Modelo :nome guardado.', ['nome' => $m->name])]);
    }

    public function historico(Request $request): JsonResponse
    {
        $f = $request->validate([
            'gateway' => ['nullable', 'in:telcosms,d7networks'],
            'estado' => ['nullable', 'in:sent,failed,pending,delivered'],
            'tipo' => ['nullable', 'string', 'max:60'],
            'procura' => ['nullable', 'string', 'max:120'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $termo = trim((string) ($f['procura'] ?? ''));

        $pagina = SmsLog::with(['user:id,name', 'tenant:id,name'])
            // Os registos antigos não têm `gateway`: o remetente SOSERP é da
            // TelcoSMS, o resto era da D7.
            ->when(($f['gateway'] ?? '') === 'telcosms', fn ($q) => $q->where(fn ($x) => $x
                ->where('gateway', 'telcosms')->orWhere(fn ($o) => $o->whereNull('gateway')->where('sender_id', 'SOSERP'))))
            ->when(($f['gateway'] ?? '') === 'd7networks', fn ($q) => $q->where(fn ($x) => $x
                ->where('gateway', 'd7networks')->orWhere(fn ($o) => $o->whereNull('gateway')->where('sender_id', '!=', 'SOSERP'))))
            ->when(! empty($f['estado']), fn ($q) => $q->where('status', $f['estado']))
            ->when(! empty($f['tipo']), fn ($q) => $q->where('type', $f['tipo']))
            ->when($termo !== '', fn ($q) => $q->where(fn ($x) => $x->where('recipient', 'like', "%{$termo}%")->orWhere('message', 'like', "%{$termo}%")))
            ->when(! empty($f['de']), fn ($q) => $q->whereDate('sent_at', '>=', $f['de']))
            ->when(! empty($f['ate']), fn ($q) => $q->whereDate('sent_at', '<=', $f['ate']))
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'pagina', $f['pagina'] ?? 1);

        return response()->json([
            'registos' => collect($pagina->items())->map(fn (SmsLog $l) => [
                'id' => $l->id,
                'destino' => $l->recipient,
                'mensagem' => $l->message,
                'remetente' => $l->sender_id,
                'gateway' => $l->gateway ?? ($l->sender_id === 'SOSERP' ? 'telcosms' : 'd7networks'),
                'tipo' => $l->type,
                'estado' => $l->status,
                'erro' => $l->error_message,
                'pedido' => $l->request_id,
                'quem' => $l->user?->name,
                'empresa' => $l->tenant?->name,
                'enviado_em' => ($l->sent_at ?? $l->created_at)?->format('d/m/Y H:i'),
                'entregue_em' => $l->delivered_at?->format('d/m/Y H:i'),
            ])->values(),
            'paginacao' => ['pagina' => $pagina->currentPage(), 'ultima' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    /** A gravação partilhada entre «Guardar» e «Testar». */
    private function gravar(Request $request): SmsSetting
    {
        $d = $request->validate([
            'provider' => ['required', Rule::in(self::FORNECEDORES)],
            'api_url' => ['required', 'url', 'max:255'],
            'api_token' => ['nullable', 'string', 'max:500'],
            'telco_api_key_qas' => ['nullable', 'string', 'max:500'],
            'sender_id' => ['nullable', 'string', 'max:11'],
            'telco_application' => ['required_if:provider,telcosms', 'nullable', 'in:soserp_prd,soserp_qas'],
            'report_url' => ['nullable', 'url', 'max:255'],
            'is_active' => ['boolean'],
        ], [], [
            'api_url' => __('endereço da API'),
            'api_token' => __('chave'),
            'sender_id' => __('remetente'),
        ]);

        $existente = SmsSetting::whereNull('tenant_id')->first();
        $telco = $d['provider'] === 'telcosms';

        if (blank($d['api_token'] ?? null) && blank($existente?->api_token)) {
            throw ValidationException::withMessages(['api_token' => __('Indique o token da D7 ou a chave api_key_app da TelcoSMS.')]);
        }

        if ($telco && ($d['telco_application'] ?? '') === 'soserp_qas'
            && blank($d['telco_api_key_qas'] ?? null) && blank($existente?->telco_api_key_qas)) {
            throw ValidationException::withMessages(['telco_api_key_qas' => __('Indique a chave QAS da aplicação SOSERP.')]);
        }

        $campos = [
            'provider' => $d['provider'],
            'api_url' => $d['api_url'],
            'sender_id' => $telco ? 'SOSERP' : ($d['sender_id'] ?? null),
            'report_url' => $telco ? null : ($d['report_url'] ?? null),
            'config' => $telco
                ? ['telco_application' => $d['telco_application'] ?? 'soserp_prd', 'sender' => 'SOSERP']
                : ($existente?->config ?? []),
            'is_active' => (bool) ($d['is_active'] ?? true),
        ];

        if (filled($d['api_token'] ?? null)) {
            $campos['api_token'] = trim($d['api_token']);
        }

        if ($telco && filled($d['telco_api_key_qas'] ?? null)) {
            $campos['telco_api_key_qas'] = trim($d['telco_api_key_qas']);
        }

        return SmsSetting::updateOrCreate(['tenant_id' => null], $campos);
    }
}
