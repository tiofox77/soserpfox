<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\CRM\Activity;
use App\Models\CRM\Lead;
use App\Models\CRM\MetaContact;
use App\Models\CRM\MetaIntegration;
use App\Services\CRM\ConversaoDeLead;
use App\Services\CRM\EnviarPeloMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OS LEADS — quem ainda não é cliente mas pode vir a ser.
 *
 * O ecrã é uma FILA DE TRABALHO e não um arquivo: criar é uma linha (nome +
 * telefone + origem, Enter), e cada cartão tem os três destinos possíveis à
 * vista — avançar no estado, converter em cliente, ou perder com motivo.
 *
 * VER NÃO ERA MEXER, MAS ERA. Existem `crm.leads.view` e `crm.leads.manage`, e
 * o componente Livewire não perguntava pela segunda a ninguém: quem abrisse a
 * página convertia leads em clientes, dava-os por perdidos e respondia-lhes por
 * WhatsApp em nome da empresa.
 */
class LeadsApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'crm.leads.view');

        $integracao = MetaIntegration::forTenant()->first();

        return response()->json([
            'origens' => collect(Lead::ORIGENS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'estados' => collect(Lead::ESTADOS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'tipos_de_actividade' => collect(Activity::TIPOS)
                ->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'whatsapp_ligado' => (bool) ($integracao && $integracao->whatsappActivo()),
            'permissoes' => ['pode_gerir' => (bool) $request->user()?->can('crm.leads.manage')],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'crm.leads.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'max:20'],
            'origem' => ['nullable', Rule::in(array_keys(Lead::ORIGENS))],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $estado = $filtros['estado'] ?? 'abertos';

        $lista = Lead::forTenant()
            ->with(['assignee:id,name', 'client:id,name'])
            ->withCount('activities')
            ->when($estado === 'abertos', fn ($q) => $q->whereIn('status', Lead::ABERTOS))
            ->when(array_key_exists($estado, Lead::ESTADOS), fn ($q) => $q->where('status', $estado))
            ->when($filtros['origem'] ?? null, fn ($q, $o) => $q->where('source', $o))
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->where(fn ($w) => $w->where('name', 'like', $t)
                    ->orWhere('company', 'like', $t)
                    ->orWhere('phone', 'like', $t)
                    ->orWhere('email', 'like', $t));
            })
            ->latest()
            ->paginate($filtros['por_pagina'] ?? 24);

        return response()->json([
            'data' => collect($lista->items())->map(fn (Lead $l) => $this->linha($l))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'abertos' => Lead::forTenant()->whereIn('status', Lead::ABERTOS)->count(),
                'novos' => Lead::forTenant()->where('status', 'novo')->count(),
                'convertidos' => Lead::forTenant()->where('status', 'convertido')->count(),
                'perdidos' => Lead::forTenant()->where('status', 'perdido')->count(),
            ],
        ]);
    }

    private function linha(Lead $l): array
    {
        return [
            'id' => $l->id,
            'nome' => $l->name,
            'empresa' => $l->company,
            'telefone' => $l->phone,
            'email' => $l->email,
            'origem' => $l->source,
            'origem_rotulo' => __(Lead::ORIGENS[$l->source] ?? $l->source),
            'estado' => $l->status,
            'estado_rotulo' => __(Lead::ESTADOS[$l->status] ?? $l->status),
            'notas' => $l->notes,
            'motivo_da_perda' => $l->lost_reason,
            'responsavel' => $l->assignee?->name,
            'cliente' => $l->client?->name,
            'client_id' => $l->converted_client_id,
            'actividades' => (int) ($l->activities_count ?? 0),
            'criado_em' => $l->created_at?->format('Y-m-d H:i'),
            // O passo seguinte do caminho, ou nada quando já não há.
            'seguinte' => ['novo' => 'contactado', 'contactado' => 'qualificado'][$l->status] ?? null,
        ];
    }

    /**
     * O LEAD NOVO É UMA LINHA.
     *
     * Nome, telefone e origem. Um formulário de vinte campos para registar uma
     * chamada perdida é a razão por que ninguém regista chamadas perdidas.
     */
    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'crm.leads.manage');

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'source' => ['required', Rule::in(array_keys(Lead::ORIGENS))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['name' => __('nome'), 'phone' => __('telefone'), 'source' => __('origem')]);

        $valores = [
            'name' => trim($dados['name']),
            'phone' => trim($dados['phone'] ?? '') ?: null,
            'email' => trim($dados['email'] ?? '') ?: null,
            'company' => trim($dados['company'] ?? '') ?: null,
            'source' => $dados['source'],
            'notes' => trim($dados['notes'] ?? '') ?: null,
        ];

        if ($id) {
            $lead = Lead::forTenant()->findOrFail($id);
            $lead->update($valores);
        } else {
            $lead = Lead::create($valores + [
                'tenant_id' => activeTenantId(),
                'status' => 'novo',
                'assigned_to' => $request->user()?->id,
                'created_by' => $request->user()?->id,
            ]);
        }

        return response()->json([
            'message' => $id ? __('Lead actualizado.') : __('Na fila. O próximo passo é ligar-lhe.'),
            'data' => $this->linha($lead->fresh(['assignee', 'client'])),
        ], $id ? 200 : 201);
    }

    /** Avança um passo no caminho: novo → contactado → qualificado. */
    public function avancar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'crm.leads.manage');

        $lead = Lead::forTenant()->findOrFail($id);

        $seguinte = ['novo' => 'contactado', 'contactado' => 'qualificado'][$lead->status] ?? null;

        if (! $seguinte) {
            $this->recusa(__('Este lead já não avança daqui.'));
        }

        $lead->update(['status' => $seguinte]);

        return response()->json([
            'message' => __('Agora está :estado.', ['estado' => __(Lead::ESTADOS[$seguinte])]),
            'data' => $this->linha($lead->fresh(['assignee', 'client'])),
        ]);
    }

    /**
     * CONVERTER: o lead vira cliente da facturação, com a oportunidade aberta.
     *
     * É a porta única — `ConversaoDeLead` — e não uma cópia de campos à mão: o
     * cliente nasce com o que o lead tem, e a oportunidade fica ligada aos dois.
     */
    public function converter(Request $request, int $id, ConversaoDeLead $servico): JsonResponse
    {
        $this->exigir($request, 'crm.leads.manage');

        $dados = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ], [], ['title' => __('título')]);

        $lead = Lead::forTenant()->findOrFail($id);

        if ($lead->status === 'convertido') {
            $this->recusa(__('Este lead já é cliente.'));
        }

        try {
            $cliente = $servico->converter($lead, activeTenantId(), $request->user()?->id, [
                'title' => trim($dados['title']),
                'amount' => (float) ($dados['amount'] ?? 0),
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json([
            'message' => __('Cliente criado: :nome. A oportunidade está no funil.', ['nome' => $cliente->name]),
            'data' => $this->linha($lead->fresh(['assignee', 'client'])),
        ]);
    }

    /**
     * PERDER PEDE MOTIVO.
     *
     * «Perdido sem razão» não ensina nada: os motivos somados são a única coisa
     * que diz onde é que a casa perde negócios.
     */
    public function perder(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'crm.leads.manage');

        $dados = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:255'],
        ], [], ['motivo' => __('motivo')]);

        Lead::forTenant()->findOrFail($id)->update([
            'status' => 'perdido',
            'lost_reason' => trim($dados['motivo']),
        ]);

        return response()->json(['message' => __('Registado. Os motivos somados dizem onde se perde.')]);
    }

    public function reabrir(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'crm.leads.manage');

        Lead::forTenant()->where('status', 'perdido')->findOrFail($id)
            ->update(['status' => 'contactado', 'lost_reason' => null]);

        return response()->json(['message' => __('Lead reaberto.')]);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'crm.leads.manage');

        $lead = Lead::forTenant()->findOrFail($id);

        // Um lead convertido é o princípio do historial de um cliente: apagá-lo
        // deixava a ficha do cliente sem dizer de onde veio.
        if ($lead->status === 'convertido') {
            $this->recusa(__('Este lead já é cliente — fica no historial.'));
        }

        $lead->delete();

        return response()->json(['message' => __('Lead removido.')]);
    }

    /* ─── A conversa ───────────────────────────────────────────────────── */

    /** O lead, as suas actividades por ordem, e se dá para responder por WhatsApp. */
    public function conversa(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'crm.leads.view');

        $tenantId = activeTenantId();
        $lead = Lead::forTenant()->findOrFail($id);

        $numero = MetaContact::forTenant()->where('channel', 'whatsapp')
            ->where('lead_id', $lead->id)->value('external_id') ?: $lead->phone;

        $integracao = MetaIntegration::forTenant()->first();

        return response()->json([
            'data' => $this->linha($lead->fresh(['assignee', 'client'])),
            'numero' => $numero,
            'pode_whatsapp' => (bool) ($integracao && $integracao->whatsappActivo() && $numero)
                && (bool) $request->user()?->can('crm.leads.manage'),
            'actividades' => Activity::forTenant()->where('lead_id', $lead->id)
                ->orderBy('created_at')->get()
                ->map(fn (Activity $a) => [
                    'id' => $a->id,
                    'tipo' => $a->type,
                    'tipo_rotulo' => __(Activity::TIPOS[$a->type] ?? $a->type),
                    'assunto' => $a->subject,
                    'notas' => $a->notes,
                    'sentido' => $a->direction,
                    'feita' => (bool) $a->done,
                    'quando' => $a->created_at?->format('Y-m-d H:i'),
                ])->values(),
        ]);
    }

    /**
     * A ACTIVIDADE RÁPIDA — e o estado acompanha sozinho.
     *
     * Falar com um lead novo é tê-lo contactado: sem isto, o quadro mentia por
     * esquecimento, e alguém voltava a ligar à mesma pessoa na segunda-feira.
     */
    public function actividade(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'crm.leads.manage');

        $dados = $request->validate([
            'type' => ['required', Rule::in(array_keys(Activity::TIPOS))],
            'subject' => ['required', 'string', 'min:2', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['subject' => __('assunto'), 'type' => __('tipo')]);

        $lead = Lead::forTenant()->findOrFail($id);

        Activity::create([
            'tenant_id' => activeTenantId(),
            'type' => $dados['type'],
            'subject' => trim($dados['subject']),
            'notes' => trim($dados['notes'] ?? '') ?: null,
            'lead_id' => $lead->id,
            'done' => true,
            'assigned_to' => $request->user()?->id,
            'created_by' => $request->user()?->id,
        ]);

        if ($lead->status === 'novo') {
            $lead->update(['status' => 'contactado']);
        }

        return response()->json(['message' => __('Registado no histórico.')], 201);
    }

    /** Responder por WhatsApp, sem sair da fila. */
    public function responder(Request $request, int $id, EnviarPeloMeta $servico): JsonResponse
    {
        $this->exigir($request, 'crm.leads.manage');

        $dados = $request->validate([
            'texto' => ['required', 'string', 'min:1', 'max:4000'],
        ], [], ['texto' => __('mensagem')]);

        $tenantId = activeTenantId();
        $lead = Lead::forTenant()->findOrFail($id);
        $integracao = MetaIntegration::forTenant()->first();

        $numero = MetaContact::forTenant()->where('channel', 'whatsapp')
            ->where('lead_id', $lead->id)->value('external_id') ?: $lead->phone;

        if (! $integracao || ! $numero) {
            $this->recusa(__('Este lead não tem WhatsApp para onde responder.'));
        }

        $resultado = $servico->whatsappTexto($integracao, $numero, trim($dados['texto']));

        if (! ($resultado['ok'] ?? false)) {
            $this->recusa($resultado['erro'] ?? __('Não foi possível enviar.'));
        }

        Activity::create([
            'tenant_id' => $tenantId,
            'lead_id' => $lead->id,
            'type' => 'whatsapp',
            'direction' => 'out',
            'subject' => __('Resposta WhatsApp'),
            'notes' => trim($dados['texto']),
            'done' => true,
            'assigned_to' => $request->user()?->id,
            'created_by' => $request->user()?->id,
        ]);

        if ($lead->status === 'novo') {
            $lead->update(['status' => 'contactado']);
        }

        return response()->json(['message' => __('Enviado por WhatsApp.')]);
    }
}
