<?php

namespace App\Http\Controllers\Api\Suporte;

use App\Http\Controllers\Controller;
use App\Models\Support\FeatureRequest;
use App\Models\Support\FeatureRequestVote;
use App\Models\Support\Ticket;
use App\Models\Support\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * O SUPORTE: os pedidos de ajuda e o quadro de melhorias.
 *
 * NÃO TEM PERMISSÃO NENHUMA, e é de propósito: pedir ajuda é auto-serviço. Um
 * pedido de suporte é DE QUEM O ABRIU (a lista filtra por `user_id`), e o
 * quadro de melhorias é de toda a empresa — é esse o sentido de haver votos.
 *
 * O que tem de estar fechado à chave é o escopo: um pedido ou uma sugestão de
 * outra empresa não se lê, não se vota e não se responde.
 */
class SuporteApiController extends Controller
{
    private const CATEGORIAS = ['technical', 'billing', 'feature', 'bug', 'other'];

    private const PRIORIDADES = ['low', 'medium', 'high', 'urgent'];

    /** No máximo cinco imagens de 2 MB — o mesmo tecto do ecrã antigo. */
    private const IMAGENS = 5;

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    public function opcoes(Request $request): JsonResponse
    {
        return response()->json([
            'categorias' => [
                ['valor' => 'technical', 'rotulo' => __('Técnico')],
                ['valor' => 'billing', 'rotulo' => __('Faturação')],
                ['valor' => 'feature', 'rotulo' => __('Funcionalidade')],
                ['valor' => 'bug', 'rotulo' => __('Erro no sistema')],
                ['valor' => 'other', 'rotulo' => __('Outro')],
            ],
            'prioridades' => [
                ['valor' => 'low', 'rotulo' => __('Baixa')],
                ['valor' => 'medium', 'rotulo' => __('Média')],
                ['valor' => 'high', 'rotulo' => __('Alta')],
                ['valor' => 'urgent', 'rotulo' => __('Urgente')],
            ],
            'maximo_de_imagens' => self::IMAGENS,
        ]);
    }

    /* ─── Os pedidos de ajuda ─────────────────────────────────────────── */

    public function tickets(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in(['todos', 'abertos', 'fechados'])],
            'procura' => ['nullable', 'string', 'max:120'],
        ]);

        $base = fn () => Ticket::where('tenant_id', activeTenantId())
            // UM PEDIDO É DE QUEM O ABRIU: a descrição de um problema leva lá
            // dentro números, nomes e capturas de ecrã da casa de quem o abriu.
            ->where('user_id', $request->user()?->id);

        $lista = $base()
            ->when(($filtros['estado'] ?? 'todos') === 'abertos',
                fn ($q) => $q->whereIn('status', ['open', 'in_progress', 'waiting_response']))
            ->when(($filtros['estado'] ?? 'todos') === 'fechados',
                fn ($q) => $q->whereIn('status', ['resolved', 'closed']))
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->where(fn ($w) => $w->where('subject', 'like', $t)
                    ->orWhere('ticket_number', 'like', $t)
                    ->orWhere('description', 'like', $t));
            })
            ->withCount('messages')
            ->latest()->get();

        return response()->json([
            'data' => $lista->map(fn (Ticket $t) => $this->linhaDoTicket($t))->values(),
            'resumo' => [
                'total' => $base()->count(),
                'abertos' => $base()->where('status', 'open')->count(),
                'em_curso' => $base()->where('status', 'in_progress')->count(),
                'a_responder' => $base()->where('status', 'waiting_response')->count(),
                'resolvidos' => $base()->whereIn('status', ['resolved', 'closed'])->count(),
            ],
        ]);
    }

    private function linhaDoTicket(Ticket $t): array
    {
        return [
            'id' => $t->id,
            'numero' => $t->ticket_number,
            'assunto' => $t->subject,
            'descricao' => $t->description,
            'prioridade' => $t->priority,
            'prioridade_rotulo' => match ($t->priority) {
                'low' => __('Baixa'), 'medium' => __('Média'),
                'high' => __('Alta'), 'urgent' => __('Urgente'), default => $t->priority,
            },
            'categoria' => $t->category,
            'categoria_rotulo' => match ($t->category) {
                'technical' => __('Técnico'), 'billing' => __('Faturação'),
                'feature' => __('Funcionalidade'), 'bug' => __('Erro no sistema'),
                'other' => __('Outro'), default => $t->category,
            },
            'estado' => $t->status,
            'estado_rotulo' => match ($t->status) {
                'open' => __('Aberto'), 'in_progress' => __('Em curso'),
                'waiting_response' => __('À espera de si'), 'resolved' => __('Resolvido'),
                'closed' => __('Fechado'), default => $t->status,
            },
            'imagens' => collect($t->images ?? [])->map(fn ($c) => Storage::url($c))->values(),
            'mensagens' => $t->messages_count ?? $t->messages()->count(),
            'resolvido_em' => $t->resolved_at?->format('Y-m-d H:i'),
            'quando' => $t->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function meuTicket(Request $request, int $id): Ticket
    {
        return Ticket::where('tenant_id', activeTenantId())
            ->where('user_id', $request->user()?->id)
            ->findOrFail($id);
    }

    /** O fio do pedido: a pergunta, e o que o suporte respondeu. */
    public function ticket(Request $request, int $id): JsonResponse
    {
        $t = $this->meuTicket($request, $id);

        return response()->json([
            'data' => $this->linhaDoTicket($t),
            'fio' => $t->messages()->with('user:id,name')->oldest()->get()
                ->map(fn (TicketMessage $m) => [
                    'id' => $m->id,
                    'texto' => $m->message,
                    'do_suporte' => (bool) $m->is_staff,
                    'autor' => $m->user?->name,
                    'quando' => $m->created_at?->format('Y-m-d H:i'),
                ])->values(),
        ]);
    }

    public function abrirTicket(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'subject' => ['required', 'string', 'min:5', 'max:180'],
            'description' => ['required', 'string', 'min:10'],
            'priority' => ['required', Rule::in(self::PRIORIDADES)],
            'category' => ['required', Rule::in(self::CATEGORIAS)],
            'images' => ['nullable', 'array', 'max:'.self::IMAGENS],
            'images.*' => ['image', 'max:2048'],
        ], [
            'images.max' => __('No máximo :n imagens.', ['n' => self::IMAGENS]),
            'images.*.max' => __('Cada imagem tem de caber em 2 MB.'),
        ], ['subject' => __('assunto'), 'description' => __('descrição')]);

        $tenantId = activeTenantId();
        $userId = $request->user()?->id;

        $ticket = DB::transaction(function () use ($dados, $tenantId, $userId, $request) {
            $numero = $this->proximoNumero($tenantId);

            $ticket = Ticket::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'ticket_number' => $numero,
                'subject' => $dados['subject'],
                'description' => $dados['description'],
                'images' => $this->guardarImagens($request, "tickets/{$tenantId}/{$userId}/{$numero}"),
                'priority' => $dados['priority'],
                'category' => $dados['category'],
                'status' => 'open',
            ]);

            return $ticket;
        });

        return response()->json([
            'message' => __('Pedido :numero aberto.', ['numero' => $ticket->ticket_number]),
            'data' => $this->linhaDoTicket($ticket),
        ], 201);
    }

    /**
     * O NÚMERO DO PEDIDO É POR EMPRESA, E CONTA-SE SOB TRANCA.
     *
     * Vinha de `Ticket::count() + 1` sobre a tabela INTEIRA: duas empresas
     * diferentes abriam o `TKT-000007` com um segundo de intervalo, e dois
     * pedidos abertos ao mesmo tempo na mesma empresa levavam o mesmo número
     * — porque entre contar e gravar não havia tranca nenhuma.
     */
    private function proximoNumero(int $tenantId): string
    {
        $ultimo = Ticket::where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('ticket_number');

        $seguinte = $ultimo ? ((int) preg_replace('/\D/', '', $ultimo)) + 1 : 1;

        return 'TKT-'.str_pad((string) $seguinte, 6, '0', STR_PAD_LEFT);
    }

    /** @return array<int, string> os caminhos gravados */
    private function guardarImagens(Request $request, string $pasta): array
    {
        $caminhos = [];

        foreach ($request->file('images', []) as $imagem) {
            $caminhos[] = $imagem->store($pasta, 'public');
        }

        return $caminhos;
    }

    /**
     * RESPONDER AO PRÓPRIO PEDIDO.
     *
     * O estado `waiting_response` — «à espera de si» — existia desde sempre e
     * não havia sítio nenhum onde responder. Um pedido parado à espera de uma
     * resposta que o ecrã não deixava escrever é um pedido morto.
     */
    public function responder(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ], [], ['message' => __('mensagem')]);

        $t = $this->meuTicket($request, $id);

        if (in_array($t->status, ['resolved', 'closed'], true)) {
            $this->recusa(__('Este pedido já está fechado. Abra um novo.'));
        }

        DB::transaction(function () use ($t, $dados, $request) {
            TicketMessage::create([
                'ticket_id' => $t->id,
                'user_id' => $request->user()?->id,
                'message' => $dados['message'],
                'is_staff' => false,
            ]);

            // Quem responde tira o pedido de «à espera de si» e devolve-o à
            // fila do suporte — senão ficava lá parado com a resposta dentro.
            if ($t->status === 'waiting_response') {
                $t->update(['status' => 'in_progress']);
            }
        });

        return response()->json(['message' => __('Resposta enviada.')]);
    }

    /** Fechar o próprio pedido: já não preciso de ajuda. */
    public function fecharTicket(Request $request, int $id): JsonResponse
    {
        $t = $this->meuTicket($request, $id);

        if (in_array($t->status, ['resolved', 'closed'], true)) {
            $this->recusa(__('Este pedido já está fechado.'));
        }

        $t->update(['status' => 'closed', 'resolved_at' => now()]);

        return response()->json(['message' => __('Pedido fechado.')]);
    }

    /* ─── O quadro de melhorias ───────────────────────────────────────── */

    public function sugestoes(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'ordem' => ['nullable', Rule::in(['populares', 'recentes', 'minhas'])],
            'procura' => ['nullable', 'string', 'max:120'],
        ]);

        $ordem = $filtros['ordem'] ?? 'populares';
        $eu = $request->user()?->id;

        $base = fn () => FeatureRequest::where('tenant_id', activeTenantId());

        $lista = $base()
            ->with(['user:id,name'])
            /*
             * OS VOTOS CONTAM-SE DAS LINHAS.
             *
             * A coluna `votes_count` era somada e subtraída à mão, ao lado da
             * linha do voto, sem transacção: ao primeiro clique repetido
             * deixava de corresponder ao que lá estava. Os apelidos (`votos`,
             * `comentarios`) evitam que a contagem e a coluna com o mesmo nome
             * se atropelem no `ORDER BY`.
             */
            ->withCount(['votes as votos', 'comments as comentarios'])
            ->when($ordem === 'minhas', fn ($q) => $q->where('user_id', $eu))
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->where(fn ($w) => $w->where('title', 'like', $t)->orWhere('description', 'like', $t));
            })
            ->when($ordem === 'populares', fn ($q) => $q->orderByDesc('votos')->latest())
            ->when($ordem !== 'populares', fn ($q) => $q->latest())
            ->get();

        $meus = FeatureRequestVote::whereIn('request_id', $lista->pluck('id'))
            ->where('user_id', $eu)->pluck('request_id')->map(fn ($i) => (int) $i)->all();

        return response()->json([
            'data' => $lista->map(fn (FeatureRequest $s) => [
                'id' => $s->id,
                'titulo' => $s->title,
                'descricao' => $s->description,
                'estado' => $s->status,
                'estado_rotulo' => match ($s->status) {
                    'pending' => __('Por rever'), 'under_review' => __('Em análise'),
                    'planned' => __('Planeada'), 'in_development' => __('Em desenvolvimento'),
                    'completed' => __('Feita'), 'rejected' => __('Recusada'), default => $s->status,
                },
                'votos' => (int) $s->votos,
                'votei' => in_array((int) $s->id, $meus, true),
                'comentarios' => (int) $s->comentarios,
                'imagens' => collect($s->images ?? [])->map(fn ($c) => Storage::url($c))->values(),
                'autor' => $s->user?->name,
                'minha' => (int) $s->user_id === (int) $eu,
                'quando' => $s->created_at?->format('Y-m-d H:i'),
            ])->values(),
            'resumo' => [
                'total' => $base()->count(),
                'minhas' => $base()->where('user_id', $eu)->count(),
                'planeadas' => $base()->whereIn('status', ['planned', 'in_development'])->count(),
                'feitas' => $base()->where('status', 'completed')->count(),
            ],
        ]);
    }

    public function sugerir(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'title' => ['required', 'string', 'min:10', 'max:180'],
            'description' => ['required', 'string', 'min:20'],
            'images' => ['nullable', 'array', 'max:'.self::IMAGENS],
            'images.*' => ['image', 'max:2048'],
        ], [
            'images.max' => __('No máximo :n imagens.', ['n' => self::IMAGENS]),
            'images.*.max' => __('Cada imagem tem de caber em 2 MB.'),
        ], ['title' => __('título'), 'description' => __('descrição')]);

        $tenantId = activeTenantId();
        $userId = $request->user()?->id;

        $sugestao = FeatureRequest::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'title' => $dados['title'],
            'description' => $dados['description'],
            'images' => $this->guardarImagens($request, "features/{$tenantId}/{$userId}/".now()->format('YmdHis')),
            'status' => 'pending',
            'votes_count' => 0,
        ]);

        return response()->json([
            'message' => __('Sugestão enviada. Os colegas já a podem votar.'),
            'data' => ['id' => $sugestao->id],
        ], 201);
    }

    /**
     * O VOTO.
     *
     * DUAS COISAS ESTAVAM MAL. A sugestão era procurada por id e mais nada —
     * bastava escrever o número de outra empresa para lhe votar, e a contagem
     * dela mexia. E o total era somado e subtraído à mão, a par da linha do
     * voto: dois cliques seguidos gravavam dois votos e a coluna deixava de
     * corresponder às linhas. Agora o escopo é da empresa e o total é CONTADO.
     */
    public function votar(Request $request, int $id): JsonResponse
    {
        $sugestao = FeatureRequest::where('tenant_id', activeTenantId())->findOrFail($id);
        $eu = $request->user()?->id;

        $votos = DB::transaction(function () use ($sugestao, $eu) {
            $meu = FeatureRequestVote::where('request_id', $sugestao->id)
                ->where('user_id', $eu)->lockForUpdate()->first();

            if ($meu) {
                // Um clique repetido apagava um voto e deixava o outro: apagam-se
                // TODOS os que a pessoa tenha, que é o que «não votei» quer dizer.
                FeatureRequestVote::where('request_id', $sugestao->id)->where('user_id', $eu)->delete();
            } else {
                FeatureRequestVote::create(['request_id' => $sugestao->id, 'user_id' => $eu]);
            }

            $total = FeatureRequestVote::where('request_id', $sugestao->id)->count();

            $sugestao->update(['votes_count' => $total]);

            return ['total' => $total, 'votei' => ! $meu];
        });

        return response()->json([
            'votos' => $votos['total'],
            'votei' => $votos['votei'],
        ]);
    }

    /** Retirar a própria sugestão — e nunca a de outra pessoa. */
    public function apagarSugestao(Request $request, int $id): JsonResponse
    {
        $sugestao = FeatureRequest::where('tenant_id', activeTenantId())
            ->where('user_id', $request->user()?->id)
            ->findOrFail($id);

        // Contam-se as LINHAS e não a coluna: uma coluna que tenha derivado no
        // passado deixava retirar uma sugestão que os colegas já votaram.
        $votos = FeatureRequestVote::where('request_id', $sugestao->id)->count();

        if ($votos > 0) {
            $this->recusa(__('Já tem :n votos de colegas. Uma sugestão votada não se retira.', ['n' => $votos]));
        }

        $sugestao->delete();

        return response()->json(['message' => __('Sugestão retirada.')]);
    }
}
