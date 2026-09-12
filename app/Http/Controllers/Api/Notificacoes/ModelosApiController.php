<?php

namespace App\Http\Controllers\Api\Notificacoes;

use App\Helpers\PhoneHelper;
use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Models\TenantNotificationSetting;
use App\Services\Notifications\EnvioDeNotificacoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OS MODELOS DE NOTIFICAÇÃO: o que se diz, em que canal, e quando.
 *
 * O QUE ESTAVA ABERTO À RUA. O ecrã antigo lia e escrevia os modelos por
 * `NotificationTemplate::findOrFail($id)` — sem uma única verificação de
 * empresa. Um número escrito à mão abria, alterava e APAGAVA o modelo de outra
 * casa. Pior: o «enviar um teste» ia buscar as definições pelo `tenant_id` do
 * modelo encontrado — ou seja, mandava um e-mail pelo servidor SMTP de outra
 * empresa, com o endereço dela, a pedido de quem escrevesse o número.
 *
 * E O TESTE MENTIA. O canal de SMS era um `TODO` que escrevia no log e, logo a
 * seguir, somava «📱 SMS» aos canais enviados: o ecrã dizia «Teste enviado com
 * sucesso via SMS» sem nada ter saído. Agora o teste passa pelo MESMO caminho
 * do envio a sério (`EnvioDeNotificacoes`) — é essa a única forma de um teste
 * significar alguma coisa.
 */
class ModelosApiController extends Controller
{
    private const EVENTOS = ['created', 'updated', 'date_approaching', 'status_changed', 'custom'];

    private function empresaId(): int
    {
        return (int) activeTenantId();
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function podeEnviar(Request $request): void
    {
        abort_unless(
            $request->user()?->can('notifications.send') || $request->user()?->can('notifications.manage'),
            403,
            __('Sem permissão para enviar testes.'),
        );
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    /** O modelo TEM de ser desta empresa — era esta a linha que faltava. */
    private function daCasa(int $id): NotificationTemplate
    {
        return NotificationTemplate::where('tenant_id', $this->empresaId())->findOrFail($id);
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'notifications.view');

        return response()->json([
            'modulos' => collect(NotificationTemplate::getAvailableModules())
                ->map(fn ($rotulo, $valor) => ['valor' => $valor, 'rotulo' => $rotulo])->values(),
            'eventos' => collect(NotificationTemplate::getAvailableTriggers())
                ->map(fn ($rotulo, $valor) => ['valor' => $valor, 'rotulo' => $rotulo])->values(),
            'permissoes' => [
                'gerir' => (bool) $request->user()?->can('notifications.manage'),
                'testar' => (bool) ($request->user()?->can('notifications.send')
                    || $request->user()?->can('notifications.manage')),
            ],
        ]);
    }

    /** As variáveis que um módulo sabe preencher, e os dados de exemplo. */
    public function variaveis(Request $request, string $modulo): JsonResponse
    {
        $this->exigir($request, 'notifications.view');

        return response()->json([
            'variaveis' => collect(NotificationTemplate::getModuleVariables($modulo))
                ->map(fn ($v, $chave) => [
                    'chave' => $chave,
                    'rotulo' => $v['label'] ?? $chave,
                    'campo' => $v['field'] ?? null,
                ])->values(),
            'exemplo' => (object) $this->exemplo($modulo),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'notifications.view');

        /*
         * OS MODELOS EM FALTA CRIAM-SE ANTES DE LISTAR.
         *
         * O ecrã estava vazio em todas as empresas menos a primeira: os modelos
         * existiam só lá, postos à mão, e não havia seeder nenhum. Só se
         * ACRESCENTA o que falta — um modelo que a empresa tenha reescrito não
         * pode ser reposto por alguém abrir a página.
         */
        $criados = \App\Services\Notifications\ModelosPadrao::garantirPara($this->empresaId());

        $filtros = $request->validate([
            'canal' => ['nullable', Rule::in(['todos', 'email', 'sms', 'whatsapp'])],
            'modulo' => ['nullable', 'string', 'max:50'],
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(['todos', 'activos', 'inactivos'])],
        ]);

        $base = fn () => NotificationTemplate::where('tenant_id', $this->empresaId());

        $lista = $base()
            ->when(($filtros['modulo'] ?? 'todos') !== 'todos' && ! empty($filtros['modulo']),
                fn ($q) => $q->where('module', $filtros['modulo']))
            ->when(($filtros['canal'] ?? 'todos') !== 'todos',
                fn ($q) => $q->where($filtros['canal'].'_enabled', true))
            ->when(($filtros['estado'] ?? 'todos') === 'activos', fn ($q) => $q->where('is_active', true))
            ->when(($filtros['estado'] ?? 'todos') === 'inactivos', fn ($q) => $q->where('is_active', false))
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('description', 'like', $t));
            })
            ->orderBy('module')->orderBy('name')->get();

        return response()->json([
            'data' => $lista->map(fn (NotificationTemplate $m) => $this->linha($m))->values(),
            'resumo' => [
                'total' => $base()->count(),
                'activos' => $base()->where('is_active', true)->count(),
                'email' => $base()->where('email_enabled', true)->count(),
                'sms' => $base()->where('sms_enabled', true)->count(),
                'whatsapp' => $base()->where('whatsapp_enabled', true)->count(),
                // Um modelo sem canal nenhum ligado nunca manda nada — e não há
                // nada no ecrã que o diga sem se abrir a ficha.
                'sem_canal' => $base()->where('email_enabled', false)
                    ->where('sms_enabled', false)->where('whatsapp_enabled', false)->count(),
            ],
            'criados_agora' => $criados,
        ]);
    }

    private function linha(NotificationTemplate $m): array
    {
        $modulos = NotificationTemplate::getAvailableModules();
        $eventos = NotificationTemplate::getAvailableTriggers();

        return [
            'id' => $m->id,
            'nome' => $m->name,
            'slug' => $m->slug,
            'modulo' => $m->module,
            'modulo_rotulo' => $modulos[$m->module] ?? $m->module,
            'descricao' => $m->description,
            'evento' => $m->trigger_event,
            'evento_rotulo' => $eventos[$m->trigger_event] ?? $m->trigger_event,
            'activo' => (bool) $m->is_active,
            'canais' => array_values(array_filter([
                $m->email_enabled ? 'email' : null,
                $m->sms_enabled ? 'sms' : null,
                $m->whatsapp_enabled ? 'whatsapp' : null,
            ])),
            'antes_minutos' => $m->notify_before_minutes,
            'a_hora' => $m->notify_at_time,
            /*
             * UM MODELO PODE ESTAR CERTO E NÃO DISPARAR.
             *
             * Há módulos sem tabela nesta instalação, e há gatilhos que não têm
             * forma de se apanhar por consulta periódica (`status_changed`
             * exige ver a mudança no momento). Um modelo activo que nunca vai
             * disparar é pior do que um desligado: parece que está a funcionar.
             */
            'dispara' => \App\Services\Notifications\ModelosPadrao::dispara($m->module, $m->trigger_event),
            'porque_nao_dispara' => \App\Services\Notifications\ModelosPadrao::porQueNaoDispara(
                $m->module, $m->trigger_event,
            ),
        ];
    }

    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'notifications.view');

        $m = $this->daCasa($id);

        return response()->json([
            'data' => $this->linha($m) + [
                'email_subject' => $m->email_subject,
                'email_body' => $m->email_body,
                'email_template_id' => $m->email_template_id,
                'sms_body' => $m->sms_body,
                'sms_template_sid' => $m->sms_template_sid,
                'whatsapp_template_sid' => $m->whatsapp_template_sid,
                'email_enabled' => (bool) $m->email_enabled,
                'sms_enabled' => (bool) $m->sms_enabled,
                'whatsapp_enabled' => (bool) $m->whatsapp_enabled,
                'variable_mappings' => (object) ($m->variable_mappings ?? []),
                'conditions' => $m->conditions ?? [],
            ],
        ]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'notifications.manage');

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'module' => ['required', Rule::in(array_keys(NotificationTemplate::getAvailableModules()))],
            'description' => ['nullable', 'string', 'max:500'],
            'trigger_event' => ['required', Rule::in(self::EVENTOS)],
            'notify_before_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notify_at_time' => ['nullable', 'date_format:H:i'],
            'email_enabled' => ['boolean'],
            'sms_enabled' => ['boolean'],
            'whatsapp_enabled' => ['boolean'],
            // O ASSUNTO E O CORPO são obrigatórios com o canal ligado: um
            // modelo de e-mail sem texto mandava uma mensagem em branco.
            'email_subject' => ['exclude_unless:email_enabled,true', 'required', 'string', 'max:255'],
            'email_body' => ['exclude_unless:email_enabled,true', 'required', 'string'],
            'sms_body' => ['exclude_unless:sms_enabled,true', 'required', 'string', 'max:1000'],
            'whatsapp_template_sid' => ['exclude_unless:whatsapp_enabled,true', 'required', 'string', 'max:100'],
            'email_template_id' => ['nullable', 'integer'],
            'sms_template_sid' => ['nullable', 'string', 'max:100'],
            'variable_mappings' => ['nullable', 'array'],
            'conditions' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ], [
            'email_subject.required' => __('Um modelo de e-mail sem assunto manda uma mensagem em branco.'),
            'email_body.required' => __('Escreva o corpo do e-mail.'),
            'sms_body.required' => __('Escreva o texto do SMS.'),
            'whatsapp_template_sid.required' => __('O WhatsApp só manda modelos aprovados — escolha um.'),
        ], [
            'name' => __('nome'), 'module' => __('módulo'), 'trigger_event' => __('evento'),
        ]);

        $m = $id ? $this->daCasa($id) : new NotificationTemplate();

        $m->fill([
            'tenant_id' => $this->empresaId(),
            'name' => $dados['name'],
            'slug' => ($dados['slug'] ?? '') ?: Str::slug($dados['name']),
            'module' => $dados['module'],
            'description' => $dados['description'] ?? null,
            'email_enabled' => (bool) ($dados['email_enabled'] ?? false),
            'sms_enabled' => (bool) ($dados['sms_enabled'] ?? false),
            'whatsapp_enabled' => (bool) ($dados['whatsapp_enabled'] ?? false),
            'email_subject' => $dados['email_subject'] ?? null,
            'email_body' => $dados['email_body'] ?? null,
            'sms_body' => $dados['sms_body'] ?? null,
            'email_template_id' => $dados['email_template_id'] ?? null,
            'sms_template_sid' => $dados['sms_template_sid'] ?? null,
            'whatsapp_template_sid' => $dados['whatsapp_template_sid'] ?? null,
            'trigger_event' => $dados['trigger_event'],
            'notify_before_minutes' => $dados['notify_before_minutes'] ?? null,
            'notify_at_time' => $dados['notify_at_time'] ?? null,
            'conditions' => $dados['conditions'] ?? [],
            'is_active' => (bool) ($dados['is_active'] ?? true),
        ]);

        $m->tenant_id = $this->empresaId();
        $m->variable_mappings = $this->mapear($dados, $m);
        $m->save();

        return response()->json([
            'message' => $id ? __('Modelo actualizado.') : __('Modelo criado.'),
            'data' => $this->linha($m),
        ], $id ? 200 : 201);
    }

    /**
     * AS VARIÁVEIS ESCRITAS NO TEXTO LIGAM-SE SOZINHAS AOS CAMPOS.
     *
     * Quem escreve `{{ cliente }}` no corpo não tem de ir depois a uma segunda
     * lista dizer que `cliente` é `client.name` — o módulo já o sabe. O que
     * tiver sido mapeado à mão nunca é reescrito.
     */
    private function mapear(array $dados, NotificationTemplate $m): array
    {
        $mapeado = $dados['variable_mappings'] ?? [];

        if (! empty($mapeado)) {
            return $mapeado;
        }

        $doModulo = NotificationTemplate::getModuleVariables($dados['module']);
        $textos = array_filter([
            ($dados['sms_enabled'] ?? false) ? ($dados['sms_body'] ?? '') : '',
            ($dados['email_enabled'] ?? false) ? ($dados['email_subject'] ?? '') : '',
            ($dados['email_enabled'] ?? false) ? ($dados['email_body'] ?? '') : '',
        ]);

        $encontradas = [];

        foreach ($textos as $texto) {
            preg_match_all('/\{\{\s*(\w+)\s*\}\}/u', (string) $texto, $achados);
            $encontradas = array_merge($encontradas, $achados[1] ?? []);
        }

        $mapa = [];

        foreach (array_unique($encontradas) as $nome) {
            if (isset($doModulo[$nome]['field'])) {
                $mapa[$nome] = $doModulo[$nome]['field'];
            }
        }

        return $mapa ?: ($m->variable_mappings ?? []);
    }

    public function alternar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'notifications.manage');

        $m = $this->daCasa($id);

        $m->update(['is_active' => ! $m->is_active]);

        return response()->json([
            'message' => $m->is_active ? __('Modelo activado.') : __('Modelo desactivado.'),
            'activo' => (bool) $m->is_active,
        ]);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'notifications.manage');

        // Soft delete: o histórico de envios aponta para o modelo, e apagá-lo
        // a sério deixava o registo do que saiu sem dizer o que era.
        $this->daCasa($id)->delete();

        return response()->json(['message' => __('Modelo eliminado.')]);
    }

    /* ─── A pré-visualização e o teste ────────────────────────────────── */

    /** As variáveis que este modelo usa, venham dos mapeamentos ou do texto. */
    private function variaveisDo(NotificationTemplate $m): array
    {
        $todas = array_keys($m->variable_mappings ?? []);

        foreach ([$m->sms_body, $m->email_subject, $m->email_body] as $texto) {
            if (blank($texto)) {
                continue;
            }

            preg_match_all('/\{\{\s*(\w+)\s*\}\}/u', $texto, $achados);
            $todas = array_merge($todas, $achados[1] ?? []);
        }

        return array_values(array_unique($todas));
    }

    public function preparar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'notifications.view');

        $m = $this->daCasa($id);
        $d = TenantNotificationSetting::getForTenant($this->empresaId());
        $exemplo = $this->exemplo($m->module);

        $variaveis = collect($this->variaveisDo($m))
            ->mapWithKeys(fn ($v) => [$v => $exemplo[$v] ?? ''])->all();

        return response()->json([
            'data' => $this->linha($m),
            'variaveis' => (object) $variaveis,
            'exemplo' => (object) $exemplo,
            // O remetente da casa já preenchido: é para lá que um teste de
            // e-mail vai em nove casos em dez.
            'email_sugerido' => $d->from_email,
            'previsao' => $this->previsao($m, $variaveis),
        ]);
    }

    public function previsualizar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'notifications.view');

        $dados = $request->validate([
            'variaveis' => ['present', 'array'],
        ]);

        return response()->json([
            'previsao' => $this->previsao($this->daCasa($id), $dados['variaveis']),
        ]);
    }

    /**
     * O texto com as variáveis já postas.
     *
     * Uma variável por preencher mostra-se como `[nome]` e não em branco: um
     * buraco no meio de uma frase não se vê, e um `[cliente]` vê-se.
     */
    private function previsao(NotificationTemplate $m, array $variaveis): array
    {
        $motor = new EnvioDeNotificacoes();
        $valores = collect($variaveis)
            ->mapWithKeys(fn ($v, $k) => [$k => ($v === '' || $v === null) ? "[{$k}]" : $v])->all();

        return [
            'assunto' => $motor->preencher((string) $m->email_subject, $valores),
            'corpo' => $motor->preencher((string) $m->email_body, $valores),
            'sms' => $motor->preencher((string) $m->sms_body, $valores),
        ];
    }

    /**
     * ENVIAR UM TESTE — pelo mesmo caminho do envio a sério.
     *
     * O ecrã antigo reimplementava os três canais por dentro, e o do SMS era um
     * `TODO`: escrevia no log e somava «SMS» aos canais enviados. Dizia «Teste
     * enviado com sucesso via SMS» com nada a ter saído.
     */
    public function testar(Request $request, int $id): JsonResponse
    {
        $this->podeEnviar($request);

        $dados = $request->validate([
            'canais' => ['required', 'array', 'min:1'],
            'canais.*' => [Rule::in(['email', 'sms', 'whatsapp'])],
            'email' => ['nullable', 'email'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'variaveis' => ['present', 'array'],
        ], [], ['email' => __('e-mail'), 'telefone' => __('telefone')]);

        $m = $this->daCasa($id);
        $motor = new EnvioDeNotificacoes();

        $enviados = [];
        $falhas = [];

        foreach ($dados['canais'] as $canal) {
            $destino = $canal === 'email' ? ($dados['email'] ?? null) : ($dados['telefone'] ?? null);

            if (blank($destino)) {
                $falhas[] = $canal === 'email'
                    ? __('E-mail: escreva o endereço de destino.')
                    : __(':canal: escreva o número de destino.', ['canal' => strtoupper($canal)]);

                continue;
            }

            if ($canal !== 'email') {
                $destino = PhoneHelper::normalizeAngolanPhone($destino);

                if (! PhoneHelper::isValidAngolanPhone($destino)) {
                    $falhas[] = __(':canal: o número não é válido.', ['canal' => strtoupper($canal)]);

                    continue;
                }
            }

            try {
                if ($motor->porCanal($m, $canal, $destino, $dados['variaveis'])) {
                    $enviados[] = $canal;
                } else {
                    // «Não saiu» é o que se diz quando não saiu: o canal está
                    // desligado nas definições, sem credenciais, ou sem texto.
                    $falhas[] = __(':canal: não saiu — verifique as definições do canal.', [
                        'canal' => strtoupper($canal),
                    ]);
                }
            } catch (\Throwable $e) {
                $falhas[] = strtoupper($canal).': '.$e->getMessage();
            }
        }

        if (! $enviados) {
            $this->recusa(implode(' · ', $falhas ?: [__('Nada foi enviado.')]));
        }

        return response()->json([
            'message' => __('Teste enviado por :canais.', ['canais' => implode(', ', $enviados)]),
            'enviados' => $enviados,
            'falhas' => $falhas,
        ]);
    }

    /* ─── Os dados de exemplo ─────────────────────────────────────────── */

    /**
     * Valores realistas por módulo, para a pré-visualização e o teste.
     *
     * Um modelo revê-se com o texto preenchido: «Olá {{cliente}}» não diz nada
     * sobre o que vai chegar ao telemóvel de alguém.
     */
    private function exemplo(string $modulo): array
    {
        return [
            'events' => [
                'event' => 'Conferência de Tecnologia 2026',
                'date' => now()->addDays(7)->format('d/m/Y'),
                'end_date' => now()->addDays(7)->format('d/m/Y'),
                'local' => 'Hotel Épic Sana, Luanda',
                'cliente' => 'João Silva',
                'responsavel' => 'Maria Santos',
                'tipo' => 'Conferência',
                'participantes' => '150',
                'valor' => '1.500.000,00 Kz',
                'status' => 'Confirmado',
                'fase' => 'Preparação',
            ],
            'hr' => [
                'funcionario' => 'Carlos Mendes',
                'cargo' => 'Desenvolvedor Sénior',
                'departamento' => 'Tecnologia da Informação',
                'data_admissao' => now()->format('d/m/Y'),
                'salario' => '350.000,00 Kz',
                'email' => 'carlos.mendes@empresa.vip',
                'telefone' => '+244 939 729 902',
                'licenca_inicio' => now()->addDays(14)->format('d/m/Y'),
                'licenca_fim' => now()->addDays(28)->format('d/m/Y'),
                'tipo_licenca' => 'Férias Anuais',
            ],
            'calendar' => [
                'titulo' => 'Reunião de Planeamento Q1',
                'data_inicio' => now()->addDays(3)->format('d/m/Y H:i'),
                'data_fim' => now()->addDays(3)->addHours(2)->format('d/m/Y H:i'),
                'descricao' => 'Revisão dos objectivos trimestrais',
                'local' => 'Sala de Reuniões 3A',
                'organizador' => 'Ana Ferreira',
            ],
            'finance' => [
                'documento' => 'FT 2026/000123',
                'cliente' => 'Empresa ABC, Lda.',
                'valor' => '2.450.000,00 Kz',
                'data_emissao' => now()->format('d/m/Y'),
                'data_vencimento' => now()->addDays(30)->format('d/m/Y'),
                'status' => 'Pendente',
                'descricao' => 'Serviços de consultoria TI',
                'metodo_pagamento' => 'Transferência Bancária',
            ],
            'crm' => [
                'cliente' => 'Pedro Neto',
                'empresa' => 'TechAngola, SA',
                'email' => 'pedro.neto@techangola.vip',
                'telefone' => '+244 942 705 533',
                'responsavel' => 'Sofia Rodrigues',
                'status' => 'Em Negociação',
                'oportunidade' => '5.000.000,00 Kz',
                'proxima_acao' => 'Enviar proposta comercial',
            ],
            'projects' => [
                'projeto' => 'Implementação ERP v3.0',
                'cliente' => 'Grupo Sonangol',
                'gerente' => 'António Costa',
                'data_inicio' => now()->format('d/m/Y'),
                'data_fim' => now()->addMonths(6)->format('d/m/Y'),
                'orcamento' => '15.000.000,00 Kz',
                'status' => 'Em Andamento',
                'progresso' => '35%',
            ],
            'tasks' => [
                'tarefa' => 'Configurar módulo de notificações',
                'descricao' => 'Implementar envio automático de e-mails e SMS',
                'responsavel' => 'Ricardo Almeida',
                'data_vencimento' => now()->addDays(5)->format('d/m/Y'),
                'prioridade' => 'Alta',
                'status' => 'Em Progresso',
                'projeto' => 'Implementação ERP v3.0',
            ],
        ][$modulo] ?? [];
    }
}
