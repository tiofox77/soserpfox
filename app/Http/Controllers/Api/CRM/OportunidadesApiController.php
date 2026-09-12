<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CRM\Activity;
use App\Models\CRM\Opportunity;
use App\Models\CRM\Stage;
use App\Services\CRM\FacturarOportunidade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS OPORTUNIDADES — os negócios com nome, valor e etapa.
 *
 * A lista é o registo; o FUNIL é o quadro da parede, e vive no mesmo ecrã, num
 * separador ao lado. Ganhar e perder ficam aqui com motivo e data — é daqui que
 * saem a taxa de conversão e a resposta a «porque é que perdemos».
 *
 * O NEGÓCIO GANHO VIRA DOCUMENTO SEM SAIR DAQUI. Sem isso o CRM dizia «vá à
 * Facturação» e o valor era escrito duas vezes: uma no funil, outra no
 * documento. Duas verdades sobre o mesmo negócio divergem à primeira correcção.
 */
class OportunidadesApiController extends Controller
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
        $this->exigir($request, 'crm.opportunities.view');

        return response()->json([
            'etapas' => Stage::doTenant(activeTenantId())->map(fn (Stage $e) => [
                'valor' => (string) $e->id,
                'rotulo' => $e->name,
                'probabilidade' => (int) $e->probability,
            ])->values(),
            'clientes' => Client::where('tenant_id', activeTenantId())
                ->where('is_active', true)->orderBy('name')->limit(300)->get(['id', 'name'])
                ->map(fn ($c) => ['valor' => (string) $c->id, 'rotulo' => $c->name])->values(),
            'estados' => [
                ['valor' => 'open', 'rotulo' => __('Em aberto')],
                ['valor' => 'won', 'rotulo' => __('Ganhas')],
                ['valor' => 'lost', 'rotulo' => __('Perdidas')],
            ],
            'tipos_de_actividade' => collect(Activity::TIPOS)
                ->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'permissoes' => [
                'pode_gerir' => (bool) $request->user()?->can('crm.opportunities.manage'),
                'pode_facturar' => (bool) $request->user()?->can('invoicing.sales.invoices.create'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'crm.opportunities.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(['open', 'won', 'lost'])],
            'etapa' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $lista = Opportunity::forTenant()
            /*
             * A FACTURA VEM JUNTO: a lista mostra, em cada negócio ganho, o
             * documento que dele nasceu — ou o botão para o criar. Sem isto,
             * «ganho» e «cobrado» eram a mesma palavra e nenhum número dizia
             * quantos negócios fechados ainda não tinham sido facturados.
             */
            ->with(['stage:id,name,probability', 'client:id,name', 'assignee:id,name',
                'factura:id,invoice_number,invoice_date,status,total'])
            ->withCount('activities')
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->when($filtros['etapa'] ?? null, fn ($q, $e) => $q->where('stage_id', $e))
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->where(fn ($w) => $w->where('title', 'like', $t)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $t)));
            })
            ->latest()
            ->paginate($filtros['por_pagina'] ?? 20);

        $abertas = fn () => Opportunity::forTenant()->where('status', 'open');

        return response()->json([
            'data' => collect($lista->items())->map(fn (Opportunity $o) => $this->linha($o))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'abertas' => $abertas()->count(),
                'valor' => (float) $abertas()->sum('amount'),
                'ponderado' => (float) $abertas()->get()->sum(fn ($o) => $o->weighted_amount),
                'ganhas' => Opportunity::forTenant()->where('status', 'won')->count(),
                'por_facturar' => Opportunity::forTenant()->where('status', 'won')
                    ->whereNull('sales_invoice_id')->count(),
            ],
        ]);
    }

    private function linha(Opportunity $o): array
    {
        return [
            'id' => $o->id,
            'titulo' => $o->title,
            'client_id' => $o->client_id,
            'cliente' => $o->client?->name,
            'stage_id' => $o->stage_id,
            'etapa' => $o->stage?->name,
            'probabilidade' => (int) $o->probability,
            'valor' => (float) $o->amount,
            'ponderado' => (float) $o->weighted_amount,
            'fecho_previsto' => $o->expected_close_date?->format('Y-m-d'),
            'notas' => $o->notes,
            'estado' => $o->status,
            'estado_rotulo' => match ($o->status) {
                'open' => __('Em aberto'), 'won' => __('Ganha'), 'lost' => __('Perdida'),
                default => $o->status,
            },
            'motivo_da_perda' => $o->lost_reason,
            'fechada_em' => $o->closed_at?->format('Y-m-d'),
            'responsavel' => $o->assignee?->name,
            'actividades' => (int) ($o->activities_count ?? 0),
            'factura' => $o->factura ? [
                'id' => $o->factura->id,
                'numero' => $o->factura->invoice_number,
                'dia' => $o->factura->invoice_date?->format('Y-m-d'),
                'estado' => $o->factura->status,
                'total' => (float) $o->factura->total,
            ] : null,
        ];
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'crm.opportunities.manage');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'client_id' => ['nullable', Rule::exists('invoicing_clients', 'id')->where('tenant_id', $tenantId)],
            'stage_id' => ['required', Rule::exists('crm_stages', 'id')->where('tenant_id', $tenantId)],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['title' => __('título'), 'stage_id' => __('etapa')]);

        $etapa = Stage::forTenant()->findOrFail($dados['stage_id']);

        $valores = [
            'title' => trim($dados['title']),
            'client_id' => $dados['client_id'] ?? null,
            'stage_id' => $etapa->id,
            'amount' => (float) ($dados['amount'] ?? 0),
            'expected_close_date' => $dados['expected_close_date'] ?? null,
            'notes' => trim($dados['notes'] ?? '') ?: null,
        ];

        if ($id) {
            $o = Opportunity::forTenant()->findOrFail($id);

            // Mudar de etapa pelo formulário acompanha a probabilidade da etapa
            // nova — o mesmo que o funil faz ao mover o cartão.
            if ((int) $o->stage_id !== (int) $etapa->id) {
                $valores['probability'] = (int) $etapa->probability;
            }

            $o->update($valores);
        } else {
            $o = Opportunity::create($valores + [
                'tenant_id' => $tenantId,
                'probability' => (int) $etapa->probability,
                'status' => 'open',
                'assigned_to' => $request->user()?->id,
                'created_by' => $request->user()?->id,
            ]);
        }

        return response()->json([
            'message' => $id ? __('Oportunidade actualizada.') : __('No funil.'),
            'data' => $this->linha($o->fresh(['stage', 'client', 'assignee', 'factura'])),
        ], $id ? 200 : 201);
    }

    public function ganhar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'crm.opportunities.manage');

        $o = Opportunity::forTenant()->where('status', 'open')->findOrFail($id);

        $o->update(['status' => 'won', 'probability' => 100, 'closed_at' => now()]);

        return response()->json([
            'message' => __('Ganho! Já pode gerar a factura aqui mesmo — nasce em rascunho, para conferir.'),
            'data' => $this->linha($o->fresh(['stage', 'client', 'assignee', 'factura'])),
        ]);
    }

    public function perder(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'crm.opportunities.manage');

        $dados = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:255'],
        ], [], ['motivo' => __('motivo')]);

        $o = Opportunity::forTenant()->where('status', 'open')->findOrFail($id);

        $o->update([
            'status' => 'lost',
            'probability' => 0,
            'closed_at' => now(),
            'lost_reason' => trim($dados['motivo']),
        ]);

        return response()->json([
            'message' => __('Registado. Os motivos somados dizem onde se perde.'),
            'data' => $this->linha($o->fresh(['stage', 'client', 'assignee', 'factura'])),
        ]);
    }

    public function reabrir(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'crm.opportunities.manage');

        $o = Opportunity::forTenant()->whereIn('status', ['won', 'lost'])->findOrFail($id);

        /*
         * UM NEGÓCIO JÁ FACTURADO NÃO SE REABRE.
         *
         * Havia uma factura emitida a apontar para ele: reabri-lo punha-o de
         * volta no funil a contar como dinheiro por fechar, ao mesmo tempo que
         * o documento já estava na rua. O mesmo negócio contado duas vezes.
         */
        if ($o->sales_invoice_id) {
            $this->recusa(__('Esta oportunidade já tem factura. Anule a factura primeiro.'));
        }

        $o->update([
            'status' => 'open',
            'closed_at' => null,
            'lost_reason' => null,
            'probability' => (int) ($o->stage?->probability ?? 50),
        ]);

        return response()->json([
            'message' => __('De volta ao funil.'),
            'data' => $this->linha($o->fresh(['stage', 'client', 'assignee', 'factura'])),
        ]);
    }

    /** O negócio ganho vira documento, e a oportunidade fica a saber qual. */
    public function facturar(Request $request, int $id, FacturarOportunidade $servico): JsonResponse
    {
        $this->exigir($request, 'crm.opportunities.manage');
        $this->exigir($request, 'invoicing.sales.invoices.create');

        $o = Opportunity::forTenant()->findOrFail($id);

        try {
            $factura = $servico->facturar($o, activeTenantId());
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json([
            'message' => __('Factura :numero criada em rascunho.', ['numero' => $factura->invoice_number]),
            'data' => $this->linha($o->fresh(['stage', 'client', 'assignee', 'factura'])),
        ]);
    }

    public function actividade(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'crm.opportunities.manage');

        $dados = $request->validate([
            'type' => ['required', Rule::in(array_keys(Activity::TIPOS))],
            'subject' => ['required', 'string', 'min:2', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['subject' => __('assunto'), 'type' => __('tipo')]);

        $o = Opportunity::forTenant()->findOrFail($id);

        Activity::create([
            'tenant_id' => activeTenantId(),
            'type' => $dados['type'],
            'subject' => trim($dados['subject']),
            'notes' => trim($dados['notes'] ?? '') ?: null,
            'opportunity_id' => $o->id,
            'done' => true,
            'assigned_to' => $request->user()?->id,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['message' => __('Registado no histórico.')], 201);
    }

    public function historico(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'crm.opportunities.view');

        $o = Opportunity::forTenant()->findOrFail($id);

        return response()->json([
            'data' => Activity::forTenant()->where('opportunity_id', $o->id)
                ->orderBy('created_at')->get()
                ->map(fn (Activity $a) => [
                    'id' => $a->id,
                    'tipo' => $a->type,
                    'tipo_rotulo' => __(Activity::TIPOS[$a->type] ?? $a->type),
                    'assunto' => $a->subject,
                    'notas' => $a->notes,
                    'quando' => $a->created_at?->format('Y-m-d H:i'),
                ])->values(),
        ]);
    }

    /* ─── O funil ──────────────────────────────────────────────────────── */

    /**
     * O QUADRO DA PAREDE — as abertas em colunas, uma por etapa.
     *
     * Cada coluna soma o que lá está, e soma DUAS VEZES: o valor e o ponderado.
     * O segundo é o honesto — um negócio na primeira etapa vale 10% do que diz.
     */
    public function funil(Request $request): JsonResponse
    {
        $this->exigir($request, 'crm.opportunities.view');

        $tenantId = activeTenantId();
        $etapas = Stage::doTenant($tenantId);

        $abertas = Opportunity::forTenant()
            ->where('status', 'open')
            ->with(['client:id,name', 'assignee:id,name', 'stage:id,name,probability'])
            ->orderByDesc('amount')
            ->get()
            ->groupBy('stage_id');

        return response()->json([
            'colunas' => $etapas->map(function (Stage $etapa) use ($abertas) {
                $cartoes = $abertas->get($etapa->id, collect());

                return [
                    'id' => $etapa->id,
                    'nome' => $etapa->name,
                    'probabilidade' => (int) $etapa->probability,
                    'total' => (float) $cartoes->sum('amount'),
                    'ponderado' => (float) $cartoes->sum(fn ($o) => $o->weighted_amount),
                    'cartoes' => $cartoes->map(fn (Opportunity $o) => [
                        'id' => $o->id,
                        'titulo' => $o->title,
                        'cliente' => $o->client?->name,
                        'responsavel' => $o->assignee?->name,
                        'valor' => (float) $o->amount,
                        'ponderado' => (float) $o->weighted_amount,
                        'fecho_previsto' => $o->expected_close_date?->format('Y-m-d'),
                    ])->values(),
                ];
            })->values(),
            'permissoes' => ['pode_gerir' => (bool) $request->user()?->can('crm.opportunities.manage')],
        ]);
    }

    /** Move a oportunidade uma etapa para o lado. */
    public function mover(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'crm.opportunities.manage');

        $dados = $request->validate(['direccao' => ['required', Rule::in(['frente', 'tras'])]]);

        $tenantId = activeTenantId();
        $o = Opportunity::forTenant()->where('status', 'open')->findOrFail($id);

        $etapas = Stage::doTenant($tenantId)->values();
        $indice = $etapas->search(fn ($e) => (int) $e->id === (int) $o->stage_id);

        if ($indice === false) {
            $this->recusa(__('Esta oportunidade não está em nenhuma etapa do funil.'));
        }

        $destino = $etapas->get($dados['direccao'] === 'frente' ? $indice + 1 : $indice - 1);

        if (! $destino) {
            $this->recusa(__('Já está no fim do funil.'));
        }

        // A PROBABILIDADE ACOMPANHA A ETAPA: é ela que faz o valor ponderado do
        // funil dizer a verdade sem ninguém estimar à mão.
        $o->update(['stage_id' => $destino->id, 'probability' => (int) $destino->probability]);

        return response()->json([
            'message' => __('Em :etapa.', ['etapa' => $destino->name]),
        ]);
    }
}
