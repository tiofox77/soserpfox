<?php

namespace App\Http\Controllers\Api\Contabilidade;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\DocumentType;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Move;
use App\Models\Accounting\Period;
use App\Services\Accounting\Lancamentos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * OS LANÇAMENTOS CONTABILÍSTICOS.
 *
 * As regras vivem no `Lancamentos` — e metade delas não vivia em lado nenhum.
 * O que o ecrã antigo verificava era o equilíbrio (débito igual a crédito) e o
 * período fechado AO CRIAR. O que passava:
 *
 *  · um lançamento todo a zeros (zero é igual a zero);
 *  · uma linha com débito E crédito ao mesmo tempo;
 *  · um lançamento numa conta de AGREGAÇÃO, a contar o valor duas vezes;
 *  · uma data fora do período a que o lançamento diz pertencer;
 *  · um rascunho de Janeiro confirmado em Março, num período já fechado;
 *  · e APAGAR um lançamento CONFIRMADO — reescrever a contabilidade sem rasto.
 *
 * E a REFERÊNCIA saía ao escolher o diário e só se incrementava ao gravar: dois
 * utilizadores a lançar ao mesmo tempo levavam a mesma.
 */
class LancamentosApiController extends Controller
{
    public function __construct(private Lancamentos $regras) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function tenantId(): int
    {
        return (int) activeTenantId();
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.moves.view');

        $tenantId = $this->tenantId();

        return response()->json([
            'diarios' => Journal::where('tenant_id', $tenantId)->where('active', true)
                ->orderBy('name')->get(['id', 'name', 'code', 'sequence_prefix'])
                ->map(fn ($d) => [
                    'valor' => (string) $d->id,
                    'rotulo' => $d->name,
                    'prefixo' => $d->sequence_prefix,
                ])->values(),

            // SÓ OS PERÍODOS ABERTOS: um fechado não recebe nada, e oferecê-lo
            // era deixar alguém preencher o formulário todo para levar com a
            // recusa no fim.
            'periodos' => Period::where('tenant_id', $tenantId)->where('state', 'open')
                ->orderByDesc('date_start')->get(['id', 'name', 'code', 'date_start', 'date_end'])
                ->map(fn ($p) => [
                    'valor' => (string) $p->id,
                    'rotulo' => $p->name ?: $p->code,
                    'de' => $p->date_start?->format('Y-m-d'),
                    'ate' => $p->date_end?->format('Y-m-d'),
                ])->values(),

            /*
             * AS CONTAS QUE RECEBEM MOVIMENTO.
             *
             * Nem bloqueadas nem de AGREGAÇÃO: uma conta `is_view` existe para
             * somar as filhas, e dar-lhe movimento próprio conta o valor duas
             * vezes no balanço. A lista antiga só excluía as bloqueadas.
             */
            'contas' => Account::where('tenant_id', $tenantId)
                ->where('blocked', false)->where('is_view', false)
                ->orderBy('code')->get(['id', 'code', 'name', 'type', 'nature'])
                ->map(fn ($c) => [
                    'valor' => (string) $c->id,
                    'rotulo' => $c->code.' · '.$c->name,
                    'natureza' => $c->nature,
                ])->values(),

            // O tipo de documento não tem `name`: o que o identifica é o código
            // e a DESCRIÇÃO.
            'tipos_de_documento' => DocumentType::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('code')->get(['id', 'code', 'description'])
                ->map(fn ($t) => [
                    'valor' => (string) $t->id,
                    'rotulo' => trim($t->code.' · '.(string) $t->description, ' ·'),
                ])->values(),

            'estados' => [
                ['valor' => 'draft', 'rotulo' => __('Rascunho')],
                ['valor' => 'posted', 'rotulo' => __('Confirmado')],
            ],

            // O PERÍODO DE HOJE, já escolhido: é o que se usa em nove casos em dez.
            'periodo_de_hoje' => Period::where('tenant_id', $tenantId)->where('state', 'open')
                ->whereDate('date_start', '<=', now())->whereDate('date_end', '>=', now())
                ->value('id'),

            'permissoes' => [
                'gerir' => (bool) $request->user()?->can('accounting.moves.manage'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.moves.view');

        $tenantId = $this->tenantId();

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(['todos', 'draft', 'posted'])],
            'diario' => ['nullable', 'integer'],
            'periodo' => ['nullable', 'integer'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'por_pagina' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $base = fn () => Move::where('tenant_id', $tenantId)
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->where(fn ($w) => $w->where('ref', 'like', $t)->orWhere('narration', 'like', $t));
            })
            ->when(! empty($filtros['estado']) && $filtros['estado'] !== 'todos',
                fn ($q) => $q->where('state', $filtros['estado']))
            ->when(! empty($filtros['diario']), fn ($q) => $q->where('journal_id', $filtros['diario']))
            ->when(! empty($filtros['periodo']), fn ($q) => $q->where('period_id', $filtros['periodo']))
            ->when(! empty($filtros['de']), fn ($q) => $q->where('date', '>=', $filtros['de']))
            ->when(! empty($filtros['ate']), fn ($q) => $q->where('date', '<=', $filtros['ate']));

        $lista = $base()
            ->with(['journal:id,name', 'period:id,name,code', 'creator:id,name'])
            ->withCount('lines')
            ->latest('date')->latest('id')
            ->paginate($filtros['por_pagina'] ?? 20);

        return response()->json([
            'data' => collect($lista->items())->map(fn (Move $m) => $this->linha($m))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'total' => $base()->count(),
                'rascunhos' => $base()->where('state', 'draft')->count(),
                'confirmados' => $base()->where('state', 'posted')->count(),
                'do_mes' => $base()->whereBetween('date', [
                    now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d'),
                ])->count(),
                /*
                 * OS RASCUNHOS EM PERÍODO FECHADO.
                 *
                 * São os que já não se podem confirmar: ficam ali para sempre a
                 * parecer trabalho por acabar. Contá-los é a única forma de
                 * alguém dar por eles.
                 */
                'presos' => $base()->where('state', 'draft')
                    ->whereHas('period', fn ($q) => $q->where('state', '!=', 'open'))
                    ->count(),
            ],
        ]);
    }

    private function linha(Move $m): array
    {
        $periodoAberto = $m->period ? $m->period->state === 'open' : true;

        return [
            'id' => $m->id,
            'ref' => $m->ref,
            'dia' => $m->date?->format('Y-m-d'),
            'diario' => $m->journal?->name,
            'diario_id' => $m->journal_id,
            'periodo' => $m->period?->name ?: $m->period?->code,
            'periodo_id' => $m->period_id,
            'periodo_aberto' => $periodoAberto,
            'nota' => $m->narration,
            'estado' => $m->state,
            'estado_rotulo' => $m->state === 'posted' ? __('Confirmado') : __('Rascunho'),
            'debito' => round((float) $m->total_debit, 2),
            'credito' => round((float) $m->total_credit, 2),
            'linhas' => (int) ($m->lines_count ?? 0),
            'autor' => $m->creator?->name,
            'confirmado_em' => $m->posted_at?->format('Y-m-d H:i'),
            /*
             * O QUE SE PODE FAZER, decidido no servidor.
             *
             * Um confirmado não se apaga (rectifica-se por estorno) e um
             * rascunho num período fechado não se confirma. O ecrã não pode
             * adivinhar nem uma nem outra.
             */
            'pode_confirmar' => $m->state === 'draft' && $periodoAberto,
            'pode_apagar' => $m->state === 'draft',
            'pode_estornar' => $m->state === 'posted',
        ];
    }

    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.moves.view');

        $m = Move::with([
            'journal:id,name', 'period:id,name,code,state', 'documentType:id,code,description',
            'creator:id,name', 'poster:id,name',
            'lines.account:id,code,name,nature',
        ])->withCount('lines')->findOrFail($id);

        return response()->json([
            'data' => $this->linha($m) + [
                'tipo_de_documento_id' => $m->document_type_id,
                'tipo_de_documento' => $m->documentType
                    ? $m->documentType->code.' · '.$m->documentType->name : null,
                'confirmado_por' => $m->poster?->name,
                'linhas_do_lancamento' => $m->lines->map(fn ($l) => [
                    'id' => $l->id,
                    'conta_id' => $l->account_id,
                    'conta' => $l->account ? $l->account->code.' · '.$l->account->name : null,
                    'debito' => round((float) $l->debit, 2),
                    'credito' => round((float) $l->credit, 2),
                    'nota' => $l->narration,
                ])->values(),
            ],
        ]);
    }

    /** A referência seguinte de um diário, sob tranca. */
    public function referencia(Request $request, int $diario): JsonResponse
    {
        $this->exigir($request, 'accounting.moves.manage');

        return response()->json([
            'ref' => $this->regras->proximaReferencia(Journal::findOrFail($diario)),
        ]);
    }

    public function criar(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.moves.manage');

        $dados = $request->validate([
            'journal_id' => ['required', 'integer'],
            'period_id' => ['required', 'integer'],
            'document_type_id' => ['nullable', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            // A REFERÊNCIA PODE VIR VAZIA: nesse caso sai do diário, sob tranca.
            'ref' => ['nullable', 'string', 'max:50'],
            'narration' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['nullable', 'integer'],
            'lines.*.debit' => ['nullable', 'numeric'],
            'lines.*.credit' => ['nullable', 'numeric'],
            'lines.*.narration' => ['nullable', 'string', 'max:255'],
        ], [], [
            'journal_id' => __('diário'), 'period_id' => __('período'), 'date' => __('data'),
        ]);

        $move = $this->regras->criar(
            $dados,
            $dados['lines'],
            $this->tenantId(),
            (int) $request->user()?->id,
        );

        return response()->json([
            'message' => __('Lançamento :ref criado em rascunho.', ['ref' => $move->ref]),
            'data' => $this->linha($move->fresh(['journal', 'period', 'creator'])->loadCount('lines')),
        ], 201);
    }

    public function confirmar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.moves.manage');

        $move = $this->regras->confirmar(
            Move::with(['lines', 'period'])->findOrFail($id),
            (int) $request->user()?->id,
        );

        return response()->json([
            'message' => __('Lançamento :ref confirmado.', ['ref' => $move->ref]),
            'data' => $this->linha($move->load(['journal', 'period', 'creator'])->loadCount('lines')),
        ]);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.moves.manage');

        $move = Move::findOrFail($id);
        $ref = $move->ref;

        $this->regras->apagar($move);

        return response()->json(['message' => __('Rascunho :ref eliminado.', ['ref' => $ref])]);
    }

    /**
     * ESTORNAR — a única forma de desfazer um lançamento confirmado.
     *
     * Fica o original e fica o estorno: é essa a diferença entre corrigir e
     * apagar. A razão da conta mostra o que se lançou e o que se desfez.
     */
    public function estornar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.moves.manage');

        $dados = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'period_id' => ['nullable', 'integer'],
        ]);

        $original = Move::with(['lines', 'period'])->findOrFail($id);

        $estorno = $this->regras->estornar(
            $original,
            (int) $request->user()?->id,
            $dados['date'] ?? null,
            $dados['period_id'] ?? null,
        );

        return response()->json([
            'message' => __('Lançamento :ref estornado por :estorno.', [
                'ref' => $original->ref, 'estorno' => $estorno->ref,
            ]),
            'data' => $this->linha($estorno->load(['journal', 'period', 'creator'])->loadCount('lines')),
        ], 201);
    }
}
